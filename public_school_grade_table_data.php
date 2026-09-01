<?php
/**
 * ข้อมูล+ฟังก์ชันที่ใช้ร่วมกันระหว่าง public_school_grade_table.php (หน้าเว็บ) และ
 * public_school_grade_table_export.php (ดาวน์โหลด CSV) — ตารางผู้เรียนรายชั้น 1 แถวต่อสถานศึกษา
 * 1 คอลัมน์ต่อระดับชั้น (ย่อ เช่น อ.1, ป.1, ม.1 ... ป.เอก) ตามคำขอผู้ใช้งาน 2026-08-29 หน้าเปิดเผย
 * ต่อสาธารณะ ไม่ต้อง login เหมือน public_report*.php/public_school_search.php
 *
 * แหล่งข้อมูลคอลัมน์ระดับชั้นหลัก = ฟอร์ม 4 (`4_students` / ชีท "4.จำนวนผู้เรียน") เท่านั้น เพราะเป็น
 * ฟอร์มเดียวที่เก็บจำนวนผู้เรียนแยกตามระดับชั้นละเอียดแบบนี้ (อ.เตรียม..ป.เอก 37 ระดับ) ฟอร์มอื่น
 * (14, 11, 15, 16.2) ไม่ได้แยกระดับชั้นละเอียดขนาดนี้ จึงรวมเป็นยอดเดียวคนละคอลัมน์ท้ายตารางแทน:
 * "เด็ก ศพด." จากฟอร์ม 14, "ผู้เรียนนอกระบบ" จากฟอร์ม 15 (15.1-15.3, สช.วิชาชีพ-ครู-นร.) และ "พมจ."
 * จากฟอร์ม 16.2 — ยกเว้นฟอร์ม 11 (11.1) ที่แยกเป็นคอลัมน์ตามกิจกรรมการศึกษาแทน (คอลัมน์ "สกร.-..." —
 * ดู $nfeActivityGroups ด้านล่าง เปลี่ยนจากยอดเดียวเป็นแบบนี้เมื่อ 2026-09-01 ตามคำขอผู้ใช้งาน) — ทั้ง
 * 4 กลุ่มนี้ (ศพด./กศน./เอกชนนอกระบบ/พมจ.) เป็นสถานศึกษาคนละกลุ่มกับ
 * ฟอร์ม 4 เลย (ศพด./กศน./เอกชนนอกระบบ/สถานรับเลี้ยงเด็ก ไม่กรอกฟอร์ม 4) จึงต้องรวม "แถว" (union
 * school_code) จากทุกแหล่งเข้าด้วยกัน ไม่ใช่แค่รวม "คอลัมน์" — **สำคัญ**: ก่อนแก้เมื่อ 2026-08-30
 * ตารางนี้เคยขาดฟอร์ม 14 ไปเลย ทำให้ผลรวมในตารางนี้ไม่ตรงกับยอดรวม "จำนวนผู้เรียนทั้งหมด" ของหน้า
 * ภาพรวม/ตารางสรุป (ที่รวมฟอร์ม 14 อยู่แล้วใน `$metrics['students']` ของ public_report_data.php) —
 * ถ้าเพิ่มแหล่งข้อมูลใหม่ในอนาคต ต้องเพิ่มทั้งที่นี่และที่ `public_report_data.php` พร้อมกันเสมอ ไม่งั้น
 * จะเกิดปัญหาแบบนี้ซ้ำอีก
 */
require_once __DIR__ . '/bootstrap.php';

$db = Db::conn();
$reporting = new Reporting($db);

$currentYear = Settings::currentAcademicYear($db);
try {
    $availableYears = $db->query('SELECT DISTINCT academic_year FROM submissions ORDER BY academic_year DESC')
        ->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array($currentYear, $availableYears, true)) {
        array_unshift($availableYears, $currentYear);
    }
} catch (Throwable $e) {
    $availableYears = [$currentYear];
}
$selectedYear = (int)($_GET['year'] ?? $currentYear);
if (!in_array($selectedYear, $availableYears, true)) {
    $selectedYear = $currentYear;
}

// ต่อ query string เดิม (ปีที่เลือก) เวลาสลับไปหน้าสาธารณะอีก 3 หน้าผ่านเมนูบนสุด
$navQuery = http_build_query(['year' => $selectedYear]);

