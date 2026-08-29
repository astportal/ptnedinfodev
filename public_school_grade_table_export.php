<?php
/**
 * ดาวน์โหลด CSV ของตารางผู้เรียนรายชั้น (public_school_grade_table.php) — หน้าเปิดเผยต่อสาธารณะ
 * ไม่ต้อง login เหมือนหน้าเว็บคู่กัน ใช้ข้อมูลชุดเดียวกันจาก public_school_grade_table_data.php
 * (ไม่คำนวณซ้ำ) รูปแบบ header/BOM เดียวกับ export.php ของฝั่ง backend
 */
require_once __DIR__ . '/public_school_grade_table_data.php';

$filename = 'ผู้เรียนรายชั้น_ปีการศึกษา_' . $selectedYear . '.csv';
header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

$out = fopen('php://output', 'w');
fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM ให้ Excel เปิดข้อความไทยถูกต้อง

$header = array_merge(
    ['รหัสสถานศึกษา', 'ชื่อสถานศึกษา', 'สังกัด/หน่วยงาน', 'อำเภอ'],
    $gradeLabels,
    ['ผู้เรียน สกร.', 'ผู้เรียนนอกระบบ']
);
fputcsv($out, $header);

foreach ($gradeTableRows as $row) {
    $line = [$row['school_code'], $row['school_name'], $row['agency_name'], $row['amphoe']];
    foreach ($gradeLabels as $label) {
        $line[] = $row['grades'][$label];
    }
    $line[] = $row['nfe_total'];
    $line[] = $row['private_nonformal_total'];
    fputcsv($out, $line);
}

fclose($out);
