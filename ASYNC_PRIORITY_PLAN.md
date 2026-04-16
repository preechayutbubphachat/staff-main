# Async Priority Plan

## แนวทาง

ระบบนี้ไม่ควรแปลงเป็น SPA ทั้งระบบในรอบเดียว เพราะเป็นงาน operational ที่ต้องรักษา fallback แบบ PHP page เดิมไว้เสมอ

แนวทางที่ใช้คือ:
- PHP page เดิมยังใช้งานได้ครบ
- JavaScript + AJAX ช่วยเฉพาะจุดที่คุ้มค่าและเสี่ยงต่ำ
- export/print ยังเป็น server-driven

## Priority order

### Phase 1: Operational workflows ที่ได้ประโยชน์สูงสุด

#### 1. `pages/approval_queue.php`
เหตุผล:
- มี filter + bulk selection + approve action
- เดิม reload ทั้งหน้าหลัง approve ทำให้ flow ขาด
- เหมาะกับ partial refresh มากที่สุด

ส่วนที่ทำ async แล้ว:
- results block
- page/view switching
- selection summary ก่อนอนุมัติ
- bulk approve submit
- profile modal

ยังคง full-page ไว้:
- export / print

#### 2. `pages/time.php`
เหตุผล:
- จุดสับสนหลักคือฟอร์มใหม่กับฟอร์มแก้ไขแข่งกันบนหน้า
- modal + async save ช่วยลดความสับสนมาก

ส่วนที่ทำ async แล้ว:
- โหลด modal แก้ไข
- submit modal edit
- refresh history block หลัง save

ยังคง full-page ไว้:
- create new time entry
- date filter form หลัก
- export/report link ภายนอก

#### 3. `pages/manage_time_logs.php`
เหตุผล:
- ฝั่ง back office ต้องค้นหาและแก้ไขหลายรายการเร็ว
- การ reload ทั้งหน้าทุกครั้งทำให้ช้าและหลุดบริบท

ส่วนที่ทำ async แล้ว:
- filter/search result block
- open edit modal
- submit edit
- refresh result block
- profile modal

ยังคง full-page ไว้:
- reset approval fallback form
- export/print

### Phase 2: Viewing/report workflows

#### 4. `pages/daily_schedule.php`
- ควร refresh table ตาม filter
- profile modal ใช้ shared endpoint ได้ทันที

#### 5. `pages/department_reports.php`
- refresh result table + summary block
- export ยัง server-driven

#### 6. `pages/my_reports.php`
- refresh result block ตาม filter

### Phase 3: Secondary admin/support workflows

#### 7. Admin user management pages
- username validation
- avatar/signature preview
- partial save blocks

#### 8. Dashboard widgets
- manual refresh หรือ periodic refresh เฉพาะ widget

## หลักการที่จงใจ “ยังไม่แปลง”

- ไม่ทำ raw JSON-only frontend flow แทน PHP page
- ไม่ย้าย export / print / PDF ไปทำฝั่ง browser
- ไม่ตัด full-page fallback ออก