// คอลัมน์ระดับชั้น 37 ระดับ (label ย่อ => [column_path เพศชาย, column_path เพศหญิง]) — คัดลอก
// column_path มาจากการตรวจ reference_templates/4_จำนวนนักเรียน.xlsx จริงตรง ๆ (ไม่ได้เดา) ปลอดภัย
// ที่จะ hardcode แบบนี้เพราะฟอร์ม 4 ไม่มี merge_extra_columns_into (ดู forms/registry.php) —
// Importer::assertStructureMatches() บังคับให้ทุกไฟล์ที่อัปโหลดผ่านมี column_path ตรงกับแม่แบบเป๊ะ
// เสมอ (ไม่งั้น import ไม่ผ่าน) จึงมั่นใจได้ว่า column_path ในฐานข้อมูลจะตรงกับที่ hardcode ไว้นี้เสมอ
// (แพทเทิร์นเดียวกับ $genderSheets/$teacherGenderSheets ใน public_report_data.php)
$gradeGroups = [
    'อ.เตรียม'          => ['ก่อนประถมศึกษา / เตรียม / อนุบาล / ชาย', 'ก่อนประถมศึกษา / เตรียม / อนุบาล / หญิง'],
    'อ.1'               => ['ก่อนประถมศึกษา / อนุบาล 1(สช) / ชาย', 'ก่อนประถมศึกษา / อนุบาล 1(สช) / หญิง'],
    'อ.2'               => ['ก่อนประถมศึกษา / อนุบาล 2(สช.) / /อนุบาล 1 / ชาย', 'ก่อนประถมศึกษา / อนุบาล 2(สช.) / /อนุบาล 1 / หญิง'],
    'อ.3'               => ['ก่อนประถมศึกษา / อนุบาล 3(สช.) / /อนุบาล 2 / ชาย', 'ก่อนประถมศึกษา / อนุบาล 3(สช.) / /อนุบาล 2 / หญิง'],
    'เด็กเล็ก'           => ['ก่อนประถมศึกษา / เด็กเล็ก / ชาย', 'ก่อนประถมศึกษา / เด็กเล็ก / หญิง'],
    'ป.1'               => ['ประถมศึกษา / ปีที่ 1 / ชาย', 'ประถมศึกษา / ปีที่ 1 / หญิง'],
    'ป.2'               => ['ประถมศึกษา / ปีที่ 2 / ชาย', 'ประถมศึกษา / ปีที่ 2 / หญิง'],
    'ป.3'               => ['ประถมศึกษา / ปีที่ 3 / ชาย', 'ประถมศึกษา / ปีที่ 3 / หญิง'],
    'ป.4'               => ['ประถมศึกษา / ปีที่ 4 / ชาย', 'ประถมศึกษา / ปีที่ 4 / หญิง'],
    'ป.5'               => ['ประถมศึกษา / ปีที่ 5 / ชาย', 'ประถมศึกษา / ปีที่ 5 / หญิง'],
    'ป.6'               => ['ประถมศึกษา / ปีที่ 6 / ชาย', 'ประถมศึกษา / ปีที่ 6 / หญิง'],
    'ม.1'               => ['มัธยมศึกษาตอนต้น / มัธยมศึกษา / ปีที่ 1 / ชาย', 'มัธยมศึกษาตอนต้น / มัธยมศึกษา / ปีที่ 1 / หญิง'],
    'ม.2'               => ['มัธยมศึกษาตอนต้น / มัธยมศึกษา / ปีที่ 2 / ชาย', 'มัธยมศึกษาตอนต้น / มัธยมศึกษา / ปีที่ 2 / หญิง'],
    'ม.3'               => ['มัธยมศึกษาตอนต้น / มัธยมศึกษา / ปีที่ 3 / ชาย', 'มัธยมศึกษาตอนต้น / มัธยมศึกษา / ปีที่ 3 / หญิง'],
    'ม.4'               => ['มัธยมศึกษาตอนปลาย / มัธยมศึกษา / ปีที่ 4 / ชาย', 'มัธยมศึกษาตอนปลาย / มัธยมศึกษา / ปีที่ 4 / หญิง'],
    'ม.5'               => ['มัธยมศึกษาตอนปลาย / มัธยมศึกษา / ปีที่ 5 / ชาย', 'มัธยมศึกษาตอนปลาย / มัธยมศึกษา / ปีที่ 5 / หญิง'],
    'ม.6'               => ['มัธยมศึกษาตอนปลาย / มัธยมศึกษา / ปีที่ 6 / ชาย', 'มัธยมศึกษาตอนปลาย / มัธยมศึกษา / ปีที่ 6 / หญิง'],
    'ปวช.1'             => ['ประกาศนียบัตรวิชาชีพ / ประกาศนียบัตร / วิชาชีพปีที่ 1 / ชาย', 'ประกาศนียบัตรวิชาชีพ / ประกาศนียบัตร / วิชาชีพปีที่ 1 / หญิง'],
    'ปวช.2'             => ['ประกาศนียบัตรวิชาชีพ / ประกาศนียบัตร / วิชาชีพปีที่ 2 / ชาย', 'ประกาศนียบัตรวิชาชีพ / ประกาศนียบัตร / วิชาชีพปีที่ 2 / หญิง'],
    'ปวช.3'             => ['ประกาศนียบัตรวิชาชีพ / ประกาศนียบัตร / วิชาชีพปีที่ 3 / ชาย', 'ประกาศนียบัตรวิชาชีพ / ประกาศนียบัตร / วิชาชีพปีที่ 3 / หญิง'],
    'อนุป.1'            => ['อนุปริญญา / ปีที่ 1 / ชาย', 'อนุปริญญา / ปีที่ 1 / หญิง'],
    'อนุป.2'            => ['อนุปริญญา / ปีที่ 2 / ชาย', 'อนุปริญญา / ปีที่ 2 / หญิง'],
    'อนุป.3'            => ['อนุปริญญา / ปีที่ 3 / ชาย', 'อนุปริญญา / ปีที่ 3 / หญิง'],
    'ปวส.1'             => ['ประกาศนียบัตรวิชาชีพชั้นสูง / ประกาศนียบัตร / วิชาชีพชั้นสูงปีที่ 1 / ชาย', 'ประกาศนียบัตรวิชาชีพชั้นสูง / ประกาศนียบัตร / วิชาชีพชั้นสูงปีที่ 1 / หญิง'],
    'ปวส.2'             => ['ประกาศนียบัตรวิชาชีพชั้นสูง / ประกาศนียบัตร / วิชาชีพชั้นสูงปีที่ 2 / ชาย', 'ประกาศนียบัตรวิชาชีพชั้นสูง / ประกาศนียบัตร / วิชาชีพชั้นสูงปีที่ 2 / หญิง'],
    'ปวส.3'             => ['ประกาศนียบัตรวิชาชีพชั้นสูง / ประกาศนียบัตร / วิชาชีพชั้นสูงปีที่ 3 / ชาย', 'ประกาศนียบัตรวิชาชีพชั้นสูง / ประกาศนียบัตร / วิชาชีพชั้นสูงปีที่ 3 / หญิง'],
    'ป.ตรี1'            => ['ปริญญาตรี / ปีที่ 1 / ชาย', 'ปริญญาตรี / ปีที่ 1 / หญิง'],
    'ป.ตรี2'            => ['ปริญญาตรี / ปีที่ 2 / ชาย', 'ปริญญาตรี / ปีที่ 2 / หญิง'],
    'ป.ตรี3'            => ['ปริญญาตรี / ปีที่ 3 / ชาย', 'ปริญญาตรี / ปีที่ 3 / หญิง'],
    'ป.ตรี4'            => ['ปริญญาตรี / ปีที่ 4 / ชาย', 'ปริญญาตรี / ปีที่ 4 / หญิง'],
    'ป.ตรี5'            => ['ปริญญาตรี / ปีที่ 5 / ชาย', 'ปริญญาตรี / ปีที่ 5 / หญิง'],
    'ป.ตรี6'            => ['ปริญญาตรี / ปีที่ 6 / ชาย', 'ปริญญาตรี / ปีที่ 6 / หญิง'],
    'ป.บัณฑิต'          => ['ประกาศนียบัตร / บัณฑิต / ชาย', 'ประกาศนียบัตร / บัณฑิต / หญิง'],
    'ป.โท'              => ['ปริญญาโท / ชาย', 'ปริญญาโท / หญิง'],
    'ป.บัณฑิตชั้นสูง'    => ['ประกาศนียบัตร / บัณฑิตชั้นสูง / ชาย', 'ประกาศนียบัตร / บัณฑิตชั้นสูง / หญิง'],
    'ป.เอก'             => ['ปริญญาเอก / ชาย', 'ปริญญาเอก / หญิง'],
    'การศึกษาพิเศษ'      => ['การศึกษาพิเศษ / ชาย', 'การศึกษาพิเศษ / หญิง'],
];
$gradeLabels = array_keys($gradeGroups);

