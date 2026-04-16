## Final modal structure

The confirmation modal now follows this layout:

1. title: `ยืนยันการตรวจสอบรายการที่เลือก`
2. summary cards:
   - `จำนวนรายการที่เลือก`
   - `จำนวนเจ้าหน้าที่ไม่ซ้ำ`
   - `จำนวนแผนกไม่ซ้ำ`
3. one scrollable selected-items table
4. footer actions:
   - `ยกเลิก`
   - `ยืนยันตรวจสอบรายการ`

## Selected-items table columns

The modal table now shows:

- `ลำดับ`
- `วันที่`
- `ชื่อ`
- `ตำแหน่ง`
- `แผนก`
- `เวลา`

`เวลา` is rendered as one combined Thai-friendly range such as:

- `08.30 น. - 16.30 น.`
- `16.30 น. - 00.30 น.`

## Scrollable area behavior

- The selected-items table is wrapped in a fixed-height scrollable container.
- Modal height stays manageable on laptop screens.
- Footer buttons remain reachable even when many rows are selected.
- Table header remains readable inside the scroll area.

## Repeated selected rows

- Rows are not grouped by staff name.
- If one person has multiple selected records, each selected record is rendered as its own row in the modal table.
- This keeps reviewer verification at row level instead of person-summary level.

## Selection state -> modal table flow

- Selection state still comes from the current checkbox set on the approval results view.
- `get_selection_summary.php` now returns row-level details for the currently selected IDs.
- `approval-queue.js` renders those rows directly into the modal table.
- The modal table is reset when:
  - selection is cleared
  - the modal is closed

## Select-all behavior

- The results block now exposes both:
  - a select-all checkbox
  - a clearer action button: `เลือกรายการทั้งหมดในหน้าที่เห็น`
- This selects all currently visible selectable rows only.
