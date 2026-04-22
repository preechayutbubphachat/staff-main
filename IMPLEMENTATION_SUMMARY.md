## Dashboard tall-card fix

### Root cause found

- The left profile card on [dashboard.php](C:/xampp/htdocs/staff-main/pages/dashboard.php) was rendered inside a Bootstrap row that stretched column heights to match the taller right-hand column.
- The profile card itself also carried full-height behavior, so it expanded to the full height of the stacked cards on the right and created the long vertical column.
- The dashboard used `avatar-frame` / `avatar-fallback` markup without shared sizing rules in [app-ui.css](C:/xampp/htdocs/staff-main/assets/css/app-ui.css), which left profile media without a safe reusable constraint.

### Exact layout rule fixed

- Changed the dashboard profile row to start-align instead of stretch-align.
- Removed the left profile card from full-height stretching behavior.
- Added shared bounded avatar sizing rules so profile media stays inside a fixed visual frame and cannot dictate card height.

### Files changed

- [dashboard.php](C:/xampp/htdocs/staff-main/pages/dashboard.php)
- [app-ui.css](C:/xampp/htdocs/staff-main/assets/css/app-ui.css)
- [IMPLEMENTATION_SUMMARY.md](C:/xampp/htdocs/staff-main/IMPLEMENTATION_SUMMARY.md)
- [BUG_AUDIT.md](C:/xampp/htdocs/staff-main/BUG_AUDIT.md)