// "ผู้เรียน สกร." (ฟอร์ม 11.1) แยกเป็นคอลัมน์ตามกิจกรรมการศึกษาแทนยอดรวมเดียว (เพิ่มเมื่อ 2026-09-01
// ตามคำขอผู้ใช้งาน — เดิมรวมทุกกิจกรรมเป็นยอดเดียวคอลัมน์เดียว) column_path คัดลอกมาจากการตรวจ
// reference_templates/11_ข้อมูลการศึกษา_กศน.xlsx (ชีท 11.1) ตรง ๆ ด้วย unzip+node อ่าน worksheet
// XML ดิบ (เครื่องนี้ไม่มี PHP) ปลอดภัยที่จะ hardcode แบบนี้เพราะฟอร์ม 11 ไม่มี
// merge_extra_columns_into (ดู forms/registry.php) เหมือน $gradeGroups ข้างบน — label นำหน้าด้วย
// "สกร." (การศึกษานอกระบบและการศึกษาตามอัธยาศัย) ตามคำขอผู้ใช้งาน กันสับสนกับคอลัมน์ระดับชั้นของ
// ฟอร์ม 4 ที่ชื่อคล้ายกัน (เช่น "ประถม") — **หมายเหตุ**: คอลัมน์ลำดับที่ 10 ต้นฉบับสะกดขาดคำว่า
// "กระ" (เหลือ "การบวนการเรียนรู้ตามแนว..." แทนที่จะเป็น "กระบวนการ...") เข้าใจว่าเป็นการพิมพ์ผิดใน
// แม่แบบเอง แก้คำสะกดเฉพาะ label ที่แสดงผลให้ถูกต้อง แต่ column_path ที่ใช้ค้นหาข้อมูลจริงยังคง
// สะกดตามต้นฉบับเป๊ะ (ต้องตรงกับข้อมูลที่ import เข้า DB จริง)
$nfeActivityGroups = [
    'สกร.-การส่งเสริมการรู้หนังสือ' => ['การส่งเสริม / การรู้หนังสือ / ชาย', 'การส่งเสริม / การรู้หนังสือ / หญิง'],
    'สกร.-ประถม' => ['ประถมศึกษา / ชาย', 'ประถมศึกษา / หญิง'],
    'สกร.-ม.ต้น' => ['มัธยมศึกษา / ตอนต้น / ชาย', 'มัธยมศึกษา / ตอนต้น / หญิง'],
    'สกร.-ม.ปลาย' => ['มัธยมศึกษา / ตอนปลาย / ชาย', 'มัธยมศึกษา / ตอนปลาย / หญิง'],
    'สกร.-ปวช.' => ['ปวช. / ชาย', 'ปวช. / หญิง'],
    'สกร.-การศึกษาเพื่อพัฒนาอาชีพ' => ['การศึกษา / เพื่อพัฒนาอาชีพ / ชาย', 'การศึกษา / เพื่อพัฒนาอาชีพ / หญิง'],
    'สกร.-การศึกษาเพื่อพัฒนาทักษะชีวิต' => ['การศึกษา / เพื่อพัฒนาทักษะชีวิต / ชาย', 'การศึกษา / เพื่อพัฒนาทักษะชีวิต / หญิง'],
    'สกร.-หลักสูตรระยะสั้น' => ['หลักสูตรระยะสั้น / ชาย', 'หลักสูตรระยะสั้น / หญิง'],
    'สกร.-การศึกษาชุมชนในเขตภูเขา' => ['การศึกษาชุมชน / ในเขตภูเขา / ชาย', 'การศึกษาชุมชน / ในเขตภูเขา / หญิง'],
    'สกร.-กระบวนการเรียนรู้ตามแนวปรัชญาเศรษฐกิจพอเพียง' => [
        'การบวนการเรียนรู้ตามแนว / ปรัชญาเศรษฐกิจพอเพียง / ชาย',
        'การบวนการเรียนรู้ตามแนว / ปรัชญาเศรษฐกิจพอเพียง / หญิง',
    ],
    'สกร.-โครงการตามพระราชดำริ' => ['โครงการ / ตามพระราชดำริ / ชาย', 'โครงการ / ตามพระราชดำริ / หญิง'],
    'สกร.-การศึกษาเพื่อพัฒนาสังคมและชุมชน' => ['การศึกษาเพื่อพัฒนา / สังคมและชุมชน / ชาย', 'การศึกษาเพื่อพัฒนา / สังคมและชุมชน / หญิง'],
    'สกร.-โครงการศูนย์ฝึกอาชีพชุมชน' => ['โครงการ / ศูนย์ฝึกอาชีพชุมชน / ชาย', 'โครงการ / ศูนย์ฝึกอาชีพชุมชน / หญิง'],
];
$nfeActivityLabels = array_keys($nfeActivityGroups);

