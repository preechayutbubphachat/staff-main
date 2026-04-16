# UI Copy And Layout Notes

## Notable Wording / Label Adjustments
- `งานของฉัน` -> `เกี่ยวกับฉัน` ใน navbar
- `จำนวนรายการ` -> `จำนวนเวร` เฉพาะรายงานแผนก
- `ชื่อผู้ใช้` -> `ชื่อผู้ใช้ (username)` ในหน้าสมัคร
- eyebrow ภาษาไทยมากขึ้นในหน้า:
  - รายงานแผนก
  - เวรประจำวัน
  - รายงานส่วนตัว
  - คิวตรวจสอบ
  - จัดการหลังบ้าน
  - เกี่ยวกับฉัน
  - การแจ้งเตือน

## Notable Page Header Improvements
- หน้า report/queue ใช้ eyebrow ภาษาไทยและ subtitle ที่ถอยระดับชัดขึ้น
- bell notification ถูกทำให้เด่นใน navbar แต่ยังอยู่ในโทนเรียบร้อย
- back button ถูกซ่อนในหน้า entry หลักเพื่อลดความสับสน

## Notable Toolbar / Table Improvements
- toolbar ถูกเก็บให้ส่วน filter และ export/action ดูเป็นกลุ่มเดียวกันมากขึ้น
- rows-per-page, export buttons และ secondary actions มีรูปทรงและ spacing ใกล้กันขึ้น
- เพิ่มความอ่านง่ายของ table rows และ header บนจอ laptop

## Notable Form / Modal Improvements
- หน้า register มี helper text และ tooltip อธิบาย username ชัดเจนขึ้น
- modal ใช้ shared padding/radius ใหม่ ทำให้ header/body/footer ดูไปด้วยกันมากขึ้น
- profile/manage/admin style blocks ได้ประโยชน์จาก shared form spacing และ card surface ที่นิ่งขึ้น

## Intentionally Deferred
- `C:\xampp\htdocs\staff-main\pages\dashboard.php` และ `C:\xampp\htdocs\staff-main\pages\profile.php` ยังมี inline CSS ขนาดใหญ่บางส่วน ถ้าจะเก็บต่อรอบถัดไปควรย้ายลง shared CSS เพิ่มอีก
- หน้าเริ่มต้น `C:\xampp\htdocs\staff-main\index.php` ยังมีข้อความบางส่วนที่ encoding ใน shell อ่านยาก แม้ PHP ทำงานได้ปกติ ควรตรวจหน้า browser จริงอีกครั้งก่อนเก็บ copy ละเอียดเพิ่ม
- notification page ยังเป็น server-rendered page ปกติ ไม่ได้ทำ infinite updates ในรอบนี้เพื่อคงความเสถียร
