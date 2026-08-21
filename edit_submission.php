<?php
require_once __DIR__ . '/bootstrap.php';
Auth::requireLogin();

$db = Db::conn();
$forms = form_registry();

$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$stmt = $db->prepare('SELECT * FROM submissions WHERE id = :id');
$stmt->execute(['id' => $id]);
$submission = $stmt->fetch();
if (!$submission) {
    die('ไม่พบรายการที่ต้องการแก้ไข');
}

$formKey = $submission['form_key'];
$sheetName = $submission['sheet_name'];
$formDef = $forms[$formKey] ?? null;
$sheetDef = null;
if ($formDef) {
    foreach ($formDef['sheets'] as $sd) {
        if ($sd['sheet_name'] === $sheetName) {
            $sheetDef = $sd;
            break;
        }
    }
}
if (!$formDef || !$sheetDef) {
    die('ไม่พบการตั้งค่าฟอร์มสำหรับรายการนี้');
}
$valueType = $sheetDef['value_type'] ?? 'text';

$identityLabels = [
    'seq_no' => 'ลำดับที่', 'school_code' => 'รหัสสถานศึกษา', 'agency_name' => 'สังกัด/หน่วยงาน',
    'school_name' => 'ชื่อสถานศึกษา', 'amphoe' => 'อำเภอ', 'tambon' => 'ตำบล',
];

// รวมรายชื่อคอลัมน์ทั้งหมดที่เคยใช้ในฟอร์ม/ชีทนี้ (จากทุกหน่วยงานที่เคยอัปโหลด) เพื่อให้แก้ไข/เพิ่มค่าในช่องที่ยังว่างได้ด้วย
// key ด้วย col_index (ตัวเลข) แทน column_path ตรง ๆ เพราะชื่อคอลัมน์มีจุด/วงเล็บซึ่ง PHP
// จะแปลงเป็น underscore เวลาใช้เป็น key ของฟอร์ม ทำให้จับคู่ค่ากลับไม่ตรงได้
$colStmt = $db->prepare(
    'SELECT DISTINCT col_index, column_path FROM submission_values sv
     JOIN submissions s ON s.id = sv.submission_id
     WHERE s.form_key = :fk AND s.sheet_name = :sn
     ORDER BY col_index'
);
$colStmt->execute(['fk' => $formKey, 'sn' => $sheetName]);
$allColumns = $colStmt->fetchAll();
$pathByColIndex = [];
foreach ($allColumns as $col) {
    $pathByColIndex[(int)$col['col_index']] = $col['column_path'];
}

$flash = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $identity = [];
    foreach (array_keys($identityLabels) as $key) {
        $identity[$key] = trim((string)($_POST['identity'][$key] ?? ''));
    }

    $upd = $db->prepare(
        'UPDATE submissions SET seq_no = :seq_no, school_code = :school_code, agency_name = :agency_name,
            school_name = :school_name, amphoe = :amphoe, tambon = :tambon WHERE id = :id'
    );
    $upd->execute([
        'seq_no'      => $identity['seq_no'] !== '' ? $identity['seq_no'] : null,
        'school_code' => $identity['school_code'] !== '' ? $identity['school_code'] : null,
        'agency_name' => $identity['agency_name'] !== '' ? $identity['agency_name'] : null,
        'school_name' => $identity['school_name'] !== '' ? $identity['school_name'] : null,
        'amphoe'      => $identity['amphoe'] !== '' ? $identity['amphoe'] : null,
        'tambon'      => $identity['tambon'] !== '' ? $identity['tambon'] : null,
        'id'          => $id,
    ]);

    $selectExisting = $db->prepare('SELECT id FROM submission_values WHERE submission_id = :sid AND col_index = :ci');
    $updateVal = $db->prepare('UPDATE submission_values SET value = :val, needs_review = :nr WHERE id = :id');
    $insertVal = $db->prepare(
        'INSERT INTO submission_values (submission_id, col_index, column_path, value, needs_review) VALUES (:sid, :ci, :cp, :val, :nr)'
    );
    $deleteVal = $db->prepare('DELETE FROM submission_values WHERE id = :id');

    foreach ((array)($_POST['values'] ?? []) as $colIndex => $rawVal) {
        $colIndex = (int)$colIndex;
        $path = $pathByColIndex[$colIndex] ?? null;
        if ($path === null) {
            continue;
        }
        $raw = trim((string)$rawVal);
        $selectExisting->execute(['sid' => $id, 'ci' => $colIndex]);
        $existingId = $selectExisting->fetchColumn();

        if ($raw === '') {
            if ($existingId) {
                $deleteVal->execute(['id' => $existingId]);
            }
            continue;
        }

        if ($valueType === 'numeric') {
            [$val, $needsReview] = Importer::classifyValue($raw);
        } else {
            $val = $raw;
            $needsReview = false;
        }

        if ($existingId) {
            $updateVal->execute(['val' => $val, 'nr' => $needsReview ? 1 : 0, 'id' => $existingId]);
        } else {
            $insertVal->execute([
                'sid' => $id, 'ci' => $colIndex, 'cp' => $path,
                'val' => $val, 'nr' => $needsReview ? 1 : 0,
            ]);
        }
    }

    $flash = 'บันทึกการแก้ไขเรียบร้อยแล้ว';
    $stmt->execute(['id' => $id]);
    $submission = $stmt->fetch();
}

