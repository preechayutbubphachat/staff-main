# PROJECT_STATUS.md

> à¹„à¸Ÿà¸¥à¹Œà¸™à¸µà¹‰à¹€à¸›à¹‡à¸™à¹€à¸­à¸à¸ªà¸²à¸£à¸ªà¸–à¸²à¸™à¸°à¸à¸¥à¸²à¸‡à¸‚à¸­à¸‡à¹‚à¸›à¸£à¹€à¸ˆà¸à¸•à¹Œà¸ªà¸³à¸«à¸£à¸±à¸šà¹ƒà¸«à¹‰ AI Codex / à¸™à¸±à¸à¸žà¸±à¸’à¸™à¸² / à¸œà¸¹à¹‰à¸”à¸¹à¹à¸¥à¸£à¸°à¸šà¸š à¸­à¹ˆà¸²à¸™à¸à¹ˆà¸­à¸™à¹€à¸£à¸´à¹ˆà¸¡à¸‡à¸²à¸™à¸—à¸¸à¸à¸„à¸£à¸±à¹‰à¸‡ à¹à¸¥à¸°à¸•à¹‰à¸­à¸‡à¸­à¸±à¸›à¹€à¸”à¸•à¸«à¸¥à¸±à¸‡à¸—à¸³à¸‡à¸²à¸™à¹€à¸ªà¸£à¹‡à¸ˆà¸—à¸¸à¸à¸„à¸£à¸±à¹‰à¸‡

---

## Deployment / Git Status

- Active branch: `codex/strict-monthly-matrix-mapping`
- Rule: All Codex work must stay on this branch unless instructed otherwise.
- Latest task: Build Tailwind/frontend and push UI fixes.
- Build command used: `npm ci`; `npm run build:tailwind`
- Deployment target: Plesk
- Plesk issue checked:
  - branch mapping: must point to `codex/strict-monthly-matrix-mapping`
  - document root: must point to the live PHP project root that serves `index.php`
  - frontend build output: `assets/css/index-tailwind.css` and `assets/css/dashboard-tailwind.output.css`
  - cache clearing: clear browser/server cache and restart PHP-FPM/OPcache if stale assets remain
  - server pull status: server must show the latest commit from `codex/strict-monthly-matrix-mapping`
- Files changed:
  - UI/PHP/CSS/JS files in the current UI update set
  - `scripts/deploy-plesk.sh`
  - `PROJECT_STATUS.md`
- Verification:
  - local build passed with `npm run build:tailwind`
  - pushed to origin branch after local verification
  - server updated: pending manual Plesk deploy confirmation
- Next action:
  - On Plesk, run `scripts/deploy-plesk.sh` or the equivalent fetch/pull/build commands, then hard refresh the browser.

---

## 1. Project Overview

**à¸Šà¸·à¹ˆà¸­à¹‚à¸›à¸£à¹€à¸ˆà¸à¸•à¹Œ:** StaffMain / à¸£à¸°à¸šà¸šà¸¥à¸‡à¹€à¸§à¸¥à¸²à¹€à¸§à¸£à¹‚à¸£à¸‡à¸žà¸¢à¸²à¸šà¸²à¸¥à¸«à¸™à¸­à¸‡à¸žà¸­à¸
**à¸›à¸£à¸°à¹€à¸ à¸—à¸£à¸°à¸šà¸š:** Hospital Staff Attendance / Shift Management / Review & Reporting System
**Stack à¸«à¸¥à¸±à¸:** PHP, MariaDB/MySQL, HTML, CSS, JavaScript
**Environment à¸«à¸¥à¸±à¸:** XAMPP local à¹à¸¥à¸° production server à¸œà¹ˆà¸²à¸™ Git pull
**à¹€à¸›à¹‰à¸²à¸«à¸¡à¸²à¸¢à¸£à¸°à¸šà¸š:**
à¸£à¸°à¸šà¸šà¸ªà¸³à¸«à¸£à¸±à¸šà¹€à¸ˆà¹‰à¸²à¸«à¸™à¹‰à¸²à¸—à¸µà¹ˆà¹‚à¸£à¸‡à¸žà¸¢à¸²à¸šà¸²à¸¥à¹ƒà¸Šà¹‰à¸¥à¸‡à¹€à¸§à¸¥à¸²à¹€à¸§à¸£ à¸•à¸£à¸§à¸ˆà¸ªà¸­à¸šà¸£à¸²à¸¢à¸à¸²à¸£à¹€à¸§à¸£ à¸£à¸²à¸¢à¸‡à¸²à¸™à¸‚à¹‰à¸­à¸¡à¸¹à¸¥ à¹à¸¥à¸°à¹ƒà¸«à¹‰à¸œà¸¹à¹‰à¸”à¸¹à¹à¸¥à¸£à¸°à¸šà¸šà¸ˆà¸±à¸”à¸à¸²à¸£à¸‚à¹‰à¸­à¸¡à¸¹à¸¥à¸œà¸¹à¹‰à¹ƒà¸Šà¹‰/à¹€à¸§à¸£/à¸ªà¸´à¸—à¸˜à¸´à¹Œà¸•à¹ˆà¸²à¸‡ à¹† à¹„à¸”à¹‰à¸ˆà¸²à¸à¸«à¸™à¹‰à¸²à¹€à¸§à¹‡à¸šà¹€à¸”à¸µà¸¢à¸§à¸à¸±à¸™à¸­à¸¢à¹ˆà¸²à¸‡à¸›à¸¥à¸­à¸”à¸ à¸±à¸¢ à¸•à¸£à¸§à¸ˆà¸ªà¸­à¸šà¸¢à¹‰à¸­à¸™à¸«à¸¥à¸±à¸‡à¹„à¸”à¹‰ à¹à¸¥à¸°à¹ƒà¸Šà¹‰à¸‡à¸²à¸™à¸‡à¹ˆà¸²à¸¢

---

## 2. Current Design Direction

à¸£à¸°à¸šà¸šà¸à¸³à¸¥à¸±à¸‡à¸–à¸¹à¸à¸›à¸£à¸±à¸š UI/UX à¹„à¸›à¹ƒà¸™à¹à¸™à¸§:

- Modern hospital admin dashboard
- Side navigation layout
- Light blue / mint soft background
- Dark navy hero banner
- Glass cards
- Rounded cards
- Subtle shadow
- Clean spacing
- Thai typography à¸­à¹ˆà¸²à¸™à¸‡à¹ˆà¸²à¸¢
- KPI cards à¸Šà¸±à¸”à¹€à¸ˆà¸™
- Table à¹ƒà¸Šà¹‰à¸‡à¸²à¸™à¸ˆà¸£à¸´à¸‡ à¸­à¹ˆà¸²à¸™à¸‡à¹ˆà¸²à¸¢
- Icon badge à¸•à¹‰à¸­à¸‡à¸­à¸¢à¸¹à¹ˆà¸à¸¶à¹ˆà¸‡à¸à¸¥à¸²à¸‡à¸§à¸‡à¸à¸¥à¸¡à¹€à¸ªà¸¡à¸­
- Layout à¸•à¹‰à¸­à¸‡à¹€à¸«à¸¡à¸²à¸°à¸à¸±à¸šà¸‡à¸²à¸™ back-office à¹‚à¸£à¸‡à¸žà¸¢à¸²à¸šà¸²à¸¥ à¹„à¸¡à¹ˆà¹ƒà¸Šà¹ˆà¹à¸„à¹ˆà¸ªà¸§à¸¢

