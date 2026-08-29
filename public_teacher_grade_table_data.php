<?php
/**
 * ข้อมูล+ฟังก์ชันที่ใช้ร่วมกันระหว่าง public_teacher_grade_table.php (หน้าเว็บ) และ
 * public_teacher_grade_table_export.php (ดาวน์โหลด CSV) — ตารางครูผู้สอนรายชั้น 1 แถวต่อสถานศึกษา
 * 1 คอลัมน์ต่อระดับชั้น เหมือน public_school_grade_table_data.php (ตารางผู้เรียนรายชั้น) ทุกประการ
 * แค่สลับแหล่งข้อมูลเป็นฝั่งครู ตามคำขอผู้ใช้งาน 2026-08-29 ("ทำตารางแบบนี้กับข้อมูล 'ครูผู้สอน'")
 * หน้าเปิดเผยต่อสาธารณะ ไม่ต้อง login เหมือนหน้าสาธารณะอื่น ๆ
 *
 * แหล่งคอลัมน์ระดับชั้นหลัก = ฟอร์ม 10.2 (`10_teachers` / ชีท "10.2ทุกสังกัด" — "จำนวนผู้สอน จำแนก
 * ตามสถานศึกษาและระดับการศึกษาที่เปิดสอน") เป็นชีทเดียวในฟอร์ม 10 ที่แยกครู/ผู้สอนตามระดับชั้น (ชีท
 * อื่นของฟอร์ม 10 แยกตามตำแหน่ง/อันดับ/วุฒิการศึกษา/วิชาเอกแทน คนละมิติ ไม่ใช่ระดับชั้น) มีแค่ 7 ระดับ
 * (ไม่ละเอียดเท่าฟอร์ม 4 ของผู้เรียนที่มี 37 ระดับ เพราะครู 1 คนสอนได้หลายชั้นปีในระดับเดียวกัน ฟอร์ม
 * นี้เลยรายงานเป็นช่วงชั้นกว้าง ๆ แทนที่จะแยกทีละปีเหมือนฟอร์ม 4)
 *
 * เพิ่ม 2 คอลัมน์ท้ายตาราง เหมือนตารางผู้เรียนรายชั้น: "ครู ศพด." จากฟอร์ม 14 (ศูนย์พัฒนาเด็กเล็ก/
 * สถานพัฒนาเด็กปฐมวัย ไม่ได้กรอกฟอร์ม 10 เลย) และ "ครูนอกระบบ" จากฟอร์ม 15 (โรงเรียนเอกชนนอกระบบ ก็
 * ไม่ได้กรอกฟอร์ม 10 เช่นกัน) — คอลัมน์ทั้งสองใช้ column_path ชุดเดียวกับที่ตรวจสอบไว้แล้วใน metric
 * "จำนวนครู/บุคลากรทางการศึกษา" ของ public_report_data.php **ไม่มี "ครู สกร." จากฟอร์ม 11** เพราะ
 * ฟอร์ม 11 (11.1-11.5) เก็บแต่ข้อมูลผู้เรียน กศน. ไม่มีชีทไหนเก็บจำนวนครู/บุคลากรเลย (ต่างจากตาราง
 * ผู้เรียนรายชั้นที่มี "ผู้เรียน สกร." เพราะฝั่งผู้เรียนมีข้อมูลนี้จริง)
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

// ต่อ query string เดิม (ปีที่เลือก) เวลาสลับไปหน้าสาธารณะอื่นผ่านเมนูบนสุด
$navQuery = http_build_query(['year' => $selectedYear]);

// คอลัมน์ระดับชั้น 7 ระดับ (label ย่อ => [column_path เพศชาย, column_path เพศหญิง]) — คัดลอก
// column_path มาจากการตรวจ reference_templates/10_ข้อมูลครูV2.xlsx จริงตรง ๆ ด้วยสคริปต์เทียบอัตโนมัติ
// (ไม่ได้เดา) ปลอดภัยที่จะ hardcode แบบนี้เพราะชีท 10.2 ไม่มี merge_extra_columns_into (ดู
// forms/registry.php) — Importer::assertStructureMatches() บังคับให้ทุกไฟล์ที่อัปโหลดผ่านมี
// column_path ตรงกับแม่แบบเป๊ะเสมอ
$gradeGroups = [
    'ก่อนอนุบาล' => ['ระดับชั้นที่ปฏิบัติการสอน / ก่อนอนุบาล / ชาย', 'ระดับชั้นที่ปฏิบัติการสอน / ก่อนอนุบาล / หญิง'],
    'อนุบาล'    => ['ระดับชั้นที่ปฏิบัติการสอน / อนุบาล / ชาย', 'ระดับชั้นที่ปฏิบัติการสอน / อนุบาล / หญิง'],
    'ประถม'     => ['ระดับชั้นที่ปฏิบัติการสอน / ประถมศึกษา / ชาย', 'ระดับชั้นที่ปฏิบัติการสอน / ประถมศึกษา / หญิง'],
    'ม.ต้น'     => ['ระดับชั้นที่ปฏิบัติการสอน / มัธยมศึกษาตอนต้น / ชาย', 'ระดับชั้นที่ปฏิบัติการสอน / มัธยมศึกษาตอนต้น / หญิง'],
    'ม.ปลาย'    => ['ระดับชั้นที่ปฏิบัติการสอน / มัธยมศึกษาตอนปลาย / ชาย', 'ระดับชั้นที่ปฏิบัติการสอน / มัธยมศึกษาตอนปลาย / หญิง'],
    'ปวช.'      => ['ระดับชั้นที่ปฏิบัติการสอน / ปวช / ชาย', 'ระดับชั้นที่ปฏิบัติการสอน / ปวช / หญิง'],
    'ปวส.'      => ['ระดับชั้นที่ปฏิบัติการสอน / ปวส / ชาย', 'ระดับชั้นที่ปฏิบัติการสอน / ปวส / หญิง'],
];
$gradeLabels = array_keys($gradeGroups);

// ครู/ผู้ดูแลเด็ก ศพด. (ฟอร์ม 14) — คอลัมน์เดียวกับที่ใช้ใน metric "จำนวนครู/บุคลากรทางการศึกษา" ของ
// public_report_data.php (ตรวจแล้วว่าไม่ปนกับคอลัมน์จำนวนเด็กเล็กในชีทเดียวกัน)
$childcareColumns = ['จำนวนครู/ผู้ดูแลเด็ก (คน) / ชาย', 'จำนวนครู/ผู้ดูแลเด็ก (คน) / หญิง'];

// ครูนอกระบบ (ฟอร์ม 15) — ชุดเดียวกับที่ใช้ใน public_report_data.php (เฉพาะคอลัมน์ผู้สอน/โต๊ะครู ไม่
// รวมคอลัมน์ผู้เรียนในชีทเดียวกัน)
$privateNonformalTeacherSheets = [
    ['15_private_nonformal', '15.1', ['จำนวนผู้สอน / ชาย', 'จำนวนผู้สอน / หญิง']],
    ['15_private_nonformal', '15.2', ['จำนวนโต๊ะครู / ชาย', 'จำนวนโต๊ะครู / หญิง']],
    ['15_private_nonformal', '15.3', ['จำนวนผู้สอน / ชาย', 'จำนวนผู้สอน / หญิง']],
    ['15_private_nonformal', 'สช.วิชาชีพ-ครู-นร.', ['จำนวนครู / รวม']],
];

/** แถวว่างเปล่า 1 แถว (ทุกระดับชั้น = 0) ให้เติมตอนพบ school_code ใหม่จากฟอร์ม 14/15 ที่ฟอร์ม 10 ไม่มี */
function teacher_table_blank_row(string $schoolCode, string $schoolName, string $agencyName, string $amphoe, array $gradeLabels): array
{
    $row = [
        'school_code' => $schoolCode,
        'school_name' => $schoolName,
        'agency_name' => $agencyName,
        'amphoe'      => $amphoe,
        'childcare_total' => ['male' => 0.0, 'female' => 0.0],
        'private_nonformal_total' => 0.0,
    ];
    foreach ($gradeLabels as $label) {
        $row['grades'][$label] = ['male' => 0.0, 'female' => 0.0];
    }
    return $row;
}

