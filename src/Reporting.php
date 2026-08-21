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
     * @return array{columns: array<int,string>, rows: array<int, array<string,mixed>>}
     *   columns: col_index => column_path, in original column order
     *   rows: each row has identity fields + one entry per column_path (keyed by column_path)
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
            return ['columns' => [], 'rows' => []];
        }

        $ids = array_column($submissions, 'id');
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $valStmt = $this->db->prepare(
            "SELECT submission_id, col_index, column_path, value FROM submission_values
             WHERE submission_id IN ($placeholders)"
        );
        $valStmt->execute($ids);

        $columns = []; // col_index => column_path
        $valuesBySubmission = []; // submission_id => [column_path => value]
        while ($row = $valStmt->fetch()) {
            $columns[(int)$row['col_index']] = $row['column_path'];
            $valuesBySubmission[$row['submission_id']][$row['column_path']] = $row['value'];
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
            ];
            foreach ($columns as $path) {
                $row[$path] = $valuesBySubmission[$s['id']][$path] ?? '';
            }
            $rows[] = $row;
        }

        return ['columns' => $columns, 'rows' => $rows];
    }
}
