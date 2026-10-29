<?php
require_once __DIR__ . '/bootstrap.php';
Auth::requireLogin();

$db = Db::conn();
$forms = form_registry();

$currentYear = Settings::currentAcademicYear($db);

// academic_year only exists after migrations/004_academic_year.sql is applied — degrade to
// "no year filter available yet" rather than breaking the whole dashboard.
try {
    $availableYears = $db->query('SELECT DISTINCT academic_year FROM submissions ORDER BY academic_year DESC')
        ->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array($currentYear, $availableYears, true)) {
        array_unshift($availableYears, $currentYear);
    }
    $yearFilterAvailable = true;
} catch (Throwable $e) {
    $availableYears = [$currentYear];
    $yearFilterAvailable = false;
}

$selectedYear = $_GET['year'] ?? (string)$currentYear;
$showAllYears = $selectedYear === 'all';
$selectedYearInt = $showAllYears ? null : (int)$selectedYear;

$countSql = 'SELECT COUNT(*) FROM submissions WHERE form_key = :fk AND sheet_name = :sn';
$lastSql  = 'SELECT uploaded_at FROM uploads WHERE form_key = :fk AND sheet_name = :sn';
if ($yearFilterAvailable && !$showAllYears) {
    $countSql .= ' AND academic_year = :yr';
    $lastSql  .= ' AND academic_year = :yr';
}
$lastSql .= ' ORDER BY uploaded_at DESC LIMIT 1';
$countStmt = $db->prepare($countSql);
$lastStmt  = $db->prepare($lastSql);

