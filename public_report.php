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

      <!-- สรุปภาพรวมสำหรับผู้บริหาร (เพิ่มเมื่อ 2026-09-10 ตามคำขอผู้ใช้งาน ให้ตอบคำถามผู้ว่าราชการ
           จังหวัด/ศึกษาธิการจังหวัดได้ทันทีโดยไม่ต้องไล่หากราฟด้านล่าง — ตัวเลขทุกตัวมาจาก $dataByMetric
           ที่คำนวณไว้แล้วในหน้านี้อยู่แล้วทั้งหมด ดู public_report_data.php) -->
      <div class="stat-grid viz-root">
        <div class="card stat-tile">
          <span class="icon-badge blue">🧑‍🎓</span>
          <div class="stat-tile-label">นักเรียน/ผู้เรียนทั้งหมด</div>
          <div class="stat-tile-value"><?= h(fmt_num($totalStudents)) ?></div>
          <div class="stat-tile-sub">คน</div>
        </div>
        <div class="card stat-tile">
          <span class="icon-badge orange">👨‍🏫</span>
          <div class="stat-tile-label">ครู/ผู้สอนทั้งหมด</div>
          <div class="stat-tile-value"><?= h(fmt_num($totalTeachers)) ?></div>
          <div class="stat-tile-sub">คน</div>
        </div>
        <div class="card stat-tile">
          <span class="icon-badge green">🏫</span>
          <div class="stat-tile-label">สถานศึกษาทั้งหมด</div>
          <div class="stat-tile-value"><?= h(fmt_num($totalSchools)) ?></div>
          <div class="stat-tile-sub">แห่ง</div>
        </div>
        <div class="card stat-tile">
          <span class="icon-badge cyan">📍</span>
          <div class="stat-tile-label">อำเภอที่มีข้อมูล</div>
          <div class="stat-tile-value"><?= h(fmt_num($totalAmphoeServed)) ?></div>
          <div class="stat-tile-sub">อำเภอ</div>
        </div>
        <div class="card stat-tile">
          <span class="icon-badge purple">🏛️</span>
          <div class="stat-tile-label">สังกัด/หน่วยงาน</div>
          <div class="stat-tile-value"><?= h(fmt_num($totalAgencies)) ?></div>
          <div class="stat-tile-sub">หน่วยงาน</div>
        </div>
        <div class="card stat-tile">
          <span class="icon-badge pink">⚠️</span>
          <div class="stat-tile-label">อัตรานักเรียนออกกลางคัน</div>
          <div class="stat-tile-value"><?= $dropoutRateOverall === null ? '—' : h(number_format($dropoutRateOverall, 2)) . '%' ?></div>
          <div class="stat-tile-sub"><?= h(fmt_num($totalDropout)) ?> คน จากทั้งหมด <?= h(fmt_num($totalStudents)) ?> คน</div>
        </div>
      </div>

      <!-- กราฟอำเภอ+โดนัทรูปแบบการศึกษา ย้ายขึ้นมาไว้ต้นหน้า (เดิมอยู่ปนอยู่ในลิสต์กราฟแท่งด้านล่าง
           ร่วมกับต้นสังกัด — ยังอยู่ในหน้านี้ครบ แค่ย้ายตำแหน่งให้เห็นภาพรวมก่อนตามคำขอผู้ใช้งาน) -->
      <div class="chart-row">
        <div class="card viz-root">
          <div class="card-head-row"><h2>📊 จำนวนนักเรียน/ผู้เรียน แยกตามอำเภอ</h2></div>
          <?php render_bar_chart($studentsByAmphoe, $fmtPeople); ?>
        </div>
        <div class="card viz-root">
          <div class="card-head-row"><h2>🍩 สัดส่วนนักเรียน/ผู้เรียน แยกตามรูปแบบการศึกษา</h2></div>
          <?php render_donut_chart($studentsByEducationForm, $fmtPeople); ?>
        </div>
      </div>

      <div class="card viz-root">
        <div class="card-head-row"><h2>🏛️ จำนวนนักเรียน/ผู้เรียน แยกตามต้นสังกัด</h2></div>
        <?php render_bar_chart($studentsByDept, $fmtPeople); ?>
      </div>

      <!-- สรุปข้อมูลรายอำเภอ (การ์ดต่ออำเภอ) — ใหม่ทั้งหมด เพิ่มเมื่อ 2026-09-10 ตามคำขอผู้ใช้งาน ใช้
           $studentsByAmphoe/$schoolsByAmphoe ที่มีอยู่แล้วในหน้านี้ ไม่ได้เพิ่ม query ใหม่ — % คือสัดส่วน
           ต่อยอดผู้เรียนทั้งจังหวัด แถบกราฟเทียบกับอำเภอที่มีผู้เรียนมากที่สุด (แบบเดียวกับกราฟแท่งอื่น
           ในหน้านี้ทั้งหมด) -->
      <h2 style="margin: 28px 0 4px;">🗺️ สรุปข้อมูลรายอำเภอ (<?= h(fmt_num($totalAmphoeServed)) ?> อำเภอ)</h2>
      <p class="muted" style="margin-bottom:16px;">คลิกที่อำเภอเพื่อค้นหาสถานศึกษาในพื้นที่ได้ที่หน้า
        "<a href="public_school_search.php?year=<?= h((string)$selectedYear) ?>">ค้นหารหัสสถานศึกษา</a>" — % คือสัดส่วนผู้เรียนต่อยอดรวมทั้งจังหวัด</p>
      <div class="district-grid viz-root">
        <?php
          // max เฉพาะอำเภอจริง ไม่รวม "ไม่ระบุ" (ไม่งั้นถ้าโรงเรียนที่ยังจับคู่อำเภอไม่ได้มีเยอะ
          // จะไปดันค่า max ขึ้น ทำให้แถบกราฟของอำเภอจริงทุกอำเภอดูเตี้ยลงผิดสัดส่วน)
          $realDistrictValues = array_filter($studentsByAmphoe, static fn($amphoe) => $amphoe !== 'ไม่ระบุ', ARRAY_FILTER_USE_KEY);
          $maxDistrictStudents = $realDistrictValues ? max($realDistrictValues) : 0;
          foreach ($studentsByAmphoe as $amphoe => $studentCount):
            if ($amphoe === 'ไม่ระบุ') { continue; }
            $sharePct = $totalStudents > 0 ? $studentCount / $totalStudents * 100 : 0;
            $barPct = $maxDistrictStudents > 0 ? $studentCount / $maxDistrictStudents * 100 : 0;
            $districtSchoolCount = $schoolsByAmphoe[$amphoe] ?? 0;
        ?>
          <div class="card district-card">
            <div class="district-card-head">
              <span class="name">อ.<?= h($amphoe) ?></span>
              <span class="badge badge-ok"><?= h(number_format($sharePct, 1)) ?>%</span>
            </div>
            <div class="meta"><?= h(fmt_num($districtSchoolCount)) ?> สถานศึกษา</div>
            <div class="num-label">จำนวนผู้เรียน</div>
            <div class="num-value"><?= h(fmt_num($studentCount)) ?> คน</div>
            <div class="progress-track"><div class="progress-fill" style="width: <?= h(number_format($barPct, 2, '.', '')) ?>%"></div></div>
          </div>
        <?php endforeach; ?>
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
        // ตัดต้นสังกัด/อำเภอ/รูปแบบการศึกษา ออกจากลิสต์นี้แล้ว (ย้ายขึ้นไปแสดงต้นหน้าแทนแล้วด้านบน —
        // ดูคอมเมนต์ตรง .chart-row/.district-grid — ข้อมูลชุดเดียวกัน ไม่ได้ลบทิ้ง แค่ไม่ให้แสดงซ้ำ 2 รอบ)
        $barCharts = [
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
