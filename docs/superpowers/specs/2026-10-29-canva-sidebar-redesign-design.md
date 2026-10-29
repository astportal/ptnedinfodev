# Canva-Style Redesign + Left Sidebar Navigation

## Intent & why

The user wants the whole site restyled to look like canva.com — colorful,
friendly, rounded — and wants the current horizontal top navigation bar moved
to a left-hand sidebar (collapsing to a hamburger-triggered drawer on mobile).
This is a pure presentation/navigation-placement change: no data, computation,
or backend logic changes.

Confirmed with the user during brainstorming (see conversation):
- Scope is the **whole site** — both the 6 public report pages (no login) and
  the 9 admin/staff pages (login required).
- Visual direction, chosen from 3 mocked-up options and then validated in a
  closer-to-real mockup: **"Candy Pastel"** — white/light backgrounds, pastel
  rounded icon chips, friendly colorful cards, calmer than a full-saturation
  purple Canva skin but clearly colorful and "fun" compared to the current
  dark-green executive-dashboard theme.
- Mobile nav behavior: sidebar collapses behind a **hamburger button** (not a
  narrow icon-only rail) below the mobile breakpoint.

## Current state (verified 2026-10-29)

- `public/assets/style.css` is the one stylesheet every page in the system
  loads (admin + public). It currently implements the "dark green executive
  dashboard" theme added 2026-09-10: `--primary: #16a34a`, a dark-green
  gradient `.topbar` (with a `::before` emoji logo), dark-green `.hero-card`
  and `.site-footer`, and a 6-color pastel `.icon-badge` set (blue/green/
  pink/purple/orange/cyan) already used for stat icons.
- `public/assets/public_report.css` holds extras only the 6 public pages use:
  `.stat-grid`/`.stat-tile`, `.section-divider`, `.bar-chart`/`.bar-fill`,
  `.donut`, `.district-grid`, `.gender-bar` (uses `--series-1`/`--series-2`,
  a deliberately colorblind-safe blue/pink pair — see Constraints).
- **13 places** currently hand-write `<div class="topbar">…<nav>…</nav></div>`
  markup directly — there is no shared nav component today:
  - **Admin (9 separate copies, each with a slightly different subset of
    links):** `index.php` (7 links), `upload.php` (5), `view.php` (3),
    `edit_submission.php` (2), `review.php` (5), `uploads_history.php` (4),
    `schools_master.php` (5), `settings.php` (4), `population_upload.php` (7).
    This per-page variation looks like incidental drift from being added over
    many sessions, not an intentional design.
  - **Public, shared via one function (3 pages, 1 copy):**
    `public_report_data.php`'s `render_report_start()`/`render_report_end()`
    is called by `public_report.php`, `public_report_table.php`,
    `public_population.php`. This already shows a consistent 7-link menu
    with emoji icons and an `active` class per page.
  - **Public, standalone (3 separate copies, same 7-link list each):**
    `public_school_grade_table.php`, `public_teacher_grade_table.php`,
    `public_school_search.php`.
  - **Not in scope:** `login.php`/`logout.php` (no topbar today — login.php
    is a standalone pre-auth page with nothing to navigate to) and every
    `*_export.php`/`export.php`/`export_tidy.php` (CSV download endpoints,
    no HTML UI at all).
- `body { font-family: "Sarabun", "Noto Sans Thai", system-ui, ... }` is set
  but **"Sarabun" is never actually loaded** (no `<link>` to Google Fonts, no
  self-hosted `@font-face`) — it has silently fallen back to the OS default
  sans-serif the whole time.
- `bootstrap.php` is the one file every page (`require`s) already includes —
  `render_survey_fab()` (the floating feedback button) already lives there
  for exactly this "needs to reach every page" reason, and is a useful
  precedent for where new shared UI helpers belong.

## Goals

1. Every page that currently shows `.topbar` (all 13 locations above) shows
   the new left sidebar nav instead, consistently styled.
2. The sidebar nav markup is defined **once**, not duplicated per page.
3. Whole-site re-skin to the approved "Candy Pastel" direction: light
   backgrounds, pastel rounded icon chips (reusing the existing
   `.icon-badge` palette where possible), rounded cards/buttons, a purple
   brand accent for active/primary elements.
4. Sidebar is persistent (sticky down the left side) on desktop/tablet;
   collapses to an off-canvas drawer behind a `☰` hamburger button below a
   mobile breakpoint, using vanilla JS (no new dependency).
