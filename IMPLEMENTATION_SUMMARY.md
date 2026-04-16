## Scope completed

- Fixed remaining broken Thai text on the Daily Schedule page and its AJAX result partial.
- Tightened the Department Monthly Matrix Report so it fits A4 landscape more cleanly.
- Added vertical header treatment for selected narrow monthly summary columns.

## Thai text fixes

- Daily Schedule still had leftover placeholder literals such as `????` in:
  - [daily_schedule.php](C:/xampp/htdocs/staff-main/pages/daily_schedule.php)
  - [daily_schedule_results.php](C:/xampp/htdocs/staff-main/partials/reports/daily_schedule_results.php)
- Replaced those placeholder strings with real Thai labels and helper text.
- Updated the AJAX render path in [daily_schedule_rows.php](C:/xampp/htdocs/staff-main/ajax/reports/daily_schedule_rows.php) so the results partial always receives the full matrix/table context it expects.
- Shared print/export templates remain on UTF-8 paths and continue using Thai-safe fonts.

## Department monthly matrix width changes

- Kept the document in A4 landscape.
- Reduced monthly matrix width pressure in [report_print.php](C:/xampp/htdocs/staff-main/pages/report_print.php) by:
  - shrinking person-column widths
  - shrinking day-column widths
  - reducing print font size and cell padding slightly
  - keeping the remark column compact but readable
- Added vertical header text for:
  - `จำนวนเวร`
  - `ชั่วโมงรวม`
  - `OT`

## Monthly matrix trailing columns

The department monthly matrix now ends with:

- `จำนวนเวร`
- `ชั่วโมงรวม`
- `OT`
- `หมายเหตุ`

Behavior:

- `จำนวนเวร` counts only safely resolved matrix shifts.
- `ชั่วโมงรวม` sums only those resolved counted shifts.
- `OT` stays blank because there is no reliable structured OT source in current `time_logs`.
- `หมายเหตุ` stays blank because there is no reliable month-level summary note source in the current schema.

## Signature area

The print signature block now renders:

- `ลงชื่อ ...............................................................`
- `(...............................................................)`
- `ผู้ตรวจสอบเวร`

## Files changed in this round

- [daily_schedule.php](C:/xampp/htdocs/staff-main/pages/daily_schedule.php)
- [daily_schedule_results.php](C:/xampp/htdocs/staff-main/partials/reports/daily_schedule_results.php)
- [daily_schedule_rows.php](C:/xampp/htdocs/staff-main/ajax/reports/daily_schedule_rows.php)
- [report_helpers.php](C:/xampp/htdocs/staff-main/includes/report_helpers.php)
- [report_print.php](C:/xampp/htdocs/staff-main/pages/report_print.php)
- [export_report.php](C:/xampp/htdocs/staff-main/pages/export_report.php)
- [IMPLEMENTATION_SUMMARY.md](C:/xampp/htdocs/staff-main/IMPLEMENTATION_SUMMARY.md)
- [THAI_TEXT_AND_MATRIX_WIDTH_FIX_NOTES.md](C:/xampp/htdocs/staff-main/THAI_TEXT_AND_MATRIX_WIDTH_FIX_NOTES.md)
- [BUG_AUDIT.md](C:/xampp/htdocs/staff-main/BUG_AUDIT.md)
