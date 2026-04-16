# Navbar Behavior Notes

## กติกาการทำงาน
- อยู่บนสุดของหน้า: navbar แสดง
- เลื่อนลงต่อเนื่อง: navbar ซ่อน
- เลื่อนขึ้น: navbar กลับมาแสดง
- กลับถึงด้านบน: navbar แสดงค้าง

## การกัน flicker
- ใช้ threshold การเลื่อนขั้นต่ำก่อนเปลี่ยน state
- ใช้ `requestAnimationFrame` ลดการกระตุกจาก scroll event

## ข้อยกเว้นที่บังคับให้ navbar แสดง
- อยู่ใกล้ด้านบนของหน้า
- มี dropdown ของ navbar เปิดอยู่
- มี collapse menu เปิดอยู่
- มี modal เปิดอยู่

## CSS classes ที่ใช้
- `.navbar-scrolled`
- `.navbar-hidden`

## วิธีซ่อน
- ใช้ `transform: translateY(...)`
- ไม่ใช้การลบ element ออกจาก flow โดยตรง
- ลด layout jump และยังคุม transition ได้เรียบกว่า

## จุดที่ระวัง
- dropdown ต้องไม่โดนซ่อนกลางคัน
- modal interaction ต้องไม่ทำให้ navbar หายจนดูสับสน
- spacing ของหน้าไม่ควรสะดุดเวลาสลับ state
