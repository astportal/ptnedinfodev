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

// ตัวเลขหลักที่แสดงในหน้านี้ — คัดเฉพาะรายการที่ตรวจสอบกับ reference_templates/ ด้วยมือแล้วว่า
// ปลอดภัยต่อการรวมยอด (ไม่มีคอลัมน์ "รวม" ปนอยู่ในชีทเดียวกับคอลัมน์ย่อย ไม่งั้นจะรวมยอดซ้ำสอง) —
// ยังไม่ครบทั้ง 15 ฟอร์มโดยตั้งใจ เพิ่มรายการอื่นได้ทีหลังถ้าตรวจสอบโครงสร้างสมชีทนั้นแล้ว
$metrics = [
    'schools' => ['label' => 'จำนวนสถานศึกษา (แห่ง)'],
    'students' => ['label' => 'จำนวนนักเรียน/ผู้เรียน (คน)', 'sheets' => [
        ['4_students', '4.จำนวนผู้เรียน', null],
        // เด็กเล็กในศูนย์พัฒนาเด็กเล็ก/สถานพัฒนาเด็กปฐมวัย (ฟอร์ม 14) ไม่ได้กรอกในฟอร์ม 4 — รวมเข้า
        // มาด้วยตามคำขอผู้ใช้งาน (2026-08-29) แต่ต้องระบุคอลัมน์ "รวมทั้งสิ้น" อย่างเดียวเท่านั้น
        // (ไม่ใช่ทุกคอลัมน์) เพราะชีทนี้มีคอลัมน์จำนวนครู/ผู้ดูแลเด็กปนอยู่ด้วย ถ้ารวมทุกคอลัมน์จะ
        // เอาจำนวนครูมาบวกกับจำนวนเด็กผิด ๆ
        ['14_childcare_centers', '14.ข้อมูลศูนย์พัฒนาเด็กเล็ก', ['รวมทั้งสิ้น']],
        // โรงเรียนเอกชนนอกระบบ (ฟอร์ม 15) ก็ไม่ได้กรอกฟอร์ม 4 เช่นกัน — รวมเข้ามาด้วยตามคำขอ
        // ผู้ใช้งาน (2026-08-29) เฉพาะคอลัมน์ "จำนวนผู้เรียน"/"จำนวนนักเรียน" เท่านั้น (ไม่รวม
        // คอลัมน์ผู้สอน/โต๊ะครูในชีทเดียวกัน) — ชีท "สช.วิชาชีพ-ครู-นร." ใช้แค่คอลัมน์ "รวม" อย่าง
        // เดียว เพราะ "ชาย"/"หญิง"/"รวม" เป็น 3 คอลัมน์ที่ค่า "รวม" ก็คือผลบวกของอีก 2 คอลัมน์อยู่
        // แล้ว (ไม่ได้แยก split_last แบบฟอร์มอื่น — ดู forms/registry.php) ถ้ารวมทั้ง 3 จะนับซ้ำสอง
        // — ชีท "สช.วิชาชีพ" ไม่มีคอลัมน์ตัวเลขเลย (value_type=text) จึงไม่เกี่ยวกับตัวเลขนี้
        ['15_private_nonformal', '15.1', ['จำนวนผู้เรียน / ชาย', 'จำนวนผู้เรียน / หญิง']],
        ['15_private_nonformal', '15.2', ['จำนวนผู้เรียน / ชาย', 'จำนวนผู้เรียน / หญิง']],
        ['15_private_nonformal', '15.3', ['จำนวนผู้เรียน / ชาย', 'จำนวนผู้เรียน / หญิง']],
        ['15_private_nonformal', 'สช.วิชาชีพ-ครู-นร.', ['จำนวนนักเรียน / รวม']],
        // ผู้เรียน กศน. (ฟอร์ม 11) ก็ไม่ได้กรอกฟอร์ม 4 เช่นกัน — รวมเข้ามาด้วยตามคำขอผู้ใช้งาน
        // (2026-08-29) ชีท 11.1 ไม่มีคอลัมน์ "รวม" ปนอยู่เลย (ตรวจ reference_templates/ แล้ว มีแต่
        // คอลัมน์กิจกรรมการศึกษา×เพศ ไม่มี subtotal) จึงส่ง null รวมทุกคอลัมน์ได้เลยเหมือนฟอร์ม 4/10.1
        ['11_nfe', '11.1', null],
    ]],
    'classrooms' => ['label' => 'จำนวนห้องเรียน (ห้อง)', 'sheets' => [
        ['3_classrooms', '3.จำนวนห้องเรียน'],
    ]],
    'teachers' => ['label' => 'จำนวนครู/บุคลากรทางการศึกษา (คน)', 'sheets' => [
        ['10_teachers', '10.1ทุกสังกัด', null],
        // ครู/ผู้ดูแลเด็กของศูนย์พัฒนาเด็กเล็ก/สถานพัฒนาเด็กปฐมวัย (ฟอร์ม 14) ไม่ได้กรอกฟอร์ม 10 เลย
        // — รวมเข้ามาด้วยตามคำขอผู้ใช้งาน (2026-08-29) เฉพาะ 2 คอลัมน์นี้เท่านั้น (ไม่รวมคอลัมน์
        // เด็กเล็ก หรือคอลัมน์แยกวุฒิการศึกษาของครู ซึ่งเป็นการแจกแจงซ้ำของ 2 คอลัมน์นี้อยู่แล้ว
        // ถ้ารวมด้วยจะนับครูคนเดิมซ้ำสอง)
        ['14_childcare_centers', '14.ข้อมูลศูนย์พัฒนาเด็กเล็ก', [
            'จำนวนครู/ผู้ดูแลเด็ก (คน) / ชาย',
            'จำนวนครู/ผู้ดูแลเด็ก (คน) / หญิง',
        ]],
        // โรงเรียนเอกชนนอกระบบ (ฟอร์ม 15) — เหตุผลเดียวกับ "จำนวนนักเรียน" ข้างบน ใช้เฉพาะคอลัมน์
        // ผู้สอน/โต๊ะครู (ไม่รวมคอลัมน์ผู้เรียนในชีทเดียวกัน) และใช้แค่ "รวม" สำหรับชีทที่มีคอลัมน์
        // ชาย/หญิง/รวมแยกกัน
        ['15_private_nonformal', '15.1', ['จำนวนผู้สอน / ชาย', 'จำนวนผู้สอน / หญิง']],
        ['15_private_nonformal', '15.2', ['จำนวนโต๊ะครู / ชาย', 'จำนวนโต๊ะครู / หญิง']],
        ['15_private_nonformal', '15.3', ['จำนวนผู้สอน / ชาย', 'จำนวนผู้สอน / หญิง']],
        ['15_private_nonformal', 'สช.วิชาชีพ-ครู-นร.', ['จำนวนครู / รวม']],
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
        $sheetTotals = $onlyColumns !== null
            ? $reporting->groupedTotalForColumns($formKey, $sheetName, $onlyColumns, $dimension, $academicYear)
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

// สัดส่วนนักเรียนชาย:หญิง — ใช้คอลัมน์ชุดเดียวกับตัวเลข "จำนวนนักเรียน" ด้านบนทุกประการ แต่ใช้คอลัมน์
// แยกเพศดิบแทนคอลัมน์ "รวม"/"รวมทั้งสิ้น" (ซึ่งไม่มีเพศให้แยก) — ดู Reporting::genderTotalsForColumns
$genderSheets = [
    ['4_students', '4.จำนวนผู้เรียน', null],
    ['14_childcare_centers', '14.ข้อมูลศูนย์พัฒนาเด็กเล็ก', [
        'เด็กเล็ก / อายุ 2 ปี / ชาย', 'เด็กเล็ก / อายุ 2 ปี / หญิง',
        'เด็กเล็ก / อายุ 3 ปี / ชาย', 'เด็กเล็ก / อายุ 3 ปี / หญิง',
        'เด็กเล็ก / อายุ 4 ปี / ชาย', 'เด็กเล็ก / อายุ 4 ปี / หญิง',
        'เด็กเล็ก / อายุ 5 ปี / ชาย', 'เด็กเล็ก / อายุ 5 ปี / หญิง',
    ]],
    ['15_private_nonformal', '15.1', ['จำนวนผู้เรียน / ชาย', 'จำนวนผู้เรียน / หญิง']],
    ['15_private_nonformal', '15.2', ['จำนวนผู้เรียน / ชาย', 'จำนวนผู้เรียน / หญิง']],
    ['15_private_nonformal', '15.3', ['จำนวนผู้เรียน / ชาย', 'จำนวนผู้เรียน / หญิง']],
    ['15_private_nonformal', 'สช.วิชาชีพ-ครู-นร.', ['จำนวนนักเรียน / ชาย', 'จำนวนนักเรียน / หญิง']],
    ['11_nfe', '11.1', null],
];
$genderMale = 0.0;
$genderFemale = 0.0;
foreach ($genderSheets as [$formKey, $sheetName, $onlyColumns]) {
    $g = $reporting->genderTotalsForColumns($formKey, $sheetName, $onlyColumns, $selectedYear);
    $genderMale += $g['male'];
    $genderFemale += $g['female'];
}
$genderTotal = $genderMale + $genderFemale;

// สัดส่วนครูชาย:หญิง — เหตุผลเดียวกับสัดส่วนนักเรียนข้างบน ใช้ชุดชีทเดียวกับตัวเลข "จำนวนครู/
// บุคลากร" แต่สลับไปใช้คอลัมน์แยกเพศดิบแทนคอลัมน์ "รวม"/คอลัมน์เดี่ยวของฟอร์ม 10.1 (ไม่มี "รวม"
// ปนอยู่จึงส่ง null ให้สแกนทุกคอลัมน์ได้เลยเหมือนฟอร์ม 4)
$teacherGenderSheets = [
    ['10_teachers', '10.1ทุกสังกัด', null],
    ['14_childcare_centers', '14.ข้อมูลศูนย์พัฒนาเด็กเล็ก', [
        'จำนวนครู/ผู้ดูแลเด็ก (คน) / ชาย', 'จำนวนครู/ผู้ดูแลเด็ก (คน) / หญิง',
    ]],
    ['15_private_nonformal', '15.1', ['จำนวนผู้สอน / ชาย', 'จำนวนผู้สอน / หญิง']],
    ['15_private_nonformal', '15.2', ['จำนวนโต๊ะครู / ชาย', 'จำนวนโต๊ะครู / หญิง']],
    ['15_private_nonformal', '15.3', ['จำนวนผู้สอน / ชาย', 'จำนวนผู้สอน / หญิง']],
    ['15_private_nonformal', 'สช.วิชาชีพ-ครู-นร.', ['จำนวนครู / ชาย', 'จำนวนครู / หญิง']],
];
$teacherGenderMale = 0.0;
$teacherGenderFemale = 0.0;
foreach ($teacherGenderSheets as [$formKey, $sheetName, $onlyColumns]) {
    $tg = $reporting->genderTotalsForColumns($formKey, $sheetName, $onlyColumns, $selectedYear);
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
arsort($disabilityByType);

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
  <a href="public_report.php">สถิติการศึกษาจังหวัดปัตตานี</a>
  <nav>
    <a href="public_report.php?<?= h($navQuery) ?>" class="<?= $activePage === 'charts' ? 'active' : '' ?>">ภาพรวม (กราฟ)</a>
    <a href="public_report_table.php?<?= h($navQuery) ?>" class="<?= $activePage === 'table' ? 'active' : '' ?>">ตารางสรุปยอดรวม</a>
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
