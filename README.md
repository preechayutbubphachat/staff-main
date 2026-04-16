# Staff Time Attendance

เอกสารฉบับนี้อธิบายสถานะปัจจุบันของโปรเจกต์ `staff-main` แบบละเอียด เพื่อให้คนที่เข้ามาดูโค้ดต่อเข้าใจได้เร็วว่าโปรเจกต์นี้คืออะไร ทำงานอย่างไร มีไฟล์สำคัญตรงไหน และข้อมูลไหลผ่านระบบแบบใด

## 1. ภาพรวมโปรเจกต์

โปรเจกต์นี้เป็นระบบลงเวลาเวรสำหรับบุคลากรโรงพยาบาล พัฒนาแบบ PHP + MySQL โดยใช้โครงสร้างไฟล์ตรงไปตรงมา ไม่ได้ใช้ framework ขนาดใหญ่ เช่น Laravel

แนวคิดหลักของระบบตอนนี้มี 4 ส่วน:

1. ระบบยืนยันตัวตนและกำหนดสิทธิ์ผู้ใช้
2. ระบบลงเวลาเวรของรายบุคคล
3. ระบบรายงาน ทั้งรายบุคคล รายแผนก และรายวัน
4. ระบบตรวจสอบ/อนุมัติข้อมูลสำหรับผู้มีสิทธิ์

ระบบถูกออกแบบใหม่ให้ใช้ role-based access มากขึ้น แทนการให้ผู้ใช้เลือกโหมดเองตอน login แบบเดิม

## 2. เป้าหมายของระบบ

ระบบนี้รองรับงานหลักดังนี้:

- เจ้าหน้าที่ทั่วไปลงเวลาเวรและดูข้อมูลของตนเอง
- เจ้าหน้าที่การเงินหรือผู้มีสิทธิ์พิเศษดูรายงานรายบุคคลและรายแผนก
- ผู้ตรวจสอบดูคิวรอตรวจ อนุมัติรายการ และส่งออกรายงาน
- ทุกคนดูหน้าเวรรายวันได้ว่า “วันนี้ใครลงเวรบ้าง”

## 3. เทคโนโลยีที่ใช้

- PHP แบบ file-based
- MySQL / MariaDB
- PDO สำหรับเชื่อมฐานข้อมูล
- Bootstrap 5 สำหรับ layout และ component พื้นฐาน
- Google Fonts (`Prompt`, `Sarabun`)
- JavaScript แบบ vanilla สำหรับ interaction หน้าเว็บ

## 4. โครงสร้างโฟลเดอร์หลัก

```text
staff-main/
├─ auth/                  หน้า login / register / logout
├─ config/                ตั้งค่าฐานข้อมูล
├─ css/                   CSS เก่าบางส่วน
├─ includes/              helper กลาง เช่น auth, navigation, report helpers
├─ migrations/            SQL migration สำหรับ schema ใหม่
├─ pages/                 หน้าใช้งานหลักทั้งหมดของระบบ
├─ uploads/signatures/    ลายเซ็นที่อัปโหลด/วาดจากระบบใหม่
├─ signatures/            ลายเซ็นเดิมจากระบบเก่า
├─ LOGO/                  โลโก้โรงพยาบาล
├─ index.php              landing page หน้าแรกของโปรเจกต์
├─ README-RUN.md          คู่มือ run แบบเดิม
├─ README.md              เอกสารอธิบายโปรเจกต์ฉบับนี้
└─ staff.sql              schema + ข้อมูลตั้งต้น
```

## 5. ไฟล์สำคัญที่ควรรู้ก่อน

### 5.1 ไฟล์ตั้งค่าฐานข้อมูล

ไฟล์: [db.php](/C:/xampp/htdocs/staff-main/config/db.php)

หน้าที่:

- สร้างการเชื่อมต่อ PDO ไปยังฐานข้อมูล `staff`
- เป็นจุดเริ่มต้นของทุกหน้าที่ใช้ข้อมูลจากฐานข้อมูล

