## Root cause

The approval/review report did not mainly fail because of query logic. The visible Thai text was broken in the shared report templates used by print/export output. Some literals had been saved as placeholder-style broken strings, so headings and labels rendered unreadably.

## Solution used

- Restored clean Thai literals in the shared report print/export template.
- Kept UTF-8-safe output paths:
  - HTML print output remains UTF-8.
  - CSV export keeps the UTF-8 BOM prefix.
- Replaced corrupted approval report labels in:
  - report title
  - summary cards
  - table headers
  - print action buttons
  - document metadata labels

## Templates/pages updated

- [report_print.php](C:/xampp/htdocs/staff-main/pages/report_print.php)
- [export_report.php](C:/xampp/htdocs/staff-main/pages/export_report.php)

## Expected result

- Thai headings on the approval report are readable again.
- Thai labels in approval print/PDF/CSV outputs no longer show broken characters or question-mark placeholders.
- Existing approval report filtering and permission logic remains unchanged.
