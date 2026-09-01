<?php
/**
 * อัปโหลดไฟล์ประชากรรายอายุจาก stat.bora.dopa.go.th (pipe-delimited .txt) แยกช่วงอายุ x อำเภอ ใช้คู่กับ
 * จำนวนผู้เรียนจริง (ฟอร์ม 4) คำนวณ "อัตราการเข้าเรียน" ในหน้าสาธารณะ public_population.php — เก็บแยก
 * ตามปีการศึกษาเหมือน schools_master.php (อัปโหลดใหม่ = แทนที่เฉพาะปีนั้น) ดูรายละเอียดตรรกะการนำเข้า/
 * เหตุผลที่ต้องกันนับซ้ำ-นับขาดที่ src/PopulationImporter.php (โครงสร้างไฟล์นี้ซับซ้อนกว่าที่เห็น
 * ตอนแรกมาก — อ่าน class doc ที่นั่นก่อนแก้โค้ดนี้)
 */
require_once __DIR__ . '/bootstrap.php';
Auth::requireLogin();

$db = Db::conn();
$currentYear = Settings::currentAcademicYear($db);

$flash = '';
$flashType = 'ok';
$report = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? 'upload') === 'upload') {
    $academicYear = (int)($_POST['academic_year'] ?? $currentYear);
    if ($academicYear < 2500 || $academicYear > 2700) {
        $flash = 'ปีการศึกษาไม่ถูกต้อง';
        $flashType = 'err';
    } elseif (empty($_FILES['population_file']) || $_FILES['population_file']['error'] !== UPLOAD_ERR_OK) {
        $flash = 'กรุณาเลือกไฟล์ .txt ที่ดาวน์โหลดจาก stat.bora.dopa.go.th';
        $flashType = 'err';
    } else {
        try {
            $rawText = file_get_contents($_FILES['population_file']['tmp_name']);
            if ($rawText === false || trim($rawText) === '') {
                throw new RuntimeException('ไฟล์ว่างเปล่าหรืออ่านไม่ได้');
            }
            $tambonLookup = PopulationImporter::buildTambonLookup($db, $academicYear);
            $result = PopulationImporter::parse($rawText, $tambonLookup);
            if (!$result['byAmphoe']) {
                throw new RuntimeException('ไม่พบข้อมูลระดับอำเภอในไฟล์นี้เลย — ตรวจสอบว่าเป็นไฟล์รูปแบบเดียวกับที่ระบบรองรับ (ดูตัวอย่างที่ stat.bora.dopa.go.th)');
            }

            $db->beginTransaction();
            $del = $db->prepare('DELETE FROM population_by_age WHERE academic_year = :y');
            $del->execute(['y' => $academicYear]);
            $ins = $db->prepare(
                'INSERT INTO population_by_age
                    (academic_year, amphoe, age_3_5_male, age_3_5_female, age_6_11_male, age_6_11_female,
                     age_12_14_male, age_12_14_female, age_15_17_male, age_15_17_female, age_18_19_male, age_18_19_female)
                 VALUES
                    (:academic_year, :amphoe, :age_3_5_male, :age_3_5_female, :age_6_11_male, :age_6_11_female,
                     :age_12_14_male, :age_12_14_female, :age_15_17_male, :age_15_17_female, :age_18_19_male, :age_18_19_female)'
            );
            foreach ($result['byAmphoe'] as $amphoeName => $bands) {
                $ins->execute([
                    'academic_year'    => $academicYear,
                    'amphoe'           => $amphoeName,
                    'age_3_5_male'     => $bands['age_3_5_male'],
                    'age_3_5_female'   => $bands['age_3_5_female'],
                    'age_6_11_male'    => $bands['age_6_11_male'],
                    'age_6_11_female'  => $bands['age_6_11_female'],
                    'age_12_14_male'   => $bands['age_12_14_male'],
                    'age_12_14_female' => $bands['age_12_14_female'],
                    'age_15_17_male'   => $bands['age_15_17_male'],
                    'age_15_17_female' => $bands['age_15_17_female'],
                    'age_18_19_male'   => $bands['age_18_19_male'],
                    'age_18_19_female' => $bands['age_18_19_female'],
                ]);
            }
            $db->commit();

            $report = $result;
            $flash = "อัปโหลดข้อมูลประชากรปีการศึกษา {$academicYear} สำเร็จ — แทนที่ข้อมูลเดิมของปีนี้ด้วย "
                . count($result['byAmphoe']) . ' อำเภอ';
            if ($result['warnings']) {
                $flashType = 'err';
                $flash .= ' (มีข้อสังเกตด้านล่าง กรุณาตรวจสอบ)';
            }
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            $flash = 'เกิดข้อผิดพลาด: ' . $e->getMessage();
            $flashType = 'err';
        }
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_year') {
    $deleteYear = (int)($_POST['academic_year'] ?? 0);
    $stmt = $db->prepare('DELETE FROM population_by_age WHERE academic_year = :y');
    $stmt->execute(['y' => $deleteYear]);
    $flash = "ลบข้อมูลประชากรปีการศึกษา {$deleteYear} ออกจากระบบแล้ว";
}

$yearRows = $db->query(
    'SELECT academic_year, COUNT(*) AS cnt, SUM(age_3_5_male+age_3_5_female+age_6_11_male+age_6_11_female
        +age_12_14_male+age_12_14_female+age_15_17_male+age_15_17_female+age_18_19_male+age_18_19_female) AS total_pop,
     MAX(updated_at) AS last_updated
     FROM population_by_age GROUP BY academic_year ORDER BY academic_year DESC'
)->fetchAll();
?>
<!doctype html>
<html lang="th">
<head>
<meta charset="utf-8">
<title>ข้อมูลประชากรรายอายุ — ptnedinfo</title>
<link rel="stylesheet" href="public/assets/style.css">
</head>
<body>
<div class="topbar">
  <a href="index.php">ptnedinfo — ระบบรวบรวมข้อมูล</a>
  <nav>
    <a href="review.php">รายการที่ต้องตรวจสอบ</a>
    <a href="uploads_history.php">ประวัติการอัปโหลด</a>
    <a href="schools_master.php">ทำเนียบโรงเรียน</a>
    <a href="population_upload.php">ข้อมูลประชากรรายอายุ</a>
    <a href="public_school_search.php" target="_blank">ค้นหารหัสสถานศึกษา</a>
    <a href="public_report.php" target="_blank">สถิติการศึกษาจังหวัด (สาธารณะ)</a>
    <a href="settings.php">ตั้งค่า</a>
    <span class="muted"><?= h(Auth::displayName()) ?></span>
    &nbsp;&nbsp;<a href="logout.php">ออกจากระบบ</a>
  </nav>