5. Zero change to any computation, query, or `src/*.php` reporting logic —
   this is HTML/CSS/nav-markup/light-JS only.

## Non-goals

- No changes to `forms/registry.php`, `src/Importer.php`, `src/Reporting.php`,
  or any data/DB logic.
- No redesign of page *content* structure (tables, charts, forms, filters)
  beyond what's needed to sit next to a sidebar instead of under a topbar.
- No new routes, no session/auth changes.
- `login.php` is not getting a sidebar (nothing to navigate to pre-login) —
  it may get the new color palette applied to its own card/button styling
  for visual consistency, but keeps its current standalone layout.
- CSV/export endpoints are untouched (they have no HTML UI).

## Anti-goals (would make this a failure even if it "runs")

- Breaking any existing feature (search, filters, upload, review queue,
  edit forms) while reskinning around it.
- A hamburger menu that doesn't actually open/close correctly on a real
  phone — this project has no PHP runtime on any dev machine (see
  Constraints), so this must be built carefully by direct code reading, and
  called out for extra attention once it's live on the dev site.
- Losing the deliberately colorblind-safe male/female chart color pairing
  (`--series-1` ~blue hue 210°, `--series-2` ~pink hue 340°, documented at
  length in `ai_note.md`) — the new palette may shift the exact shades but
  must preserve that hue separation.
- Making the admin pages (used daily by staff to upload/review data) more
  confusing or slower because of the nav change.

## Decisions already made (so the builder doesn't have to re-derive them)

1. **One shared nav/shell helper, added to `bootstrap.php`.** Every page
   (admin or public) calls it instead of hand-writing `<div class="topbar">`.
   It takes that page's nav item list (href, label, emoji icon, "is this the
   active page") and renders the sidebar + opens the main content wrapper;
   a matching "end" function closes the wrapper and renders the footer.
   `bootstrap.php` is the correct home because literally every page in the
   system already `require`s it (same reasoning as the existing
   `render_survey_fab()`).
2. **Admin pages get one consistent, full nav list**, not each page's current
   narrower subset. **Confirmed with the user.**
   - **The pending-review-count badge (`index.php`'s "(12)" next to "รายการ
     ที่ต้องตรวจสอบ") stays dashboard-only, as today — confirmed with the
     user.** This needs no special feature in the shared helper: each page
     builds its own nav-items array before calling the helper, so
     `index.php` keeps interpolating its own count into its own copy of
     that one label (exactly as it does today); every other page just
     passes the plain label with no count. No new query runs anywhere
     except where it already does.
3. **Public nav keeps today's 7 links** (Overview, Summary table, Students by
   grade, Teachers by grade, School-age population, School code search,
   Staff login) — just repositioned into the sidebar, same icons.
4. **Layout:** `<body>` contains a flex row: `.sidebar` (fixed width, sticky
   down the left) + `.app-main` (today's `.container`/`.card` content,
   otherwise unchanged). This replaces the current sticky-horizontal-topbar
   pattern with a sticky-vertical-sidebar pattern.
5. **Mobile breakpoint:** pick one breakpoint (~880px) below which `.sidebar`
   becomes an off-canvas drawer (`transform: translateX(-100%)` when closed),
   a slim top bar with a `☰` button appears, and plain inline JS toggles a
   class to slide the drawer in with a dimmed backdrop (same vanilla-JS,
   no-dependency style as the existing survey-fab script). Clicking the
   backdrop or a nav link closes the drawer.
6. **Color palette ("Candy Pastel"):**
   - Light backgrounds (white sidebar + very-light-lavender main area)
     replacing the current dark-green gradients on `.topbar`/`.hero-card`/
     `.site-footer`.
   - New brand accent: purple (used for active nav state, primary buttons,
     links) — replacing `--primary: #16a34a` as the "default" accent.
     `--primary` is currently reused for ~20 unrelated things (table header
     tint, progress bars, chart "brand" bar fill, badges) — the builder
     should audit each usage rather than assume a single find/replace is
     safe (see "Left to the builder").
   - Reuse the existing 6-color `.icon-badge` pastel set for sidebar icon
     chips and stat tiles instead of inventing a 7th palette.
   - `--series-1`/`--series-2` (gender chart colors): keep the hue
     separation; shades may shift slightly to match the new palette's
     pastel feel.
