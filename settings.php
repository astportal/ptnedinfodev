<?php
require_once __DIR__ . '/bootstrap.php';
Auth::requireLogin();

$db = Db::conn();

$flash = '';
$flashType = 'ok';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'set_current_year') {
    $year = trim($_POST['current_academic_year'] ?? '');
    if (!preg_match('/^\d{4}$/', $year)) {
        $flash = 'กรุณากรอกปีการศึกษาเป็นตัวเลข 4 หลัก (พ.ศ.)';
        $flashType = 'err';
    } else {
        try {
            Settings::set($db, 'current_academic_year', $year);
            $flash = "ตั้งปีการศึกษาปัจจุบันเป็น {$year} เรียบร้อย — การอัปโหลดไฟล์ครั้งถัดไปจะถูกแปะปีนี้ให้อัตโนมัติ";
        } catch (Throwable $e) {
            $flash = 'บันทึกไม่สำเร็จ: ' . $e->getMessage();
            $flashType = 'err';
        }
    }
}

$currentYear = Settings::currentAcademicYear($db);
?>
<!doctype html>
<html lang="th">
<head>
<meta charset="utf-8">
<title>ตั้งค่าระบบ — ptnedinfo</title>
<link rel="stylesheet" href="public/assets/style.css">
</head>
<body>
<div class="topbar">
  <a href="index.php">ptnedinfo — ระบบรวบรวมข้อมูล</a>
  <nav>
    <a href="index.php">แดชบอร์ด</a>
    <a href="review.php">รายการที่ต้องตรวจสอบ</a>
    <a href="uploads_history.php">ประวัติการอัปโหลด</a>
    <a href="schools_master.php">ทำเนียบโรงเรียน</a>
    <a href="school_search.php">ค้นหารหัสสถานศึกษา</a>
    <span class="muted"><?= h(Auth::displayName()) ?></span>
    &nbsp;&nbsp;<a href="logout.php">ออกจากระบบ</a>
  </nav>
</div>
<div class="container">
  <div class="card">
    <h1>ตั้งค่าระบบ</h1>

    <?php if ($flash): ?>
      <div class="alert <?= $flashType === 'ok' ? 'alert-ok' : 'alert-err' ?>"><?= h($flash) ?></div>
    <?php endif; ?>

    <h2>ปีการศึกษาปัจจุบัน</h2>
    <p class="muted">ไฟล์แบบฟอร์มสำรวจ (1-15) ไม่มีคอลัมน์ปีการศึกษาอยู่ในไฟล์ ระบบจึงแปะปีนี้ให้
      ทุกไฟล์ที่อัปโหลด<strong>ใหม่</strong>โดยอัตโนมัติ — เปลี่ยนค่านี้ตอนขึ้นปีการศึกษาใหม่ก่อน
      เริ่มอัปโหลดข้อมูลปีใหม่ (ไม่กระทบข้อมูลปีเก่าที่อัปโหลดไปแล้ว ยังอยู่ครบทุกปี เลือกดูย้อนหลัง
      ได้ที่หน้าแดชบอร์ด)</p>
    <form method="post" style="display:flex; gap:12px; align-items:flex-end;">
      <div class="field" style="margin-bottom:0;">
        <label>ปีการศึกษาปัจจุบัน (พ.ศ.)</label>
        <input type="text" name="current_academic_year" value="<?= h((string)$currentYear) ?>" style="width:120px;" pattern="\d{4}" required>
      </div>
      <input type="hidden" name="action" value="set_current_year">
      <button class="btn" type="submit">บันทึก</button>
    </form>
  </div>
</div>
</body>
</html>
