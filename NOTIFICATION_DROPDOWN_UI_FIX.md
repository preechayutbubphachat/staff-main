# Notification Dropdown UI Fix

## Old Issues Observed
- ข้อความ title และ message ถูกวางเหมือนลิงก์ข้อความธรรมดา
- เนื้อหา notification ยาวแล้วห่อบรรทัดเละ ทำให้ dropdown ดูรก
- ไม่มี hierarchy ชัดระหว่าง title, message, และเวลา
- unread/read ยังไม่แยกอารมณ์การมองเห็นพอ
- มี style เก่าซ้ำหลายก้อนใน CSS ทำให้ผลลัพธ์ไม่เสถียร

## Final Item Layout Structure
- header ของ dropdown:
  - ชื่อ `การแจ้งเตือน`
  - คำอธิบายสั้น
  - ปุ่ม `อ่านทั้งหมด`
- body:
  - section label `ยังไม่ได้อ่าน`
  - unread items
  - section label `อ่านแล้ว`
  - read items
- item:
  - top row: title + unread dot
  - middle row: short message
  - bottom row: time text
  - read action อยู่ขวาเฉพาะ unread item

## Text Truncation Behavior
- title: 1 line + ellipsis
- message: 1 line + ellipsis
- time: 1 line
- มี `title` attribute บน title/message เพื่อให้ hover ดูข้อความเต็มได้

## Width and Spacing Decisions
- dropdown width ปรับเป็น `min(456px, calc(100vw - 2rem))`
- เพิ่ม padding ให้ body และแยก section label ชัดขึ้น
- item แต่ละอันเป็น block ของตัวเอง ไม่ใช่ข้อความลิงก์กองรวม
- ใช้ card-like clickable row ที่มี border, radius, hover, และ spacing คงที่

## Read / Unread Styling Decisions
- unread item ใช้พื้นหลัง tint อ่อนโทนแดง
- unread dot สีแดงแสดงในแถวหัวข้อ
- read item คงพื้นหลังขาวสะอาดกว่า
- ปุ่ม `อ่านแล้ว` แยกเป็น secondary action ด้านขวา
