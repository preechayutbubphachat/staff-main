## Table column width matrix

### `pages/my_reports.php`
- Table type: `my_reports`
- Visible columns:
  - ลำดับ `8%`
  - วันที่ `16%`
  - แผนก `14%`
  - เวลาเข้า - ออก `18%`
  - ชั่วโมงรวม `10%`
  - หมายเหตุ `20%`
  - สถานะ `14%`
- Reuse notes: ใช้กับตารางผลลัพธ์รายงานส่วนบุคคล

### `pages/department_reports.php`
- Table type: `department_summary`
- Visible columns:
  - ลำดับ `8%`
  - ชื่อเจ้าหน้าที่ `24%`
  - ตำแหน่ง `16%`
  - แผนก `14%`
  - จำนวนเวร `10%`
  - ชั่วโมงรวม `10%`
  - ตรวจแล้ว `9%`
  - รอตรวจ `9%`
- Reuse notes: ใช้กับตารางสรุปรายบุคคลในรายงานแผนก

### `pages/daily_schedule.php`
- Table type: `daily_roster`
- Visible columns:
  - ลำดับ `8%`
  - ชื่อเจ้าหน้าที่ `29%`
  - ตำแหน่ง `18%`
  - แผนก `16%`
  - เบอร์โทรศัพท์ `14%`
  - หมายเหตุ `15%`
- Reuse notes: ใช้ซ้ำทุกกลุ่มเวร `เวรเช้า/บ่าย/ดึก/เวลาอื่นๆ` บนหน้าเดียวกัน

### `pages/approval_queue.php`
- Table type: `approval_queue`
- Visible columns:
  - เลือก `6%`
  - ลำดับ `6%`
  - วันที่ `10%`
  - ชื่อเจ้าหน้าที่ `18%`
  - ตำแหน่ง `12%`
  - แผนก `10%`
  - เวลาเข้า `10%`
  - เวลาออก `8%`
  - ชั่วโมงรวม `10%`
  - หมายเหตุ `10%`
  - สถานะ `10%`
- Reuse notes: ใช้กับ table view ของ approval queue

### `pages/manage_time_logs.php`
- Table type: `manage_time_logs`
- Visible columns:
  - ลำดับ `4%`
  - วันที่ `8%`
  - ชื่อเจ้าหน้าที่ `14%`
  - ตำแหน่ง `9%`
  - แผนก `8%`
  - เวลาเข้า `6%`
  - เวลาออก `6%`
  - ชั่วโมงรวม `6%`
  - หมายเหตุ `10%`
  - สถานะ `7%`
  - ตรวจโดย `7%`
  - ตรวจเมื่อ `7%`
  - จัดการ `8%`
- Reuse notes: ใช้กับตารางจัดการลงเวลาเวรหลัก

### `pages/manage_users.php`
- Table type: `manage_users`
- Visible columns:
  - ลำดับ `6%`
  - เจ้าหน้าที่ `24%`
  - ตำแหน่ง `14%`
  - แผนก `14%`
  - บทบาท `12%`
  - สถานะสิทธิ์ `14%`
  - จัดการ `16%`
- Reuse notes: ใช้กับตารางผู้ใช้งานในฝั่งหลังบ้าน

### `pages/db_change_logs.php`
- Table type: `db_change_logs`
- Visible columns:
  - ลำดับ `6%`
  - เวลา `16%`
  - ตาราง `16%`
  - การกระทำ `12%`
  - ผู้ดำเนินการ `16%`
  - หมายเหตุ `34%`
- Reuse notes:
  - ใช้กับ partial `db_change_log_results`
  - ใช้ซ้ำที่ `pages/db_admin_dashboard.php` ในตาราง recent logs

### `pages/db_table_browser.php`
- Table type: `db_table_generic`
- Visible columns:
  - ลำดับ `7%`
  - คอลัมน์ข้อมูลภายในตาราง `กระจายเปอร์เซ็นต์เท่ากันจากพื้นที่ 80%`
  - จัดการ `13%`
- Reuse notes: ใช้กับตาราง allowlist ที่จำนวนคอลัมน์ไม่ตายตัว

### `pages/load_staff.php`
- Table type: `load_staff`
- Visible columns:
  - ชื่อ-นามสกุลเจ้าหน้าที่ `45%`
  - แผนก/หน่วยงาน `30%`
  - เวลาเข้าเวร `25%`
- Reuse notes: ใช้กับตาราง AJAX รายชื่อเจ้าหน้าที่แบบย่อ
