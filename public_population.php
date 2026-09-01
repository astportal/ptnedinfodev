<?php
/**
 * สถิติการศึกษาจังหวัดปัตตานี — หน้าประชากรวัยเรียน (หน้าเปิดเผยต่อสาธารณะ ไม่ต้อง login) แสดงจำนวน
 * ประชากรแยกช่วงอายุ x อำเภอ (จากกรมการปกครอง อัปโหลดที่ population_upload.php — ดูรายละเอียดที่มา/
 * เหตุผลกันนับซ้ำที่ src/PopulationImporter.php) เทียบกับจำนวนผู้เรียนจริง (ตารางที่ 4) คำนวณ
 * "อัตราการเข้าเรียน" ต่อช่วงอายุ — ใช้ข้อมูล/ฟังก์ชันร่วมกับหน้าอื่นจาก public_report_data.php
 */
require_once __DIR__ . '/public_report_data.php';

$ageBandLabels = [
    'age_3_5'   => '3-5 ปี',
    'age_6_11'  => '6-11 ปี',
    'age_12_14' => '12-14 ปี',
    'age_15_17' => '15-17 ปี',
    'age_18_19' => '18-19 ปี',
];
// ช่วงอายุที่คำนวณ "อัตราการเข้าเรียน" ได้ — ไม่รวม 18-19 ปี ตามคำขอผู้ใช้งาน (2026-09-01) เพราะระดับ
// อุดมศึกษาอายุจริงคลาดเคลื่อนจากชั้นปีมากกว่าระดับอื่นมาก (เข้าช้า/เรียนซ้ำชั้นบ่อย) ตัวเลขจะไม่แม่นยำ
// พอจะนำเสนอเป็นอัตรา หน้านี้จึงแสดงเฉพาะจำนวนประชากรของช่วง 18-19 ปี ไม่มีคอลัมน์ผู้เรียน/อัตรา
$enrollableAgeBands = ['age_3_5', 'age_6_11', 'age_12_14', 'age_15_17'];

// จับคู่ระดับชั้นของฟอร์ม 4 (จำนวนผู้เรียน) เข้าช่วงอายุ — จับคู่ด้วย "ชื่อ category" ที่ระบบแยกให้เอง
// จากหัวตาราง (ไม่ hardcode เป็น exact string เพราะหัวตารางแม่แบบเคยเปลี่ยนมาแล้วหลายรอบ ดู ai_note.md)
// ตรวจสอบกับ reference_templates/4_จำนวนนักเรียน.xlsx แล้ว (2026-09-01):
//   - "อนุบาล 1(สช)"/"อนุบาล 2(สช.)"/"อนุบาล 3(สช.)" มีเลข 1/2/3 ต่อท้ายคำว่า "อนุบาล" เสมอ — regex
//     /อนุบาล\s*[123]/u ต่างจาก "เตรียม/อนุบาล" (เตรียมอนุบาล อายุต่ำกว่า 3 ปี) ที่ไม่มีเลขต่อท้าย จึง
//     ไม่ติด regex นี้ (ตั้งใจไม่รวม — ผู้ใช้งานยืนยันให้นับเฉพาะอนุบาล 1-3 เท่านั้น ไม่รวมเตรียมอนุบาล/
//     เด็กเล็ก กันซ้ำกับข้อมูล ศพด. ฟอร์ม 14 ที่ใช้แยกอยู่แล้วในหน้าภาพรวม)
//   - "ประถมศึกษา" (ปีที่ 1-6)
//   - "มัธยมศึกษาตอนต้น"/"มัธยมศึกษาตอนปลาย" — ต้องคง dropFirst=0 (เก็บชื่อกลุ่มหัวตารางระดับบนสุดไว้
//     ในชื่อ category) ถึงจะแยกต้น/ปลายออกจากกันได้ เพราะแถวหัวย่อยกว่าเขียนแค่ "มัธยมศึกษา" เหมือนกัน
//     ทั้งคู่ ไม่มีคำว่า "ตอนต้น"/"ตอนปลาย" ในระดับนั้น
//   - ช่วง 15-17 ปี รวม "ประกาศนียบัตรวิชาชีพ" (ปวช.) เข้าไปด้วยตามที่เสนอไปตอนออกแบบ (ผู้ใช้งานไม่ได้
//     ท้วงจุดนี้ตอนถามคำถามยืนยัน) — regex /วิชาชีพปีที่/u ไม่ติด "วิชาชีพชั้นสูงปีที่" (ปวส.) เพราะมีคำ
//     ว่า "ชั้นสูง" คั่นกลางอยู่ ("วิชาชีพปีที่" ไม่ใช่ substring ของ "วิชาชีพชั้นสูงปีที่")
// **หมายเหตุสำหรับ AI ที่มาทำงานต่อ**: regex พวกนี้ตรวจกับแม่แบบเปล่าเท่านั้น (เครื่องนี้ไม่มี PHP รัน
// จริงไม่ได้) ถ้าตัวเลขช่วงอายุไหนออกมาเป็น 0 ทั้งจังหวัดหลัง deploy ให้สงสัยจุดนี้ก่อน — ตรวจสอบด้วย
// การ dump $enrollmentCrosstab ทั้งก้อนออกมาดู category จริงที่ระบบแยกได้เทียบกับ regex ด้านล่าง
$gradeBandPatterns = [
    'age_3_5'   => ['/อนุบาล\s*[123]/u'],
    'age_6_11'  => ['/ประถมศึกษา/u'],
    'age_12_14' => ['/มัธยมศึกษาตอนต้น/u'],
    'age_15_17' => ['/มัธยมศึกษาตอนปลาย/u', '/วิชาชีพปีที่/u'],
];

