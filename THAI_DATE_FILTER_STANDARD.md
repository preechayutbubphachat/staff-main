# Thai Date Filter Standard

## Thai Date Placeholder Standard
- ช่องวันที่แบบ native `input[type="date"]` ใช้ข้อความกำกับภาษาไทยใต้ช่องเป็นมาตรฐาน
- เมื่อยังไม่เลือกค่า:
  - `รูปแบบ วัน/เดือน/ปี`
- เมื่อเลือกค่าแล้ว:
  - แสดงผลในรูปแบบ `9 เมษายน 2569`

## Thai Month/Year Display Standard
- ช่องเดือนแบบ native `input[type="month"]` ใช้ข้อความกำกับภาษาไทยใต้ช่องเป็นมาตรฐาน
- เมื่อยังไม่เลือกค่า:
  - `รูปแบบ เดือน/ปี`
- เมื่อเลือกค่าแล้ว:
  - แสดงผลในรูปแบบ `เมษายน 2569`

## Full Month vs Short Month Rules
- หัวรายงาน, hero heading, summary และ report context:
  - ใช้ชื่อเดือนเต็ม เช่น `เมษายน 2569`
- พื้นที่แคบหรือ chip/compact control:
  - สามารถใช้ชื่อเดือนย่อ เช่น `เม.ย. 2569`
- ฝั่ง PHP เตรียม helper รายชื่อเดือนย่อไว้แล้วใน `app_thai_month_short_names()`

## Buddhist Era Conversion Rule
- ทุกข้อความภาษาไทยที่แสดงปีให้ผู้ใช้เห็น ใช้ปี พ.ศ.
- การแปลงใช้หลัก:
  - `พ.ศ. = ค.ศ. + 543`

## Native Browser Date/Month Input Strategy
- ไม่พึ่ง placeholder ของ browser อย่างเดียว เพราะบาง browser ยังแสดง `mm/dd/yyyy` หรือชื่อเดือนภาษาอังกฤษ
- ใช้ shared JS:
  - `C:\xampp\htdocs\staff-main\assets\js\thai-date-ui.js`
- shared JS จะ:
  - สแกน `input[type="date"]` และ `input[type="month"]`
  - เติมข้อความไทยกำกับใต้ช่องอัตโนมัติ
  - อัปเดตข้อความเมื่อค่าเปลี่ยน
  - ติดตาม DOM ที่รีเฟรชแบบ AJAX ผ่าน `MutationObserver`

## Shared Styling
- ใช้ CSS กลางใน:
  - `C:\xampp\htdocs\staff-main\assets\css\app-ui.css`
- class ที่เกี่ยวข้อง:
  - `.thai-date-input`
  - `.thai-date-display`
  - `.thai-date-display.is-empty`

## Filter Submission Compatibility
- ค่าที่ส่งขึ้น backend ยังคงเป็น machine-readable format เดิม:
  - วันที่: `YYYY-MM-DD`
  - เดือน: `YYYY-MM`
- การเปลี่ยนในรอบนี้เป็นการปรับ visible display เท่านั้น จึงไม่เปลี่ยน query/filter behavior เดิม
