# Table Numbering And Refresh Matrix

| Page | Table/List | Rows-per-page refresh | Filter refresh | Raw ID hidden | Numbering style | Async endpoint | Notes |
|---|---|---|---|---|---|---|---|
| `pages/my_reports.php` | รายการลงเวลาเวรของฉัน | Yes | Yes | Yes | Global paginated | `ajax/reports/my_report_rows.php` | ตารางหลักใช้ `ลำดับ` |
| `pages/department_reports.php` | สรุปรายบุคคล | Yes | Yes | Yes | Global paginated | `ajax/reports/department_rows.php` | table + cards แสดงลำดับ |
| `pages/daily_schedule.php` | รายการเวรประจำวัน | Yes | Yes | Yes | Global paginated | `ajax/reports/daily_schedule_rows.php` | table + cards แสดงลำดับ |
| `pages/approval_queue.php` | รายการรอตรวจสอบ | Yes | Yes | Yes | Global paginated | `ajax/approval/list_rows.php` | table + cards ซ่อน `id`, internal id ยังอยู่ใน checkbox/action |
| `pages/manage_time_logs.php` | รายการลงเวลาเวรที่จัดการได้ | Yes | Yes | Yes | Global paginated | `ajax/manage_time_logs/list_rows.php` | modal/detail ใช้ internal id แต่ตารางใช้ `ลำดับ` |
| `pages/time.php` | ประวัติลงเวลาเวรย้อนหลัง | Yes | Yes | Yes | Global paginated | `ajax/time/history_rows.php` | เป็น list card แต่เพิ่ม `ลำดับที่` แล้ว |
| `pages/manage_users.php` | รายชื่อผู้ใช้งาน | Yes | Yes | Yes | Global paginated | `ajax/admin/users_rows.php` | ไม่โชว์ raw user id |
| `pages/db_table_browser.php` | ตารางข้อมูลหลังบ้าน | Yes | Yes | Yes | Global paginated | `ajax/admin/db_table_rows.php` | ใช้ visible browse columns helper |
| `pages/db_change_logs.php` | บันทึกการเปลี่ยนแปลงข้อมูล | Yes | Yes | Yes | Global paginated | `ajax/admin/audit_rows.php` | ไม่โชว์ `row_primary_key` |
| `pages/db_admin_dashboard.php` | บันทึกล่าสุดบนหน้า dashboard | No dedicated selector | No dedicated filters | Yes | Local dashboard sequence | None | ตารางสรุปล่าสุด 8 รายการ ไม่มี toolbar แยก |
| `pages/report_print.php` | ทุกประเภท report print view | N/A | Uses request scope | Yes | Filtered full dataset | Server render | ใช้ `ลำดับ` ในเอกสารพิมพ์ |
| `pages/export_report.php` | ทุกประเภท CSV export | N/A | Uses request scope | Yes | Filtered full dataset | Server response | ใช้ `ลำดับ` ใน CSV |

## หมายเหตุ
- รูปแบบเลขลำดับหลักที่ใช้บนหน้าจอคือ `Global paginated row numbering`
- export/print ไม่อิง page size ปัจจุบันเป็นหลัก แต่ส่งออกทั้ง filtered dataset ภายใต้สิทธิ์เดียวกับหน้าจอ
- internal id ยังถูกเก็บไว้ใน action URLs, hidden inputs, data attributes, และ modal target IDs เพื่อไม่ให้ฟังก์ชันเดิมพัง