/** @return array<string,array<string,float>> อำเภอ => (age band key => จำนวนผู้เรียน) */
function compute_enrollment_by_age_band(Reporting $reporting, array $gradeBandPatterns, ?int $academicYear): array
{
    $crosstab = $reporting->sumByColumnPathPartsByDimension('4_students', '4.จำนวนผู้เรียน', 0, 1, 'amphoe', $academicYear);
    $result = [];
    foreach ($crosstab as $amphoe => $categories) {
        $bands = array_fill_keys(array_keys($gradeBandPatterns), 0.0);
        foreach ($categories as $category => $sum) {
            foreach ($gradeBandPatterns as $bandKey => $patterns) {
                foreach ($patterns as $pattern) {
                    if (preg_match($pattern, $category)) {
                        $bands[$bandKey] += $sum;
                        continue 3; // นับ category นี้เข้าช่วงเดียวเท่านั้น ไม่ซ้ำหลายช่วง
                    }
                }
            }
        }
        $result[$amphoe] = $bands;
    }
    return $result;
}

$popStmt = $db->prepare('SELECT * FROM population_by_age WHERE academic_year = :y ORDER BY amphoe');
$popStmt->execute(['y' => $selectedYear]);
$populationRows = $popStmt->fetchAll();

$enrollmentByAmphoe = compute_enrollment_by_age_band($reporting, $gradeBandPatterns, $selectedYear);

// รวมข้อมูล 2 แหล่งเข้าด้วยกันต่ออำเภอ: ประชากร 5 ช่วงอายุ (จากไฟล์ประชากร) + ผู้เรียนจริง 4 ช่วงอายุ
// (จากฟอร์ม 4) + อัตราการเข้าเรียน (ผู้เรียน/ประชากร x 100) — ไม่หารถ้าประชากรของช่วงนั้นเป็น 0
$rows = [];
foreach ($populationRows as $p) {
    $amphoe = $p['amphoe'];
    $bands = [];
    foreach ($ageBandLabels as $key => $label) {
        $population = (int)$p["{$key}_male"] + (int)$p["{$key}_female"];
        $enrolled = in_array($key, $enrollableAgeBands, true) ? (float)($enrollmentByAmphoe[$amphoe][$key] ?? 0) : null;
        $rate = ($enrolled !== null && $population > 0) ? ($enrolled / $population * 100) : null;
        $bands[$key] = ['population' => $population, 'enrolled' => $enrolled, 'rate' => $rate];
    }
    $rows[$amphoe] = $bands;
}
ksort($rows, SORT_STRING | SORT_FLAG_CASE);

// แถวรวมทั้งจังหวัด — บวกยอดประชากร/ผู้เรียนของทุกอำเภอก่อน แล้วค่อยหารเป็นอัตราทีเดียว (ไม่ใช่เฉลี่ย
// อัตราของแต่ละอำเภอ) กันอำเภอเล็ก ๆ ถ่วงอัตราภาพรวมผิดสัดส่วนเทียบกับอำเภอใหญ่
$provinceTotals = [];
foreach ($ageBandLabels as $key => $label) {
    $popSum = 0;
    $enrolledSum = in_array($key, $enrollableAgeBands, true) ? 0.0 : null;
    foreach ($rows as $bands) {
        $popSum += $bands[$key]['population'];
        if ($enrolledSum !== null) {
            $enrolledSum += $bands[$key]['enrolled'] ?? 0;
        }
    }
    $rate = ($enrolledSum !== null && $popSum > 0) ? ($enrolledSum / $popSum * 100) : null;
    $provinceTotals[$key] = ['population' => $popSum, 'enrolled' => $enrolledSum, 'rate' => $rate];
}

$fmtRate = static fn($v) => $v === null ? '—' : number_format((float)$v, 1) . '%';

