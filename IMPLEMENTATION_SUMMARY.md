## Scope completed

- Made the select-all action on Approval Queue easier to notice.
- Rebuilt the bulk confirmation modal into a single scrollable row-based table.
- Removed the old selected-staff / departments / IDs accordion sections.

## Select-all visibility

- Added a stronger select-all area in [results_block.php](C:/xampp/htdocs/staff-main/partials/approval/results_block.php).
- Kept the existing select-all checkbox.
- Added a clearer action button:
  - `เลือกรายการทั้งหมดในหน้าที่เห็น`
- The button selects all currently visible selectable rows only.

## Confirmation modal redesign

- The modal in [approval_queue.php](C:/xampp/htdocs/staff-main/pages/approval_queue.php) now shows:
  - summary cards
  - one scrollable selected-items table
  - standard action footer
- The detailed review area is now a real table with columns:
  - `ลำดับ`
  - `วันที่`
  - `ชื่อ`
  - `ตำแหน่ง`
  - `แผนก`
  - `เวลา`

## Removed old sections

Removed the old accordion/dropdown review sections for:

- selected staff names
- related departments
- selected record IDs

Those values are still used internally where needed, but the reviewer now verifies records directly from the selected-items table.

## Selection data flow

- Expanded [get_selection_summary.php](C:/xampp/htdocs/staff-main/ajax/approval/get_selection_summary.php) so it returns row-level details needed by the modal table.
- Updated [approval-queue.js](C:/xampp/htdocs/staff-main/assets/js/approval-queue.js) to render every selected record as its own row.
- Repeated names remain repeated rows when multiple records are selected for the same person.
- Modal table state is reset when clearing selection or closing the modal.

## Styling updates

- Added compact modal table styles and scrollable container behavior in [app-ui.css](C:/xampp/htdocs/staff-main/assets/css/app-ui.css).
- Added clearer select-all styling in the approval results header.

## Files changed

- [approval_queue.php](C:/xampp/htdocs/staff-main/pages/approval_queue.php)
- [approval-queue.js](C:/xampp/htdocs/staff-main/assets/js/approval-queue.js)
- [report_helpers.php](C:/xampp/htdocs/staff-main/includes/report_helpers.php)
- [get_selection_summary.php](C:/xampp/htdocs/staff-main/ajax/approval/get_selection_summary.php)
- [results_block.php](C:/xampp/htdocs/staff-main/partials/approval/results_block.php)
- [app-ui.css](C:/xampp/htdocs/staff-main/assets/css/app-ui.css)
- [IMPLEMENTATION_SUMMARY.md](C:/xampp/htdocs/staff-main/IMPLEMENTATION_SUMMARY.md)
- [APPROVAL_CONFIRM_MODAL_REDESIGN_NOTES.md](C:/xampp/htdocs/staff-main/APPROVAL_CONFIRM_MODAL_REDESIGN_NOTES.md)
- [BUG_AUDIT.md](C:/xampp/htdocs/staff-main/BUG_AUDIT.md)