</div>
<div class="container">
  <div class="card">
    <h1>ข้อมูลประชากรรายอายุ</h1>
    <p class="muted">ใช้เทียบกับจำนวนผู้เรียนจริง (ตารางที่ 4) คำนวณ "อัตราการเข้าเรียน" แยกช่วงอายุ x
      อำเภอ ในหน้าสาธารณะ — ดาวน์โหลดไฟล์ต้นทางได้ที่
      <a href="https://stat.bora.dopa.go.th/stat/statnew/statMenu/newStat/home.php" target="_blank" rel="noopener">stat.bora.dopa.go.th</a>
      (เลือกจังหวัดปัตตานี, ประชากรแยกรายอายุ) เก็บแยกตามปีการศึกษา อัปโหลดไฟล์ปีใดจะแทนที่เฉพาะข้อมูล
      ของปีนั้น ไม่กระทบปีอื่น</p>
    <p class="muted"><strong>ต้องอัปโหลดทำเนียบโรงเรียน (schools_master) ของปีเดียวกันไว้ก่อนแล้ว</strong>
      — ระบบใช้ทำเนียบหาว่าตำบลในเขตเทศบาลที่แยกอยู่ท้ายไฟล์ต้นทางเป็นของอำเภอไหน ถ้ายังไม่มีทำเนียบปีนี้
      ตำบลกลุ่มนั้นจะนับไม่ได้ (โผล่เป็น "หาอำเภอไม่เจอ" หลังอัปโหลด)</p>

    <?php if ($flash): ?>
      <div class="alert <?= $flashType === 'ok' ? 'alert-ok' : 'alert-err' ?>"><?= h($flash) ?></div>
    <?php endif; ?>

    <?php if ($report): ?>
      <div class="card" style="background:#f9fafb;">
        <h3>รายงานผลการนำเข้า</h3>
        <p>ยอดรวมทั้งจังหวัดในไฟล์: <?= h(number_format($report['provinceTotal'])) ?> คน —
           ยอดรวมหลังประมวลผล: <?= h(number_format(array_sum(array_column($report['byAmphoe'], 'total')))) ?> คน</p>
        <?php if ($report['addedByAmphoe']): ?>
          <p>อำเภอที่มีการบวกยอดในเขตเทศบาลเพิ่มจากทำเนียบโรงเรียน:</p>
          <ul>
            <?php foreach ($report['addedByAmphoe'] as $amphoe => $added): ?>
              <li><?= h($amphoe) ?>: +<?= h(number_format($added)) ?> คน</li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>
        <?php if ($report['unresolved']): ?>
          <p style="color:#dc2626;"><strong>ตำบลที่หาอำเภอไม่เจอ (ไม่ถูกนับเข้าอำเภอไหนเลย —
            ยอดรวมทั้งจังหวัดจะขาดไปเท่ากับยอดด้านล่างนี้):</strong></p>
          <ul>
            <?php foreach ($report['unresolved'] as $u): ?>
              <li><?= h($u['name']) ?>: <?= h(number_format($u['population'])) ?> คน</li>
            <?php endforeach; ?>
          </ul>
          <p class="muted">แก้ไขโดยอัปโหลดทำเนียบโรงเรียน (schools_master) ของปีนี้ให้ครบก่อน แล้วอัปโหลด
            ไฟล์ประชากรซ้ำอีกครั้ง (แทนที่ข้อมูลเดิมของปีนี้ได้ตามปกติ ไม่ต้องลบเองก่อน)</p>
        <?php endif; ?>
        <?php foreach ($report['warnings'] as $w): ?>
          <p style="color:#dc2626;"><?= h($w) ?></p>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <form method="post" enctype="multipart/form-data">
      <input type="hidden" name="action" value="upload">
      <div class="field">
        <label>ไฟล์ .txt ประชากรรายอายุ (ดาวน์โหลดจาก stat.bora.dopa.go.th)</label>
        <input type="file" name="population_file" accept=".txt" required>
      </div>
      <div class="field">
        <label>ปีการศึกษา</label>
        <input type="text" name="academic_year" value="<?= h((string)$currentYear) ?>" style="max-width:150px;" required>
      </div>
      <button class="btn" type="submit">อัปโหลดข้อมูลประชากร</button>
    </form>
  </div>

  <div class="card">
    <h2>ข้อมูลประชากรที่มีอยู่ในระบบ</h2>
    <?php if (!$yearRows): ?>
      <p class="muted">ยังไม่เคยอัปโหลดข้อมูลประชากรเลย</p>
    <?php else: ?>
      <div class="table-scroll">
        <table>
          <thead>
            <tr>
              <th>ปีการศึกษา</th>
              <th>จำนวนอำเภอ</th>
              <th>ยอดรวมประชากร (5 ช่วงอายุ)</th>
              <th>อัปเดตล่าสุด</th>
              <th>จัดการ</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($yearRows as $yr): ?>
              <tr>
                <td><?= h((string)$yr['academic_year']) ?></td>
                <td><?= (int)$yr['cnt'] ?></td>
                <td><?= number_format((int)$yr['total_pop']) ?></td>
                <td class="muted"><?= h((string)$yr['last_updated']) ?></td>
                <td>
                  <form method="post" onsubmit="return confirm('ยืนยันลบข้อมูลประชากรปีการศึกษา <?= (int)$yr['academic_year'] ?> ทั้งหมด? การกระทำนี้ย้อนกลับไม่ได้');" style="margin:0;">
                    <input type="hidden" name="action" value="delete_year">
                    <input type="hidden" name="academic_year" value="<?= (int)$yr['academic_year'] ?>">
                    <button type="submit" class="btn" style="background:#dc2626; padding:6px 12px; font-size:13px;">ลบข้อมูลปีนี้</button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</div>
</body>
</html>
