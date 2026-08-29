<?php
/**
 * ค้นหารหัสสถานศึกษาจากชื่อสถานศึกษา (ทำเนียบโรงเรียน / schools_master) — หน้าเปิดเผยต่อสาธารณะ
 * ไม่ต้อง login (ย้ายมาจาก school_search.php ฝั่ง backend ตามคำขอผู้ใช้งาน 2026-08-29 ให้ผู้ใช้ทั่วไป
 * เช่น เจ้าหน้าที่หน่วยงานที่ยังไม่มีบัญชีเข้าระบบ ก็ค้นหารหัสสถานศึกษาเองได้โดยไม่ต้องขอสิทธิ์เข้าระบบ
 * หลังบ้านก่อน) ใช้ layout เดียวกับ public_report.php/public_report_table.php (เมนูด้านซ้าย) เพื่อ
 * ให้สลับไปมาระหว่าง 3 หน้าสาธารณะนี้ได้สะดวก — แสดงเฉพาะข้อมูลทำเนียบโรงเรียน (รหัส/ชื่อ/สังกัด/
 * อำเภอ/ตำบล) ไม่มีข้อมูลการรายงานผลรายสถานศึกษาใด ๆ ปนอยู่
 *
 * ค้นแล้วเจอชื่อซ้ำกันได้บ่อย (คนละสังกัด/คนละอำเภอแต่ชื่อพ้องกัน เช่น "บ้านตลาด") จึงต้องแสดงสังกัด/
 * หน่วยงาน + อำเภอ + ตำบล กำกับทุกแถวเพื่อแยกให้ผู้ใช้เห็นชัด และทำเนียบเก็บแยกตามปีการศึกษา
 * (โรงเรียนเดียวกันอาจเปลี่ยนสังกัด/อำเภอข้ามปีได้ — ดู migrations/004_academic_year.sql) จึงต้อง
 * เลือกปีก่อนค้นเสมอ ค้นข้ามปีพร้อมกันไม่ได้เพราะรหัสสถานศึกษาเดียวกันในคนละปีอาจมีข้อมูลไม่ตรงกัน
 */
require_once __DIR__ . '/bootstrap.php';

$db = Db::conn();

$availableYears = $db->query('SELECT DISTINCT academic_year FROM schools_master ORDER BY academic_year DESC')
    ->fetchAll(PDO::FETCH_COLUMN);
$currentYear = Settings::currentAcademicYear($db);
if (!in_array($currentYear, $availableYears, true)) {
    array_unshift($availableYears, $currentYear);
}

$selectedYear = (int)($_GET['year'] ?? $currentYear);
if (!in_array($selectedYear, $availableYears, true) && $availableYears) {
    $selectedYear = $availableYears[0];
}

$q = trim($_GET['q'] ?? '');
$results = [];
if ($q !== '' && $availableYears) {
    $stmt = $db->prepare(
        'SELECT school_code, school_name, area_name, department, amphoe, tambon
         FROM schools_master
         WHERE academic_year = :year AND school_name LIKE :q
         ORDER BY school_name, area_name'
    );
    $stmt->execute(['year' => $selectedYear, 'q' => '%' . str_replace(['%', '_'], ['\%', '\_'], $q) . '%']);
    $results = $stmt->fetchAll();
}

// นับชื่อสถานศึกษาที่ซ้ำกัน (ในผลค้นหาที่แสดงอยู่) เพื่อเน้นแถวที่ต้องดูสังกัด/อำเภอประกอบให้ดี ๆ
$nameCounts = [];
foreach ($results as $r) {
    $nameCounts[$r['school_name']] = ($nameCounts[$r['school_name']] ?? 0) + 1;
}

// ต่อ query string เดิม (ปีที่เลือก) เวลาสลับไปหน้ากราฟ/ตารางสรุปอีก 2 หน้าผ่านเมนูด้านซ้าย — คนละ
// ชุดพารามิเตอร์กับหน้านั้น (ไม่มี dim/q) แต่ page ปลายทางไม่ได้ error ถ้ามีพารามิเตอร์เกินมาแค่เฉย ๆ
$reportNavQuery = http_build_query(['year' => $selectedYear]);
?>
<!doctype html>
<html lang="th">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>ค้นหารหัสสถานศึกษา — ptnedinfo</title>
<link rel="stylesheet" href="public/assets/style.css">
<link rel="stylesheet" href="public/assets/public_report.css">
</head>
<body>
<div class="topbar">
  <a href="public_report.php">ข้อมูลด้านการศึกษาจังหวัดปัตตานี</a>
  <nav>
    <a href="public_report.php?<?= h($reportNavQuery) ?>">ภาพรวม</a>
    <a href="public_report_table.php?<?= h($reportNavQuery) ?>">ตารางสรุปยอดรวม</a>
    <a href="public_school_search.php?<?= h($reportNavQuery) ?>" class="active">ค้นหารหัสสถานศึกษา</a>
    <a href="public_school_grade_table.php?year=<?= h((string)$selectedYear) ?>">ผู้เรียนรายชั้น</a>
    <a href="public_teacher_grade_table.php?year=<?= h((string)$selectedYear) ?>">ครูผู้สอนรายชั้น</a>
    <a href="login.php">เข้าสู่ระบบเจ้าหน้าที่</a>
  </nav>
