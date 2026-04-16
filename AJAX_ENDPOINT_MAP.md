# AJAX Endpoint Map

| Endpoint | Method | Domain | Request params | Response | Permission | Used by |
|---|---|---|---|---|---|---|
| `ajax/profile/get_staff_profile.php` | `GET` | profile | `id` | HTML | login + scope check | approval, manage time logs, future shared pages |
| `ajax/approval/list_rows.php` | `GET` | approval | `name`, `position_name`, `department`, `date_from`, `date_to`, `status`, `view`, `p` | HTML | `can_approve_logs` | `pages/approval_queue.php` |
| `ajax/approval/get_selection_summary.php` | `POST` | approval | `_csrf`, `selected_ids[]` | JSON | `can_approve_logs` | `pages/approval_queue.php` |
| `ajax/approval/bulk_approve.php` | `POST` | approval | `_csrf`, `selected_ids[]` | JSON | `can_approve_logs` | `pages/approval_queue.php` |
| `ajax/time/get_time_log.php` | `GET` | time | `id`, optional `p`, `date` | HTML | login + owner check + lock check | `pages/time.php` |
| `ajax/time/update_time_log.php` | `POST` | time | `_csrf`, `id`, time form fields | JSON | login + owner check + lock check | `pages/time.php` |
| `ajax/time/history_rows.php` | `GET` | time | `p`, `date` | HTML | login | `pages/time.php` |
| `ajax/manage_time_logs/list_rows.php` | `GET` | manage_time_logs | `name`, `position_name`, `department`, `date_from`, `date_to`, `status`, `p` | HTML | `can_manage_time_logs` | `pages/manage_time_logs.php` |
| `ajax/manage_time_logs/get_row.php` | `GET` | manage_time_logs | `id` | HTML | `can_manage_time_logs` + scope/lock check | `pages/manage_time_logs.php` |
| `ajax/manage_time_logs/update_row.php` | `POST` | manage_time_logs | `_csrf`, `id`, `work_date`, `time_in`, `time_out`, `note` | JSON | `can_manage_time_logs` + scope/lock check | `pages/manage_time_logs.php` |

## Planned phase 2 endpoints

| Planned endpoint | Method | Domain | Planned use |
|---|---|---|---|
| `ajax/reports/daily_schedule_rows.php` | `GET` | reports | refresh daily schedule table |
| `ajax/reports/department_rows.php` | `GET` | reports | refresh department report results |
| `ajax/reports/my_report_rows.php` | `GET` | reports | refresh my reports results |
| `ajax/users/check_username.php` | `GET/POST` | users | username uniqueness check in admin user edit |
