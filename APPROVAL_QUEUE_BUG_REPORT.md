# Approval Queue Bug Report

## อาการที่พบ
- คลิกแถวแล้วไม่เลือก
- คลิก checkbox แล้ว state ไม่เสถียรในบางจังหวะ
- หลัง filter refresh หรือเปลี่ยนจำนวนรายการต่อหน้า selection ทำงานไม่แน่นอน

## Root Cause

### 1. มี nested form ในหน้า approval queue
- `bulkApproveForm` ครอบ `approvalFilterForm`
- browser จะซ่อม DOM ให้เองเพราะ HTML ไม่ถูกต้อง
- ผลคือ selector และ event behavior ที่คาดหวังจากโค้ด JS ไม่ตรงกับ DOM จริง

### 2. JS selection ผูกกับ node ย่อยแบบครั้งต่อครั้ง
- หลัง AJAX refresh โค้ดเดิมต้อง bind event ใหม่กับ checkbox และ row แต่ละตัว
- วิธีนี้เปราะบางเมื่อ markup เปลี่ยน หรือ browser สร้าง DOM จาก nested form แบบไม่ตรงคาด

## สิ่งที่เปลี่ยนเพื่อแก้

### โครงสร้างหน้า
- แยก `approvalFilterForm` ออกมาเป็น GET form ของ toolbar
- ให้ `bulkApproveForm` ครอบเฉพาะ bulk action bar และผลลัพธ์ที่ต้อง submit

### JavaScript
- เปลี่ยน selection handling เป็น event delegation บน `approvalResultsContainer`
- รองรับ:
  - คลิก checkbox เพื่อเลือก/ยกเลิก
  - คลิกพื้นที่ว่างของแถวเพื่อ toggle checkbox
  - คลิก `select all` เพื่อเลือกเฉพาะรายการที่เลือกได้ในหน้านี้
- กรอง element interactive ไม่ให้ trigger row toggle เช่น:
  - profile link
  - button
  - input/select/textarea
  - bootstrap modal trigger

## พฤติกรรมใหม่

### คลิก checkbox
- เลือก/ยกเลิกรายการได้ตรง ๆ
- แถวถูก highlight ตาม state

### คลิกแถว
- จะ toggle checkbox เฉพาะเมื่อคลิกพื้นที่ที่ไม่ใช่ interactive child element

### หลัง AJAX refresh
- selection จะถูก reset ทุกครั้งเมื่อ dataset เปลี่ยนจาก:
  - filter change
  - rows-per-page change
  - pagination change
  - view change
- เหตุผล: ปลอดภัยกว่าและลดความเสี่ยงในการอนุมัติรายการค้างจากชุดข้อมูลเดิม

### Selection summary
- summary bar อัปเดตตาม `selectedIds` ชุดปัจจุบัน
- ปุ่มเปิด modal จะเปิดได้ก็ต่อเมื่อมีรายการเลือกอย่างน้อย 1 รายการ

## Remaining Edge Cases
- ควรทดสอบจริงอีกครั้งบน browser หลังเปลี่ยน filter รัว ๆ ต่อเนื่อง
- หากในอนาคตมี interactive control ใหม่ในแถว อาจต้องเพิ่ม selector เข้ากลุ่มที่ไม่ให้ trigger row toggle
