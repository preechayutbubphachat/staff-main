## Bug Audit

### First-Load Warning Checked

- แก้ warning `Undefined variable $dateHeading` ที่ [partials/reports/daily_schedule_results.php](C:/xampp/htdocs/staff-main/partials/reports/daily_schedule_results.php)
- partial ไม่พึ่งตัวแปรที่บาง path อาจไม่ได้ส่งมาอีกต่อไป
- ใช้ `heading_context` จาก `$schedule` เป็นหลัก และมี fallback helper เผื่อกรณี render context ไม่ครบ

### AJAX / Partial Render Warning Checked

- [ajax/reports/daily_schedule_rows.php](C:/xampp/htdocs/staff-main/ajax/reports/daily_schedule_rows.php) ถูกปรับให้ใช้ `heading_context` จากผลลัพธ์เดียวกับ first load
- heading และ scope ของ partial refresh จึงใช้ source เดียวกับหน้าเต็ม

### Heading / Filter Sync Checked

- main heading ขึ้นกับ:
  - วันที่
  - แผนกที่เลือก
- scope label และ table context label มาจาก helper เดียวกัน
- เมื่อเปลี่ยน filter แล้ว heading ไม่ควรหลุดไปคนละแผนกกับข้อมูลในตาราง

### Grouped Count Labels Checked

- `app_group_daily_schedule_rows_by_shift(...)` เติมข้อมูล `item_count`, `time_range_label`, `heading_text` ให้แต่ละกลุ่ม
- เวรเช้า / เวรบ่าย / เวรดึก แสดงช่วงเวลามาตรฐาน
- `เวลาอื่นๆ` แสดงเฉพาะชื่อกลุ่มและจำนวนรายการ ไม่มีเวลาคงที่ต่อท้าย

### Syntax Check

ตรวจผ่าน `C:\xampp\php\php.exe -l` แล้วสำหรับ:

- [includes/report_helpers.php](C:/xampp/htdocs/staff-main/includes/report_helpers.php)
- [pages/daily_schedule.php](C:/xampp/htdocs/staff-main/pages/daily_schedule.php)
- [partials/reports/daily_schedule_results.php](C:/xampp/htdocs/staff-main/partials/reports/daily_schedule_results.php)
- [ajax/reports/daily_schedule_rows.php](C:/xampp/htdocs/staff-main/ajax/reports/daily_schedule_rows.php)

### Manual Follow-Up

- เปิดหน้า daily schedule ครั้งแรกหลังล็อกอินและยืนยันว่าไม่มี warning
- เปลี่ยนแผนกแล้วดูว่า heading หลักเปลี่ยนเป็นชื่อแผนกที่เลือก
- เลือกทุกแผนกแล้วดูว่า heading หลักกลับเป็น “ทุกแผนก”
- ตรวจว่า group header ของ เวรเช้า / เวรบ่าย / เวรดึก แสดงช่วงเวลาคงที่ถูกต้อง
- ตรวจว่า `เวลาอื่นๆ` ไม่มี suffix เวลา