7. **Typography:** load "Sarabun" properly via a Google Fonts `<link>`
   (weights 400/600/700/800, Thai + Latin subsets) — fixing the existing
   silent-fallback bug as part of this work, from wherever `<head>` is now
   centralized (see shared shell helper above).
8. **No new JS libraries, no build step, no icon font.** Vanilla JS for the
   hamburger toggle; emoji for icons (the public nav already does this —
   `📊 📋 🏫 👨‍🏫 👥 🔍 🔑` — extend the same convention to admin nav icons).
9. **Add `<meta name="viewport" content="width=device-width, initial-scale=1">`
   to the 9 admin pages + `login.php`, which are all missing it today**
   (verified: only the 6 public pages have it). Without it, mobile browsers
   render these pages at a fixed desktop-width virtual viewport, so the
   mobile breakpoint/hamburger drawer (goal #4, required site-wide per goal
   #1) cannot engage on a real phone no matter how the CSS is written. This
   is required for the stated goals to work at all, not a style nicety —
   settled, not left open.
10. **`--series-brand` (the general, non-gender bar-chart fill color in
    `public_report.css`) tracks the new brand accent.** It was split out
    from `--series-1`/`--series-2` on 2026-09-10 specifically so the general
    chart color wouldn't be *coupled to the gender-specific colors* — the
    variable is literally named "brand," i.e. it was always meant to follow
    whatever the site's brand/primary color is. Recolor it to the new
    purple accent (or a Candy-Pastel variant of it) along with `--primary`;
    only `--series-1`/`--series-2` (gender colors) are protected.
11. **Rollout order recommendation:** build the shared shell helper +
   both CSS files first, prove it on one admin page + the 3
   shared-function public pages + one standalone public page, then roll the
   same helper call to the remaining 8 files. Smaller diff to debug if
   something's structurally wrong, given there's no local PHP to test with.
10. **Target branch:** land this on `develop` first (per the project's
    existing deploy workflow in `ai_note.md`) so it's visible on the dev
    site before any `main`/production merge is discussed.

## Constraints (from this project's documented history — see `ai_note.md`)

- **No PHP runtime on any dev machine used for this project.** All PHP must
  be hand-verified (brace/paren balance, logic re-read) — nothing can be
  executed locally before it's deployed to the dev server.
- The project folder syncs through OneDrive, which flips file permission
  bits (644↔755) in the background unrelated to content changes — before
  `git add`, check `git diff --summary -- <file>` for mode-only changes on
  files not intentionally touched, and `chmod 644` them back rather than
  letting a mode-only change ride into a commit.
- `git commit`/`git push` occasionally fail with an "Invalid argument" error
  against `.git/logs/` because OneDrive is mid-sync-locking the file —
  re-running the same command resolves it. Never change git config to work
  around this (`core.fileMode false`, `windows.appendAtomically false`, etc.
  are explicitly off-limits).
- `main` branch git operations (commit, reset --hard) are auto-blocked by
  the permission system and need the user's explicit in-chat go-ahead —
  this design's work should stay on `develop` and not need that at all.

## Left to the builder

- Exact hex values beyond "purple brand accent, reuse the existing 6
  `.icon-badge` pastels" — pick values with adequate text contrast; match
  the saturation/lightness feel shown in the approved mockup rather than a
  pixel-exact spec (none was given at that fidelity).
- Exact sidebar width/padding/icon sizing — mockup proportions are a guide,
  not a spec.
- Whether `<head>` boilerplate (viewport meta, per-page `<title>`, CSS
  `<link>`s, the new font `<link>`) gets centralized into the shared shell
  helper, or the helper only replaces the `<body>`-level topbar/nav markup
  and each file keeps its own `<head>`. Recommend centralizing it if it
  doesn't fight each page's distinct `<title>` — but either is acceptable.
- The precise mobile breakpoint value (~880px suggested, not mandated) and
  drawer animation timing/easing.
- Whether `.table-scroll`/`.report-table-scroll`'s documented sticky-header
  CSS (see `ai_note.md`, "ปักหมุดเมนู (topbar)...") interacts badly with a
  sticky/fixed sidebar's stacking context — re-read that history before
  assuming it's unaffected, and verify visually once on the dev site.

## Open questions — resolved

Both questions raised during review were put to the user and answered; see
decision #2 above for both resolutions (unified admin nav list;
dashboard-only pending-review badge, no new query needed). Nothing is left
open for the user — the remaining items are the builder's to decide (see
"Left to the builder" above).
