# Notification UI Notes

## Final Item Layout
- ชื่อเรื่องอยู่บรรทัดแรกด้วยน้ำหนักตัวอักษรชัดกว่า
- ข้อความอธิบายอยู่บรรทัดถัดมาในโทน muted
- เวลาอยู่ล่างสุดเป็นข้อความรอง
- ปุ่ม `อ่านแล้ว` อยู่ด้านขวาและไม่ดันข้อความหลักให้เสียจังหวะ

## Truncation Strategy
- `notification-title` ใช้ single-line ellipsis
- `notification-message` ใช้ single-line ellipsis
- `notification-meta` ใช้ single-line ellipsis
- เพิ่ม `title` attribute ให้ title/message เพื่อให้ hover ดูข้อความเต็มได้เมื่อจำเป็น

## Dropdown Width / Layout Decisions
- ขยายความกว้างจากประมาณ `400px` เป็น `440px` แบบ responsive
- คง `max-width` ตาม `calc(100vw - 2rem)` เพื่อไม่ล้นจอเล็ก
- บังคับ `min-width: 0` กับ content wrapper เพื่อให้ ellipsis ทำงานเสถียรใน flex layout

## Thai Readability Considerations
- รักษาโครงแบบ `ชื่อเรื่อง -> ข้อความ -> เวลา` เพื่อให้อ่านไทยได้เร็ว
- เลือกตัดข้อความแบบบรรทัดเดียวเพื่อให้ dropdown นิ่งและไม่เกิดการหักบรรทัดแปลก ๆ
- ลดปัญหาข้อความยาวชนกับปุ่ม `อ่านแล้ว` และไม่ให้ timestamp เละรวมกับ body text