### 5.2 ไฟล์จัดการสิทธิ์

ไฟล์: [auth.php](/C:/xampp/htdocs/staff-main/includes/auth.php)

หน้าที่:

- กำหนด label ของ role
- กำหนด default permissions ของแต่ละ role
- normalize role ให้เหลือเฉพาะ `staff`, `finance`, `checker`
- บันทึก session หลัง login
- เช็กสิทธิ์ด้วย `app_can()`
- บังคับ login ด้วย `app_require_login()`
- บังคับ permission ด้วย `app_require_permission()`

สรุปสั้น ๆ:

- ไฟล์นี้คือหัวใจของระบบสิทธิ์ทั้งโปรเจกต์
- ถ้าจะเพิ่ม role ใหม่ หรือเพิ่ม permission ใหม่ จุดแรกที่ควรแก้คือไฟล์นี้

### 5.3 ไฟล์ helper รายงาน

ไฟล์: [report_helpers.php](/C:/xampp/htdocs/staff-main/includes/report_helpers.php)

หน้าที่:

- รวม logic สำหรับ query รายงาน
- คำนวณ filter ของรายงานส่วนตัว
- ดึงข้อมูลรายงานรายแผนก
- ดึงข้อมูลหน้าเวรรายวัน
- ตรวจการลงเวลาเวรชนกัน
- ตรวจรายการที่ incomplete / overlap

ไฟล์นี้สำคัญมากเพราะ:

- ช่วยลด query ซ้ำในหลายหน้า
- ใช้ร่วมกันระหว่างหน้าจอ report, export, print

### 5.4 ไฟล์เมนูนำทาง

ไฟล์: [navigation.php](/C:/xampp/htdocs/staff-main/includes/navigation.php)

หน้าที่:

- สร้าง navigation กลางของระบบ
- แสดงเมนูตาม role / permission

## 6. ระบบสิทธิ์ผู้ใช้

ปัจจุบันระบบใช้ 3 role หลัก

### 6.1 `staff`

สิทธิ์หลัก:

- ดูข้อมูลของตัวเอง
- ลงเวลาเวรของตัวเอง
- ดูรายงานส่วนตัว
- ดูเวรรายวัน

ไม่มีสิทธิ์:

- ดูรายงานรายแผนก
- อนุมัติรายการ
- ดูข้อมูลเจ้าหน้าที่คนอื่นแบบเต็ม

### 6.2 `finance`

สิทธิ์หลัก:

- ได้ทุกอย่างของ `staff`
- ดูข้อมูลรายบุคคลของผู้อื่นได้
- ดูรายงานรายแผนกได้
- ส่งออกรายงานได้

ไม่มีสิทธิ์:

- อนุมัติรายการลงเวลา ถ้าไม่มี `can_approve_logs`

หมายเหตุ:

- role นี้สามารถเปิด/ปิด permission เสริมบางอย่างได้ตอนสมัคร

### 6.3 `checker`

สิทธิ์หลัก:

- ได้ทุกอย่างของ `finance`
- เข้าหน้าคิวรอตรวจ
- อนุมัติรายการลงเวลา
- ดูรายงานรวมและส่งออกได้ครบ

## 7. Permission ที่ระบบใช้อยู่

ค่า permission ที่มีตอนนี้:

- `can_view_all_staff`
- `can_view_department_reports`
- `can_export_reports`
- `can_approve_logs`

logic หลัก:

- `staff` ค่าเริ่มต้นเป็น 0 ทั้งหมด
- `finance` ค่าเริ่มต้นเปิดดูรายงานและ export แต่ยังไม่ approve
- `checker` ได้ครบทั้งหมด

## 8. หน้าใช้งานหลักของระบบ

### 8.1 หน้าแรก

ไฟล์: [index.php](/C:/xampp/htdocs/staff-main/index.php)

หน้าที่:

- เป็น landing page ของโปรเจกต์
- ถ้ามี session อยู่แล้ว จะพาไป dashboard
- ถ้ายังไม่ login จะแสดงหน้าแรกพร้อมปุ่มไป login / register

### 8.2 เข้าสู่ระบบ

ไฟล์: [login.php](/C:/xampp/htdocs/staff-main/auth/login.php)

หน้าที่:

- รับ username/password
- ตรวจรหัสผ่านได้ทั้งแบบ hash ใหม่และ plain text เดิม
- ถ้าถูกต้องจะเรียก `app_set_auth_session()` แล้ว redirect ไป dashboard

จุดที่ควรรู้:

- หน้า login ตอนนี้ยังมีข้อความไทยเพี้ยนบางส่วนใน UI เก่า
- แต่ logic ฝั่งหลังบ้านใช้งานได้

### 8.3 สมัครสมาชิก

ไฟล์: [register.php](/C:/xampp/htdocs/staff-main/auth/register.php)

หน้าที่:

- สมัครสมาชิกใหม่
- เลือก role ตอนสมัคร
- กำหนด `ตำแหน่ง` และ `เบอร์โทร`
- เลือกแผนก
- วาดหรืออัปโหลดลายเซ็น
- ถ้า role เป็น `finance` สามารถเลือก permission เพิ่มได้

จุดสำคัญ:

- หน้า register พยายามอ่าน foreign key จริงจากฐานข้อมูลก่อน เพื่อเลือก source ของตารางแผนกให้ตรงกับ schema ที่ใช้อยู่
- ถ้าฐานข้อมูลยังอ้าง `bk01_departments` ระบบจะพยายามอ่านจากตารางนั้นก่อน
- ถ้า schema ใหม่พร้อมแล้ว จะใช้ `departments`

### 8.4 Dashboard

ไฟล์: [dashboard.php](/C:/xampp/htdocs/staff-main/pages/dashboard.php)

หน้าที่:

- เป็นหน้า landing หลัง login
- แสดงข้อมูลผู้ใช้ปัจจุบัน
- แสดงสรุปชั่วโมงเดือนนี้/ปีนี้
- แสดงรายการล่าสุด
- แสดง badge เตือนถ้าวันนี้มีรายการผิดปกติ เช่น incomplete หรือ overlap

### 8.5 หน้าโปรไฟล์

ไฟล์: [profile.php](/C:/xampp/htdocs/staff-main/pages/profile.php)

หน้าที่:

- แสดงข้อมูลโปรไฟล์ของผู้ใช้
- แสดงบทบาท แผนก ลายเซ็น และ quick actions

### 8.6 หน้าลงเวลาเวร

ไฟล์: [time.php](/C:/xampp/htdocs/staff-main/pages/time.php)

นี่คือหนึ่งในหน้าหลักที่สุดของระบบ

หน้าที่:

- ลงเวลาเวรของผู้ใช้
- เลือกเวลาเข้า/ออก
- ใช้ preset เวรเช้า / บ่าย / ดึก
- ดูประวัติย้อนหลัง
- แก้ไขรายการเดิม
- เช็กเวลาเวรชนกันก่อนบันทึก
- แสดงสถานะเตือนในประวัติย้อนหลัง

ฟังก์ชันเด่นในหน้านี้:

- preset เวรเช้า `08:30 - 16:30`
- preset เวรบ่าย `16:30 - 00:30`
- preset เวรดึก `00:30 - 08:30`
- มีทั้งโหมด `24h` และ `ampm`
- มี preview ชั่วโมงรวม
- ถ้าเวลาออกน้อยกว่าเวลาเข้า จะถือว่าเป็นเวรข้ามวัน

### 8.7 My Reports

ไฟล์: [my_reports.php](/C:/xampp/htdocs/staff-main/pages/my_reports.php)

หน้าที่:

- รายงานส่วนตัวของผู้ใช้
- filter ได้แบบ week / month / year / custom range
- แสดง summary และรายละเอียดรายการ
- พิมพ์หรือ export ได้

