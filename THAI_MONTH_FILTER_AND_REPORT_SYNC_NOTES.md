# Thai Month Filter And Report Sync Notes

## Root cause ของปัญหา summary/filter mismatch

- ฝั่ง `department_reports` ใช้ `app_fetch_department_report_data()` เป็นแหล่งข้อมูลเดียวจริง แต่ UI ไม่ได้รีเฟรชทั้งหน้า
- block ผลลัพธ์ที่ AJAX รีเฟรชคือ `departmentReportsResults` เท่านั้น
- summary cards ชุดบนอยู่นอก block นี้ จึงค้างค่าเดิมจาก request ก่อนหน้า
- ผลลัพธ์ที่ผู้ใช้เห็นเลยกลายเป็น:
  - summary cards จาก filter เก่า
  - ตาราง/การ์ดจาก filter ใหม่
- ปุ่ม export ยังอยู่คนนอก block เช่นกัน แต่ตัวนี้มี `TableFilters.syncExportLinks()` ช่วยอัปเดต `href` ตาม form อยู่แล้ว

## Shared filter context approach

- ยังคงใช้ `app_fetch_department_report_data()` เป็น source of truth สำหรับ:
  - `filters`
  - `department_totals`
  - `staff_rows`
  - `heading_context`
- ปรับให้ partial ผลลัพธ์ [partials/reports/department_results.php](C:/xampp/htdocs/staff-main/partials/reports/department_results.php) แสดงทั้ง:
  - summary cards
  - current heading
  - card/table results
- ผลคือทุกส่วนใน result panel เปลี่ยนพร้อมกันจาก filter context เดียว

## Thai month control strategy

- ไม่ใช้ native `input[type="month"]` สำหรับหน้าที่ต้องการ Thai-first month display โดยตรง
- เปลี่ยนเป็น `select name="month"` ที่:
  - `option value` เป็น `YYYY-MM`
  - `option label` เป็น `เดือนภาษาไทย + ปี พ.ศ.`
- helper ใหม่:
  - `app_get_thai_month_options(?string $selectedMonth = null, int $monthsBack = 24, int $monthsForward = 3)`
- helper นี้ใช้ `app_format_thai_month_year()` เพื่อให้ label มาจาก source เดียวกัน

## Internal value vs visible label

- Internal value:
  - `2026-04`
- Visible label:
  - `เมษายน 2569`
- ทำให้ SQL/filtering เดิมยังทำงานแบบปลอดภัย โดยไม่ต้อง parse Thai string ใน query

## หน้าที่อัปเดต month control

- [pages/department_reports.php](C:/xampp/htdocs/staff-main/pages/department_reports.php)
- [pages/my_reports.php](C:/xampp/htdocs/staff-main/pages/my_reports.php)

## หมายเหตุด้าน heading/export

- heading ของ Department Reports ฝั่ง page และ partial ใช้ `heading_context` ชุดเดียวกัน
- hero heading ของหน้า Department Reports ถูกตั้งจาก PHP ตั้งแต่แรก และ sync ซ้ำด้วย JS หลัง refresh
- export/print/PDF ยังคงรับค่า query จาก form ปัจจุบันผ่าน `TableFilters.syncExportLinks()`
