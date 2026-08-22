<?php
require_once __DIR__ . '/bootstrap.php';
Auth::requireLogin();

$db = Db::conn();
$forms = form_registry();

$flash = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'resolve_value') {
    $valueId = (int)($_POST['value_id'] ?? 0);
    $newVal = trim($_POST['new_value'] ?? '');
    if ($newVal === '') {
        $flash = 'กรุณากรอกค่าที่ถูกต้องก่อนบันทึก';
    } else {
        $stmt = $db->prepare('UPDATE submission_values SET value = :v, needs_review = 0 WHERE id = :id');
        $stmt->execute(['v' => $newVal, 'id' => $valueId]);
        $flash = 'บันทึกค่าที่ตรวจสอบแล้วเรียบร้อย';
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'resolve_school_code') {
    $submissionId = (int)($_POST['submission_id'] ?? 0);
    $newCode = trim($_POST['new_school_code'] ?? '');
    if ($newCode === '') {
        $flash = 'กรุณากรอกรหัสสถานศึกษาที่ถูกต้องก่อนบันทึก';
    } else {
        $stmt = $db->prepare('UPDATE submissions SET school_code = :c, school_code_issue = NULL WHERE id = :id');
        $stmt->execute(['c' => $newCode, 'id' => $submissionId]);
        $flash = 'บันทึกรหัสสถานศึกษาที่ตรวจสอบแล้วเรียบร้อย';
    }
}

$sql = "SELECT sv.id AS value_id, sv.column_path, sv.value, s.form_key, s.sheet_name,
               s.agency_name, s.school_name, s.school_code, u.original_filename, u.uploaded_at
        FROM submission_values sv
        JOIN submissions s ON s.id = sv.submission_id
        JOIN uploads u ON u.id = s.upload_id
        WHERE sv.needs_review = 1
        ORDER BY u.uploaded_at DESC, s.id, sv.col_index";
$items = $db->query($sql)->fetchAll();

// school_code_issue only exists after migrations/003_school_code_check.sql is applied — degrade
// gracefully (empty list) rather than erroring out the whole page if it hasn't been run yet.
try {
    $codeSql = "SELECT s.id AS submission_id, s.form_key, s.sheet_name, s.agency_name, s.school_name,
                       s.school_code, s.school_code_issue, u.original_filename, u.uploaded_at
                FROM submissions s
                JOIN uploads u ON u.id = s.upload_id
                WHERE s.school_code_issue IS NOT NULL
                ORDER BY u.uploaded_at DESC, s.id";
    $codeItems = $db->query($codeSql)->fetchAll();
} catch (Throwable $e) {
    $codeItems = [];
}

function form_label_for(array $forms, string $key): string
{
    return $forms[$key]['form_label'] ?? $key;
}
?>
<!doctype html>
<html lang="th">
<head>
<meta charset="utf-8">
<title>รายการที่ต้องตรวจสอบ — ptnedinfo</title>
<link rel="stylesheet" href="public/assets/style.css">
</head>
<body>
<div class="topbar">
  <a href="index.php">ptnedinfo — ระบบรวบรวมข้อมูล</a>
  <nav>
    <a href="index.php">แดชบอร์ด</a>
    <a href="uploads_history.php">ประวัติการอัปโหลด</a>
    <a href="schools_master.php">ทำเนียบโรงเรียน</a>
    <span class="muted"><?= h(Auth::displayName()) ?></span>
    &nbsp;&nbsp;<a href="logout.php">ออกจากระบบ</a>
  </nav>
</div>
<div class="container" style="max-width: 1100px;">
  <div class="card">
    <h1>รายการที่ต้องตรวจสอบ</h1>
    <?php if ($flash): ?><div class="alert alert-ok"><?= h($flash) ?></div><?php endif; ?>
  </div>

  <div class="card">
    <h2>ค่าตัวเลขที่ไม่แน่ใจ</h2>
    <p class="muted">ค่าเหล่านี้ในไฟล์ที่อัปโหลดไม่ใช่ตัวเลข ไม่ใช่เครื่องหมาย "-" (=0) หรือ "/" (=1)
      ระบบจึงไม่กล้าอนุมานเอง กรุณาตรวจสอบไฟล์ต้นฉบับแล้วกรอกค่าที่ถูกต้อง</p>

    <?php if (!$items): ?>
      <p class="muted">ไม่มีรายการที่ต้องตรวจสอบในขณะนี้ 🎉</p>
    <?php else: ?>
      <div class="table-scroll">
        <table>
          <thead>
            <tr>
              <th>ฟอร์ม / ชีท</th>
              <th>หน่วยงาน/โรงเรียน</th>
              <th>คอลัมน์</th>
              <th>ค่าที่กรอกมา</th>
              <th>ไฟล์ต้นฉบับ</th>
              <th>แก้ไขเป็น</th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($items as $it): ?>
            <tr>
              <td>
                <?= h(form_label_for($forms, $it['form_key'])) ?><br>
                <span class="muted"><?= h($it['sheet_name']) ?></span>
              </td>
              <td>
                <?= h($it['agency_name'] ?: '—') ?><br>
                <span class="muted"><?= h($it['school_name'] ?: '') ?> <?= $it['school_code'] ? '(' . h($it['school_code']) . ')' : '' ?></span>
              </td>
              <td><?= h($it['column_path']) ?></td>
              <td><span class="badge badge-err"><?= h($it['value']) ?></span></td>
              <td class="muted"><?= h($it['original_filename']) ?><br><?= h($it['uploaded_at']) ?></td>
              <td>
                <form method="post" style="display:flex; gap:6px;">
                  <input type="hidden" name="action" value="resolve_value">
                  <input type="hidden" name="value_id" value="<?= (int)$it['value_id'] ?>">
                  <input type="text" name="new_value" placeholder="เช่น 0" style="width:90px;" required>
                  <button type="submit" class="btn" style="padding:6px 12px; font-size:13px;">บันทึก</button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>

  <div class="card">
    <h2>รหัสสถานศึกษาที่ต้องตรวจสอบ</h2>
    <p class="muted">แถวเหล่านี้ไม่ได้กรอกรหัสสถานศึกษามา หรือกรอกมาแต่ไม่ตรงกับ
      <a href="schools_master.php">ทำเนียบโรงเรียน</a> ที่อัปโหลดไว้ (อาจพิมพ์ผิด หรือใช้รหัสเก่า)
      กรุณาตรวจสอบไฟล์ต้นฉบับแล้วกรอกรหัสที่ถูกต้อง — ถ้าทำเนียบยังไม่เคยอัปโหลด ระบบจะไม่ตรวจสอบ
      ส่วนนี้ให้จนกว่าจะอัปโหลด</p>

    <?php if (!$codeItems): ?>
      <p class="muted">ไม่มีรายการที่ต้องตรวจสอบในขณะนี้ 🎉</p>
    <?php else: ?>
      <div class="table-scroll">
        <table>
          <thead>
            <tr>
              <th>ฟอร์ม / ชีท</th>
              <th>หน่วยงาน/โรงเรียน</th>
              <th>ปัญหา</th>
              <th>รหัสที่กรอกมา</th>
              <th>ไฟล์ต้นฉบับ</th>
              <th>แก้ไขเป็น</th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($codeItems as $it): ?>
            <tr>
              <td>
                <?= h(form_label_for($forms, $it['form_key'])) ?><br>
                <span class="muted"><?= h($it['sheet_name']) ?></span>
              </td>
              <td>
                <?= h($it['agency_name'] ?: '—') ?><br>
                <span class="muted"><?= h($it['school_name'] ?: '') ?></span>
              </td>
              <td>
                <?php if ($it['school_code_issue'] === 'missing'): ?>
                  <span class="badge badge-err">ไม่ได้กรอกมา</span>
                <?php else: ?>
                  <span class="badge badge-err">ไม่พบในทำเนียบ</span>
                <?php endif; ?>
              </td>
              <td><?= $it['school_code'] ? h($it['school_code']) : '—' ?></td>
              <td class="muted"><?= h($it['original_filename']) ?><br><?= h($it['uploaded_at']) ?></td>
              <td>
                <form method="post" style="display:flex; gap:6px;">
                  <input type="hidden" name="action" value="resolve_school_code">
                  <input type="hidden" name="submission_id" value="<?= (int)$it['submission_id'] ?>">
                  <input type="text" name="new_school_code" placeholder="รหัส 10 หลัก" style="width:110px;" required>
                  <button type="submit" class="btn" style="padding:6px 12px; font-size:13px;">บันทึก</button>
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
