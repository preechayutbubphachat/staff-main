# Time Save Flow Debug Report

## Expected Workflow
- ผู้ใช้เปิดหน้า `C:\xampp\htdocs\staff-main\pages\time.php`
- กรอกเวลาเข้า เวลาออก แผนก และหมายเหตุ
- กด `บันทึกการปฏิบัติงาน`
- ระบบส่ง request ไปยัง backend ของหน้าเดียวกัน
- backend สร้าง row ใหม่ใน `time_logs`
- row ใหม่ต้องอยู่ในสถานะ pending
- หน้า `time.php` ต้องแสดงข้อความสำเร็จและเห็นรายการใหม่ในประวัติย้อนหลังทันที
- reviewer/checker/admin ต้องเห็นรายการเดียวกันใน approval queue

## Actual Broken Behavior Found
- ผู้ใช้กด `บันทึกการปฏิบัติงาน` แล้วหน้าเหมือน submit สำเร็จ แต่:
  - ไม่เกิดรายการใหม่ในประวัติย้อนหลัง
  - ไม่เกิดรายการ pending ใน approval queue
  - ไม่มีข้อความสำเร็จที่สอดคล้องกับการสร้าง row ใหม่
- อาการนี้ไม่ใช่แค่ UI ไม่ refresh แต่เป็นกรณีที่ PHP ไม่เข้า branch สร้างรายการใหม่ตั้งแต่ต้น

## Exact Root Cause
- ฟอร์มสร้างรายการใน `C:\xampp\htdocs\staff-main\pages\time.php` ใช้ native form submit และพึ่งชื่อปุ่ม submit เดิมคือ `save_all_time`
- shared loading helper ใน `C:\xampp\htdocs\staff-main\assets\js\global-loading.js` ผูกกับ `form[data-global-loading-form]`
- ตอนเกิด submit helper จะเรียก `showGlobalLoading(..., { trigger: submitter })`
- ภายใน flow นี้ `submitter` ถูก disable ทันทีเพื่อกันกดซ้ำ
- เมื่อ submit button ถูก disable ระหว่าง native submit ค่าปุ่ม `save_all_time` อาจไม่ถูก serialize ไปใน POST
- ฝั่ง PHP เดิมใช้เงื่อนไข:
  - `if (isset($_POST['save_all_time'])) { ... }`
- เมื่อ `save_all_time` ไม่ถูกส่งมา request จึงไม่เข้า create branch
- ผลลัพธ์คือ:
  - ไม่มี insert
  - ไม่มี history ใหม่
  - ไม่มี pending queue ใหม่
  - ผู้ใช้เห็นเหมือนกดแล้วเงียบ

## Corrected Request / Response Flow
- ใน `C:\xampp\htdocs\staff-main\assets\js\global-loading.js`
  - เก็บ `name=value` ของ submitter ลง hidden input ก่อน disable ปุ่ม
  - ช่วยให้ฟอร์มที่ใช้ submit button เป็น action marker ยังส่ง action เดิมได้
- ใน `C:\xampp\htdocs\staff-main\pages\time.php`
  - เพิ่ม hidden field `create_time_log=1` ในฟอร์มสร้างรายการ
  - เปลี่ยนเงื่อนไข backend เป็น:
    - `($_POST['create_time_log'] ?? '') === '1' || isset($_POST['save_all_time'])`
  - ทำให้ create flow ทนต่อการเปลี่ยนแปลงของ submitter มากขึ้น
- เมื่อ save สำเร็จ:
  - insert row ใหม่ลง `time_logs`
  - เรียก `app_sync_reviewer_queue_notifications($conn)`
  - set flash success message
  - redirect กลับ `time.php?date=<today>&p=1`

## Corrected History Refresh Behavior
- เดิมหลังบันทึกสำเร็จหน้าอาจกลับมาด้วย state/date เก่าจนทำให้ผู้ใช้ไม่เห็นรายการที่เพิ่งสร้าง
- ตอนนี้หลัง save สำเร็จจะ redirect กลับพร้อม `date=<today>` และ `p=1`
- ผลคือ history ฝั่งขวาจะอยู่ใน scope ของวันที่เพิ่งบันทึกและเห็นรายการใหม่ทันที

## Corrected Pending Queue Behavior
- สร้างรายการใหม่ด้วยค่าที่สอดคล้องกับสถานะ pending อย่างชัดเจน:
  - `status = 'submitted'`
  - `checked_by = NULL`
  - `checked_at = NULL`
  - `signature = NULL`
  - `approval_note = NULL`
- กติกา pending ถูกใช้ร่วมกับ approval queue ผ่าน helper กลาง
- เมื่อสร้างรายการสำเร็จ row ใหม่จึงเข้าเงื่อนไข pending และพร้อมให้ reviewer เห็นใน queue

## Files Changed
- `C:\xampp\htdocs\staff-main\assets\js\global-loading.js`
- `C:\xampp\htdocs\staff-main\pages\time.php`
- `C:\xampp\htdocs\staff-main\includes\auth.php`
- `C:\xampp\htdocs\staff-main\includes\report_helpers.php`
- `C:\xampp\htdocs\staff-main\includes\notification_helpers.php`
- `C:\xampp\htdocs\staff-main\includes\navigation.php`
- `C:\xampp\htdocs\staff-main\pages\dashboard.php`

## Reviewer Notification Dependency
- notification ฝั่ง reviewer อาศัยการที่รายการใหม่ถูกสร้างเป็น pending ก่อน
- เมื่อต้นทาง save-flow ไม่เข้า create branch notification จึงไม่มี event ให้ sync
- หลังแก้ save-flow แล้ว reviewer queue notification สามารถทำงานตาม pending-state จริงได้