// ชีทฟอร์ม 15 ที่นับเป็น "ผู้เรียนนอกระบบ" — ชุดเดียวกับที่ใช้ใน public_report_data.php metric
// "จำนวนนักเรียน" (เฉพาะส่วนของฟอร์ม 15) คัดลอกมาตรงนี้แทนการ include ไฟล์นั้น เพราะไฟล์นั้นมี
// ตัวแปรชื่อชนกันหลายตัว ($reporting, $selectedYear, $navQuery ฯลฯ) ไม่ได้ออกแบบให้ include ซ้อนกัน
// — ทุกชีทแยกชาย/หญิงได้ครบ (แก้เมื่อ 2026-08-30: ชีท "สช.วิชาชีพ-ครู-นร." เดิมเข้าใจผิดว่ามีแค่
// คอลัมน์ "รวม" อย่างเดียว ตรวจ reference_templates/ ใหม่แล้วมีคอลัมน์ชาย/หญิงแยกให้จริง เหมือนที่
// แก้ไปแล้วฝั่งครูผู้สอนใน public_teacher_grade_table_data.php)
$privateNonformalSheets = [
    ['15_private_nonformal', '15.1', ['จำนวนผู้เรียน / ชาย', 'จำนวนผู้เรียน / หญิง']],
    ['15_private_nonformal', '15.2', ['จำนวนผู้เรียน / ชาย', 'จำนวนผู้เรียน / หญิง']],
    ['15_private_nonformal', '15.3', ['จำนวนผู้เรียน / ชาย', 'จำนวนผู้เรียน / หญิง']],
    ['15_private_nonformal', 'สช.วิชาชีพ-ครู-นร.', ['จำนวนนักเรียน / ชาย', 'จำนวนนักเรียน / หญิง']],
];

// เด็กเล็กที่ศูนย์พัฒนาเด็กเล็ก/สถานพัฒนาเด็กปฐมวัย (ฟอร์ม 14) — **แก้กลับอีกครั้งเมื่อ 2026-08-30
// (คำขอถัดมาทันที)**: เคยเปลี่ยนไปใช้คอลัมน์ "รวมทั้งสิ้น" ตรง ๆ (กันยอดไม่ตรงกับหน้าภาพรวม) แต่ผู้ใช้
// สั่งกลับให้ **ห้ามใช้คอลัมน์ "รวม"/"รวมทั้งสิ้น" ของฟอร์มไหนเป็นแหล่งข้อมูลเด็ดขาด** เพราะเป็นตัวเลข
// ที่หน่วยงานพิมพ์บวกเอง เสี่ยงกรอกผิด/บวกเลขผิดได้ (ไม่ใช่ตัวเลขที่ระบบคำนวณเอง) ต้องการข้อมูลแยกชาย/
// หญิงเสมอ — ใช้ผลรวมคอลัมน์ "เด็กเล็ก / อายุ 2-5 ปี / เพศ" (8 คอลัมน์) แทน ชุดเดียวกับที่
// public_report_data.php ($metrics['students'] + $genderSheets) แก้ตามไปด้วยพร้อมกัน (แก้ 2 ไฟล์
// พร้อมกันเสมอ กันปัญหายอดไม่ตรงแบบที่เจอไปแล้ว) — ยอดรวม 8 คอลัมน์นี้อาจไม่เท่ากับ "รวมทั้งสิ้น" เป๊ะ
// ทุกแถว (ถ้าหน่วยงานกรอกไม่ตรงกัน) แต่นั่นคือพฤติกรรมที่ตั้งใจแล้วตามคำสั่งผู้ใช้งาน — ไม่ใช่บั๊ก
$childcareChildColumns = [
    'เด็กเล็ก / อายุ 2 ปี / ชาย', 'เด็กเล็ก / อายุ 2 ปี / หญิง',
    'เด็กเล็ก / อายุ 3 ปี / ชาย', 'เด็กเล็ก / อายุ 3 ปี / หญิง',
    'เด็กเล็ก / อายุ 4 ปี / ชาย', 'เด็กเล็ก / อายุ 4 ปี / หญิง',
    'เด็กเล็ก / อายุ 5 ปี / ชาย', 'เด็กเล็ก / อายุ 5 ปี / หญิง',
];

// เด็กที่สถานรับเลี้ยงเด็กเอกชนสังกัด พมจ. (ฟอร์ม 16.2) — เฉพาะคอลัมน์ "จำนวนเด็ก..." เท่านั้น (ไม่รวม
// "จำนวนผู้ดูแลเด็ก..." ในชีทเดียวกัน ซึ่งเป็นข้อมูลครู/ผู้ดูแล ไม่ใช่ผู้เรียน) — เพิ่มเมื่อ 2026-08-30
// ตามคำขอผู้ใช้งาน สถานศึกษากลุ่มนี้จับคู่ school_code ด้วยชื่อกับทำเนียบโรงเรียนอัตโนมัติ (ดู
// match_school_code_by_name ใน forms/registry.php + Importer::schoolCodesByName()) แถวที่จับคู่
// ไม่ได้จะไม่มี school_code เลยและจะไม่ปรากฏในตารางนี้ (เหมือนแถวที่ขาด school_code ของฟอร์มอื่น)
$pmjColumns = [
    'จำนวนเด็กช่วงอายุ 0-2 ปี / ชาย', 'จำนวนเด็กช่วงอายุ 0-2 ปี / หญิง',
    'จำนวนเด็กช่วงอายุ 3-5 ปี / ชาย', 'จำนวนเด็กช่วงอายุ 3-5 ปี / หญิง',
];

