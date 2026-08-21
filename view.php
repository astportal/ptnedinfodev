<?php
require_once __DIR__ . '/bootstrap.php';
Auth::requireLogin();

$db = Db::conn();
$forms = form_registry();
$formKey = $_GET['form'] ?? '';
$sheetName = $_GET['sheet'] ?? '';

if (!isset($forms[$formKey])) {
    die('ไม่พบฟอร์มที่ระบุ');
}
$formDef = $forms[$formKey];
$sheetDef = null;
foreach ($formDef['sheets'] as $sd) {
    if ($sd['sheet_name'] === $sheetName) {
        $sheetDef = $sd;
        break;
    }
}
if (!$sheetDef) {
    die('ไม่พบชีทที่ระบุ');
}

$flash = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_submission') {
    $submissionId = (int)($_POST['submission_id'] ?? 0);
    $stmt = $db->prepare('DELETE FROM submissions WHERE id = :id AND form_key = :fk AND sheet_name = :sn');
    $stmt->execute(['id' => $submissionId, 'fk' => $formKey, 'sn' => $sheetName]);
    $flash = 'ลบรายการนี้ออกจากระบบแล้ว';
}

$reporting = new Reporting($db);
$pivot = $reporting->pivot($formKey, $sheetName);
$identityLabels = [
    'seq_no' => 'ลำดับที่', 'school_code' => 'รหัสสถานศึกษา', 'agency_name' => 'สังกัด/หน่วยงาน',
    'school_name' => 'ชื่อสถานศึกษา', 'amphoe' => 'อำเภอ', 'tambon' => 'ตำบล',
];
?>
<!doctype html>
<html lang="th">
<head>
<meta charset="utf-8">
<title><?= h($sheetName) ?> — ptnedinfo</title>
<link rel="stylesheet" href="public/assets/style.css">
</head>
<body>
<div class="topbar">
  <a href="index.php">ptnedinfo — ระบบรวบรวมข้อมูล</a>
  <nav>
    <a href="index.php">แดชบอร์ด</a>
    <a href="upload.php?form=<?= urlencode($formKey) ?>">อัปโหลดไฟล์</a>
    <a href="review.php">รายการที่ต้องตรวจสอบ</a>
    <span class="muted"><?= h(Auth::displayName()) ?></span>
    &nbsp;&nbsp;<a href="logout.php">ออกจากระบบ</a>
  </nav>
</div>
<div class="container" style="max-width: 100%;">
  <div class="card">
    <h1><?= h($formDef['form_label']) ?></h1>
    <h2 class="muted" style="font-weight:400;"><?= h($sheetName) ?></h2>
    <p class="muted">ทั้งหมด <?= count($pivot['rows']) ?> รายการ</p>
    <a class="btn" href="export.php?form=<?= urlencode($formKey) ?>&sheet=<?= urlencode($sheetName) ?>">ดาวน์โหลด CSV (รวมทุกหน่วยงาน)</a>
    <a class="btn" href="export_tidy.php?form=<?= urlencode($formKey) ?>&sheet=<?= urlencode($sheetName) ?>">ดาวน์โหลดสำหรับทำ Pivot Table</a>
    <a class="btn btn-secondary" href="upload.php?form=<?= urlencode($formKey) ?>">อัปโหลดไฟล์เพิ่ม</a>
    <a class="btn btn-secondary" href="uploads_history.php?form=<?= urlencode($formKey) ?>&sheet=<?= urlencode($sheetName) ?>">ประวัติการอัปโหลด / ลบไฟล์</a>
  </div>

  <?php if ($flash): ?><div class="alert alert-ok"><?= h($flash) ?></div><?php endif; ?>

  <div class="card">
    <?php if (!$pivot['rows']): ?>
      <p class="muted">ยังไม่มีข้อมูล — อัปโหลดไฟล์ที่หน่วยงานส่งกลับมาก่อน</p>
    <?php else: ?>
      <div class="table-scroll">
        <table>
          <thead>
            <tr>
              <?php foreach ($identityLabels as $key => $label): ?>
                <th><?= h($label) ?></th>
              <?php endforeach; ?>
              <?php foreach ($pivot['extra_identity_fields'] as $field): ?>
                <th><?= h(extra_identity_label($field)) ?></th>
              <?php endforeach; ?>
              <?php foreach ($pivot['columns'] as $path): ?>
                <th><?= h($path) ?></th>
              <?php endforeach; ?>
              <th>จัดการ</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($pivot['rows'] as $row): ?>
              <tr>
                <?php foreach (array_keys($identityLabels) as $key): ?>
                  <td><?= h((string)($row[$key] ?? '')) ?></td>
                <?php endforeach; ?>
                <?php foreach ($pivot['extra_identity_fields'] as $field): ?>
                  <td><?= h((string)($row[$field] ?? '')) ?></td>
                <?php endforeach; ?>
                <?php foreach ($pivot['columns'] as $path): ?>
                  <?php $flagged = !empty($row['_needs_review'][$path]); ?>
                  <td<?= $flagged ? ' style="background:#fee2e2;" title="ต้องตรวจสอบ — ไม่แน่ใจว่าเป็นตัวเลข"' : '' ?>><?= h((string)($row[$path] ?? '')) ?></td>
                <?php endforeach; ?>
                <td style="white-space:nowrap;">
                  <a class="btn btn-secondary" style="padding:4px 10px; font-size:12px;" href="edit_submission.php?id=<?= (int)$row['id'] ?>">แก้ไข</a>
                  <form method="post" onsubmit="return confirm('ยืนยันลบรายการนี้? การกระทำนี้ย้อนกลับไม่ได้');" style="margin:0; display:inline;">
                    <input type="hidden" name="action" value="delete_submission">
                    <input type="hidden" name="submission_id" value="<?= (int)$row['id'] ?>">
                    <button type="submit" class="btn" style="background:#dc2626; padding:4px 10px; font-size:12px;">ลบ</button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
          <tfoot>
            <tr style="font-weight:700; background:#f3f4f6;">
              <td>รวมทั้งหมด</td>
              <?php for ($i = 1; $i < count($identityLabels); $i++): ?>
                <td></td>
              <?php endfor; ?>
              <?php foreach ($pivot['extra_identity_fields'] as $field): ?>
                <td></td>
              <?php endforeach; ?>
              <?php foreach ($pivot['columns'] as $path): ?>
                <td><?= h((string)($pivot['totals'][$path] ?? '')) ?></td>
              <?php endforeach; ?>
              <td></td>
            </tr>
          </tfoot>
        </table>
      </div>
    <?php endif; ?>
  </div>
</div>
</body>
</html>
