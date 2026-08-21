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
     * @return array{columns: array<int,string>, rows: array<int, array<string,mixed>>, totals: array<string,string>}
     *   columns: col_index => column_path, in original column order
     *   rows: each row has identity fields + one entry per column_path (keyed by column_path)
     *   totals: column_path => sum of all numeric values in that column (blank if the column has no numeric values)
     */
    public function pivot(string $formKey, string $sheetName): array
    {
        $subStmt = $this->db->prepare(
            'SELECT id, row_seq, seq_no, school_code, agency_name, school_name, amphoe, tambon, extra_identity
             FROM submissions
             WHERE form_key = :fk AND sheet_name = :sn
             ORDER BY agency_name, school_name, id'
        );
        $subStmt->execute(['fk' => $formKey, 'sn' => $sheetName]);
        $submissions = $subStmt->fetchAll();

        if (!$submissions) {
            return ['columns' => [], 'rows' => [], 'totals' => []];
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

        $rows = [];
        foreach ($submissions as $s) {
            $row = [
                'id'          => $s['id'],
                'seq_no'      => $s['seq_no'],
                'school_code' => $s['school_code'],
                'agency_name' => $s['agency_name'],
                'school_name' => $s['school_name'],
                'amphoe'      => $s['amphoe'],
                'tambon'      => $s['tambon'],
                '_needs_review' => [],
            ];
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

        return ['columns' => $columns, 'rows' => $rows, 'totals' => $totals];
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
     * @return array{value_label: string, split_label: ?string, rows: array<int, array<string,mixed>>}
     */
    public function tidyRows(string $formKey, string $sheetName, string $valueLabel = 'รายการ', ?string $splitLastLabel = null): array
    {
        $subStmt = $this->db->prepare(
            'SELECT id, seq_no, school_code, agency_name, school_name, amphoe, tambon
             FROM submissions
             WHERE form_key = :fk AND sheet_name = :sn
             ORDER BY agency_name, school_name, id'
        );
        $subStmt->execute(['fk' => $formKey, 'sn' => $sheetName]);
        $submissions = $subStmt->fetchAll();

        if (!$submissions) {
            return ['value_label' => $valueLabel, 'split_label' => $splitLastLabel, 'rows' => []];
        }
        $byId = [];
        foreach ($submissions as $s) {
            $byId[$s['id']] = $s;
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
                'seq_no'      => $s['seq_no'],
                'school_code' => $s['school_code'],
                'agency_name' => $s['agency_name'],
                'school_name' => $s['school_name'],
                'amphoe'      => $s['amphoe'],
                'tambon'      => $s['tambon'],
                $valueLabel   => $labelByPath[$v['column_path']],
            ];
            if ($splitLastLabel !== null) {
                $row[$splitLastLabel] = $splitValueByPath[$v['column_path']];
            }
            $row['ค่า'] = $v['value'];
            $row['ต้องตรวจสอบ'] = $v['needs_review'] ? 'ใช่' : '';
            $rows[] = $row;
        }

        return ['value_label' => $valueLabel, 'split_label' => $splitLastLabel, 'rows' => $rows];
    }

    /**
     * Join header level parts into one readable label. A bare number (e.g. "1") is joined onto
     * the previous part with a space (so "ปีที่" + "1" -> "ปีที่ 1"); anything else is
     * concatenated directly, matching how these level names are conventionally written together
     * in Thai (e.g. "ประถมศึกษา" + "ปีที่ 1" -> "ประถมศึกษาปีที่ 1").
     *
     * @param string[] $parts
     */
    private function joinLabel(array $parts): string
    {
        $label = '';
        foreach ($parts as $part) {
            if ($part === '') {
                continue;
            }
            if ($label !== '' && ctype_digit($part)) {
                $label .= ' ' . $part;
            } else {
                $label .= $part;
            }
        }
        return $label;
    }
}