---

## 3. Core Layout Standard

à¸—à¸¸à¸à¸«à¸™à¹‰à¸²à¸«à¸¥à¸±à¸‡à¸šà¹‰à¸²à¸™à¸„à¸§à¸£à¹ƒà¸Šà¹‰à¹‚à¸„à¸£à¸‡à¸ªà¸£à¹‰à¸²à¸‡à¹ƒà¸à¸¥à¹‰à¹€à¸„à¸µà¸¢à¸‡à¸à¸±à¸™:

1. **Sidebar**
   - à¸­à¸¢à¸¹à¹ˆà¸”à¹‰à¸²à¸™à¸‹à¹‰à¸²à¸¢
   - à¹à¸ªà¸”à¸‡à¹‚à¸¥à¹‚à¸à¹‰à¸£à¸°à¸šà¸š
   - à¹à¸ªà¸”à¸‡à¹€à¸¡à¸™à¸¹à¸•à¸²à¸¡à¸ªà¸´à¸—à¸˜à¸´à¹Œà¸œà¸¹à¹‰à¹ƒà¸Šà¹‰
   - active menu à¸•à¹‰à¸­à¸‡à¸Šà¸±à¸”à¹€à¸ˆà¸™
   - user card + logout à¸­à¸¢à¸¹à¹ˆà¸”à¹‰à¸²à¸™à¸¥à¹ˆà¸²à¸‡

2. **Topbar**
   - à¸­à¸¢à¸¹à¹ˆà¸”à¹‰à¸²à¸™à¸šà¸™à¸‚à¸­à¸‡ content
   - à¹à¸ªà¸”à¸‡à¸Šà¸·à¹ˆà¸­ workspace/page
   - à¸¡à¸µà¸Šà¹ˆà¸­à¸‡à¸„à¹‰à¸™à¸«à¸²
   - notification
   - profile compact

3. **Hero Banner**
   - dark navy gradient
   - title à¹ƒà¸«à¸à¹ˆ
   - subtitle à¸ªà¸±à¹‰à¸™
   - meta chips
   - KPI/summary à¸à¸±à¹ˆà¸‡à¸‚à¸§à¸²à¸«à¸£à¸·à¸­à¸à¸¥à¸²à¸‡
   - action buttons à¸à¸±à¹ˆà¸‡à¸‚à¸§à¸²

4. **KPI Cards**
   - 4 cards à¸•à¹ˆà¸­à¹à¸–à¸§à¸šà¸™ desktop
   - 2 cards à¸šà¸™ tablet
   - 1 card à¸šà¸™ mobile

5. **Main Content Grid**
   - à¸«à¸™à¹‰à¸²à¸—à¸µà¹ˆà¸¡à¸µ filter + table à¹ƒà¸Šà¹‰ grid:
     - left filter panel
     - right table/list panel
   - à¸«à¸¥à¸µà¸à¹€à¸¥à¸µà¹ˆà¸¢à¸‡à¸„à¸§à¸²à¸¡à¸ªà¸¹à¸‡à¹€à¸à¸´à¸™à¸ˆà¸­à¸–à¹‰à¸²à¹€à¸›à¹‡à¸™ dashboard
   - à¸•à¸²à¸£à¸²à¸‡à¸¢à¸²à¸§à¹ƒà¸«à¹‰ scroll à¸ à¸²à¸¢à¹ƒà¸™ table container à¹„à¸¡à¹ˆà¹ƒà¸Šà¹ˆà¸”à¸±à¸™à¸—à¸±à¹‰à¸‡à¸«à¸™à¹‰à¸²à¸ˆà¸™à¹€à¸ªà¸µà¸¢ layout

6. **Bottom Summary Bar**
   - à¹ƒà¸Šà¹‰à¹€à¸›à¹‡à¸™à¸ªà¸£à¸¸à¸›à¸‚à¹‰à¸­à¸¡à¸¹à¸¥à¸ªà¸³à¸„à¸±à¸à¸‚à¸­à¸‡à¸«à¸™à¹‰à¸²à¸™à¸±à¹‰à¸™
   - à¹à¸šà¹ˆà¸‡à¹€à¸›à¹‡à¸™à¸Šà¹ˆà¸­à¸‡ à¹† à¸žà¸£à¹‰à¸­à¸¡ progress bar à¸–à¹‰à¸²à¸¡à¸µà¸‚à¹‰à¸­à¸¡à¸¹à¸¥à¸„à¸§à¸²à¸¡à¸„à¸·à¸šà¸«à¸™à¹‰à¸²

---

## 4. Pages Already Designed / In Progress

| Page | Status | Notes |
|---|---|---|
| Dashboard | Designed / Implemented partially | à¸›à¸£à¸±à¸šà¹€à¸›à¹‡à¸™ side nav + card dashboard à¹à¸¥à¹‰à¸§ |
| à¸¥à¸‡à¹€à¸§à¸¥à¸²à¹€à¸§à¸£ | Designed / Implemented close to target | à¸•à¹‰à¸­à¸‡à¸„à¸¸à¸¡ hero, KPI, form, history list |
| à¸•à¸£à¸§à¸ˆà¸ªà¸­à¸šà¹€à¸§à¸£ | Designed / Implemented close to target | à¹€à¸«à¸¥à¸·à¸­à¹€à¸à¹‡à¸š alignment icon à¹à¸¥à¸° spacing |
| à¹€à¸§à¸£à¸§à¸±à¸™à¸™à¸µà¹‰ | Designed | à¸•à¹‰à¸­à¸‡à¸„à¸‡ style à¹€à¸”à¸µà¸¢à¸§à¸à¸±à¸™ |
| à¸£à¸²à¸¢à¸‡à¸²à¸™à¸‚à¸­à¸‡à¸‰à¸±à¸™ | Designed | à¹ƒà¸Šà¹‰ report dashboard style |
| à¸£à¸²à¸¢à¸‡à¸²à¸™à¹à¸œà¸™à¸ | Designed | à¹ƒà¸Šà¹‰ department report dashboard style |
| à¹‚à¸›à¸£à¹„à¸Ÿà¸¥à¹Œ | Designed | à¸•à¹‰à¸­à¸‡à¸„à¸‡ style dashboard à¹à¸¥à¸°à¸ˆà¸±à¸” form à¹ƒà¸«à¹‰à¹ƒà¸Šà¹‰à¸‡à¹ˆà¸²à¸¢ |
| à¸ˆà¸±à¸”à¸à¸²à¸£à¸¥à¸‡à¹€à¸§à¸¥à¸²à¹€à¸§à¸£ | In Progress | à¸¢à¸±à¸‡à¸¡à¸µà¸›à¸±à¸à¸«à¸² layout height, hero, filter, table à¹„à¸¡à¹ˆà¹€à¸«à¸¡à¸·à¸­à¸™ target |
| à¸ˆà¸±à¸”à¸à¸²à¸£à¸œà¸¹à¹‰à¹ƒà¸Šà¹‰à¸‡à¸²à¸™ | Next / Requested | à¸•à¹‰à¸­à¸‡à¸­à¸­à¸à¹à¸šà¸šà¸•à¸²à¸¡ Admin User Management layout |

