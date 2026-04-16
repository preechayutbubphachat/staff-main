# Table Toolbar Alignment Matrix

| หน้า | rows-per-page | export group ชิดขวา | หมายเหตุ layout |
|---|---|---:|---|
| `pages/approval_queue.php` | ใช่ | ใช่ | แยก filter form ออกจาก bulk form และย้าย export ไปฝั่งขวา |
| `pages/manage_time_logs.php` | ใช่ | ใช่ | reset filter อยู่กลุ่มเดียวกับ export เพื่อคุมแถวเดียวกัน |
| `pages/my_reports.php` | ใช่ | ใช่ | แก้ CSV ให้ใช้ query ถูกชุด |
| `pages/department_reports.php` | ใช่ | ใช่ | toolbar อยู่ติดกับผลลัพธ์รายงาน |
| `pages/daily_schedule.php` | ใช่ | ใช่ | toolbar ขวาจัดและยังรองรับสลับ table/cards ในผลลัพธ์ |
| `pages/manage_users.php` | ใช่ | ใช่ | export + reset อยู่ขวาเหมือนหน้าปฏิบัติการ |
| `pages/db_table_browser.php` | ใช่ | ใช่ | export + add + reset อยู่กลุ่มขวาเดียวกัน |
| `pages/db_change_logs.php` | ใช่ | ใช่ | export + reset อยู่กลุ่มขวาเดียวกัน |
| `pages/time.php` | ใช่ | ไม่เกี่ยวข้อง | มี rows-per-page และ filter ใกล้ history table แต่ไม่มีปุ่ม export ใน toolbar นี้ |

## หมายเหตุ
- มาตรฐานใหม่คือ:
  - ซ้าย: filter/search
  - ขวา: rows-per-page + export group
- บนจอเล็ก `table-export-group` จะ wrap ลงบรรทัดถัดไปโดยยังชิดขวา
