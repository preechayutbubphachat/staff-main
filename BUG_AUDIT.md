## Checked items

- Approval report Thai text rendering in shared print/export template.
- Department report monthly matrix day-count generation.
- Shift code mapping for `ช / บ / ด / BD`.
- Strict BD detection rule.
- Same-day conflict handling for monthly matrix cells.
- Blank future-day rule for the current incomplete month.
- Footer legend and signature area in print layout.
- A4 landscape configuration in print/PDF path.

## Findings addressed

- Approval/review report text corruption came from broken Thai literals inside shared report templates, not from the database query itself.
- Department report did not have a formal monthly matrix output; a dedicated department-only matrix mode was added to print and CSV export.
- Daily schedule helper heading text had leftover placeholder strings in the shared helper and was restored while touching the shared report layer.
- Monthly matrix mapping is now deterministic and conservative: approved-only, exact-time matching, explicit BD only, blank on unresolved conflict.

## Manual follow-up still recommended

- Open [report_print.php](C:/xampp/htdocs/staff-main/pages/report_print.php?type=approval) in a browser and confirm Thai labels render correctly in the live print view.
- Open [report_print.php](C:/xampp/htdocs/staff-main/pages/report_print.php?type=department) with several month lengths:
  - 28 days
  - 29 days
  - 30 days
  - 31 days
- Generate PDF from the department report and confirm the matrix still fits A4 landscape cleanly on the actual viewer/printer used in the hospital.
- Verify the business expectation for duplicate same-day shifts. Current rule keeps all recognized codes joined in one cell (example: `ช/บ`) instead of discarding data.
- Verify the business expectation for duplicate same-day shifts. Current rule is now:
  - explicit `BD` wins
  - one unique standard shift code wins
  - conflicting same-day standard shift codes produce a blank cell

## Manual test checklist

- Approval/review report Thai headings display correctly.
- Approval/review report Thai labels display correctly in print/PDF/CSV.
- Department monthly report reflects the selected department correctly.
- Department monthly report reflects the selected Thai month/year correctly.
- Department monthly report prints as A4 landscape.
- Department monthly report PDF exports as A4 landscape.
- One row per person appears in the matrix.
- Correct number of day columns appears for the selected month.
- `ช / บ / ด / BD` mapping appears correctly from real logs.
- `08:30–16:30` maps to `ช`.
- `16:30–00:30` maps to `บ`.
- `00:30–08:30` maps to `ด`.
- explicit `BD` marker maps to `BD`.
- unmatched or conflicting same-day records stay blank.
- Future days in the current incomplete month remain blank.
- Notes legend appears at the bottom.
- Signature section appears at the bottom.
- No PHP warnings/notices introduced.
- No JS errors introduced.
- No permission regression introduced.
