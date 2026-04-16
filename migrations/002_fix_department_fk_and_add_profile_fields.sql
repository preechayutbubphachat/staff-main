ALTER TABLE users
  DROP FOREIGN KEY fk_users_department;

ALTER TABLE time_logs
  DROP FOREIGN KEY fk_time_logs_department;

ALTER TABLE users
  ADD COLUMN IF NOT EXISTS position_name VARCHAR(100) NULL AFTER fullname,
  ADD COLUMN IF NOT EXISTS phone_number VARCHAR(30) NULL AFTER position_name;

ALTER TABLE users
  ADD CONSTRAINT fk_users_department
    FOREIGN KEY (department_id) REFERENCES departments(id);

ALTER TABLE time_logs
  ADD CONSTRAINT fk_time_logs_department
    FOREIGN KEY (department_id) REFERENCES departments(id);
