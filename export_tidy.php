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

if (!isset($forms[$formKey])) {
    die('ไม่พบฟอร์มที่ระบุ');
}
$sheetDef = null;
foreach ($forms[$formKey]['sheets'] as $sd) {
    if ($sd['sheet_name'] === $sheetName) {
        $sheetDef = $sd;
        break;
    }
}
if (!$sheetDef) {
    die('ไม่พบชีทที่ระบุ');
}

$reporting = new Reporting($db);
$tidy = $reporting->tidyRows($formKey, $sheetName, $sheetDef['value_label'] ?? 'รายการ', $sheetDef['value_split_last'] ?? null);

$identityLabels = [
    'seq_no' => 'ลำดับที่', 'school_code' => 'รหัสสถานศึกษา', 'agency_name' => 'สังกัด/หน่วยงาน',
    'school_name' => 'ชื่อสถานศึกษา', 'amphoe' => 'อำเภอ', 'tambon' => 'ตำบล',
];

$filename = preg_replace('/[^A-Za-z0-9ก-๙_\-]+/u', '_', $sheetName) . '_pivot.csv';

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

$out = fopen('php://output', 'w');
fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM ให้ Excel เปิดภาษาไทยถูกต้อง

$valueCols = [$tidy['value_label']];
if ($tidy['split_label']) {
    $valueCols[] = $tidy['split_label'];
}
$header = array_merge(array_values($identityLabels), $valueCols, ['ค่า', 'ต้องตรวจสอบ']);
fputcsv($out, $header);

foreach ($tidy['rows'] as $row) {
    $line = [];
    foreach (array_keys($identityLabels) as $key) {
        $line[] = $row[$key] ?? '';
    }
    foreach ($valueCols as $col) {
        $line[] = $row[$col] ?? '';
    }
    $line[] = $row['ค่า'] ?? '';
    $line[] = $row['ต้องตรวจสอบ'] ?? '';
    fputcsv($out, $line);
}

fclose($out);
