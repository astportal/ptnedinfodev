<?php
/**
 * ข้อมูล+ฟังก์ชันที่ใช้ร่วมกันระหว่าง public_teacher_grade_table.php (หน้าเว็บ) และ
 * public_teacher_grade_table_export.php (ดาวน์โหลด CSV) — ตารางครูผู้สอน 1 แถวต่อสถานศึกษา แยก
 * ชาย/หญิง หน้าเปิดเผยต่อสาธารณะ ไม่ต้อง login เหมือนหน้าสาธารณะอื่น ๆ
 *
 * **ปรับโครงสร้างเมื่อ 2026-08-29 (คำขอถัดจากที่สร้างหน้าครั้งแรกทันที)** — เดิมคอลัมน์หลักแยกตาม
 * ระดับชั้นที่สอน (ฟอร์ม 10.2) เหมือนตารางผู้เรียนรายชั้น แต่ผู้ใช้ขอเปลี่ยนใหม่ให้คอลัมน์หลักเป็น
 * "ครูผู้สอน" (แยกชาย/หญิง) จากฟอร์ม 10.1 แทน **เฉพาะกลุ่ม "ครูที่ปฏิบัติการสอน(1)" เท่านั้น ไม่รวม
 * ผู้บริหาร (ผอ.รร./รอง ผอ.รร.) และไม่รวมบุคลากรสนับสนุน** (ลูกจ้างประจำ/พนักงานราชการ/ลูกจ้าง
 * ชั่วคราว ฯลฯ) — ตารางนี้เลยไม่มีคอลัมน์แยกตามระดับชั้นอีกต่อไป (ไม่มีข้อมูลระดับชั้นในฟอร์ม 10.1)
 * เหลือแค่ 1 ตัวเลข "ครูผู้สอน" ต่อสถานศึกษา แยกเพศ
 *
 * คอลัมน์เสริมท้ายตารางยังเหมือนเดิม: "ครู ศพด." จากฟอร์ม 14 และ "ครูนอกระบบ" จากฟอร์ม 15
 * (สถานศึกษาคนละกลุ่มกับฟอร์ม 10 เลย ไม่ได้กรอกฟอร์ม 10 จึงต้อง union แถว school_code จาก 3 แหล่ง)
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

// "ครูผู้สอน" (ฟอร์ม 10.1) — เฉพาะกลุ่ม "ครูที่ปฏิบัติการสอน(1)" 4 ตำแหน่งย่อย (ข้าราชการ, พนักงาน,
// ครูประจำตามสัญญา, ครูพิเศษ) ไม่รวมผู้บริหาร (ผอ.รร./รอง ผอ.รร.) และไม่รวมบุคลากรสนับสนุน — ตรวจ
// column_path เทียบ reference_templates/10_ข้อมูลครูV2.xlsx จริงด้วยสคริปต์อัตโนมัติก่อน hardcode
// (identity_cols=6, header_rows=5, skip_rows=[1,2] ตามที่ระบุไว้ใน forms/registry.php)
$teachingColumns = [
    'ครูที่ปฏิบัติการสอน(1) / ข้าราชการ(2) / ชาย', 'ครูที่ปฏิบัติการสอน(1) / ข้าราชการ(2) / หญิง',
    'ครูที่ปฏิบัติการสอน(1) / พนักงาน(3) / ชาย', 'ครูที่ปฏิบัติการสอน(1) / พนักงาน(3) / หญิง',
    'ครูที่ปฏิบัติการสอน(1) / ครูประจำตามสัญญา(4) / ชาย', 'ครูที่ปฏิบัติการสอน(1) / ครูประจำตามสัญญา(4) / หญิง',
    'ครูที่ปฏิบัติการสอน(1) / ครูพิเศษ(5) / ชาย', 'ครูที่ปฏิบัติการสอน(1) / ครูพิเศษ(5) / หญิง',
];

// ครู/ผู้ดูแลเด็ก ศพด. (ฟอร์ม 14) — คอลัมน์เดียวกับที่ใช้ใน metric "จำนวนครู/บุคลากรทางการศึกษา" ของ
// public_report_data.php (ตรวจแล้วว่าไม่ปนกับคอลัมน์จำนวนเด็กเล็กในชีทเดียวกัน)
$childcareColumns = ['จำนวนครู/ผู้ดูแลเด็ก (คน) / ชาย', 'จำนวนครู/ผู้ดูแลเด็ก (คน) / หญิง'];

// ครูนอกระบบ (ฟอร์ม 15) — เฉพาะคอลัมน์ผู้สอน/โต๊ะครู (ไม่รวมคอลัมน์ผู้เรียนในชีทเดียวกัน) — **แก้เมื่อ
// 2026-08-29**: ชีท "สช.วิชาชีพ-ครู-นร." เดิมเข้าใจผิดว่ามีแค่คอลัมน์ "จำนวนครู / รวม" อย่างเดียว ไม่
// แยกเพศ (ดูบันทึกความผิดพลาดนี้ใน ai_note.md) — ตรวจ column_path เทียบ reference_templates/
// 15_ข้อมูลโรงเรียนเอกชนนอกระบบ.xlsx ใหม่จริง ๆ ด้วยสคริปต์อัตโนมัติ พบว่าชีทนี้มีครบทั้ง "จำนวนครู /
// ชาย", "จำนวนครู / หญิง", "จำนวนครู / รวม" (3 คอลัมน์ "รวม" เป็นผลบวกของอีก 2 ไม่ใช่มิติอิสระ —
// สอดคล้องกับที่ registry.php บันทึกไว้อยู่แล้วว่าทำไมไม่ใช้ split_last) จึงแยกเพศได้เหมือนอีก 3 ชีท
// ทุกประการ ใช้ ['ชาย','หญิง'] แทน ['รวม'] ทุกชีทตอนนี้
$privateNonformalTeacherSheets = [
    ['15_private_nonformal', '15.1', ['จำนวนผู้สอน / ชาย', 'จำนวนผู้สอน / หญิง']],
    ['15_private_nonformal', '15.2', ['จำนวนโต๊ะครู / ชาย', 'จำนวนโต๊ะครู / หญิง']],
    ['15_private_nonformal', '15.3', ['จำนวนผู้สอน / ชาย', 'จำนวนผู้สอน / หญิง']],
    ['15_private_nonformal', 'สช.วิชาชีพ-ครู-นร.', ['จำนวนครู / ชาย', 'จำนวนครู / หญิง']],
];

// ผู้ดูแลเด็กที่สถานรับเลี้ยงเด็กเอกชนสังกัด พมจ. (ฟอร์ม 16.2) — เฉพาะคอลัมน์ "จำนวนผู้ดูแลเด็ก..."
// เท่านั้น (ไม่รวมคอลัมน์ "จำนวนเด็ก..." ในชีทเดียวกัน ซึ่งใช้ไปแล้วในตาราง "พมจ." ของตารางผู้เรียน
// รายชั้น) — เพิ่มเมื่อ 2026-08-30 ตามคำขอผู้ใช้งาน
$pmjColumns = [
    'จำนวนผู้ดูแลเด็กช่วงอายุ 0-2 ปี / ชาย', 'จำนวนผู้ดูแลเด็กช่วงอายุ 0-2 ปี / หญิง',
    'จำนวนผู้ดูแลเด็กช่วงอายุ  3-5 ปี / ชาย', 'จำนวนผู้ดูแลเด็กช่วงอายุ  3-5 ปี / หญิง',
];

/** แถวว่างเปล่า 1 แถว ให้เติมตอนพบ school_code ใหม่จากฟอร์ม 14/15 ที่ฟอร์ม 10.1 ไม่มี */
function teacher_table_blank_row(string $schoolCode, string $schoolName, string $agencyName, string $amphoe): array
{
    return [
        'school_code' => $schoolCode,
        'school_name' => $schoolName,
        'agency_name' => $agencyName,
        'amphoe'      => $amphoe,
        'teaching_total'  => ['male' => 0.0, 'female' => 0.0],
        'childcare_total' => ['male' => 0.0, 'female' => 0.0],
        'private_nonformal_total' => ['male' => 0.0, 'female' => 0.0],
        'pmj_total' => ['male' => 0.0, 'female' => 0.0],
    ];
}

