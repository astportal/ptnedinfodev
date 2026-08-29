<?php
/**
 * ค้นหารหัสสถานศึกษาจากชื่อสถานศึกษา (ทำเนียบโรงเรียน / schools_master) — สำหรับเจ้าหน้าที่ที่จำ
 * รหัสสถานศึกษาไม่ได้ตอนกรอกฟอร์ม ค้นแล้วเจอชื่อซ้ำกันได้บ่อย (คนละสังกัด/คนละอำเภอแต่ชื่อพ้องกัน
 * เช่น "บ้านตลาด") จึงต้องแสดงสังกัด/หน่วยงาน + อำเภอ + ตำบล กำกับทุกแถวเพื่อแยกให้ผู้ใช้เห็นชัด
 * และทำเนียบเก็บแยกตามปีการศึกษา (โรงเรียนเดียวกันอาจเปลี่ยนสังกัด/อำเภอข้ามปีได้ — ดู
 * migrations/004_academic_year.sql) จึงต้องเลือกปีก่อนค้นเสมอ ค้นข้ามปีพร้อมกันไม่ได้เพราะรหัส
 * สถานศึกษาเดียวกันในคนละปีอาจมีข้อมูลไม่ตรงกัน
 */
require_once __DIR__ . '/bootstrap.php';
Auth::requireLogin();

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
?>
<!doctype html>
<html lang="th">
<head>
<meta charset="utf-8">
<title>ค้นหารหัสสถานศึกษา — ptnedinfo</title>
<link rel="stylesheet" href="public/assets/style.css">
</head>
<body>
<div class="topbar">
  <a href="index.php">ptnedinfo — ระบบรวบรวมข้อมูล</a>
  <nav>
    <a href="review.php">รายการที่ต้องตรวจสอบ</a>
    <a href="uploads_history.php">ประวัติการอัปโหลด</a>
    <a href="schools_master.php">ทำเนียบโรงเรียน</a>
    <a href="school_search.php">ค้นหารหัสสถานศึกษา</a>
    <a href="public_report.php" target="_blank">สถิติการศึกษาจังหวัด (สาธารณะ)</a>
    <a href="settings.php">ตั้งค่า</a>
    <span class="muted"><?= h(Auth::displayName()) ?></span>
    &nbsp;&nbsp;<a href="logout.php">ออกจากระบบ</a>
  </nav>
</div>
<div class="container">
  <div class="card">
    <h1>ค้นหารหัสสถานศึกษา</h1>
    <p class="muted">ค้นจากชื่อสถานศึกษา (พิมพ์บางส่วนของชื่อก็ค้นเจอ) — ถ้าเจอชื่อซ้ำกันหลายแห่ง
      ระบบจะแสดงสังกัด/หน่วยงาน, อำเภอ, ตำบล กำกับทุกแถวเพื่อช่วยแยกให้ถูกต้อง ต้องเลือกปีการศึกษา
      ก่อนค้นเสมอ เพราะทำเนียบโรงเรียนเก็บแยกตามปี โรงเรียนเดียวกันอาจเปลี่ยนสังกัด/อำเภอข้ามปีได้</p>

    <?php if (!$availableYears): ?>
      <div class="alert alert-err">ยังไม่มีทำเนียบโรงเรียนในระบบเลย — ไปอัปโหลดที่หน้า
        <a href="schools_master.php">ทำเนียบโรงเรียน</a> ก่อน</div>
    <?php else: ?>
      <form method="get" class="filter-bar" style="display:flex; gap:16px; align-items:flex-end; flex-wrap:wrap; margin:0;">
        <div class="field" style="margin-bottom:0; min-width:220px;">
          <label>ชื่อสถานศึกษา</label>
          <input type="text" name="q" value="<?= h($q) ?>" placeholder="เช่น บ้านตลาด" autofocus style="width:100%;">
        </div>
        <div class="field" style="margin-bottom:0; min-width:150px;">
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
        <div class="table-scroll">
          <table>
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
</body>
</html>
