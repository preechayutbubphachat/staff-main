ALTER TABLE users
  MODIFY COLUMN role ENUM('admin', 'staff', 'finance', 'checker') NOT NULL DEFAULT 'staff';

ALTER TABLE users
  ADD COLUMN can_edit_locked_time_logs TINYINT(1) NOT NULL DEFAULT 0 AFTER can_manage_time_logs,
  ADD COLUMN can_manage_user_permissions TINYINT(1) NOT NULL DEFAULT 0 AFTER can_edit_locked_time_logs;

CREATE TABLE user_permission_audit_trails (
  id INT(11) NOT NULL AUTO_INCREMENT,
  target_user_id INT(11) NOT NULL,
  action_type VARCHAR(50) NOT NULL,
  old_values_json LONGTEXT DEFAULT NULL,
  new_values_json LONGTEXT DEFAULT NULL,
  actor_user_id INT(11) NOT NULL,
  actor_name_snapshot VARCHAR(100) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  note VARCHAR(255) DEFAULT NULL,
  PRIMARY KEY (id),
  KEY idx_user_permission_audit_target_user_id (target_user_id),
  KEY idx_user_permission_audit_actor_user_id (actor_user_id),
  CONSTRAINT fk_user_permission_audit_target_user
    FOREIGN KEY (target_user_id) REFERENCES users(id),
  CONSTRAINT fk_user_permission_audit_actor_user
    FOREIGN KEY (actor_user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

ALTER TABLE users
  ADD INDEX idx_users_role (role);

UPDATE users
SET
  role = CASE WHEN username = 'admin' THEN 'admin' ELSE role END,
  can_view_all_staff = CASE WHEN username = 'admin' THEN 1 ELSE can_view_all_staff END,
  can_view_department_reports = CASE WHEN username = 'admin' THEN 1 ELSE can_view_department_reports END,
  can_export_reports = CASE WHEN username = 'admin' THEN 1 ELSE can_export_reports END,
  can_approve_logs = CASE WHEN username = 'admin' THEN 1 ELSE can_approve_logs END,
  can_manage_time_logs = CASE WHEN username = 'admin' THEN 1 ELSE can_manage_time_logs END,
  can_edit_locked_time_logs = CASE WHEN username = 'admin' THEN 1 ELSE can_edit_locked_time_logs END,
  can_manage_user_permissions = CASE WHEN username = 'admin' THEN 1 ELSE can_manage_user_permissions END
WHERE username = 'admin';

-- Seed a default admin only if it does not already exist.
-- Temporary password hash is for: TempAdmin!2026#NPH
-- Admin should change this password immediately after first login.
INSERT INTO users (
  username,
  password,
  fullname,
  department_id,
  role,
  can_view_all_staff,
  can_view_department_reports,
  can_export_reports,
  can_approve_logs,
  can_manage_time_logs,
  can_edit_locked_time_logs,
  can_manage_user_permissions,
  is_active
)
SELECT
  'admin',
  '$2y$10$Xr5pbnofon693gIdwic7FeRljzzwplcOeQ4lyuBfKzK92r/Y/HtiO',
  'System Admin',
  NULL,
  'admin',
  1,
  1,
  1,
  1,
  1,
  1,
  1,
  1
WHERE NOT EXISTS (
  SELECT 1 FROM users WHERE username = 'admin'
);
