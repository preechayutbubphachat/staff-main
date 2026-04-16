# Time Delete Flow Notes

## Frontend Flow
- ผู้ใช้เปิด modal แก้ไขรายการจาก `pages/time.php`
- ปุ่ม `ลบรายการ` ใน modal ใช้ handler ตรงจาก `assets/js/time-page.js`
- เมื่อกดลบ:
  1. แสดง browser confirm
  2. ถ้ายืนยัน จะส่ง `POST` ไปที่ `ajax/time/delete_time_log.php`
  3. ส่ง `id` และ `delete_csrf` จาก hidden input ใน modal
  4. ระหว่างส่ง request ปุ่มลบจะถูก disable ชั่วคราว

## Backend Validation
- ต้อง login อยู่
- ต้องเป็น `POST`
- ต้องผ่าน CSRF ของ `time_page_delete`
- ต้องเป็นรายการของผู้ใช้คนนั้น
- ต้องยังไม่อนุมัติ/ไม่ล็อก เว้นแต่มีสิทธิ์พิเศษ

## Delete + Audit
- endpoint บันทึก audit snapshot ก่อนลบ
- ใช้ transaction เพื่อให้ผลลัพธ์เสถียร
- schema ถูกปรับให้ `time_log_audit_trails.time_log_id` เป็น nullable และ `ON DELETE SET NULL`
- ทำให้ลบ `time_logs` ได้โดยยังคงข้อมูล audit เดิมไว้

## UI Result
- ถ้าลบสำเร็จ:
  - modal ปิด
  - แสดงข้อความสำเร็จ
  - หน้า `time.php` reload อัตโนมัติ
- ถ้าลบไม่สำเร็จ:
  - modal ยังอยู่
  - แสดงข้อความ error ใน modal
  - ไม่มี silent failure
