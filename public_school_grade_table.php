<?php
/**
 * ตารางผู้เรียนรายชั้น 1 แถวต่อสถานศึกษา — หน้าเปิดเผยต่อสาธารณะ ไม่ต้อง login เหมือนหน้าสาธารณะ
 * อื่น ๆ ตามคำขอผู้ใช้งาน 2026-08-29 ดูรายละเอียดที่มาของข้อมูล/เหตุผลออกแบบใน
 * public_school_grade_table_data.php และ ai_note.md
 */
require_once __DIR__ . '/public_school_grade_table_data.php';
?>
<!doctype html>
<html lang="th">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>ตารางผู้เรียนรายชั้น — ptnedinfo</title>
<link rel="stylesheet" href="public/assets/style.css">
<link rel="stylesheet" href="public/assets/public_report.css">
</head>
<body>
<div class="topbar">
  <a href="public_report.php">สถิติการศึกษาจังหวัดปัตตานี</a>
  <nav>
    <a href="public_report.php?<?= h($navQuery) ?>">ภาพรวม (กราฟ)</a>
    <a href="public_report_table.php?<?= h($navQuery) ?>">ตารางสรุปยอดรวม</a>
    <a href="public_school_search.php?<?= h($navQuery) ?>">ค้นหารหัสสถานศึกษา</a>
    <a href="public_school_grade_table.php?<?= h($navQuery) ?>" class="active">ผู้เรียนรายชั้น</a>
    <a href="login.php">เข้าสู่ระบบเจ้าหน้าที่</a>
  </nav>
</div>
<div class="container" style="max-width: 98vw;">
  <div class="report-main">
    <div class="card">
      <h1>ตารางผู้เรียนรายชั้น ประจำปีการศึกษา <?= h((string)$selectedYear) ?></h1>
      <p class="muted">จำนวนผู้เรียนแต่ละสถานศึกษา แยกตามระดับชั้น (รวมชาย+หญิงต่อระดับ) จากตารางที่ 4
        พร้อมยอดรวม "ผู้เรียน สกร." จากตารางที่ 11 และ "ผู้เรียนนอกระบบ" จากตารางที่ 15 (สถานศึกษา
        กศน./เอกชนนอกระบบ ไม่ได้กรอกตารางที่ 4 จึงแสดงยอดรวมเป็นคอลัมน์เดียวแทนการแยกตามระดับชั้น)</p>

      <form method="get" class="filter-bar">
        <div class="field">
          <label>ปีการศึกษา</label>
          <select name="year" onchange="this.form.submit()">
            <?php foreach ($availableYears as $y): ?>
              <option value="<?= h((string)$y) ?>" <?= $selectedYear === (int)$y ? 'selected' : '' ?>><?= h((string)$y) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </form>
      <p><a class="btn btn-secondary" href="public_school_grade_table_export.php?year=<?= h((string)$selectedYear) ?>" style="display:inline-block;">ดาวน์โหลด CSV</a></p>
    </div>

    <div class="card">
      <?php if (!$gradeTableRows): ?>
        <p class="muted">ยังไม่มีข้อมูลสำหรับปีการศึกษานี้</p>
      <?php else: ?>
        <p class="muted">พบ <?= count($gradeTableRows) ?> สถานศึกษา</p>
        <div class="report-table-scroll">
          <table class="stats-table">
            <thead>
              <tr>
                <th>รหัสสถานศึกษา</th>
                <th>ชื่อสถานศึกษา</th>
                <th>สังกัด/หน่วยงาน</th>
                <th>อำเภอ</th>
                <?php foreach ($gradeLabels as $label): ?>
                  <th class="num"><?= h($label) ?></th>
                <?php endforeach; ?>
                <th class="num">ผู้เรียน สกร.</th>
                <th class="num">ผู้เรียนนอกระบบ</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($gradeTableRows as $row): ?>
                <tr>
                  <td><code><?= h($row['school_code']) ?></code></td>
                  <td><?= h($row['school_name']) ?></td>
                  <td><?= h($row['agency_name']) ?></td>
                  <td><?= h($row['amphoe']) ?></td>
                  <?php foreach ($gradeLabels as $label): ?>
                    <td class="num"><?= fmt_num($row['grades'][$label]) ?></td>
                  <?php endforeach; ?>
                  <td class="num"><?= fmt_num($row['nfe_total']) ?></td>
                  <td class="num"><?= fmt_num($row['private_nonformal_total']) ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>
<footer style="text-align:center; padding:20px 16px; margin-top:12px;">
  <p class="muted">สำนักงานศึกษาธิการจังหวัดปัตตานี</p>
</footer>
</body>
</html>
