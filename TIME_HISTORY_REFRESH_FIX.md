# Time History Refresh Fix

## Expected Behavior
- บันทึกรายการลงเวลาเวรสำเร็จ
- แสดงข้อความสำเร็จ
- ประวัติย้อนหลัง refresh ตาม scope ที่ผู้ใช้กำลังดูอยู่
- รายการใหม่แสดงขึ้นโดยไม่ทำให้รายการเก่าใน scope เดิมหายไป

## Actual Bug Found
- หลัง save สำเร็จ รายการใหม่อาจแสดง แต่ประวัติรายการเก่าหายไปจากหน้าจอ
- อาการนี้ทำให้เหมือนระบบเหลือแค่รายการล่าสุด ทั้งที่ข้อมูลเดิมยังอยู่

## Root Cause
- ปัญหาไม่ได้อยู่ที่ query ประวัติหลักหรือ AJAX refresh endpoint
- ปัญหาจริงอยู่ที่ save success flow ใน `C:\xampp\htdocs\staff-main\pages\time.php`
- หลังบันทึกสำเร็จ ระบบ redirect ไป `time.php?date=<today>&p=1` แบบบังคับ
- เมื่อ redirect แบบนี้:
  - history จะถูก filter เป็นเฉพาะวันที่ปัจจุบันทันที
  - ถ้าก่อนหน้าผู้ใช้กำลังดูประวัติแบบไม่กรองวันที่ หรือกำลังดู scope ที่กว้างกว่า รายการเก่าจะหายจากมุมมองทันที

## How The Fix Works
- เก็บ state ของ history เดิมจาก request ปัจจุบัน:
  - `history_date`
  - `history_per_page`
  - `history_page`
- ส่ง state นี้ผ่าน hidden fields ในฟอร์มสร้างรายการใหม่
- เมื่อ save สำเร็จ:
  - ใช้ state เดิมในการ redirect กลับ
  - คง `date` และ `per_page` เดิมไว้
  - reset หน้าเป็น `p=1` สำหรับ create flow เพื่อให้รายการใหม่มีโอกาสแสดง โดยไม่บีบ scope แคบลงเอง

## History State Preservation Rules
- ถ้ามี date filter อยู่:
  - คง date เดิมไว้
  - แสดงทุกรายการที่ตรงกับวันนั้นต่อไป
- ถ้าไม่มี date filter:
  - คง recent history แบบกว้างไว้
  - ไม่เปลี่ยนเป็น `date=today` โดยอัตโนมัติ
- ถ้ามีการกำหนด rows-per-page:
  - คง `per_page` เดิมไว้

## Why Older Items Now Stay Visible
- เพราะหลัง save ระบบไม่บังคับเปลี่ยน visible scope ของ history อีกแล้ว
- ตัว render ประวัติยังใช้ helper ชุดเดิมทั้งใน initial render และ refresh endpoint
- ดังนั้นรายการเก่าจะยังอยู่ตราบใดที่ยังตรงกับ filter/date scope ที่ผู้ใช้เลือกไว้
