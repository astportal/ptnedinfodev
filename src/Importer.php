<?php

require_once __DIR__ . '/XlsxReader.php';

/**
 * Generic importer: given a form definition (see forms/registry.php) and an uploaded
 * .xlsx file, parses every configured sheet and stores the rows into submissions /
 * submission_values. Works for any double/triple header table without per-column mapping.
 */
class Importer
{
    /** @var array<string,true>|null null = not loaded yet */
    private ?array $knownSchoolCodes = null;

    public function __construct(private PDO $db)
    {
    }

    /**
     * Reference school codes uploaded via schools_master.php, cached for the lifetime of this
     * Importer instance. Returns an empty array (validation effectively off) if the admin hasn't
     * uploaded a roster yet — checking against an empty roster would flag every single code as
     * unknown, which is worse than not checking at all.
     */
    private function knownSchoolCodes(): array
    {
        if ($this->knownSchoolCodes === null) {
            try {
                $codes = $this->db->query('SELECT school_code FROM schools_master')->fetchAll(PDO::FETCH_COLUMN);
                $this->knownSchoolCodes = array_fill_keys($codes, true);
            } catch (Throwable $e) {
                // schools_master table not migrated in yet — treat as "validation not set up".
                $this->knownSchoolCodes = [];
            }
        }
        return $this->knownSchoolCodes;
    }

