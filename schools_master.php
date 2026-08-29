<?php
require_once __DIR__ . '/bootstrap.php';
Auth::requireLogin();

$db = Db::conn();

$flash = '';
$flashType = 'ok';

// จับคู่คอลัมน์ในไฟล์ทำเนียบด้วยชื่อ header แถวแรก (ไม่ใช่ตำแหน่งคอลัมน์) เพราะไฟล์แต่ละปีอาจ
// สลับลำดับคอลัมน์ได้ — ชื่อ header เหล่านี้อ้างอิงจากไฟล์ทำเนียบโรงเรียนตัวอย่างจริงที่ใช้อยู่
// YearEdu ใช้ระบุปีการศึกษาของไฟล์นี้โดยอัตโนมัติ ไม่ต้องให้แอดมินกรอกเอง
$columnMap = [
    'academic_year' => 'YearEdu',
    'school_code'   => 'SchoolCode',
    'school_name'   => 'SchoolName',
    'tambon'        => 'SubDistrictNameThai',
    'amphoe'        => 'DistrictNameThai',
    'province'      => 'ProvinceNameTH',
    'department'    => 'DepartmentNameThai',
    'area_name'     => 'AreaName',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? 'upload') === 'upload') {
    if (empty($_FILES['xlsx_file']) || $_FILES['xlsx_file']['error'] !== UPLOAD_ERR_OK) {
        $flash = 'กรุณาเลือกไฟล์ .xlsx ที่ต้องการอัปโหลด';
        $flashType = 'err';
    } elseif (strtolower(pathinfo($_FILES['xlsx_file']['name'], PATHINFO_EXTENSION)) !== 'xlsx') {
        $flash = 'รองรับเฉพาะไฟล์ .xlsx เท่านั้น';
        $flashType = 'err';
    } else {
        try {
            $reader = new XlsxReader($_FILES['xlsx_file']['tmp_name']);
            $sheetNames = $reader->sheetNames();
            if (!$sheetNames) {
                throw new RuntimeException('ไม่พบชีทในไฟล์นี้');
            }
            // ใช้ชีทแรกในไฟล์เสมอ เพราะชื่อชีทผูกกับปีการศึกษา (เช่น "2569_School") เปลี่ยนทุกปี
            $data = $reader->readGrid($sheetNames[0]);
            $grid = $data['grid'];
            $maxCol = $data['maxCol'];
            $maxRow = $data['maxRow'];

            $colIndex = [];
            for ($c = 1; $c <= $maxCol; $c++) {
                $headerText = trim((string)($grid[1][$c] ?? ''));
                foreach ($columnMap as $field => $headerName) {
                    if ($headerText === $headerName) {
                        $colIndex[$field] = $c;
                    }
                }
            }
            if (!isset($colIndex['school_code']) || !isset($colIndex['school_name'])) {
                throw new RuntimeException(
                    'ไม่พบคอลัมน์ "SchoolCode" หรือ "SchoolName" ในแถวหัวตาราง (แถวที่ 1) ของชีท "'
                    . $sheetNames[0] . '" — ตรวจสอบว่าเป็นไฟล์ทำเนียบโรงเรียนที่ถูกต้อง'
                );
            }

            // ปีการศึกษาของไฟล์นี้ = ค่า YearEdu ของแถวข้อมูลแถวแรกที่มีรหัสสถานศึกษา (ทุกแถวใน
            // ไฟล์ตัวอย่างจริงมีค่าเดียวกันหมด ไม่ต้องเช็กว่าทุกแถวตรงกัน)
            $academicYear = null;
            if (isset($colIndex['academic_year'])) {
                for ($r = 2; $r <= $maxRow; $r++) {
                    $code = trim((string)($grid[$r][$colIndex['school_code']] ?? ''));
                    if ($code === '') {
                        continue;
                    }
                    $yearVal = trim((string)($grid[$r][$colIndex['academic_year']] ?? ''));
                    if (preg_match('/^\d{4}$/', $yearVal)) {
                        $academicYear = (int)$yearVal;
                    }
                    break;
                }
            }
            if ($academicYear === null) {
                $manualYear = trim($_POST['manual_academic_year'] ?? '');
                if (preg_match('/^\d{4}$/', $manualYear)) {
                    $academicYear = (int)$manualYear;
                } else {
                    throw new RuntimeException(
                        'ตรวจไม่พบคอลัมน์ "YearEdu" หรืออ่านปีการศึกษาจากไฟล์ไม่ได้ '
                        . 'กรุณาระบุปีการศึกษาด้วยตนเองในช่องด้านล่างแล้วอัปโหลดใหม่'
                    );
                }
            }

            $rows = [];
            for ($r = 2; $r <= $maxRow; $r++) {
                $code = trim((string)($grid[$r][$colIndex['school_code']] ?? ''));
                if ($code === '') {
                    continue;
                }
                $rows[] = [
                    'school_code'   => $code,
                    'academic_year' => $academicYear,
                    'school_name'   => trim((string)($grid[$r][$colIndex['school_name']] ?? '')),
                    'tambon'        => isset($colIndex['tambon']) ? trim((string)($grid[$r][$colIndex['tambon']] ?? '')) : null,
                    'amphoe'        => isset($colIndex['amphoe']) ? trim((string)($grid[$r][$colIndex['amphoe']] ?? '')) : null,
                    'province'      => isset($colIndex['province']) ? trim((string)($grid[$r][$colIndex['province']] ?? '')) : null,
                    'department'    => isset($colIndex['department']) ? trim((string)($grid[$r][$colIndex['department']] ?? '')) : null,
                    'area_name'     => isset($colIndex['area_name']) ? trim((string)($grid[$r][$colIndex['area_name']] ?? '')) : null,
                ];
            }
            if (!$rows) {
                throw new RuntimeException('ไม่พบแถวข้อมูลโรงเรียนในไฟล์นี้เลย');
            }

            // อัปโหลดใหม่ = แทนที่เฉพาะทำเนียบของปีนั้น ๆ (ไม่กระทบปีอื่นที่เคยอัปโหลดไว้)
            $db->beginTransaction();
            $del = $db->prepare('DELETE FROM schools_master WHERE academic_year = :y');
            $del->execute(['y' => $academicYear]);
            $ins = $db->prepare(
                'INSERT INTO schools_master (school_code, academic_year, school_name, tambon, amphoe, province, department, area_name)
                 VALUES (:school_code, :academic_year, :school_name, :tambon, :amphoe, :province, :department, :area_name)'
            );
            foreach ($rows as $row) {
                $ins->execute($row);
            }
            $db->commit();

            $flash = "อัปโหลดทำเนียบโรงเรียนปีการศึกษา {$academicYear} สำเร็จ — แทนที่ข้อมูลเดิมของปีนี้ด้วย " . count($rows) . ' รายการ';
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            $flash = 'เกิดข้อผิดพลาด: ' . $e->getMessage();
            $flashType = 'err';
        }
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_year') {
    $deleteYear = (int)($_POST['academic_year'] ?? 0);
    $stmt = $db->prepare('DELETE FROM schools_master WHERE academic_year = :y');
    $stmt->execute(['y' => $deleteYear]);
    $flash = "ลบทำเนียบโรงเรียนปีการศึกษา {$deleteYear} ออกจากระบบแล้ว";
}

$yearRows = $db->query(
    'SELECT academic_year, COUNT(*) AS cnt, MAX(updated_at) AS last_updated
     FROM schools_master GROUP BY academic_year ORDER BY academic_year DESC'
)->fetchAll();
?>
<!doctype html>
<html lang="th">
<head>
<meta charset="utf-8">
<title>ทำเนียบโรงเรียน — ptnedinfo</title>
<link rel="stylesheet" href="public/assets/style.css">
</head>
<body>
<div class="topbar">
  <a href="index.php">ptnedinfo — ระบบรวบรวมข้อมูล</a>
  <nav>
    <a href="index.php">แดชบอร์ด</a>
    <a href="review.php">รายการที่ต้องตรวจสอบ</a>
    <a href="uploads_history.php">ประวัติการอัปโหลด</a>
    <a href="school_search.php">ค้นหารหัสสถานศึกษา</a>
    <a href="settings.php">ตั้งค่า</a>
    <span class="muted"><?= h(Auth::displayName()) ?></span>
    &nbsp;&nbsp;<a href="logout.php">ออกจากระบบ</a>
  </nav>
</div>
<div class="container">
  <div class="card">
    <h1>ทำเนียบโรงเรียน</h1>
    <p class="muted">ใช้เป็นข้อมูลอ้างอิงตรวจสอบรหัสสถานศึกษาที่กรอกมาในไฟล์ที่อัปโหลดของแต่ละฟอร์ม
      — เก็บแยกตามปีการศึกษา (อ่านปีจากคอลัมน์ "YearEdu" ในไฟล์อัตโนมัติ) อัปโหลดไฟล์ปีใดจะแทนที่
      เฉพาะทำเนียบของปีนั้น ไม่กระทบปีอื่น และไม่กระทบข้อมูลที่รวบรวมไว้แล้วในฟอร์มต่าง ๆ</p>

    <?php if ($flash): ?>
      <div class="alert <?= $flashType === 'ok' ? 'alert-ok' : 'alert-err' ?>"><?= h($flash) ?></div>
    <?php endif; ?>

    <form method="post" enctype="multipart/form-data">
      <input type="hidden" name="action" value="upload">
      <div class="field">
        <label>ไฟล์ .xlsx ทำเนียบโรงเรียน (คอลัมน์ header แถวแรกต้องมี SchoolCode, SchoolName, YearEdu)</label>
        <input type="file" name="xlsx_file" accept=".xlsx" required>
      </div>
      <div class="field">
        <label>ปีการศึกษา (กรอกเฉพาะกรณีระบบตรวจจับจากคอลัมน์ YearEdu ในไฟล์ไม่ได้)</label>
        <input type="text" name="manual_academic_year" placeholder="เช่น 2569" style="max-width:150px;">
      </div>
      <button class="btn" type="submit">อัปโหลดทำเนียบ</button>
    </form>
  </div>

  <div class="card">
    <h2>ทำเนียบที่มีอยู่ในระบบ</h2>
    <?php if (!$yearRows): ?>
      <p class="muted">ยังไม่เคยอัปโหลดทำเนียบโรงเรียนเลย</p>
    <?php else: ?>
      <div class="table-scroll">
        <table>
          <thead>
            <tr>
              <th>ปีการศึกษา</th>
              <th>จำนวนโรงเรียน</th>
              <th>อัปเดตล่าสุด</th>
              <th>จัดการ</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($yearRows as $yr): ?>
              <tr>
                <td><?= h((string)$yr['academic_year']) ?></td>
                <td><?= number_format((int)$yr['cnt']) ?></td>
                <td class="muted"><?= h((string)$yr['last_updated']) ?></td>
                <td>
                  <form method="post" onsubmit="return confirm('ยืนยันลบทำเนียบโรงเรียนปีการศึกษา <?= (int)$yr['academic_year'] ?> ทั้งหมด? การกระทำนี้ย้อนกลับไม่ได้');" style="margin:0;">
                    <input type="hidden" name="action" value="delete_year">
                    <input type="hidden" name="academic_year" value="<?= (int)$yr['academic_year'] ?>">
                    <button type="submit" class="btn" style="background:#dc2626; padding:6px 12px; font-size:13px;">ลบทำเนียบปีนี้</button>
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
