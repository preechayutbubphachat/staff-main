## Checked items

- Daily Schedule Thai text in page labels.
- Daily Schedule Thai text in result partial / AJAX refresh path.
- Department monthly matrix landscape width.
- Vertical header readability for narrow end columns.
- Print/PDF report output alignment.
- Signature block structure.

## Findings addressed

- Daily Schedule still had real placeholder literals (`????`) in page and partial source files.
- The AJAX render path for Daily Schedule did not pass the full results context consistently into the shared partial.
- Department monthly matrix width became too wide after adding end-summary columns.
- Narrow end columns benefited from vertical header treatment in print layout.

## Manual follow-up still recommended

- Open [daily_schedule.php](C:/xampp/htdocs/staff-main/pages/daily_schedule.php) and confirm all visible labels render as Thai, not `????`.
- Open the Department monthly print view from [department_reports.php](C:/xampp/htdocs/staff-main/pages/department_reports.php) and confirm the matrix fits A4 landscape on the actual browser/printer combination used.
- Generate PDF from the department monthly report and confirm vertical headers do not clip.
- Confirm whether a real reviewer name should later populate the parenthesized signature line.

## Manual test checklist

- Daily Schedule page headings are correct in Thai.
- Daily Schedule filter labels are correct in Thai.
- Daily Schedule summary/context labels are correct in Thai.
- Daily Schedule AJAX refresh still shows Thai correctly after filter changes.
- Department monthly report fits landscape A4 better.
- `จำนวนเวร` header is readable.
- `ชั่วโมงรวม` header is readable.
- `OT` header is readable.
- Day columns still align properly.
- Matrix body is still readable.
- Print preview looks correct.
- PDF output looks correct.
- No clipped or overlapping text appears.
- Signature line appears.
- Parenthesized line appears below the signature line.
- `ผู้ตรวจสอบเวร` appears below the parentheses line.
- No PHP warnings/notices introduced.
- No JS errors introduced.
