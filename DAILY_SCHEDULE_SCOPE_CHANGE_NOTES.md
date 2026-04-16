## Daily Schedule Scope Change Notes

### Previous Behavior

- หน้า daily schedule เคยอิง helper ขอบเขตสิทธิ์ตามแผนก ทำให้ผู้ใช้ทั่วไปเห็นเฉพาะแผนกที่ตัวเองเข้าถึงได้
- ผลคือหน้าเวรวันนี้ไม่ตอบโจทย์การประสานงานข้ามแผนก แม้หน้าดังกล่าวมีเบอร์โทรและมีจุดประสงค์เชิงปฏิบัติการชัดเจน

### New Operational Rule

- daily schedule ถูกกำหนดให้เป็นหน้าประสานงานประจำวัน
- ผู้ใช้ที่ล็อกอินทุกคนสามารถดูเวรของทุกแผนกในระบบได้
- ผู้ใช้ยังกรองกลับมาเฉพาะแผนกเดียวได้เมื่อต้องการ

### Where It Was Changed

- [includes/report_helpers.php](C:/xampp/htdocs/staff-main/includes/report_helpers.php)
  - เพิ่ม `app_get_daily_schedule_departments()`
  - เปลี่ยน `app_fetch_daily_schedule_data()` ให้ใช้ขอบเขตทุกแผนกสำหรับหน้านี้โดยเฉพาะ
- [pages/daily_schedule.php](C:/xampp/htdocs/staff-main/pages/daily_schedule.php)
  - เปลี่ยน option ของตัวกรองแผนกและข้อความขอบเขตให้ตรงกับกติกาใหม่
- [ajax/reports/daily_schedule_rows.php](C:/xampp/htdocs/staff-main/ajax/reports/daily_schedule_rows.php)
  - ทำให้ผลลัพธ์ที่รีเฟรชแบบ async ใช้ scope แบบเดียวกับหน้าแรก
- [pages/report_print.php](C:/xampp/htdocs/staff-main/pages/report_print.php)
  - subtitle ของ print/PDF ใช้ข้อความขอบเขตใหม่
- [pages/export_report.php](C:/xampp/htdocs/staff-main/pages/export_report.php)
  - CSV ใช้ข้อความขอบเขตใหม่

### Heading and Export Scope Behavior

- ถ้าไม่ได้เลือกแผนก: แสดง “ทุกแผนกในระบบ”
- ถ้าเลือกแผนก: แสดง “แสดงข้อมูลเฉพาะแผนก {ชื่อแผนก}”
- ข้อความนี้ถูกใช้ให้สอดคล้องกันระหว่าง:
  - หน้าแสดงผล
  - partial refresh
  - print/PDF
  - CSV export

### Intentional Exception

- การขยายการมองเห็นนี้ใช้กับหน้า daily schedule เท่านั้น
- หน้าอนุมัติ, หน้าจัดการ, รายงานแผนก และหน้าที่มีข้อมูลอ่อนไหวอื่นยังคงใช้กติกาสิทธิ์เดิมตาม helper และ permission ที่มีอยู่