render_report_start('population');
?>
      <div class="card">
        <h1>ประชากรวัยเรียน แยกช่วงอายุ x อำเภอ</h1>
        <p class="muted">จำนวนประชากรจากกรมการปกครอง (stat.bora.dopa.go.th) เทียบกับจำนวนผู้เรียนจริงที่
          รวบรวมจากตารางที่ 4 คำนวณเป็น "อัตราการเข้าเรียน" — เป็นค่าประมาณโดยจับคู่ระดับชั้นเข้าช่วงอายุ
          มาตรฐาน (เช่น ป.1-6 = อายุ 6-11 ปี) ไม่ใช่อายุจริงรายบุคคล จึงคลาดเคลื่อนได้บ้างถ้ามีเด็กเข้าเรียน
          ช้า/เร็วกว่าเกณฑ์ หรือเรียนซ้ำชั้น — ช่วง 18-19 ปี แสดงเฉพาะจำนวนประชากร ไม่คำนวณอัตรา เพราะ
          ระดับอุดมศึกษาอายุจริงคลาดเคลื่อนจากชั้นปีมากกว่าระดับอื่นมาก</p>
      </div>

      <?php if (!$rows): ?>
        <div class="card">
          <p class="muted">ยังไม่มีข้อมูลประชากรของปีการศึกษานี้ — ผู้ดูแลระบบต้องอัปโหลดผ่านหน้า
            "ข้อมูลประชากรรายอายุ" (ต้องเข้าสู่ระบบ) ก่อน</p>
        </div>
      <?php else: ?>
        <div class="card">
          <h2>ตารางประชากรและอัตราการเข้าเรียน แยกตามอำเภอ</h2>
          <div class="report-table-scroll">
            <table class="stats-table two-row-header">
              <thead>
                <tr>
                  <th rowspan="2">อำเภอ</th>
                  <?php foreach ($ageBandLabels as $key => $label): ?>
                    <th colspan="<?= in_array($key, $enrollableAgeBands, true) ? 3 : 1 ?>"><?= h($label) ?></th>
                  <?php endforeach; ?>
                </tr>
                <tr>
                  <?php foreach ($ageBandLabels as $key => $label): ?>
                    <th class="num">ประชากร</th>
                    <?php if (in_array($key, $enrollableAgeBands, true)): ?>
                      <th class="num">ผู้เรียน</th>
                      <th class="num">อัตรา</th>
                    <?php endif; ?>
                  <?php endforeach; ?>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($rows as $amphoe => $bands): ?>
                  <tr>
                    <td><?= h($amphoe) ?></td>
                    <?php foreach ($ageBandLabels as $key => $label): ?>
                      <td class="num"><?= h(fmt_num($bands[$key]['population'])) ?></td>
                      <?php if (in_array($key, $enrollableAgeBands, true)): ?>
                        <td class="num"><?= h(fmt_num($bands[$key]['enrolled'])) ?></td>
                        <td class="num"><?= h($fmtRate($bands[$key]['rate'])) ?></td>
                      <?php endif; ?>
                    <?php endforeach; ?>
                  </tr>
                <?php endforeach; ?>
              </tbody>
              <tfoot>
                <tr class="row-total">
                  <td>รวมทั้งจังหวัด</td>
                  <?php foreach ($ageBandLabels as $key => $label): ?>
                    <td class="num"><?= h(fmt_num($provinceTotals[$key]['population'])) ?></td>
                    <?php if (in_array($key, $enrollableAgeBands, true)): ?>
                      <td class="num"><?= h(fmt_num($provinceTotals[$key]['enrolled'])) ?></td>
                      <td class="num"><?= h($fmtRate($provinceTotals[$key]['rate'])) ?></td>
                    <?php endif; ?>
                  <?php endforeach; ?>
                </tr>
              </tfoot>
            </table>
          </div>
        </div>

        <?php foreach ($enrollableAgeBands as $key): ?>
          <div class="card viz-root">
            <h2>อัตราการเข้าเรียน ช่วงอายุ <?= h($ageBandLabels[$key]) ?> แยกตามอำเภอ</h2>
            <?php
              $rateData = [];
              foreach ($rows as $amphoe => $bands) {
                  if ($bands[$key]['rate'] !== null) {
                      $rateData[$amphoe] = $bands[$key]['rate'];
                  }
              }
              arsort($rateData);
              render_bar_chart($rateData, $fmtRate);
            ?>
          </div>
        <?php endforeach; ?>

        <div class="card viz-root">
          <h2>จำนวนประชากร ช่วงอายุ 18-19 ปี แยกตามอำเภอ</h2>
          <p class="muted">ไม่มีคอลัมน์ผู้เรียน/อัตราการเข้าเรียน (ดูเหตุผลด้านบน)</p>
          <?php
            $pop1819 = [];
            foreach ($rows as $amphoe => $bands) {
                $pop1819[$amphoe] = $bands['age_18_19']['population'];
            }
            arsort($pop1819);
            render_bar_chart($pop1819, $fmtPeople);
          ?>
        </div>
      <?php endif; ?>
<?php
render_report_end();