$rowsByCode = [];

// 1) ฟอร์ม 10.1 — แหล่งหลัก ให้ identity (สังกัด/อำเภอ ผ่าน pivot() ที่ resolve กับ schools_master ให้
// แล้ว) + ยอดครูผู้สอน แยกชาย/หญิง (รวม 4 ตำแหน่งย่อยในกลุ่ม "ครูที่ปฏิบัติการสอน" เข้าด้วยกัน)
$pivot10 = $reporting->pivot('10_teachers', '10.1ทุกสังกัด', $selectedYear);
foreach ($pivot10['rows'] as $r) {
    $code = trim((string)($r['school_code'] ?? ''));
    if ($code === '') {
        continue;
    }
    $row = teacher_table_blank_row($code, (string)($r['school_name'] ?? ''), (string)($r['agency_name'] ?? ''), (string)($r['amphoe'] ?? ''));
    foreach ($teachingColumns as $path) {
        $v = $r[$path] ?? '';
        if ($v === '' || !is_numeric($v)) {
            continue;
        }
        if (preg_match('/ชาย$/u', $path)) {
            $row['teaching_total']['male'] += (float)$v;
        } elseif (preg_match('/หญิง$/u', $path)) {
            $row['teaching_total']['female'] += (float)$v;
        }
    }
    $rowsByCode[$code] = $row;
}

