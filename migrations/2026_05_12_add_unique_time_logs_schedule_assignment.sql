CREATE UNIQUE INDEX IF NOT EXISTS uq_time_logs_schedule_assignment_id
  ON time_logs (schedule_assignment_id);
