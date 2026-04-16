# Page State Strategy

## วิธีที่เลือกใช้
- ใช้ `localStorage`

## เหตุผลที่เลือก
- เหมาะกับ UI convenience state มากกว่า cookies
- ไม่ต้องส่งค่ากลับไปกับทุก request
- เก็บ JSON ได้ตรงไปตรงมาและแก้ไขได้ง่าย
- ถ้า storage ใช้งานไม่ได้ ระบบยัง fallback เป็น PHP ปกติได้

## รูปแบบ key
- state key:
  - `staff_main:{user_id}:{page_key}:state`
- marker key:
  - `staff_main:{user_id}:login_marker`

## ข้อมูลที่ถูกเก็บ
- คำค้นหา
- dropdown filters
- rows-per-page
- วันที่ / เดือน / ช่วงวันที่
- hidden view state ที่อยู่ใน filter form เดียวกัน

## ข้อมูลที่ตั้งใจไม่เก็บ
- modal เปิดอยู่หรือไม่
- checkbox selection
- flash message
- token หรือข้อมูล auth
- ข้อมูล sensitive ของระบบ

## การผูกกับผู้ใช้
- state ทุกชุดถูกผูกกับ `user_id`
- จึงไม่ปะปนกันตรง ๆ ระหว่างผู้ใช้คนละคนบนเครื่องเดียวกัน

## การ invalidate ตอน login ใหม่
- ตอน login ระบบสร้าง `ui_state_login_marker` ใหม่ใน PHP session
- ฝั่ง frontend จะอ่าน marker นี้จาก navbar dataset
- ถ้า marker ใน `localStorage` ไม่ตรงกับ marker ปัจจุบัน:
  - state ทั้งหมดของ user คนนั้นจะถูกล้าง
  - จากนั้นจึงเริ่มเก็บ state รอบใหม่

## การล้างตอน logout
- `auth/logout.php` พยายามล้าง key prefix ของ user ปัจจุบันจาก `localStorage`
- ถ้าล้างไม่ได้ marker ใหม่หลัง login รอบถัดไปก็ยังช่วยกัน state เก่าถูก restore อยู่ดี

## การ restore
- restore จะทำเฉพาะตอนหน้าเปิดโดยไม่มี query/filter ที่ชัดเจนอยู่ใน URL
- ถ้าผู้ใช้เปิดหน้าพร้อม query string ใหม่ ระบบจะเชื่อค่าจาก URL ก่อน ไม่ทับด้วย state เก่า

## หน้าที่ผูกแล้วในรอบนี้
- `approval_queue`
- `manage_time_logs`
- `daily_schedule`
- `department_reports`
- `my_reports`
- `time_history`
- `manage_users`
- `db_table_browser`
- `db_change_logs`
