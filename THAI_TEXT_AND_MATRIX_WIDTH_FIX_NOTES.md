## Root cause of Daily Schedule Thai text issue

The remaining Daily Schedule problem was not a missing font in the browser. The real issue was that some page and partial labels were still hardcoded as placeholder-style broken strings (`????`) in the source files.

Affected render paths:

- [daily_schedule.php](C:/xampp/htdocs/staff-main/pages/daily_schedule.php)
- [daily_schedule_results.php](C:/xampp/htdocs/staff-main/partials/reports/daily_schedule_results.php)
- [daily_schedule_rows.php](C:/xampp/htdocs/staff-main/ajax/reports/daily_schedule_rows.php)

## Exact Thai-rendering fix used

- Replaced leftover placeholder literals with real Thai strings.
- Kept report print/export paths on UTF-8 output.
- Kept Thai-safe fonts in the print template.
- Ensured the AJAX daily schedule results renderer passes the same context variables as the first page render, so the partial does not fall back to broken default text.

## Department matrix width strategy

The Department Monthly Matrix Report was too wide for A4 landscape after adding end-summary columns. The width strategy used in [report_print.php](C:/xampp/htdocs/staff-main/pages/report_print.php) is:

- slightly smaller print font size
- slightly tighter cell padding
- narrower person columns:
  - ลำดับ
  - ชื่อ-สกุล
  - ตำแหน่ง
  - แผนก
- narrower uniform day columns
- compact OT / summary columns
- compact but still readable remark column

## Vertical header columns

Selected columns now use vertical header text to save horizontal width:

- `จำนวนเวร`
- `ชั่วโมงรวม`
- `OT`

Implementation pattern:

- CSS class for vertical print headers inside the monthly matrix
- vertical writing mode with controlled height so print/PDF output stays aligned

## Print / PDF decisions

- Department monthly matrix remains A4 landscape.
- Vertical headers are used only on narrow end columns that benefit from rotation.
- Day columns stay compact and centered for short codes such as `ช / บ / ด / BD`.
- The rest of the matrix keeps horizontal text for readability.

## Known limitations / deliberate choices

- `OT` is intentionally blank because the current schema does not provide a reliable OT source of truth.
- `หมายเหตุ` is intentionally blank in the monthly matrix unless a future month-level summary note source is introduced.
- The parenthesized signature line is left blank for manual filling because no reliable reviewer display name is currently injected into this print context.
