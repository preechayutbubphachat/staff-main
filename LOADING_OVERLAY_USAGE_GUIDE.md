# Loading Overlay Usage Guide

## Shared API
- `window.GlobalLoading.showGlobalLoading(message, options)`
- `window.GlobalLoading.hideGlobalLoading(options)`
- `window.GlobalLoading.withGlobalLoading(promiseOrFactory, message, options)`
- `window.GlobalLoading.showPageNavigationLoading(message, options)`

## Shared Files
- `C:\xampp\htdocs\staff-main\assets\css\loading-overlay.css`
- `C:\xampp\htdocs\staff-main\assets\js\global-loading.js`

## Thai Messages Used
- `โปรดรอสักครู่...`
- `กำลังบันทึกข้อมูล...`
- `กำลังลบรายการ...`
- `กำลังตรวจสอบข้อมูล...`
- `กำลังส่งออกเอกสาร...`
- `กำลังเปิดหน้าการแจ้งเตือน...`
- `กำลังรีเฟรชข้อมูล...`
- `กำลังสร้างบัญชีผู้ใช้งาน...`
- `กำลังบันทึกข้อมูลโปรไฟล์...`

## When To Use Full-Screen Overlay
- ใช้กับ action ที่ผู้ใช้กดแล้วต้องรอผลชัดเจน เช่น save, delete, bulk approve, export, upload, redirect สำคัญ
- ใช้กับ modal submit ที่ถ้าระบบนิ่งเกินไปผู้ใช้อาจกดซ้ำ
- ใช้กับ navigation ที่พาไป workflow ถัดไปหรือหน้าที่หนักกว่าปกติ

## When To Keep Lighter Loading State
- การรีเฟรชตารางเฉพาะ block จาก filter/pagination ยังใช้ `ops-loading` แบบ local ต่อไป
- notification polling ปกติไม่ใช้ full-screen overlay
- interaction เล็กมากที่แทบไม่รู้สึก delay ไม่ควรยก overlay ทับทั้งหน้า

## Declarative Hooks
- ฟอร์มที่ต้องการ overlay ตอน submit:
  - `data-global-loading-form`
  - `data-loading-message="..."`
- ลิงก์หรือปุ่มที่ต้องการ overlay ตอน navigate:
  - `data-global-loading-nav`
  - `data-loading-message="..."`

## Modal Compatibility
- overlay ใช้ `z-index` สูงกว่า modal และ backdrop ของ Bootstrap
- เมื่อ action เริ่มจาก modal, overlay จะขึ้นทับ modal ได้โดยตรงเพื่อลดความสับสน
- หาก async action fail ต้องเรียก `hideGlobalLoading()` และคืนสถานะปุ่มทุกครั้ง

## Duplicate-Click Prevention
- utility กลางจะ mark trigger เป็น busy และ disable ปุ่มที่ส่งงานเมื่อทำได้
- page script สำคัญยัง disable ปุ่ม submit/confirm ซ้ำอีกชั้นสำหรับ flow ที่เสี่ยง เช่น:
  - delete/save ใน `time-page.js`
  - bulk approve ใน `approval-queue.js`
  - save ใน `manage-time-logs.js`

## Recovery Rules
- success:
  - ปิด overlay หลัง promise จบ หรือคง overlay ต่อจนเริ่ม navigation/reload
- failure:
  - ซ่อน overlay
  - แสดงข้อความ error ภาษาไทย
  - คืนสถานะปุ่มเพื่อให้ลองใหม่ได้

## Current Integrated Workflows
- `C:\xampp\htdocs\staff-main\pages\time.php`
  - สร้างรายการลงเวลาเวร
  - บันทึกแก้ไขจาก modal
  - ลบรายการจาก modal
- `C:\xampp\htdocs\staff-main\pages\approval_queue.php`
  - bulk approve
  - export links ผ่าน `.table-export-group`
- `C:\xampp\htdocs\staff-main\pages\manage_time_logs.php`
  - บันทึกแก้ไขจาก modal
- `C:\xampp\htdocs\staff-main\auth\register.php`
  - submit สร้างบัญชี
- `C:\xampp\htdocs\staff-main\pages\profile.php`
  - submit อัปเดตโปรไฟล์
- shared navbar
  - notification open
  - ดูทั้งหมด
  - logout
  - back navigation
