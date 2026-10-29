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
 * เมนูซ้าย (sidebar) สำหรับหน้าแอดมิน — รายการเดียวกันทุกหน้า (เพิ่มเมื่อ 2026-10-29 ตามคำขอผู้ใช้งาน
 * ย้ายจากเมนูบนที่แต่เดิมแต่ละหน้าโชว์ลิงก์คนละชุดไม่เท่ากัน มารวมเป็นชุดเดียวกันทุกหน้า) — ป้ายเลข
 * เตือน "รายการที่ต้องตรวจสอบ" (เช่น ที่ index.php เคยต่อท้าย label เป็น "(12)") ไม่ได้อยู่ในฟังก์ชันนี้
 * เพราะผู้ใช้งานยืนยันให้โชว์เฉพาะหน้าแดชบอร์ดเหมือนเดิม — index.php แก้ label ของ item ตัวเองก่อนส่งต่อ
 * ให้ render_app_shell_start() เอง ไม่ใช่ฟังก์ชันนี้คำนวณ query ใหม่ทุกหน้า
 *
 * @return array<int,array{key:string,href:string,label:string,icon:string,target:string}>
 */
function admin_nav_items(): array
{
    return [
        ['key' => 'dashboard',    'href' => 'index.php',                'label' => 'แดชบอร์ด',                       'icon' => '🏠', 'target' => '_self'],
        ['key' => 'review',       'href' => 'review.php',               'label' => 'รายการที่ต้องตรวจสอบ',            'icon' => '✅', 'target' => '_self'],
        ['key' => 'history',     'href' => 'uploads_history.php',      'label' => 'ประวัติการอัปโหลด',               'icon' => '📁', 'target' => '_self'],
        ['key' => 'schools',     'href' => 'schools_master.php',       'label' => 'ทำเนียบโรงเรียน',                 'icon' => '🏫', 'target' => '_self'],
        ['key' => 'population',  'href' => 'population_upload.php',    'label' => 'ข้อมูลประชากรรายอายุ',           'icon' => '👥', 'target' => '_self'],
        ['key' => 'search',      'href' => 'public_school_search.php', 'label' => 'ค้นหารหัสสถานศึกษา',             'icon' => '🔍', 'target' => '_blank'],
        ['key' => 'public_stats','href' => 'public_report.php',        'label' => 'สถิติการศึกษาจังหวัด (สาธารณะ)',  'icon' => '📊', 'target' => '_blank'],
        ['key' => 'settings',    'href' => 'settings.php',             'label' => 'ตั้งค่า',                         'icon' => '⚙️', 'target' => '_self'],
    ];
}

/**
 * เมนูซ้ายสำหรับหน้าสาธารณะ — รายการเดียวกันทุกหน้า ต่อ query string ปี (+มิติ ถ้ามี) ให้ทุกลิงก์
 * เหมือนกันหมด (เดิมแต่ละหน้าสร้าง query string กันเองคนละแบบ บางหน้าใช้ $navQuery บางหน้าใช้
 * $reportNavQuery บางหน้าใช้ year= ตรง ๆ ไม่ตรงกัน — รวมเป็นจุดเดียวที่นี่)
 *
 * @param array<string,scalar> $queryParams เช่น ['year'=>2569] หรือ ['year'=>2569,'dim'=>'agency_name']
 * @return array<int,array{key:string,href:string,label:string,icon:string,target:string}>
 */
