# Queue Entry Flow Report

## Expected Workflow
- เจ้าหน้าที่บันทึกรายการจาก `pages/time.php`
- ระบบสร้างแถวใหม่ใน `time_logs`
- รายการใหม่ต้องอยู่ในสถานะรอตรวจ
- reviewer/checker/admin ต้องเห็นรายการนี้ใน `pages/approval_queue.php` ตามขอบเขตสิทธิ์

## Actual Broken Behavior Found
- การบันทึกจากหน้าลงเวลาเวรสำเร็จจริง และมีการ insert แถวใหม่ใน `time_logs`
- แถวใหม่ถูกบันทึกเป็น pending จริงผ่านค่า `checked_by = NULL` และ `checked_at = NULL`
- ปัญหาที่ทำให้ผู้ใช้เข้าใจว่า “ไม่มีคิว” ไม่ได้เกิดจาก insert หาย
- root cause ที่พบมี 2 ส่วน:

### 1. Reviewer queue notification aggregate ไม่ไวพอ
- logic เดิมเทียบแค่ `pending_count`
- ถ้ามีรายการใหม่เข้าคิว แต่จำนวนรวมยังเท่าเดิม ระบบจะไม่สร้าง unread ใหม่
- reviewer จึงอาจไม่เห็นสัญญาณว่ามีงานใหม่เข้ามา

### 2. Approval queue ถูกซ่อนด้วย page-state/filter เดิมได้
- ลิงก์เข้า queue เดิมใช้ `approval_queue.php` ตรง ๆ
- เมื่อระบบ restore filter/page state เก่า หน้า queue อาจเปิดด้วยเงื่อนไขเดิมที่ซ่อนรายการ pending ใหม่
- ผลคือมี pending จริงในฐานข้อมูล แต่หน้าจอดูเหมือนไม่มีรายการ

## Corrected Pending-State Rules
- กติกากลางของ pending ถูกทำให้ explicit และใช้ร่วมกัน:
  - `time_out IS NOT NULL`
  - `checked_at IS NULL`
- เพิ่ม helper กลางใน `includes/auth.php`
  - `app_time_log_pending_condition()`
  - `app_time_log_checked_condition()`
  - `app_time_log_is_pending()`

## Corrected Approval Queue Matching Rules
- `pages/time.php`
  - insert ใหม่ด้วยค่า pending แบบ explicit:
    - `status = 'submitted'`
    - `checked_by = NULL`
    - `checked_at = NULL`
    - `signature = NULL`
    - `approval_note = NULL`
- `includes/report_helpers.php`
  - approval queue filters และ bulk approve logic ใช้ helper กลางของ pending มากขึ้น
- `includes/notification_helpers.php`
  - reviewer queue snapshot ใช้:
    - `pending_count`
    - `latest_pending_id`
    - `pending_id_checksum`
  - ทำให้ detect queue change ได้แม้ count ไม่เปลี่ยน
- `includes/navigation.php`
  - entry point ของ queue ถูกปรับเป็น `approval_queue.php?status=pending`
  - ช่วยกันไม่ให้ filter เก่าซ่อนคิวใหม่

## Final Corrected Flow
- staff submit log จาก `time.php`
- ระบบ insert `time_logs` เป็น pending
- ระบบ sync reviewer queue notification หลัง save สำเร็จ
- reviewer เปิด bell หรือเข้า queue แล้วเห็นคิว pending ที่ถูกต้อง
- เมื่อ reviewer อนุมัติ เจ้าของรายการจึงได้รับ approval notification ตาม workflow เดิม

## Files Changed In This Fix
- `C:\xampp\htdocs\staff-main\pages\time.php`
- `C:\xampp\htdocs\staff-main\includes\auth.php`
- `C:\xampp\htdocs\staff-main\includes\report_helpers.php`
- `C:\xampp\htdocs\staff-main\includes\notification_helpers.php`
- `C:\xampp\htdocs\staff-main\includes\navigation.php`
- `C:\xampp\htdocs\staff-main\pages\dashboard.php`
- `C:\xampp\htdocs\staff-main\assets\css\app-ui.css`
