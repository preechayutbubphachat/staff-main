# Department Report Hero Heading Notes

## Wording For All Departments
- ถ้าไม่ได้เลือกแผนกเฉพาะใน filter ระบบจะแสดงหัวหลักเป็น:
  - `รายงานสรุปแผนกทั้งหมด ประจำเดือน <เดือน> <ปี พ.ศ.>`
- บรรทัดรองใช้ข้อความ:
  - `ข้อมูลสรุปของทุกแผนกตามสิทธิ์ที่เข้าถึงได้ในช่วงเวลาที่เลือก`

## Wording For A Specific Department
- ถ้าเลือกแผนกเฉพาะ ระบบจะแสดงหัวหลักเป็น:
  - `รายงานสรุปแผนก ICU ประจำเดือน เมษายน 2569`
  - `รายงานสรุปแผนก IPD ประจำเดือน เมษายน 2569`
- บรรทัดรองใช้ข้อความ:
  - `ข้อมูลสรุปของแผนกที่เลือกตามตัวกรองปัจจุบัน`

## Hero Layout Choice
- คงโครงสร้าง hero เดิมของหน้าไว้
- ปรับให้ข้อความ `h1` หลักใน hero เป็น dynamic ตามแผนกและเดือน
- ปรับข้อความคำอธิบายใต้ `h1` ให้สะท้อน context ปัจจุบัน
- ค่าด้านขวาของ hero ในส่วนขอบเขตรายงานและช่วงเดือนยังแสดง context เดียวกัน

## Sync With Lower Report Heading
- ส่วนหัวรายงานด้านล่างใน `C:\xampp\htdocs\staff-main\partials\reports\department_results.php` ใช้ `heading_context` จาก helper กลาง
- hero ด้านบนใช้ wording rule เดียวกันและอัปเดตตาม form state เดียวกัน
- เมื่อ filter เปลี่ยน hero และ lower heading จึงสอดคล้องกันทั้งสองจุด

## Helper And Refresh Strategy
- source of truth ฝั่ง PHP:
  - `app_get_department_report_heading_context()`
  - `app_format_thai_month_year()`
- source of truth ฝั่ง refresh:
  - `C:\xampp\htdocs\staff-main\assets\js\table-filters.js` รองรับ `onRefresh`
  - `C:\xampp\htdocs\staff-main\pages\department_reports.php` ใช้ `syncDepartmentReportHero(...)` เพื่ออัปเดต hero หลัง async refresh และตอนเปิดหน้า
