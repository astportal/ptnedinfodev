<?php
require_once __DIR__ . '/bootstrap.php';
Auth::requireLogin();

$db = Db::conn();
$forms = form_registry();
$formKey = $_GET['form'] ?? $_POST['form'] ?? array_key_first($forms);
if (!isset($forms[$formKey])) {
    $formKey = array_key_first($forms);
}
$formDef = $forms[$formKey];
$currentYear = Settings::currentAcademicYear($db);

$results = null;
$uploadError = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $formKey = $_POST['form'];
    $formDef = $forms[$formKey] ?? null;

    if (!$formDef) {
        $uploadError = 'ไม่พบฟอร์มที่เลือก';
    } elseif (empty($_FILES['xlsx_file']) || $_FILES['xlsx_file']['error'] !== UPLOAD_ERR_OK) {
        $uploadError = 'กรุณาเลือกไฟล์ .xlsx ที่ต้องการอัปโหลด';
    } else {
        $tmpPath = $_FILES['xlsx_file']['tmp_name'];
        $originalName = $_FILES['xlsx_file']['name'];
        if (strtolower(pathinfo($originalName, PATHINFO_EXTENSION)) !== 'xlsx') {
            $uploadError = 'รองรับเฉพาะไฟล์ .xlsx เท่านั้น';
        } else {
            $storedName = date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.xlsx';
            $destDir = __DIR__ . '/uploads';
            if (!is_dir($destDir)) {
                mkdir($destDir, 0775, true);
            }
            $destPath = $destDir . '/' . $storedName;

            if (!move_uploaded_file($tmpPath, $destPath)) {
                $uploadError = 'บันทึกไฟล์ไม่สำเร็จ กรุณาลองใหม่';
            } else {
                try {
                    $importer = new Importer($db);
                    $results = $importer->importFile($formKey, $formDef, $destPath, $originalName, $storedName, Auth::userId(), $currentYear);
                } catch (Throwable $e) {
                    $uploadError = 'เกิดข้อผิดพลาดขณะประมวลผลไฟล์: ' . $e->getMessage();
                }
            }
        }
    }
}
?>
<!doctype html>
<html lang="th">
<head>
<meta charset="utf-8">
<title>อัปโหลดไฟล์ — ptnedinfo</title>
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
    <a href="settings.php">ตั้งค่า</a>
    <span class="muted"><?= h(Auth::displayName()) ?></span>
    &nbsp;&nbsp;<a href="logout.php">ออกจากระบบ</a>
  </nav>
</div>
<div class="container">
  <div class="card">
    <h1>อัปโหลดไฟล์แบบฟอร์ม</h1>
    <p class="muted">เลือกฟอร์มและไฟล์ .xlsx ที่หน่วยงานกรอกและส่งกลับมา ระบบจะดึงข้อมูลอัตโนมัติ
      หากหน่วยงาน/รหัสสถานศึกษาเดิมเคยอัปโหลดมาก่อน<strong>ในปีการศึกษาเดียวกัน</strong>
      ข้อมูลจะถูกแทนที่ด้วยไฟล์ล่าสุด</p>
    <p class="muted">ไฟล์ที่อัปโหลดจะถูกบันทึกเป็นของ <strong>ปีการศึกษา <?= h((string)$currentYear) ?></strong>
      — <a href="settings.php">เปลี่ยนปีการศึกษาปัจจุบัน</a></p>

    <?php if ($uploadError): ?><div class="alert alert-err"><?= h($uploadError) ?></div><?php endif; ?>

    <form method="post" enctype="multipart/form-data">
      <div class="field">
        <label>ฟอร์ม</label>
        <select name="form">
          <?php foreach ($forms as $key => $def): ?>
            <option value="<?= h($key) ?>" <?= $key === $formKey ? 'selected' : '' ?>>
              <?= h($def['form_label']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field">
        <label>ไฟล์ .xlsx</label>
        <input type="file" name="xlsx_file" accept=".xlsx" required>
      </div>
      <button class="btn" type="submit">อัปโหลดและนำเข้าข้อมูล</button>
    </form>
  </div>

  <?php if ($results): ?>
    <div class="card">
      <h2>ผลการนำเข้า</h2>
      <table>
        <thead><tr><th>ชีท</th><th>สถานะ</th><th>รายละเอียด</th></tr></thead>
        <tbody>
        <?php foreach ($results['uploads'] as $r): ?>
          <tr>
            <td><?= h($r['sheet_name']) ?></td>
            <td>
              <?php if ($r['status'] === 'parsed'): ?>
                <span class="badge badge-ok">สำเร็จ</span>
              <?php else: ?>
                <span class="badge badge-err">ผิดพลาด</span>
              <?php endif; ?>
            </td>
            <td style="white-space: normal;"><?= h($r['message']) ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
      <?php
        // Link to whichever sheet was actually parsed from this upload — sheet_name in
        // $results is the raw Excel sheet name, but data is stored under db_sheet_name
        // when a sheetDef declares one (e.g. a merged form like 14ก+14ข), so look that up.
        $viewSheetName = null;
        foreach ($results['uploads'] as $r) {
            if ($r['status'] === 'parsed') {
                foreach ($formDef['sheets'] as $sd) {
                    if ($sd['sheet_name'] === $r['sheet_name']) {
                        $viewSheetName = $sd['db_sheet_name'] ?? $sd['sheet_name'];
                        break 2;
                    }
                }
            }
        }
      ?>
      <p style="margin-top:16px;">
        <?php if ($viewSheetName !== null): ?>
          <a class="btn btn-secondary" href="view.php?form=<?= urlencode($formKey) ?>&sheet=<?= urlencode($viewSheetName) ?>">ดูข้อมูลที่รวบรวม</a>
        <?php endif; ?>
        <?php if (array_sum(array_column($results['uploads'], 'needs_review')) + array_sum(array_column($results['uploads'], 'school_code_issues'))): ?>
          <a class="btn" style="background:#dc2626;" href="review.php">ไปตรวจสอบรายการที่ต้องตรวจสอบ</a>
        <?php endif; ?>
      </p>
    </div>
  <?php endif; ?>
</div>
</body>
</html>