$rowsByCode = [];

// 1) ฟอร์ม 10.2 — แหล่งหลัก ให้ identity (สังกัด/อำเภอ ผ่าน pivot() ที่ resolve กับ schools_master ให้
// แล้ว) + ยอดแยกตามระดับชั้น 7 คอลัมน์ แยกชาย/หญิง
$pivot10 = $reporting->pivot('10_teachers', '10.2ทุกสังกัด', $selectedYear);
foreach ($pivot10['rows'] as $r) {
    $code = trim((string)($r['school_code'] ?? ''));
    if ($code === '') {
        continue;
    }
    $row = teacher_table_blank_row($code, (string)($r['school_name'] ?? ''), (string)($r['agency_name'] ?? ''), (string)($r['amphoe'] ?? ''), $gradeLabels);
    foreach ($gradeGroups as $label => [$maleCol, $femaleCol]) {
        $m = $r[$maleCol] ?? '';
        $f = $r[$femaleCol] ?? '';
        $row['grades'][$label]['male'] = is_numeric($m) ? (float)$m : 0.0;
        $row['grades'][$label]['female'] = is_numeric($f) ? (float)$f : 0.0;
    }
    $rowsByCode[$code] = $row;
}

// 2) ฟอร์ม 14 (ครู/ผู้ดูแลเด็ก ศพด.) — โรงเรียนคนละกลุ่มกับฟอร์ม 10 เลย (ศพด.ไม่กรอกฟอร์ม 10) เติมแถว
// ใหม่ถ้ายังไม่เคยเจอ school_code นี้มาก่อน แยกชาย/หญิงตรง ๆ ตามคอลัมน์ที่ระบุไว้ (ไม่ต้องเดาด้วย
// regex เพราะรู้ชื่อคอลัมน์แน่นอนอยู่แล้ว)
[$childcareMaleCol, $childcareFemaleCol] = $childcareColumns;
$pivot14 = $reporting->pivot('14_childcare_centers', '14.ข้อมูลศูนย์พัฒนาเด็กเล็ก', $selectedYear);
foreach ($pivot14['rows'] as $r) {
    $code = trim((string)($r['school_code'] ?? ''));
    if ($code === '') {
        continue;
    }
    if (!isset($rowsByCode[$code])) {
        $rowsByCode[$code] = teacher_table_blank_row($code, (string)($r['school_name'] ?? ''), (string)($r['agency_name'] ?? ''), (string)($r['amphoe'] ?? ''), $gradeLabels);
    }
    $m = $r[$childcareMaleCol] ?? '';
    $f = $r[$childcareFemaleCol] ?? '';
    $rowsByCode[$code]['childcare_total']['male'] += is_numeric($m) ? (float)$m : 0.0;
    $rowsByCode[$code]['childcare_total']['female'] += is_numeric($f) ? (float)$f : 0.0;
}

