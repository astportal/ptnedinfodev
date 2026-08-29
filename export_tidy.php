<?php
/**
 * ส่งออกข้อมูลแบบ "เรียงยาว" (long/tidy format) — 1 แถวต่อ 1 ค่าข้อมูล แยกแต่ละระดับ header
 * ออกเป็นคนละคอลัมน์ (เช่น ประเภทบุคลากร, เพศ) เพื่อนำไปสร้าง PivotTable ใน Excel ได้ทันที
 * (ต่างจาก export.php ที่ส่งออกตารางกว้างรูปแบบเดียวกับไฟล์ต้นฉบับ)
 */
require_once __DIR__ . '/bootstrap.php';
Auth::requireLogin();

$db = Db::conn();
$forms = form_registry();
$formKey = $_GET['form'] ?? '';
$sheetName = $_GET['sheet'] ?? '';
// sheet=__all__ combines every sheet of this form into one file — see export.php for the same idea.
$allSheets = $sheetName === '__all__';

if (!isset($forms[$formKey])) {
    die('ไม่พบฟอร์มที่ระบุ');
}

$selectedYear = $_GET['year'] ?? 'all';
$showAllYears = $selectedYear === 'all';
$selectedYearInt = $showAllYears ? null : (int)$selectedYear;

$reporting = new Reporting($db);
$identityLabels = [
    'seq_no' => 'ลำดับที่', 'school_code' => 'รหัสสถานศึกษา', 'agency_name' => 'สังกัด/หน่วยงาน',
    'school_name' => 'ชื่อสถานศึกษา', 'amphoe' => 'อำเภอ', 'tambon' => 'ตำบล', 'department' => 'ต้นสังกัด',
];

// tidiesBySheet: storage sheet_name => tidyRows() result — single-sheet mode has exactly one
// entry. Different sheets can use different value_label/split_last text (e.g. "ตำแหน่ง" vs
// "ระดับชั้น") — combined mode can't show every sheet's own header, so it uses fixed generic
// column names ("รายการ" / "แยกย่อย") instead, wide enough to hold whichever sheet a row is from.
$tidiesBySheet = [];
$extraFields = [];
$anySplit = false;
if ($allSheets) {
    $seen = [];
    foreach ($forms[$formKey]['sheets'] as $sd) {
        $sn = $sd['db_sheet_name'] ?? $sd['sheet_name'];
        if (isset($seen[$sn])) {
            continue;
        }
        $seen[$sn] = true;
        $tidiesBySheet[$sn] = $reporting->tidyRows($formKey, $sn, $sd['value_label'] ?? 'รายการ', $sd['value_split_last'] ?? null, $selectedYearInt);
    }
} else {
    $sheetDef = null;
    foreach ($forms[$formKey]['sheets'] as $sd) {
        if (($sd['db_sheet_name'] ?? $sd['sheet_name']) === $sheetName) {
            $sheetDef = $sd;
            break;
        }
    }
    if (!$sheetDef) {
        die('ไม่พบชีทที่ระบุ');
    }
    $tidiesBySheet[$sheetName] = $reporting->tidyRows($formKey, $sheetName, $sheetDef['value_label'] ?? 'รายการ', $sheetDef['value_split_last'] ?? null, $selectedYearInt);
}
foreach ($tidiesBySheet as $t) {
    if ($t['split_label']) {
        $anySplit = true;
    }
    foreach ($t['extra_identity_fields'] as $f) {
        if (!in_array($f, $extraFields, true)) {
            $extraFields[] = $f;
        }
    }
}
// Column headers: when every included sheet happens to use the same value_label/split_label text
// (true for single-sheet mode, and often true for combined mode too — e.g. form 12's 13 sheets all
// default to "รายการ"), show that actual text instead of a generic fallback.
$valueLabels = array_values(array_unique(array_map(static fn($t) => $t['value_label'], $tidiesBySheet)));
$valueColHeader = count($valueLabels) === 1 ? $valueLabels[0] : 'รายการ';
$splitLabels = array_values(array_unique(array_filter(array_map(static fn($t) => $t['split_label'], $tidiesBySheet))));
$splitColHeader = count($splitLabels) === 1 ? $splitLabels[0] : 'แยกย่อย';

$filenameBase = $allSheets
    ? ($forms[$formKey]['form_label'] ?? $formKey) . '_รวมทุกชีท_pivot'
    : $sheetName . '_pivot';
$filename = preg_replace('/[^A-Za-z0-9ก-๙_\-]+/u', '_', $filenameBase) . '.csv';

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

$out = fopen('php://output', 'w');
fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM ให้ Excel เปิดภาษาไทยถูกต้อง

$valueCols = [$valueColHeader];
if ($anySplit) {
    $valueCols[] = $splitColHeader;
}
$extraLabels = array_map('extra_identity_label', $extraFields);
$yearHeader = $showAllYears ? ['ปีการศึกษา'] : [];
$sheetHeader = $allSheets ? ['ชีท'] : [];
$header = array_merge($yearHeader, $sheetHeader, array_values($identityLabels), $extraLabels, $valueCols, ['ค่า', 'ต้องตรวจสอบ']);
fputcsv($out, $header);

foreach ($tidiesBySheet as $sn => $t) {
    foreach ($t['rows'] as $row) {
        $line = [];
        if ($showAllYears) {
            $line[] = $row['academic_year'] ?? '';
        }
        if ($allSheets) {
            $line[] = $sn;
        }
        foreach (array_keys($identityLabels) as $key) {
            $line[] = $row[$key] ?? '';
        }
        foreach ($extraFields as $field) {
            $line[] = $row[$field] ?? '';
        }
        $line[] = $row[$t['value_label']] ?? '';
        if ($anySplit) {
            $line[] = $t['split_label'] !== null ? ($row[$t['split_label']] ?? '') : '';
        }
        $line[] = $row['ค่า'] ?? '';
        $line[] = $row['ต้องตรวจสอบ'] ?? '';
        fputcsv($out, $line);
    }
}

fclose($out);
