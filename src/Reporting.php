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
     * per submission. The joined column_path (e.g. "ข้าราชการ / ชาย") is split back into
     * separate level columns so each header level becomes its own draggable PivotTable field.
     *
     * @param string[] $levelLabels optional names for the split level columns, in order
     * @return array{level_labels: string[], rows: array<int, array<string,mixed>>}
     */
    public function tidyRows(string $formKey, string $sheetName, array $levelLabels = []): array
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
            return ['level_labels' => [], 'rows' => []];
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

        // First pass: split every column_path into its real header levels, then line them up
        // into a fixed number of columns. Two wrinkles in the source templates make this more
        // than a plain explode():
        //  1. Some header cells are visual continuations of the cell above (e.g. a diagonally
        //     split header shows "อนุบาล 2 (สช.)" then "/อนุบาล 1" in the row below) — these were
        //     stored as their own part but start with "/", which marks them as a fragment rather
        //     than a real new level, so they get glued back onto the previous part.
        //  2. Different column groups within the same sheet don't always have the same number of
        //     real levels (e.g. "ประถมศึกษา / ปีที่ 1" has 2, "มัธยมศึกษาตอนต้น / มัธยมศึกษา / ปี 1"
        //     has 3) — left-aligning them would shove unrelated things into the same column. Instead
        //     the first part always goes in the first column and the last part always goes in the
        //     last column; anything in between fills the middle columns in order, so the same
        //     column always holds the same kind of thing regardless of how deep that group's header is.
        $splitPaths = [];
        $maxDepth = 1;
        foreach ($values as $v) {
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
            $maxDepth = max($maxDepth, count($parts));
        }
        if ($levelLabels) {
            $maxDepth = max($maxDepth, count($levelLabels));
        }

        $labels = [];
        for ($i = 0; $i < $maxDepth; $i++) {
            $labels[] = $levelLabels[$i] ?? ('ระดับที่ ' . ($i + 1));
        }

        $rows = [];
        foreach ($values as $v) {
            if ($v['value'] === null || $v['value'] === '') {
                continue;
            }
            $s = $byId[$v['submission_id']];
            $aligned = $this->alignLevels($splitPaths[$v['column_path']], $maxDepth);
            $row = [
                'seq_no'      => $s['seq_no'],
                'school_code' => $s['school_code'],
                'agency_name' => $s['agency_name'],
                'school_name' => $s['school_name'],
                'amphoe'      => $s['amphoe'],
                'tambon'      => $s['tambon'],
            ];
            for ($i = 0; $i < $maxDepth; $i++) {
                $row[$labels[$i]] = $aligned[$i] ?? '';
            }
            $row['ค่า'] = $v['value'];
            $row['ต้องตรวจสอบ'] = $v['needs_review'] ? 'ใช่' : '';
            $rows[] = $row;
        }

        return ['level_labels' => $labels, 'rows' => $rows];
    }

    /**
     * Place a column's header parts into $width fixed slots: first part -> first slot,
     * last part -> last slot, everything else fills the middle slots in order (overflow,
     * if any, gets joined into the last middle slot). Missing slots are left blank.
     *
     * @param string[] $parts
     * @return string[]
     */
    private function alignLevels(array $parts, int $width): array
    {
        $n = count($parts);
        $out = array_fill(0, $width, '');
        if ($n === 0) {
            return $out;
        }
        if ($n === 1 || $width === 1) {
            $out[0] = $parts[0];
            return $out;
        }

        $out[0] = $parts[0];
        $out[$width - 1] = $parts[$n - 1];

        $middleParts = array_slice($parts, 1, $n - 2);
        $middleSlots = $width - 2;
        if ($middleSlots <= 0 || !$middleParts) {
            return $out;
        }
        if (count($middleParts) <= $middleSlots) {
            foreach ($middleParts as $i => $p) {
                $out[1 + $i] = $p;
            }
        } else {
            // more middle parts than slots: fill slots 1:1, dump the remainder into the last one
            for ($i = 0; $i < $middleSlots - 1; $i++) {
                $out[1 + $i] = $middleParts[$i];
            }
            $out[$middleSlots] = implode(' / ', array_slice($middleParts, $middleSlots - 1));
        }
        return $out;
    }
}
