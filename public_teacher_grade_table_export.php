<?php
/**
 * ดาวน์โหลด CSV ของตารางครูผู้สอนรายชั้น (public_teacher_grade_table.php) — หน้าเปิดเผยต่อสาธารณะ
 * ไม่ต้อง login เหมือนหน้าเว็บคู่กัน ใช้ข้อมูลชุดเดียวกันจาก public_teacher_grade_table_data.php
 * (ไม่คำนวณซ้ำ) รูปแบบ header/BOM เดียวกับ export.php ของฝั่ง backend
 */
require_once __DIR__ . '/public_teacher_grade_table_data.php';

$filename = 'ครูผู้สอนรายชั้น_ปีการศึกษา_' . $selectedYear . '.csv';
header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

$out = fopen('php://output', 'w');
fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM ให้ Excel เปิดข้อความไทยถูกต้อง

// ระดับชั้นและ "ครู ศพด." แยกชาย/หญิงเป็นคนละคอลัมน์ (เหมือนหน้าเว็บ) — "ครูนอกระบบ" ยังเป็นยอดรวม
// เดียวไม่แยกเพศ (ดูเหตุผลใน public_teacher_grade_table_data.php ส่วนฟอร์ม 15)
$header = ['รหัสสถานศึกษา', 'ชื่อสถานศึกษา', 'สังกัด/หน่วยงาน', 'อำเภอ', 'รวม'];
foreach ($gradeLabels as $label) {
    $header[] = $label . ' (ชาย)';
    $header[] = $label . ' (หญิง)';
}
$header[] = 'ครู ศพด. (ชาย)';
$header[] = 'ครู ศพด. (หญิง)';
$header[] = 'ครูนอกระบบ';
fputcsv($out, $header);

foreach ($gradeTableRows as $row) {
    $line = [$row['school_code'], $row['school_name'], $row['agency_name'], $row['amphoe'], $row['grand_total']];
    foreach ($gradeLabels as $label) {
        $line[] = $row['grades'][$label]['male'];
        $line[] = $row['grades'][$label]['female'];
    }
    $line[] = $row['childcare_total']['male'];
    $line[] = $row['childcare_total']['female'];
    $line[] = $row['private_nonformal_total'];
    fputcsv($out, $line);
}

fclose($out);
