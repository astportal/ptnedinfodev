<?php
/**
 * สถิติการศึกษาจังหวัดปัตตานี — หน้าตารางสรุปยอดรวมแบบละเอียด (หน้าเปิดเผยต่อสาธารณะ ไม่ต้อง
 * login) ดูเป็นกราฟได้ที่ public_report.php — ทั้งสองหน้าใช้ข้อมูล/ฟังก์ชันร่วมกันจาก
 * public_report_data.php (ไม่ query ซ้ำ) สลับกันได้ผ่านเมนูด้านซ้าย
 */
require_once __DIR__ . '/public_report_data.php';

render_report_start('table');
?>
      <div class="card">
        <h2>สรุปยอดรวมแยกตาม<?= h($dimensions[$selectedDimension]) ?></h2>
        <?php if (!$groups): ?>
          <p class="muted">ยังไม่มีข้อมูลสำหรับปีการศึกษานี้</p>
        <?php else: ?>
          <div class="table-scroll">
            <table class="stats-table">
              <thead>
                <tr>
                  <th><?= h($dimensions[$selectedDimension]) ?></th>
                  <?php foreach ($metrics as $m): ?>
                    <th class="num"><?= h($m['label']) ?></th>
                  <?php endforeach; ?>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($groups as $g): ?>
                  <tr>
                    <td><?= h($g) ?></td>
                    <?php foreach (array_keys($metrics) as $key): ?>
                      <td class="num"><?= fmt_num($dataByMetric[$key][$g] ?? 0) ?></td>
                    <?php endforeach; ?>
                  </tr>
                <?php endforeach; ?>
              </tbody>
              <tfoot>
                <tr>
                  <td>รวมทั้งจังหวัด</td>
                  <?php foreach (array_keys($metrics) as $key): ?>
                    <?php $grand = array_sum($dataByMetric[$key]); ?>
                    <td class="num"><?= fmt_num($grand) ?></td>
                  <?php endforeach; ?>
                </tr>
              </tfoot>
            </table>
          </div>
        <?php endif; ?>
      </div>
<?php
render_report_end();
