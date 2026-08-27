<?php

/**
 * Rebuilds a consolidated, wide (pivoted) table for one form+sheet — same shape as the
 * original Excel template — from the generic submissions / submission_values storage.
 */
class Reporting
{
    public function __construct(private PDO $db)
    {
    }

    /**
     * Look up สังกัด/หน่วยงาน (area_name), อำเภอ, ตำบล from schools_master (ทำเนียบโรงเรียน) for
     * every distinct (academic_year, school_code) pair actually present among $submissions, so
     * pivot()/tidyRows() can prefer the roster's values over whatever an agency typed into the
     * survey file. Only rows with a non-blank school_code participate — sheets that don't have a
     * school_code identity column (forms 1, 13, 14 — see forms/registry.php) are left untouched.
     * Wrapped in try/catch like every other schools_master query (migration 003 may not be applied
     * yet on some servers — degrade to "no overrides" rather than break the page).
     *
     * @param array<int,array<string,mixed>> $submissions rows from the `submissions` table
     * @return array<string,array{amphoe:?string,tambon:?string,area_name:?string}> keyed "{academic_year}|{school_code}"
     */
    private function schoolMasterOverrides(array $submissions): array
    {
        $codesByYear = [];
        foreach ($submissions as $s) {
            $code = $s['school_code'] ?? null;
            if ($code === null || $code === '') {
                continue;
            }
            $codesByYear[(int)$s['academic_year']][$code] = true;
        }
        if (!$codesByYear) {
            return [];
        }

        $overrides = [];
        try {
            foreach ($codesByYear as $year => $codes) {
                $codeList = array_keys($codes);
                $placeholders = implode(',', array_fill(0, count($codeList), '?'));
                $stmt = $this->db->prepare(
                    "SELECT school_code, amphoe, tambon, area_name FROM schools_master
                     WHERE academic_year = ? AND school_code IN ($placeholders)"
                );
                $stmt->execute(array_merge([$year], $codeList));
                while ($row = $stmt->fetch()) {
                    $overrides["{$year}|{$row['school_code']}"] = $row;
                }
            }
        } catch (Throwable $e) {
            return []; // ยังไม่ได้รัน migration 003 บนเซิร์ฟเวอร์นี้ — ไม่มีทำเนียบให้ใช้ ข้ามไปเงียบ ๆ
        }
        return $overrides;
    }

