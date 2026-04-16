# Queue And Notification Debug Report

## Expected Workflow
- เจ้าหน้าที่บันทึกรายการจาก `pages/time.php`
- ระบบบันทึก `time_logs` ในสถานะรอตรวจ
- รายการใหม่ต้องเข้าคิวของ `pages/approval_queue.php`
- reviewer/checker/admin ต้องมีสัญญาณว่ามีงานรอตรวจผ่าน notification bell หรือ notification queue summary
- เมื่อ reviewer อนุมัติ เจ้าของรายการจึงค่อยได้รับ approval notification

## Actual Broken Behavior Found
- จากการตรวจฐานข้อมูลจริง พบว่า:
  - รายการใหม่ถูกบันทึกลง `time_logs` สำเร็จ
  - รายการใหม่อยู่ในสถานะ pending จริง (`checked_at IS NULL`)
  - reviewer-side queue summary notification มีอยู่จริงในตาราง `notifications`
- ดังนั้นปัญหาไม่ได้อยู่ที่ “บันทึกไม่เข้า time_logs”
- อาการที่ผู้ใช้เห็นว่า “ไม่มีคิว” และ “ไม่มีแจ้งเตือน” เกิดจาก 2 จุดหลัก:

### Root Cause 1: Queue Notification Dedup ดูแค่จำนวนรวม
- logic เดิมใน `includes/notification_helpers.php` เปรียบเทียบ aggregate queue notification ด้วย `pending_count` เป็นหลัก
- ถ้ามีรายการใหม่เข้าคิว แต่จำนวนรวมคงเดิม เพราะมีอีกรายการถูกอนุมัติ/หายจากคิวในช่วงเวลาใกล้กัน:
  - queue composition เปลี่ยนจริง
  - แต่ `pending_count` เท่าเดิม
  - ระบบจะไม่สร้าง unread notification ใหม่
- ผลคือ reviewer อาจกด submit ฝั่ง staff แล้ว “ไม่เห็นแจ้งเตือนใหม่” ทั้งที่มีงานใหม่เข้าคิวจริง

### Root Cause 2: Approval Queue Entry Point เปิดโดยไม่มี URL State
- ลิงก์เดิมไป `approval_queue.php` ตรง ๆ
- ระบบมี page-state persistence อยู่แล้ว
- ถ้าผู้ใช้เคยมี filter/date range เก่าค้างไว้ หน้า queue อาจ restore state เก่ากลับมา
- ผลคือมี pending จริงในฐานข้อมูล แต่หน้า queue อาจดูเหมือนไม่มีงาน เพราะถูก filter เก่าซ่อนไว้

## Final Corrected Flow
- submit จาก `pages/time.php`
  - insert row พร้อมค่า pending แบบ explicit:
    - `status = 'submitted'`
    - `checked_by = NULL`
    - `checked_at = NULL`
    - `signature = NULL`
    - `approval_note = NULL`
  - เรียก `app_sync_reviewer_queue_notifications($conn)` หลัง save สำเร็จ
  - ตั้ง flash message ว่า “บันทึกรายการลงเวลาเวรเรียบร้อยแล้ว และส่งเข้าคิวตรวจสอบแล้ว”
- reviewer queue notification
  - aggregate summary ใช้ snapshot ของคิวแทนการดู count อย่างเดียว
  - metadata ที่ใช้เปรียบเทียบตอนนี้คือ:
    - `pending_count`
    - `latest_pending_id`
    - `pending_id_checksum`
  - ถ้าคิวเปลี่ยนจริง แม้ count เท่าเดิม ระบบจะ update/create unread notification ได้
- queue visibility
  - entry point หลักของ queue ถูกปรับให้เปิดด้วย `approval_queue.php?status=pending`
  - ช่วยลดโอกาสที่ filter/state เก่าจะ restore มาทับจนซ่อนคิว

## Notification Strategy After Fix
- reviewer side:
  - ใช้ aggregated queue notification
  - ไม่สร้าง row ต่อหนึ่ง time log
  - แต่จะ sync unread ใหม่เมื่อ snapshot ของคิวเปลี่ยน
- owner side:
  - ยังไม่ได้รับ notification ตอน submit
  - จะได้รับเมื่อรายการถูกอนุมัติจริง

## Files Changed
- `C:\xampp\htdocs\staff-main\includes\notification_helpers.php`
- `C:\xampp\htdocs\staff-main\pages\time.php`
- `C:\xampp\htdocs\staff-main\includes\navigation.php`
- `C:\xampp\htdocs\staff-main\assets\css\app-ui.css`

## Manual Verification Focus
- submit log ใหม่จาก staff user
- ตรวจใน reviewer account ว่ามี unread queue notification หรือ badge update
- เปิด `approval_queue.php` จาก navbar/notification ว่ายังเห็น pending queue โดยไม่โดน filter เก่าซ่อน
- อนุมัติรายการแล้วตรวจว่า owner ได้ approval notification ตามปกติ