### 8.8 Department Reports

ไฟล์: [department_reports.php](/C:/xampp/htdocs/staff-main/pages/department_reports.php)

หน้าที่:

- รายงานระดับแผนก
- สำหรับผู้มีสิทธิ์ `can_view_department_reports`
- แสดงรายชื่อเจ้าหน้าที่ในแผนกและชั่วโมงรวม
- รองรับการ export / print

### 8.9 Daily Schedule

ไฟล์: [daily_schedule.php](/C:/xampp/htdocs/staff-main/pages/daily_schedule.php)

หน้าที่:

- เป็นหน้าที่ทุกคนเข้าได้
- default คือวันนี้
- เลือกวันอื่นได้
- filter ตามแผนกและชื่อได้
- ดูได้ว่าใครลงเวรบ้าง อยู่แผนกไหน เวลาอะไร
- สลับมุมมองการ์ด / ตารางได้

### 8.10 Approval Queue

ไฟล์: [approval_queue.php](/C:/xampp/htdocs/staff-main/pages/approval_queue.php)

หน้าที่:

- เป็นคิวรายการสำหรับผู้ตรวจสอบ
- ใช้ดูรายการรอตรวจ / ตรวจแล้ว
- มี filter และ pagination
- รองรับมุมมองการ์ด / ตาราง

### 8.11 Report Print

ไฟล์: [report_print.php](/C:/xampp/htdocs/staff-main/pages/report_print.php)

หน้าที่:

- สร้างเอกสารพร้อมพิมพ์
- ใช้เป็นแหล่งกลางสำหรับ print / PDF
- ใช้ข้อมูลชุดเดียวกับหน้ารายงานเพื่อลดความคลาดเคลื่อน

### 8.12 Export Report

ไฟล์: [export_report.php](/C:/xampp/htdocs/staff-main/pages/export_report.php)

หน้าที่:

- ส่งออกข้อมูลรายงานเป็น CSV

## 9. หน้า legacy ที่ยังมีอยู่

บางหน้าเก่ายังอยู่เพื่อความเข้ากันได้กับ flow เดิม หรือใช้ redirect ไปหน้าใหม่

ตัวอย่าง:

- [staff_page.php](/C:/xampp/htdocs/staff-main/pages/staff_page.php)
- [checker_page.php](/C:/xampp/htdocs/staff-main/pages/checker_page.php)
- [load_staff.php](/C:/xampp/htdocs/staff-main/pages/load_staff.php)
- [save_signature.php](/C:/xampp/htdocs/staff-main/pages/save_signature.php)

สถานะปัจจุบัน:

- บางไฟล์เป็นตัวกลาง redirect ไปหน้าใหม่
- บางไฟล์ยังเป็น endpoint support ของระบบเก่า
- ถ้าจะ refactor ใหญ่ต่อ ควรเช็กการอ้างอิงไฟล์เหล่านี้ก่อนลบ

## 10. การไหลของข้อมูลในระบบ

ส่วนนี้สำคัญที่สุดสำหรับคนที่จะพัฒนาต่อ

### 10.1 Flow การสมัครสมาชิก

1. ผู้ใช้เปิด [register.php](/C:/xampp/htdocs/staff-main/auth/register.php)
2. ระบบโหลดรายการแผนกจากฐานข้อมูล
3. ผู้ใช้กรอกข้อมูลส่วนตัว เลือกแผนก เลือก role
4. ผู้ใช้วาดหรืออัปโหลดลายเซ็น
5. ระบบตรวจความครบถ้วนของข้อมูล
6. ระบบเช็ก username ซ้ำ
7. ระบบบันทึกลายเซ็นลงโฟลเดอร์ `uploads/signatures/`
8. ระบบ insert ผู้ใช้ใหม่ลงตาราง `users`
9. ระบบแสดง success message และ redirect ไป login

### 10.2 Flow การ login

