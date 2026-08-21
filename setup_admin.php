<?php
/**
 * หน้าเว็บสำหรับสร้างบัญชีแอดมินคนแรก (ใช้แทน scripts/create_admin.php บนโฮสต์ที่ไม่มี SSH)
 *
 * เพื่อความปลอดภัย หน้านี้จะทำงานได้ก็ต่อเมื่อ "ยังไม่มีผู้ใช้ในระบบเลย" เท่านั้น
 * หลังจากสร้างแอดมินคนแรกสำเร็จ หน้านี้จะถูกล็อกใช้งานต่อไม่ได้อีก (ปลอดภัยแม้จะลืมลบไฟล์)
 * แนะนำให้ลบไฟล์นี้ทิ้งหลังใช้งานเสร็จเพื่อความเรียบร้อย
 */
require_once __DIR__ . '/bootstrap.php';

$db = Db::conn();
$userCount = (int)$db->query('SELECT COUNT(*) FROM users')->fetchColumn();

$error = '';
$success = false;

if ($userCount > 0) {
    $error = 'มีบัญชีผู้ใช้ในระบบอยู่แล้ว หน้านี้ถูกล็อกไว้เพื่อความปลอดภัย (ลบไฟล์ setup_admin.php ออกจากเซิร์ฟเวอร์ได้เลย)';
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = (string)($_POST['password'] ?? '');
    $displayName = trim($_POST['display_name'] ?? '') ?: $username;

    if ($username === '' || strlen($password) < 8) {
        $error = 'กรุณากรอกชื่อผู้ใช้ และรหัสผ่านอย่างน้อย 8 ตัวอักษร';
    } else {
        $stmt = $db->prepare('INSERT INTO users (username, password_hash, display_name) VALUES (:u, :p, :d)');
        $stmt->execute([
            'u' => $username,
            'p' => password_hash($password, PASSWORD_DEFAULT),
            'd' => $displayName,
        ]);
        $success = true;
    }
}
?>
<!doctype html>
<html lang="th">
<head>
<meta charset="utf-8">
<title>ตั้งค่าแอดมินคนแรก — ptnedinfo</title>
<link rel="stylesheet" href="public/assets/style.css">
</head>
<body>
<div class="container" style="max-width:420px; margin-top:80px;">
  <div class="card">
    <h1>สร้างบัญชีแอดมินคนแรก</h1>
    <p class="muted">หน้านี้ใช้ได้ครั้งเดียวตอนติดตั้งระบบ แล้วจะถูกล็อกอัตโนมัติ</p>

    <?php if ($success): ?>
      <div class="alert alert-ok">สร้างบัญชี "<?= h($username) ?>" สำเร็จแล้ว — ไปที่ <a href="login.php">หน้า login</a> ได้เลย
      <br>อย่าลืมลบไฟล์ <code>setup_admin.php</code> ออกจากเซิร์ฟเวอร์เพื่อความปลอดภัย</div>
    <?php else: ?>
      <?php if ($error): ?><div class="alert alert-err"><?= h($error) ?></div><?php endif; ?>
      <?php if ($userCount === 0): ?>
        <form method="post">
          <div class="field">
            <label>ชื่อผู้ใช้ (username)</label>
            <input type="text" name="username" required autofocus>
          </div>
          <div class="field">
            <label>รหัสผ่าน (อย่างน้อย 8 ตัวอักษร)</label>
            <input type="password" name="password" required minlength="8">
          </div>
          <div class="field">
            <label>ชื่อที่แสดง</label>
            <input type="text" name="display_name" placeholder="เช่น ชื่อ-นามสกุล">
          </div>
          <button class="btn" type="submit" style="width:100%;">สร้างบัญชี</button>
        </form>
      <?php endif; ?>
    <?php endif; ?>
  </div>
</div>
</body>
</html>
