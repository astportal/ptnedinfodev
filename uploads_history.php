<?php
require_once __DIR__ . '/bootstrap.php';
Auth::requireLogin();

$db = Db::conn();
$forms = form_registry();
$formKey = $_GET['form'] ?? '';
$sheetName = $_GET['sheet'] ?? '';

$flash = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_upload') {
    $uploadId = (int)($_POST['upload_id'] ?? 0);
    $stmt = $db->prepare('DELETE FROM uploads WHERE id = :id');
    $stmt->execute(['id' => $uploadId]);
    $flash = 'ลบชีทนี้ และข้อมูลที่มากับชีทนี้ทั้งหมดเรียบร้อยแล้ว';
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_upload_batch') {
    // All sheets parsed from the same uploaded file share one stored_filename
    // (assigned once in upload.php and reused across every importSheet() call
    // for that file), so this pair uniquely identifies "one file upload event".
    $batchFormKey = $_POST['form_key'] ?? '';
    $storedFilename = $_POST['stored_filename'] ?? '';
    $stmt = $db->prepare('DELETE FROM uploads WHERE form_key = :fk AND stored_filename = :sf');
    $stmt->execute(['fk' => $batchFormKey, 'sf' => $storedFilename]);
    $flash = 'ลบไฟล์ทั้งหมด (ทุกชีท) และข้อมูลที่มากับไฟล์นี้เรียบร้อยแล้ว';
}

$sql = 'SELECT u.*, us.display_name AS uploaded_by_name
        FROM uploads u
        LEFT JOIN users us ON us.id = u.uploaded_by
        WHERE 1=1';
$params = [];
if ($formKey !== '' && isset($forms[$formKey])) {
    $sql .= ' AND u.form_key = :fk';
    $params['fk'] = $formKey;
}
if ($sheetName !== '') {
    $sql .= ' AND u.sheet_name = :sn';
    $params['sn'] = $sheetName;
}
$sql .= ' ORDER BY u.uploaded_at DESC';
$stmt = $db->prepare($sql);
$stmt->execute($params);
$uploads = $stmt->fetchAll();

// Group sheets that came from the same file upload event together
// (see delete_upload_batch handler above for why stored_filename is the key).
$batches = [];
foreach ($uploads as $u) {
    $gkey = $u['form_key'] . '|' . $u['stored_filename'];
    if (!isset($batches[$gkey])) {
        $batches[$gkey] = [
            'form_key'          => $u['form_key'],
            'stored_filename'   => $u['stored_filename'],
            'original_filename' => $u['original_filename'],
            'max_uploaded_at'   => $u['uploaded_at'],
            'rows'              => [],
        ];
    }
    $batches[$gkey]['rows'][] = $u;
    if ($u['uploaded_at'] > $batches[$gkey]['max_uploaded_at']) {
        $batches[$gkey]['max_uploaded_at'] = $u['uploaded_at'];
    }
}
usort($batches, fn($a, $b) => strcmp($b['max_uploaded_at'], $a['max_uploaded_at']));

function form_label_for(array $forms, string $key): string
{
    return $forms[$key]['form_label'] ?? $key;
}
?>
<!doctype html>
<html lang="th">
<head>
<meta charset="utf-8">
<title>ประวัติการอัปโหลด — ptnedinfo</title>
<link rel="stylesheet" href="public/assets/style.css">
</head>
<body>
<div class="topbar">
  <a href="index.php">ptnedinfo — ระบบรวบรวมข้อมูล</a>
  <nav>
    <a href="index.php">แดชบอร์ด</a>
    <a href="review.php">รายการที่ต้องตรวจสอบ</a>
    <span class="muted"><?= h(Auth::displayName()) ?></span>
    &nbsp;&nbsp;<a href="logout.php">ออกจากระบบ</a>
  </nav>
