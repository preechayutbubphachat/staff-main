# BUG AUDIT

## ตรวจแล้ว

- landscape print ถูกตั้งไว้ใน [report_print.php](C:/xampp/htdocs/staff-main/pages/report_print.php)
- landscape PDF ถูกตั้งไว้ใน jsPDF ของ [report_print.php](C:/xampp/htdocs/staff-main/pages/report_print.php)
- daily schedule รองรับ `mode=daily|monthly` ใน:
  - [pages/daily_schedule.php](C:/xampp/htdocs/staff-main/pages/daily_schedule.php)
  - [ajax/reports/daily_schedule_rows.php](C:/xampp/htdocs/staff-main/ajax/reports/daily_schedule_rows.php)
- monthly matrix ถูกสร้างจาก helper กลางใน [report_helpers.php](C:/xampp/htdocs/staff-main/includes/report_helpers.php)
- footer notes + signature ถูกเพิ่มใน layout พิมพ์
- metadata card `เวลาที่สร้างรายงาน` ถูกย้ายขึ้น header report แล้ว
- CSV ของ daily report รองรับทั้ง daily และ monthly mode ใน [export_report.php](C:/xampp/htdocs/staff-main/pages/export_report.php)

## กติกาที่ตรวจซ้ำ

- จำนวนวันของเดือนใช้ค่า dynamic ตามเดือนจริง
- วันในอนาคตของเดือนปัจจุบันถูกปล่อยว่าง
- mapping `ช / บ / ด / BD` อยู่ใน helper กลาง ไม่ได้กระจายหลายไฟล์
- print / PDF / CSV ใช้ context เดียวกับหน้า daily schedule

## Manual Follow-up ที่ยังควรเช็ก

- ทดลองพิมพ์เดือน 31 วันจริงบนเครื่องพิมพ์หรือ PDF viewer จริง เพื่อดูความแน่นของคอลัมน์
- ทดลองกรณีหนึ่งคนมีหลายเวรในวันเดียวและยืนยันว่ารูปแบบ `ช/บ` อ่านได้ตามที่หน่วยงานต้องการ
- ตรวจดู wording ในเอกสารจริงอีกครั้งบนเครื่องผู้ใช้ เพราะ console ของ Windows ใน environment นี้แสดงภาษาไทยเพี้ยน แต่ไฟล์ lint ผ่านและบันทึกเป็น UTF-8 แล้ว

## Syntax Check

- [includes/report_helpers.php](C:/xampp/htdocs/staff-main/includes/report_helpers.php)
- [pages/daily_schedule.php](C:/xampp/htdocs/staff-main/pages/daily_schedule.php)
- [partials/reports/daily_schedule_results.php](C:/xampp/htdocs/staff-main/partials/reports/daily_schedule_results.php)
- [ajax/reports/daily_schedule_rows.php](C:/xampp/htdocs/staff-main/ajax/reports/daily_schedule_rows.php)
- [pages/report_print.php](C:/xampp/htdocs/staff-main/pages/report_print.php)
- [pages/export_report.php](C:/xampp/htdocs/staff-main/pages/export_report.php)
