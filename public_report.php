<?php
/**
 * สถิติการศึกษาจังหวัดปัตตานี — หน้ากราฟ (หน้าเปิดเผยต่อสาธารณะ ไม่ต้อง login) ดูหน้าตารางสรุป
 * ยอดรวมแบบละเอียดได้ที่ public_report_table.php — ทั้งสองหน้าใช้ข้อมูล/ฟังก์ชันร่วมกันจาก
 * public_report_data.php (ไม่ query ซ้ำ) สลับกันได้ผ่านเมนูด้านซ้าย
 */
require_once __DIR__ . '/public_report_data.php';

render_report_start('charts');
?>
      <p class="section-nav"><a href="#section-students">↓ ข้อมูลผู้เรียน</a><a href="#section-teachers">↓ ข้อมูลครู</a></p>

      <div class="card viz-root">
        <div class="kpi-row">
          <div class="kpi-col">
            <h3>จำนวนผู้เรียนทั้งหมด</h3>
            <div class="stat-value"><?= h(fmt_num($totalStudents)) ?></div>
            <div class="stat-sub">คน</div>
          </div>
          <div class="kpi-col">
            <h3>จำนวนผู้สอนทั้งหมด</h3>
            <div class="stat-value"><?= h(fmt_num($totalTeachers)) ?></div>
            <div class="stat-sub">คน</div>
          </div>
        </div>
      </div>

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

      <div class="section-divider students" id="section-students">
        <h2>ข้อมูลผู้เรียน</h2>
        <span class="section-sub">จำนวน/สัดส่วนนักเรียนและผู้เรียนทุกกลุ่ม แยกตามมิติต่าง ๆ</span>
      </div>

      <div class="card viz-root">
        <h2>จำนวนนักเรียน/ผู้เรียน รายปีการศึกษา</h2>
        <?php render_bar_chart($studentsByYear, $fmtPeople); ?>
      </div>

      <?php
        $barCharts = [
            ['title' => 'จำนวนนักเรียน/ผู้เรียน แยกตามต้นสังกัด', 'data' => $studentsByDept, 'fmt' => $fmtPeople],
            ['title' => 'จำนวนนักเรียน/ผู้เรียน แยกตามอำเภอ', 'data' => $studentsByAmphoe, 'fmt' => $fmtPeople],
            ['title' => 'จำนวนนักเรียน/ผู้เรียน แยกตามรูปแบบการศึกษา', 'data' => $studentsByEducationForm, 'fmt' => $fmtPeople],
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

      <div class="card viz-root">
        <h2>สถานะหลังจบการศึกษา แยกตามระดับชั้น</h2>
        <p class="muted">ไม่รวมยอด "ทั้งหมด"/"ที่จบการศึกษา"/"ที่ไม่จบการศึกษา" (เป็นยอดรวมของคอลัมน์
          ปลายทางย่อยด้านล่างอยู่แล้ว) แสดงเฉพาะปลายทางย่อยของนักเรียนที่จบการศึกษาแต่ละระดับชั้น</p>
        <div class="kpi-row">
          <div class="kpi-col">
            <h3>จบ ป.6</h3>
            <?php render_bar_chart($graduateStatusP6, $fmtPeople); ?>
          </div>
          <div class="kpi-col">
            <h3>จบ ม.3</h3>
            <?php render_bar_chart($graduateStatusM3, $fmtPeople); ?>
          </div>
          <div class="kpi-col">
            <h3>จบ ม.6</h3>
            <?php render_bar_chart($graduateStatusM6, $fmtPeople); ?>
          </div>
        </div>
      </div>

      <div class="section-divider teachers" id="section-teachers">
        <h2>ข้อมูลครู</h2>
        <span class="section-sub">จำนวนครู/ผู้สอน แยกตามอันดับ-วิทยฐานะ, ตำแหน่งทางวิชาการ, วุฒิการศึกษา</span>
      </div>

      <div class="card viz-root">
        <h2>จำนวนครูแยกตามอันดับ/วิทยฐานะ (ตารางที่ 10.3)</h2>
        <p class="muted">เฉพาะครู (ไม่รวมผู้บริหาร/บุคลากรสนับสนุน) — แยกตามอันดับ/วิทยฐานะที่ได้รับ</p>
        <?php render_gender_breakdown_table_and_chart($teacherByRank, 'อันดับ/วิทยฐานะ', $fmtPeople); ?>
      </div>

      <div class="card viz-root">
        <h2>จำนวนผู้สอนสาย อว. แยกตามตำแหน่งทางวิชาการ (ตารางที่ 10.4)</h2>
        <p class="muted">เฉพาะสถานศึกษาสังกัดกระทรวงการอุดมศึกษาฯ (อว.) — แยกตามตำแหน่งทางวิชาการ</p>
        <?php render_gender_breakdown_table_and_chart($teacherByAcademicRank, 'ตำแหน่งทางวิชาการ', $fmtPeople); ?>
      </div>

      <div class="card viz-root">
        <h2>จำนวนครูแยกตามวุฒิการศึกษาสูงสุด (ตารางที่ 10.5)</h2>
        <p class="muted">เฉพาะครู (ไม่รวมผู้บริหาร/บุคลากรสนับสนุน) — แยกตามวุฒิการศึกษาสูงสุดที่สำเร็จ</p>
        <?php render_gender_breakdown_table_and_chart($teacherByEducation, 'วุฒิการศึกษา', $fmtPeople); ?>
      </div>
<?php
render_report_end();
