# Table UI Standardization

## เป้าหมายของรอบนี้
ทำให้ทุกหน้าที่มีตารางหรือผลลัพธ์แบบ list ใช้ pattern เดียวกัน:
1. หัวหน้า / คำอธิบาย
2. summary cards ถ้ามี
3. table card
4. toolbar อยู่ในหัว table card
5. table / result list
6. pagination

## สรุปรายหน้าที่มีตาราง

| หน้า | ปัญหาเดิม | layout ใหม่ | ตำแหน่ง rows-per-page | ตำแหน่ง export |
|---|---|---|---|---|
| `my_reports.php` | filter ลอยแยกจากตาราง | toolbar อยู่ในหัวการ์ดรายงาน | ขวาของ toolbar | ขวาของ toolbar |
| `department_reports.php` | filter อยู่สูงเกินไป | toolbar อยู่ในหัวการ์ดรายงานแผนก | ขวาของ toolbar | ขวาของ toolbar |
| `daily_schedule.php` | filter กับ table แยกชั้นกัน | toolbar อยู่ในหัวการ์ดตารางเวรประจำวัน | ขวาของ toolbar | ขวาของ toolbar |
| `approval_queue.php` | filter แยก panel จากตาราง | ย้ายเข้า table card เดียวกับคิวตรวจสอบ | ขวาของ toolbar | ขวาของ toolbar |
| `manage_time_logs.php` | filter แยก panel จากผลลัพธ์ | ย้ายเข้า table card เดียวกับรายการลงเวลา | ขวาของ toolbar | ขวาของ toolbar |
| `time.php` | history controls กระจาย | รวม filter history ไว้ใน panel เดียวกับรายการย้อนหลัง | อยู่กับ filter history | หน้า time ไม่มี export หลักใน history |
| `manage_users.php` | หลังบ้านยังไม่ใช้ pattern เดียวกัน | ใช้ table card มาตรฐาน | ขวาของ toolbar | ขวาของ toolbar |
| `db_table_browser.php` | search/action กระจาย | toolbar อยู่ในหัวการ์ดตารางฐานข้อมูล | ขวาของ toolbar | ขวาของ toolbar |
| `db_change_logs.php` | audit table ยังไม่เป็นระบบเดียวกัน | toolbar อยู่ในหัวการ์ด log | ขวาของ toolbar | ขวาของ toolbar |

## Pattern กลางที่ใช้

### ส่วนหัวของการ์ดตาราง
- `table-toolbar`
- `table-toolbar-main`
- `table-toolbar-title`
- `table-toolbar-help`
- `table-toolbar-form`
- `table-toolbar-side`

### พฤติกรรมร่วม
- filters อยู่ใกล้ตาราง
- rows-per-page อยู่ข้าง export
- reset filter อยู่ชุดเดียวกัน
- ถ้ามี AJAX อยู่แล้ว จะ refresh เฉพาะ result block
- ถ้า JS ใช้ไม่ได้ หน้า PHP ยังใช้งานได้ตามปกติ

## หมายเหตุเรื่อง rows-per-page
ใช้รูปแบบเดียวกันทั้งระบบ:
- `แสดง [10|20|50|100] รายการต่อหน้า`

## หมายเหตุเรื่อง export
- export อิง filter scope เดียวกับหน้าจอ
- on-screen table เป็นแบบแบ่งหน้า
- export เป็น “ทั้งชุดข้อมูลตามตัวกรองปัจจุบัน” ไม่ใช่เฉพาะหน้าปัจจุบัน เว้นแต่หน้าดังกล่าวกำหนดต่างออกไปใน business rule

## หมายเหตุเรื่อง layout
- summary cards ยังอยู่เหนือ table card ได้ตามปกติ
- แต่ filter ที่มีผลกับตาราง ต้องอยู่ในหัวของ table card เท่านั้น
- ลดช่องว่างแนวตั้งระหว่าง filter กับ table เพื่อให้ความสัมพันธ์ชัดขึ้น