function public_nav_items(array $queryParams): array
{
    $qs = http_build_query($queryParams);
    $items = [
        ['key' => 'overview',       'href' => 'public_report.php',              'label' => 'ภาพรวม',               'icon' => '📊'],
        ['key' => 'table',          'href' => 'public_report_table.php',        'label' => 'ตารางสรุปยอดรวม',       'icon' => '📋'],
        ['key' => 'grades',         'href' => 'public_school_grade_table.php',  'label' => 'ผู้เรียนรายชั้น',       'icon' => '🏫'],
        ['key' => 'teacher_grades', 'href' => 'public_teacher_grade_table.php', 'label' => 'ครูผู้สอน',             'icon' => '👨‍🏫'],
        ['key' => 'population',     'href' => 'public_population.php',         'label' => 'ประชากรวัยเรียน',       'icon' => '👥'],
        ['key' => 'search',         'href' => 'public_school_search.php',      'label' => 'ค้นหารหัสสถานศึกษา',    'icon' => '🔍'],
    ];
    foreach ($items as &$item) {
        $item['href'] .= '?' . $qs;
        $item['target'] = '_self';
    }
    unset($item);
    $items[] = ['key' => 'login', 'href' => 'login.php', 'label' => 'เข้าสู่ระบบเจ้าหน้าที่', 'icon' => '🔑', 'target' => '_self'];
    return $items;
}

/**
 * เปิดโครง sidebar+เนื้อหา — เรียกแทนการเขียน <div class="topbar">...</div> ตรง ๆ ทุกหน้า (เพิ่มเมื่อ
 * 2026-10-29 แทนที่เมนูบนแนวนอนเดิมด้วยเมนูซ้าย) ปิดด้วย render_app_shell_end() เสมอ — เพิ่ม viewport
 * meta tag ให้ทุกหน้าที่เรียกใช้ฟังก์ชันนี้โดยอัตโนมัติ (หน้าแอดมินหลายหน้าไม่มี tag นี้มาตั้งแต่ต้น
 * ทำให้เมนูมือถือใช้งานไม่ได้จริงบนโทรศัพท์) โชว์กล่องชื่อผู้ใช้+ออกจากระบบท้าย sidebar อัตโนมัติเมื่อ
 * login แล้ว (Auth::check() คืน false เงียบ ๆ บนหน้าสาธารณะที่ไม่เคย login เลย ไม่ error)
 *
 * @param array<int,array{key:string,href:string,label:string,icon:string,target:string}> $navItems
 */
function render_app_shell_start(string $pageTitle, string $brandLabel, array $navItems, string $activeKey, string $extraCss = ''): void
{
    ?>
<!doctype html>
<html lang="th">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= h($pageTitle) ?> — ptnedinfo</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@400;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="public/assets/style.css">
<?php if ($extraCss !== ''): ?>
<link rel="stylesheet" href="<?= h($extraCss) ?>">
<?php endif; ?>
<script src="public/assets/sidebar.js" defer></script>
</head>
<body>
<div class="mobile-topbar">
  <button class="hamburger-btn" type="button" data-hamburger-toggle aria-label="เปิดเมนู"><span></span><span></span><span></span></button>
  <span><?= h($brandLabel) ?></span>
</div>
<div class="sidebar-backdrop"></div>
<div class="app-shell">
  <nav class="sidebar">
    <div class="brand"><?= h($brandLabel) ?></div>
    <nav>
      <?php foreach ($navItems as $item): ?>
        <a href="<?= h($item['href']) ?>"
           class="<?= $item['key'] === $activeKey ? 'active' : '' ?>"
           <?= ($item['target'] ?? '_self') === '_blank' ? 'target="_blank"' : '' ?>>
          <span class="ic"><?= $item['icon'] ?></span><?= h($item['label']) ?>
        </a>
      <?php endforeach; ?>
    </nav>
    <?php if (Auth::check()): ?>
    <div style="margin-top:auto; padding:10px 12px; font-size:12px; color:var(--muted);">
      <?= h(Auth::displayName()) ?><br><a href="logout.php">ออกจากระบบ</a>
    </div>
    <?php endif; ?>
  </nav>
  <div class="app-main">
    <?php
}

/** ปิด .app-main / .app-shell / body / html ที่เปิดไว้ใน render_app_shell_start() — ไม่มี footer
 *  ในตัว (หน้าที่ต้องการ footer เรนเดอร์เองก่อนเรียกฟังก์ชันนี้ เหมือนที่ทำกันอยู่แล้วทุกหน้าในปัจจุบัน) */
function render_app_shell_end(): void
{
    ?>
  </div>
</div>
</body>
</html>
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
