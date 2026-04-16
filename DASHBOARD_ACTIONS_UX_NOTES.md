## Final dashboard action structure

1. Summary cards remain near the top as passive status information.
2. A dedicated `ทางลัดการใช้งาน` section now appears directly after the summary row.
3. The profile card still shows personal information, but now ends with an explicit profile-management CTA.
4. Operational shortcut cards below the profile area now use visible action buttons instead of small inline links.
5. Supporting cards such as latest activity and review queue also include clear follow-up actions.

## Actions shown by role / permission

Always shown:
- ลงเวลาเวร
- เวรวันนี้
- รายงานของฉัน
- โปรไฟล์และลายเซ็น

Shown only when permitted:
- ตรวจสอบเวร: `can_approve_logs`
- รายงานแผนก: `can_view_department_reports`
- พื้นที่ผู้ดูแล / จัดการผู้ใช้งาน / จัดการลงเวลาเวร: based on the strongest available admin-style permission among:
  - `can_manage_database`
  - `can_manage_user_permissions`
  - `can_manage_time_logs`

## Button and CTA wording used

Quick actions / card actions now use direct action wording:
- `ไปหน้าลงเวลาเวร`
- `เปิดหน้าตรวจสอบเวร`
- `เปิดตารางเวรวันนี้`
- `เปิดรายงานของฉัน`
- `เปิดรายงานแผนก`
- `จัดการโปรไฟล์และรูปภาพ`
- `เปิดหน้าหลังบ้าน`
- `เปิดหน้าผู้ดูแลผู้ใช้งาน`
- `เปิดหน้าจัดการลงเวลาเวร`

## Visual clickability decisions

- Important shortcuts use dedicated action cards with icon + heading + CTA button.
- CTA buttons use pill styling and arrow icons so users can immediately recognize them as navigation actions.
- Informational cards remain visually calmer than quick-action cards.
- Operational cards now include button footers so users do not have to guess whether the whole card or only a text link is clickable.
- Hover elevation is applied to the quick-action cards to reinforce clickability without making the dashboard feel flashy.

## Notes

- This task intentionally updates only the dashboard page UX, not the navbar.
- Existing dashboard data and permission checks were preserved.