// 2) ฟอร์ม 14 (ครู/ผู้ดูแลเด็ก ศพด.) — โรงเรียนคนละกลุ่มกับฟอร์ม 10 เลย (ศพด.ไม่กรอกฟอร์ม 10) เติมแถว
// ใหม่ถ้ายังไม่เคยเจอ school_code นี้มาก่อน แยกชาย/หญิงตรง ๆ ตามคอลัมน์ที่ระบุไว้
[$childcareMaleCol, $childcareFemaleCol] = $childcareColumns;
$pivot14 = $reporting->pivot('14_childcare_centers', '14.ข้อมูลศูนย์พัฒนาเด็กเล็ก', $selectedYear);
foreach ($pivot14['rows'] as $r) {
    $code = trim((string)($r['school_code'] ?? ''));
    if ($code === '') {
        continue;
    }
    if (!isset($rowsByCode[$code])) {
        $rowsByCode[$code] = teacher_table_blank_row($code, (string)($r['school_name'] ?? ''), (string)($r['agency_name'] ?? ''), (string)($r['amphoe'] ?? ''));
    }
    $m = $r[$childcareMaleCol] ?? '';
    $f = $r[$childcareFemaleCol] ?? '';
    $rowsByCode[$code]['childcare_total']['male'] += is_numeric($m) ? (float)$m : 0.0;
    $rowsByCode[$code]['childcare_total']['female'] += is_numeric($f) ? (float)$f : 0.0;
}

