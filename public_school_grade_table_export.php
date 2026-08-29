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

// ระดับชั้น, "ผู้เรียน สกร.", "ผู้เรียนนอกระบบ" และ "พมจ." แยกชาย/หญิงเป็นคนละคอลัมน์ทั้งหมด
$header = ['รหัสสถานศึกษา', 'ชื่อสถานศึกษา', 'สังกัด/หน่วยงาน', 'อำเภอ', 'รวม'];
foreach ($gradeLabels as $label) {
    $header[] = $label . ' (ชาย)';
    $header[] = $label . ' (หญิง)';
}
$header[] = 'ผู้เรียน สกร. (ชาย)';
$header[] = 'ผู้เรียน สกร. (หญิง)';
$header[] = 'ผู้เรียนนอกระบบ (ชาย)';
$header[] = 'ผู้เรียนนอกระบบ (หญิง)';
$header[] = 'พมจ. (ชาย)';
$header[] = 'พมจ. (หญิง)';
fputcsv($out, $header);

foreach ($gradeTableRows as $row) {
    $line = [$row['school_code'], $row['school_name'], $row['agency_name'], $row['amphoe'], $row['grand_total']];
    foreach ($gradeLabels as $label) {
        $line[] = $row['grades'][$label]['male'];
        $line[] = $row['grades'][$label]['female'];
    }
    $line[] = $row['nfe_total']['male'];
    $line[] = $row['nfe_total']['female'];
    $line[] = $row['private_nonformal_total']['male'];
    $line[] = $row['private_nonformal_total']['female'];
    $line[] = $row['pmj_total']['male'];
    $line[] = $row['pmj_total']['female'];
    fputcsv($out, $line);
}

fclose($out);
