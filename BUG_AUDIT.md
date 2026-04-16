## Checked items

- Select-all visibility in the approval results area.
- Selected count vs rendered modal rows.
- Modal stale state after close / clear selection.
- Scrollable table usability inside the confirmation modal.
- Backend bulk approval action still receiving the correct selected IDs.

## Findings addressed

- The previous confirmation modal split review data into three accordion sections, which made bulk verification slower.
- The previous detail layout could hide repeated selected records behind grouped summaries.
- Select-all existed but was not prominent enough in the results header.

## Current behavior

- Select-all is now exposed with:
  - a checkbox
  - a stronger `เลือกรายการทั้งหมดในหน้าที่เห็น` button
- Modal detail review is now a single row-based table.
- Repeated staff names remain repeated rows when multiple records are selected.
- Old accordion sections for staff / departments / IDs were removed.
- Selection state is cleared from the modal table when clearing selection or closing the modal.

## Manual follow-up still recommended

- Open the approval queue in both `table` and `cards` view and confirm select-all works the same way.
- Test a large selection to confirm the modal scroll area is comfortable on the actual reviewer screen size.
- Confirm the time range format matches reviewer expectations for overnight rows.

## Manual test checklist

- Select-all control is easy to find.
- Clicking select-all selects all current visible rows correctly.
- Selected summary count updates correctly.
- Opening the confirmation modal shows all selected rows in a table.
- Repeated names appear as repeated rows if multiple records are selected.
- Modal table columns show:
  - `ลำดับ`
  - `วันที่`
  - `ชื่อ`
  - `ตำแหน่ง`
  - `แผนก`
  - `เวลา`
- Time shows as one combined range like `08.30 น. - 16.30 น.`
- Old dropdown/accordion sections are removed.
- Scroll works when many rows are selected.
- Confirm action still processes the correct selected records.
- No PHP warnings/notices introduced.
- No JS errors introduced.