</div>
<div class="container" style="max-width: 1100px;">
  <div class="card">
    <h1>ประวัติการอัปโหลด</h1>
    <p class="muted">ลบไฟล์ที่อัปโหลดผิด หรือไฟล์เก่าที่ถูกแทนที่แล้วออกจากระบบได้ที่นี่ — หนึ่งไฟล์ .xlsx ที่มีหลายชีท
      จะแสดงเป็นหลายแถวที่จัดกลุ่มไว้ด้วยกัน "ลบชีทนี้" จะลบเฉพาะชีทที่กด ส่วน "ลบทั้งไฟล์" จะลบทุกชีทของไฟล์นั้นพร้อมข้อมูลทั้งหมดในครั้งเดียว</p>

    <?php if ($flash): ?><div class="alert alert-ok"><?= h($flash) ?></div><?php endif; ?>

    <form method="get" style="display:flex; gap:12px; align-items:flex-end; margin-bottom:16px;">
      <div class="field" style="margin-bottom:0; flex:1;">
        <label>กรองตามฟอร์ม</label>
        <select name="form" onchange="this.form.submit()">
          <option value="">— ทั้งหมด —</option>
          <?php foreach ($forms as $key => $def): ?>
            <option value="<?= h($key) ?>" <?= $key === $formKey ? 'selected' : '' ?>><?= h($def['form_label']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </form>
  </div>

  <div class="card">
    <?php if (!$uploads): ?>
      <p class="muted">ยังไม่มีประวัติการอัปโหลด</p>
    <?php else: ?>
      <div class="table-scroll">
        <table>
          <thead>
            <tr>
              <th>วันที่อัปโหลด</th>
              <th>ฟอร์ม</th>
              <th>ชีท</th>
              <th>ชื่อไฟล์เดิม</th>
              <th>จำนวนแถว</th>
              <th>สถานะ</th>
              <th>อัปโหลดโดย</th>
              <th>จัดการ</th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($batches as $batch): ?>
            <?php $rowCount = count($batch['rows']); ?>
            <?php foreach ($batch['rows'] as $i => $u): ?>
              <?php $isFirstOfGroup = $i === 0; ?>
              <?php $groupBorder = $rowCount > 1 ? ' style="border-top: 3px solid #cbd5e1;"' : ''; ?>
              <tr>
                <td<?= $isFirstOfGroup ? $groupBorder : '' ?>><?= h($u['uploaded_at']) ?></td>
                <td<?= $isFirstOfGroup ? $groupBorder : '' ?>><?= h(form_label_for($forms, $u['form_key'])) ?></td>
                <td<?= $isFirstOfGroup ? $groupBorder : '' ?>><?= h($u['sheet_name']) ?></td>
                <td<?= $isFirstOfGroup ? $groupBorder : '' ?>><?= h($u['original_filename']) ?></td>
                <td<?= $isFirstOfGroup ? $groupBorder : '' ?>><?= h((string)$u['row_count']) ?></td>
                <td<?= $isFirstOfGroup ? $groupBorder : '' ?>>
                  <?php if ($u['status'] === 'parsed'): ?>
                    <span class="badge badge-ok">สำเร็จ</span>
                  <?php else: ?>
                    <span class="badge badge-err">ผิดพลาด</span>
                  <?php endif; ?>
                </td>
                <td<?= $isFirstOfGroup ? $groupBorder : '' ?>><?= h($u['uploaded_by_name'] ?? '—') ?></td>
                <td style="white-space:nowrap;"<?= $isFirstOfGroup ? $groupBorder : '' ?>>
                  <form method="post" onsubmit="return confirm('ยืนยันลบชีทนี้และข้อมูลที่มากับชีทนี้ทั้งหมด? การกระทำนี้ย้อนกลับไม่ได้');" style="margin:0; display:inline;">
                    <input type="hidden" name="action" value="delete_upload">
                    <input type="hidden" name="upload_id" value="<?= (int)$u['id'] ?>">
                    <button type="submit" class="btn btn-secondary" style="padding:6px 12px; font-size:13px;">ลบชีทนี้</button>
                  </form>
                  <?php if ($isFirstOfGroup && $rowCount > 1): ?>
                    <form method="post" onsubmit="return confirm('ยืนยันลบไฟล์นี้ทั้งหมด (<?= $rowCount ?> ชีท) และข้อมูลที่มากับไฟล์นี้ทั้งหมด? การกระทำนี้ย้อนกลับไม่ได้');" style="margin:0; display:inline;">
                      <input type="hidden" name="action" value="delete_upload_batch">
                      <input type="hidden" name="form_key" value="<?= h($batch['form_key']) ?>">
                      <input type="hidden" name="stored_filename" value="<?= h($batch['stored_filename']) ?>">
                      <button type="submit" class="btn" style="background:#dc2626; padding:6px 12px; font-size:13px;">ลบทั้งไฟล์ (<?= $rowCount ?> ชีท)</button>
                    </form>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</div>
</body>
</html>