    /**
     * @return array{uploads: array, total_rows: int}
     */
    public function importFile(string $formKey, array $formDef, string $filePath, string $originalFilename, string $storedFilename, ?int $uploadedBy): array
    {
        $reader = new XlsxReader($filePath);
        $results = [];
        $totalRows = 0;

        // The original blank template for a sheet, used to check the uploaded file's column
        // structure hasn't drifted (columns inserted/deleted/reordered) before importing anything.
        // Normally every sheet of a form comes from the same source_file (declared once on the
        // form), but a merged form (e.g. 14ก+14ข) has sheets from two different original files,
        // so source_file can also be declared per-sheet; readers are cached by filename either way.
        $referenceReaders = [];

        foreach ($formDef['sheets'] as $sheetDef) {
            $sheetName = $sheetDef['sheet_name'];
            if (!in_array($sheetName, $reader->sheetNames(), true)) {
                if (!empty($sheetDef['optional'])) {
                    // This sheet belongs to an alternate source file for a merged form (the
                    // uploaded file is expected to contain only one of the alternatives) —
                    // not finding it here is normal, not an error.
                    continue;
                }
                $results[] = [
                    'sheet_name' => $sheetName,
                    'status'     => 'error',
                    'message'    => "ไม่พบชีท \"$sheetName\" ในไฟล์นี้ — ตรวจสอบว่าเป็นไฟล์แบบฟอร์มที่ถูกต้อง",
                    'row_count'  => 0,
                ];
                continue;
            }

            $sourceFile = $sheetDef['source_file'] ?? $formDef['source_file'] ?? null;
            if ($sourceFile !== null && !array_key_exists($sourceFile, $referenceReaders)) {
                $referencePath = __DIR__ . '/../reference_templates/' . $sourceFile;
                if (is_file($referencePath)) {
                    try {
                        $referenceReaders[$sourceFile] = new XlsxReader($referencePath);
                    } catch (Throwable $e) {
                        $referenceReaders[$sourceFile] = null; // don't let a broken reference file block real uploads
                    }
                } else {
                    $referenceReaders[$sourceFile] = null;
                }
            }
            $referenceReader = $sourceFile !== null ? $referenceReaders[$sourceFile] : null;

            try {
                $sheetResult = $this->importSheet($reader, $referenceReader, $formKey, $sheetDef, $originalFilename, $storedFilename, $uploadedBy);
                $rowCount = $sheetResult['rows'];
                $needsReview = $sheetResult['needs_review'];
                $message = "นำเข้าสำเร็จ {$rowCount} แถว";
                if ($needsReview > 0) {
                    $message .= " — พบ {$needsReview} ค่าที่ไม่แน่ใจว่าเป็นตัวเลขหรือไม่ ต้องตรวจสอบ";
                }
                if ($sheetResult['school_code_issues'] > 0) {
                    $message .= " — พบ {$sheetResult['school_code_issues']} แถวที่รหัสสถานศึกษาว่างหรือไม่ตรงกับทำเนียบ ต้องตรวจสอบ";
                }
                if ($sheetResult['hidden_rows'] > 0 || $sheetResult['hidden_cols'] > 0) {
                    $message .= " — ข้าม " . $sheetResult['hidden_rows'] . " แถว และ "
                        . $sheetResult['hidden_cols'] . " คอลัมน์ที่ถูกซ่อนไว้ในไฟล์ ไม่นำเข้าข้อมูล";
                }
                $results[] = [
                    'sheet_name'         => $sheetName,
                    'status'             => 'parsed',
                    'message'            => $message,
                    'row_count'          => $rowCount,
                    'needs_review'       => $needsReview,
                    'school_code_issues' => $sheetResult['school_code_issues'],
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
     * @return array{rows: int, needs_review: int, hidden_rows: int, hidden_cols: int}
     */
    private function importSheet(XlsxReader $reader, ?XlsxReader $referenceReader, string $formKey, array $sheetDef, string $originalFilename, string $storedFilename, ?int $uploadedBy): array
    {
        $sheetName    = $sheetDef['sheet_name'];
        // Normally the same as $sheetName; differs only for a merged form (e.g. 14ก+14ข) where
        // several distinct Excel sheets (from different original files) should land in the same
        // submissions bucket so they're counted/viewed together.
        $storageSheetName = $sheetDef['db_sheet_name'] ?? $sheetName;
        $headerRows   = $sheetDef['header_rows'];
        $skipRows     = $sheetDef['skip_rows'] ?? [$sheetDef['title_row'] ?? 0];
        $identityCols = $sheetDef['identity_cols'];
        $identityFields = $sheetDef['identity_fields'];
        $valueType    = $sheetDef['value_type'] ?? 'text';

        $data = $reader->readGrid($sheetName);
        $grid = $data['grid'];
        $maxCol = $data['maxCol'];
        $maxRow = $data['maxRow'];
        $hiddenRows = $data['hiddenRows'];
        $hiddenCols = $data['hiddenCols'];

        // column_path for every value column beyond identity_cols, built from the full header —
        // hidden columns are included here (structure is compared against the reference template
        // regardless of visibility); which columns get skipped when actually importing DATA is a
        // separate decision made further down, in the value-insert loop.
        $columnPaths = $this->buildColumnPaths($grid, $maxCol, $identityCols, $headerRows, $skipRows);

        if ($referenceReader !== null && in_array($sheetName, $referenceReader->sheetNames(), true)) {
            $this->assertStructureMatches($referenceReader, $sheetName, $identityCols, $headerRows, $skipRows, $columnPaths);
        }

        $hiddenColsSkipped = 0;
        foreach (array_keys($columnPaths) as $c) {
            if (isset($hiddenCols[$c])) {
                $hiddenColsSkipped++;
            }
        }

        // Record this upload first.
        $stmt = $this->db->prepare(
            'INSERT INTO uploads (form_key, sheet_name, original_filename, stored_filename, uploaded_by, status)
             VALUES (:form_key, :sheet_name, :original_filename, :stored_filename, :uploaded_by, :status)'
        );
        $stmt->execute([
            'form_key'          => $formKey,
            'sheet_name'        => $storageSheetName,
            'original_filename' => $originalFilename,
            'stored_filename'   => $storedFilename,
            'uploaded_by'       => $uploadedBy,
            'status'            => 'parsed',
        ]);
        $uploadId = (int)$this->db->lastInsertId();

        $carryFields = $sheetDef['carry_identity_fields'] ?? [];
        $carried = [];

        $rowsImported = 0;
        $needsReviewCount = 0;
        $schoolCodeIssueCount = 0;
        $hiddenRowsSkipped = 0;
        $this->db->beginTransaction();
        try {
            for ($r = $headerRows + 1; $r <= $maxRow; $r++) {
                if (isset($hiddenRows[$r])) {
                    $hiddenRowsSkipped++;
                    continue;
                }
                $rowData = $grid[$r] ?? [];
                if ($this->isNoteMarkerRow($rowData, $identityCols)) {
                    // "หมายเหตุ" marks the start of an explanatory note block below the
                    // table (often spanning many rows of footnotes/instructions, only the
                    // first of which literally starts with "หมายเหตุ") — everything from
                    // here to the end of the sheet is instructions, not data, so stop.
                    break;
                }
                if (!$this->isRealDataRow($rowData, $identityCols)) {
                    continue;
                }

                $identity = [];
                foreach ($identityFields as $i => $fieldName) {
                    $identity[$fieldName] = trim((string)($rowData[$i + 1] ?? ''));
                }

                // Some sheets only write the agency/school name on the first row of a block and
                // leave it blank on the rows below (e.g. one row per age bracket, same entity) —
                // those fields inherit the last non-blank value seen for that column.
                foreach ($carryFields as $field) {
                    if (($identity[$field] ?? '') === '' && isset($carried[$field])) {
                        $identity[$field] = $carried[$field];
                    } elseif (($identity[$field] ?? '') !== '') {
                        $carried[$field] = $identity[$field];
                    }
                }

                $knownFields = ['seq_no', 'school_code', 'agency_name', 'school_name', 'amphoe', 'tambon'];
                $standard = [];
                // Metadata fixed per sheet (not read from any column) — e.g. which of two merged
                // source forms a row came from — goes into extra_identity alongside real columns.
                $extra = $sheetDef['fixed_extra_identity'] ?? [];
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

                $extraJson = $extra ? json_encode($extra, JSON_UNESCAPED_UNICODE) : null;

                // Flag rows whose school_code is blank or not found in the reference roster
                // (schools_master, uploaded via schools_master.php) for manual review — only for
                // sheets that actually have a school_code identity column, and only once the admin
                // has uploaded a roster (see knownSchoolCodes() for why an empty roster skips this).
                $schoolCodeIssue = null;
                if (in_array('school_code', $identityFields, true) && $this->knownSchoolCodes()) {
                    if (($standard['school_code'] ?? null) === null) {
                        $schoolCodeIssue = 'missing';
                    } elseif (!isset($this->knownSchoolCodes()[$standard['school_code']])) {
                        $schoolCodeIssue = 'not_found';
                    }
                }
                if ($schoolCodeIssue !== null) {
                    $schoolCodeIssueCount++;
                }

                // Upsert key: same form+sheet+school_code, or — when there's no school_code —
                // the same agency_name + school_name + extra_identity together (NULL-safe), so
                // multiple rows that share an agency (e.g. several centers under one อปท., or
                // several age-bracket rows for one agency) don't overwrite each other; re-imports
                // still replace the previous submission for that same exact entity.
                $this->deleteExistingSubmission($formKey, $storageSheetName, $standard['school_code'], $standard['agency_name'], $standard['school_name'], $extraJson);

                $ins = $this->db->prepare(
                    'INSERT INTO submissions
                        (upload_id, form_key, sheet_name, row_seq, seq_no, school_code, school_code_issue, agency_name, school_name, amphoe, tambon, extra_identity)
                     VALUES
                        (:upload_id, :form_key, :sheet_name, :row_seq, :seq_no, :school_code, :school_code_issue, :agency_name, :school_name, :amphoe, :tambon, :extra_identity)'
                );
                $ins->execute([
                    'upload_id'         => $uploadId,
                    'form_key'          => $formKey,
                    'sheet_name'        => $storageSheetName,
                    'row_seq'           => $r,
                    'seq_no'            => $standard['seq_no'],
                    'school_code'       => $standard['school_code'],
                    'school_code_issue' => $schoolCodeIssue,
                    'agency_name'       => $standard['agency_name'],
                    'school_name'       => $standard['school_name'],
                    'amphoe'            => $standard['amphoe'],
                    'tambon'            => $standard['tambon'],
                    'extra_identity'    => $extraJson,
                ]);
                $submissionId = (int)$this->db->lastInsertId();

                $valStmt = $this->db->prepare(
                    'INSERT INTO submission_values (submission_id, col_index, column_path, value, needs_review)
                     VALUES (:sid, :ci, :cp, :val, :nr)'
                );
                foreach ($columnPaths as $c => $path) {
                    if (isset($hiddenCols[$c])) {
                        continue; // hidden column — never imported, even though it exists in the sheet
                    }
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

        return [
            'rows'               => $rowsImported,
            'needs_review'       => $needsReviewCount,
            'school_code_issues' => $schoolCodeIssueCount,
            'hidden_rows'        => $hiddenRowsSkipped,
            'hidden_cols'        => $hiddenColsSkipped,
        ];
    }

    /**
     * Join every non-skipped header row's text for each value column (beyond identity_cols) into
     * one column_path, the same way for both the uploaded file and the reference template so the
     * two are directly comparable. A header cell left blank to visually group with its neighbour
     * (with or without an actual Excel merge) inherits the last non-blank text seen in that same
     * header row, but only while every shallower row still matches the previous column too — the
     * moment a shallower row diverges, inheritance stops for the rest of that column.
     *
     * @return array<int,string> col_index => column_path
     */
    private function buildColumnPaths(array $grid, int $maxCol, int $identityCols, int $headerRows, array $skipRows): array
    {
        $columnPaths = [];
        $prevRowTexts = [];
        for ($c = $identityCols + 1; $c <= $maxCol; $c++) {
            $parts = [];
            $prev = null;
            $curRowTexts = [];
            $scopeBroken = false;
            for ($r = 1; $r <= $headerRows; $r++) {
                if (in_array($r, $skipRows, true)) {
                    continue;
                }
                $text = trim((string)($grid[$r][$c] ?? ''));
                if ($text === '' && !$scopeBroken) {
                    $text = $prevRowTexts[$r] ?? '';
                }
                if (($prevRowTexts[$r] ?? null) !== $text) {
                    $scopeBroken = true;
                }
                $curRowTexts[$r] = $text;
                if ($text === '' || $text === $prev) {
                    continue;
                }
                $parts[] = $text;
                $prev = $text;
            }
            $prevRowTexts = $curRowTexts;
            $columnPaths[$c] = $parts ? implode(' / ', $parts) : "คอลัมน์ที่ " . $c;
        }
        return $columnPaths;
    }

    /**
     * Compare the uploaded file's column structure for this sheet against the original blank
     * template — same column count and same header text per column — so a column inserted,
     * deleted, or reordered before the agency sent the file back gets caught with a clear error
     * instead of silently landing data in the wrong column.
     */
    private function assertStructureMatches(XlsxReader $referenceReader, string $sheetName, int $identityCols, int $headerRows, array $skipRows, array $uploadedPaths): void
    {
        $refData = $referenceReader->readGrid($sheetName);
        $refPaths = $this->buildColumnPaths($refData['grid'], $refData['maxCol'], $identityCols, $headerRows, $skipRows);

        $normalize = static fn(string $s): string => preg_replace('/\s+/u', ' ', trim($s));

        $refCount = count($refPaths);
        $uploadedCount = count($uploadedPaths);
        if ($refCount !== $uploadedCount) {
            throw new RuntimeException(
                "โครงสร้างคอลัมน์ในชีท \"$sheetName\" ไม่ตรงกับแบบฟอร์มต้นฉบับ: ต้นฉบับมี $refCount คอลัมน์ "
                . "แต่ไฟล์นี้มี $uploadedCount คอลัมน์ — อาจมีการเพิ่ม/ลบ/แทรกคอลัมน์ กรุณาแก้ไขให้ตรงกับแบบฟอร์มต้นฉบับแล้วอัปโหลดใหม่"
            );
        }

        $refList = array_values($refPaths);
        $uploadedList = array_values($uploadedPaths);
        foreach ($refList as $i => $expected) {
            $actual = $uploadedList[$i] ?? '';
            if ($normalize($expected) !== $normalize($actual)) {
                throw new RuntimeException(
                    "โครงสร้างคอลัมน์ในชีท \"$sheetName\" ไม่ตรงกับแบบฟอร์มต้นฉบับ: คอลัมน์ที่ " . ($identityCols + $i + 1)
                    . " ต้นฉบับคือ \"$expected\" แต่ไฟล์นี้เป็น \"$actual\" — กรุณาแก้ไขให้ตรงกับแบบฟอร์มต้นฉบับแล้วอัปโหลดใหม่"
                );
            }
        }
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
    public static function classifyValue(string $raw): array
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

    private function deleteExistingSubmission(string $formKey, string $sheetName, ?string $schoolCode, ?string $agencyName, ?string $schoolName, ?string $extraJson): void
    {
        if ($schoolCode) {
            $del = $this->db->prepare(
                'DELETE FROM submissions WHERE form_key = :fk AND sheet_name = :sn AND school_code = :sc'
            );
            $del->execute(['fk' => $formKey, 'sn' => $sheetName, 'sc' => $schoolCode]);
        } elseif ($agencyName) {
            // NULL-safe equality (<=>) so this still matches correctly when school_name/extra_identity
            // are legitimately absent for this sheet, without falsely matching a different sub-row
            // (different school under the same agency, or a different extra_identity combination)
            // that just happens to share the same agency_name.
            $del = $this->db->prepare(
                'DELETE FROM submissions WHERE form_key = :fk AND sheet_name = :sn AND school_code IS NULL
                 AND agency_name <=> :an AND school_name <=> :snm AND extra_identity <=> :ei'
            );
            $del->execute(['fk' => $formKey, 'sn' => $sheetName, 'an' => $agencyName, 'snm' => $schoolName, 'ei' => $extraJson]);
        }
    }

    /**
     * True when this row is the start of a "หมายเหตุ" footnote block below the table.
     * These blocks commonly span many rows of explanation, only the first of which
     * literally starts with "หมายเหตุ" — the caller stops importing entirely once this
     * is hit, rather than trying to recognize every continuation line individually.
     */
    private function isNoteMarkerRow(array $rowData, int $identityCols): bool
    {
        for ($i = 1; $i <= $identityCols; $i++) {
            $text = trim((string)($rowData[$i] ?? ''));
            if ($text !== '' && str_starts_with($text, 'หมายเหตุ')) {
                return true;
            }
        }
        return false;
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
                || str_starts_with($text, '(ตัวอย่าง)')) {
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
