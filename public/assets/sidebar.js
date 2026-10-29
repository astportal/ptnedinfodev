// เปิด/ปิดเมนูซ้าย (sidebar) แบบลิ้นชักบนมือถือ — vanilla JS ล้วน ๆ ไม่พึ่ง framework/library
// ใดๆ (สไตล์เดียวกับ render_survey_fab() ใน bootstrap.php) เพิ่มเมื่อ 2026-10-29
document.addEventListener('DOMContentLoaded', function () {
  var sidebar = document.querySelector('.sidebar');
  var backdrop = document.querySelector('.sidebar-backdrop');
  var toggles = document.querySelectorAll('[data-hamburger-toggle]');

  // หน้าที่ยังไม่ได้ย้ายมาใช้ sidebar ใหม่ (ระหว่าง rollout) จะไม่มี element พวกนี้ — ออกเงียบ ๆ
  if (!sidebar || !backdrop) {
    return;
  }

  function openDrawer() {
    sidebar.classList.add('open');
    backdrop.classList.add('open');
  }

  function closeDrawer() {
    sidebar.classList.remove('open');
    backdrop.classList.remove('open');
  }

  for (var i = 0; i < toggles.length; i++) {
    toggles[i].addEventListener('click', openDrawer);
  }

  backdrop.addEventListener('click', closeDrawer);

  var links = sidebar.querySelectorAll('nav a');
  for (var j = 0; j < links.length; j++) {
    links[j].addEventListener('click', closeDrawer);
  }
});
