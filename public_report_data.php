<?php
/**
 * ข้อมูล+ฟังก์ชันที่ใช้ร่วมกันระหว่าง public_report.php (หน้ากราฟ) และ public_report_table.php
 * (หน้าตารางสรุปยอดรวม) — แยกออกมาเป็นไฟล์เดียวเพื่อไม่ให้ query/logic คำนวณยอดซ้ำกันสองที่
 * (เดิมทั้งหมดนี้อยู่รวมกับ HTML ในไฟล์เดียว ก่อนแยกหน้ากราฟ/ตารางตามคำขอผู้ใช้งาน 2026-08-29)
 *
 * ทั้งสองหน้าที่ include ไฟล์นี้**ไม่เรียก `Auth::requireLogin()`** เจตนา — เปิดเผยต่อสาธารณะ
 * สำหรับศึกษาธิการจังหวัด/ผู้ว่าราชการจังหวัด/ผู้สนใจทั่วไป แสดงเฉพาะยอดรวมระดับจังหวัด
 * แยกตามมิติ สังกัด/หน่วยงาน, ต้นสังกัด, อำเภอ — ไม่มีข้อมูลรายสถานศึกษา/รายบุคคล
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

$dimensions = [
    'agency_name' => 'สังกัด/หน่วยงาน',
    'department'  => 'ต้นสังกัด',
    'amphoe'      => 'อำเภอ',
];
$selectedDimension = $_GET['dim'] ?? 'agency_name';
if (!isset($dimensions[$selectedDimension])) {
    $selectedDimension = 'agency_name';
}

// ใช้ต่อ query string เดิม (ปี+มิติที่เลือก) เวลาสลับไปหน้ากราฟ/ตารางอีกหน้า ผ่านเมนูด้านซ้าย
$navQuery = http_build_query(['year' => $selectedYear, 'dim' => $selectedDimension]);

// "ครูผู้สอน" (ฟอร์ม 10.1) — เฉพาะกลุ่ม "ครูที่ปฏิบัติการสอน(1)" 4 ตำแหน่งย่อย (ข้าราชการ, พนักงาน,
// ครูประจำตามสัญญา, ครูพิเศษ) ไม่รวมผู้บริหาร (ผอ.รร./รอง ผอ.รร.) และไม่รวมบุคลากรสนับสนุน — คัดลอก
// มาจาก public_teacher_grade_table_data.php ตรง ๆ (ตรวจ column_path เทียบ reference_templates/
// 10_ข้อมูลครูV2.xlsx ด้วยสคริปต์อัตโนมัติแล้วที่นั่น) ใช้ตัวเดียวกันที่นี่เพื่อให้ยอดรวม "จำนวน
// ผู้สอน" ในหน้านี้ตรงกับตารางครูผู้สอน (public_teacher_grade_table.php) เป๊ะ — เพิ่มเมื่อ 2026-08-29
// (ก่อนหน้านี้ metric นี้เคยรวมทุกตำแหน่งของฟอร์ม 10.1 รวมผู้บริหาร/บุคลากรสนับสนุนด้วย ตัวเลขเลย
// ไม่ตรงกับตารางครูผู้สอน ผู้ใช้ขอให้แก้ให้สอดคล้องกัน)
// ฟอร์ม 14 ("14.ข้อมูลศูนย์พัฒนาเด็กเล็ก") มีบางแถวที่ "ต้นสังกัด" ไม่ใช่ ศพด. จริง (เช่น สถานศึกษาที่
// กรอกฟอร์ม 4 อยู่แล้วแต่หน่วยงานกรอกฟอร์ม 14 ซ้ำมาด้วยโดยเข้าใจผิด) ถ้ารวมแถวเหล่านั้นเข้าไปด้วยจะนับ
// ผู้เรียนซ้ำกับตารางที่ 4 — ผู้ใช้ยืนยันว่าคอลัมน์ "รูปแบบการศึกษา" (education_form, มาจากทำเนียบ
// โรงเรียนเท่านั้น ไม่ใช่คอลัมน์ในไฟล์สำรวจ) ของศูนย์พัฒนาเด็กเล็กจริงจะเป็นค่า "ศูนย์พัฒนาเด็ก" เป๊ะ
// ใช้กรองแถวฟอร์ม 14 ก่อนรวมยอดผู้เรียนทุกจุด (เพิ่มเมื่อ 2026-08-30 ตามคำขอผู้ใช้งาน) — แถวที่ทำเนียบ
// ยังไม่มีคอลัมน์นี้ (migration 005 ยังไม่รัน) หรือจับคู่ school_code ไม่ได้เลย จะถูกตัดออกไปด้วย
// (ปลอดภัยไว้ก่อน ไม่นับถ้าพิสูจน์ไม่ได้ว่าเป็น ศพด. จริง)
$onlyChildcareCenterRows = static function (array $row): bool {
    return trim((string)($row['education_form'] ?? '')) === 'ศูนย์พัฒนาเด็ก';
};

$teachingColumns = [
    'ครูที่ปฏิบัติการสอน(1) / ข้าราชการ(2) / ชาย', 'ครูที่ปฏิบัติการสอน(1) / ข้าราชการ(2) / หญิง',
    'ครูที่ปฏิบัติการสอน(1) / พนักงาน(3) / ชาย', 'ครูที่ปฏิบัติการสอน(1) / พนักงาน(3) / หญิง',
    'ครูที่ปฏิบัติการสอน(1) / ครูประจำตามสัญญา(4) / ชาย', 'ครูที่ปฏิบัติการสอน(1) / ครูประจำตามสัญญา(4) / หญิง',
    'ครูที่ปฏิบัติการสอน(1) / ครูพิเศษ(5) / ชาย', 'ครูที่ปฏิบัติการสอน(1) / ครูพิเศษ(5) / หญิง',
];

// ตัวเลขหลักที่แสดงในหน้านี้ — คัดเฉพาะรายการที่ตรวจสอบกับ reference_templates/ ด้วยมือแล้วว่า
// ปลอดภัยต่อการรวมยอด (ไม่มีคอลัมน์ "รวม" ปนอยู่ในชีทเดียวกับคอลัมน์ย่อย ไม่งั้นจะรวมยอดซ้ำสอง) —
// ยังไม่ครบทั้ง 15 ฟอร์มโดยตั้งใจ เพิ่มรายการอื่นได้ทีหลังถ้าตรวจสอบโครงสร้างสมชีทนั้นแล้ว
$metrics = [
    'schools' => ['label' => 'จำนวนสถานศึกษา (แห่ง)'],
    'students' => ['label' => 'จำนวนนักเรียน/ผู้เรียน (คน)', 'sheets' => [
        ['4_students', '4.จำนวนผู้เรียน', null],
        // เด็กเล็กในศูนย์พัฒนาเด็กเล็ก/สถานพัฒนาเด็กปฐมวัย (ฟอร์ม 14) ไม่ได้กรอกในฟอร์ม 4 — รวมเข้า
        // มาด้วยตามคำขอผู้ใช้งาน (2026-08-29) ต้องระบุคอลัมน์เจาะจง (ไม่ใช่ทุกคอลัมน์) เพราะชีทนี้มี
        // คอลัมน์จำนวนครู/ผู้ดูแลเด็กปนอยู่ด้วย ถ้ารวมทุกคอลัมน์จะเอาจำนวนครูมาบวกกับจำนวนเด็กผิด ๆ —
        // **ห้ามใช้คอลัมน์ "รวมทั้งสิ้น" เด็ดขาด** (แก้เมื่อ 2026-08-30 ตามคำสั่งผู้ใช้งานชัดเจน) เพราะ
        // เป็นตัวเลขที่หน่วยงานพิมพ์รวมเอง เสี่ยงบวกเลขผิดได้ — ใช้ผลรวมคอลัมน์ "เด็กเล็ก / อายุ 2-5
        // ปี / เพศ" (8 คอลัมน์ ระบบมาแยกเป็นก้อนๆ ให้อยู่แล้ว ไม่ต้องพึ่งการบวกเลขของหน่วยงานเอง) ชุด
        // เดียวกับที่ $genderSheets ด้านล่างและ public_school_grade_table_data.php ใช้ ให้ยอดรวมตรง
        // กันทุกจุดในระบบเสมอ (ไม่มีจุดไหนอ้างอิงคอลัมน์ "รวม"/"รวมทั้งสิ้น" ของฟอร์มไหนเป็นแหล่งข้อมูล
        // โดยตรงอีกต่อไปทั้งระบบ ณ วันที่แก้)
        // เฉพาะแถวที่ "รูปแบบการศึกษา" (จากทำเนียบโรงเรียน) = "ศูนย์พัฒนาเด็ก" เท่านั้น (ดู
        // $onlyChildcareCenterRows ด้านบน) — แถวอื่นในชีทเดียวกันซ้ำกับตารางที่ 4 (เพิ่มเมื่อ 2026-08-30)
        ['14_childcare_centers', '14.ข้อมูลศูนย์พัฒนาเด็กเล็ก', [
            'เด็กเล็ก / อายุ 2 ปี / ชาย', 'เด็กเล็ก / อายุ 2 ปี / หญิง',
            'เด็กเล็ก / อายุ 3 ปี / ชาย', 'เด็กเล็ก / อายุ 3 ปี / หญิง',
            'เด็กเล็ก / อายุ 4 ปี / ชาย', 'เด็กเล็ก / อายุ 4 ปี / หญิง',
            'เด็กเล็ก / อายุ 5 ปี / ชาย', 'เด็กเล็ก / อายุ 5 ปี / หญิง',
        ], $onlyChildcareCenterRows],
        // โรงเรียนเอกชนนอกระบบ (ฟอร์ม 15) ก็ไม่ได้กรอกฟอร์ม 4 เช่นกัน — รวมเข้ามาด้วยตามคำขอ
        // ผู้ใช้งาน (2026-08-29) เฉพาะคอลัมน์ "จำนวนผู้เรียน"/"จำนวนนักเรียน" เท่านั้น (ไม่รวม
        // คอลัมน์ผู้สอน/โต๊ะครูในชีทเดียวกัน) — ชีท "สช.วิชาชีพ-ครู-นร." ใช้แค่คอลัมน์ "รวม" อย่าง
        // เดียว เพราะ "ชาย"/"หญิง"/"รวม" เป็น 3 คอลัมน์ที่ค่า "รวม" ก็คือผลบวกของอีก 2 คอลัมน์อยู่
        // แล้ว (ไม่ได้แยก split_last แบบฟอร์มอื่น — ดู forms/registry.php) ถ้ารวมทั้ง 3 จะนับซ้ำสอง
        // — ชีท "สช.วิชาชีพ" ไม่มีคอลัมน์ตัวเลขเลย (value_type=text) จึงไม่เกี่ยวกับตัวเลขนี้
        ['15_private_nonformal', '15.1', ['จำนวนผู้เรียน / ชาย', 'จำนวนผู้เรียน / หญิง']],
        ['15_private_nonformal', '15.2', ['จำนวนผู้เรียน / ชาย', 'จำนวนผู้เรียน / หญิง']],
        ['15_private_nonformal', '15.3', ['จำนวนผู้เรียน / ชาย', 'จำนวนผู้เรียน / หญิง']],
        // ใช้คอลัมน์ ชาย/หญิง แยกกันแทน "รวม" (แก้เมื่อ 2026-08-29 พร้อมกับจุดเดียวกันฝั่งครู
        // ด้านล่าง — ชีทนี้มีคอลัมน์ชาย/หญิงแยกให้จริง ผลรวมไม่เปลี่ยนไม่ว่าจะใช้แบบไหนเพราะ
        // "รวม" = ชาย+หญิงอยู่แล้ว แค่เปลี่ยนให้สอดคล้องกับ $genderSheets ด้านล่างที่ใช้แบบนี้อยู่แล้ว)
        ['15_private_nonformal', 'สช.วิชาชีพ-ครู-นร.', ['จำนวนนักเรียน / ชาย', 'จำนวนนักเรียน / หญิง']],
        // ผู้เรียน กศน. (ฟอร์ม 11) ก็ไม่ได้กรอกฟอร์ม 4 เช่นกัน — รวมเข้ามาด้วยตามคำขอผู้ใช้งาน
        // (2026-08-29) ชีท 11.1 ไม่มีคอลัมน์ "รวม" ปนอยู่เลย (ตรวจ reference_templates/ แล้ว มีแต่
        // คอลัมน์กิจกรรมการศึกษา×เพศ ไม่มี subtotal) จึงส่ง null รวมทุกคอลัมน์ได้เลยเหมือนฟอร์ม 4/10.1
        ['11_nfe', '11.1', null],
        // ผู้เรียนพิการ กศน. (ฟอร์ม 11.2) — เพิ่มเมื่อ 2026-09-01 ตามคำขอผู้ใช้งาน — **11.1 กับ 11.2
        // เป็นคนละกลุ่มผู้เรียนที่ไม่ทับซ้อนกัน ไม่ใช่มองข้อมูลชุดเดียวกันคนละมิติแบบ 11.2/11.3**
        // (ชื่อเต็ม 11.1 คือ "จำนวนผู้เรียนปกติ..." ส่วน 11.2 คือ "จำนวนผู้เรียนพิการ..." — "ปกติ" ในชื่อ
        // 11.1 เป็นตัวบ่งชี้ชัดว่าตั้งใจแยกเป็น 2 กลุ่มคนละแบบฟอร์ม ไม่ใช่ 11.2 เป็น subset ของ 11.1 —
        // แพทเทิร์นเดียวกับคู่ 11.4/11.5 ที่แยก "ปกติ"/"พิการ" สำหรับผู้สำเร็จการศึกษา) จึงบวกรวมกันได้
        // โดยไม่นับซ้ำ — โครงสร้างคอลัมน์เหมือน 11.1 ทุกประการ (ตรวจแล้ว) จึงส่ง null รวมทุกคอลัมน์ได้
        // เช่นกัน — **11.2 ยังคงอยู่ใน metric 'disability' ด้านล่างด้วย** (ไม่ใช่ย้ายมาที่นี่แทน) เพราะ
        // เป็นคนละคำถาม: metric นี้ตอบ "มีผู้เรียนทั้งหมดกี่คน" ส่วน 'disability' ตอบ "ในนั้นมีผู้พิการ
        // กี่คน" — ผู้เรียนกลุ่มเดียวกันตอบ 2 คำถามพร้อมกันได้ ไม่ใช่การนับซ้ำ
        ['11_nfe', '11.2', null],
        // เด็กที่สถานรับเลี้ยงเด็กเอกชนสังกัด พมจ. (ฟอร์ม 16.2) ก็ไม่ได้กรอกฟอร์ม 4 เช่นกัน — รวมเข้า
        // มาด้วยตามคำขอผู้ใช้งาน (2026-08-30) เฉพาะคอลัมน์ "จำนวนเด็ก..." เท่านั้น (ไม่รวมคอลัมน์
        // "จำนวนผู้ดูแลเด็ก..." ในชีทเดียวกัน ซึ่งเป็นข้อมูลครู/ผู้ดูแล ไม่ใช่ผู้เรียน)
        ['16_pmj', 'พมจ-16.2', [
            'จำนวนเด็กช่วงอายุ 0-2 ปี / ชาย', 'จำนวนเด็กช่วงอายุ 0-2 ปี / หญิง',
            'จำนวนเด็กช่วงอายุ 3-5 ปี / ชาย', 'จำนวนเด็กช่วงอายุ 3-5 ปี / หญิง',
        ]],
    ]],
    'classrooms' => ['label' => 'จำนวนห้องเรียน (ห้อง)', 'sheets' => [
        ['3_classrooms', '3.จำนวนห้องเรียน'],
    ]],
    'teachers' => ['label' => 'จำนวนครูผู้สอน (คน)', 'sheets' => [
        // เฉพาะกลุ่ม "ครูที่ปฏิบัติการสอน" ($teachingColumns ด้านบน) ไม่รวมผู้บริหาร/บุคลากร
        // สนับสนุน ให้ตรงกับตารางครูผู้สอน (public_teacher_grade_table.php) — แก้เมื่อ 2026-08-29
        ['10_teachers', '10.1ทุกสังกัด', $teachingColumns],
        // ครู/ผู้ดูแลเด็กของศูนย์พัฒนาเด็กเล็ก/สถานพัฒนาเด็กปฐมวัย (ฟอร์ม 14) ไม่ได้กรอกฟอร์ม 10 เลย
        // — รวมเข้ามาด้วยตามคำขอผู้ใช้งาน (2026-08-29) เฉพาะ 2 คอลัมน์นี้เท่านั้น (ไม่รวมคอลัมน์
        // เด็กเล็ก หรือคอลัมน์แยกวุฒิการศึกษาของครู ซึ่งเป็นการแจกแจงซ้ำของ 2 คอลัมน์นี้อยู่แล้ว
        // ถ้ารวมด้วยจะนับครูคนเดิมซ้ำสอง)
        // เฉพาะแถวที่ "รูปแบบการศึกษา" = "ศูนย์พัฒนาเด็ก" เท่านั้น (เหตุผลเดียวกับ metric "students"
        // ด้านบน — แถวอื่นในชีทเดียวกันซ้ำกับฟอร์ม 10 — เพิ่มเมื่อ 2026-08-30)
        ['14_childcare_centers', '14.ข้อมูลศูนย์พัฒนาเด็กเล็ก', [
            'จำนวนครู/ผู้ดูแลเด็ก (คน) / ชาย',
            'จำนวนครู/ผู้ดูแลเด็ก (คน) / หญิง',
        ], $onlyChildcareCenterRows],
        // โรงเรียนเอกชนนอกระบบ (ฟอร์ม 15) — เหตุผลเดียวกับ "จำนวนนักเรียน" ข้างบน ใช้เฉพาะคอลัมน์
        // ผู้สอน/โต๊ะครู (ไม่รวมคอลัมน์ผู้เรียนในชีทเดียวกัน) — ใช้คอลัมน์ ชาย/หญิง แยกกันทุกชีทรวมถึง
        // "สช.วิชาชีพ-ครู-นร." ด้วย (แก้เมื่อ 2026-08-29 — เดิมเข้าใจผิดว่าชีทนี้มีแค่คอลัมน์ "รวม"
        // อย่างเดียว ที่จริงมีคอลัมน์ชาย/หญิงแยกให้ด้วย ตรวจแล้วจาก reference_templates/ ใช้ชุดเดียว
        // กับ $teacherGenderSheets ด้านล่างและ public_teacher_grade_table_data.php ให้ยอดรวมตรงกัน)
        ['15_private_nonformal', '15.1', ['จำนวนผู้สอน / ชาย', 'จำนวนผู้สอน / หญิง']],
        ['15_private_nonformal', '15.2', ['จำนวนโต๊ะครู / ชาย', 'จำนวนโต๊ะครู / หญิง']],
        ['15_private_nonformal', '15.3', ['จำนวนผู้สอน / ชาย', 'จำนวนผู้สอน / หญิง']],
        ['15_private_nonformal', 'สช.วิชาชีพ-ครู-นร.', ['จำนวนครู / ชาย', 'จำนวนครู / หญิง']],
        // ผู้ดูแลเด็กที่สถานรับเลี้ยงเด็กเอกชนสังกัด พมจ. (ฟอร์ม 16.2) ก็ไม่ได้กรอกฟอร์ม 10 เช่นกัน —
        // รวมเข้ามาด้วยตามคำขอผู้ใช้งาน (2026-08-30) เฉพาะคอลัมน์ "จำนวนผู้ดูแลเด็ก..." เท่านั้น
        // (ไม่รวมคอลัมน์ "จำนวนเด็ก..." ในชีทเดียวกัน ซึ่งใช้ไปแล้วในตัวเลข "จำนวนผู้เรียน" ข้างบน)
        ['16_pmj', 'พมจ-16.2', [
            'จำนวนผู้ดูแลเด็กช่วงอายุ 0-2 ปี / ชาย', 'จำนวนผู้ดูแลเด็กช่วงอายุ 0-2 ปี / หญิง',
            'จำนวนผู้ดูแลเด็กช่วงอายุ  3-5 ปี / ชาย', 'จำนวนผู้ดูแลเด็กช่วงอายุ  3-5 ปี / หญิง',
        ]],
    ]],
    'dropout' => ['label' => 'นักเรียนออกกลางคัน (คน)', 'sheets' => [
        ['7_dropout', '7.1ออกกลางคัน ประถม '],
        ['7_dropout', '7.2ออกกลางคัน มัธยมต้น'],
        ['7_dropout', '7.3ออกกลางคัน มัธยมปลาย'],
        ['7_dropout', '7.4ออกกลางคัน วิชาชีพ'],
        ['7_dropout', '7.4ออกกลางคัน ปวส.'],
        ['7_dropout', '7.5ออกกลางคัน ป.ตรี'],
    ]],
    'disability' => ['label' => 'นักเรียนพิการ (คน)', 'sheets' => [
        ['8_disability', '8.1 นักเรียนพิการ'],
        // ผู้เรียนพิการ กศน. (ฟอร์ม 11.2) — เหตุผลเดียวกับ 11.1 ข้างบน ไม่มีคอลัมน์ "รวม" ปนอยู่
        // เลย (ตรวจ reference_templates/ แล้ว โครงสร้างเหมือน 11.1 ทุกประการ) จึงรวมทุกคอลัมน์ได้
        ['11_nfe', '11.2', null],
    ]],
    'disadvantaged' => ['label' => 'นักเรียนด้อยโอกาส (คน)', 'sheets' => [
        ['5_disadvantaged', '5.1 นักเรียนด้อยโอกาส'],
    ]],
    // ผู้สำเร็จการศึกษา (ฟอร์ม 9.1) — ตรวจ reference_templates/ แล้ว ทุกคอลัมน์เป็นระดับชั้น×เพศ
    // ล้วน ไม่มีคอลัมน์ "รวม" ปนอยู่เลย (ต่างจาก 9.3-9.5 ที่มีคอลัมน์รวมปนอยู่ — ดูส่วน
    // "สถานะหลังจบการศึกษา" ด้านล่างที่ต้องกรองคอลัมน์รวมออกก่อนใช้) จึงรวมทุกคอลัมน์ได้เลย ไม่ใช้
    // 9.2 (สายอาชีพ) หรือ 9.6 (มีงานทำ) เพราะเป็นข้อมูลคนละมิติ/ซ้ำกับ 9.1 อยู่แล้วบางส่วน
    'graduates' => ['label' => 'จำนวนผู้สำเร็จการศึกษา (คน)', 'sheets' => [
        ['9_graduates', '9.1ผู้สำเร็จการศึกษา', null],
    ]],
];

// รวมยอด 1 metric แยกตาม 1 มิติ — ใช้ทั้งกับตารางหลัก (ตามมิติที่เลือกบน dropdown) และกราฟแท่ง
// ด้านล่างที่ตรึงมิติไว้ตายตัว (ต้นสังกัด/อำเภอ) โดยไม่ขึ้นกับ dropdown
function compute_metric_totals(Reporting $reporting, string $key, array $metricDef, string $dimension, int $academicYear): array
{
    if ($key === 'schools') {
        return $reporting->schoolCountByDimension($dimension, $academicYear);
    }
    $totals = [];
    foreach ($metricDef['sheets'] as $sheet) {
        [$formKey, $sheetName] = $sheet;
        $onlyColumns = $sheet[2] ?? null; // null = sum every value column (see groupedTotal())
        $rowFilter = $sheet[3] ?? null; // optional predicate — see groupedTotalForColumns() in Reporting.php
        $sheetTotals = $onlyColumns !== null
            ? $reporting->groupedTotalForColumns($formKey, $sheetName, $onlyColumns, $dimension, $academicYear, $rowFilter)
            : $reporting->groupedTotal($formKey, $sheetName, $dimension, $academicYear);
        foreach ($sheetTotals as $g => $v) {
            $totals[$g] = ($totals[$g] ?? 0) + $v;
        }
    }
    return $totals;
}

$dataByMetric = [];
$groupSet = [];
foreach ($metrics as $key => $m) {
    $totals = compute_metric_totals($reporting, $key, $m, $selectedDimension, $selectedYear);
    $dataByMetric[$key] = $totals;
    foreach (array_keys($totals) as $g) {
        $groupSet[$g] = true;
    }
}

$groups = array_keys($groupSet);
sort($groups, SORT_STRING | SORT_FLAG_CASE);
// ย้าย "ไม่ระบุ" ไปแถวท้ายสุดเสมอถ้ามี (ไม่ปนอยู่กลางรายชื่อที่เรียงตามตัวอักษร)
$unknownIdx = array_search('ไม่ระบุ', $groups, true);
if ($unknownIdx !== false) {
    unset($groups[$unknownIdx]);
    $groups = array_values($groups);
    $groups[] = 'ไม่ระบุ';
}

// --- ข้อมูลสำหรับกราฟ ---

// จำนวนนักเรียนแยกตามต้นสังกัดและอำเภอ — ตรึงมิติไว้ตายตัว (ไม่ขึ้นกับ dropdown ด้านบน) เพราะ
// ผู้ใช้งานอยากเห็นทั้ง 2 มิตินี้พร้อมกันเสมอ ไม่ใช่แค่มิติที่เลือกอยู่
$studentsByDept = compute_metric_totals($reporting, 'students', $metrics['students'], 'department', $selectedYear);
$studentsByAmphoe = compute_metric_totals($reporting, 'students', $metrics['students'], 'amphoe', $selectedYear);
arsort($studentsByDept);
arsort($studentsByAmphoe);

// จำนวนนักเรียนแยกตามรูปแบบการศึกษา (เช่น "ในระบบ") — มาจากทำเนียบโรงเรียน (schools_master,
// migration 005) ไม่มีคอลัมน์นี้ในไฟล์สำรวจฟอร์มไหนเลย เหมือนต้นสังกัด — เพิ่มเมื่อ 2026-08-30 ตาม
// คำขอผู้ใช้งาน ถ้าทำเนียบยังไม่มีคอลัมน์นี้ (migration 005 ยังไม่รัน) หรือโรงเรียนไหนไม่มีค่านี้ใน
// ทำเนียบ จะรวมอยู่ใต้ "ไม่ระบุ" (พฤติกรรมเดียวกับมิติอื่นทุกมิติ — ดู groupedTotalForColumns())
$studentsByEducationForm = compute_metric_totals($reporting, 'students', $metrics['students'], 'education_form', $selectedYear);
arsort($studentsByEducationForm);

// สัดส่วนนักเรียนชาย:หญิง — ใช้คอลัมน์ชุดเดียวกับตัวเลข "จำนวนนักเรียน" ด้านบนทุกประการ แต่ใช้คอลัมน์
// แยกเพศดิบแทนคอลัมน์ "รวม"/"รวมทั้งสิ้น" (ซึ่งไม่มีเพศให้แยก) — ดู Reporting::genderTotalsForColumns
$genderSheets = [
    ['4_students', '4.จำนวนผู้เรียน', null],
    // เฉพาะแถว "รูปแบบการศึกษา" = "ศูนย์พัฒนาเด็ก" เท่านั้น (เหตุผลเดียวกับ $metrics['students'] ด้านบน)
    ['14_childcare_centers', '14.ข้อมูลศูนย์พัฒนาเด็กเล็ก', [
        'เด็กเล็ก / อายุ 2 ปี / ชาย', 'เด็กเล็ก / อายุ 2 ปี / หญิง',
        'เด็กเล็ก / อายุ 3 ปี / ชาย', 'เด็กเล็ก / อายุ 3 ปี / หญิง',
        'เด็กเล็ก / อายุ 4 ปี / ชาย', 'เด็กเล็ก / อายุ 4 ปี / หญิง',
        'เด็กเล็ก / อายุ 5 ปี / ชาย', 'เด็กเล็ก / อายุ 5 ปี / หญิง',
    ], $onlyChildcareCenterRows],
    ['15_private_nonformal', '15.1', ['จำนวนผู้เรียน / ชาย', 'จำนวนผู้เรียน / หญิง']],
    ['15_private_nonformal', '15.2', ['จำนวนผู้เรียน / ชาย', 'จำนวนผู้เรียน / หญิง']],
    ['15_private_nonformal', '15.3', ['จำนวนผู้เรียน / ชาย', 'จำนวนผู้เรียน / หญิง']],
    ['15_private_nonformal', 'สช.วิชาชีพ-ครู-นร.', ['จำนวนนักเรียน / ชาย', 'จำนวนนักเรียน / หญิง']],
    ['11_nfe', '11.1', null],
    // 11.1+11.2 ไม่ทับซ้อนกัน (ผู้เรียนปกติ vs ผู้เรียนพิการ) — ดูเหตุผลเต็มที่ $metrics['students'] ด้านบน
    ['11_nfe', '11.2', null],
    ['16_pmj', 'พมจ-16.2', [
        'จำนวนเด็กช่วงอายุ 0-2 ปี / ชาย', 'จำนวนเด็กช่วงอายุ 0-2 ปี / หญิง',
        'จำนวนเด็กช่วงอายุ 3-5 ปี / ชาย', 'จำนวนเด็กช่วงอายุ 3-5 ปี / หญิง',
    ]],
];
$genderMale = 0.0;
$genderFemale = 0.0;
foreach ($genderSheets as $gs) {
    [$formKey, $sheetName, $onlyColumns] = $gs;
    $rowFilter = $gs[3] ?? null;
    $g = $reporting->genderTotalsForColumns($formKey, $sheetName, $onlyColumns, $selectedYear, $rowFilter);
    $genderMale += $g['male'];
    $genderFemale += $g['female'];
}
$genderTotal = $genderMale + $genderFemale;

// สัดส่วนครูชาย:หญิง — เหตุผลเดียวกับสัดส่วนนักเรียนข้างบน ใช้ชุดชีทเดียวกับตัวเลข "จำนวนครู/
// บุคลากร" แต่สลับไปใช้คอลัมน์แยกเพศดิบแทนคอลัมน์ "รวม"/คอลัมน์เดี่ยวของฟอร์ม 10.1 (ไม่มี "รวม"
// ปนอยู่จึงส่ง null ให้สแกนทุกคอลัมน์ได้เลยเหมือนฟอร์ม 4)
$teacherGenderSheets = [
    // ใช้ $teachingColumns ตัวเดียวกับ metric "teachers" (ไม่รวมผู้บริหาร/บุคลากรสนับสนุน) ไม่งั้น
    // ยอดรวมชาย+หญิงของกราฟนี้จะไม่เท่ากับ $totalTeachers ที่ใช้ในตารางสรุป/tile อัตราส่วน
    ['10_teachers', '10.1ทุกสังกัด', $teachingColumns],
    // เฉพาะแถว "รูปแบบการศึกษา" = "ศูนย์พัฒนาเด็ก" เท่านั้น (เหตุผลเดียวกับฝั่งผู้เรียนด้านบน)
    ['14_childcare_centers', '14.ข้อมูลศูนย์พัฒนาเด็กเล็ก', [
        'จำนวนครู/ผู้ดูแลเด็ก (คน) / ชาย', 'จำนวนครู/ผู้ดูแลเด็ก (คน) / หญิง',
    ], $onlyChildcareCenterRows],
    ['15_private_nonformal', '15.1', ['จำนวนผู้สอน / ชาย', 'จำนวนผู้สอน / หญิง']],
    ['15_private_nonformal', '15.2', ['จำนวนโต๊ะครู / ชาย', 'จำนวนโต๊ะครู / หญิง']],
    ['15_private_nonformal', '15.3', ['จำนวนผู้สอน / ชาย', 'จำนวนผู้สอน / หญิง']],
    ['15_private_nonformal', 'สช.วิชาชีพ-ครู-นร.', ['จำนวนครู / ชาย', 'จำนวนครู / หญิง']],
    ['16_pmj', 'พมจ-16.2', [
        'จำนวนผู้ดูแลเด็กช่วงอายุ 0-2 ปี / ชาย', 'จำนวนผู้ดูแลเด็กช่วงอายุ 0-2 ปี / หญิง',
        'จำนวนผู้ดูแลเด็กช่วงอายุ  3-5 ปี / ชาย', 'จำนวนผู้ดูแลเด็กช่วงอายุ  3-5 ปี / หญิง',
    ]],
];
$teacherGenderMale = 0.0;
$teacherGenderFemale = 0.0;
foreach ($teacherGenderSheets as $tgs) {
    [$formKey, $sheetName, $onlyColumns] = $tgs;
    $rowFilter = $tgs[3] ?? null;
    $tg = $reporting->genderTotalsForColumns($formKey, $sheetName, $onlyColumns, $selectedYear, $rowFilter);
    $teacherGenderMale += $tg['male'];
    $teacherGenderFemale += $tg['female'];
}
$teacherGenderTotal = $teacherGenderMale + $teacherGenderFemale;

// อัตราส่วนนักเรียนต่อครู — ยอดรวมทั้งจังหวัด ไม่ขึ้นกับมิติที่เลือก (ผลรวมทุกกลุ่มเท่ากันไม่ว่าจะ
// แยกตามมิติไหน) จึงใช้ $dataByMetric ที่คำนวณไว้แล้วสำหรับตารางหลักได้เลย ไม่ต้อง query ซ้ำ
$totalStudents = array_sum($dataByMetric['students']);
$totalTeachers = array_sum($dataByMetric['teachers']);
$studentTeacherRatio = $totalTeachers > 0 ? $totalStudents / $totalTeachers : null;

// นักเรียนออกกลางคัน แยกตามสาเหตุ — รวมทั้ง 6 ชีท (แยกตามช่วงชั้นเดิม) เข้าด้วยกัน ตัดมิติ "ชั้นปี"
// (level แรก) กับ "เพศ" (level สุดท้าย) ออก เหลือแค่ "สาเหตุ" — ดู Reporting::sumByColumnPathParts
$dropoutByReason = [];
foreach ($metrics['dropout']['sheets'] as [$formKey, $sheetName]) {
    foreach ($reporting->sumByColumnPathParts($formKey, $sheetName, 1, 1, $selectedYear) as $reason => $v) {
        $dropoutByReason[$reason] = ($dropoutByReason[$reason] ?? 0) + $v;
    }
}
arsort($dropoutByReason);

// นักเรียนพิการ แยกตามประเภทความพิการ — ชีท 8.2 มีคอลัมน์ "รวม" ปนท้ายตาราง (ตัด dropLast=1 ออก
// พอสำหรับคอลัมน์แยกเพศปกติ แต่คอลัมน์ "รวม" เป็น 1 ระดับเดียวจะเหลือ 0 ระดับหลังตัด ถูกข้ามอัตโนมัติ)
$disabilityByType = $reporting->sumByColumnPathParts('8_disability', '8.2 ประเภทความพิการ', 0, 1, $selectedYear);
// รวมฟอร์ม 11.3 (ผู้เรียนพิการ กศน. แยกตามประเภทความพิการ) เข้ากราฟเดียวกัน (เพิ่มเมื่อ 2026-09-01
// ตามคำขอผู้ใช้งาน) — ตรวจ reference_templates/ ทั้ง 2 ไฟล์ด้วย xlsx dump แล้ว: 10 ประเภทความพิการ
// ของฟอร์ม 11.3 ใช้ชื่อเดียวกันเป๊ะกับฟอร์ม 8.2 (ทางการมองเห็น, ทางการได้ยิน, สติปัญญา, ร่างกาย/
// สุขภาพ, ทางการเรียนรู้, ทางการพูดและภาษา, พฤติกรรมหรืออารมณ์, ออทิสติก, พิการซ้ำซ้อน, อื่น ๆ ไม่
// ระบุ) รวมยอดผ่าน key ชื่อเดียวกันได้ตรง ๆ — **ข้อยกเว้น 2 ประเภท**: ฟอร์ม 8.2 แตกละเอียดกว่า มี
// คอลัมน์ย่อย "ตาบอด"/"เลือนราง" ใต้ "ทางการมองเห็น" และ "หูหนวก"/"หูตึง" ใต้ "ทางการได้ยิน" (ฟอร์ม
// 11.3 ไม่มีคอลัมน์ย่อยพวกนี้ มีแค่ระดับเดียว) ทำให้กราฟจะมีแท่ง "ทางการมองเห็น"/"ทางการได้ยิน" (จาก
// ฟอร์ม 11.3 ล้วน ๆ) แยกต่างหากจากแท่ง "ทางการมองเห็น / ตาบอด" ฯลฯ (จากฟอร์ม 8.2) ไม่ได้รวมเป็นแท่ง
// เดียวกัน เพราะความละเอียดของข้อมูลต้นทางไม่เท่ากันระหว่าง 2 ฟอร์ม — ไม่ได้ยุบระดับย่อยของ 8.2 ทิ้ง
// เพื่อรวมให้เข้ากัน (ผู้ใช้งานไม่ได้ขอ) — **ไม่ได้เพิ่มเข้า $metrics['disability'] (ยอดรวม/การ์ด
// หน้าภาพรวม/ตารางสรุป)** เพราะจะนับซ้ำกับฟอร์ม 11.2 ที่ metric นั้นใช้อยู่แล้ว (คนพิการกลุ่มเดียวกัน
// นับจากคนละมิติ — 11.2 แยกตามกิจกรรมการศึกษา, 11.3 แยกตามประเภทความพิการ) ผู้ใช้งานยืนยันแล้วให้
// เข้าเฉพาะกราฟนี้เท่านั้น
foreach ($reporting->sumByColumnPathParts('11_nfe', '11.3', 0, 1, $selectedYear) as $type => $v) {
    $disabilityByType[$type] = ($disabilityByType[$type] ?? 0) + $v;
}
arsort($disabilityByType);

// สถานะหลังจบการศึกษา แยกตามระดับชั้น (ป.6/ม.3/ม.6 — ชีท 9.3/9.4/9.5) — ต่างจาก 8.2 ข้างบน ชีทพวกนี้
// มีคอลัมน์ "รวม" ปนอยู่ที่ต้น ๆ ตาราง (ก่อนคอลัมน์ปลายทางย่อย) ไม่ใช่ท้ายตารางแบบ 8.2 คือ "ทั้งหมด"
// (= ที่จบ + ที่ไม่จบ) กับ "ที่จบการศึกษา"/"ที่ไม่จบการศึกษา" (= ผลรวมของคอลัมน์ปลายทางย่อยทั้งหมดที่
// ตามมา) sumByColumnPathParts ตัดได้แค่ระดับ "เพศ" ท้ายสุด ตัดคอลัมน์รวมเฉพาะเจาะจงไม่ได้ ต้อง unset()
// 3 คีย์นี้ออกเองหลังเรียกฟังก์ชัน ไม่งั้นกราฟจะเอายอดรวมมาปนกับคอลัมน์ปลายทางย่อย ทำให้แท่งกราฟยอดรวม
// สูงเกินจริงเทียบกับแท่งอื่น (ตรวจโครงสร้างจริงจาก reference_templates/ แล้วทั้ง 3 ชีท มีคอลัมน์รวม
// ชื่อเดียวกันทุกชีท) — คอลัมน์ปลายทางย่อยที่เหลือ (เช่น "ศึกษาต่อโรงเรียนเดิม", "ไม่ศึกษาต่อ ทำงาน...")
// ไม่เหมือนกันในแต่ละชีท (ป.6/ม.3/ม.6 มีปลายทางต่างกันตามธรรมชาติ) จึงแสดงเป็น 3 กราฟแยกกัน ไม่รวมเข้า
// กราฟเดียว
$graduateStatusTotalKeys = ['ทั้งหมด', 'ที่จบการศึกษา', 'ที่ไม่จบการศึกษา'];
$graduateStatusP6 = $reporting->sumByColumnPathParts('9_graduates', '9.3 จบ ป.6', 0, 1, $selectedYear);
$graduateStatusM3 = $reporting->sumByColumnPathParts('9_graduates', '9.4 จบ ม.3', 0, 1, $selectedYear);
$graduateStatusM6 = $reporting->sumByColumnPathParts('9_graduates', '9.5 ม.6', 0, 1, $selectedYear);
foreach ($graduateStatusTotalKeys as $totalKey) {
    unset($graduateStatusP6[$totalKey], $graduateStatusM3[$totalKey], $graduateStatusM6[$totalKey]);
}
arsort($graduateStatusP6);
arsort($graduateStatusM3);
arsort($graduateStatusM6);

// 5 อำเภอที่มีอัตรานักเรียนออกกลางคันสูงสุด/ต่ำสุด (% ของนักเรียนทั้งหมดในอำเภอนั้น) — ใช้ยอด
// นักเรียนต่ออำเภอที่คำนวณไว้แล้วด้านบน ($studentsByAmphoe) คู่กับยอดออกกลางคันต่ออำเภอ เพื่อไม่ให้
// อำเภอที่มีนักเรียนเยอะเป็นทุนเดิมดูน่ากังวลเกินจริงเมื่อเทียบกับอำเภอเล็ก ๆ
$dropoutByAmphoe = compute_metric_totals($reporting, 'dropout', $metrics['dropout'], 'amphoe', $selectedYear);
$dropoutRateByAmphoe = [];
foreach ($studentsByAmphoe as $amphoe => $studentCount) {
    if ($amphoe === 'ไม่ระบุ' || $studentCount <= 0) {
        continue; // หารด้วยศูนย์ไม่ได้ และ "ไม่ระบุ" ไม่ใช่อำเภอจริงที่เทียบกันได้
    }
    $dropoutRateByAmphoe[$amphoe] = ($dropoutByAmphoe[$amphoe] ?? 0) / $studentCount * 100;
}
arsort($dropoutRateByAmphoe);
$dropoutRateTop5 = array_slice($dropoutRateByAmphoe, 0, 5, true);
$dropoutRateBottom5 = array_slice(array_reverse($dropoutRateByAmphoe, true), 0, 5, true);

// แนวโน้มจำนวนนักเรียนรายปีการศึกษา — ยอดรวมทั้งจังหวัดของทุกปีที่มีข้อมูล เรียงปีน้อยไปมาก (จะมี
// แค่ 1-2 แท่งถ้าระบบเพิ่งเริ่มเก็บข้อมูลไม่กี่ปี ก็ยังแสดงผลได้ปกติ ไม่พัง รอข้อมูลปีต่อ ๆ ไปสะสม)
$studentsByYear = [];
$yearsAscending = $availableYears;
sort($yearsAscending);
foreach ($yearsAscending as $y) {
    $yearTotals = compute_metric_totals($reporting, 'students', $metrics['students'], 'amphoe', (int)$y);
    $studentsByYear[(string)$y] = array_sum($yearTotals);
}

function fmt_num($v): string
{
    if ($v === null || $v === '') {
        return '—';
    }
    return number_format((float)$v);
}

// เรนเดอร์กราฟแท่งแนวนอน 1 ชุด — ใช้ร่วมกันทุกกราฟแท่งในหน้ากราฟ (สังกัด/อำเภอ/ปีการศึกษา/สาเหตุ/
// ประเภทความพิการ/อัตราออกกลางคัน) ต่างกันแค่ข้อมูลกับวิธี format ค่า (จำนวนคน หรือ เปอร์เซ็นต์)
function render_bar_chart(array $data, callable $formatValue): void
{
    if (!$data) {
        echo '<p class="muted">ยังไม่มีข้อมูล</p>';
        return;
    }
    $max = max(array_map('abs', $data));
    echo '<div class="bar-chart">';
    foreach ($data as $label => $value) {
        $pct = $max > 0 ? abs($value) / $max * 100 : 0;
        $valueLabel = $formatValue($value);
        echo '<div class="bar-row">';
        echo '<div class="bar-label">' . h((string)$label) . '</div>';
        echo '<div class="bar-wrap"><div class="bar-fill" style="width: ' . h(number_format($pct, 2, '.', '')) . '%"'
            . ' title="' . h($label . ': ' . $valueLabel) . '"></div></div>';
        echo '<div class="bar-value">' . h($valueLabel) . '</div>';
        echo '</div>';
    }
    echo '</div>';
}
$fmtPeople = static fn($v) => fmt_num($v) . ' คน';
$fmtPercent = static fn($v) => number_format((float)$v, 1) . '%';

/**
 * ส่วนหัว + topbar + เมนูด้านซ้าย + การ์ดแนะนำ/ตัวกรอง ที่ใช้ร่วมกันทั้ง 2 หน้า — เปิด
 * <div class="report-main"> ทิ้งไว้ให้ผู้เรียกใส่เนื้อหาเฉพาะหน้าต่อ แล้วปิดด้วย render_report_end()
 */
