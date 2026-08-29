<?php
/**
 * สถิติการศึกษาจังหวัดปัตตานี — หน้ากราฟ (หน้าเปิดเผยต่อสาธารณะ ไม่ต้อง login) ดูหน้าตารางสรุป
 * ยอดรวมแบบละเอียดได้ที่ public_report_table.php — ทั้งสองหน้าใช้ข้อมูล/ฟังก์ชันร่วมกันจาก
 * public_report_data.php (ไม่ query ซ้ำ) สลับกันได้ผ่านเมนูด้านซ้าย
 */
require_once __DIR__ . '/public_report_data.php';

render_report_start('charts');
?>
      <div class="card viz-root">
        <div class="kpi-row">
          <div class="kpi-col">
            <h3>สัดส่วนนักเรียนชาย : หญิง</h3>
            <?php if ($genderTotal <= 0): ?>
              <p class="muted">ยังไม่มีข้อมูล</p>
            <?php else: ?>
              <?php
                $malePct = $genderMale / $genderTotal * 100;
                $femalePct = $genderFemale / $genderTotal * 100;
              ?>
              <div class="gender-bar">
                <div class="gender-seg male" style="width: <?= h(number_format($malePct, 2, '.', '')) ?>%"
                     title="ชาย: <?= h(fmt_num($genderMale)) ?> คน (<?= h(number_format($malePct, 1)) ?>%)"></div>
                <div class="gender-seg female" style="width: <?= h(number_format($femalePct, 2, '.', '')) ?>%"
                     title="หญิง: <?= h(fmt_num($genderFemale)) ?> คน (<?= h(number_format($femalePct, 1)) ?>%)"></div>
              </div>
              <div class="gender-legend">
                <span class="legend-item"><span class="swatch male"></span>ชาย <?= h(fmt_num($genderMale)) ?> คน (<?= h(number_format($malePct, 1)) ?>%)</span>
                <span class="legend-item"><span class="swatch female"></span>หญิง <?= h(fmt_num($genderFemale)) ?> คน (<?= h(number_format($femalePct, 1)) ?>%)</span>
              </div>
            <?php endif; ?>
          </div>
          <div class="kpi-col">
            <h3>สัดส่วนครูชาย : หญิง</h3>
            <?php if ($teacherGenderTotal <= 0): ?>
              <p class="muted">ยังไม่มีข้อมูล</p>
            <?php else: ?>
              <?php
                $tMalePct = $teacherGenderMale / $teacherGenderTotal * 100;
                $tFemalePct = $teacherGenderFemale / $teacherGenderTotal * 100;
              ?>
              <div class="gender-bar">
                <div class="gender-seg male" style="width: <?= h(number_format($tMalePct, 2, '.', '')) ?>%"
                     title="ชาย: <?= h(fmt_num($teacherGenderMale)) ?> คน (<?= h(number_format($tMalePct, 1)) ?>%)"></div>
                <div class="gender-seg female" style="width: <?= h(number_format($tFemalePct, 2, '.', '')) ?>%"
                     title="หญิง: <?= h(fmt_num($teacherGenderFemale)) ?> คน (<?= h(number_format($tFemalePct, 1)) ?>%)"></div>
              </div>
              <div class="gender-legend">
                <span class="legend-item"><span class="swatch male"></span>ชาย <?= h(fmt_num($teacherGenderMale)) ?> คน (<?= h(number_format($tMalePct, 1)) ?>%)</span>
                <span class="legend-item"><span class="swatch female"></span>หญิง <?= h(fmt_num($teacherGenderFemale)) ?> คน (<?= h(number_format($tFemalePct, 1)) ?>%)</span>
              </div>
            <?php endif; ?>
          </div>
          <div class="kpi-col">
            <h3>อัตราส่วนนักเรียนต่อครู/บุคลากร</h3>
            <?php if ($studentTeacherRatio === null): ?>
              <p class="muted">ยังไม่มีข้อมูล</p>
            <?php else: ?>
              <div class="stat-value"><?= h(number_format($studentTeacherRatio, 1)) ?> : 1</div>
              <div class="stat-sub">นักเรียน <?= h(fmt_num($totalStudents)) ?> คน ต่อครู/บุคลากร <?= h(fmt_num($totalTeachers)) ?> คน</div>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <div class="card viz-root">
        <h2>จำนวนนักเรียน/ผู้เรียน รายปีการศึกษา</h2>
        <?php render_bar_chart($studentsByYear, $fmtPeople); ?>
      </div>

      <?php
        $barCharts = [
            ['title' => 'จำนวนนักเรียน/ผู้เรียน แยกตามต้นสังกัด', 'data' => $studentsByDept, 'fmt' => $fmtPeople],
            ['title' => 'จำนวนนักเรียน/ผู้เรียน แยกตามอำเภอ', 'data' => $studentsByAmphoe, 'fmt' => $fmtPeople],
            ['title' => 'นักเรียนออกกลางคัน แยกตามสาเหตุ', 'data' => $dropoutByReason, 'fmt' => $fmtPeople],
            ['title' => 'นักเรียนพิการ แยกตามประเภทความพิการ', 'data' => $disabilityByType, 'fmt' => $fmtPeople],
        ];
      ?>
      <?php foreach ($barCharts as $chart): ?>
        <div class="card viz-root">
          <h2><?= h($chart['title']) ?></h2>
          <?php render_bar_chart($chart['data'], $chart['fmt']); ?>
        </div>
      <?php endforeach; ?>

      <div class="card viz-root">
        <h2>อำเภอที่มีอัตรานักเรียนออกกลางคันสูงสุด/ต่ำสุด (% ของนักเรียนในอำเภอนั้น)</h2>
        <p class="muted">คำนวณจากนักเรียนออกกลางคันหารด้วยจำนวนนักเรียนทั้งหมดในอำเภอเดียวกัน ไม่ใช่จำนวนดิบ
          เพื่อไม่ให้อำเภอที่มีนักเรียนเยอะดูน่ากังวลเกินจริงเทียบกับอำเภอเล็ก ๆ</p>
        <div class="kpi-row">
          <div class="kpi-col">
            <h3>5 อันดับสูงสุด</h3>
            <?php render_bar_chart($dropoutRateTop5, $fmtPercent); ?>
          </div>
          <div class="kpi-col">
            <h3>5 อันดับต่ำสุด</h3>
            <?php render_bar_chart($dropoutRateBottom5, $fmtPercent); ?>
          </div>
        </div>
      </div>
<?php
render_report_end();
