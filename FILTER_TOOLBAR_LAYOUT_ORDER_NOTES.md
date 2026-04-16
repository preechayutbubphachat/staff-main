## 3-Row Layout Rule

หน้าที่มี summary + filter + rows-per-page/export ใช้ลำดับเดียวกันดังนี้

1. `result-summary row`
2. `filter row`
3. `rows-per-page + export row`
4. `result heading / table / cards`

## หน้าที่อัปเดต

- `daily_schedule`
- `department_reports`
- `my_reports`
- `approval_queue`
- `manage_time_logs`

## แนวทาง implementation

- summary row ยังถูกสร้างจาก partial ผลลัพธ์เหมือนเดิม เพื่อให้ข้อมูล summary และผลลัพธ์มาจาก source เดียวกัน
- เพิ่ม summary mount แยกในแต่ละหน้า แล้วใช้ `TableFilters.syncSummaryBlock()` ย้าย summary ที่มากับ partial ไปวางเหนือ filter row
- หน้าที่ใช้ `TableFilters.init()` จะ sync summary ผ่าน `onRefresh`
- หน้าที่ใช้ custom AJAX (`approval_queue`, `manage_time_logs`) จะ sync summary ภายใน script ของหน้าเองหลัง refresh

## การจัด spacing และ alignment

- ใช้ `table-toolbar--filters` สำหรับแถวตัวกรอง
- ใช้ `table-toolbar--actions` สำหรับแถว `rows-per-page + export`
- action row บังคับให้ selector จำนวนรายการกับกลุ่มปุ่มส่งออกอยู่ในแถวเดียวกันก่อน แล้วค่อย wrap เมื่อจอแคบ
- summary mount ใช้ `mb-4` เพื่อเว้นระยะจาก filter row อย่างสม่ำเสมอ

## ข้อยกเว้น

- หัวข้อรายงานแบบ dynamic เช่นหัวข้อรายงานแผนก หรือหัวข้อเวรประจำวัน ยังคงอยู่ก่อนตาราง/การ์ดใน results block เพราะช่วยอธิบายชุดข้อมูลที่กำลังแสดง
- hero ด้านบนของหน้าไม่ได้ถูกย้าย เพราะเป็น page-level context ไม่ใช่ส่วนควบคุมตาราง
