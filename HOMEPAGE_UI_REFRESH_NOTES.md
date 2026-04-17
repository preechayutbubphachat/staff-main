## Chosen first page

- Checked [index.php](C:/xampp/htdocs/staff-main/index.php) and confirmed it redirects logged-in users to [dashboard.php](C:/xampp/htdocs/staff-main/pages/dashboard.php).
- The UI refresh therefore targets the main working homepage at [dashboard.php](C:/xampp/htdocs/staff-main/pages/dashboard.php).

## Final layout structure

1. Bright hero / welcome surface with a short greeting and compact day summary.
2. Lightweight metric row for monthly hours, yearly hours, and today shift count.
3. Clear `ทางลัดการใช้งาน` section with obvious action cards.
4. Profile / personal information block with a direct profile-management action.
5. Supporting operational cards for personal activity, reports, and review work.

## Color and style direction

- Overall mood shifted toward brighter white and near-white surfaces.
- Navy / teal remains in the identity, but now mostly as accent instead of dominant background.
- Hero uses a pale aqua / soft blue layered background to feel lighter and fresher.
- Cards use soft shadows, thin borders, and more open spacing.
- Important actions use darker solid CTA buttons so they remain obvious against the lighter page.

## Typography and spacing decisions

- Reduced the visual density of the hero and support copy.
- Kept headings in Prompt for strong Thai-first hierarchy.
- Simplified helper lines to one short sentence where possible.
- Increased breathing room between major sections while softening card chrome.
- Avoided introducing extra sections above the fold.

## Action and shortcut decisions

Primary shortcuts emphasized:
- ลงเวลาเวร
- เวรวันนี้
- ตรวจสอบเวร (permission-gated)
- รายงานของฉัน
- รายงานแผนก (permission-gated)
- โปรไฟล์และลายเซ็น
- พื้นที่ผู้ดูแล / จัดการผู้ใช้งาน / จัดการลงเวลาเวร when relevant by permission

Each major shortcut now includes:
- icon
- short label
- one-sentence explanation
- explicit CTA button

## Copy simplification decisions

- The hero now explains the dashboard in one short line instead of a longer paragraph.
- Metric helper text was shortened to quick operational labels.
- Workspace cards now describe one purpose only, instead of mixing multiple explanations.
- Review queue and latest activity sections were simplified so the action stands out more than the prose.

## Notes

- This refresh intentionally focuses on the first page only.
- Navbar structure and deeper pages were not redesigned in this task.
