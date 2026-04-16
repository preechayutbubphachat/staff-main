ALTER TABLE users
    ADD COLUMN can_manage_database TINYINT(1) NOT NULL DEFAULT 0 AFTER can_manage_user_permissions;

UPDATE users
SET can_manage_database = 1
WHERE role = 'admin';

CREATE TABLE IF NOT EXISTS db_admin_audit_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    table_name VARCHAR(100) NOT NULL,
    row_primary_key VARCHAR(100) NOT NULL,
    action_type VARCHAR(20) NOT NULL,
    old_values_json LONGTEXT NULL,
    new_values_json LONGTEXT NULL,
    actor_user_id INT NOT NULL,
    actor_name_snapshot VARCHAR(100) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    note VARCHAR(255) NULL,
    request_context LONGTEXT NULL,
    KEY idx_db_admin_audit_table_name (table_name),
    KEY idx_db_admin_audit_actor_user_id (actor_user_id),
    KEY idx_db_admin_audit_created_at (created_at),
    CONSTRAINT fk_db_admin_audit_actor_user FOREIGN KEY (actor_user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

ALTER TABLE users
    ADD KEY idx_users_can_manage_database (can_manage_database);
