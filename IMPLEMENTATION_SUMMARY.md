## Implementation Summary

งานรอบนี้แก้ daily schedule ใน 3 จุดหลัก:

1. แก้ warning `Undefined variable $dateHeading` ที่เกิดตอน first load
2. ย้าย logic ของ heading context ไปไว้ใน helper กลาง
3. ปรับหัวข้อหลักและหัวข้อกลุ่มเวรให้สื่อแผนก + วันที่ไทย + จำนวนรายการ + ช่วงเวลาเวรมาตรฐานชัดขึ้น

### Fix For Undefined Variable

- partial [partials/reports/daily_schedule_results.php](C:/xampp/htdocs/staff-main/partials/reports/daily_schedule_results.php) ไม่พึ่งตัวแปร `$dateHeading` แบบลอย ๆ อีกต่อไป
- partial จะอ่าน `heading_context` จาก `$schedule` ก่อน และ fallback ผ่าน helper กลางหากจำเป็น
- ฝั่ง [pages/daily_schedule.php](C:/xampp/htdocs/staff-main/pages/daily_schedule.php) และ [ajax/reports/daily_schedule_rows.php](C:/xampp/htdocs/staff-main/ajax/reports/daily_schedule_rows.php) ใช้ context ชุดเดียวกัน

### Daily Schedule Heading

- เพิ่ม helper `app_get_daily_schedule_heading_context(...)` ใน [includes/report_helpers.php](C:/xampp/htdocs/staff-main/includes/report_helpers.php)
- heading หลักของผลลัพธ์ตอนนี้อยู่ในรูป:
  - `รายงานเวรประจำวัน แผนก {ชื่อแผนก} ประจำวันที่ {วันที่ไทย}`
  - หรือ `รายงานเวรประจำวัน ทุกแผนก ประจำวันที่ {วันที่ไทย}`
- kicker/context line ของตารางก็แสดงบริบทแผนกด้วยเช่นกัน

### Grouped Shift Headers

- เพิ่ม helper `app_get_daily_shift_group_display_meta(...)`
- หัวข้อกลุ่มเวรตอนนี้แสดงแบบ:
  - `เวรเช้า / 2 รายการ / เวลา 08.30 น. - 16.30 น.`
  - `เวรบ่าย / 2 รายการ / เวลา 16.30 น. - 00.30 น.`
  - `เวรดึก / 2 รายการ / เวลา 00.30 น. - 08.30 น.`
  - `เวลาอื่นๆ / 2 รายการ`
- กลุ่ม `เวลาอื่นๆ` ไม่มี suffix เวลาคงที่ตามที่กำหนด

### Files Changed

- [includes/report_helpers.php](C:/xampp/htdocs/staff-main/includes/report_helpers.php)
- [pages/daily_schedule.php](C:/xampp/htdocs/staff-main/pages/daily_schedule.php)
- [partials/reports/daily_schedule_results.php](C:/xampp/htdocs/staff-main/partials/reports/daily_schedule_results.php)
- [ajax/reports/daily_schedule_rows.php](C:/xampp/htdocs/staff-main/ajax/reports/daily_schedule_rows.php)
- [IMPLEMENTATION_SUMMARY.md](C:/xampp/htdocs/staff-main/IMPLEMENTATION_SUMMARY.md)
- [DAILY_SCHEDULE_HEADING_AND_GROUP_NOTES.md](C:/xampp/htdocs/staff-main/DAILY_SCHEDULE_HEADING_AND_GROUP_NOTES.md)
- [BUG_AUDIT.md](C:/xampp/htdocs/staff-main/BUG_AUDIT.md)
