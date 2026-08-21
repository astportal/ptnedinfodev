<?php
require_once __DIR__ . '/bootstrap.php';
Auth::requireLogin();

$db = Db::conn();
$forms = form_registry();

$countStmt = $db->prepare('SELECT COUNT(*) FROM submissions WHERE form_key = :fk AND sheet_name = :sn');
$lastStmt  = $db->prepare('SELECT uploaded_at FROM uploads WHERE form_key = :fk AND sheet_name = :sn ORDER BY uploaded_at DESC LIMIT 1');
$needsReviewTotal = (int)$db->query('SELECT COUNT(*) FROM submission_values WHERE needs_review = 1')->fetchColumn();
?>
<!doctype html>
<html lang="th">
<head>
<meta charset="utf-8">
<title>แดชบอร์ด — ptnedinfo</title>
<link rel="stylesheet" href="public/assets/style.css">
</head>
<body>
<div class="topbar">
  <a href="index.php">ptnedinfo — ระบบรวบรวมข้อมูล</a>
  <nav>
    <a href="review.php">รายการที่ต้องตรวจสอบ<?= $needsReviewTotal > 0 ? " ({$needsReviewTotal})" : '' ?></a>
    <a href="uploads_history.php">ประวัติการอัปโหลด</a>
    <span class="muted"><?= h(Auth::displayName()) ?></span>
    &nbsp;&nbsp;<a href="logout.php">ออกจากระบบ</a>
  </nav>
</div>
<div class="container">
  <h1>รายการแบบฟอร์ม</h1>
  <p class="muted">เลือกฟอร์มเพื่ออัปโหลดไฟล์ที่ได้รับจากหน่วยงาน หรือดู/ส่งออกข้อมูลที่รวบรวมแล้ว</p>

  <?php if ($needsReviewTotal > 0): ?>
    <div class="alert alert-err">
      พบ <?= $needsReviewTotal ?> ค่าที่ระบบไม่แน่ใจว่าเป็นตัวเลขหรือไม่ ต้องการให้ตรวจสอบ —
      <a href="review.php">ไปตรวจสอบตอนนี้</a>
    </div>
  <?php endif; ?>

  <div class="card">
    <div class="table-scroll">
      <table>
        <thead>
          <tr>
            <th>ฟอร์ม</th>
            <th>ชีท</th>
            <th>จำนวนรายการ</th>
            <th>อัปโหลดล่าสุด</th>
            <th>จัดการ</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($forms as $formKey => $formDef): ?>
          <?php foreach ($formDef['sheets'] as $sheetDef): ?>
            <?php
              $sheetName = $sheetDef['sheet_name'];
              $countStmt->execute(['fk' => $formKey, 'sn' => $sheetName]);
              $count = (int)$countStmt->fetchColumn();
              $lastStmt->execute(['fk' => $formKey, 'sn' => $sheetName]);
              $lastUpload = $lastStmt->fetchColumn();
            ?>
            <tr>
              <td><?= h($formDef['form_label']) ?></td>
              <td><?= h($sheetName) ?></td>
              <td><?= $count ?></td>
              <td class="muted"><?= $lastUpload ? h($lastUpload) : '— ยังไม่มี' ?></td>
              <td style="white-space:nowrap;">
                <a class="btn" style="padding:6px 12px; font-size:13px;" href="upload.php?form=<?= urlencode($formKey) ?>">อัปโหลดไฟล์</a>
                <a class="btn btn-secondary" style="padding:6px 12px; font-size:13px;" href="view.php?form=<?= urlencode($formKey) ?>&sheet=<?= urlencode($sheetName) ?>">ดูข้อมูล</a>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
</body>
</html>