</div>
<div class="container" style="max-width: 98vw;">
  <div class="report-main">
    <div class="card">
      <h1>ค้นหารหัสสถานศึกษา</h1>
      <p class="muted">ค้นจากชื่อสถานศึกษา (พิมพ์บางส่วนของชื่อก็ค้นเจอ) — ถ้าเจอชื่อซ้ำกันหลายแห่ง
        ระบบจะแสดงสังกัด/หน่วยงาน, อำเภอ, ตำบล กำกับทุกแถวเพื่อช่วยแยกให้ถูกต้อง ต้องเลือกปีการศึกษา
        ก่อนค้นเสมอ เพราะทำเนียบโรงเรียนเก็บแยกตามปี โรงเรียนเดียวกันอาจเปลี่ยนสังกัด/อำเภอข้ามปีได้</p>

      <?php if (!$availableYears): ?>
        <div class="alert alert-err">ยังไม่มีทำเนียบโรงเรียนในระบบเลย</div>
      <?php else: ?>
        <form method="get" class="filter-bar">
          <div class="field">
            <label>ชื่อสถานศึกษา</label>
            <input type="text" name="q" value="<?= h($q) ?>" placeholder="เช่น บ้านตลาด" autofocus>
          </div>
          <div class="field">
            <label>ปีการศึกษา</label>
            <select name="year">
              <?php foreach ($availableYears as $y): ?>
                <option value="<?= (int)$y ?>" <?= $selectedYear === (int)$y ? 'selected' : '' ?>><?= (int)$y ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <button class="btn" type="submit">ค้นหา</button>
        </form>
      <?php endif; ?>
    </div>

      <?php if ($q !== ''): ?>
        <div class="card">
          <h2>ผลการค้นหา "<?= h($q) ?>" ปีการศึกษา <?= (int)$selectedYear ?></h2>
          <?php if (!$results): ?>
            <p class="muted">ไม่พบสถานศึกษาที่ชื่อตรงกับคำค้นนี้ในปีการศึกษาที่เลือก</p>
          <?php else: ?>
            <p class="muted">พบ <?= count($results) ?> รายการ</p>
            <div class="report-table-scroll">
              <table class="stats-table">
                <thead>
                  <tr>
                    <th>รหัสสถานศึกษา</th>
                    <th>ชื่อสถานศึกษา</th>
                    <th>สังกัด/หน่วยงาน</th>
                    <th>ต้นสังกัด</th>
                    <th>อำเภอ</th>
                    <th>ตำบล</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($results as $r): ?>
                    <tr>
                      <td><code><?= h($r['school_code']) ?></code></td>
                      <td>
                        <?= h($r['school_name']) ?>
                        <?php if (($nameCounts[$r['school_name']] ?? 0) > 1): ?>
                          <span class="badge badge-err" title="มีสถานศึกษาชื่อนี้มากกว่า 1 แห่ง — ดูสังกัด/อำเภอประกอบให้ดี">ชื่อซ้ำ</span>
                        <?php endif; ?>
                      </td>
                      <td><?= h($r['area_name'] ?? '') ?: '<span class="muted">—</span>' ?></td>
                      <td><?= h($r['department'] ?? '') ?: '<span class="muted">—</span>' ?></td>
                      <td><?= h($r['amphoe'] ?? '') ?: '<span class="muted">—</span>' ?></td>
                      <td><?= h($r['tambon'] ?? '') ?: '<span class="muted">—</span>' ?></td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          <?php endif; ?>
        </div>
      <?php endif; ?>
  </div>
</div>
<footer style="text-align:center; padding:20px 16px; margin-top:12px;">
  <p class="muted">สำนักงานศึกษาธิการจังหวัดปัตตานี</p>
</footer>
</body>
</html>
