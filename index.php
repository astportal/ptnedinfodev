<?php
require_once __DIR__ . '/bootstrap.php';
Auth::requireLogin();

$db = Db::conn();
$forms = form_registry();

$countStmt = $db->prepare('SELECT COUNT(*) FROM submissions WHERE form_key = :fk AND sheet_name = :sn');
$lastStmt  = $db->prepare('SELECT uploaded_at FROM uploads WHERE form_key = :fk AND sheet_name = :sn ORDER BY uploaded_at DESC LIMIT 1');
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
    <span class="muted"><?= h(Auth::displayName()) ?></span>
    &nbsp;&nbsp;<a href="logout.php">ออกจากระบบ</a>
  </nav>
</div>
<div class="container">
  <h1>รายการแบบฟอร์ม</h1>
  <p class="muted">เลือกฟอร์มเพื่ออัปโหลดไฟล์ที่ได้รับจากหน่วยงาน หรือดู/ส่งออกข้อมูลที่รวบรวมแล้ว</p>

  <div class="grid-cards">
    <?php foreach ($forms as $formKey => $formDef): ?>
      <?php foreach ($formDef['sheets'] as $sheetDef): ?>
        <?php
          $sheetName = $sheetDef['sheet_name'];
          $countStmt->execute(['fk' => $formKey, 'sn' => $sheetName]);
          $count = (int)$countStmt->fetchColumn();
          $lastStmt->execute(['fk' => $formKey, 'sn' => $sheetName]);
          $lastUpload = $lastStmt->fetchColumn();
        ?>
        <div class="card form-card">
          <div class="muted"><?= h($formDef['form_label']) ?></div>
          <h2 style="margin-top:4px;"><?= h($sheetName) ?></h2>
          <div class="count"><?= $count ?></div>
          <div class="muted">รายการที่รวบรวมแล้ว</div>
          <div class="muted" style="margin:8px 0 16px;">
            อัปโหลดล่าสุด: <?= $lastUpload ? h($lastUpload) : '— ยังไม่มี' ?>
          </div>
          <a class="btn" href="upload.php?form=<?= urlencode($formKey) ?>">อัปโหลดไฟล์</a>
          <a class="btn btn-secondary" href="view.php?form=<?= urlencode($formKey) ?>&sheet=<?= urlencode($sheetName) ?>">ดูข้อมูล</a>
        </div>
      <?php endforeach; ?>
    <?php endforeach; ?>
  </div>
</div>
</body>
</html>
