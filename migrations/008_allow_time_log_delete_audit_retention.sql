ALTER TABLE time_log_audit_trails
  DROP FOREIGN KEY fk_time_log_audit_time_log;

ALTER TABLE time_log_audit_trails
  MODIFY time_log_id INT(11) NULL;

ALTER TABLE time_log_audit_trails
  ADD CONSTRAINT fk_time_log_audit_time_log
    FOREIGN KEY (time_log_id) REFERENCES time_logs(id)
    ON DELETE SET NULL
    ON UPDATE CASCADE;