$valStmt = $db->prepare('SELECT col_index, value, needs_review FROM submission_values WHERE submission_id = :id');
$valStmt->execute(['id' => $id]);
$currentValues = [];
foreach ($valStmt->fetchAll() as $v) {
    $currentValues[(int)$v['col_index']] = $v;
}
?>
<!doctype html>
<html lang="th">
<head>
<meta charset="utf-8">
<title>แก้ไขข้อมูล — ptnedinfo</title>
<link rel="stylesheet" href="public/assets/style.css">
</head>
<body>
<div class="topbar">
  <a href="index.php">ptnedinfo — ระบบรวบรวมข้อมูล</a>
  <nav>
    <a href="index.php">แดชบอร์ด</a>
    <a href="view.php?form=<?= urlencode($formKey) ?>&sheet=<?= urlencode($sheetName) ?>">กลับไปดูข้อมูล</a>
    <span class="muted"><?= h(Auth::displayName()) ?></span>
    &nbsp;&nbsp;<a href="logout.php">ออกจากระบบ</a>
  </nav>
</div>
<div class="container">
  <div class="card">
    <h1>แก้ไขข้อมูล</h1>
    <p class="muted"><?= h($formDef['form_label']) ?> — <?= h($sheetName) ?></p>
    <?php if ($flash): ?><div class="alert alert-ok"><?= h($flash) ?></div><?php endif; ?>

    <form method="post">
      <input type="hidden" name="id" value="<?= (int)$id ?>">

      <h2>ข้อมูลระบุตัวตน</h2>
      <?php foreach ($identityLabels as $key => $label): ?>
        <div class="field">
          <label><?= h($label) ?></label>
          <input type="text" name="identity[<?= h($key) ?>]" value="<?= h((string)($submission[$key] ?? '')) ?>">
        </div>
      <?php endforeach; ?>

      <h2 style="margin-top:24px;">ข้อมูลรายคอลัมน์</h2>
      <p class="muted">ปล่อยว่างเพื่อลบค่าของคอลัมน์นั้นออก</p>
      <?php foreach ($allColumns as $col): ?>
        <?php
          $colIndex = (int)$col['col_index'];
          $cur = $currentValues[$colIndex] ?? null;
          $flagged = $cur && $cur['needs_review'];
        ?>
        <div class="field">
          <label><?= h($col['column_path']) ?><?= $flagged ? ' <span class="badge badge-err">ต้องตรวจสอบ</span>' : '' ?></label>
          <input type="text" name="values[<?= $colIndex ?>]" value="<?= h((string)($cur['value'] ?? '')) ?>"
            <?= $flagged ? 'style="border-color:#dc2626;"' : '' ?>>
        </div>
      <?php endforeach; ?>

      <button class="btn" type="submit" style="margin-top:12px;">บันทึกการแก้ไข</button>
      <a class="btn btn-secondary" href="view.php?form=<?= urlencode($formKey) ?>&sheet=<?= urlencode($sheetName) ?>">ยกเลิก</a>
    </form>
  </div>
</div>
</body>
</html>
