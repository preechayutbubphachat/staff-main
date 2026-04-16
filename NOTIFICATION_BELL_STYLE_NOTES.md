# Notification Bell Style Notes

## Default State
- ใช้ปุ่มทรงกลมมนขนาด 52x52 พิกเซล
- พื้นหลังโทนขาวเทาอ่อน
- icon สีเข้มเพื่อให้ยังอ่านเป็นปุ่มแจ้งเตือนได้ชัด แม้ยังไม่มี unread
- เพิ่มขอบและเงาอ่อน ๆ เพื่อให้ปุ่มไม่จมหายไปกับ navbar

## Unread State
- ใช้ปุ่มพื้นแดงแบบ gradient เพื่อให้เห็นทันทีว่าเป็น notification ที่ต้องสนใจ
- icon เปลี่ยนเป็นสีขาวเพื่อเพิ่ม contrast
- เพิ่ม glow อ่อน ๆ รอบปุ่มเพื่อช่วยให้ unread state เด่นกว่าปุ่มปกติ แต่ยังไม่ดูฉูดฉาด
- ใช้ state class:
  - `.notification-bell`
  - `.notification-bell--active`
  - `.has-unread`

## Badge Styling Decisions
- badge ใหญ่ขึ้นจากเดิมเพื่อให้เลข `1` ก็ยังอ่านง่าย
- badge วางลอยที่มุมขวาบนของปุ่ม
- ใช้พื้นแดงเข้มกว่าเล็กน้อย พร้อมตัวอักษรสีขาว
- เพิ่ม white ring และ shadow เพื่อไม่ให้ badge กลืนกับปุ่ม unread สีแดง

## Alignment And Spacing Decisions
- คง markup เดิมของ dropdown เพื่อไม่กระทบ behavior
- ใช้การขยายขนาดปุ่มและจัดตำแหน่ง badge ผ่าน CSS เท่านั้น
- รักษา click target เดิมของ bell และไม่เปลี่ยนโครงสร้าง navbar ส่วนอื่น
