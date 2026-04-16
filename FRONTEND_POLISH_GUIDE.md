# Frontend Polish Guide

## Visual Consistency Rules Applied
- ใช้โครงร่วมแบบ `page title -> summary cards -> main content panel -> table/form area`
- รักษาโทน calm professional ด้วยพื้นขาวนวล เงาอ่อน และเส้นขอบเบา
- ให้ heading เด่นขึ้น แต่ helper text ต้องถอยลงเป็นชั้นรองเสมอ
- ใช้ปุ่มทรง pill และระยะห่างคงที่กับ action สำคัญ/รอง/อันตราย

## Shared Layout Patterns Used
- Navbar:
  - เมนูหลักชัด
  - notification bell อยู่ในกลุ่ม action เดียวกับบทบาทและ logout
  - unread state ใช้ bell สีแดงอ่อน + badge แดงชัด
- Table sections:
  - toolbar อยู่ติดกับ card/table
  - ซ้ายเป็น filter
  - ขวาเป็น rows-per-page และ export/action
- Forms:
  - section title -> helper text -> field grid -> action row
- Modals:
  - header, body, footer spacing ถูก normalize ผ่าน shared CSS
  - destructive action แยกด้วยสีแดงและระยะห่างจากปุ่มหลัก

## Shared CSS Classes Introduced / Refined
- `notification-bell.has-unread`
- `table-toolbar-head`
- `section-header`
- `badge-chip`
- ปรับ shared behavior ของ:
  - `.panel`
  - `.ops-results-panel`
  - `.report-stat-card`
  - `.metric-card`
  - `.workspace-card`
  - `.profile-card`
  - `.preview-card`
  - `.mini-card`
  - `.modal-content`
  - `.modal-header`
  - `.modal-body`
  - `.modal-footer`

## Table Polish Decisions
- เพิ่มช่องไฟใน `thead` และ `tbody` เพื่อให้อ่านบนจอ laptop ง่ายขึ้น
- รักษา sticky table header เดิม
- ไม่เปลี่ยนโครง async/filter/pagination เดิม เพื่อกัน regression

## Form Polish Decisions
- labels และ helper text ถูกทำให้มีจังหวะระยะห่างใกล้กันมากขึ้น
- inputs/selects ใช้ focus state เดียวกันทั้งระบบ
- helper text หน้า register ใช้ทั้ง inline text และ tooltip เพื่อความเสถียร

## Modal Polish Decisions
- ใช้ shared padding และ radius เดียวกันมากขึ้น
- ลดความรู้สึกว่าแต่ละ modal มาจากคนละระบบ
- ไม่แตะ JS open/close logic ในรอบนี้ ยกเว้นส่วนที่กระทบการแสดงผลโดยตรง
