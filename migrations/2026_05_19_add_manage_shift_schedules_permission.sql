SET @has_manage_shift_schedules_column := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'users'
    AND COLUMN_NAME = 'can_manage_shift_schedules'
);

SET @add_manage_shift_schedules_column_sql := IF(
  @has_manage_shift_schedules_column = 0,
  'ALTER TABLE users ADD COLUMN can_manage_shift_schedules TINYINT(1) NOT NULL DEFAULT 0 AFTER can_manage_time_logs',
  'SELECT 1'
);

PREPARE add_manage_shift_schedules_column_stmt FROM @add_manage_shift_schedules_column_sql;
EXECUTE add_manage_shift_schedules_column_stmt;
DEALLOCATE PREPARE add_manage_shift_schedules_column_stmt;

UPDATE users
SET can_manage_shift_schedules = 1
WHERE role IN ('admin', 'checker', 'finance')
   OR can_approve_logs = 1
   OR can_manage_time_logs = 1;

SET @has_manage_shift_schedules_index := (
  SELECT COUNT(*)
  FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'users'
    AND INDEX_NAME = 'idx_users_can_manage_shift_schedules'
);

SET @add_manage_shift_schedules_index_sql := IF(
  @has_manage_shift_schedules_index = 0,
  'ALTER TABLE users ADD KEY idx_users_can_manage_shift_schedules (can_manage_shift_schedules)',
  'SELECT 1'
);

PREPARE add_manage_shift_schedules_index_stmt FROM @add_manage_shift_schedules_index_sql;
EXECUTE add_manage_shift_schedules_index_stmt;
DEALLOCATE PREPARE add_manage_shift_schedules_index_stmt;