---

## 5. Current Active Task

**Task à¸¥à¹ˆà¸²à¸ªà¸¸à¸”:** à¸›à¸£à¸±à¸šà¸«à¸™à¹‰à¸² `à¸ˆà¸±à¸”à¸à¸²à¸£à¸œà¸¹à¹‰à¹ƒà¸Šà¹‰à¸‡à¸²à¸™` à¹ƒà¸«à¹‰à¹€à¸›à¹‡à¸™ Admin User Management dashboard

**à¹€à¸›à¹‰à¸²à¸«à¸¡à¸²à¸¢:**
- à¹ƒà¸Šà¹‰ layout à¹€à¸«à¸¡à¸·à¸­à¸™à¸ à¸²à¸žà¹€à¸›à¹‰à¸²à¸«à¸¡à¸²à¸¢à¸¥à¹ˆà¸²à¸ªà¸¸à¸”
- à¹€à¸›à¹‡à¸™à¸«à¸™à¹‰à¸² admin à¸ªà¸³à¸«à¸£à¸±à¸šà¸ˆà¸±à¸”à¸à¸²à¸£ user à¸ˆà¸£à¸´à¸‡
- à¹„à¸¡à¹ˆà¹ƒà¸Šà¹ˆà¸«à¸™à¹‰à¸²à¸£à¸²à¸¢à¸‡à¸²à¸™à¹à¸œà¸™à¸
- à¸„à¸‡ backend logic à¹€à¸”à¸´à¸¡
- à¸„à¸‡ form/action/input name à¹€à¸”à¸´à¸¡
- à¸›à¸£à¸±à¸šà¹€à¸‰à¸žà¸²à¸° UI/UX, HTML structure, CSS class, responsive à¹€à¸—à¹ˆà¸²à¸—à¸µà¹ˆà¸ˆà¸³à¹€à¸›à¹‡à¸™

**à¸ªà¹ˆà¸§à¸™à¸›à¸£à¸°à¸à¸­à¸šà¸—à¸µà¹ˆà¸•à¹‰à¸­à¸‡à¸¡à¸µ:**
- Hero: â€œà¸ˆà¸±à¸”à¸à¸²à¸£à¸œà¸¹à¹‰à¹ƒà¸Šà¹‰à¸‡à¸²à¸™â€
- KPI hero metrics:
  - à¸ˆà¸³à¸™à¸§à¸™à¸œà¸¹à¹‰à¹ƒà¸Šà¹‰à¸—à¸±à¹‰à¸‡à¸«à¸¡à¸”
  - à¸šà¸—à¸šà¸²à¸—à¹ƒà¸™à¸£à¸°à¸šà¸š
  - à¹ƒà¸Šà¹‰à¸‡à¸²à¸™à¸­à¸¢à¸¹à¹ˆ
  - à¸£à¸­à¸­à¸™à¸¸à¸¡à¸±à¸•à¸´/à¸£à¸°à¸‡à¸±à¸š
- KPI cards à¹ƒà¸•à¹‰ hero
- Filter card à¸‹à¹‰à¸²à¸¢
- User table card à¸‚à¸§à¸²
- Bottom summary bar
- Action buttons: à¹€à¸žà¸´à¹ˆà¸¡à¸œà¸¹à¹‰à¹ƒà¸Šà¹‰à¸‡à¸²à¸™, à¸”à¸¹à¸›à¸£à¸°à¸§à¸±à¸•à¸´à¸ªà¸´à¸—à¸˜à¸´à¹Œ, à¹à¸à¹‰à¹„à¸‚à¸‚à¹‰à¸­à¸¡à¸¹à¸¥, à¸”à¸¹à¸£à¸²à¸¢à¸¥à¸°à¹€à¸­à¸µà¸¢à¸”, export

---

## 6. Known Issues / Bugs

à¹ƒà¸«à¹‰ Codex à¸•à¸£à¸§à¸ˆà¸—à¸¸à¸à¸„à¸£à¸±à¹‰à¸‡à¸à¹ˆà¸­à¸™à¹à¸à¹‰:

### UI / Layout
- à¸šà¸²à¸‡à¸«à¸™à¹‰à¸²à¸„à¸§à¸²à¸¡à¸ªà¸¹à¸‡à¹€à¸à¸´à¸™à¸ˆà¸­à¹‚à¸”à¸¢à¹„à¸¡à¹ˆà¸ˆà¸³à¹€à¸›à¹‡à¸™
- filter panel à¸šà¸²à¸‡à¸«à¸™à¹‰à¸²à¸ªà¸¹à¸‡à¹€à¸à¸´à¸™à¹à¸¥à¸°à¸”à¸±à¸™ content
- hero à¸šà¸²à¸‡à¸«à¸™à¹‰à¸²à¸à¸´à¸™à¸žà¸·à¹‰à¸™à¸—à¸µà¹ˆà¸¡à¸²à¸à¹€à¸à¸´à¸™à¹„à¸›
- icon à¹ƒà¸™à¸§à¸‡à¸à¸¥à¸¡à¸ªà¸µà¸šà¸²à¸‡à¸ˆà¸¸à¸”à¹„à¸¡à¹ˆà¸­à¸¢à¸¹à¹ˆà¸à¸¶à¹ˆà¸‡à¸à¸¥à¸²à¸‡
- à¸›à¸¸à¹ˆà¸¡à¸šà¸²à¸‡à¸«à¸™à¹‰à¸²à¸”à¸¹à¹€à¸«à¸¡à¸·à¸­à¸™ disabled à¸«à¸£à¸·à¸­à¸à¸”à¹„à¸¡à¹ˆà¸Šà¸±à¸”
- table à¸šà¸²à¸‡à¸«à¸™à¹‰à¸²à¸à¸§à¹‰à¸²à¸‡/à¸ªà¸¹à¸‡à¹€à¸à¸´à¸™à¹à¸¥à¸°à¸—à¸³à¹ƒà¸«à¹‰ layout à¹€à¸ªà¸µà¸¢
- à¸šà¸²à¸‡à¸«à¸™à¹‰à¸²à¹ƒà¸Šà¹‰ top nav à¹€à¸”à¸´à¸¡à¹à¸—à¸™ side nav
- à¸šà¸²à¸‡à¸«à¸™à¹‰à¸² copy layout à¸œà¸´à¸”à¸›à¸£à¸°à¹€à¸ à¸— à¹€à¸Šà¹ˆà¸™ admin page à¸à¸¥à¸²à¸¢à¹€à¸›à¹‡à¸™ report page

