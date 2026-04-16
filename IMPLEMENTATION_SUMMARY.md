## Scope completed

- Fixed Thai text rendering for approval/review report print and export outputs.
- Added a dedicated monthly department matrix report for print/PDF/CSV in A4 landscape.
- Kept the special matrix format scoped to Department Reports only.

## Approval report text fix

- Restored broken Thai literals in the shared report templates used by approval print/export.
- Kept UTF-8 output paths in place:
  - HTML print template already uses `UTF-8` meta.
  - CSV export still writes UTF-8 BOM.
- Approval report headings, summary labels, table headers, and print action labels now come from clean Thai strings again.

## Department monthly matrix report

- `type=department` in [report_print.php](C:/xampp/htdocs/staff-main/pages/report_print.php) now renders a formal monthly matrix document.
- `type=department` in [export_report.php](C:/xampp/htdocs/staff-main/pages/export_report.php) now exports the same monthly matrix structure to CSV.
- Added matrix dataset generation in [report_helpers.php](C:/xampp/htdocs/staff-main/includes/report_helpers.php) with one staff row and one day column per selected month.
- Added strict shift abbreviations:
  - `ช` = เวรเช้า 08.30 - 16.30 น.
  - `บ` = เวรบ่าย 16.30 - 00.30 น.
  - `ด` = เวรดึก 00.30 - 08.30 น.
  - `BD` = เวรบ่ายนอกเวลาราชการ
- Mapping now uses explicit rules only:
  - approved-only records (`checked_at IS NOT NULL`)
  - explicit `BD` marker from `note` / `approval_note`
  - otherwise exact normalized time match only
- Future days in the current incomplete month stay blank.
- Conflicting same-day standard shifts now resolve to blank instead of guessing.

## A4 / landscape decisions

- Print stylesheet keeps `@page { size: A4 landscape; }`.
- PDF export continues using landscape orientation via jsPDF (`'l', 'mm', 'a4'`).
- Header metadata was kept compact in the document head area instead of dashboard-style body cards.
- Footer keeps notes/legend plus signature area for the shift reviewer.

## Files changed

- [report_helpers.php](C:/xampp/htdocs/staff-main/includes/report_helpers.php)
- [report_print.php](C:/xampp/htdocs/staff-main/pages/report_print.php)
- [export_report.php](C:/xampp/htdocs/staff-main/pages/export_report.php)
- [IMPLEMENTATION_SUMMARY.md](C:/xampp/htdocs/staff-main/IMPLEMENTATION_SUMMARY.md)
- [APPROVAL_REPORT_TEXT_FIX_NOTES.md](C:/xampp/htdocs/staff-main/APPROVAL_REPORT_TEXT_FIX_NOTES.md)
- [DEPARTMENT_MONTHLY_MATRIX_REPORT_NOTES.md](C:/xampp/htdocs/staff-main/DEPARTMENT_MONTHLY_MATRIX_REPORT_NOTES.md)
- [BUG_AUDIT.md](C:/xampp/htdocs/staff-main/BUG_AUDIT.md)
