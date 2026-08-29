<?php
require_once __DIR__ . '/bootstrap.php';
Auth::requireLogin();

$db = Db::conn();
$forms = form_registry();
$formKey = $_GET['form'] ?? '';
$sheetName = $_GET['sheet'] ?? '';
// sheet=__all__ combines every sheet of this form into one file (e.g. form 12's 13 project
// sheets) instead of one sheet at a time — a "ชีท" column identifies which sheet each row is from.
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
    'school_name' => 'ชื่อสถานศึกษา', 'amphoe' => 'อำเภอ', 'tambon' => 'ตำบล', 'department' => 'กระทรวง',
];

// pivotsBySheet: storage sheet_name => pivot() result — single-sheet mode has exactly one entry,
// keeping the rest of this file the same either way.
$pivotsBySheet = [];
$columns = [];     // union of column_path across every included sheet, in first-seen order
$extraFields = []; // union of extra_identity fields, in first-seen order
if ($allSheets) {
    $sheetNames = [];
    foreach ($forms[$formKey]['sheets'] as $sd) {
        $sn = $sd['db_sheet_name'] ?? $sd['sheet_name'];
        if (!in_array($sn, $sheetNames, true)) {
            $sheetNames[] = $sn;
        }
    }
    foreach ($sheetNames as $sn) {
        $pivotsBySheet[$sn] = $reporting->pivot($formKey, $sn, $selectedYearInt);
    }
} else {
    $pivotsBySheet[$sheetName] = $reporting->pivot($formKey, $sheetName, $selectedYearInt);
}
foreach ($pivotsBySheet as $p) {
    foreach ($p['columns'] as $path) {
        if (!in_array($path, $columns, true)) {
            $columns[] = $path;
        }
    }
    foreach ($p['extra_identity_fields'] as $f) {
        if (!in_array($f, $extraFields, true)) {
            $extraFields[] = $f;
        }
    }
}

$filenameBase = $allSheets
    ? ($forms[$formKey]['form_label'] ?? $formKey) . '_รวมทุกชีท'
    : $sheetName;
$filename = preg_replace('/[^A-Za-z0-9ก-๙_\-]+/u', '_', $filenameBase) . '.csv';

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

$out = fopen('php://output', 'w');
fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM so Excel opens Thai text correctly

$extraLabels = array_map('extra_identity_label', $extraFields);
$yearHeader = $showAllYears ? ['ปีการศึกษา'] : [];
$sheetHeader = $allSheets ? ['ชีท'] : [];
$header = array_merge($yearHeader, $sheetHeader, array_values($identityLabels), $extraLabels, array_values($columns));
fputcsv($out, $header);

$anyRows = false;
foreach ($pivotsBySheet as $sn => $p) {
    foreach ($p['rows'] as $row) {
        $anyRows = true;
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
        foreach ($columns as $path) {
            $line[] = $row[$path] ?? '';
        }
        fputcsv($out, $line);
    }
}

if ($anyRows) {
    // แถวรวม: บวกเฉพาะคอลัมน์ที่มีค่าเป็นตัวเลขอยู่จริง รวมข้ามทุกชีทที่รวมอยู่ในไฟล์นี้
    $totals = [];
    foreach ($columns as $path) {
        $sum = 0.0;
        $hasNumeric = false;
        foreach ($pivotsBySheet as $p) {
            foreach ($p['rows'] as $row) {
                $v = $row[$path] ?? '';
                if ($v !== '' && is_numeric($v)) {
                    $sum += (float)$v;
                    $hasNumeric = true;
                }
            }
        }
        $totals[$path] = $hasNumeric ? ((fmod($sum, 1.0) === 0.0) ? (string)(int)$sum : (string)$sum) : '';
    }

    $totalLine = [];
    if ($showAllYears) {
        $totalLine[] = '';
    }
    if ($allSheets) {
        $totalLine[] = '';
    }
    $totalLine[] = 'รวมทั้งหมด';
    foreach (array_slice(array_keys($identityLabels), 1) as $key) {
        $totalLine[] = '';
    }
    foreach ($extraFields as $field) {
        $totalLine[] = '';
    }
    foreach ($columns as $path) {
        $totalLine[] = $totals[$path] ?? '';
    }
    fputcsv($out, $totalLine);
}

fclose($out);