// 3) ฟอร์ม 15 (ครูนอกระบบ) — โรงเรียนคนละกลุ่มกับฟอร์ม 10 เช่นกัน รวมยอดข้าม 4 ชีท แต่ละชีทมีคอลัมน์
// อื่นปนอยู่ด้วย ต้องระบุ onlyColumns กันนับซ้ำ — **คอลัมน์นี้ไม่แยกชาย/หญิง** เพราะชีท "สช.วิชาชีพ-
// ครู-นร." (1 ใน 4 ชีทที่รวมอยู่นี้) มีแค่คอลัมน์ "จำนวนครู / รวม" อย่างเดียว ไม่ได้แยกเพศไว้เลยใน
// ต้นฉบับ (อีก 3 ชีทแยกเพศได้ปกติ) เหตุผลเดียวกับ "ผู้เรียนนอกระบบ" ในตารางผู้เรียนรายชั้น
foreach ($privateNonformalTeacherSheets as [$formKey, $sheetName, $onlyColumns]) {
    $pivot15 = $reporting->pivot($formKey, $sheetName, $selectedYear);
    foreach ($pivot15['rows'] as $r) {
        $code = trim((string)($r['school_code'] ?? ''));
        if ($code === '') {
            continue;
        }
        if (!isset($rowsByCode[$code])) {
            $rowsByCode[$code] = teacher_table_blank_row($code, (string)($r['school_name'] ?? ''), (string)($r['agency_name'] ?? ''), (string)($r['amphoe'] ?? ''), $gradeLabels);
        }
        $sum = 0.0;
        foreach ($onlyColumns as $path) {
            $v = $r[$path] ?? '';
            if ($v !== '' && is_numeric($v)) {
                $sum += (float)$v;
            }
        }
        $rowsByCode[$code]['private_nonformal_total'] += $sum;
    }
}