$needsReviewTotal = (int)$db->query('SELECT COUNT(*) FROM submission_values WHERE needs_review = 1')->fetchColumn();
try {
    // school_code_issue column only exists after migrations/003_school_code_check.sql is applied.
    $needsReviewTotal += (int)$db->query("SELECT COUNT(*) FROM submissions WHERE school_code_issue IS NOT NULL")->fetchColumn();
} catch (Throwable $e) {
    // migration not applied yet — skip silently, needs_review still works for numeric values
}
// เมนูซ้าย (เพิ่มเมื่อ 2026-10-29) — override label ของ item "review" เองที่นี่เพื่อแปะเลขเตือนสด
// ("(12)" เช่นเดิม) ไม่ทำใน admin_nav_items() เองเพราะผู้ใช้งานยืนยันให้โชว์แค่หน้านี้หน้าเดียว ไม่ใช่
// ทุกหน้าแอดมิน — ถ้าไปทำใน admin_nav_items() จะรัน query นี้ซ้ำทุกหน้าโดยไม่ได้ขอ
$navItems = admin_nav_items();
foreach ($navItems as &$navItem) {
    if ($navItem['key'] === 'review' && $needsReviewTotal > 0) {
        $navItem['label'] .= " ({$needsReviewTotal})";
    }
}
unset($navItem);
render_app_shell_start('แดชบอร์ด', 'ptnedinfo — ระบบรวบรวมข้อมูล', $navItems, 'dashboard');
?>
<div class="container">
  <h1>รายการแบบฟอร์ม</h1>
  <p class="muted">เลือกฟอร์มเพื่ออัปโหลดไฟล์ที่ได้รับจากหน่วยงาน หรือดู/ส่งออกข้อมูลที่รวบรวมแล้ว</p>

  <?php if ($yearFilterAvailable): ?>
    <form method="get" style="display:flex; gap:12px; align-items:flex-end; margin-bottom:16px;">
      <div class="field" style="margin-bottom:0; max-width:220px;">
        <label>ปีการศึกษา</label>
        <select name="year" onchange="this.form.submit()">
          <?php foreach ($availableYears as $y): ?>
            <option value="<?= h((string)$y) ?>" <?= !$showAllYears && (int)$selectedYear === (int)$y ? 'selected' : '' ?>>
              <?= h((string)$y) ?><?= (int)$y === $currentYear ? ' (ปัจจุบัน)' : '' ?>
            </option>
          <?php endforeach; ?>
          <option value="all" <?= $showAllYears ? 'selected' : '' ?>>— ทุกปีรวมกัน —</option>
        </select>
      </div>
    </form>
  <?php endif; ?>

  <?php if ($needsReviewTotal > 0): ?>
    <div class="alert alert-err">
      พบ <?= $needsReviewTotal ?> ค่าที่ระบบไม่แน่ใจว่าเป็นตัวเลขหรือไม่ ต้องการให้ตรวจสอบ —
      <a href="review.php">ไปตรวจสอบตอนนี้</a>
    </div>
  <?php endif; ?>

  <div class="card">
    <div class="table-scroll">
      <table style="table-layout: fixed;">
        <colgroup>
          <col style="width: 26%;">
          <col style="width: 24%;">
          <col style="width: 10%;">
          <col style="width: 14%;">
          <col style="width: 26%;">
        </colgroup>
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
          <?php
            // Several sheetDefs can share one db_sheet_name (a merged form, e.g. 14ก+14ข from
            // two different original files stored together) — collapse those to one dashboard row.
            $displaySheetNames = [];
            foreach ($formDef['sheets'] as $sheetDef) {
                $storageName = $sheetDef['db_sheet_name'] ?? $sheetDef['sheet_name'];
                $displaySheetNames[$storageName] = $storageName;
            }
            $displaySheetNames = array_values($displaySheetNames);
            $sheetCount = count($displaySheetNames);
          ?>
          <?php foreach ($displaySheetNames as $i => $sheetName): ?>
            <?php
              $params = ['fk' => $formKey, 'sn' => $sheetName];
              if ($yearFilterAvailable && !$showAllYears) {
                  $params['yr'] = $selectedYearInt;
              }
              $countStmt->execute($params);
              $count = (int)$countStmt->fetchColumn();
              $lastStmt->execute($params);
              $lastUpload = $lastStmt->fetchColumn();
              $isFirstOfGroup = $i === 0;
              $groupBorder = $isFirstOfGroup ? ' style="border-top: 3px solid #cbd5e1;"' : '';
            ?>
            <tr>
              <?php if ($isFirstOfGroup): ?>
                <td rowspan="<?= $sheetCount ?>" style="border-top: 3px solid #cbd5e1; vertical-align: top; font-weight: 600; white-space: normal; word-break: break-word;">
                  <?= h($formDef['form_label']) ?>
                  <?php if ($sheetCount > 1): ?>
                    <div style="margin-top:8px; font-weight:400;">
                      <a class="btn btn-secondary" style="padding:6px 12px; font-size:13px; display:inline-block; margin-bottom:4px;" href="export.php?form=<?= urlencode($formKey) ?>&sheet=__all__&year=<?= urlencode((string)$selectedYear) ?>">ดาวน์โหลดรวมทุกชีท (CSV)</a>
                      <a class="btn btn-secondary" style="padding:6px 12px; font-size:13px; display:inline-block; margin-bottom:4px;" href="export_tidy.php?form=<?= urlencode($formKey) ?>&sheet=__all__&year=<?= urlencode((string)$selectedYear) ?>">รวมทุกชีทสำหรับ Pivot Table</a>
                    </div>
                  <?php endif; ?>
                </td>
              <?php endif; ?>
              <td style="white-space: normal; word-break: break-word;<?= $isFirstOfGroup ? ' border-top: 3px solid #cbd5e1;' : '' ?>"><?= h($sheetName) ?></td>
              <td<?= $groupBorder ?>><?= $count ?></td>
              <td class="muted" style="white-space: normal;<?= $isFirstOfGroup ? ' border-top: 3px solid #cbd5e1;' : '' ?>"><?= $lastUpload ? h($lastUpload) : '— ยังไม่มี' ?></td>
              <td style="white-space: normal;<?= $isFirstOfGroup ? ' border-top: 3px solid #cbd5e1;' : '' ?>">
                <a class="btn" style="padding:6px 12px; font-size:13px; display:inline-block; margin-bottom:4px;" href="upload.php?form=<?= urlencode($formKey) ?>">อัปโหลดไฟล์</a>
                <a class="btn btn-secondary" style="padding:6px 12px; font-size:13px; display:inline-block; margin-bottom:4px;" href="view.php?form=<?= urlencode($formKey) ?>&sheet=<?= urlencode($sheetName) ?>&year=<?= urlencode((string)$selectedYear) ?>">ดูข้อมูล</a>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<?php render_app_shell_end(); ?>
