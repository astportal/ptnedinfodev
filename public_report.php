<?php
/**
 * สถิติการศึกษาจังหวัดปัตตานี — หน้าเปิดเผยต่อสาธารณะ ไม่ต้อง login (ไม่มี Auth::requireLogin()
 * เจตนา) สำหรับศึกษาธิการจังหวัด/ผู้ว่าราชการจังหวัด/ผู้สนใจทั่วไป แสดงเฉพาะยอดรวมระดับจังหวัด
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
    ]],
    'disadvantaged' => ['label' => 'นักเรียนด้อยโอกาส (คน)', 'sheets' => [
        ['5_disadvantaged', '5.1 นักเรียนด้อยโอกาส'],
    ]],
];

$dataByMetric = [];
$groupSet = [];
foreach ($metrics as $key => $m) {
    if ($key === 'schools') {
        $totals = $reporting->schoolCountByDimension($selectedDimension, $selectedYear);
    } else {
        $totals = [];
        foreach ($m['sheets'] as $sheet) {
            [$formKey, $sheetName] = $sheet;
            $onlyColumns = $sheet[2] ?? null; // null = sum every value column (see groupedTotal())
            $sheetTotals = $onlyColumns !== null
                ? $reporting->groupedTotalForColumns($formKey, $sheetName, $onlyColumns, $selectedDimension, $selectedYear)
                : $reporting->groupedTotal($formKey, $sheetName, $selectedDimension, $selectedYear);
            foreach ($sheetTotals as $g => $v) {
                $totals[$g] = ($totals[$g] ?? 0) + $v;
            }
        }
    }
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

function fmt_num($v): string
{
    if ($v === null || $v === '') {
        return '—';
    }
    return number_format((float)$v);
}
?>
<!doctype html>
<html lang="th">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>สถิติการศึกษาจังหวัดปัตตานี — ptnedinfo</title>
<link rel="stylesheet" href="public/assets/style.css">
<style>
  .stats-table th, .stats-table td { white-space: nowrap; }
  .stats-table td.num, .stats-table th.num { text-align: right; }
  .stats-table tfoot td { font-weight: 700; background: #f3f4f6; }
  .filter-bar { display: flex; gap: 16px; align-items: flex-end; flex-wrap: wrap; margin: 12px 0; }
  .filter-bar .field { margin-bottom: 0; min-width: 220px; }
</style>
</head>
<body>
<div class="topbar">
  <a href="public_report.php">สถิติการศึกษาจังหวัดปัตตานี</a>
  <nav>
    <a href="login.php">เข้าสู่ระบบเจ้าหน้าที่</a>
  </nav>
</div>
<div class="container" style="max-width: 1100px;">
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
      <div class="field">
        <label>แยกตามมิติ</label>
        <select name="dim" onchange="this.form.submit()">
          <?php foreach ($dimensions as $key => $label): ?>
            <option value="<?= h($key) ?>" <?= $selectedDimension === $key ? 'selected' : '' ?>><?= h($label) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </form>
  </div>

  <div class="card">
    <h2>สรุปยอดรวมแยกตาม<?= h($dimensions[$selectedDimension]) ?></h2>
    <?php if (!$groups): ?>
      <p class="muted">ยังไม่มีข้อมูลสำหรับปีการศึกษานี้</p>
    <?php else: ?>
      <div class="table-scroll">
        <table class="stats-table">
          <thead>
            <tr>
              <th><?= h($dimensions[$selectedDimension]) ?></th>
              <?php foreach ($metrics as $m): ?>
                <th class="num"><?= h($m['label']) ?></th>
              <?php endforeach; ?>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($groups as $g): ?>
              <tr>
                <td><?= h($g) ?></td>
                <?php foreach (array_keys($metrics) as $key): ?>
                  <td class="num"><?= fmt_num($dataByMetric[$key][$g] ?? 0) ?></td>
                <?php endforeach; ?>
              </tr>
            <?php endforeach; ?>
          </tbody>
          <tfoot>
            <tr>
              <td>รวมทั้งจังหวัด</td>
              <?php foreach (array_keys($metrics) as $key): ?>
                <?php $grand = array_sum($dataByMetric[$key]); ?>
                <td class="num"><?= fmt_num($grand) ?></td>
              <?php endforeach; ?>
            </tr>
          </tfoot>
        </table>
      </div>
    <?php endif; ?>
  </div>
</div>
<footer style="text-align:center; padding:20px 16px; margin-top:12px;">
  <p class="muted">สำนักงานศึกษาธิการจังหวัดปัตตานี<br>
    ข้อมูล ณ วันที่เข้าถึงหน้านี้ (อัปเดตอัตโนมัติทุกครั้งที่มีการนำเข้าข้อมูลใหม่)</p>
</footer>
</body>
</html>
