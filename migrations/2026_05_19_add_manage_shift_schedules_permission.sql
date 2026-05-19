ALTER TABLE users
  ADD COLUMN can_manage_shift_schedules TINYINT(1) NOT NULL DEFAULT 0 AFTER can_manage_time_logs;

UPDATE users
SET can_manage_shift_schedules = 1
WHERE role IN ('admin', 'checker')
   OR can_approve_logs = 1
   OR can_manage_time_logs = 1;

ALTER TABLE users
  ADD KEY idx_users_can_manage_shift_schedules (can_manage_shift_schedules);
