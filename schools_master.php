<?php
require_once __DIR__ . '/bootstrap.php';
Auth::requireLogin();

$db = Db::conn();

$flash = '';
$flashType = 'ok';

// จับคู่คอลัมน์ในไฟล์ทำเนียบด้วยชื่อ header แถวแรก (ไม่ใช่ตำแหน่งคอลัมน์) เพราะไฟล์แต่ละปีอาจ
// สลับลำดับคอลัมน์ได้ — ชื่อ header เหล่านี้อ้างอิงจากไฟล์ทำเนียบโรงเรียนตัวอย่างจริงที่ใช้อยู่
$columnMap = [
    'school_code' => 'SchoolCode',
    'school_name' => 'SchoolName',
    'tambon'      => 'SubDistrictNameThai',
    'amphoe'      => 'DistrictNameThai',
    'province'    => 'ProvinceNameTH',
    'department'  => 'DepartmentNameThai',
    'area_name'   => 'AreaName',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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

            $rows = [];
            for ($r = 2; $r <= $maxRow; $r++) {
                $code = trim((string)($grid[$r][$colIndex['school_code']] ?? ''));
                if ($code === '') {
                    continue;
                }
                $rows[] = [
                    'school_code' => $code,
                    'school_name' => trim((string)($grid[$r][$colIndex['school_name']] ?? '')),
                    'tambon'      => isset($colIndex['tambon']) ? trim((string)($grid[$r][$colIndex['tambon']] ?? '')) : null,
                    'amphoe'      => isset($colIndex['amphoe']) ? trim((string)($grid[$r][$colIndex['amphoe']] ?? '')) : null,
                    'province'    => isset($colIndex['province']) ? trim((string)($grid[$r][$colIndex['province']] ?? '')) : null,
                    'department'  => isset($colIndex['department']) ? trim((string)($grid[$r][$colIndex['department']] ?? '')) : null,
                    'area_name'   => isset($colIndex['area_name']) ? trim((string)($grid[$r][$colIndex['area_name']] ?? '')) : null,
                ];
            }
            if (!$rows) {
                throw new RuntimeException('ไม่พบแถวข้อมูลโรงเรียนในไฟล์นี้เลย');
            }

            // อัปโหลดครั้งใหม่ = แทนที่ทำเนียบทั้งชุด (ไม่ใช่ upsert ทีละแถว) เพราะทำเนียบนี้เป็น
            // snapshot ของปีการศึกษาปัจจุบัน โรงเรียนที่ปิด/ย้ายสังกัดจะได้หายไปจากรายการเก่าด้วย
            $db->beginTransaction();
            $db->exec('TRUNCATE TABLE schools_master');
            $ins = $db->prepare(
                'INSERT INTO schools_master (school_code, school_name, tambon, amphoe, province, department, area_name)
                 VALUES (:school_code, :school_name, :tambon, :amphoe, :province, :department, :area_name)'
            );
            foreach ($rows as $row) {
                $ins->execute($row);
            }
            $db->commit();

            $flash = 'อัปโหลดทำเนียบโรงเรียนสำเร็จ — แทนที่ข้อมูลเดิมด้วย ' . count($rows) . ' รายการ';
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            $flash = 'เกิดข้อผิดพลาด: ' . $e->getMessage();
            $flashType = 'err';
        }
    }
}

$currentCount = (int)$db->query('SELECT COUNT(*) FROM schools_master')->fetchColumn();
$lastUpdated = $db->query('SELECT MAX(updated_at) FROM schools_master')->fetchColumn();
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
    <span class="muted"><?= h(Auth::displayName()) ?></span>
    &nbsp;&nbsp;<a href="logout.php">ออกจากระบบ</a>
  </nav>
</div>
<div class="container">
  <div class="card">
    <h1>ทำเนียบโรงเรียน</h1>
    <p class="muted">ใช้เป็นข้อมูลอ้างอิงตรวจสอบรหัสสถานศึกษาที่กรอกมาในไฟล์ที่อัปโหลด — อัปโหลดไฟล์
      ทำเนียบใหม่ (เช่นทุกต้นปีการศึกษา) จะ<strong>แทนที่ข้อมูลเดิมทั้งหมด</strong>ในทำเนียบ ไม่กระทบ
      ข้อมูลที่รวบรวมไว้แล้วในฟอร์มต่าง ๆ</p>
    <p class="muted">
      ปัจจุบันมี <strong><?= number_format($currentCount) ?></strong> รายการในทำเนียบ
      <?= $lastUpdated ? '— อัปเดตล่าสุดเมื่อ ' . h($lastUpdated) : '— ยังไม่เคยอัปโหลด' ?>
    </p>

    <?php if ($flash): ?>
      <div class="alert <?= $flashType === 'ok' ? 'alert-ok' : 'alert-err' ?>"><?= h($flash) ?></div>
    <?php endif; ?>

    <form method="post" enctype="multipart/form-data">
      <div class="field">
        <label>ไฟล์ .xlsx ทำเนียบโรงเรียน (คอลัมน์ header แถวแรกต้องมี SchoolCode, SchoolName)</label>
        <input type="file" name="xlsx_file" accept=".xlsx" required>
      </div>
      <button class="btn" type="submit" onclick="return confirm('อัปโหลดไฟล์นี้จะแทนที่ทำเนียบโรงเรียนเดิมทั้งหมด ยืนยันหรือไม่?');">อัปโหลดและแทนที่ทำเนียบ</button>
    </form>
  </div>
</div>
</body>
</html>
