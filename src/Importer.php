<?php

require_once __DIR__ . '/XlsxReader.php';

/**
 * Generic importer: given a form definition (see forms/registry.php) and an uploaded
 * .xlsx file, parses every configured sheet and stores the rows into submissions /
 * submission_values. Works for any double/triple header table without per-column mapping.
 */
class Importer
{
    public function __construct(private PDO $db)
    {
    }

    /**
     * @return array{uploads: array, total_rows: int}
     */
    public function importFile(string $formKey, array $formDef, string $filePath, string $originalFilename, string $storedFilename, ?int $uploadedBy): array
    {
        $reader = new XlsxReader($filePath);
        $results = [];
        $totalRows = 0;

        foreach ($formDef['sheets'] as $sheetDef) {
            $sheetName = $sheetDef['sheet_name'];
            if (!in_array($sheetName, $reader->sheetNames(), true)) {
                $results[] = [
                    'sheet_name' => $sheetName,
                    'status'     => 'error',
                    'message'    => "ไม่พบชีท \"$sheetName\" ในไฟล์นี้ — ตรวจสอบว่าเป็นไฟล์แบบฟอร์มที่ถูกต้อง",
                    'row_count'  => 0,
                ];
                continue;
            }

            try {
                $sheetResult = $this->importSheet($reader, $formKey, $sheetDef, $originalFilename, $storedFilename, $uploadedBy);
                $rowCount = $sheetResult['rows'];
                $needsReview = $sheetResult['needs_review'];
                $message = "นำเข้าสำเร็จ {$rowCount} แถว";
                if ($needsReview > 0) {
                    $message .= " — พบ {$needsReview} ค่าที่ไม่แน่ใจว่าเป็นตัวเลขหรือไม่ ต้องตรวจสอบ";
                }
                $results[] = [
                    'sheet_name'   => $sheetName,
                    'status'       => 'parsed',
                    'message'      => $message,
                    'row_count'    => $rowCount,
                    'needs_review' => $needsReview,
                ];
                $totalRows += $rowCount;
            } catch (Throwable $e) {
                $results[] = [
                    'sheet_name' => $sheetName,
                    'status'     => 'error',
                    'message'    => $e->getMessage(),
                    'row_count'  => 0,
                ];
            }
        }

        return ['uploads' => $results, 'total_rows' => $totalRows];
    }

    /**
     * @return array{rows: int, needs_review: int}
     */
    private function importSheet(XlsxReader $reader, string $formKey, array $sheetDef, string $originalFilename, string $storedFilename, ?int $uploadedBy): array
    {
        $sheetName    = $sheetDef['sheet_name'];
        $headerRows   = $sheetDef['header_rows'];
        $titleRow     = $sheetDef['title_row'] ?? 0;
        $identityCols = $sheetDef['identity_cols'];
        $identityFields = $sheetDef['identity_fields'];
        $valueType    = $sheetDef['value_type'] ?? 'text';

        $data = $reader->readGrid($sheetName);
        $grid = $data['grid'];
        $maxCol = $data['maxCol'];
        $maxRow = $data['maxRow'];

        // Build column_path for every value column beyond identity_cols, by joining
        // the text of every non-title header row (blank/duplicate-adjacent parts skipped).
        $columnPaths = [];
        for ($c = $identityCols + 1; $c <= $maxCol; $c++) {
            $parts = [];
            $prev = null;
            for ($r = 1; $r <= $headerRows; $r++) {
                if ($r === $titleRow) {
                    continue;
                }
                $text = trim((string)($grid[$r][$c] ?? ''));
                if ($text === '' || $text === $prev) {
                    continue;
                }
                $parts[] = $text;
                $prev = $text;
            }
            $columnPaths[$c] = $parts ? implode(' / ', $parts) : "คอลัมน์ที่ " . $c;
        }

        // Record this upload first.
        $stmt = $this->db->prepare(
            'INSERT INTO uploads (form_key, sheet_name, original_filename, stored_filename, uploaded_by, status)
             VALUES (:form_key, :sheet_name, :original_filename, :stored_filename, :uploaded_by, :status)'
        );
        $stmt->execute([
            'form_key'          => $formKey,
            'sheet_name'        => $sheetName,
            'original_filename' => $originalFilename,
            'stored_filename'   => $storedFilename,
            'uploaded_by'       => $uploadedBy,
            'status'            => 'parsed',
        ]);
        $uploadId = (int)$this->db->lastInsertId();

        $rowsImported = 0;
        $needsReviewCount = 0;
        $this->db->beginTransaction();
        try {
            for ($r = $headerRows + 1; $r <= $maxRow; $r++) {
                $rowData = $grid[$r] ?? [];
                if (!$this->isRealDataRow($rowData, $identityCols)) {
                    continue;
                }

                $identity = [];
                foreach ($identityFields as $i => $fieldName) {
                    $identity[$fieldName] = trim((string)($rowData[$i + 1] ?? ''));
                }

                $knownFields = ['seq_no', 'school_code', 'agency_name', 'school_name', 'amphoe', 'tambon'];
                $standard = [];
                $extra = [];
                foreach ($identity as $field => $val) {
                    if (in_array($field, $knownFields, true)) {
                        $standard[$field] = $val !== '' ? $val : null;
                    } else {
                        $extra[$field] = $val;
                    }
                }
                foreach ($knownFields as $kf) {
                    if (!array_key_exists($kf, $standard)) {
                        $standard[$kf] = null;
                    }
                }

                // Upsert key: same form+sheet+school_code (or agency_name when no school_code)
                // re-imports replace the previous submission for that same entity.
                $this->deleteExistingSubmission($formKey, $sheetName, $standard['school_code'], $standard['agency_name']);

                $ins = $this->db->prepare(
                    'INSERT INTO submissions
                        (upload_id, form_key, sheet_name, row_seq, seq_no, school_code, agency_name, school_name, amphoe, tambon, extra_identity)
                     VALUES
                        (:upload_id, :form_key, :sheet_name, :row_seq, :seq_no, :school_code, :agency_name, :school_name, :amphoe, :tambon, :extra_identity)'
                );
                $ins->execute([
                    'upload_id'      => $uploadId,
                    'form_key'       => $formKey,
                    'sheet_name'     => $sheetName,
                    'row_seq'        => $r,
                    'seq_no'         => $standard['seq_no'],
                    'school_code'    => $standard['school_code'],
                    'agency_name'    => $standard['agency_name'],
                    'school_name'    => $standard['school_name'],
                    'amphoe'         => $standard['amphoe'],
                    'tambon'         => $standard['tambon'],
                    'extra_identity' => $extra ? json_encode($extra, JSON_UNESCAPED_UNICODE) : null,
                ]);
                $submissionId = (int)$this->db->lastInsertId();

                $valStmt = $this->db->prepare(
                    'INSERT INTO submission_values (submission_id, col_index, column_path, value, needs_review)
                     VALUES (:sid, :ci, :cp, :val, :nr)'
                );
                foreach ($columnPaths as $c => $path) {
                    $raw = trim((string)($rowData[$c] ?? ''));
                    if ($raw === '') {
                        continue;
                    }
                    if ($valueType === 'numeric') {
                        [$val, $needsReview] = $this->classifyValue($raw);
                    } else {
                        $val = $raw;
                        $needsReview = false;
                    }
                    if ($needsReview) {
                        $needsReviewCount++;
                    }
                    $valStmt->execute(['sid' => $submissionId, 'ci' => $c, 'cp' => $path, 'val' => $val, 'nr' => $needsReview ? 1 : 0]);
                }

                $rowsImported++;
            }

            $upd = $this->db->prepare('UPDATE uploads SET row_count = :rc WHERE id = :id');
            $upd->execute(['rc' => $rowsImported, 'id' => $uploadId]);

            $this->db->commit();
        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }

        return ['rows' => $rowsImported, 'needs_review' => $needsReviewCount];
    }

