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
  <a href="public_report.php">ข้อมูลด้านการศึกษาจังหวัดปัตตานี</a>
  <nav>
    <a href="public_report.php?<?= h($navQuery) ?>">ภาพรวม</a>
    <a href="public_report_table.php?<?= h($navQuery) ?>">ตารางสรุปยอดรวม</a>
    <a href="public_school_grade_table.php?<?= h($navQuery) ?>" class="active">ผู้เรียนรายชั้น</a>
    <a href="public_teacher_grade_table.php?<?= h($navQuery) ?>">ครูผู้สอน</a>
    <a href="public_school_search.php?<?= h($navQuery) ?>">ค้นหารหัสสถานศึกษา</a>
    <a href="login.php">เข้าสู่ระบบเจ้าหน้าที่</a>
  </nav>
</div>
<div class="container" style="max-width: 98vw;">
  <div class="report-main">
    <div class="card">
      <h1>ตารางผู้เรียนรายชั้น ประจำปีการศึกษา <?= h((string)$selectedYear) ?></h1>

      <form method="get" class="filter-bar">
        <div class="field">
          <label>ปีการศึกษา</label>
          <select name="year" onchange="this.form.submit()">
            <?php foreach ($availableYears as $y): ?>
              <option value="<?= h((string)$y) ?>" <?= $selectedYear === (int)$y ? 'selected' : '' ?>><?= h((string)$y) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field">
          <label>ค้นหาชื่อสถานศึกษา</label>
          <input type="text" name="q" value="<?= h($searchName) ?>" placeholder="เช่น บ้านตลาด">
        </div>
        <div class="field">
          <label>สังกัด/หน่วยงาน (เลือกได้หลายรายการ — กด Ctrl หรือ Cmd ค้างไว้แล้วคลิกเพื่อเลือกเพิ่ม)</label>
          <select name="agency[]" multiple size="6">
            <?php foreach ($agencyOptions as $opt): ?>
              <option value="<?= h($opt) ?>" <?= in_array($opt, $filterAgency, true) ? 'selected' : '' ?>><?= h($opt) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field">
          <label>อำเภอ</label>
          <select name="amphoe">
            <option value="">— ทั้งหมด —</option>
            <?php foreach ($amphoeOptions as $opt): ?>
              <option value="<?= h($opt) ?>" <?= $filterAmphoe === $opt ? 'selected' : '' ?>><?= h($opt) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <button class="btn" type="submit">ค้นหา</button>
      </form>
      <p><a class="btn btn-secondary" href="public_school_grade_table_export.php?<?= h(http_build_query(['year' => $selectedYear, 'q' => $searchName, 'agency' => $filterAgency, 'amphoe' => $filterAmphoe])) ?>" style="display:inline-block;">ดาวน์โหลด CSV (ตามผลค้นหาปัจจุบัน)</a></p>
    </div>

    <div class="card">
      <?php if (!$gradeTableRows): ?>
        <p class="muted">ไม่พบสถานศึกษาที่ตรงกับเงื่อนไขค้นหานี้</p>
      <?php else: ?>
        <p class="muted">พบ <?= count($gradeTableRows) ?> สถานศึกษา</p>
        <div class="report-table-scroll">
          <table class="stats-table two-row-header">
            <thead>
              <tr>
                <th rowspan="2">รหัสสถานศึกษา</th>
                <th rowspan="2">ชื่อสถานศึกษา</th>
                <th rowspan="2">สังกัด/หน่วยงาน</th>
                <th rowspan="2">อำเภอ</th>
                <th class="num" rowspan="2">รวม</th>
                <th class="num" rowspan="2">รวมชาย</th>
                <th class="num" rowspan="2">รวมหญิง</th>
                <?php foreach ($gradeLabels as $label): ?>
                  <th class="num" colspan="2" style="text-align:center;"><?= h($label) ?></th>
                <?php endforeach; ?>
                <th class="num" colspan="2" style="text-align:center;">เด็ก ศพด.</th>
                <?php foreach ($nfeActivityLabelsShown as $label): ?>
                  <th class="num" colspan="2" style="text-align:center;"><?= h($label) ?></th>
                <?php endforeach; ?>
                <th class="num" colspan="2" style="text-align:center;">ผู้เรียนนอกระบบ</th>
                <th class="num" colspan="2" style="text-align:center;">พมจ.</th>
              </tr>
              <tr>
                <?php foreach ($gradeLabels as $label): ?>
                  <th class="num">ชาย</th>
                  <th class="num">หญิง</th>
                <?php endforeach; ?>
                <th class="num">ชาย</th>
                <th class="num">หญิง</th>
                <?php foreach ($nfeActivityLabelsShown as $label): ?>
                  <th class="num">ชาย</th>
                  <th class="num">หญิง</th>
                <?php endforeach; ?>
                <th class="num">ชาย</th>
                <th class="num">หญิง</th>
                <th class="num">ชาย</th>
                <th class="num">หญิง</th>
              </tr>
            </thead>
            <tbody>
              <tr class="row-total">
                <td colspan="4">รวม (ตามผลค้นหาปัจจุบัน)</td>
                <td class="num"><?= fmt_num($gradeTotals['grand_total']) ?></td>
                <td class="num"><?= fmt_num($gradeTotals['grand_total_male']) ?></td>
                <td class="num"><?= fmt_num($gradeTotals['grand_total_female']) ?></td>
                <?php foreach ($gradeLabels as $label): ?>
                  <td class="num"><?= fmt_num($gradeTotals['grades'][$label]['male']) ?></td>
                  <td class="num"><?= fmt_num($gradeTotals['grades'][$label]['female']) ?></td>
                <?php endforeach; ?>
                <td class="num"><?= fmt_num($gradeTotals['childcare_total']['male']) ?></td>
                <td class="num"><?= fmt_num($gradeTotals['childcare_total']['female']) ?></td>
                <?php foreach ($nfeActivityLabelsShown as $label): ?>
                  <td class="num"><?= fmt_num($gradeTotals['nfe_by_activity'][$label]['male']) ?></td>
                  <td class="num"><?= fmt_num($gradeTotals['nfe_by_activity'][$label]['female']) ?></td>
                <?php endforeach; ?>
                <td class="num"><?= fmt_num($gradeTotals['private_nonformal_total']['male']) ?></td>
                <td class="num"><?= fmt_num($gradeTotals['private_nonformal_total']['female']) ?></td>
                <td class="num"><?= fmt_num($gradeTotals['pmj_total']['male']) ?></td>
                <td class="num"><?= fmt_num($gradeTotals['pmj_total']['female']) ?></td>
              </tr>
              <?php foreach ($gradeTableRows as $row): ?>
                <tr>
                  <td><code><?= h($row['school_code']) ?></code></td>
                  <td><?= h($row['school_name']) ?></td>
                  <td><?= h($row['agency_name']) ?></td>
                  <td><?= h($row['amphoe']) ?></td>
                  <td class="num"><?= fmt_num($row['grand_total']) ?></td>
                  <td class="num"><?= fmt_num($row['grand_total_male']) ?></td>
                  <td class="num"><?= fmt_num($row['grand_total_female']) ?></td>
                  <?php foreach ($gradeLabels as $label): ?>
                    <td class="num"><?= fmt_num($row['grades'][$label]['male']) ?></td>
                    <td class="num"><?= fmt_num($row['grades'][$label]['female']) ?></td>
                  <?php endforeach; ?>
                  <td class="num"><?= fmt_num($row['childcare_total']['male']) ?></td>
                  <td class="num"><?= fmt_num($row['childcare_total']['female']) ?></td>
                  <?php foreach ($nfeActivityLabelsShown as $label): ?>
                    <td class="num"><?= fmt_num($row['nfe_by_activity'][$label]['male']) ?></td>
                    <td class="num"><?= fmt_num($row['nfe_by_activity'][$label]['female']) ?></td>
                  <?php endforeach; ?>
                  <td class="num"><?= fmt_num($row['private_nonformal_total']['male']) ?></td>
                  <td class="num"><?= fmt_num($row['private_nonformal_total']['female']) ?></td>
                  <td class="num"><?= fmt_num($row['pmj_total']['male']) ?></td>
                  <td class="num"><?= fmt_num($row['pmj_total']['female']) ?></td>
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
