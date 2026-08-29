<?php
/**
 * ดาวน์โหลด CSV ของตารางครูผู้สอน (public_teacher_grade_table.php) — หน้าเปิดเผยต่อสาธารณะ ไม่ต้อง
 * login เหมือนหน้าเว็บคู่กัน ใช้ข้อมูลชุดเดียวกันจาก public_teacher_grade_table_data.php (ไม่คำนวณ
 * ซ้ำ) รูปแบบ header/BOM เดียวกับ export.php ของฝั่ง backend
 */
require_once __DIR__ . '/public_teacher_grade_table_data.php';

$filename = 'ครูผู้สอน_ปีการศึกษา_' . $selectedYear . '.csv';
header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

$out = fopen('php://output', 'w');
fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM ให้ Excel เปิดข้อความไทยถูกต้อง

// "ครูผู้สอน" และ "ครู ศพด." แยกชาย/หญิงเป็นคนละคอลัมน์ — "ครูนอกระบบ" ยังเป็นยอดรวมเดียวไม่แยกเพศ
// (ดูเหตุผลใน public_teacher_grade_table_data.php ส่วนฟอร์ม 15)
$header = [
    'รหัสสถานศึกษา', 'ชื่อสถานศึกษา', 'สังกัด/หน่วยงาน', 'อำเภอ', 'รวม',
    'ครูผู้สอน (ชาย)', 'ครูผู้สอน (หญิง)',
    'ครู ศพด. (ชาย)', 'ครู ศพด. (หญิง)',
    'ครูนอกระบบ',
];
fputcsv($out, $header);

foreach ($gradeTableRows as $row) {
    fputcsv($out, [
        $row['school_code'], $row['school_name'], $row['agency_name'], $row['amphoe'], $row['grand_total'],
        $row['teaching_total']['male'], $row['teaching_total']['female'],
        $row['childcare_total']['male'], $row['childcare_total']['female'],
        $row['private_nonformal_total'],
    ]);
}

fclose($out);
