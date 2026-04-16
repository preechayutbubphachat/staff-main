## Navbar Priority Order Notes

### Final Order

เมนูด้านหน้าถูกจัดลำดับใหม่ใน [includes/navigation.php](C:/xampp/htdocs/staff-main/includes/navigation.php) เป็น:

1. แดชบอร์ด
2. ลงเวลาเวร
3. ตรวจสอบเวร
4. เวรวันนี้
5. รายงาน
6. งานปฏิบัติการ
7. เกี่ยวกับฉัน
8. หลังบ้าน ตามสิทธิ์

### Permission Rule for Review Menu

- เมนู “ตรวจสอบเวร” แสดงเฉพาะเมื่อ `app_can('can_approve_logs')` เป็นจริง
- การซ่อนเมนูไม่ใช่ตัวป้องกันหลัก ฝั่งหน้าเพจยังต้องผ่าน permission check ตามเดิม
- หน้า approval queue จึงยังปลอดภัยแม้ navbar ถูกจัดลำดับใหม่

### Grouping Adjustments

- “แดชบอร์ด”, “ลงเวลาเวร”, “ตรวจสอบเวร”, “เวรวันนี้” ถูกย้ายออกมาเป็น direct link ด้านหน้า
- “รายงาน” คงบทบาทเป็น dropdown ของหน้ารายงาน
- “งานปฏิบัติการ” รวมหน้าจัดการที่ใช้สิทธิ์สูงกว่า
- “เกี่ยวกับฉัน” ถูกคงเป็นกลุ่มส่วนตัวที่อยู่ถัดจากเมนูงานหลัก

### Navbar Layout Considerations

- ใช้โครงสร้างเดิมของระบบเพื่อคง active state และพฤติกรรม responsive
- notification bell, role badge และปุ่มออกจากระบบไม่ได้ถูกย้ายตำแหน่ง
- การ reorder ทำในระดับ menu model จึงลดความเสี่ยงต่อการแตก layout เมื่อ navbar wrap ในจอแคบ