$gradeTableRows = array_values($rowsByCode);
usort($gradeTableRows, static fn($a, $b) => strcmp($a['school_name'], $b['school_name']));

// คอลัมน์ "รวม" ต่อสถานศึกษา — ผลรวมทุกระดับชั้น (ชาย+หญิง) + ครู ศพด. (ชาย+หญิง) + ครูนอกระบบ
foreach ($gradeTableRows as &$gtRow) {
    $grandTotal = $gtRow['childcare_total']['male'] + $gtRow['childcare_total']['female'] + $gtRow['private_nonformal_total'];
    foreach ($gradeLabels as $label) {
        $grandTotal += $gtRow['grades'][$label]['male'] + $gtRow['grades'][$label]['female'];
    }
    $gtRow['grand_total'] = $grandTotal;
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

// ค้นหา/กรอง — ใช้ร่วมกันทั้งหน้าเว็บและ CSV export เหมือนตารางผู้เรียนรายชั้นทุกประการ
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

// แถวรวม — คำนวณจากผลลัพธ์ "หลัง" กรองเสมอ แสดงไว้แถวบนสุดของตาราง
$gradeTotals = ['grades' => [], 'childcare_total' => ['male' => 0.0, 'female' => 0.0], 'private_nonformal_total' => 0.0, 'grand_total' => 0.0];
foreach ($gradeLabels as $label) {
    $gradeTotals['grades'][$label] = ['male' => 0.0, 'female' => 0.0];
}
foreach ($gradeTableRows as $r) {
    foreach ($gradeLabels as $label) {
        $gradeTotals['grades'][$label]['male'] += $r['grades'][$label]['male'];
        $gradeTotals['grades'][$label]['female'] += $r['grades'][$label]['female'];
    }
    $gradeTotals['childcare_total']['male'] += $r['childcare_total']['male'];
    $gradeTotals['childcare_total']['female'] += $r['childcare_total']['female'];
    $gradeTotals['private_nonformal_total'] += $r['private_nonformal_total'];
    $gradeTotals['grand_total'] += $r['grand_total'];
}

// ก็อปมาจาก fmt_num() ใน public_report_data.php ตรง ๆ (ไม่ include ไฟล์นั้นเพราะตัวแปรชื่อชนกัน) —
// ถ้าแก้ตัวใดตัวหนึ่งต้องแก้อีกตัวให้ตรงกันด้วย
function fmt_num($v): string
{
    if ($v === null || $v === '') {
        return '—';
    }
    return number_format((float)$v);
}