// 3) ฟอร์ม 15 (ครูนอกระบบ) — โรงเรียนคนละกลุ่มกับฟอร์ม 10 เช่นกัน รวมยอดข้าม 4 ชีท แต่ละชีทมีคอลัมน์
// อื่นปนอยู่ด้วย ต้องระบุ onlyColumns กันนับซ้ำ — แยกชาย/หญิงได้ครบทั้ง 4 ชีท (แก้เมื่อ 2026-08-29 ดู
// เหตุผลที่ $privateNonformalTeacherSheets ด้านบน) จับคู่ด้วย regex ปลาย column_path แบบเดียวกับที่
// ใช้กับฟอร์ม 10.1/11.1
foreach ($privateNonformalTeacherSheets as [$formKey, $sheetName, $onlyColumns]) {
    $pivot15 = $reporting->pivot($formKey, $sheetName, $selectedYear);
    foreach ($pivot15['rows'] as $r) {
        $code = trim((string)($r['school_code'] ?? ''));
        if ($code === '') {
            continue;
        }
        if (!isset($rowsByCode[$code])) {
            $rowsByCode[$code] = teacher_table_blank_row($code, (string)($r['school_name'] ?? ''), (string)($r['agency_name'] ?? ''), (string)($r['amphoe'] ?? ''));
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

// 4) ฟอร์ม 16.2 (ผู้ดูแลเด็กสถานรับเลี้ยงเด็กเอกชนสังกัด พมจ.) — โรงเรียนคนละกลุ่มกับฟอร์ม 10 เช่นกัน
// join ด้วย school_code ที่จับคู่จากชื่อไว้แล้วตอน import (ดู match_school_code_by_name) แถวที่
// จับคู่ไม่สำเร็จจะไม่มี school_code เลย ข้ามไปเหมือนแถวที่ขาด school_code ของฟอร์มอื่นทุกประการ
$pivot16 = $reporting->pivot('16_pmj', 'พมจ-16.2', $selectedYear);
foreach ($pivot16['rows'] as $r) {
    $code = trim((string)($r['school_code'] ?? ''));
    if ($code === '') {
        continue;
    }
    if (!isset($rowsByCode[$code])) {
        $rowsByCode[$code] = teacher_table_blank_row($code, (string)($r['school_name'] ?? ''), (string)($r['agency_name'] ?? ''), (string)($r['amphoe'] ?? ''));
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

// คอลัมน์ "รวม"/"รวมชาย"/"รวมหญิง" ต่อสถานศึกษา — ผลรวมครูผู้สอน + ครู ศพด. + ครูนอกระบบ + พมจ.
// ("รวมชาย"/"รวมหญิง" เพิ่มเมื่อ 2026-08-30 ตามคำขอผู้ใช้งาน — ทุกแหล่งในตารางนี้แยกชาย/หญิงอยู่แล้ว
// ทั้งหมด รวมตรง ๆ ได้เลย)
foreach ($gradeTableRows as &$gtRow) {
    $grandMale = $gtRow['teaching_total']['male'] + $gtRow['childcare_total']['male']
        + $gtRow['private_nonformal_total']['male'] + $gtRow['pmj_total']['male'];
    $grandFemale = $gtRow['teaching_total']['female'] + $gtRow['childcare_total']['female']
        + $gtRow['private_nonformal_total']['female'] + $gtRow['pmj_total']['female'];
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
$gradeTotals = [
    'teaching_total'  => ['male' => 0.0, 'female' => 0.0],
    'childcare_total' => ['male' => 0.0, 'female' => 0.0],
    'private_nonformal_total' => ['male' => 0.0, 'female' => 0.0],
    'pmj_total' => ['male' => 0.0, 'female' => 0.0],
    'grand_total' => 0.0,
    'grand_total_male' => 0.0,
    'grand_total_female' => 0.0,
];
foreach ($gradeTableRows as $r) {
    $gradeTotals['teaching_total']['male'] += $r['teaching_total']['male'];
    $gradeTotals['teaching_total']['female'] += $r['teaching_total']['female'];
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

// ก็อปมาจาก fmt_num() ใน public_report_data.php ตรง ๆ (ไม่ include ไฟล์นั้นเพราะตัวแปรชื่อชนกัน) —
// ถ้าแก้ตัวใดตัวหนึ่งต้องแก้อีกตัวให้ตรงกันด้วย
function fmt_num($v): string
{
    if ($v === null || $v === '') {
        return '—';
    }
    return number_format((float)$v);
}
