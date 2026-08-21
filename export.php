<?php
require_once __DIR__ . '/bootstrap.php';
Auth::requireLogin();

$db = Db::conn();
$forms = form_registry();
$formKey = $_GET['form'] ?? '';
$sheetName = $_GET['sheet'] ?? '';

if (!isset($forms[$formKey])) {
    die('ไม่พบฟอร์มที่ระบุ');
}

$reporting = new Reporting($db);
$pivot = $reporting->pivot($formKey, $sheetName);
$identityLabels = [
    'seq_no' => 'ลำดับที่', 'school_code' => 'รหัสสถานศึกษา', 'agency_name' => 'สังกัด/หน่วยงาน',
    'school_name' => 'ชื่อสถานศึกษา', 'amphoe' => 'อำเภอ', 'tambon' => 'ตำบล',
];

$filename = preg_replace('/[^A-Za-z0-9ก-๙_\-]+/u', '_', $sheetName) . '.csv';

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

$out = fopen('php://output', 'w');
fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM so Excel opens Thai text correctly

$header = array_merge(array_values($identityLabels), array_values($pivot['columns']));
fputcsv($out, $header);

foreach ($pivot['rows'] as $row) {
    $line = [];
    foreach (array_keys($identityLabels) as $key) {
        $line[] = $row[$key] ?? '';
    }
    foreach ($pivot['columns'] as $path) {
        $line[] = $row[$path] ?? '';
    }
    fputcsv($out, $line);
}

fclose($out);