### Backend / PHP / SQL
- à¹€à¸„à¸¢à¹€à¸ˆà¸­à¸›à¸±à¸à¸«à¸² MariaDB syntax error à¸ˆà¸²à¸à¸à¸²à¸£ bind `LIMIT` / `OFFSET` à¹€à¸›à¹‡à¸™ string à¹€à¸Šà¹ˆà¸™ `'20' OFFSET '0'`
- à¸–à¹‰à¸²à¹€à¸ˆà¸­ query pagination à¹ƒà¸«à¹‰ cast à¹€à¸›à¹‡à¸™ integer à¹à¸¥à¸° bind à¸”à¹‰à¸§à¸¢ `PDO::PARAM_INT`
- à¸«à¹‰à¸²à¸¡à¹à¸à¹‰ SQL logic à¹‚à¸”à¸¢à¹„à¸¡à¹ˆà¸ˆà¸³à¹€à¸›à¹‡à¸™
- à¸«à¹‰à¸²à¸¡à¸¥à¸š permission check

---

## 7. Coding Rules for Codex

à¸à¹ˆà¸­à¸™à¹à¸à¹‰:
1. à¸­à¹ˆà¸²à¸™à¹„à¸Ÿà¸¥à¹Œà¸™à¸µà¹‰à¸à¹ˆà¸­à¸™à¸—à¸¸à¸à¸„à¸£à¸±à¹‰à¸‡
2. à¸•à¸£à¸§à¸ˆ route/page à¸—à¸µà¹ˆà¸œà¸¹à¹‰à¹ƒà¸Šà¹‰à¸£à¸°à¸šà¸¸à¹ƒà¸«à¹‰à¸Šà¸±à¸”
3. à¸«à¹‰à¸²à¸¡à¹€à¸”à¸²à¸§à¹ˆà¸²à¹€à¸›à¹‡à¸™à¸«à¸™à¹‰à¸²à¸­à¸·à¹ˆà¸™
4. à¸„à¹‰à¸™à¸«à¸²à¹„à¸Ÿà¸¥à¹Œà¸—à¸µà¹ˆà¹€à¸à¸µà¹ˆà¸¢à¸§à¸‚à¹‰à¸­à¸‡à¸à¹ˆà¸­à¸™à¹à¸à¹‰
5. à¸ªà¸£à¸¸à¸›à¹à¸œà¸™à¸ªà¸±à¹‰à¸™ à¹† à¸à¹ˆà¸­à¸™à¸¥à¸‡à¸¡à¸·à¸­à¸–à¹‰à¸² task à¹ƒà¸«à¸à¹ˆ

à¸£à¸°à¸«à¸§à¹ˆà¸²à¸‡à¹à¸à¹‰:
1. à¹à¸à¹‰à¹€à¸‰à¸žà¸²à¸°à¹„à¸Ÿà¸¥à¹Œà¸—à¸µà¹ˆà¹€à¸à¸µà¹ˆà¸¢à¸§à¸‚à¹‰à¸­à¸‡
2. à¸«à¹‰à¸²à¸¡à¸¥à¸š backend logic à¹€à¸”à¸´à¸¡
3. à¸«à¹‰à¸²à¸¡à¹€à¸›à¸¥à¸µà¹ˆà¸¢à¸™à¸Šà¸·à¹ˆà¸­ input name à¸—à¸µà¹ˆ PHP à¹ƒà¸Šà¹‰à¸­à¸¢à¸¹à¹ˆ
4. à¸«à¹‰à¸²à¸¡ hardcode data à¸–à¹‰à¸²à¸¡à¸µà¸•à¸±à¸§à¹à¸›à¸£à¸ˆà¸£à¸´à¸‡à¸­à¸¢à¸¹à¹ˆà¹à¸¥à¹‰à¸§
5. à¹ƒà¸Šà¹‰ class à¹€à¸‰à¸žà¸²à¸°à¸«à¸™à¹‰à¸²à¹€à¸žà¸·à¹ˆà¸­à¸¥à¸”à¸œà¸¥à¸à¸£à¸°à¸—à¸šà¸‚à¹‰à¸²à¸¡à¸«à¸™à¹‰à¸²
6. à¸—à¸³ responsive à¸”à¹‰à¸§à¸¢
7. à¸›à¸¸à¹ˆà¸¡à¸•à¹‰à¸­à¸‡à¸¡à¸µ hover/focus/active states
8. icon badge à¸•à¹‰à¸­à¸‡à¹ƒà¸Šà¹‰ flex center à¹€à¸ªà¸¡à¸­

à¸«à¸¥à¸±à¸‡à¹à¸à¹‰:
1. à¸•à¸£à¸§à¸ˆ PHP syntax
2. à¸•à¸£à¸§à¸ˆ console error
3. à¸•à¸£à¸§à¸ˆ responsive desktop/tablet/mobile
4. à¸•à¸£à¸§à¸ˆà¸§à¹ˆà¸²à¸›à¸¸à¹ˆà¸¡à¹à¸¥à¸° form à¸¢à¸±à¸‡ submit à¹„à¸”à¹‰
5. à¸•à¸£à¸§à¸ˆà¸§à¹ˆà¸² permission à¸¢à¸±à¸‡à¸—à¸³à¸‡à¸²à¸™
6. à¸­à¸±à¸›à¹€à¸”à¸•à¹„à¸Ÿà¸¥à¹Œà¸™à¸µà¹‰à¹ƒà¸™ section â€œWork Logâ€
7. commit à¸žà¸£à¹‰à¸­à¸¡à¸‚à¹‰à¸­à¸„à¸§à¸²à¸¡à¸Šà¸±à¸”à¹€à¸ˆà¸™

---

## 8. Recommended CSS Patterns

```css
.icon-badge,
.kpi-icon,
.metric-icon {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  flex: 0 0 auto;
}

.admin-page-shell {
  min-height: 100vh;
  background:
    radial-gradient(circle at top left, rgba(178, 235, 242, 0.45), transparent 32rem),
    linear-gradient(135deg, #f7fbff 0%, #eef8f8 100%);
}

.glass-card {
  background: rgba(255, 255, 255, 0.82);
  border: 1px solid rgba(219, 231, 239, 0.85);
  box-shadow: 0 18px 45px rgba(15, 46, 70, 0.08);
  backdrop-filter: blur(18px);
  border-radius: 24px;
}

.navy-hero {
  background:
    radial-gradient(circle at top right, rgba(22, 177, 194, 0.25), transparent 22rem),
    linear-gradient(135deg, #083553 0%, #0b3f63 45%, #062a44 100%);
  color: #fff;
  border-radius: 28px;
}
```

---

## 9. Verification Checklist

à¹ƒà¸Šà¹‰ checklist à¸™à¸µà¹‰à¸«à¸¥à¸±à¸‡à¸—à¸¸à¸ task:

