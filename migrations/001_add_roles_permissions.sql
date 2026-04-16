ALTER TABLE users
  ADD COLUMN role ENUM('staff','finance','checker') NOT NULL DEFAULT 'staff' AFTER department_id,
  ADD COLUMN can_view_all_staff TINYINT(1) NOT NULL DEFAULT 0 AFTER role,
  ADD COLUMN can_view_department_reports TINYINT(1) NOT NULL DEFAULT 0 AFTER can_view_all_staff,
  ADD COLUMN can_export_reports TINYINT(1) NOT NULL DEFAULT 0 AFTER can_view_department_reports,
  ADD COLUMN can_approve_logs TINYINT(1) NOT NULL DEFAULT 0 AFTER can_export_reports,
  ADD COLUMN updated_at TIMESTAMP NULL DEFAULT NULL AFTER created_at;

UPDATE users
SET
  role = 'staff',
  can_view_all_staff = 0,
  can_view_department_reports = 0,
  can_export_reports = 0,
  can_approve_logs = 0
WHERE role IS NULL OR role = '';