/** แถวว่างเปล่า 1 แถว (ทุกระดับชั้น = 0) ให้เติมตอนพบ school_code ใหม่จากฟอร์ม 11/15 ที่ฟอร์ม 4 ไม่มี */
function grade_table_blank_row(string $schoolCode, string $schoolName, string $agencyName, string $amphoe, array $gradeLabels, array $nfeActivityLabels): array
{
    $row = [
        'school_code' => $schoolCode,
        'school_name' => $schoolName,
        'agency_name' => $agencyName,
        'amphoe'      => $amphoe,
        'childcare_total' => ['male' => 0.0, 'female' => 0.0],
        'private_nonformal_total' => ['male' => 0.0, 'female' => 0.0],
        'pmj_total'   => ['male' => 0.0, 'female' => 0.0],
    ];
    foreach ($gradeLabels as $label) {
        $row['grades'][$label] = ['male' => 0.0, 'female' => 0.0];
    }
    foreach ($nfeActivityLabels as $label) {
        $row['nfe_by_activity'][$label] = ['male' => 0.0, 'female' => 0.0];
    }
    return $row;
}

$rowsByCode = [];

// 1) ฟอร์ม 4 — แหล่งหลัก ให้ identity (สังกัด/อำเภอ ผ่าน pivot() ที่ resolve กับ schools_master ให้
// แล้ว) + ยอดแยกตามระดับชั้น 37 คอลัมน์ แยกชาย/หญิง ตามคำขอผู้ใช้งาน (2026-08-29) — column_path ของ
// แต่ละระดับชั้นแยกเพศอยู่แล้วในต้นฉบับ (2 คอลัมน์ต่อระดับ) ไม่ต้องรวมกันเหมือนเดิมอีกต่อไป
$pivot4 = $reporting->pivot('4_students', '4.จำนวนผู้เรียน', $selectedYear);
foreach ($pivot4['rows'] as $r) {
    $code = trim((string)($r['school_code'] ?? ''));
    if ($code === '') {
        continue;
    }
    $row = grade_table_blank_row($code, (string)($r['school_name'] ?? ''), (string)($r['agency_name'] ?? ''), (string)($r['amphoe'] ?? ''), $gradeLabels, $nfeActivityLabels);
    foreach ($gradeGroups as $label => [$maleCol, $femaleCol]) {
        $m = $r[$maleCol] ?? '';
        $f = $r[$femaleCol] ?? '';
        $row['grades'][$label]['male'] = is_numeric($m) ? (float)$m : 0.0;
        $row['grades'][$label]['female'] = is_numeric($f) ? (float)$f : 0.0;
    }
    $rowsByCode[$code] = $row;
}

// 2) ฟอร์ม 14 (เด็กเล็ก ศพด.) — โรงเรียนคนละกลุ่มกับฟอร์ม 4 เลย (ศพด.ไม่กรอกฟอร์ม 4) เติมแถวใหม่ถ้า
// ยังไม่เคยเจอ school_code นี้มาก่อน จับคู่ด้วย regex ปลาย column_path แบบเดียวกับฟอร์มอื่น — แต่บาง
// แถวในชีทนี้ "รูปแบบการศึกษา" (จากทำเนียบโรงเรียน) ไม่ใช่ "ศูนย์พัฒนาเด็ก" จริง (สถานศึกษาที่กรอก
// ฟอร์ม 4 อยู่แล้วแต่หน่วยงานกรอกฟอร์ม 14 ซ้ำมาด้วยโดยเข้าใจผิด) ต้องข้ามแถวเหล่านั้น ไม่งั้นจะนับ
// ผู้เรียนซ้ำกับฟอร์ม 4 — ใช้เงื่อนไขเดียวกับ $onlyChildcareCenterRows ใน public_report_data.php
// (คัดลอกมาตรง ๆ แทนการ include เพราะ 2 ไฟล์นี้ไม่ได้ออกแบบให้ include ซ้อนกัน — ดูเหตุผลเดียวกันที่
// $privateNonformalSheets ด้านบน) เพิ่มเมื่อ 2026-08-30 ตามคำขอผู้ใช้งาน
$pivot14 = $reporting->pivot('14_childcare_centers', '14.ข้อมูลศูนย์พัฒนาเด็กเล็ก', $selectedYear);
foreach ($pivot14['rows'] as $r) {
    $code = trim((string)($r['school_code'] ?? ''));
    if ($code === '') {
        continue;
    }
    if (trim((string)($r['education_form'] ?? '')) !== 'ศูนย์พัฒนาเด็ก') {
        continue;
    }
    if (!isset($rowsByCode[$code])) {
        $rowsByCode[$code] = grade_table_blank_row($code, (string)($r['school_name'] ?? ''), (string)($r['agency_name'] ?? ''), (string)($r['amphoe'] ?? ''), $gradeLabels, $nfeActivityLabels);
    }
    foreach ($childcareChildColumns as $path) {
        $v = $r[$path] ?? '';
        if ($v === '' || !is_numeric($v)) {
            continue;
        }
        if (preg_match('/ชาย$/u', $path)) {
            $rowsByCode[$code]['childcare_total']['male'] += (float)$v;
        } elseif (preg_match('/หญิง$/u', $path)) {
            $rowsByCode[$code]['childcare_total']['female'] += (float)$v;
        }
    }
}