- [ ] à¸«à¸™à¹‰à¸²à¹‚à¸«à¸¥à¸”à¹„à¸”à¹‰ à¹„à¸¡à¹ˆà¸¡à¸µ PHP fatal error
- [ ] à¹„à¸¡à¹ˆà¸¡à¸µ undefined variable warning
- [ ] sidebar à¹à¸ªà¸”à¸‡à¸–à¸¹à¸
- [ ] active menu à¸–à¸¹à¸à¸«à¸™à¹‰à¸²
- [ ] topbar à¹„à¸¡à¹ˆà¸—à¸±à¸š content
- [ ] hero à¹„à¸¡à¹ˆà¸ªà¸¹à¸‡/à¹„à¸¡à¹ˆà¹€à¸•à¸µà¹‰à¸¢à¸œà¸´à¸”à¸›à¸à¸•à¸´
- [ ] KPI cards align à¹€à¸—à¹ˆà¸²à¸à¸±à¸™
- [ ] icon à¸­à¸¢à¸¹à¹ˆà¸à¸¥à¸²à¸‡à¸§à¸‡à¸à¸¥à¸¡
- [ ] filter input à¹ƒà¸Šà¹‰à¸‡à¸²à¸™à¹„à¸”à¹‰
- [ ] table à¹„à¸¡à¹ˆà¸¥à¹‰à¸™à¸ˆà¸­à¹à¸šà¸šà¸œà¸´à¸”à¸›à¸à¸•à¸´
- [ ] à¸›à¸¸à¹ˆà¸¡ action à¸à¸”à¹„à¸”à¹‰
- [ ] export/print à¸¢à¸±à¸‡à¸—à¸³à¸‡à¸²à¸™
- [ ] form submit à¹„à¸”à¹‰
- [ ] permission check à¸¢à¸±à¸‡à¸­à¸¢à¸¹à¹ˆ
- [ ] responsive à¹„à¸¡à¹ˆà¸žà¸±à¸‡
- [ ] à¸­à¸±à¸›à¹€à¸”à¸• Work Log à¹à¸¥à¹‰à¸§

---

## 10. Work Log

### 2026-04-30 09:43
**Task:** Fix Thai text rendering on the authenticated ลงเวลาเวร page.
**Files changed:** pages/time.php, PROJECT_STATUS.md
**Root cause:** pages/time.php contained corrupted question-mark UI strings and invalid non-UTF-8 bytes, so labels, placeholders, buttons, cards, hero copy, PHP messages, and JS labels rendered incorrectly in the browser. Other pages were not the source of this issue.
**What changed:** Restored Thai copy for the Topbar, hero, KPI cards, Today Entry form, preset shifts, time summary, History & Review filters/list controls, bottom summary, PHP flash/audit messages, and page JavaScript labels while preserving route, SQL, permissions, form names, POST flow, layout classes, and design-system styling. Re-saved pages/time.php as UTF-8.
**Verification:** Ran ripgrep for repeated question marks, replacement characters, and mojibake bullets in pages/time.php and found no corrupted placeholders; ran C:\xampp\php\php.exe -l pages\time.php and it passed.
**Remaining issues:** Needs logged-in browser hard refresh on pages/time.php to visually confirm all strings after runtime data loads. If department/status/note data from the database still appears as question marks, the next fix is database/connection charset, not this page template.
**Next recommended task:** Hard refresh the ลงเวลาเวร page at desktop and mobile widths, verify no question-mark placeholders remain, then test save/filter/history interactions once.
> à¸—à¸¸à¸à¸„à¸£à¸±à¹‰à¸‡à¸—à¸µà¹ˆ Codex à¸—à¸³à¸‡à¸²à¸™à¹€à¸ªà¸£à¹‡à¸ˆ à¹ƒà¸«à¹‰à¹€à¸žà¸´à¹ˆà¸¡ entry à¹ƒà¸«à¸¡à¹ˆà¹„à¸§à¹‰à¸šà¸™à¸ªà¸¸à¸”à¸‚à¸­à¸‡à¸£à¸²à¸¢à¸à¸²à¸£à¸™à¸µà¹‰

### 2026-04-29 09:02
**Task:** Refine Database Admin dashboard (`จัดการข้อมูลฐานข้อมูล`) to fix viewport clipping and align hero/filter/table composition with the latest reference.
**Files changed:** `assets/css/dashboard-tailwind.css`, `assets/css/dashboard-tailwind.output.css`, `PROJECT_STATUS.md`
**What changed:** Applied page-scoped root-cause layout fixes for Database Admin only: unlocked page scroll on desktop by overriding `dash-shell`/`dash-main` when `db-admin-page-*` classes are present, reduced hero height/density, normalized hero metric dividers, tightened KPI row and content grid spacing, increased filter input/search control consistency, switched table wrappers to `overflow-x-auto` + visible vertical flow, and prevented audit card clipping. Kept all existing PHP data sources, filters, routes, permission checks, export/print links, and row actions unchanged.
**Verification:** Ran `npm run build:tailwind:dashboard`; ran `C:\xampp\php\php.exe -l pages\db_admin_dashboard.php` (passed); verified updated selectors compile into `assets/css/dashboard-tailwind.output.css`.
**Remaining issues:** Pixel-perfect parity for icon optics and button spacing still requires a live browser compare on 1440px/1920px after cache clear.
**Next recommended task:** Hard refresh `pages/db_admin_dashboard.php`, validate scroll to audit + bottom summary, then do a final 1-pass visual tune only if any remaining mismatch is visible.

### 2026-04-28 15:27
**Task:** Refine Database Admin dashboard (`จัดการข้อมูลฐานข้อมูล`) spacing and density against the latest target screenshot.
**Files changed:** `assets/css/dashboard-tailwind.css`, `assets/css/dashboard-tailwind.output.css`, `PROJECT_STATUS.md`
**What changed:** Tuned page-scoped `.db-admin-*` CSS only: compacted the navy hero, reduced title/copy/pill spacing, tightened KPI cards, narrowed the filter/table grid, reduced table and audit row height, reduced forced table min-width, compacted action/status buttons, and lowered bottom summary density. Existing PHP data sources, filters, table/action links, audit/export/print routes, permissions, and page routing were preserved.
**Verification:** Ran `npm run build:tailwind:dashboard`; ran `C:\xampp\php\php.exe -l pages\db_admin_dashboard.php`; verified key `.db-admin-*` selectors compile into `assets/css/dashboard-tailwind.output.css`; ran `git diff --check` for the relevant files (CRLF warnings only).
**Remaining issues:** Browser pixel-level QA and end-to-end clicks for open table, audit history, filter, print, PDF, and CSV were not run in this turn.
**Next recommended task:** Hard-refresh `pages/db_admin_dashboard.php` in the browser, compare with the reference at 1440px/1920px, then tune only if the table/action area still feels cropped.

### 2026-04-28 15:14
**Task:** Redesign Database Admin page (`จัดการข้อมูลฐานข้อมูล`) to match the latest dashboard target while preserving existing database table/audit actions.
**Files changed:** `pages/db_admin_dashboard.php`, `assets/css/dashboard-tailwind.css`, `assets/css/dashboard-tailwind.output.css`, `tailwind.config.js`, `PROJECT_STATUS.md`
**What changed:** Rebuilt the Database Admin page into the shared dashboard shell with sidebar/topbar, navy gradient hero, hero KPI metrics, summary cards, filter/tools panel, managed table list, audit log card, and bottom summary strip. Added page-scoped `.db-admin-*` styling, fixed icon-circle centering, and added Database Admin pages/partials to Tailwind content scanning so the scoped CSS is compiled. Existing permission checks, helper data sources, table open links, audit log links, export/print routes, and form field names were preserved.
**Verification:** Ran `npm run build:tailwind:dashboard`; verified `db-admin-hero-card` exists in `assets/css/dashboard-tailwind.output.css`; ran `C:\xampp\php\php.exe -l pages\db_admin_dashboard.php`; ran `git diff --check` for the relevant files.
**Remaining issues:** Browser pixel-level QA and end-to-end clicks for open table, audit history, filter, print, PDF, and CSV were not run in this turn.
**Next recommended task:** Hard-refresh `pages/db_admin_dashboard.php` in the browser, compare against the target screenshot, then test all Database Admin actions and tune spacing if needed.