1. ผู้ใช้เปิด [login.php](/C:/xampp/htdocs/staff-main/auth/login.php)
2. ระบบรับ `username` และ `password`
3. query ผู้ใช้จากตาราง `users`
4. ตรวจ password
5. เรียก `app_set_auth_session()`
6. เก็บ `id`, `fullname`, `username`, `department_id`, `role`, `permissions` ลง session
7. redirect ไป [dashboard.php](/C:/xampp/htdocs/staff-main/pages/dashboard.php)

### 10.3 Flow การลงเวลา

1. ผู้ใช้เปิด [time.php](/C:/xampp/htdocs/staff-main/pages/time.php)
2. ระบบโหลดข้อมูลผู้ใช้และแผนก
3. ผู้ใช้เลือกเวลาเข้า/ออก หรือกด preset เวร
4. ระบบแปลงค่าเวลาเป็น `HH:MM`
5. สร้าง `full datetime` จากวันปัจจุบัน + เวลา
6. ถ้าเวลาออกน้อยกว่าเวลาเข้า ถือว่าข้ามวัน
7. เรียก `app_find_overlapping_time_log()` เพื่อตรวจเวลาชนกัน
8. ถ้าไม่ชนกันจึง insert ลง `time_logs`
9. redirect กลับหน้าเดิมเพื่อเห็นรายการล่าสุด

### 10.4 Flow การแก้ไขรายการลงเวลา

1. ผู้ใช้เปิดรายการเดิมจากหน้า [time.php](/C:/xampp/htdocs/staff-main/pages/time.php)
2. ระบบโหลดรายการตาม `edit_id`
3. ผู้ใช้แก้เวลาและหมายเหตุ
4. ระบบตรวจ overlap อีกครั้ง โดย exclude id เดิม
5. ถ้า valid จะ update รายการ
6. ระบบ reset สถานะตรวจสอบเดิมบางส่วน เช่น `checked_by`, `checked_at`, `signature`

### 10.5 Flow รายงานส่วนตัว

1. เปิด [my_reports.php](/C:/xampp/htdocs/staff-main/pages/my_reports.php)
2. หน้าเรียก helper ใน [report_helpers.php](/C:/xampp/htdocs/staff-main/includes/report_helpers.php)
3. helper สร้าง where clause จาก filter
4. query summary และรายการ log
5. ส่งผลลัพธ์กลับมาแสดงผล
6. ถ้าผู้ใช้กด print / export จะใช้ filter ชุดเดียวกันส่งต่อไปยังหน้าส่งออก

### 10.6 Flow รายงานแผนก

1. เปิด [department_reports.php](/C:/xampp/htdocs/staff-main/pages/department_reports.php)
2. ระบบเช็ก permission `can_view_department_reports`
3. helper query เจ้าหน้าที่ทั้งหมดในแผนก พร้อมยอดชั่วโมง
4. หน้ารายงานแสดง summary + ตาราง/การ์ด
5. export หรือ print โดยใช้ข้อมูลชุดเดียวกัน

### 10.7 Flow เวรรายวัน

1. เปิด [daily_schedule.php](/C:/xampp/htdocs/staff-main/pages/daily_schedule.php)
2. default เป็น `วันนี้`
3. helper query `time_logs` ตามวันที่
4. filter เพิ่มได้ตามแผนกและชื่อ
5. หน้าแสดงว่าใครลงเวร เวลาไหน อยู่แผนกใด

### 10.8 Flow การตรวจสอบ

1. ผู้ตรวจสอบเปิด [approval_queue.php](/C:/xampp/htdocs/staff-main/pages/approval_queue.php)
2. ระบบเช็ก `can_approve_logs`
3. โหลดรายการรอตรวจและตรวจแล้ว
4. ผู้ตรวจสอบดูและอนุมัติรายการ
5. ค่าที่เกี่ยวข้องใน `time_logs` เช่น `checked_by`, `checked_at` จะถูกอัปเดต