// 3) ฟอร์ม 11.1+11.2 (ผู้เรียน กศน. ปกติ+พิการ) — โรงเรียนคนละกลุ่มกับฟอร์ม 4 เลย (กศน.ไม่กรอกฟอร์ม 4)
// เติมแถวใหม่ถ้ายังไม่เคยเจอ school_code นี้มาก่อน แยกเป็นคอลัมน์ตามกิจกรรมการศึกษา
// ($nfeActivityGroups ข้างบน) แทนยอดรวมเดียวเหมือนเดิม (เปลี่ยนเมื่อ 2026-09-01 ตามคำขอผู้ใช้งาน) —
// lookup ตรงด้วย column_path ของแต่ละกิจกรรมแบบเดียวกับ $gradeGroups ของฟอร์ม 4 แทนการวนคอลัมน์
// ทั้งหมดด้วย regex เหมือนเดิม — **รวม 11.2 (ผู้เรียนพิการ) เข้ามาบวกด้วยเมื่อ 2026-09-01** ตามคำขอ
// ผู้ใช้งาน เพราะ 11.1 ("ผู้เรียนปกติ") กับ 11.2 ("ผู้เรียนพิการ") เป็นคนละกลุ่มผู้เรียนที่ไม่ทับซ้อนกัน
// (แยกแบบฟอร์มโดยตั้งใจ ไม่ใช่มองข้อมูลชุดเดียวกันคนละมิติแบบ 11.2/11.3 — ดูเหตุผลเต็มที่
// public_report_data.php ต้องแก้คู่กันเสมอให้ metric 'students'/$genderSheets ที่นั่นรวม 11.2 ด้วย
// เช่นกัน ไม่งั้นยอดรวมตารางนี้จะไม่ตรงกับหน้าภาพรวมอีก) โครงสร้างคอลัมน์ของ 11.2 ตรวจแล้วเหมือน 11.1
// ทุกประการ จึงใช้ $nfeActivityGroups ชุดเดียวกัน — ผลรวมข้ามทั้ง 2 ชีท×13 กิจกรรม สะสมเข้า
// nfe_by_activity เดียวกัน (ไม่แยกปกติ/พิการในตารางนี้ เพราะต้องการแค่ยอดรวมผู้เรียนต่อกิจกรรม)
foreach (['11.1', '11.2'] as $nfeSheetName) {
    $pivot11x = $reporting->pivot('11_nfe', $nfeSheetName, $selectedYear);
    foreach ($pivot11x['rows'] as $r) {
        $code = trim((string)($r['school_code'] ?? ''));
        if ($code === '') {
            continue;
        }
        if (!isset($rowsByCode[$code])) {
            $rowsByCode[$code] = grade_table_blank_row($code, (string)($r['school_name'] ?? ''), (string)($r['agency_name'] ?? ''), (string)($r['amphoe'] ?? ''), $gradeLabels, $nfeActivityLabels);
        }
        foreach ($nfeActivityGroups as $label => [$maleCol, $femaleCol]) {
            $m = $r[$maleCol] ?? '';
            $f = $r[$femaleCol] ?? '';
            if ($m !== '' && is_numeric($m)) {
                $rowsByCode[$code]['nfe_by_activity'][$label]['male'] += (float)$m;
            }
            if ($f !== '' && is_numeric($f)) {
                $rowsByCode[$code]['nfe_by_activity'][$label]['female'] += (float)$f;
            }
        }
    }
}

// 4) ฟอร์ม 15 (ผู้เรียนนอกระบบ) — โรงเรียนคนละกลุ่มกับฟอร์ม 4 เช่นกัน รวมยอดข้าม 4 ชีท แต่ละชีทมี
// คอลัมน์อื่นปนอยู่ด้วย ต้องระบุ onlyColumns กันนับซ้ำ — แยกชาย/หญิงได้ครบทั้ง 4 ชีท (แก้เมื่อ
// 2026-08-30 — ดูเหตุผลที่ $privateNonformalSheets ด้านบน) จับคู่ด้วย regex ปลาย column_path แบบ
// เดียวกับที่ใช้กับฟอร์ม 11.1
foreach ($privateNonformalSheets as [$formKey, $sheetName, $onlyColumns]) {
    $pivot15 = $reporting->pivot($formKey, $sheetName, $selectedYear);
    foreach ($pivot15['rows'] as $r) {
        $code = trim((string)($r['school_code'] ?? ''));
        if ($code === '') {
            continue;
        }
        if (!isset($rowsByCode[$code])) {
            $rowsByCode[$code] = grade_table_blank_row($code, (string)($r['school_name'] ?? ''), (string)($r['agency_name'] ?? ''), (string)($r['amphoe'] ?? ''), $gradeLabels, $nfeActivityLabels);
        }
        foreach ($onlyColumns as $path) {
            $v = $r[$path] ?? '';
            if ($v === '' || !is_numeric($v)) {
                continue;
            }
            if (preg_match('/ชาย$/u', $path)) {
                $rowsByCode[$code]['private_nonformal_total']['male'] += (float)$v;
            } elseif (preg_match('/หญิง$/u', $path)) {
                $rowsByCode[$code]['private_nonformal_total']['female'] += (float)$v;
            }
        }
    }
}