### 2026-04-28 14:27
**Task:** Refine Admin User Management dashboard layout to match the target screenshot more closely and prevent filter/table cropping.
**Files changed:** `assets/css/dashboard-tailwind.css`, `assets/css/dashboard-tailwind.output.css`, `PROJECT_STATUS.md`
**What changed:** Tuned page-scoped `.admin-users-*` CSS: reduced hero/KPI/table spacing, made desktop dashboard frame and content grid stop clipping children, allowed the page/table layout to show the full filter card and more table rows, kept icon badges flex-centered, and rebuilt the dashboard CSS output. No backend queries, form names, permissions, or action routes were changed.
**Verification:** Ran `npm run build:tailwind:dashboard`; ran `C:\xampp\php\php.exe -l` on `pages/manage_users.php`, `partials/admin/manage_users_results.php`, `ajax/admin/users_rows.php`, and `includes/report_helpers.php`; ran `git diff --check` for the relevant files.
**Remaining issues:** Browser pixel QA and end-to-end clicks for filter/export/detail/edit were not executed in this turn.
**Next recommended task:** Hard-refresh `pages/manage_users.php` in the browser, confirm the table is no longer cropped, then do one pixel-level pass on hero/action spacing if needed.

### 2026-04-28 14:07
**Task:** Fix Admin User Management dashboard styling so `pages/manage_users.php` renders as the intended dashboard layout instead of raw stacked content.
**Files changed:** `pages/manage_users.php`, `partials/admin/manage_users_results.php`, `ajax/admin/users_rows.php`, `includes/report_helpers.php`, `assets/css/dashboard-tailwind.css`, `assets/css/dashboard-tailwind.output.css`, `PROJECT_STATUS.md`
**What changed:** Added page-scoped plain CSS for `.admin-users-*` layout selectors so Tailwind content pruning cannot drop the dashboard hero, KPI, filter, table, and summary styles; preserved existing PHP data flow, filters, export links, AJAX table loading, detail/edit actions, and permission checks.
**Verification:** Ran `npm run build:tailwind:dashboard`; verified key `.admin-users-*` selectors exist in `assets/css/dashboard-tailwind.output.css`; ran `C:\xampp\php\php.exe -l` on `pages/manage_users.php`, `partials/admin/manage_users_results.php`, `ajax/admin/users_rows.php`, and `includes/report_helpers.php` with no syntax errors.
**Remaining issues:** Browser pixel-level QA and end-to-end click testing for filters/export/detail/edit were not run in this turn.
**Next recommended task:** Open `pages/manage_users.php` in the browser, hard-refresh, compare against the target screenshot, then tune spacing/height if needed.

### YYYY-MM-DD HH:mm
**Task:**
**Files changed:**
**What changed:**
**Verification:**
**Remaining issues:**
**Next recommended task:**

---

## 11. Next Recommended Tasks

1. à¸›à¸£à¸±à¸šà¸«à¸™à¹‰à¸² Admin â€œà¸ˆà¸±à¸”à¸à¸²à¸£à¸œà¸¹à¹‰à¹ƒà¸Šà¹‰à¸‡à¸²à¸™â€
2. à¸•à¸£à¸§à¸ˆà¸«à¸™à¹‰à¸² Admin â€œà¸ˆà¸±à¸”à¸à¸²à¸£à¸¥à¸‡à¹€à¸§à¸¥à¸²à¹€à¸§à¸£â€ à¹€à¸£à¸·à¹ˆà¸­à¸‡à¸„à¸§à¸²à¸¡à¸ªà¸¹à¸‡, filter, table, hero
3. à¹€à¸à¹‡à¸š global icon badge alignment à¸—à¸¸à¸à¸«à¸™à¹‰à¸²
4. à¸•à¸£à¸§à¸ˆ CSS à¸‹à¹‰à¸³à¸‹à¹‰à¸­à¸™à¸£à¸°à¸«à¸§à¹ˆà¸²à¸‡ report/admin/review pages
5. à¸—à¸³ design tokens à¸à¸¥à¸²à¸‡ à¹€à¸Šà¹ˆà¸™ color, radius, shadow, spacing

---

## 12. Git / Deployment Notes

à¸«à¸¥à¸±à¸‡ Codex à¹à¸à¹‰à¸‡à¸²à¸™à¹€à¸ªà¸£à¹‡à¸ˆ:

```bash
git status
git add .
git commit -m "ui: update admin user management dashboard layout"
git push origin main
```

à¸šà¸™ server:

```bash
cd /path/to/project
git pull origin main
```

à¸–à¹‰à¸²à¸¡à¸µ asset build pipeline:

```bash
npm install
npm run build
```

à¸–à¹‰à¸²à¹€à¸›à¹‡à¸™ PHP/CSS à¸˜à¸£à¸£à¸¡à¸”à¸²à¹à¸¥à¸°à¹„à¸¡à¹ˆà¹„à¸”à¹‰à¹ƒà¸Šà¹‰ bundler:
- à¹„à¸¡à¹ˆà¸•à¹‰à¸­à¸‡à¸£à¸±à¸™ node
- hard refresh browser
- clear cache à¸–à¹‰à¸²à¸ˆà¸³à¹€à¸›à¹‡à¸™
- à¸•à¸£à¸§à¸ˆ path CSS/JS à¸§à¹ˆà¸² server à¹‚à¸«à¸¥à¸”à¹„à¸Ÿà¸¥à¹Œà¹ƒà¸«à¸¡à¹ˆà¸ˆà¸£à¸´à¸‡

---

## 13. Important Reminder

à¸£à¸°à¸šà¸šà¸™à¸µà¹‰à¹€à¸›à¹‡à¸™à¸£à¸°à¸šà¸šà¹ƒà¸Šà¹‰à¸‡à¸²à¸™à¹ƒà¸™à¸šà¸£à¸´à¸šà¸—à¹‚à¸£à¸‡à¸žà¸¢à¸²à¸šà¸²à¸¥ à¸•à¹‰à¸­à¸‡à¹ƒà¸«à¹‰à¸„à¸§à¸²à¸¡à¸ªà¸³à¸„à¸±à¸à¸à¸±à¸š:

