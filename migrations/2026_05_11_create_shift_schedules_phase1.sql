CREATE TABLE IF NOT EXISTS shift_schedules (
  id INT(11) NOT NULL AUTO_INCREMENT,
  department_id INT(11) NOT NULL,
  schedule_date DATE NOT NULL,
  shift_type VARCHAR(30) NOT NULL,
  start_time TIME NOT NULL,
  end_time TIME NOT NULL,
  planned_hours DECIMAL(5,2) NOT NULL DEFAULT 0.00,
  status VARCHAR(20) NOT NULL DEFAULT 'draft',
  note TEXT NULL,
  created_by INT(11) NOT NULL,
  published_by INT(11) NULL,
  published_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_shift_schedules_slot (department_id, schedule_date, shift_type, start_time, end_time),
  KEY idx_shift_schedules_department_date (department_id, schedule_date),
  KEY idx_shift_schedules_status (status),
  KEY idx_shift_schedules_shift_type (shift_type),
  KEY idx_shift_schedules_created_by (created_by),
  KEY idx_shift_schedules_published_by (published_by),
  CONSTRAINT fk_shift_schedules_department
    FOREIGN KEY (department_id) REFERENCES departments(id),
  CONSTRAINT fk_shift_schedules_created_by
    FOREIGN KEY (created_by) REFERENCES users(id),
  CONSTRAINT fk_shift_schedules_published_by
    FOREIGN KEY (published_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS shift_assignments (
  id INT(11) NOT NULL AUTO_INCREMENT,
  schedule_id INT(11) NOT NULL,
  staff_id INT(11) NOT NULL,
  assignment_status VARCHAR(20) NOT NULL DEFAULT 'assigned',
  role_note VARCHAR(100) NULL,
  created_by INT(11) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_shift_assignment_schedule_staff (schedule_id, staff_id),
  KEY idx_shift_assignments_schedule (schedule_id),
  KEY idx_shift_assignments_staff (staff_id),
  KEY idx_shift_assignments_status (assignment_status),
  KEY idx_shift_assignments_created_by (created_by),
  CONSTRAINT fk_shift_assignments_schedule
    FOREIGN KEY (schedule_id) REFERENCES shift_schedules(id),
  CONSTRAINT fk_shift_assignments_staff
    FOREIGN KEY (staff_id) REFERENCES users(id),
  CONSTRAINT fk_shift_assignments_created_by
    FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

ALTER TABLE time_logs
  ADD COLUMN IF NOT EXISTS schedule_assignment_id INT(11) NULL AFTER id,
  ADD COLUMN IF NOT EXISTS source VARCHAR(20) NOT NULL DEFAULT 'manual' AFTER schedule_assignment_id;

CREATE INDEX IF NOT EXISTS idx_time_logs_schedule_assignment_id
  ON time_logs (schedule_assignment_id);
