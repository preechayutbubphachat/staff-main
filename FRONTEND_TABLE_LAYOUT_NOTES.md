## Chosen implementation pattern

- ใช้ `colgroup` เป็นกลไกหลักในการล็อกสัดส่วนคอลัมน์แบบเปอร์เซ็นต์
- ใช้ helper กลาง:
  - `app_get_table_layout_widths()`
  - `app_render_table_colgroup()`
- แต่ละ partial เรียก layout ตามชนิดตารางแทนการเขียน `style="width: ..."` ซ้ำกระจาย

## Same-page multi-table consistency

- หน้า Daily Schedule ที่มีหลายตารางซ้ำตามกลุ่มเวร ใช้ layout `daily_roster` เดียวกันทุกกลุ่ม
- ตาราง audit log บนหน้า dashboard ฝั่ง DB admin ใช้ width plan เดียวกับหน้าบันทึกการเปลี่ยนแปลงเต็ม
- ตาราง dynamic browser ของ DB ใช้สูตร generic เดียวกันตามจำนวนคอลัมน์จริง

## Responsiveness preservation

- คง `table-responsive` wrapper เดิมไว้เพื่อไม่ให้เปอร์เซ็นต์ทำให้ใช้งานไม่ได้บนหน้าจอแคบ
- ใช้ `table-layout: fixed` กับตารางหลักที่มี width plan ชัดเจน
- ใช้ `truncate` + `text-overflow: ellipsis` กับคอลัมน์ยาว เช่น ชื่อ/ตำแหน่ง/แผนก/หมายเหตุ
- ปล่อย action cell ให้ wrap ในกรณีมีหลายปุ่ม เพื่อไม่บีบคอลัมน์อื่นจนอ่านยาก

## Special exceptions

- `db_table_generic` ไม่สามารถกำหนดเปอร์เซ็นต์รายชื่อคอลัมน์แบบตายตัวได้ เพราะ schema ต่างกันตามตาราง จึงใช้สูตร:
  - ลำดับ `7%`
  - จัดการ `13%`
  - คอลัมน์ที่เหลือเฉลี่ยพื้นที่ที่เหลือร่วมกัน
- card views ไม่ถูกบังคับด้วย width matrix นี้ เพราะไม่ใช่ `<table>` layout