// 5) ฟอร์ม 16.2 (เด็กสถานรับเลี้ยงเด็กเอกชนสังกัด พมจ.) — โรงเรียนคนละกลุ่มกับฟอร์ม 4 เช่นกัน (ไม่มี
// อยู่ในฟอร์ม 4 เลย) ต้อง join ด้วย school_code ที่จับคู่จากชื่อไว้แล้วตอน import (ดู
// match_school_code_by_name) แถวที่จับคู่ไม่สำเร็จจะไม่มี school_code เลย ข้ามไปเหมือนแถวที่ขาด
// school_code ของฟอร์มอื่นทุกประการ (ผู้ใช้ต้องไปแก้ที่หน้า "รายการที่ต้องตรวจสอบ" ก่อนถึงจะขึ้นที่นี่)
$pivot16 = $reporting->pivot('16_pmj', 'พมจ-16.2', $selectedYear);
foreach ($pivot16['rows'] as $r) {
    $code = trim((string)($r['school_code'] ?? ''));
    if ($code === '') {
        continue;
    }
    if (!isset($rowsByCode[$code])) {
        $rowsByCode[$code] = grade_table_blank_row($code, (string)($r['school_name'] ?? ''), (string)($r['agency_name'] ?? ''), (string)($r['amphoe'] ?? ''), $gradeLabels, $nfeActivityLabels);
    }
    foreach ($pmjColumns as $path) {
        $v = $r[$path] ?? '';
        if ($v === '' || !is_numeric($v)) {
            continue;
        }
        if (preg_match('/ชาย$/u', $path)) {
            $rowsByCode[$code]['pmj_total']['male'] += (float)$v;
        } elseif (preg_match('/หญิง$/u', $path)) {
            $rowsByCode[$code]['pmj_total']['female'] += (float)$v;
        }
    }
}

$gradeTableRows = array_values($rowsByCode);
usort($gradeTableRows, static fn($a, $b) => strcmp($a['school_name'], $b['school_name']));

// คอลัมน์ "รวม" ต่อสถานศึกษา — ผลรวมทุกระดับชั้น (ชาย+หญิง) + เด็ก ศพด. (ชาย+หญิง) + ผู้เรียน สกร.
// (ชาย+หญิง) + ผู้เรียนนอกระบบ (ชาย+หญิง) + พมจ. (ชาย+หญิง) คำนวณครั้งเดียวตรงนี้หลังข้อมูลทุกแหล่ง
// (ฟอร์ม 4/14/11/15/16) รวมเข้าแถวเดียวกันครบแล้ว กันไม่ให้ต้องคำนวณซ้ำ/พลาดจุดใดจุดหนึ่งถ้าไปคำนวณ
// แทรกในแต่ละ pass ข้างบน
// "รวมชาย"/"รวมหญิง" — ผลรวมแยกเพศข้ามทุกแหล่งข้อมูล (เพิ่มเมื่อ 2026-08-30 ตามคำขอผู้ใช้งาน) ทุก
// แหล่งในตารางนี้แยกชาย/หญิงอยู่แล้วทั้งหมด (grades, childcare_total, nfe_by_activity,
// private_nonformal_total, pmj_total) จึงรวมตรง ๆ ได้เลย ไม่ต้องกังวลเรื่องแหล่งไหนไม่มีข้อมูลเพศ —
// รวมทุกกิจกรรมของ $nfeActivityLabels เสมอ (ไม่ใช่แค่รายการที่แสดงผลจริงหลังกรองคอลัมน์ว่าง) กัน
// ยอดรวมขาดหายถ้ามีกิจกรรมที่ไม่มีข้อมูลเลยแต่ไม่ได้ถูกกรองออกด้วยเหตุผลอื่น (ในทางปฏิบัติค่าจะเป็น 0
// อยู่แล้วไม่มีผลต่อผลรวม แต่เขียนให้ถูกต้องตามหลักการเสมอ)
foreach ($gradeTableRows as &$gtRow) {
    $grandMale = $gtRow['childcare_total']['male']
        + $gtRow['private_nonformal_total']['male'] + $gtRow['pmj_total']['male'];
    $grandFemale = $gtRow['childcare_total']['female']
        + $gtRow['private_nonformal_total']['female'] + $gtRow['pmj_total']['female'];
    foreach ($gradeLabels as $label) {
        $grandMale += $gtRow['grades'][$label]['male'];
        $grandFemale += $gtRow['grades'][$label]['female'];
    }
    foreach ($nfeActivityLabels as $label) {
        $grandMale += $gtRow['nfe_by_activity'][$label]['male'];
        $grandFemale += $gtRow['nfe_by_activity'][$label]['female'];
    }
    $gtRow['grand_total_male'] = $grandMale;
    $gtRow['grand_total_female'] = $grandFemale;
    $gtRow['grand_total'] = $grandMale + $grandFemale;
}
unset($gtRow);

// รายการตัวเลือก dropdown "สังกัด/หน่วยงาน" และ "อำเภอ" — ดึงจากข้อมูลทั้งหมดก่อนกรอง (ไม่ใช่หลัง
// กรอง) ไม่งั้นตัวเลือกจะหายไปเรื่อย ๆ เมื่อเลือกกรองแล้ว ทำให้เปลี่ยนไปเลือกตัวอื่นไม่ได้อีก
$agencyOptions = [];
$amphoeOptions = [];
foreach ($gradeTableRows as $r) {
    if ($r['agency_name'] !== '') {
        $agencyOptions[$r['agency_name']] = true;
    }
    if ($r['amphoe'] !== '') {
        $amphoeOptions[$r['amphoe']] = true;
    }
}
$agencyOptions = array_keys($agencyOptions);
$amphoeOptions = array_keys($amphoeOptions);
sort($agencyOptions, SORT_STRING | SORT_FLAG_CASE);
sort($amphoeOptions, SORT_STRING | SORT_FLAG_CASE);