- à¸„à¸§à¸²à¸¡à¸–à¸¹à¸à¸•à¹‰à¸­à¸‡à¸‚à¸­à¸‡à¸‚à¹‰à¸­à¸¡à¸¹à¸¥
- à¸ªà¸´à¸—à¸˜à¸´à¹Œà¸à¸²à¸£à¹€à¸‚à¹‰à¸²à¸–à¸¶à¸‡
- audit trail
- workflow à¸•à¹ˆà¸­à¹€à¸™à¸·à¹ˆà¸­à¸‡
- à¸›à¸¸à¹ˆà¸¡à¹à¸¥à¸°à¸ªà¸–à¸²à¸™à¸°à¸—à¸µà¹ˆà¸Šà¸±à¸”à¹€à¸ˆà¸™
- à¸«à¹‰à¸²à¸¡à¹€à¸”à¸²à¸‚à¹‰à¸­à¸¡à¸¹à¸¥
- à¸«à¹‰à¸²à¸¡à¸‹à¹ˆà¸­à¸™ error à¸ªà¸³à¸„à¸±à¸
- à¸«à¹‰à¸²à¸¡à¸—à¸³ UI à¸ªà¸§à¸¢à¹à¸•à¹ˆà¸—à¸³à¹ƒà¸«à¹‰à¹€à¸ˆà¹‰à¸²à¸«à¸™à¹‰à¸²à¸—à¸µà¹ˆà¹ƒà¸Šà¹‰à¸‡à¸²à¸™à¸¢à¸²à¸

---

## ล่าสุด: ปรับหน้าแรกระบบ StaffMain / Over Time

- วันที่: 30 เมษายน 2569
- หน้า: หน้าแรก / Homepage Dashboard
- ไฟล์ที่แก้: `index.php`, `assets/css/tailwind-index.css`, `assets/css/index-tailwind.css`
- สิ่งที่ปรับ:
  - เพิ่ม Hero Summary Card พร้อมภาพ `images/hopital.png` และ fallback กรณีไม่พบไฟล์
  - เพิ่ม Real-time Overview Block ขนาดใหญ่ พร้อม live clock, วันที่ภาษาไทย, location และ KPI ภาพรวม
  - ปรับ KPI cards, ตารางบุคลากรที่ลงเวรตอนนี้, ตารางแผนกที่ปฏิบัติงาน และ CTA ให้เข้ากับ dashboard hospital operation
  - ใช้ mock fallback แยกชัดเจนเฉพาะกรณีไม่มีข้อมูลจากฐานข้อมูล
  - ปรับ responsive layout สำหรับ desktop/tablet/mobile
- วิธีทดสอบ:
  - `php -l index.php` ผ่าน
  - `npm run build:tailwind` ผ่าน
  - `curl http://localhost/staff-main/index.php` พบข้อความ `หน้าแรกระบบ Over Time`, `ภาพรวมวันนี้`, และ asset `images/hopital.png`
  - ตรวจ `rg` ไม่พบ `????`, replacement character หรือ mojibake ในไฟล์หน้าแรก/CSS ที่แก้
- สถานะ: completed / รอตรวจเทียบภาพจริงใน browser ที่ 1440px และ 1600px
---

## ล่าสุด: ปรับหน้าแรก Over Time เป็น Public Landing Dashboard

- วันที่: 30 เมษายน 2569
- Branch: `codex/strict-monthly-matrix-mapping`
- หน้า: หน้าแรก / Public Homepage
- ไฟล์ที่แก้:
  - `index.php`
  - `assets/css/tailwind-index.css`
  - `assets/css/index-tailwind.css`
  - `assets/css/dashboard-tailwind.output.css` (generated จาก `npm run build:tailwind`)
  - `PROJECT_STATUS.md`
- สิ่งที่ปรับ:
  - เปลี่ยนชื่อและ header เป็น `Over Time` พร้อม subtitle, date badge, ปุ่มสมัครใช้งาน และปุ่มเข้าสู่ระบบ
  - ลบเมนูกลาง public header, search, notification, user profile และ section ที่ไม่ต้องการออกจากหน้าแรก
  - เพิ่ม Hero Card พร้อมภาพ `images/hopital.png` และสถานะระบบ
  - เพิ่ม Real-time Overview Card พร้อม live clock, วันที่ไทย, location และ KPI จากข้อมูลฐานข้อมูลพร้อม fallback
  - เพิ่ม realtime cards 3 ใบ: User Active, Department Active, Today Attendance
  - เพิ่มตารางบุคลากรที่ลงเวรตอนนี้และตารางแผนกที่กำลังปฏิบัติงาน
  - เพิ่ม modal รายละเอียดและ export/print/PDF/CSV แบบ frontend helper
  - เพิ่ม CSS สำหรับ modal, status badge และ responsive layout
- วิธีทดสอบ:
  - `C:\xampp\php\php.exe -l index.php` ผ่าน
  - `npm run build:tailwind` ผ่าน
  - `curl http://localhost/staff-main/index.php` พบข้อความ `หน้าแรกระบบ Over Time`, `ภาพรวมวันนี้`, `User Active`, `Department Active`, `Today Attendance`
  - ตรวจไม่พบ `Core Workflow`, `NEXT STEP`, `งานหลักในระบบ`, `home-nav-menu` ใน HTML ที่ render
  - ตรวจไม่พบ `????`, `�`, หรือ mojibake pattern ใน `index.php` และ `assets/css/tailwind-index.css`
- สิ่งที่ยังต้องตรวจ:
  - Visual QA ใน browser ที่ 1440px/1600px เทียบภาพออกแบบ
  - ทดสอบปุ่ม modal/export บน browser จริง
  - ตรวจนโยบายข้อมูล public ว่าสามารถแสดงรายชื่อบุคลากรบนหน้าแรกได้หรือควรจำกัดสิทธิ์
- สถานะ: completed / pending visual browser review

---

## ล่าสุด: Polish หน้าแรก Over Time ให้ compact ใกล้ภาพออกแบบ

- วันที่: 30 เมษายน 2569 15:33 น.
- Branch: `codex/strict-monthly-matrix-mapping`
- หน้า: หน้าแรก / Public Homepage
- ไฟล์ที่แก้:
  - `index.php`
  - `assets/css/tailwind-index.css`
  - `assets/css/index-tailwind.css`
  - `assets/css/dashboard-tailwind.output.css`
  - `PROJECT_STATUS.md`
- สิ่งที่ปรับ:
  - เพิ่มความกว้าง container เป็นแนว `calc(100% - 48px)` และ `max-width: 1720px` เพื่อลดพื้นที่ว่างซ้าย-ขวาบน desktop
  - ปรับ hero row เป็น 2 columns แบบ compact และลดความสูง card หลักให้อยู่ราว 270px
  - ปรับ realtime overview ให้สูงเท่ากับ hero card และจัด KPI ด้านขวาให้แน่นขึ้น
  - ปรับ card แถว 2 ให้สูงเท่ากันและ compact ขึ้น พร้อมเอาปุ่ม `รายละเอียด` ออกจาก User Active, Department Active และ Today Attendance
  - ปรับ User Active ให้ใช้ `profile_image_path` ถ้ามีรูปจริง และ fallback เป็น initials avatar ถ้าไม่มีรูป
  - ปรับ User Active และ Department Active ให้ render รายการทั้งหมดใน preview list พร้อม internal scroll เมื่อเกิน 3 รายการ
  - ลด gap/padding/table spacing เพื่อให้ layout ใกล้ภาพออกแบบที่ 1440px/1600px มากขึ้น
- วิธีทดสอบ:
  - `C:\xampp\php\php.exe -l index.php` ผ่าน
  - `npm run build:tailwind` ผ่าน
  - ตรวจ HTML render ไม่พบเมนูกลาง, Core Workflow, NEXT STEP หรือปุ่มรายละเอียดในการ์ดแถว 2