    /**
     * @return array{columns: array<int,string>, extra_identity_fields: string[], rows: array<int, array<string,mixed>>, totals: array<string,string>}
     *   columns: col_index => column_path, in original column order
     *   extra_identity_fields: names of any non-standard identity fields this sheet uses (e.g.
     *     "age_group", "center_type" — see forms/registry.php identity_fields), in first-seen order
     *   rows: each row has identity fields (standard + extra) + one entry per column_path
     *   totals: column_path => sum of all numeric values in that column (blank if the column has no numeric values)
     */
    public function pivot(string $formKey, string $sheetName, ?int $academicYear = null): array
    {
        $sql = 'SELECT id, row_seq, seq_no, school_code, agency_name, school_name, amphoe, tambon, extra_identity, academic_year
                FROM submissions
                WHERE form_key = :fk AND sheet_name = :sn';
        $params = ['fk' => $formKey, 'sn' => $sheetName];
        if ($academicYear !== null) {
            $sql .= ' AND academic_year = :yr';
            $params['yr'] = $academicYear;
        }
        $sql .= ' ORDER BY agency_name, school_name, id';
        $subStmt = $this->db->prepare($sql);
        $subStmt->execute($params);
        $submissions = $subStmt->fetchAll();

        if (!$submissions) {
            return ['columns' => [], 'extra_identity_fields' => [], 'rows' => [], 'totals' => []];
        }

        $ids = array_column($submissions, 'id');
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $valStmt = $this->db->prepare(
            "SELECT submission_id, col_index, column_path, value, needs_review FROM submission_values
             WHERE submission_id IN ($placeholders)"
        );
        $valStmt->execute($ids);

        $columns = []; // col_index => column_path
        $valuesBySubmission = []; // submission_id => [column_path => value]
        $reviewFlags = [];        // submission_id => [column_path => bool]
        while ($row = $valStmt->fetch()) {
            $columns[(int)$row['col_index']] = $row['column_path'];
            $valuesBySubmission[$row['submission_id']][$row['column_path']] = $row['value'];
            $reviewFlags[$row['submission_id']][$row['column_path']] = (bool)$row['needs_review'];
        }
        ksort($columns);

        $masterOverrides = $this->schoolMasterOverrides($submissions);

        $extraIdentityFields = [];
        $rows = [];
        $seqNo = 0;
        foreach ($submissions as $s) {
            $extra = $s['extra_identity'] ? json_decode($s['extra_identity'], true) : [];
            foreach (array_keys($extra) as $field) {
                if (!in_array($field, $extraIdentityFields, true)) {
                    $extraIdentityFields[] = $field;
                }
            }

            // "ลำดับที่" ที่กรอกมาในไฟล์อัปโหลดไม่น่าเชื่อถือ (พิมพ์เอง ไม่ต่อเนื่อง/ซ้ำได้ระหว่าง
            // หลายไฟล์ที่รวมกัน) — เรียงเลขใหม่เองตามลำดับที่แสดงผลจริงเสมอ ไม่ใช้ค่าที่อัปโหลดมา
            $seqNo++;
            $master = $masterOverrides[$s['academic_year'] . '|' . $s['school_code']] ?? null;
            $row = [
                'id'            => $s['id'],
                'seq_no'        => $seqNo,
                'school_code'   => $s['school_code'],
                // สังกัด/หน่วยงาน, อำเภอ, ตำบล ที่กรอกมาในไฟล์สำรวจพิมพ์เองได้คลาดเคลื่อน — ถ้าจับคู่
                // รหัสสถานศึกษากับทำเนียบโรงเรียน (schools_master) ของปีเดียวกันได้ ให้ใช้ค่าจาก
                // ทำเนียบแทนเสมอ (ไม่งั้น fallback เป็นค่าที่อัปโหลดมาตามเดิม)
                'agency_name'   => ($master['area_name'] ?? '') !== '' ? $master['area_name'] : $s['agency_name'],
                'school_name'   => $s['school_name'],
                'amphoe'        => ($master['amphoe'] ?? '') !== '' ? $master['amphoe'] : $s['amphoe'],
                'tambon'        => ($master['tambon'] ?? '') !== '' ? $master['tambon'] : $s['tambon'],
                'academic_year' => $s['academic_year'],
                '_needs_review' => [],
            ];
            foreach ($extra as $field => $val) {
                $row[$field] = $val;
            }
            foreach ($columns as $path) {
                $row[$path] = $valuesBySubmission[$s['id']][$path] ?? '';
                if ($reviewFlags[$s['id']][$path] ?? false) {
                    $row['_needs_review'][$path] = true;
                }
            }
            $rows[] = $row;
        }

        // แถวรวม: บวกเฉพาะคอลัมน์ที่มีค่าเป็นตัวเลขอยู่จริง (คอลัมน์ข้อความ เช่น ชื่อผู้บริหาร/ขนาดสถานศึกษา จะเว้นว่างไว้)
        $totals = [];
        foreach ($columns as $path) {
            $sum = 0.0;
            $hasNumeric = false;
            foreach ($rows as $row) {
                $v = $row[$path] ?? '';
                if ($v !== '' && is_numeric($v)) {
                    $sum += (float)$v;
                    $hasNumeric = true;
                }
            }
            if (!$hasNumeric) {
                $totals[$path] = '';
                continue;
            }
            $totals[$path] = (fmod($sum, 1.0) === 0.0) ? (string)(int)$sum : (string)$sum;
        }

        return ['columns' => $columns, 'extra_identity_fields' => $extraIdentityFields, 'rows' => $rows, 'totals' => $totals];
    }

