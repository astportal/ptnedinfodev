<?php
require_once __DIR__ . '/bootstrap.php';
Auth::start();

if (Auth::check()) {
    header('Location: index.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = (string)($_POST['password'] ?? '');
    if (Auth::attempt(Db::conn(), $username, $password)) {
        header('Location: index.php');
        exit;
    }
    $error = 'ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง';
}
?>
<!doctype html>
<html lang="th">
<head>
<meta charset="utf-8">
<title>เข้าสู่ระบบ — ptnedinfo</title>
<link rel="stylesheet" href="public/assets/style.css">
</head>
<body>
<div class="container" style="max-width:400px; margin-top:80px;">
  <div class="card">
    <h1>เข้าสู่ระบบ</h1>
    <p class="muted">ระบบรวบรวมข้อมูลแบบฟอร์ม ptnedinfo</p>
    <?php if ($error): ?><div class="alert alert-err"><?= h($error) ?></div><?php endif; ?>
    <form method="post">
      <div class="field">
        <label>ชื่อผู้ใช้</label>
        <input type="text" name="username" required autofocus>
      </div>
      <div class="field">
        <label>รหัสผ่าน</label>
        <input type="password" name="password" required>
      </div>
      <button class="btn" type="submit" style="width:100%;">เข้าสู่ระบบ</button>
    </form>
  </div>
</div>
</body>
</html>