- สิ่งที่ยังต้องตรวจ:
  - Visual QA ด้วย browser hard refresh ที่ 1440px และ 1600px
  - ทดสอบปุ่ม Print/PDF/CSV และ internal scroll ใน preview list ด้วยข้อมูลจริงหลายรายการ
- สถานะ: completed / pending browser visual review

---

## ล่าสุด: ปรับ realtime/empty state หน้าแรก Public Over Time

- วันที่/เวลา: 1 พฤษภาคม 2569 09:25 น.
- Branch: `codex/strict-monthly-matrix-mapping`
- หน้า: หน้าแรก public landing page / Over Time
- ไฟล์ที่แก้:
  - `index.php`
  - `api/public/home/realtime.php`
  - `assets/css/tailwind-index.css`
  - `assets/css/index-tailwind.css`
  - `assets/css/dashboard-tailwind.output.css`
  - `PROJECT_STATUS.md`
- สิ่งที่ปรับ:
  - ตัดวงเปอร์เซ็นต์และข้อความ `อัตราการลงเวรภาพรวม` ออกจากการ์ด `ภาพรวมวันนี้` แล้วจัด metrics เหลือ 3 ช่องให้เต็มพื้นที่
  - แก้ logic active-now ให้อิง `work_date`, `TIME(time_in)`, `TIME(time_out)` และรองรับเวรข้ามวัน
  - การ์ด `แผนกที่ลงเวรตอนนี้` แสดง empty state เมื่อไม่มีแผนกที่มีบุคลากรปฏิบัติงานจริง และไม่แสดงแถว 0 คน
  - ตาราง `แผนกที่กำลังปฏิบัติงาน` แสดงเฉพาะแผนกที่มีคน > 0 หรือ empty state แทนแถว ICU/IPD/OPD 0 คน
  - ตาราง `รายการบุคลากรที่ลงเวรตอนนี้` ซ่อน pagination ที่ทำให้เข้าใจผิดเมื่อไม่มีข้อมูล และแสดง `ไม่มีรายการ`
  - เพิ่ม endpoint `GET /api/public/home/realtime.php` และ polling ทุก 15 วินาที เพื่ออัปเดตตัวเลข/list/table โดยไม่ reload หน้า
  - ปุ่ม export/print ของ User Active และ Department Active ถูก disable เมื่อไม่มีข้อมูลสำหรับส่งออก
- วิธีทดสอบ:
  - `C:\xampp\php\php.exe -l index.php` ผ่าน
  - `C:\xampp\php\php.exe -l api\public\home\realtime.php` ผ่าน
  - `curl http://localhost/staff-main/api/public/home/realtime.php` คืน JSON UTF-8 พร้อม `active_users`, `active_departments`, metrics และ timestamp
  - `npm run build:tailwind` ผ่าน และ generate `assets/css/index-tailwind.css`
- สิ่งที่ยังต้องตรวจ:
  - Browser visual QA หลัง hard refresh ที่ 1440px/1600px
  - ตรวจ DevTools ว่า polling ทุก 15 วินาทีไม่มี console/network error บนเครื่อง deploy
- สถานะ: completed / pending browser visual review

---

## Deployment / Git Status

- วันที่/เวลา: 30 เมษายน 2569 16:13 น.
- Active branch: `codex/strict-monthly-matrix-mapping`
- Rule: All Codex work must stay on this branch unless instructed otherwise.
- Latest task: Build Tailwind/frontend assets and prepare Plesk deployment.
- Build command used:
  - `npm install`
  - `npm run build:tailwind`
- Build output:
  - `assets/css/index-tailwind.css`
  - `assets/css/dashboard-tailwind.output.css`
- Project type checked:
  - Plain PHP + Tailwind CLI build scripts in `package.json`
  - No Laravel/Next/Vite build output or manifest detected for this page
- Plesk deployment status:
  - Local build completed.
  - Existing deploy helper: `scripts/deploy-plesk.sh`
  - Plesk document root: pending verification in Plesk/SSH; not guessed.
  - Server pull status: pending verification in Plesk/SSH.
  - Cache clearing: pending server access; no Laravel/Next cache commands needed locally.
- Files changed for commit:
  - `index.php`
  - `assets/css/tailwind-index.css`
  - `assets/css/index-tailwind.css`
  - `assets/css/dashboard-tailwind.output.css`
  - `images/hopital.png`
  - `PROJECT_STATUS.md`
- Verification:
  - `C:\xampp\php\php.exe -l index.php` passed
  - `npm run build:tailwind` passed
  - `curl -I http://localhost/staff-main/uploads/profiles/profile_1775119508_69cf30acb56071.92287533.png` returned `200 OK`
  - Browser/Plesk production verification still requires server access and hard refresh/cache-bust check.
- Next action:
  - On Plesk server, run `scripts/deploy-plesk.sh` from the project root after confirming document root.
  - Verify production with `https://<domain>/?v=<timestamp>` and DevTools Network cache disabled.

---

## ล่าสุด: เพิ่มรูปโปรไฟล์ในหน้าแรก Over Time

- วันที่: 30 เมษายน 2569 15:56 น.
- Branch: `codex/strict-monthly-matrix-mapping`
- หน้า: หน้าแรก / Public Homepage
- ไฟล์ที่แก้:
  - `index.php`
  - `assets/css/tailwind-index.css`
  - `assets/css/index-tailwind.css`
  - `assets/css/dashboard-tailwind.output.css`
  - `PROJECT_STATUS.md`
- สิ่งที่ปรับ:
  - ปรับ helper รูปโปรไฟล์ให้ resolve path จริงจาก `uploads/avatars` และ `uploads/profiles` แทนการชี้ไป root path
  - User Active card แสดงรูปโปรไฟล์จาก `profile_image_path` ถ้ามี และ fallback เป็น initials avatar ถ้าไม่มีรูปหรือไม่พบไฟล์
  - ตาราง `รายการบุคลากรที่ลงเวรตอนนี้` และ modal รายชื่อ แสดง avatar ซ้ายของชื่อผู้ใช้
  - ลดขนาด typography ของชื่อ/ตำแหน่งใน preview row และ table cell พร้อม truncate เพื่อกันชื่อยาวดัน layout
  - ปรับ CSS avatar/row layout ให้เป็นวงกลม, object-fit cover, และ spacing ใกล้ภาพออกแบบ
- วิธีทดสอบ:
  - `C:\xampp\php\php.exe -l index.php` ผ่าน
  - `npm run build:tailwind` ผ่าน
  - `curl -I http://localhost/staff-main/uploads/profiles/profile_1775119508_69ce2c946848e1.14449180.png` ได้ `200 OK`
  - ตรวจ HTML render พบ `active-user-row`, `active-table-person`, `active-table-avatar` และ path รูป `/staff-main/uploads/profiles/...`
- สิ่งที่ยังต้องตรวจ:
  - Visual QA ใน browser ว่ารูปไม่ถูก crop ผิดจุด และชื่อยาว truncate สวยที่ 1440px/1600px
  - ตรวจ Network tab ว่าไม่มี 404 ของรูปผู้ใช้รายอื่นที่มี filename ภาษาไทยหรือ path เก่า
- สถานะ: completed / pending browser visual review
