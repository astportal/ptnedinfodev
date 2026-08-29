<?php
/**
 * ตารางครูผู้สอน 1 แถวต่อสถานศึกษา แยกชาย/หญิง — หน้าเปิดเผยต่อสาธารณะ ไม่ต้อง login เหมือนหน้า
 * สาธารณะอื่น ๆ ดูรายละเอียดที่มาของข้อมูล/เหตุผลออกแบบใน public_teacher_grade_table_data.php และ
 * ai_note.md (ปรับโครงสร้างเมื่อ 2026-08-29 — เดิมแยกตามระดับชั้นที่สอน เปลี่ยนเป็นยอดรวมครูผู้สอน
 * เดียว แยกชาย/หญิง ตามคำขอผู้ใช้งาน จึงไม่มี 2-row header/colspan แบบตารางผู้เรียนรายชั้นแล้ว)
 */
require_once __DIR__ . '/public_teacher_grade_table_data.php';
?>
<!doctype html>
<html lang="th">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>ตารางครูผู้สอน — ptnedinfo</title>
<link rel="stylesheet" href="public/assets/style.css">
<link rel="stylesheet" href="public/assets/public_report.css">
</head>
<body>
<div class="topbar">
  <a href="public_report.php">ข้อมูลด้านการศึกษาจังหวัดปัตตานี</a>
  <nav>
    <a href="public_report.php?<?= h($navQuery) ?>">ภาพรวม</a>
    <a href="public_report_table.php?<?= h($navQuery) ?>">ตารางสรุปยอดรวม</a>
    <a href="public_school_search.php?<?= h($navQuery) ?>">ค้นหารหัสสถานศึกษา</a>
    <a href="public_school_grade_table.php?<?= h($navQuery) ?>">ผู้เรียนรายชั้น</a>
    <a href="public_teacher_grade_table.php?<?= h($navQuery) ?>" class="active">ครูผู้สอน</a>
    <a href="login.php">เข้าสู่ระบบเจ้าหน้าที่</a>
  </nav>
</div>
<div class="container" style="max-width: 98vw;">
  <div class="report-main">
    <div class="card">
      <h1>ตารางครูผู้สอน ประจำปีการศึกษา <?= h((string)$selectedYear) ?></h1>

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
      <p><a class="btn btn-secondary" href="public_teacher_grade_table_export.php?<?= h(http_build_query(['year' => $selectedYear, 'q' => $searchName, 'agency' => $filterAgency, 'amphoe' => $filterAmphoe])) ?>" style="display:inline-block;">ดาวน์โหลด CSV (ตามผลค้นหาปัจจุบัน)</a></p>
    </div>

    <div class="card">
      <?php if (!$gradeTableRows): ?>
        <p class="muted">ไม่พบสถานศึกษาที่ตรงกับเงื่อนไขค้นหานี้</p>
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
                <th class="num">รวม</th>
                <th class="num">ครูผู้สอน (ชาย)</th>
                <th class="num">ครูผู้สอน (หญิง)</th>
                <th class="num">ครู ศพด. (ชาย)</th>
                <th class="num">ครู ศพด. (หญิง)</th>
                <th class="num">ครูนอกระบบ</th>
              </tr>
            </thead>
            <tbody>
              <tr class="row-total">
                <td colspan="4">รวม (ตามผลค้นหาปัจจุบัน)</td>
                <td class="num"><?= fmt_num($gradeTotals['grand_total']) ?></td>
                <td class="num"><?= fmt_num($gradeTotals['teaching_total']['male']) ?></td>
                <td class="num"><?= fmt_num($gradeTotals['teaching_total']['female']) ?></td>
                <td class="num"><?= fmt_num($gradeTotals['childcare_total']['male']) ?></td>
                <td class="num"><?= fmt_num($gradeTotals['childcare_total']['female']) ?></td>
                <td class="num"><?= fmt_num($gradeTotals['private_nonformal_total']) ?></td>
              </tr>
              <?php foreach ($gradeTableRows as $row): ?>
                <tr>
                  <td><code><?= h($row['school_code']) ?></code></td>
                  <td><?= h($row['school_name']) ?></td>
                  <td><?= h($row['agency_name']) ?></td>
                  <td><?= h($row['amphoe']) ?></td>
                  <td class="num"><?= fmt_num($row['grand_total']) ?></td>
                  <td class="num"><?= fmt_num($row['teaching_total']['male']) ?></td>
                  <td class="num"><?= fmt_num($row['teaching_total']['female']) ?></td>
                  <td class="num"><?= fmt_num($row['childcare_total']['male']) ?></td>
                  <td class="num"><?= fmt_num($row['childcare_total']['female']) ?></td>
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
