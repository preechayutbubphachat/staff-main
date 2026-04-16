# Department Report Heading Notes

## Heading Logic For All Departments
- ถ้า `department_id` ว่างหรือเท่ากับ 0 ระบบจะถือว่าเป็นมุมมองรวม
- heading หลักใช้คำว่า `แผนกทั้งหมด`
- heading เต็มถูกสร้างในรูปแบบ:
  - `รายงานสรุปแผนกทั้งหมด ประจำเดือน <เดือน> <ปี พ.ศ.>`

## Heading Logic For A Specific Department
- ถ้า `department_id` มีค่าและอยู่ในขอบเขตสิทธิ์ที่เข้าถึงได้
- heading หลักใช้ชื่อแผนกจริง เช่น:
  - `รายงานสรุปแผนก IPD ประจำเดือน เมษายน 2569`
  - `รายงานสรุปแผนก ห้องฉุกเฉิน ประจำเดือน เมษายน 2569`

## Thai Month/Year Formatting Used
- ใช้ชื่อเดือนภาษาไทยจาก helper เดือนของระบบ
- แสดงปีเป็น พ.ศ.
- helper กลางที่ใช้:
  - `app_format_thai_month_year()`

## Where The Heading Is Rendered
- หน้าเว็บ:
  - `C:\xampp\htdocs\staff-main\partials\reports\department_results.php`
- partial refresh:
  - `C:\xampp\htdocs\staff-main\ajax\reports\department_rows.php`
- print/PDF:
  - `C:\xampp\htdocs\staff-main\pages\report_print.php`
- CSV:
  - `C:\xampp\htdocs\staff-main\pages\export_report.php`

## Source Of Truth
- ใช้ helper กลาง `app_get_department_report_heading_context()` เพื่อให้:
  - department label
  - month/year label
  - heading text
  - subheading text
  มาจาก filter state เดียวกันทั้งหมด
