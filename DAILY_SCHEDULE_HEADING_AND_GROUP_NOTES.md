## Daily Schedule Heading And Group Notes

### Heading Context Source Of Truth

daily schedule ใช้ helper กลาง `app_get_daily_schedule_heading_context(...)` ใน [includes/report_helpers.php](C:/xampp/htdocs/staff-main/includes/report_helpers.php) เป็นแหล่งข้อมูลเดียวสำหรับ:

- main heading
- table context label
- scope label
- Thai date wording

ทั้ง first load, partial refresh และ render ใน partial จึงอ่านจาก context ชุดเดียวกัน

### Wording Rules

- ถ้าไม่ได้เลือกแผนก:
  - `รายงานเวรประจำวัน ทุกแผนก ประจำวันที่ {วันที่ไทย}`
- ถ้าเลือกแผนก:
  - `รายงานเวรประจำวัน แผนก {ชื่อแผนก} ประจำวันที่ {วันที่ไทย}`

สำหรับ line บอกบริบทของตาราง:

- ทุกแผนก:
  - `ตารางเวรประจำวันที่เลือก ทุกแผนก`
- แผนกเดียว:
  - `ตารางเวรประจำวันที่เลือก แผนก {ชื่อแผนก}`

### Grouped Section Heading Format

ใช้ helper `app_get_daily_shift_group_display_meta(...)` สร้างข้อความหัวข้อกลุ่มในรูปแบบ:

- `{ชื่อกะ} / {จำนวนรายการ} รายการ / {ช่วงเวลา}` สำหรับกะมาตรฐาน
- `{ชื่อกะ} / {จำนวนรายการ} รายการ` สำหรับ `เวลาอื่นๆ`

### Fixed Time Suffix Rules

- เวรเช้า:
  - `เวลา 08.30 น. - 16.30 น.`
- เวรบ่าย:
  - `เวลา 16.30 น. - 00.30 น.`
- เวรดึก:
  - `เวลา 00.30 น. - 08.30 น.`
- เวลาอื่นๆ:
  - ไม่มี suffix เวลาคงที่

### Handling Of “เวลาอื่นๆ”

- `เวลาอื่นๆ` ยังถูกจัดกลุ่มตาม logic เดิม
- แต่ไม่ถูกต่อท้ายด้วยช่วงเวลามาตรฐาน เพราะเวลาเข้าออกจริงอาจแตกต่างกันต่อคน
- จึงแสดงเพียงชื่อกลุ่มและจำนวนรายการ
