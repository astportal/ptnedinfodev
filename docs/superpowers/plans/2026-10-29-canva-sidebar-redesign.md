# Canva-Style Pastel Redesign + Left Sidebar Navigation Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace the 13 hand-written `<div class="topbar">` horizontal-nav blocks across the whole site (9 admin pages + 6 public pages) with one shared left-sidebar nav component (hamburger drawer on mobile), and recolor the site from the current dark-green theme to the approved "Candy Pastel" palette.

**Architecture:** One new pair of shell functions (`render_app_shell_start()`/`render_app_shell_end()`) plus two canonical nav-list builders (`admin_nav_items()`, `public_nav_items()`) live in `bootstrap.php`, the one file every page already `require`s. Every page that hand-writes `<div class="topbar">` today is rewritten to build its own nav-items array (optionally overriding one item's label, as `index.php` does for its live pending-count badge) and call the shared helper instead. CSS for the sidebar/hamburger/backdrop and the new palette lives in `public/assets/style.css` (loaded by every page); a new static `public/assets/sidebar.js` holds the vanilla-JS toggle logic.

**Tech Stack:** PHP 8 (no framework), vanilla CSS, vanilla JS (no build step, no new dependency, no npm/package.json introduced).

**Spec:** `docs/superpowers/specs/2026-10-29-canva-sidebar-redesign-design.md`

## Global Constraints

- No PHP runtime exists on any dev machine for this project (confirmed in the spec's Constraints section and `ai_note.md`). **"Run the test" in every task below means:** (a) a Node.js one-liner that counts `(`/`)`, `{`/`}`, `[`/`]` in the changed PHP/CSS/JS file and confirms the delta matches what it was before the edit (the established pattern used throughout this project's history — some files have a small pre-existing imbalance from Thai comment text; the check is "delta is zero," not "counts are literally equal"), run via the Bash tool's `node -e "..."`; (b) for the new `public/assets/sidebar.js` file specifically, `node --check public/assets/sidebar.js` (Node can syntax-check a plain `.js` file without a browser/DOM); (c) a manual line-by-line read confirming the logic is correct, since nothing can be executed end-to-end locally. Real confirmation only happens after this branch is pushed to `develop` and viewed on the dev site — call this out explicitly in each task's verification step, don't imply more certainty than hand-review provides.
- Zero changes to `forms/registry.php`, `src/Importer.php`, `src/Reporting.php`, `src/PopulationImporter.php`, or any DB query logic outside the two specific nav-related reads already in `index.php` (the pending-review count) — this is a presentation-layer change only.
- No new JS libraries, no build tooling, no icon font, no npm/package.json. Icons are emoji (matching the existing public nav's `📊 📋 🏫 👨‍🏫 👥 🔍 🔑` convention).
- Target branch is `develop` (per the project's existing deploy workflow) — do not touch `main`.
- `--series-1: #7fb3e0` and `--series-2: #f4a6c6` (the colorblind-safe gender chart colors in `public/assets/public_report.css`) must not change in this work.
- `.icon-badge`'s existing 6-color pastel set (`blue`/`green`/`pink`/`purple`/`orange`/`cyan`), `--ok`, `.badge-ok`, `.alert-ok`, and `.icon-badge.green` must not change — green stays green for "success/ok" states regardless of the new brand color.
- The donut-chart category palette (`$palette` array in `render_donut_chart()`, `public_report_data.php:462`) is a categorical multi-color list unrelated to the brand color — leave it untouched.
- Admin nav becomes one consistent canonical list on every admin page (confirmed with the user). The pending-review-count badge stays dashboard-only, exactly as today (confirmed with the user) — it must be handled by `index.php` overriding its own copy of one nav item's label before calling the shared helper, **never** by adding a query inside the shared `admin_nav_items()` function itself (that would run the count query on every admin page load, which the user did not ask for).

## Review Focus

- **Nav links that currently open in a new tab must keep doing so.** `public_school_search.php` and `public_report.php`, when linked from inside the authenticated admin area, use `target="_blank"` today (so clicking them doesn't navigate the admin session away from the admin UI). If the canonical nav-item array drops the `target` field for these two, that regresses. Task 3's pilot migration and the final cleanup task must grep-verify `target="_blank"` survives on exactly those two admin nav items.
- **Exactly one nav item is marked active per page, never zero or two.** Every migrated page passes an `$activeKey` to `render_app_shell_start()`; a typo or copy-paste mismatch (e.g. `'grade'` vs `'grades'`) silently shows no highlighted item instead of erroring. Each migration task's verification step must grep the specific `$activeKey` string passed against the specific `key` present in the matching canonical-list entry, by eye, for every page it touches.
- **Year/dimension filter query strings must survive clicking between nav links.** The public pages currently thread `$navQuery` (year+dim) or bare `year=` through every nav link so a visitor's selected academic year isn't reset when they switch pages. `public_nav_items()` must take the already-built query string from the caller, not reconstruct its own — Task 3/4/5 verification must check that `$navQuery`/`year=` flows into every one of the 6 public links, not just some.
- **The pending-review-count badge must only ever appear on `index.php`.** Task 3 and Task 6 (where `index.php` is migrated) must confirm the `({$needsReviewTotal})` suffix is built inside `index.php`'s own PHP, applied only to that one page's copy of the `admin_nav_items()` return value, and that `admin_nav_items()` itself takes no such parameter.
- **The two contextual, page-specific admin links (`view.php`'s "อัปโหลดไฟล์" for the current form, `edit_submission.php`'s "กลับไปดูข้อมูล" for the current form/sheet) must not silently disappear** when their nav bar switches to the canonical list — Task 8 must move them into each page's own content area (near its `<h1>`) rather than drop them, since they're the only way to get from "editing a row" back to "viewing that form's data" or forward to "re-upload this form."

---

## Task 1: Palette + sidebar/hamburger CSS foundation

**Files:**
- Modify: `public/assets/style.css`
- Modify: `public/assets/public_report.css`

**Interfaces:**
- Produces (CSS classes every later task's generated HTML will use — names are pinned here, not negotiable later): `.app-shell`, `.sidebar`, `.sidebar .brand`, `.sidebar nav`, `.sidebar nav a`, `.sidebar nav a .ic`, `.sidebar nav a.active`, `.sidebar .nav-sep`, `.app-main`, `.mobile-topbar`, `.hamburger-btn`, `.sidebar-backdrop`, and modifier `.sidebar.open` / `.sidebar-backdrop.open` for the mobile drawer state.
- Produces (CSS variables later tasks' inline styles may reference): `--primary: #7c3aed`, `--primary-dark: #6d28d9`, `--primary-light: #f3e8ff`, `--bg: #fafaff`, `--sidebar-w: 230px`.
- Consumes: nothing (foundation task).

- [ ] **Step 1: Update color variables in `public/assets/style.css`'s `:root` block**
  - Change `--bg` from `#f4f7f5` to `#fafaff`.
  - Change `--primary` from `#16a34a` to `#7c3aed`.
  - Change `--primary-dark` from `#15803d` to `#6d28d9`.
  - Change `--primary-light` from `#dcfce7` to `#f3e8ff`.
  - Delete the now-unused `--navy: #0d2b22;` and `--navy-2: #0a1a20;` lines (grep-confirmed nowhere else in the codebase references `--navy`).
  - Add `--sidebar-w: 230px;`.
  - Leave `--ok: #16a34a;` unchanged (success-state green, not the brand color).

- [ ] **Step 2: Replace the `.topbar` rule block with `.app-shell`/`.sidebar`/`.mobile-topbar`/`.hamburger-btn`/`.sidebar-backdrop` rules**
  - Keep the existing `.topbar` selector and its rules **as-is, untouched, for now** (Task 10 deletes it once nothing references it — deleting it here would be premature while any of the 13 old call sites still render it).
  - Add new rules (desktop-first, then one `@media (max-width: 880px)` block):
    - `.app-shell { display: flex; min-height: 100vh; }`
    - `.sidebar { width: var(--sidebar-w); flex-shrink: 0; background: #fff; border-right: 1px solid var(--border); padding: 18px 12px; position: sticky; top: 0; height: 100vh; overflow-y: auto; z-index: 100; display: flex; flex-direction: column; }`
    - `.sidebar .brand { font-weight: 800; font-size: 14px; color: var(--primary-dark); padding: 6px 10px 18px; line-height: 1.4; }`
    - `.sidebar nav { display: flex; flex-direction: column; gap: 4px; }`
    - `.sidebar nav a { display: flex; align-items: center; gap: 10px; padding: 10px 12px; border-radius: 10px; color: var(--text); text-decoration: none; font-weight: 500; font-size: 14px; }`
    - `.sidebar nav a:hover { background: #f8f8fb; }`
    - `.sidebar nav a .ic { width: 28px; height: 28px; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 13px; background: #f1f5f9; flex-shrink: 0; }`
    - `.sidebar nav a.active { background: var(--primary-light); color: var(--primary-dark); font-weight: 700; }`
    - `.sidebar .nav-sep { font-size: 11px; text-transform: uppercase; letter-spacing: .05em; color: var(--muted); margin: 14px 10px 6px; }`
    - `.app-main { flex: 1; min-width: 0; }`
    - `.mobile-topbar { display: none; align-items: center; gap: 12px; padding: 12px 16px; background: #fff; border-bottom: 1px solid var(--border); position: sticky; top: 0; z-index: 150; }`
    - `.hamburger-btn { width: 32px; height: 26px; display: flex; flex-direction: column; justify-content: space-between; background: none; border: none; cursor: pointer; padding: 0; }`
    - `.hamburger-btn span { display: block; height: 3px; border-radius: 2px; background: var(--primary); }`
    - `.sidebar-backdrop { display: none; position: fixed; inset: 0; background: rgba(15, 23, 42, .4); z-index: 199; }`
    - `@media (max-width: 880px) { .sidebar { position: fixed; left: 0; top: 0; transform: translateX(-100%); transition: transform .2s ease; z-index: 200; } .sidebar.open { transform: translateX(0); } .mobile-topbar { display: flex; } .sidebar-backdrop.open { display: block; } }`

- [ ] **Step 3: Recolor `.hero-card` and `.site-footer` to the light palette (same file)**
  - `.hero-card`: change `background: linear-gradient(135deg, #0f2a24 0%, #0a1a20 100%); color: #fff;` to `background: linear-gradient(135deg, #f3e8ff 0%, #fce7f3 100%); color: var(--text); border: none;`. Change `.hero-card h1, .hero-card p { color: #fff; }` to `{ color: var(--text); }`. Change `.hero-card .muted { color: rgba(255,255,255,.68); }` to `{ color: var(--muted); }`.
  - `.hero-badge`: change to a light pastel pill — `background: #ede9fe; color: #6d28d9; border: 1px solid #ddd6fe;` (keep the same padding/radius/font rules).
  - `.hero-card .filter-bar select`: the dark-glass styling (`rgba(255,255,255,.08)` background, light border) no longer makes sense on a light card — change to plain `background: #fff; border-color: var(--border); color: var(--text);` and delete the now-redundant `.hero-card .filter-bar select option { color: #1f2937; }` rule (no longer needed once the select itself is already dark-text-on-white).
  - `.site-footer`: change `background: linear-gradient(135deg, #0f2a24 0%, #0a1a20 100%); color: rgba(255,255,255,.75);` to `background: #faf5ff; color: var(--text); border-top: 1px solid var(--border);`. Change `.site-footer a { color: rgba(255,255,255,.75); }` to `{ color: var(--primary-dark); }` and its `:hover` to `{ color: var(--primary); }`. Change `.site-footer h3 { color: #fff; }` to `{ color: var(--text); }`. Change `.footer-bottom`'s border/text colors from the `rgba(255,255,255,…)` values to `var(--border)` / `var(--muted)`.

- [ ] **Step 4: Recolor `--series-brand` in `public/assets/public_report.css`**
  - Change `--series-brand: #16a34a;` to `--series-brand: #7c3aed;` (it was named "brand" specifically to track the site's brand color — see the spec's decision #10 — unlike `--series-1`/`--series-2` which are protected).
  - Update the comment above it (currently explains the 2026-09-10 split from `--series-1`) with one line noting it now tracks the new purple brand accent as of this redesign.

- [ ] **Step 5: Verify brace/paren/bracket balance didn't change unexpectedly**

  Run (Bash tool), once per file:
  ```
  node -e "const s=require('fs').readFileSync('public/assets/style.css','utf8'); const c={'(' :0,')':0,'{':0,'}':0}; for(const ch of s) if(ch in c) c[ch]++; console.log(c);"
  ```
  and the same for `public/assets/public_report.css`. Expected: `(` count equals `)` count and `{` count equals `}` count in both files (CSS has no legitimate reason to have unbalanced braces/parens, unlike the Thai-comment-paren situation in some `.php` files — so here the counts should come out exactly equal, not just "same delta as before").

- [ ] **Step 6: Commit**
  ```bash
  git add public/assets/style.css public/assets/public_report.css
  git commit -m "style: switch to Candy Pastel palette + add sidebar/hamburger CSS foundation"
  ```

---

## Task 2: Hamburger/drawer toggle script

**Files:**
- Create: `public/assets/sidebar.js`

**Interfaces:**
- Consumes: the CSS classes from Task 1 (`.sidebar`, `.sidebar.open`, `.sidebar-backdrop`, `.sidebar-backdrop.open`, `.hamburger-btn`).
- Produces: a script that, once loaded with `defer`, wires up every element matching `[data-hamburger-toggle]` to open `.sidebar`/`.sidebar-backdrop` (add `.open` to both), every `.sidebar-backdrop` click to close them (remove `.open` from both), and every `.sidebar nav a` click to also close them (so navigating on mobile doesn't leave the drawer open on the next page load — note this only matters if the next page is reached without a full reload breaking state, which it will be since this is a classic multi-page PHP app; closing before navigation just avoids a one-frame flash of the drawer closing is unnecessary here, but *do* still close it in case the browser serves the page from cache with the drawer mid-animation). No framework, no bundler — plain `document.addEventListener`/`querySelectorAll`.

- [ ] **Step 1: Write `public/assets/sidebar.js`**

  Exact behavior to implement (vanilla JS, wrapped in an IIFE or guarded by `DOMContentLoaded` the same way other inline scripts in this codebase are written — see `render_survey_fab()`'s script in `bootstrap.php` for the project's existing style convention):
  - Select the hamburger button(s) via `document.querySelectorAll('[data-hamburger-toggle]')` and the sidebar/backdrop via `document.querySelector('.sidebar')` / `document.querySelector('.sidebar-backdrop')`.
  - On hamburger click: toggle the `open` class on both the sidebar and the backdrop.
  - On backdrop click: remove the `open` class from both.
  - If no `.sidebar` or `.sidebar-backdrop` element exists on the page (defensive — shouldn't happen once rollout is complete, but guards against a page that hasn't been migrated yet mid-rollout), do nothing instead of throwing.

- [ ] **Step 2: Syntax-check the new file**

  Run: `node --check public/assets/sidebar.js`
  Expected: no output, exit code 0.

- [ ] **Step 3: Commit**
  ```bash
  git add public/assets/sidebar.js
  git commit -m "feat: add vanilla-JS hamburger/drawer toggle for mobile sidebar"
  ```

---

## Task 3: Shared shell helper + canonical nav lists in bootstrap.php, piloted on settings.php

**Files:**
- Modify: `bootstrap.php`
- Modify: `settings.php`

**Interfaces:**
- Consumes: CSS classes from Task 1, `public/assets/sidebar.js` from Task 2.
- Produces (pinned — every later task calls these exactly):
  ```php
  /** @return array<int,array{key:string,href:string,label:string,icon:string,target:string}> */
  function admin_nav_items(): array

  /** @param array<string,scalar> $queryParams e.g. ['year'=>2569] or ['year'=>2569,'dim'=>'agency_name']
   *  @return array<int,array{key:string,href:string,label:string,icon:string,target:string}> */
  function public_nav_items(array $queryParams): array

  /** @param array<int,array{key:string,href:string,label:string,icon:string,target:string}> $navItems */
  function render_app_shell_start(string $pageTitle, string $brandLabel, array $navItems, string $activeKey, string $extraCss = ''): void

  function render_app_shell_end(): void
  ```
  `target` is `'_self'` or `'_blank'`. `render_app_shell_start()` marks an item active by comparing `$item['key'] === $activeKey` — callers never pass a pre-computed `active` flag themselves.

- [ ] **Step 1: Define `admin_nav_items()` in `bootstrap.php`**

  Return exactly this list (union of every link currently shown across the 9 admin pages' topbars, minus the two page-specific contextual links which Task 8 handles separately — see spec decision #2 and this plan's Review Focus):
  ```php
  function admin_nav_items(): array
  {
      return [
          ['key' => 'dashboard',   'href' => 'index.php',               'label' => 'แดชบอร์ด',                       'icon' => '🏠', 'target' => '_self'],
          ['key' => 'review',      'href' => 'review.php',              'label' => 'รายการที่ต้องตรวจสอบ',            'icon' => '✅', 'target' => '_self'],
          ['key' => 'history',     'href' => 'uploads_history.php',     'label' => 'ประวัติการอัปโหลด',               'icon' => '📁', 'target' => '_self'],
          ['key' => 'schools',     'href' => 'schools_master.php',      'label' => 'ทำเนียบโรงเรียน',                 'icon' => '🏫', 'target' => '_self'],
          ['key' => 'population',  'href' => 'population_upload.php',   'label' => 'ข้อมูลประชากรรายอายุ',           'icon' => '👥', 'target' => '_self'],
          ['key' => 'search',      'href' => 'public_school_search.php','label' => 'ค้นหารหัสสถานศึกษา',             'icon' => '🔍', 'target' => '_blank'],
          ['key' => 'public_stats','href' => 'public_report.php',       'label' => 'สถิติการศึกษาจังหวัด (สาธารณะ)',  'icon' => '📊', 'target' => '_blank'],
          ['key' => 'settings',    'href' => 'settings.php',            'label' => 'ตั้งค่า',                         'icon' => '⚙️', 'target' => '_self'],
      ];
  }
  ```

- [ ] **Step 2: Define `public_nav_items(array $queryParams)` in `bootstrap.php`**

  Return exactly this list, appending `'?' . http_build_query($queryParams)` to every `href` (build the query string once at the top of the function and reuse it for all 7 items):
  ```php
  function public_nav_items(array $queryParams): array
  {
      $qs = http_build_query($queryParams);
      $items = [
          ['key' => 'overview',       'href' => 'public_report.php',              'label' => 'ภาพรวม',                'icon' => '📊'],
          ['key' => 'table',          'href' => 'public_report_table.php',        'label' => 'ตารางสรุปยอดรวม',        'icon' => '📋'],
          ['key' => 'grades',         'href' => 'public_school_grade_table.php',  'label' => 'ผู้เรียนรายชั้น',        'icon' => '🏫'],
          ['key' => 'teacher_grades', 'href' => 'public_teacher_grade_table.php', 'label' => 'ครูผู้สอน',              'icon' => '👨‍🏫'],
          ['key' => 'population',     'href' => 'public_population.php',         'label' => 'ประชากรวัยเรียน',        'icon' => '👥'],
          ['key' => 'search',         'href' => 'public_school_search.php',      'label' => 'ค้นหารหัสสถานศึกษา',     'icon' => '🔍'],
      ];
      foreach ($items as &$item) {
          $item['href'] .= '?' . $qs;
          $item['target'] = '_self';
      }
      unset($item);
      $items[] = ['key' => 'login', 'href' => 'login.php', 'label' => 'เข้าสู่ระบบเจ้าหน้าที่', 'icon' => '🔑', 'target' => '_self'];
      return $items;
  }
  ```

- [ ] **Step 3: Define `render_app_shell_start()`/`render_app_shell_end()` in `bootstrap.php`**

  `render_app_shell_start()` echoes, in order: `<!doctype html><html lang="th"><head>` with `<meta charset="utf-8">`, `<meta name="viewport" content="width=device-width, initial-scale=1">`, `<title><?= h($pageTitle) ?> — ptnedinfo</title>`, a Google Fonts `<link>` for Sarabun (`https://fonts.googleapis.com/css2?family=Sarabun:wght@400;600;700;800&display=swap`), `<link rel="stylesheet" href="public/assets/style.css">`, and — only if `$extraCss !== ''` — a second `<link rel="stylesheet" href="<?= h($extraCss) ?>">`; then `<script src="public/assets/sidebar.js" defer></script></head><body>`; then the mobile topbar (`<div class="mobile-topbar"><button class="hamburger-btn" data-hamburger-toggle aria-label="เปิดเมนู"><span></span><span></span><span></span></button><span><?= h($brandLabel) ?></span></div>`), the backdrop (`<div class="sidebar-backdrop"></div>`), then `<div class="app-shell"><nav class="sidebar"><div class="brand"><?= h($brandLabel) ?></div><nav>` followed by one `<a href="..." class="...">` per `$navItems` entry (icon in a `<span class="ic">`, `active` class added when `$item['key'] === $activeKey`, `target="_blank"` attribute included only when `$item['target'] === '_blank'`), then `</nav></nav><div class="app-main">` — leaving the `.app-main`/`.app-shell`/`<body>`/`<html>` tags open for the caller's content.

  `render_app_shell_end()` echoes the matching closers: `</div></div></body></html>` (closes `.app-main`, `.app-shell`, `<body>`, `<html>`) — no footer; callers that want one render their own after calling this, same as every page does today.

- [ ] **Step 4: Migrate `settings.php` to use the new shell (pilot)**

  Replace `settings.php`'s hand-written `<div class="topbar">...</div>` block (and its closing `</html>` at the bottom of the file) with:
  - A call to `render_app_shell_start('ตั้งค่าระบบ', 'ptnedinfo — ระบบรวบรวมข้อมูล', admin_nav_items(), 'settings')` where the page's `<body>` used to start.
  - Keep everything from `<div class="container">` down to the existing end-of-page content exactly as it is today — do not change `settings.php`'s own page logic or content.
  - Replace the trailing `</body></html>` with a call to `render_app_shell_end()`.
  - `settings.php` currently shows `<span class="muted"><?= h(Auth::displayName()) ?></span>` + a logout link inside its old topbar — since the new sidebar has no slot for this, add it as a small `<div>` at the bottom of the `.sidebar` (inside `render_app_shell_start()`'s own markup, right before the closing `</nav></div>` of the sidebar — i.e. this belongs in Step 3's template, not duplicated per page): `<div style="margin-top:auto; padding:10px 12px; font-size:12px; color:var(--muted);"><?= h(Auth::displayName()) ?><br><a href="logout.php">ออกจากระบบ</a></div>`, rendered only when `Auth::displayName()` doesn't throw/returns non-empty (wrap in the same pattern `Auth::displayName()` is used elsewhere — it's already called unconditionally in every admin page today, so no new guard is needed).

- [ ] **Step 5: Verify brace/paren/bracket balance on both changed files**

  Run the same Node one-liner from Task 1 Step 5, substituting `bootstrap.php` and `settings.php`. Record each file's `(`/`)`/`{`/`}` counts from `git show HEAD:bootstrap.php` and `git show HEAD:settings.php` (the pre-edit version) first, then confirm the post-edit delta is zero on all four counters (PHP files in this project do legitimately have non-zero-but-balanced Thai-comment parens sometimes — the check is "same imbalance as before," matching the established pattern from prior sessions in `ai_note.md`).

- [ ] **Step 6: Hand-verify the generated markup by tracing through it line by line**

  Confirm, by reading (not executing): `settings.php`'s nav shows exactly one `class="active"` item (`key === 'settings'`); the two items with `'target' => '_blank'` (`search`, `public_stats`) render `target="_blank"`, every other item does not; the username+logout block appears once, at the bottom of the sidebar; `</div></div></body></html>` closes everything `render_app_shell_start()` opened (count opened vs. closed tags by hand across both functions plus `settings.php`'s own content).

- [ ] **Step 7: Commit**
  ```bash
  git add bootstrap.php settings.php
  git commit -m "feat: add shared sidebar shell + canonical nav lists, pilot on settings.php"
  ```

---

## Task 4: Migrate the 3 shared-function public pages (public_report.php, public_report_table.php, public_population.php)

**Files:**
- Modify: `public_report_data.php:541-636` (the `render_report_start()`/`render_report_end()` functions — exact current line range, re-check before editing since earlier tasks don't touch this file)

**Interfaces:**
- Consumes: `render_app_shell_start()`/`render_app_shell_end()`/`public_nav_items()` from Task 3.
- Produces: `render_report_start(string $activePage)` / `render_report_end()` keep their existing signatures and existing callers (`public_report.php`, `public_report_table.php`, `public_population.php` need zero changes to their own files) — only the *implementation* of these two functions changes.

- [ ] **Step 1: Rewrite `render_report_start()`'s topbar block**

  Replace the hand-written `<!doctype html>...<div class="topbar">...</div><div class="container"...><div class="report-main"><div class="card hero-card">` opening with:
  - Build `$navItems = public_nav_items(['year' => $selectedYear, 'dim' => $selectedDimension]);` (reuses the function's existing `global $selectedYear, $selectedDimension;` — already declared).
  - Map `$activePage` (today's values: `'charts'`, `'table'`, `'population'` — grep the 3 callers to confirm the exact strings each passes) to the matching `public_nav_items()` key (`'overview'`, `'table'`, `'population'` respectively) before calling `render_app_shell_start('สถิติการศึกษาจังหวัดปัตตานี', 'ข้อมูลด้านการศึกษาจังหวัดปัตตานี', $navItems, $activeKey, 'public/assets/public_report.css')`.
  - Keep the `<div class="card hero-card">...</div>` block (badge, `<h1>`, description, filter form) exactly as it is today — it now renders *inside* the `.app-main` div that `render_app_shell_start()` already opened, so delete only the `<div class="container"...><div class="report-main">` wrapper tags that `render_app_shell_start()`'s `.app-main` now replaces.

- [ ] **Step 2: Rewrite `render_report_end()`'s closing block**

  Replace `</div></div><footer class="site-footer">...</footer></body></html>` with: keep the existing `<footer class="site-footer">...</footer>` markup exactly as-is (Task 1 already recolored `.site-footer` to the light palette; no markup change needed here), but replace the `</div></div>` before it and the `</body></html>` after it with a single call to `render_app_shell_end()` placed *after* the footer markup (since `render_app_shell_end()` closes `.app-main`/`.app-shell`/`<body>`/`<html>`, and the footer needs to render inside `.app-main` before those close — same nesting position it has today relative to `.container`/`.report-main`).

- [ ] **Step 3: Verify brace/paren/bracket balance** on `public_report_data.php` using the Task 1 Step 5 method.

- [ ] **Step 4: Hand-verify** that each of the 3 real callers (`public_report.php` passing `'charts'`, `public_report_table.php` passing `'table'`, `public_population.php` passing `'population'`) maps to a distinct, correct `$activeKey`, and that the year+dim query string is present on all 6 `public_nav_items()` links (not just some).

- [ ] **Step 5: Commit**
  ```bash
  git add public_report_data.php
  git commit -m "refactor: migrate public_report.php/_table.php/population.php to shared sidebar shell"
  ```

---

## Task 5: Migrate the 3 standalone public pages

**Files:**
- Modify: `public_school_grade_table.php`
- Modify: `public_teacher_grade_table.php`
- Modify: `public_school_search.php`

**Interfaces:**
- Consumes: `render_app_shell_start()`/`render_app_shell_end()`/`public_nav_items()` from Task 3.

- [ ] **Step 1: Migrate `public_school_grade_table.php`**
  - Replace its `<div class="topbar">...</div>` with `render_app_shell_start('ตารางผู้เรียนรายชั้น', 'ข้อมูลด้านการศึกษาจังหวัดปัตตานี', public_nav_items(['year' => $selectedYear]), 'grades', 'public/assets/public_report.css')`.
  - Keep this page's own `<div class="container" style="max-width: 98vw;"><div class="report-main">...` content as-is except deleting those now-redundant wrapper divs (replaced by `.app-main` from the shell) — same adjustment pattern as Task 4 Step 1.
  - Replace the closing `</div></div>` + the existing `<footer style="text-align:center;...">สำนักงานศึกษาธิการจังหวัดปัตตานี</footer>` + `</body></html>` with: keep the `<footer>` line exactly as-is, call `render_app_shell_end()` after it (same reasoning as Task 4 Step 2).

- [ ] **Step 2: Migrate `public_teacher_grade_table.php`** — identical pattern to Step 1, using its own page title, `'teacher_grades'` as the active key, and its own existing `$selectedYear`.

- [ ] **Step 3: Migrate `public_school_search.php`**
  - This page currently builds `$reportNavQuery` and mixes it inconsistently with bare `year=` across its 7 links (see spec's Current State section) — replace all of that with one call: `public_nav_items(['year' => $selectedYear])`, active key `'search'`.
  - Same wrapper-div and footer treatment as Steps 1-2.

- [ ] **Step 4: Verify brace/paren/bracket balance** on all 3 files using the Task 1 Step 5 method.

- [ ] **Step 5: Hand-verify** each page's `$activeKey` matches exactly one `public_nav_items()` entry, and that the `year=` query string survives on every link (this is the one Review Focus item most likely to regress in `public_school_search.php` specifically, since it previously built query strings three different ways across its own 7 links).

- [ ] **Step 6: Commit**
  ```bash
  git add public_school_grade_table.php public_teacher_grade_table.php public_school_search.php
  git commit -m "refactor: migrate standalone public pages to shared sidebar shell"
  ```

---

## Task 6: Migrate admin batch 1 (index.php, upload.php, review.php, uploads_history.php)

**Files:**
- Modify: `index.php`
- Modify: `upload.php`
- Modify: `review.php`
- Modify: `uploads_history.php`

**Interfaces:**
- Consumes: `render_app_shell_start()`/`render_app_shell_end()`/`admin_nav_items()` from Task 3.

- [ ] **Step 1: Migrate `index.php`**
  - Replace its topbar with: build `$navItems = admin_nav_items();` then override the one item needing the live count — `foreach ($navItems as &$item) { if ($item['key'] === 'review' && $needsReviewTotal > 0) { $item['label'] .= " ({$needsReviewTotal})"; } } unset($item);` — **this override lives in `index.php` only**, never inside `admin_nav_items()` itself (Review Focus item).
  - Call `render_app_shell_start('แดชบอร์ด', 'ptnedinfo — ระบบรวบรวมข้อมูล', $navItems, 'dashboard')`.
  - Keep page content as-is minus the redundant wrapper div, close with `render_app_shell_end()` (this page has no footer today — don't add one).

- [ ] **Step 2: Migrate `upload.php`** — same pattern, plain `admin_nav_items()` (no override), active key `'dashboard'`... wait: `upload.php` is reached *from* the dashboard's form grid, but it is not itself the dashboard — it has no matching canonical nav key of its own (it's accessed with a `?form=` query param for a specific form, not a standalone nav destination). Pass `''` as `$activeKey` (no item highlighted) rather than guessing a wrong one.

- [ ] **Step 3: Migrate `review.php`** — plain `admin_nav_items()`, active key `'review'`.

- [ ] **Step 4: Migrate `uploads_history.php`** — plain `admin_nav_items()`, active key `'history'`.

- [ ] **Step 5: Verify brace/paren/bracket balance** on all 4 files.

- [ ] **Step 6: Hand-verify** `index.php`'s count override appears only in `index.php`'s own code (grep the other 3 files in this task plus Task 3's `settings.php` and confirm `needsReviewTotal` does not appear in any of them); confirm each of the 4 pages' active key matches (or correctly omits, for `upload.php`) exactly one canonical item.

- [ ] **Step 7: Commit**
  ```bash
  git add index.php upload.php review.php uploads_history.php
  git commit -m "refactor: migrate dashboard/upload/review/history pages to shared sidebar shell"
  ```

---

## Task 7: Migrate admin batch 2 (schools_master.php, population_upload.php)

**Files:**
- Modify: `schools_master.php`
- Modify: `population_upload.php`

**Interfaces:**
- Consumes: `render_app_shell_start()`/`render_app_shell_end()`/`admin_nav_items()` from Task 3.

- [ ] **Step 1: Migrate `schools_master.php`** — plain `admin_nav_items()`, active key `'schools'`.

- [ ] **Step 2: Migrate `population_upload.php`** — plain `admin_nav_items()`, active key `'population'`.

- [ ] **Step 3: Verify brace/paren/bracket balance** on both files.

- [ ] **Step 4: Hand-verify** active keys as in prior tasks.

- [ ] **Step 5: Commit**
  ```bash
  git add schools_master.php population_upload.php
  git commit -m "refactor: migrate school roster/population upload pages to shared sidebar shell"
  ```

---

## Task 8: Migrate the 2 contextual admin pages, moving their page-specific links into content

**Files:**
- Modify: `view.php`
- Modify: `edit_submission.php`

**Interfaces:**
- Consumes: `render_app_shell_start()`/`render_app_shell_end()`/`admin_nav_items()` from Task 3.

- [ ] **Step 1: Migrate `view.php`**
  - Replace its topbar with `render_app_shell_start($formDef['form_label'], 'ptnedinfo — ระบบรวบรวมข้อมูล', admin_nav_items(), '')` (no canonical item matches "viewing a specific form's data," so no active key — same reasoning as `upload.php` in Task 6).
  - Its current topbar's second link, `<a href="upload.php?form=<?= urlencode($formKey) ?>">อัปโหลดไฟล์</a>` (upload more data for *this* form), is page-specific and must not vanish — add it as a plain `<a class="btn btn-secondary" href="upload.php?form=<?= urlencode($formKey) ?>">⬆️ อัปโหลดไฟล์เพิ่ม</a>` next to this page's existing `<h1><?= h($formDef['form_label']) ?></h1>` (inside the `.card` that already wraps the page's title).

- [ ] **Step 2: Migrate `edit_submission.php`**
  - Same shell call pattern, no active key.
  - Its current topbar's second link, `<a href="view.php?form=...&sheet=...">กลับไปดูข้อมูล</a>` (back to this form/sheet's data view), is page-specific — add it as a plain `<a class="btn btn-secondary" href="view.php?form=<?= urlencode($formKey) ?>&sheet=<?= urlencode($sheetName) ?>">← กลับไปดูข้อมูล</a>` next to this page's existing `<h1>แก้ไขข้อมูล</h1>`.

- [ ] **Step 3: Verify brace/paren/bracket balance** on both files.

- [ ] **Step 4: Hand-verify** both page-specific links still exist somewhere on their page (grep each file for `upload.php?form=` and `view.php?form=` respectively) and that neither page claims an active sidebar item it doesn't have.

- [ ] **Step 5: Commit**
  ```bash
  git add view.php edit_submission.php
  git commit -m "refactor: migrate view/edit-submission pages to shared sidebar shell, move contextual links into page content"
  ```

---

## Task 9: Fix login.php's missing viewport meta tag

**Files:**
- Modify: `login.php`

**Interfaces:**
- Consumes: nothing new (already loads `public/assets/style.css`, so Task 1's palette applies automatically — no sidebar needed, since there's nothing to navigate to before authenticating).

- [ ] **Step 1: Add the viewport meta tag**

  Add `<meta name="viewport" content="width=device-width, initial-scale=1">` to `login.php`'s `<head>`, next to its existing `<meta charset="utf-8">` (confirmed via grep this tag is absent today).

- [ ] **Step 2: Verify brace/paren/bracket balance** on `login.php` (should show zero delta — this is a one-line addition with no braces/parens involved).

- [ ] **Step 3: Commit**
  ```bash
  git add login.php
  git commit -m "fix: add missing viewport meta tag to login.php"
  ```

---

## Task 10: Final cleanup — remove dead CSS, verify full rollout, update ai_note.md

**Files:**
- Modify: `public/assets/style.css`
- Modify: `ai_note.md`

**Interfaces:**
- Consumes: nothing new — this task only verifies/cleans up after Tasks 1-9.

- [ ] **Step 1: Grep-verify no file still renders the old topbar markup**

  Run: `grep -rln 'class="topbar"' --include="*.php" .` (from the project root). Expected: no output (every one of the 13 original call sites was migrated in Tasks 3-8).

- [ ] **Step 2: Remove the now-dead `.topbar` CSS rule block from `public/assets/style.css`**

  Delete the `.topbar`, `.topbar a`, `.topbar > a:first-child`, `.topbar > a:first-child::before`, `.topbar nav`, `.topbar nav a`, `.topbar nav a:hover`, `.topbar nav a.active`, `.topbar nav .muted` rules (confirmed dead by Step 1's empty grep result).

- [ ] **Step 3: Grep-verify the two `target="_blank"` nav items survived everywhere**

  Run: `grep -c 'target="_blank"' index.php upload.php view.php edit_submission.php review.php uploads_history.php schools_master.php settings.php population_upload.php` and confirm every admin page shows exactly 2 (the `search`/`public_stats` items), except `upload.php`/`view.php`/`edit_submission.php` which also show 2 from `admin_nav_items()` plus their own unrelated links don't add any `_blank` (verify by eye that the count is exactly 2, not 0 or more).

- [ ] **Step 4: Verify brace/paren/bracket balance** on `public/assets/style.css` one more time (expect exact `(`=`)` and `{`=`}` after deletion, same as Task 1 Step 5's bar).

- [ ] **Step 5: Add a summary entry to `ai_note.md`**, following this project's existing documentation convention (see any of the dated `###` sections already in the file for the expected level of detail: what changed, why, and what was explicitly decided vs. left alone) — cover: the sidebar/hamburger nav replacing the topbar across all 13 original locations, the new Candy Pastel palette, the two "theirs" decisions confirmed with the user (unified admin nav list; dashboard-only pending-review badge), and the fact that none of this was testable locally (no PHP runtime), only hand-verified plus brace-balance checks, same as this whole project's history.

- [ ] **Step 6: Commit**
  ```bash
  git add public/assets/style.css ai_note.md
  git commit -m "chore: remove dead .topbar CSS, document sidebar redesign rollout in ai_note.md"
  ```
