# Table Standardization Matrix

| Page | Table/List | Rows-per-page | Filter close to table | Auto-refresh | Export notes | Async endpoint | Clickable profile |
|---|---|---|---|---|---|---|---|
| `my_reports.php` | รายการรายงานส่วนตัว | Yes | Yes | Yes | Export ตาม filter ปัจจุบัน | `ajax/reports/my_report_rows.php` | N/A |
| `department_reports.php` | รายงานรายบุคคลในแผนก | Yes | Yes | Yes | Export ตามเดือน/แผนกใน scope | `ajax/reports/department_rows.php` | Yes |
| `daily_schedule.php` | ตารางเวรประจำวัน | Yes | Yes | Yes | Export ตามวัน/แผนก/ชื่อ | `ajax/reports/daily_schedule_rows.php` | Yes |
| `approval_queue.php` | รายการรอตรวจสอบเวร | Yes | Yes | Yes | Export ตาม filter ปัจจุบันในสิทธิ์ที่เห็น | `ajax/approval/list_rows.php` | Yes |
| `manage_time_logs.php` | ตารางจัดการลงเวลาเวร | Yes | Yes | Yes | Export ตาม filter ปัจจุบันในสิทธิ์ที่จัดการได้ | `ajax/manage_time_logs/list_rows.php` | Yes |
| `time.php` | ประวัติย้อนหลังของตนเอง | Yes | Yes | Yes | ไม่มี export ในหน้านี้ | `ajax/time/history_rows.php` | N/A |
| `manage_users.php` | รายชื่อผู้ใช้งาน | Yes | Yes | Yes | Export ตามตัวกรองผู้ใช้ปัจจุบัน | `ajax/admin/users_rows.php` | Yes |
| `db_table_browser.php` | ตารางข้อมูลหลังบ้านที่ allowlist ไว้ | Yes | Yes | Yes | Export ตามตาราง/คำค้นปัจจุบัน | `ajax/admin/db_table_rows.php` | เฉพาะตารางที่มีชื่อเจ้าหน้าที่ |
| `db_change_logs.php` | audit log หลังบ้าน | Yes | Yes | Yes | Export ตามคำค้นปัจจุบัน | `ajax/admin/audit_rows.php` | No |

## Notes / Limitations
- หน้า export ยังเป็น server-driven ทั้งหมด และตั้งใจไม่เปลี่ยนเป็น frontend-only generation
- `rows-per-page` มีผลกับการแสดงบนหน้าจอ ส่วน export ใช้ full filtered scope ตามสิทธิ์ ไม่ใช่แค่หน้า current page
- บางหน้าที่เป็น modal edit / bulk approve ยังใช้ JS เฉพาะหน้าเสริมจาก `table-filters.js` เพื่อรักษา workflow เดิม
