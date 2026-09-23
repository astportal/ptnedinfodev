<?php
declare(strict_types=1);

date_default_timezone_set('Asia/Bangkok');

if (!file_exists(__DIR__ . '/config.php')) {
    http_response_code(500);
    die('ยังไม่ได้ตั้งค่าระบบ: กรุณาคัดลอก config.sample.php เป็น config.php แล้วใส่ค่าฐานข้อมูลให้ถูกต้อง');
}

require_once __DIR__ . '/src/Db.php';
require_once __DIR__ . '/src/Settings.php';
require_once __DIR__ . '/src/Auth.php';
require_once __DIR__ . '/src/XlsxReader.php';
require_once __DIR__ . '/src/Importer.php';
require_once __DIR__ . '/src/Reporting.php';
require_once __DIR__ . '/src/PopulationImporter.php';

function form_registry(): array
{
    return require __DIR__ . '/forms/registry.php';
}

function h(?string $s): string
{
    return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8');
}

/**
 * ปุ่มลอยลิงก์แบบประเมินความพึงพอใจ (แบบสำรวจของ สป.ศธ.) — เรียกจากหน้าสาธารณะที่มีข้อมูลให้ดู/
 * ดาวน์โหลด (เพิ่มเมื่อ 2026-09-23 ตามคำขอผู้ใช้งาน) อยู่ตรงนี้ (bootstrap.php) เพราะเป็นจุดเดียวที่ทุก
 * หน้าในระบบ require มาถึงอยู่แล้วไม่ว่าจะเป็นหน้าแอดมินหรือหน้าสาธารณะ — ไม่ต้องเพิ่ม dependency ใหม่
 * ให้หน้าไหนเลย ปิดได้เอง (จำผ่าน localStorage ของเบราว์เซอร์แต่ละเครื่อง 30 วัน ไม่มีการเขียน/อ่าน
 * ฐานข้อมูลฝั่งเซิร์ฟเวอร์ใด ๆ ทั้งสิ้น — ฟีเจอร์นี้จึงไม่กระทบฐานข้อมูลเลย) เรียกก่อนปิด </body> ของหน้า
 */
function render_survey_fab(): void
{
    ?>
<a href="https://shorturl.moe.go.th/bh9yf" target="_blank" rel="noopener" class="survey-fab" id="surveyFab">
  <span aria-hidden="true">📋</span><span>ประเมินความพึงพอใจ</span>
</a>
<script>
(function () {
  var KEY = 'surveyFabDismissedUntil';
  var fab = document.getElementById('surveyFab');
  if (!fab) { return; }
  try {
    var until = localStorage.getItem(KEY);
    if (until && Date.now() < parseInt(until, 10)) { fab.style.display = 'none'; return; }
  } catch (e) {}
  var closeBtn = document.createElement('span');
  closeBtn.textContent = '✕';
  closeBtn.className = 'survey-fab-close';
  closeBtn.setAttribute('aria-label', 'ปิด');
  closeBtn.addEventListener('click', function (e) {
    e.preventDefault();
    e.stopPropagation();
    fab.style.display = 'none';
    try { localStorage.setItem(KEY, String(Date.now() + 30 * 24 * 60 * 60 * 1000)); } catch (e2) {}
  });
  fab.appendChild(closeBtn);
})();
</script>
    <?php
}

/**
 * Human label for a non-standard identity field name used in some sheets'
 * identity_fields (see forms/registry.php) — falls back to the raw field name.
 */
function extra_identity_label(string $field): string
{
    static $labels = [
        'age_group'  => 'ช่วงอายุ',
        'admin_name' => 'ผู้บริหาร/ผู้ประสานงาน',
        'phone' => 'เบอร์โทรศัพท์',
        'address' => 'ที่อยู่',
        'postal_code' => 'รหัสไปรษณีย์',
        'license_holder_name' => 'ผู้รับใบอนุญาต',
        'manager_name' => 'ผู้จัดการ',
        'headteacher_name' => 'ครูใหญ่',
        'director_name' => 'ผู้อำนวยการ',
        'tokkhru_name' => 'โต๊ะครู',
        'pondok_size' => 'ขนาดสถาบันศึกษาปอเนาะ',
        'school_type' => 'ประเภทโรงเรียน',
        'established_date' => 'จัดตั้งเมื่อ',
        'project_name' => 'ชื่อโครงการ',
    ];
    return $labels[$field] ?? $field;
}