// คอลัมน์กิจกรรม สกร. ที่จะแสดงจริง — ไม่โชว์คอลัมน์ที่ไม่มีข้อมูลเลยสักสถานศึกษาเดียว ตามคำขอ
// ผู้ใช้งาน (2026-09-01) ตรวจจากข้อมูล**ทั้งหมดก่อนกรอง**เหมือน $agencyOptions/$amphoeOptions ข้างบน
// เพื่อให้ชุดคอลัมน์ที่แสดงคงที่ ไม่เปลี่ยนไปมาตามตัวกรองค้นหาที่เลือกอยู่ — ไม่กระทบยอดรวม
// (grand_total ยังรวมทุกกิจกรรมใน $nfeActivityLabels ทั้งหมดเสมอ ไม่ใช่แค่ที่แสดงผล)
$nfeActivityLabelsShown = [];
foreach ($nfeActivityLabels as $label) {
    foreach ($gradeTableRows as $r) {
        if ($r['nfe_by_activity'][$label]['male'] > 0 || $r['nfe_by_activity'][$label]['female'] > 0) {
            $nfeActivityLabelsShown[] = $label;
            break;
        }
    }
}

// ค้นหา/กรอง — ใช้ร่วมกันทั้งหน้าเว็บและ CSV export (export ที่ดาวน์โหลดจะตรงกับสิ่งที่กำลังดูอยู่
// บนหน้าเว็บเสมอ) ค้นชื่อสถานศึกษาแบบ substring ไม่สนตัวพิมพ์เล็ก/ใหญ่ — ใช้ stripos() ธรรมดา ไม่ใช้
// mb_stripos() เพราะภาษาไทยไม่มีตัวพิมพ์เล็ก/ใหญ่ให้ต้องแปลง และ strpos() ตระกูลนี้ทำงานถูกต้องกับ
// substring ของสตริง UTF-8 อยู่แล้ว (ไม่ต้องพึ่ง extension mbstring ซึ่งไม่เคยมีจุดไหนในระบบนี้ใช้
// มาก่อนเลย ไม่อยากเพิ่ม dependency ใหม่) — สังกัด/หน่วยงาน เลือกได้หลายรายการพร้อมกัน (multi-select
// dropdown, `<select multiple name="agency[]">`) ตามคำขอผู้ใช้งาน 2026-08-29 ส่วนอำเภอยังเลือกได้
// รายการเดียว (ผู้ใช้ขอเจาะจงแค่ตัวสังกัด/หน่วยงาน)
$searchName = trim($_GET['q'] ?? '');
$filterAgency = array_values(array_filter(array_map('trim', (array)($_GET['agency'] ?? []))));
$filterAmphoe = trim($_GET['amphoe'] ?? '');
if ($searchName !== '' || $filterAgency || $filterAmphoe !== '') {
    $gradeTableRows = array_values(array_filter($gradeTableRows, static function ($r) use ($searchName, $filterAgency, $filterAmphoe) {
        if ($searchName !== '' && stripos($r['school_name'], $searchName) === false) {
            return false;
        }
        if ($filterAgency && !in_array($r['agency_name'], $filterAgency, true)) {
            return false;
        }
        if ($filterAmphoe !== '' && $r['amphoe'] !== $filterAmphoe) {
            return false;
        }
        return true;
    }));
}

// แถวรวม — คำนวณจากผลลัพธ์ "หลัง" กรองเสมอ (เปลี่ยนตามสถานะการค้นหาตามที่ผู้ใช้งานขอ) แสดงไว้แถวบนสุด
// ของตาราง
$gradeTotals = [
    'grades' => [],
    'childcare_total' => ['male' => 0.0, 'female' => 0.0],
    'nfe_by_activity' => [],
    'private_nonformal_total' => ['male' => 0.0, 'female' => 0.0],
    'pmj_total' => ['male' => 0.0, 'female' => 0.0],
    'grand_total' => 0.0,
    'grand_total_male' => 0.0,
    'grand_total_female' => 0.0,
];
foreach ($gradeLabels as $label) {
    $gradeTotals['grades'][$label] = ['male' => 0.0, 'female' => 0.0];
}
foreach ($nfeActivityLabels as $label) {
    $gradeTotals['nfe_by_activity'][$label] = ['male' => 0.0, 'female' => 0.0];
}
foreach ($gradeTableRows as $r) {
    foreach ($gradeLabels as $label) {
        $gradeTotals['grades'][$label]['male'] += $r['grades'][$label]['male'];
        $gradeTotals['grades'][$label]['female'] += $r['grades'][$label]['female'];
    }
    foreach ($nfeActivityLabels as $label) {
        $gradeTotals['nfe_by_activity'][$label]['male'] += $r['nfe_by_activity'][$label]['male'];
        $gradeTotals['nfe_by_activity'][$label]['female'] += $r['nfe_by_activity'][$label]['female'];
    }
    $gradeTotals['childcare_total']['male'] += $r['childcare_total']['male'];
    $gradeTotals['childcare_total']['female'] += $r['childcare_total']['female'];
    $gradeTotals['private_nonformal_total']['male'] += $r['private_nonformal_total']['male'];
    $gradeTotals['private_nonformal_total']['female'] += $r['private_nonformal_total']['female'];
    $gradeTotals['pmj_total']['male'] += $r['pmj_total']['male'];
    $gradeTotals['pmj_total']['female'] += $r['pmj_total']['female'];
    $gradeTotals['grand_total'] += $r['grand_total'];
    $gradeTotals['grand_total_male'] += $r['grand_total_male'];
    $gradeTotals['grand_total_female'] += $r['grand_total_female'];
}

// ก็อปมาจาก fmt_num() ใน public_report_data.php ตรง ๆ (ไม่ include ไฟล์นั้นเพราะตัวแปรชื่อชนกันตามที่
// อธิบายไว้ข้างบน) — ถ้าแก้ตัวใดตัวหนึ่งต้องแก้อีกตัวให้ตรงกันด้วย
function fmt_num($v): string
{
    if ($v === null || $v === '') {
        return '—';
    }
    return number_format((float)$v);
}
