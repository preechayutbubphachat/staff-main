# Frontend Cleanup Notes

## Major Layout Improvements
- ปรับ shared navbar และ notification bell ใน `C:\xampp\htdocs\staff-main\assets\css\app-ui.css` ให้ดูชัดขึ้นแต่ยังอยู่ในโทน calm back-office
- เพิ่มการคุม visual hierarchy ของ heading, subtitle, panel และ table toolbar แบบ shared แทนการแต่งเฉพาะหน้า
- เก็บคำบน eyebrow ของหน้าหลักให้เป็นไทยมากขึ้น เพื่อให้หน้า report/queue ดูเป็นระบบเดียวกัน

## Navbar Cleanup Decisions
- เปลี่ยนเมนูส่วนตัวเป็น `เกี่ยวกับฉัน` เพราะสื่อความหมายกว้างกว่า `งานของฉัน`
- notification bell ใช้โทนแดงอ่อนเป็นค่าเริ่มต้น และเข้มขึ้นเมื่อมี unread เพื่อให้มองออกทันที
- unread count ยังคงใช้ badge เดิม แต่เพิ่มการ sync class จาก `notifications.js` เพื่อให้สี bell เปลี่ยนตาม polling จริง

## Heading Hierarchy Decisions
- คงโครงเดิมของแต่ละหน้าไว้ แต่ปรับ shared rule ให้:
  - `h1` และ section title อ่านเด่นขึ้น
  - helper/subtitle มี line-height นิ่งขึ้น
  - panel และ report cards มีน้ำหนักขอบ/เงาใกล้กันมากขึ้น
- หลีกเลี่ยงการ redesign ใหม่ทั้งหน้าเพื่อลดความเสี่ยงกับระบบงานจริง

## Helper Text / Tooltip Decisions
- หน้า `C:\xampp\htdocs\staff-main\auth\register.php` ใช้ทั้ง helper text ใต้ช่องและ tooltip บน icon
- helper text เป็นตัวหลักเพราะเสถียรกว่า tooltip บนเครื่องงานจริง
- tooltip ถูกเพิ่มเป็นตัวช่วยเสริมผ่าน Bootstrap bundle เฉพาะหน้าสมัคร

## Reusable CSS Classes / Shared Rules Introduced
- ปรับสถานะ `has-unread` ให้ `notification-bell`
- เพิ่ม shared polish ให้:
  - `.ops-hero h1`
  - `.hero h1`
  - `.card-title`
  - `.section-title`
  - `.ops-results-title`
  - `.table-toolbar-title`
- ปรับ subtitle / helper text ให้ line-height สม่ำเสมอขึ้น
- ปรับ `.panel`, `.ops-results-panel`, `.report-stat-card`, `.mini-card`, `.metric-card`, `.workspace-card`, `.profile-card`, `.preview-card` ให้มีกรอบและน้ำหนักใกล้กันมากขึ้น
