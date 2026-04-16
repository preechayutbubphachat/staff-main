ALTER TABLE users
  ADD COLUMN can_manage_time_logs TINYINT(1) NOT NULL DEFAULT 0 AFTER can_approve_logs;

UPDATE users
SET can_manage_time_logs = 1
WHERE role = 'checker';

CREATE TABLE time_log_audit_trails (
  id INT(11) NOT NULL AUTO_INCREMENT,
  time_log_id INT(11) NOT NULL,
  action_type VARCHAR(50) NOT NULL,
  old_values_json LONGTEXT DEFAULT NULL,
  new_values_json LONGTEXT DEFAULT NULL,
  actor_user_id INT(11) NOT NULL,
  actor_name_snapshot VARCHAR(100) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  note VARCHAR(255) DEFAULT NULL,
  PRIMARY KEY (id),
  KEY idx_time_log_audit_time_log_id (time_log_id),
  KEY idx_time_log_audit_actor_user_id (actor_user_id),
  CONSTRAINT fk_time_log_audit_time_log
    FOREIGN KEY (time_log_id) REFERENCES time_logs(id),
  CONSTRAINT fk_time_log_audit_actor_user
    FOREIGN KEY (actor_user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

ALTER TABLE time_logs
  ADD INDEX idx_time_logs_work_date (work_date);

ALTER TABLE users
  ADD INDEX idx_users_fullname (fullname),
  ADD INDEX idx_users_position_name (position_name);