function render_report_start(string $activePage): void
{
    global $availableYears, $selectedYear, $dimensions, $selectedDimension, $navQuery;
    ?>
<!doctype html>
<html lang="th">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>สถิติการศึกษาจังหวัดปัตตานี — ptnedinfo</title>
<link rel="stylesheet" href="public/assets/style.css">
<link rel="stylesheet" href="public/assets/public_report.css">
</head>
<body>
<div class="topbar">
  <a href="public_report.php">ข้อมูลด้านการศึกษาจังหวัดปัตตานี</a>
  <nav>
    <a href="public_report.php?<?= h($navQuery) ?>" class="<?= $activePage === 'charts' ? 'active' : '' ?>">ภาพรวม</a>
    <a href="public_report_table.php?<?= h($navQuery) ?>" class="<?= $activePage === 'table' ? 'active' : '' ?>">ตารางสรุปยอดรวม</a>
    <a href="public_school_grade_table.php?year=<?= h((string)$selectedYear) ?>" class="<?= $activePage === 'grades' ? 'active' : '' ?>">ผู้เรียนรายชั้น</a>
    <a href="public_teacher_grade_table.php?year=<?= h((string)$selectedYear) ?>" class="<?= $activePage === 'teacher_grades' ? 'active' : '' ?>">ครูผู้สอน</a>
    <a href="public_school_search.php?year=<?= h((string)$selectedYear) ?>" class="<?= $activePage === 'search' ? 'active' : '' ?>">ค้นหารหัสสถานศึกษา</a>
    <a href="login.php">เข้าสู่ระบบเจ้าหน้าที่</a>
  </nav>
</div>
<div class="container" style="max-width: 98vw;">
  <div class="report-main">
    <div class="card">
      <h1>สถิติการศึกษาจังหวัดปัตตานี ประจำปีการศึกษา <?= h((string)$selectedYear) ?></h1>
      <p class="muted">สรุปยอดรวมระดับจังหวัดจากข้อมูลที่หน่วยงานทางการศึกษาในจังหวัดส่งกลับผ่านระบบ
        รวบรวมข้อมูลของสำนักงานศึกษาธิการจังหวัดปัตตานี — เป็นตัวเลขสรุประดับสังกัด/อำเภอเท่านั้น
        ไม่มีข้อมูลรายสถานศึกษาหรือรายบุคคล</p>

      <form method="get" class="filter-bar">
        <div class="field">
          <label>ปีการศึกษา</label>
          <select name="year" onchange="this.form.submit()">
            <?php foreach ($availableYears as $y): ?>
              <option value="<?= h((string)$y) ?>" <?= $selectedYear === (int)$y ? 'selected' : '' ?>><?= h((string)$y) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <?php if ($activePage === 'table'): ?>
          <div class="field">
            <label>แยกตามมิติ</label>
            <select name="dim" onchange="this.form.submit()">
              <?php foreach ($dimensions as $key => $label): ?>
                <option value="<?= h($key) ?>" <?= $selectedDimension === $key ? 'selected' : '' ?>><?= h($label) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        <?php else: ?>
          <?php // หน้ากราฟไม่มี dropdown มิตินี้ (กราฟส่วนใหญ่ตรึงมิติไว้ตายตัวอยู่แล้ว มีไว้จะทำให้
          // สับสนว่าเปลี่ยนแล้วกราฟจะเปลี่ยนตามหรือไม่) แต่ยังส่งค่ามิติที่เคยเลือกไว้จากหน้าตาราง
          // ต่อไปด้วย เผื่อผู้ใช้งานเปลี่ยนปีการศึกษาที่หน้านี้แล้วสลับกลับไปหน้าตาราง จะได้ไม่รีเซ็ต
          // มิติที่เคยเลือกไว้ ?>
          <input type="hidden" name="dim" value="<?= h($selectedDimension) ?>">
        <?php endif; ?>
      </form>
    </div>
    <?php
}

/** ปิด .report-main / .container ที่เปิดไว้ใน render_report_start() + footer */
function render_report_end(): void
{
    ?>
  </div>
</div>
<footer style="text-align:center; padding:20px 16px; margin-top:12px;">
  <p class="muted">สำนักงานศึกษาธิการจังหวัดปัตตานี<br>
    ข้อมูล ณ วันที่เข้าถึงหน้านี้ (อัปเดตอัตโนมัติทุกครั้งที่มีการนำเข้าข้อมูลใหม่)</p>
</footer>
</body>
</html>
    <?php
}