    /**
     * Interpret a raw cell value expected to be numeric:
     *   "-"           -> 0 (แปลว่าไม่มี/ไม่มีข้อมูลในช่องนั้น)
     *   "/"           -> 1 (แปลว่ามี/เปิดสอน — พบบ่อยในตารางแบบติ๊ก)
     *   ตัวเลขล้วน     -> ใช้ค่านั้นตรง ๆ (ตัด , คั่นหลักพันออกก่อนเทียบ)
     *   อย่างอื่น      -> เก็บค่าดิบไว้ตามที่กรอกมา และตั้งค่า needs_review = true ให้ผู้ดูแลตรวจสอบภายหลัง
     *                     แทนที่จะเดาค่าเอง
     *
     * @return array{0: string, 1: bool} [ค่าที่ใช้บันทึก, ต้องตรวจสอบหรือไม่]
     */
    private function classifyValue(string $raw): array
    {
        if ($raw === '-') {
            return ['0', false];
        }
        if ($raw === '/') {
            return ['1', false];
        }
        $clean = str_replace([',', ' '], '', $raw);
        if (is_numeric($clean)) {
            return [$clean, false];
        }
        return [$raw, true];
    }

    private function deleteExistingSubmission(string $formKey, string $sheetName, ?string $schoolCode, ?string $agencyName): void
    {
        if ($schoolCode) {
            $del = $this->db->prepare(
                'DELETE FROM submissions WHERE form_key = :fk AND sheet_name = :sn AND school_code = :sc'
            );
            $del->execute(['fk' => $formKey, 'sn' => $sheetName, 'sc' => $schoolCode]);
        } elseif ($agencyName) {
            $del = $this->db->prepare(
                'DELETE FROM submissions WHERE form_key = :fk AND sheet_name = :sn AND school_code IS NULL AND agency_name = :an'
            );
            $del->execute(['fk' => $formKey, 'sn' => $sheetName, 'an' => $agencyName]);
        }
    }

    /**
     * Decide whether a row is real filled-in data, or an empty/placeholder/example row
     * left over from the blank template (e.g. "รหัสสถานศึกษา 10 หลัก", "สำนักงาน...").
     */
    private function isRealDataRow(array $rowData, int $identityCols): bool
    {
        $anyContent = false;
        for ($i = 1; $i <= $identityCols; $i++) {
            $text = trim((string)($rowData[$i] ?? ''));
            if ($text === '') {
                continue;
            }
            $anyContent = true;
            // Placeholder hint text left in the blank template always contains an
            // ellipsis or the "10 หลัก" instruction — real submitted data never does.
            if (str_contains($text, '...') || str_contains($text, '10 หลัก')
                || str_starts_with($text, '(ตัวอย่าง)') || str_starts_with($text, 'หมายเหตุ')) {
                return false;
            }
        }
        if (!$anyContent) {
            // also check value columns in case identity is blank but data exists
            foreach ($rowData as $v) {
                if (trim((string)$v) !== '') {
                    $anyContent = true;
                    break;
                }
            }
        }
        return $anyContent;
    }
}
