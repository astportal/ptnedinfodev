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

        // First pass: split every column_path into level columns. When $levelLabels is given,
        // it also caps how many columns to split into — the source template's header rows don't
        // always map cleanly one-to-one onto meaningful fields (e.g. a header row that's really a
        // continuation/footnote of the row above it), so anything past the last named level stays
        // joined together in that final column instead of spilling into an extra, confusing one.
        $targetDepth = $levelLabels ? count($levelLabels) : null;
        $splitPaths = [];
        $maxDepth = 1;
        foreach ($values as $v) {
            $parts = $targetDepth
                ? array_map('trim', explode(' / ', $v['column_path'], $targetDepth))
                : array_map('trim', explode(' / ', $v['column_path']));
            $splitPaths[$v['column_path']] = $parts;
            $maxDepth = max($maxDepth, count($parts));
        }
        if ($targetDepth) {
            $maxDepth = $targetDepth;
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
            $parts = $splitPaths[$v['column_path']];
            $row = [
                'seq_no'      => $s['seq_no'],
                'school_code' => $s['school_code'],
                'agency_name' => $s['agency_name'],
                'school_name' => $s['school_name'],
                'amphoe'      => $s['amphoe'],
                'tambon'      => $s['tambon'],
            ];
            for ($i = 0; $i < $maxDepth; $i++) {
                $row[$labels[$i]] = $parts[$i] ?? '';
            }
            $row['ค่า'] = $v['value'];
            $row['ต้องตรวจสอบ'] = $v['needs_review'] ? 'ใช่' : '';
            $rows[] = $row;
        }

        return ['level_labels' => $labels, 'rows' => $rows];
    }
}
