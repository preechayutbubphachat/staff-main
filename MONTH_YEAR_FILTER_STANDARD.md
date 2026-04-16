# Month Year Filter Standard

## มาตรฐานตัวกรองรายเดือน

ใช้ 2 control แยกจากกันเสมอ:

- `เดือน`
- `ปี (พ.ศ.)`

ห้ามใช้ control แบบรวมเดือนและปีในตัวเดียวสำหรับหน้ารายงานรายเดือนที่ผู้ใช้เห็น

## Month selector standard

- ใช้ `select name="month"`
- ค่าเป็นเลขเดือน `1..12`
- label เป็นชื่อเดือนภาษาไทย

รายชื่อเดือน:

1. มกราคม
2. กุมภาพันธ์
3. มีนาคม
4. เมษายน
5. พฤษภาคม
6. มิถุนายน
7. กรกฎาคม
8. สิงหาคม
9. กันยายน
10. ตุลาคม
11. พฤศจิกายน
12. ธันวาคม

## Year input standard

- ใช้ `input type="number" name="year_be"`
- label: `ปี (พ.ศ.)`
- ผู้ใช้พิมพ์ปีเองได้โดยตรง
- ช่วงที่ยอมรับในรอบนี้:
  - `2400` ถึง `2800`

## BE to CE conversion rule

- visible year:
  - `2569`
- internal year:
  - `2026`
- กฎแปลง:
  - `year_ce = year_be - 543`

## Shared helper approach

helper กลางที่ใช้:

- `app_parse_be_year()`
- `app_parse_month_year_filter()`
- `app_get_thai_month_select_options()`
- `app_format_thai_month_year()`

## Internal value strategy

แม้ UI จะแยก `month` และ `year_be` แต่ระบบยัง normalize ได้เป็น:

- `month_number`
- `year_be`
- `year_ce`
- `month_value` ในรูป `YYYY-MM`

ทำให้ query เดิมที่อิงปี/เดือนยังใช้งานต่อได้โดยไม่ต้อง parse ข้อความไทย

## Async behavior for typed year

- dropdown เดือน:
  - refresh เมื่อเปลี่ยนค่า
- input ปี (พ.ศ.):
  - refresh เมื่อ `change/blur`
  - refresh เมื่อกด `Enter`

## หน้าที่อัปเดตในรอบนี้

- [pages/department_reports.php](C:/xampp/htdocs/staff-main/pages/department_reports.php)
- [pages/my_reports.php](C:/xampp/htdocs/staff-main/pages/my_reports.php)
- [ajax/reports/department_rows.php](C:/xampp/htdocs/staff-main/ajax/reports/department_rows.php)
- [ajax/reports/my_report_rows.php](C:/xampp/htdocs/staff-main/ajax/reports/my_report_rows.php)