## 11. โครงสร้างฐานข้อมูลที่ระบบคาดหวัง

อย่างน้อยระบบนี้คาดหวังตารางหลักดังนี้:

- `users`
- `departments`
- `time_logs`

### 11.1 ตาราง `users`

คอลัมน์สำคัญที่มีการใช้งานในโค้ด:

- `id`
- `fullname`
- `username`
- `password`
- `department_id`
- `signature_path`
- `role`
- `can_view_all_staff`
- `can_view_department_reports`
- `can_export_reports`
- `can_approve_logs`
- `position_name`
- `phone_number`

### 11.2 ตาราง `departments`

คอลัมน์หลัก:

- `id`
- `department_name`

หมายเหตุ:

- ระบบเคยมีการอ้างตาราง `bk01_departments`
- ตอนนี้โค้ดส่วนสมัครสมาชิกพยายามรองรับทั้งโครงสร้างเก่าและใหม่

### 11.3 ตาราง `time_logs`

คอลัมน์หลักที่โค้ดใช้อยู่:

- `id`
- `user_id`
- `department_id`
- `work_date`
- `time_in`
- `time_out`
- `work_hours`
- `note`
- `checked_by`
- `checked_at`
- `signature`

## 12. Migration ที่มีอยู่

### 12.1 role + permissions

ไฟล์: [001_add_roles_permissions.sql](/C:/xampp/htdocs/staff-main/migrations/001_add_roles_permissions.sql)

ไว้สำหรับ:

- เพิ่ม field role
- เพิ่ม permission flags

### 12.2 fix foreign key + profile fields

ไฟล์: [002_fix_department_fk_and_add_profile_fields.sql](/C:/xampp/htdocs/staff-main/migrations/002_fix_department_fk_and_add_profile_fields.sql)

ไว้สำหรับ:

- แก้ foreign key ของ `department_id`
- เพิ่ม `position_name`
- เพิ่ม `phone_number`

## 13. ลำดับการทำงานของไฟล์สำคัญเวลาระบบรัน

กรณีผู้ใช้ใช้งานตาม flow ปกติ:

1. เข้า [index.php](/C:/xampp/htdocs/staff-main/index.php)
2. ไป [login.php](/C:/xampp/htdocs/staff-main/auth/login.php) หรือ [register.php](/C:/xampp/htdocs/staff-main/auth/register.php)
3. เมื่อ login สำเร็จ จะไป [dashboard.php](/C:/xampp/htdocs/staff-main/pages/dashboard.php)
4. จาก dashboard จึงไปยัง:
   - [time.php](/C:/xampp/htdocs/staff-main/pages/time.php)
   - [my_reports.php](/C:/xampp/htdocs/staff-main/pages/my_reports.php)
   - [daily_schedule.php](/C:/xampp/htdocs/staff-main/pages/daily_schedule.php)
   - [department_reports.php](/C:/xampp/htdocs/staff-main/pages/department_reports.php)
   - [approval_queue.php](/C:/xampp/htdocs/staff-main/pages/approval_queue.php)
   - [profile.php](/C:/xampp/htdocs/staff-main/pages/profile.php)

## 14. จุดแข็งของโครงสร้างปัจจุบัน

- มี role system ที่ชัดกว่าเดิม
- helper กลางเริ่มแยก logic ออกจากหน้า view
- flow รายงานและ export เริ่มใช้ข้อมูลชุดเดียวกัน
- หน้าใหม่หลายหน้าถูกจัด theme ให้สอดคล้องกันมากขึ้น
- มีการเช็กเวลาเวรชนกันแล้ว

## 15. ข้อจำกัดหรือหนี้เทคนิคที่ยังมี

นี่คือสิ่งที่ควรรู้แบบตรงไปตรงมา

### 15.1 ยังเป็นระบบแบบ file-based

ข้อดี:

- เข้าใจง่าย
- แก้เร็ว

ข้อเสีย:

- query กับ view ยังปนกันอยู่ในหลายไฟล์
- โตต่อยากกว่าระบบ MVC

### 15.2 บางหน้าเก่ายังมีปัญหา encoding

ตัวอย่างที่ยังควรเก็บงานต่อ:

- [login.php](/C:/xampp/htdocs/staff-main/auth/login.php)
- [README-RUN.md](/C:/xampp/htdocs/staff-main/README-RUN.md)

หมายความว่า:

- logic ใช้งานได้ แต่ข้อความ UI/เอกสารเก่ายังมี mojibake บางจุด

### 15.3 schema ยังมีช่วงเปลี่ยนผ่าน

ตอนนี้ระบบรองรับทั้ง:

- โครงสร้าง `departments`
- โครงสร้างเก่าที่อาจอิง `bk01_departments`

ดังนั้นก่อน deploy หรือใช้งานจริง ควรทำ schema ให้เป็นมาตรฐานเดียว

### 15.4 legacy assets ยังอยู่สองชุด

มีทั้ง:

- `signatures/`
- `uploads/signatures/`

ในอนาคตควรเลือกแหล่งเก็บหลักเพียงชุดเดียว

## 16. แนวทางพัฒนาต่อที่แนะนำ

ถ้าจะพัฒนาต่ออย่างเป็นระบบ ลำดับที่แนะนำคือ:

1. เก็บ encoding ไทยในหน้าเก่าที่เหลือ โดยเฉพาะ login และเอกสารเก่า
2. แยก query/business logic ออกจากหน้า PHP ให้มากขึ้น
3. รวม style system กลาง เช่น สี spacing button input ให้ใช้ร่วมกันทุกหน้า
4. ทำ schema cleanup ให้เหลือโครงสร้างเดียว
5. เพิ่ม test checklist สำหรับ flow สำคัญ เช่น register, login, save time, edit time, export

## 17. วิธีอ่านโค้ดโปรเจกต์นี้ให้เร็วที่สุด

ถ้าคุณเพิ่งเข้ามาดูโปรเจกต์ แนะนำให้อ่านตามลำดับนี้:

1. [auth.php](/C:/xampp/htdocs/staff-main/includes/auth.php)
2. [register.php](/C:/xampp/htdocs/staff-main/auth/register.php)
3. [login.php](/C:/xampp/htdocs/staff-main/auth/login.php)
4. [dashboard.php](/C:/xampp/htdocs/staff-main/pages/dashboard.php)
5. [time.php](/C:/xampp/htdocs/staff-main/pages/time.php)
6. [report_helpers.php](/C:/xampp/htdocs/staff-main/includes/report_helpers.php)
7. [my_reports.php](/C:/xampp/htdocs/staff-main/pages/my_reports.php)
8. [department_reports.php](/C:/xampp/htdocs/staff-main/pages/department_reports.php)
9. [approval_queue.php](/C:/xampp/htdocs/staff-main/pages/approval_queue.php)

อ่านตามนี้จะเห็นภาพครบทั้ง auth, data entry, reporting, และ approval

## 18. สรุปสั้นที่สุด

โปรเจกต์นี้คือระบบลงเวลาเวรและรายงานสำหรับบุคลากรโรงพยาบาล ที่กำลังอยู่ในช่วงปรับจากระบบเก่าไปสู่โครงสร้างใหม่ที่มี role/permission ชัดเจนมากขึ้น

ตอนนี้ระบบมีแกนหลักใช้งานได้แล้ว:

- สมัครสมาชิก
- login
- dashboard
- ลงเวลาเวร
- เช็กเวลาเวรชนกัน
- รายงานส่วนตัว
- รายงานแผนก
- เวรรายวัน
- คิวอนุมัติ
- print / export

แต่ยังมีงาน cleanup ต่ออีก โดยเฉพาะ encoding ของบางหน้าเก่าและการเก็บโครงสร้างให้เป็นมาตรฐานเดียวกันทั้งโปรเจกต์