    /**
     * Long/"tidy" format for Excel PivotTable: one row per data point instead of one row
     * per submission, with every header level joined back into a single readable label
     * (e.g. "ประถมศึกษาปีที่ 1") instead of split across several columns — the source
     * templates' header rows don't split into clean, independently-meaningful dimensions
     * (a "ชั้นปี" of 1 means something different for ประถม vs อนุปริญญา vs ปริญญาตรี), so a
     * single combined label is what's actually usable as a PivotTable row field.
     *
     * @param string $valueLabel column header for the combined label (e.g. "ชั้นปี")
     * @param string|null $splitLastLabel when given, the last header level (e.g. "ชาย"/"หญิง") is
     *                     pulled out into its own column with this name instead of being joined
     *                     into $valueLabel — use for sheets that end in a genuinely independent
     *                     dimension like gender, which reads better as its own PivotTable field.
     * @return array{value_label: string, split_label: ?string, extra_identity_fields: string[], rows: array<int, array<string,mixed>>}
     */
    public function tidyRows(string $formKey, string $sheetName, string $valueLabel = 'รายการ', ?string $splitLastLabel = null, ?int $academicYear = null): array
    {
        $sql = 'SELECT id, seq_no, school_code, agency_name, school_name, amphoe, tambon, extra_identity, academic_year
                FROM submissions
                WHERE form_key = :fk AND sheet_name = :sn';
        $params = ['fk' => $formKey, 'sn' => $sheetName];
        if ($academicYear !== null) {
            $sql .= ' AND academic_year = :yr';
            $params['yr'] = $academicYear;
        }
        $sql .= ' ORDER BY agency_name, school_name, id';
        $subStmt = $this->db->prepare($sql);
        $subStmt->execute($params);
        $submissions = $subStmt->fetchAll();

        if (!$submissions) {
            return ['value_label' => $valueLabel, 'split_label' => $splitLastLabel, 'extra_identity_fields' => [], 'rows' => []];
        }
        $masterOverrides = $this->schoolMasterOverrides($submissions);

        $byId = [];
        $extraById = [];
        $seqNoById = [];
        $extraIdentityFields = [];
        $seqNo = 0;
        foreach ($submissions as $s) {
            $master = $masterOverrides[$s['academic_year'] . '|' . $s['school_code']] ?? null;
            if (($master['area_name'] ?? '') !== '') {
                $s['agency_name'] = $master['area_name'];
            }
            if (($master['amphoe'] ?? '') !== '') {
                $s['amphoe'] = $master['amphoe'];
            }
            if (($master['tambon'] ?? '') !== '') {
                $s['tambon'] = $master['tambon'];
            }
            $byId[$s['id']] = $s;
            // เรียง "ลำดับที่" ใหม่เองตามลำดับการแสดงผลจริง เหมือน pivot() — ไม่ใช้ค่าที่อัปโหลดมา
            // (1 submission ออกได้หลายแถวในตารางแบบยาวนี้ ทุกแถวของ submission เดียวกันใช้เลขเดียวกัน)
            $seqNoById[$s['id']] = ++$seqNo;
            $extra = $s['extra_identity'] ? json_decode($s['extra_identity'], true) : [];
            $extraById[$s['id']] = $extra;
            foreach (array_keys($extra) as $field) {
                if (!in_array($field, $extraIdentityFields, true)) {
                    $extraIdentityFields[] = $field;
                }
            }
        }

        $ids = array_keys($byId);
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $valStmt = $this->db->prepare(
            "SELECT submission_id, col_index, column_path, value, needs_review FROM submission_values
             WHERE submission_id IN ($placeholders)
             ORDER BY submission_id, col_index"
        );
        $valStmt->execute($ids);
        $values = $valStmt->fetchAll();

        // Split every column_path into its real header levels: a part that starts with "/" is a
        // visual continuation of the cell above it (e.g. a diagonally split header shows
        // "อนุบาล 2 (สช.)" then "/อนุบาล 1" in the row below), not a genuine new level, so it gets
        // glued back onto the previous part instead of becoming one.
        $splitPaths = [];
        foreach ($values as $v) {
            if (isset($splitPaths[$v['column_path']])) {
                continue;
            }
            $raw = array_map('trim', explode(' / ', $v['column_path']));
            $parts = [];
            foreach ($raw as $part) {
                if ($part !== '' && $part[0] === '/' && $parts) {
                    $parts[count($parts) - 1] .= ' ' . $part;
                } else {
                    $parts[] = $part;
                }
            }
            $splitPaths[$v['column_path']] = $parts;
        }

        // A level that's identical across every single column in the sheet carries no
        // distinguishing information (e.g. every column in form 1 starts with the constant
        // "จำนวนบุคลากรในหน่วยงาน (คน)") — drop those constant leading levels from the label.
        $allParts = array_values($splitPaths);
        $dropLevels = 0;
        if ($allParts) {
            $shortest = min(array_map('count', $allParts));
            for ($i = 0; $i < $shortest; $i++) {
                $first = $allParts[0][$i];
                $constant = true;
                foreach ($allParts as $parts) {
                    if ($parts[$i] !== $first) {
                        $constant = false;
                        break;
                    }
                }
                if (!$constant) {
                    break;
                }
                $dropLevels++;
            }
        }

        $labelByPath = [];
        $splitValueByPath = [];
        foreach ($splitPaths as $path => $parts) {
            $kept = array_slice($parts, $dropLevels);
            if (!$kept) {
                $kept = $parts;
            }
            if ($splitLastLabel !== null && count($kept) >= 2) {
                $splitValueByPath[$path] = array_pop($kept);
            } else {
                $splitValueByPath[$path] = '';
            }
            $labelByPath[$path] = $this->joinLabel($kept);
        }

        $rows = [];
        foreach ($values as $v) {
            if ($v['value'] === null || $v['value'] === '') {
                continue;
            }
            $s = $byId[$v['submission_id']];
            $row = [
                'seq_no'        => $seqNoById[$v['submission_id']],
                'school_code'   => $s['school_code'],
                'agency_name'   => $s['agency_name'],
                'school_name'   => $s['school_name'],
                'amphoe'        => $s['amphoe'],
                'tambon'        => $s['tambon'],
                'academic_year' => $s['academic_year'],
            ];
            foreach ($extraById[$v['submission_id']] as $field => $val) {
                $row[$field] = $val;
            }
            $row[$valueLabel] = $labelByPath[$v['column_path']];
            if ($splitLastLabel !== null) {
                $row[$splitLastLabel] = $splitValueByPath[$v['column_path']];
            }
            $row['ค่า'] = $v['value'];
            $row['ต้องตรวจสอบ'] = $v['needs_review'] ? 'ใช่' : '';
            $rows[] = $row;
        }

        return ['value_label' => $valueLabel, 'split_label' => $splitLastLabel, 'extra_identity_fields' => $extraIdentityFields, 'rows' => $rows];
    }

    /**
     * Join header level parts into one readable label with a single space between each
     * (e.g. "ประถมศึกษา" + "ปีที่ 1" -> "ประถมศึกษา ปีที่ 1"). A plain space always reads
     * correctly no matter how the sheet's header happens to be split across rows — unlike
     * concatenating with no separator, which reads fine for "ปีที่" + "1" but jams unrelated
     * words together for sheets whose levels are full phrases (e.g. a reason description
     * split across two header rows, as in form 7).
     *
     * @param string[] $parts
     */
    private function joinLabel(array $parts): string
    {
        return implode(' ', array_filter($parts, fn($p) => $p !== ''));
    }
}
