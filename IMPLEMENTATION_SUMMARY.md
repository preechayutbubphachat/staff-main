## สิ่งที่ปรับในรอบนี้

- ปรับ `report_print.php` ให้เป็นเอกสารแนวนอน A4 ทั้งโหมดพิมพ์และดาวน์โหลด PDF
- ย้ายข้อมูล `จัดทำโดย` และ `พิมพ์เมื่อ` จากกล่อง metadata เดิมขึ้นไปอยู่ในส่วนหัวรายงาน
- ปรับ daily schedule ให้รองรับ `โหมดรายวัน` และ `โหมดรายเดือน`
- เพิ่ม monthly shift matrix แบบ 1 คนต่อ 1 แถว และ 1 วันต่อ 1 คอลัมน์
- ทำให้ print / PDF / CSV ใช้ filter context ชุดเดียวกับหน้า daily schedule
- เพิ่ม footer ส่วน `หมายเหตุ` และพื้นที่ `ลงชื่อผู้ตรวจสอบเวร`

## Landscape Print / PDF

- ใช้ `@page { size: A4 landscape; }` ใน [report_print.php](C:/xampp/htdocs/staff-main/pages/report_print.php)
- เปลี่ยน jsPDF เป็น `new jsPDF('l', 'mm', 'a4')`
- ตารางรายเดือนและตารางรายวันจึงพอดีกับงานพิมพ์แนวนอนมากขึ้น

## Monthly Matrix Report

- เพิ่ม helper สำหรับสร้าง monthly matrix ใน [report_helpers.php](C:/xampp/htdocs/staff-main/includes/report_helpers.php)
- ใช้รหัสเวร:
  - `ช` = เวรเช้า 08.30 - 16.30 น.
  - `บ` = เวรบ่าย 16.30 - 00.30 น.
  - `ด` = เวรดึก 00.30 - 08.30 น.
  - `BD` = เวรบ่ายนอกเวลาราชการ
- ถ้าวันใดมีหลายรายการในวันเดียว ระบบจะรวมรหัสที่พบเป็นรูปแบบเช่น `ช/บ` แทนการเดาเลือกเพียงรายการเดียว
- ถ้าเป็นเดือนปัจจุบัน วันในอนาคตจะถูกเว้นว่างเสมอ

## Header / Footer รายงาน

- หัวรายงานถูกจัดใหม่ให้มี:
  - ชื่อหน่วยงาน
  - ชื่อระบบ
  - ชื่อรายงาน
  - ขอบเขตรายงานตามตัวกรอง
  - ผู้จัดทำ
  - เวลาพิมพ์
  - รูปแบบเอกสารแนวนอน
- footer มี:
  - legend ของรหัสเวร
  - หมายเหตุสำหรับเดือนที่ยังไม่ครบ
  - เส้นลงชื่อผู้ตรวจสอบเวร

## ไฟล์ที่แก้ไข

- [includes/report_helpers.php](C:/xampp/htdocs/staff-main/includes/report_helpers.php)
- [pages/daily_schedule.php](C:/xampp/htdocs/staff-main/pages/daily_schedule.php)
- [partials/reports/daily_schedule_results.php](C:/xampp/htdocs/staff-main/partials/reports/daily_schedule_results.php)
- [ajax/reports/daily_schedule_rows.php](C:/xampp/htdocs/staff-main/ajax/reports/daily_schedule_rows.php)
- [pages/report_print.php](C:/xampp/htdocs/staff-main/pages/report_print.php)
- [pages/export_report.php](C:/xampp/htdocs/staff-main/pages/export_report.php)
- [assets/css/app-ui.css](C:/xampp/htdocs/staff-main/assets/css/app-ui.css)
