## Thai font / encoding solution used

The remaining Thai-rendering issue was not only a font problem. Two things were checked and aligned:

- report templates already output UTF-8 content
- remaining broken labels were placeholder literals left in the page/template source

The fix used was:

- keep UTF-8 output paths in report templates
- keep UTF-8 BOM for CSV export
- keep Thai-safe web fonts in report print templates
- replace leftover placeholder/broken Thai labels in report-related pages with real Thai strings

Pages/files updated for this round:

- [daily_schedule.php](C:/xampp/htdocs/staff-main/pages/daily_schedule.php)
- [report_print.php](C:/xampp/htdocs/staff-main/pages/report_print.php)
- [export_report.php](C:/xampp/htdocs/staff-main/pages/export_report.php)
- [report_helpers.php](C:/xampp/htdocs/staff-main/includes/report_helpers.php)

## Final monthly matrix end-column meanings

Columns added after the day columns:

- `จำนวนเวร`
  - total counted shifts for that person in the selected month
  - counts only days that resolved to a safe visible code in the matrix

- `ชั่วโมงรวม`
  - total hours for the counted monthly matrix shifts under the same scope
  - if a day resolves to blank, that day does not contribute to the total

- `OT`
  - blank by default in the current implementation
  - current `time_logs` schema does not provide a reliable structured OT source for this report

- `หมายเหตุ`
  - blank by default in the current monthly matrix
  - no reliable month-level summary note source exists in the current data model

## OT handling rule

`OT` is not fabricated.

- if no real structured OT source exists -> blank
- no guessed OT count
- no guessed OT hours

## Note handling rule

`หมายเหตุ` is not fabricated.

- if no reliable month-level note source exists -> blank
- no synthetic note text is generated from scattered daily notes

## Final signature structure

The signature area now renders in this order:

- `ลงชื่อ ...............................................................`
- `(...............................................................)`
- `ผู้ตรวจสอบเวร`

If a future workflow provides a reliable reviewer name, the parenthesized line can be filled with that real name. For now it stays blank for manual signing.
