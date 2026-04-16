# Staff Time Attendance - Run Guide

โปรเจคนี้เป็น PHP + MySQL แบบไฟล์ล้วน

สิ่งที่ต้องมี
- PHP 8.x
- MySQL หรือ MariaDB
- Apache หรือใช้ PHP built-in server ก็ได้

ฐานข้อมูลที่โปรเจคคาดหวัง
- Host: `localhost`
- Database: `staff`
- Username: `root`
- Password: ว่าง

ไฟล์ตั้งค่าฐานข้อมูลอยู่ที่ `config/db.php`

## วิธีรันด้วย XAMPP

1. ติดตั้ง XAMPP
2. เปิด XAMPP Control Panel
3. กด Start ที่ `Apache`
4. กด Start ที่ `MySQL`
5. คัดลอกโฟลเดอร์โปรเจคนี้ไปไว้ใน `C:\xampp\htdocs\staff-main`
6. เปิด phpMyAdmin ที่ `http://localhost/phpmyadmin`
7. สร้างฐานข้อมูลชื่อ `staff`
8. เลือก Collation เป็น `utf8mb4_general_ci`
9. เข้า database `staff`
10. กดแท็บ Import
11. เลือกไฟล์ `staff.sql` จากโปรเจคนี้
12. กด Import
13. เปิดเว็บที่ `http://localhost/staff-main/auth/login.php`

ถ้าขึ้น DB ERROR
- ตรวจว่า MySQL start แล้ว
- ตรวจว่า database ชื่อ `staff`
- ตรวจว่า import `staff.sql` เรียบร้อยแล้ว

## วิธีรันด้วย Laragon

1. ติดตั้ง Laragon
2. เปิด Laragon
3. กด Start All
4. คัดลอกโฟลเดอร์โปรเจคนี้ไปไว้ใน `C:\laragon\www\staff-main`
5. เปิด `Menu > Database > phpMyAdmin`
6. สร้างฐานข้อมูลชื่อ `staff`
7. import ไฟล์ `staff.sql`
8. เปิดเว็บที่ `http://localhost/staff-main/auth/login.php`

ถ้าเปิด Auto Virtual Hosts ไว้ อาจเข้าได้ผ่าน
- `http://staff-main.test/auth/login.php`

## วิธีรันด้วย PHP built-in server

ใช้ได้เมื่อมี PHP และ MySQL พร้อมแล้ว

1. เปิด terminal ในโฟลเดอร์โปรเจค
2. รันคำสั่ง

```powershell
php -S localhost:8000
```

3. เปิดเว็บที่ `http://localhost:8000/auth/login.php`

หมายเหตุ
- วิธีนี้ยังต้องมี MySQL/MariaDB ทำงานอยู่
- ต้อง import `staff.sql` ก่อน

## สิ่งที่แก้แล้วในโปรเจค

- หน้า login รองรับทั้งรหัสผ่านเก่าแบบ plain text และรหัสผ่านใหม่แบบ hash
- สมัครสมาชิกใหม่จะบันทึกรหัสผ่านแบบ hash
- หน้า `pages/time.php` redirect ไปหน้า login ถูก path แล้ว
- ตัดการอ้างถึง `photo_path` ที่ไม่มีในฐานข้อมูลออก
