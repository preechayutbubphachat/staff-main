# Export Matrix

| Page | Visible dataset source | Print | PDF | CSV | Clickable staff profile modal | Notes |
|---|---|---|---|---|---|---|
| `pages/my_reports.php` | `app_fetch_my_report_data()` | Yes | Yes | Yes | N/A | ใช้ข้อมูลส่วนตัวของผู้ใช้ตามช่วงวันที่ที่เลือก |
| `pages/department_reports.php` | `app_fetch_department_report_data()` | Yes | Yes | Yes | Yes | default load แสดงสรุปรายบุคคลตาม scope ที่เข้าถึงได้ |
| `pages/daily_schedule.php` | `app_fetch_daily_schedule_data()` | Yes | Yes | Yes | Yes | default view เป็นตาราง |
| `pages/approval_queue.php` | `app_fetch_time_log_report_data(..., 'pending')` | Yes | Yes | Yes | Yes | export ใช้ filter และ scope เดียวกับหน้าจอ |
| `pages/manage_time_logs.php` | `app_fetch_time_log_report_data(..., 'all')` | Yes | Yes | Yes | Yes | export ใช้ filter และ scope เดียวกับหน้าจอ |
| `pages/manage_users.php` | `app_get_manageable_users_all()` | Yes | Yes | Yes | Yes | ใช้ตัวกรองชื่อ/username/ตำแหน่ง/แผนก/role |
| `pages/db_table_browser.php` | `app_db_admin_fetch_all_rows()` | Yes | Yes | Yes | Yes (users/time_logs) | ต้องเลือกตารางก่อนจึงจะมีปุ่ม export |
| `pages/db_change_logs.php` | `db_admin_audit_logs` | Yes | Yes | Yes | No | เป็นตาราง audit ไม่มีชื่อเจ้าหน้าที่เป็นแกนหลัก |
| `pages/db_admin_dashboard.php` | recent `db_admin_audit_logs` summary area | Yes | Yes | Yes | No | ปุ่ม export อิงชุดข้อมูลเดียวกับหน้าบันทึกการเปลี่ยนแปลงข้อมูล |
| `pages/dashboard.php` | ไม่มี result table หลักที่ใช้เป็นรายงาน | No | No | No | No | ยังไม่เพิ่ม เพราะหน้าเน้น summary/action ไม่ใช่หน้ารายงานโดยตรง |

## Notes

- export ของหน้าที่มี pagination จะใช้ผลลัพธ์ “ทั้งชุดตามตัวกรองและสิทธิ์” ไม่ใช่เฉพาะเลขหน้าปัจจุบัน
- profile modal ใช้ `pages/staff_profile_modal.php` เป็น endpoint กลาง
- หน้าที่มีชื่อเจ้าหน้าที่แต่ไม่ใช่ตารางรายงานหลัก อาจยังคงเป็นข้อความธรรมดาถ้าไม่ใช่ result list หลักของหน้า
