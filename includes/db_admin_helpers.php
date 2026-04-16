<?php

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/report_helpers.php';

function app_db_admin_tables(): array
{
    return [
        'users' => [
            'label' => 'ผู้ใช้งาน',
            'description' => 'จัดการข้อมูลผู้ใช้งาน สิทธิ์ และข้อมูลประจำตัว โดยไม่เปิดเผยรหัสผ่าน',
            'primary_key' => 'id',
            'primary_key_qualified' => 'u.id',
            'browse_columns' => ['id', 'username', 'fullname', 'role', 'position_name', 'phone_number', 'department_name', 'is_active'],
            'searchable_columns' => ['u.username', 'u.fullname', 'u.position_name'],
            'editable_columns' => ['fullname', 'role', 'department_id', 'position_name', 'phone_number', 'is_active', 'can_view_all_staff', 'can_view_department_reports', 'can_export_reports', 'can_approve_logs', 'can_manage_time_logs', 'can_edit_locked_time_logs', 'can_manage_user_permissions', 'can_manage_database'],
            'createable_columns' => [],
            'readonly_columns' => ['id', 'username', 'password', 'created_at', 'updated_at', 'signature_path', 'profile_image_path'],
            'delete_allowed' => false,
            'create_allowed' => false,
            'edit_allowed' => true,
            'default_order' => 'u.fullname ASC, u.id ASC',
            'from_sql' => 'users u LEFT JOIN departments d ON u.department_id = d.id',
            'select_sql' => 'u.*, d.department_name',
            'field_meta' => [
                'fullname' => ['label' => 'ชื่อ - นามสกุล', 'type' => 'text', 'required' => true],
                'role' => ['label' => 'บทบาท', 'type' => 'select', 'required' => true, 'options_callback' => 'app_db_admin_role_options'],
                'department_id' => ['label' => 'แผนก', 'type' => 'select', 'required' => false, 'options_callback' => 'app_db_admin_department_options'],
                'position_name' => ['label' => 'ตำแหน่ง', 'type' => 'text', 'required' => false],
                'phone_number' => ['label' => 'เบอร์โทร', 'type' => 'text', 'required' => false],
                'is_active' => ['label' => 'สถานะใช้งาน', 'type' => 'boolean'],
                'can_view_all_staff' => ['label' => 'ดูข้อมูลรายบุคคล', 'type' => 'boolean'],
                'can_view_department_reports' => ['label' => 'ดูรายงานแผนก', 'type' => 'boolean'],
                'can_export_reports' => ['label' => 'ส่งออกรายงาน', 'type' => 'boolean'],
                'can_approve_logs' => ['label' => 'ตรวจสอบรายการเวร', 'type' => 'boolean'],
                'can_manage_time_logs' => ['label' => 'จัดการลงเวลาเวร', 'type' => 'boolean'],
                'can_edit_locked_time_logs' => ['label' => 'แก้รายการที่ล็อกแล้ว', 'type' => 'boolean'],
                'can_manage_user_permissions' => ['label' => 'จัดการสิทธิ์ผู้ใช้', 'type' => 'boolean'],
                'can_manage_database' => ['label' => 'จัดการข้อมูลฐานข้อมูล', 'type' => 'boolean'],
            ],
            'identifier_columns' => ['username', 'fullname'],
        ],
        'departments' => [
            'label' => 'แผนก',
            'description' => 'จัดการรายการแผนกที่ใช้ในระบบลงเวลาเวร',
            'primary_key' => 'id',
            'primary_key_qualified' => 'departments.id',
            'browse_columns' => ['id', 'department_name', 'department_code', 'created_at'],
            'searchable_columns' => ['department_name', 'department_code'],
            'editable_columns' => ['department_name', 'department_code'],
            'createable_columns' => ['department_name', 'department_code'],
            'readonly_columns' => ['id', 'created_at'],
            'delete_allowed' => true,
            'create_allowed' => true,
            'edit_allowed' => true,
            'default_order' => 'department_name ASC, id ASC',
            'from_sql' => 'departments',
            'select_sql' => '*',
            'field_meta' => [
                'department_name' => ['label' => 'ชื่อแผนก', 'type' => 'text', 'required' => true],
                'department_code' => ['label' => 'รหัสแผนก', 'type' => 'text', 'required' => false],
            ],
            'identifier_columns' => ['department_name', 'department_code'],
        ],
        'time_logs' => [
            'label' => 'ข้อมูลลงเวลาเวร',
            'description' => 'จัดการรายการลงเวลาเวรโดยยังคงกติกาเวลาเวร การชนเวลา และการรีเซ็ตสถานะอนุมัติ',
            'primary_key' => 'id',
            'primary_key_qualified' => 't.id',
            'browse_columns' => ['id', 'work_date', 'fullname', 'position_name', 'department_name', 'time_in', 'time_out', 'work_hours', 'checked_at'],
            'searchable_columns' => ['u.fullname', 'u.position_name', 'd.department_name', 'COALESCE(t.note, \'\')'],
            'editable_columns' => ['department_id', 'work_date', 'time_in', 'time_out', 'note'],
            'createable_columns' => [],
            'readonly_columns' => ['id', 'user_id', 'work_hours', 'checked_by', 'checked_at', 'signature', 'created_at', 'updated_at'],
            'delete_allowed' => true,
            'create_allowed' => false,
            'edit_allowed' => true,
            'default_order' => 't.work_date DESC, t.id DESC',
            'from_sql' => 'time_logs t LEFT JOIN users u ON t.user_id = u.id LEFT JOIN departments d ON t.department_id = d.id LEFT JOIN users c ON t.checked_by = c.id',
            'select_sql' => 't.*, u.fullname, u.position_name, d.department_name, c.fullname AS checker_name',
            'field_meta' => [
                'department_id' => ['label' => 'แผนก', 'type' => 'select', 'required' => true, 'options_callback' => 'app_db_admin_department_options'],
                'work_date' => ['label' => 'วันที่ปฏิบัติงาน', 'type' => 'date', 'required' => true],
                'time_in' => ['label' => 'เวลาเข้า', 'type' => 'time', 'required' => true],
                'time_out' => ['label' => 'เวลาออก', 'type' => 'time', 'required' => true],
                'note' => ['label' => 'หมายเหตุ', 'type' => 'textarea', 'required' => false],
            ],
            'identifier_columns' => ['id', 'fullname', 'work_date'],
        ],
        'time_log_audit_trails' => [
            'label' => 'บันทึกการเปลี่ยนแปลงเวลาเวร',
            'description' => 'ใช้ติดตามประวัติการอนุมัติและการแก้ไขรายการลงเวลาเวรย้อนหลัง',
            'primary_key' => 'id',
            'primary_key_qualified' => 'time_log_audit_trails.id',
            'browse_columns' => ['id', 'time_log_id', 'action_type', 'actor_name_snapshot', 'created_at', 'note'],
            'searchable_columns' => ['action_type', 'actor_name_snapshot', 'COALESCE(note, \'\')'],
            'editable_columns' => [],
            'createable_columns' => [],
            'readonly_columns' => ['*'],
            'delete_allowed' => false,
            'create_allowed' => false,
            'edit_allowed' => false,
            'default_order' => 'id DESC',
            'from_sql' => 'time_log_audit_trails',
            'select_sql' => '*',
            'field_meta' => [],
            'identifier_columns' => ['id', 'action_type'],
        ],
        'user_permission_audit_trails' => [
            'label' => 'บันทึกการเปลี่ยนสิทธิ์ผู้ใช้',
            'description' => 'ใช้ติดตามการเปลี่ยนบทบาทและสิทธิ์ของผู้ใช้ย้อนหลัง',
            'primary_key' => 'id',
            'primary_key_qualified' => 'user_permission_audit_trails.id',
            'browse_columns' => ['id', 'target_user_id', 'action_type', 'actor_name_snapshot', 'created_at', 'note'],
            'searchable_columns' => ['action_type', 'actor_name_snapshot', 'COALESCE(note, \'\')'],
            'editable_columns' => [],
            'createable_columns' => [],
            'readonly_columns' => ['*'],
            'delete_allowed' => false,
            'create_allowed' => false,
            'edit_allowed' => false,
            'default_order' => 'id DESC',
            'from_sql' => 'user_permission_audit_trails',
            'select_sql' => '*',
            'field_meta' => [],
            'identifier_columns' => ['id', 'action_type'],
        ],
        'db_admin_audit_logs' => [
            'label' => 'บันทึกการเปลี่ยนแปลงข้อมูล',
            'description' => 'บันทึกทุกการสร้าง แก้ไข และลบข้อมูลจากโมดูลจัดการระบบหลังบ้าน',
            'primary_key' => 'id',
            'primary_key_qualified' => 'db_admin_audit_logs.id',
            'browse_columns' => ['id', 'table_name', 'row_primary_key', 'action_type', 'actor_name_snapshot', 'created_at', 'note'],
            'searchable_columns' => ['table_name', 'action_type', 'actor_name_snapshot', 'COALESCE(note, \'\')'],
            'editable_columns' => [],
            'createable_columns' => [],
            'readonly_columns' => ['*'],
            'delete_allowed' => false,
            'create_allowed' => false,
            'edit_allowed' => false,
            'default_order' => 'id DESC',
            'from_sql' => 'db_admin_audit_logs',
            'select_sql' => '*',
            'field_meta' => [],
            'identifier_columns' => ['id', 'table_name', 'action_type'],
        ],
    ];
}

function app_db_admin_role_options(): array
{
    $options = [];
    foreach (app_role_labels() as $value => $label) {
        $options[] = ['value' => $value, 'label' => $label];
    }
    return $options;
}

function app_db_admin_department_options(PDO $conn): array
{
    $rows = app_fetch_departments($conn);
    return array_map(static fn($row) => ['value' => (string) $row['id'], 'label' => $row['department_name']], $rows);
}

function app_db_admin_field_options(PDO $conn, array $meta): array
{
    $callback = $meta['options_callback'] ?? null;
    if (!is_string($callback) || !function_exists($callback)) {
        return [];
    }
    if ($callback === 'app_db_admin_role_options') {
        return $callback();
    }
    return $callback($conn);
}

function app_db_admin_get_table_config(string $table): ?array
{
    $tables = app_db_admin_tables();
    return $tables[$table] ?? null;
}

function app_db_admin_require_table_allowed(string $table): array
{
    $config = app_db_admin_get_table_config($table);
    if (!$config) {
        http_response_code(404);
        exit('ไม่พบตารางที่อนุญาตให้จัดการ');
    }
    return $config;
}

function app_db_admin_write_audit_log(PDO $conn, string $tableName, string $rowPrimaryKey, string $actionType, ?array $oldValues, ?array $newValues, int $actorUserId, string $actorName, ?string $note = null, ?array $requestContext = null): void
{
    $stmt = $conn->prepare("INSERT INTO db_admin_audit_logs (table_name, row_primary_key, action_type, old_values_json, new_values_json, actor_user_id, actor_name_snapshot, note, request_context) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([
        $tableName,
        $rowPrimaryKey,
        $actionType,
        $oldValues !== null ? json_encode($oldValues, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
        $newValues !== null ? json_encode($newValues, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
        $actorUserId,
        $actorName,
        $note,
        $requestContext !== null ? json_encode($requestContext, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
    ]);
}

function app_db_admin_query_string(array $filters, array $overrides = []): string
{
    $query = array_merge([
        'table' => $filters['table'] ?? '',
        'q' => $filters['q'] ?? '',
        'page' => $filters['page'] ?? 1,
        'per_page' => $filters['per_page'] ?? 20,
        'sort' => $filters['sort'] ?? '',
        'dir' => $filters['dir'] ?? '',
    ], $overrides);
    return http_build_query(array_filter($query, static fn($value) => $value !== '' && $value !== null));
}

function app_db_admin_build_filters(string $table, array $input, array $config): array
{
    $q = trim((string) ($input['q'] ?? ''));
    $page = max(1, (int) ($input['page'] ?? 1));
    $perPage = app_parse_table_page_size($input, 20);
    $sort = trim((string) ($input['sort'] ?? ''));
    $dir = strtolower(trim((string) ($input['dir'] ?? 'desc')));
    $dir = $dir === 'asc' ? 'ASC' : 'DESC';
    $allowedSorts = $config['browse_columns'];
    if (!in_array($sort, $allowedSorts, true)) {
        $sort = '';
    }
    return ['table' => $table, 'q' => $q, 'page' => $page, 'per_page' => $perPage, 'sort' => $sort, 'dir' => $dir];
}

function app_db_admin_visible_browse_columns(array $config): array
{
    $primaryKey = (string) ($config['primary_key'] ?? 'id');

    return array_values(array_filter(
        $config['browse_columns'] ?? [],
        static function ($column) use ($primaryKey): bool {
            if (!is_string($column)) {
                return false;
            }

            if ($column === $primaryKey || $column === 'row_primary_key') {
                return false;
            }

            return !preg_match('/(^id$|_id$)/', $column);
        }
    ));
}

function app_db_admin_where_sql(array $config, string $search): array
{
    $where = ['1 = 1'];
    $params = [];
    if ($search !== '' && !empty($config['searchable_columns'])) {
        $likes = [];
        foreach ($config['searchable_columns'] as $column) {
            $likes[] = $column . ' LIKE ?';
            $params[] = '%' . $search . '%';
        }
        $where[] = '(' . implode(' OR ', $likes) . ')';
    }
    return ['sql' => implode(' AND ', $where), 'params' => $params];
}

function app_db_admin_count_rows(PDO $conn, array $config, array $filters): int
{
    $where = app_db_admin_where_sql($config, $filters['q']);
    $stmt = $conn->prepare('SELECT COUNT(*) FROM ' . $config['from_sql'] . ' WHERE ' . $where['sql']);
    $stmt->execute($where['params']);
    return (int) $stmt->fetchColumn();
}

function app_db_admin_fetch_rows(PDO $conn, array $config, array $filters, int $limit, int $offset): array
{
    $where = app_db_admin_where_sql($config, $filters['q']);
    $orderBy = $config['default_order'];
    if ($filters['sort'] !== '') {
        $orderBy = $filters['sort'] . ' ' . $filters['dir'];
    }
    $sql = 'SELECT ' . $config['select_sql'] . ' FROM ' . $config['from_sql'] . ' WHERE ' . $where['sql'] . ' ORDER BY ' . $orderBy . ' LIMIT ? OFFSET ?';
    $stmt = $conn->prepare($sql);
    $index = 1;
    foreach ($where['params'] as $param) {
        $stmt->bindValue($index++, $param, PDO::PARAM_STR);
    }
    $stmt->bindValue($index++, $limit, PDO::PARAM_INT);
    $stmt->bindValue($index, $offset, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function app_db_admin_fetch_all_rows(PDO $conn, array $config, array $filters): array
{
    $where = app_db_admin_where_sql($config, $filters['q'] ?? '');
    $orderBy = $config['default_order'];
    if (!empty($filters['sort'])) {
        $orderBy = $filters['sort'] . ' ' . ($filters['dir'] ?? 'DESC');
    }

    $stmt = $conn->prepare('SELECT ' . $config['select_sql'] . ' FROM ' . $config['from_sql'] . ' WHERE ' . $where['sql'] . ' ORDER BY ' . $orderBy);
    $stmt->execute($where['params']);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function app_db_admin_fetch_row(PDO $conn, array $config, int $id): ?array
{
    $primaryKey = $config['primary_key_qualified'] ?? $config['primary_key'];
    $stmt = $conn->prepare('SELECT ' . $config['select_sql'] . ' FROM ' . $config['from_sql'] . ' WHERE ' . $primaryKey . ' = ? LIMIT 1');
    $stmt->execute([$id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function app_db_admin_format_value(string $column, mixed $value): string
{
    if ($value === null || $value === '') {
        return '-';
    }
    if (in_array($column, ['work_date'], true)) {
        return app_format_thai_date((string) $value);
    }
    if (in_array($column, ['created_at', 'updated_at', 'checked_at'], true)) {
        return app_format_thai_datetime((string) $value);
    }
    if (in_array($column, ['time_in', 'time_out'], true)) {
        return date('H:i', strtotime((string) $value));
    }
    if (is_bool($value) || $value === 0 || $value === 1 || $value === '0' || $value === '1') {
        if (str_starts_with($column, 'can_') || $column === 'is_active') {
            return (int) $value === 1 ? 'ใช่' : 'ไม่';
        }
    }
    return (string) $value;
}

function app_db_admin_validate_payload(PDO $conn, array $config, array $input, bool $isCreate = false): array
{
    $fields = $isCreate ? $config['createable_columns'] : $config['editable_columns'];
    $meta = $config['field_meta'];
    $payload = [];
    $errors = [];

    foreach ($fields as $field) {
        $definition = $meta[$field] ?? ['type' => 'text', 'required' => false];
        $type = $definition['type'];
        if ($type === 'boolean') {
            $payload[$field] = isset($input[$field]) ? 1 : 0;
            continue;
        }
        $value = trim((string) ($input[$field] ?? ''));
        if (($definition['required'] ?? false) && $value === '') {
            $errors[] = 'กรุณากรอก ' . ($definition['label'] ?? $field);
            continue;
        }
        if ($value === '') {
            $payload[$field] = null;
            continue;
        }
        if ($type === 'select') {
            $payload[$field] = is_numeric($value) ? (int) $value : $value;
        } elseif ($type === 'date') {
            $timestamp = strtotime($value);
            if ($timestamp === false) {
                $errors[] = 'รูปแบบวันที่ของ ' . ($definition['label'] ?? $field) . ' ไม่ถูกต้อง';
                continue;
            }
            $payload[$field] = date('Y-m-d', $timestamp);
        } elseif ($type === 'time') {
            if (!preg_match('/^\d{2}:\d{2}$/', $value)) {
                $errors[] = 'รูปแบบเวลาของ ' . ($definition['label'] ?? $field) . ' ไม่ถูกต้อง';
                continue;
            }
            $payload[$field] = $value;
        } else {
            $payload[$field] = $value;
        }
    }

    return ['payload' => $payload, 'errors' => $errors];
}

function app_db_admin_protect_user_update(PDO $conn, int $targetUserId, array &$payload): array
{
    $errors = [];
    $currentUserId = (int) ($_SESSION['id'] ?? 0);
    $existing = app_db_admin_fetch_row($conn, app_db_admin_get_table_config('users'), $targetUserId);
    if (!$existing) {
        $errors[] = 'ไม่พบผู้ใช้ที่ต้องการแก้ไข';
        return $errors;
    }
    if ((int) $existing['id'] === $currentUserId && (($payload['is_active'] ?? 1) === 0)) {
        $errors[] = 'ไม่สามารถปิดการใช้งานบัญชีผู้ดูแลระบบที่กำลังใช้งานอยู่';
    }
    $newRole = $payload['role'] ?? ($existing['role'] ?? 'staff');
    if (($existing['role'] ?? '') === 'admin' && $newRole !== 'admin') {
        $adminCount = (int) $conn->query("SELECT COUNT(*) FROM users WHERE role = 'admin' AND is_active = 1")->fetchColumn();
        if ($adminCount <= 1) {
            $errors[] = 'ไม่สามารถลดสิทธิ์ของผู้ดูแลระบบคนสุดท้ายได้';
        }
    }
    return $errors;
}

function app_db_admin_before_time_log_update(PDO $conn, array $existingRow, array &$payload): array
{
    $errors = [];
    $workDate = $payload['work_date'] ?? $existingRow['work_date'];
    $timeIn = $payload['time_in'] ?? (!empty($existingRow['time_in']) ? date('H:i', strtotime($existingRow['time_in'])) : '');
    $timeOut = $payload['time_out'] ?? (!empty($existingRow['time_out']) ? date('H:i', strtotime($existingRow['time_out'])) : '');
    if ($workDate === null || $timeIn === '' || $timeOut === '') {
        $errors[] = 'กรุณาระบุวันที่ เวลาเข้า และเวลาออกให้ครบถ้วน';
        return $errors;
    }
    $fullTimeIn = $workDate . ' ' . $timeIn . ':00';
    $fullTimeOut = $workDate . ' ' . $timeOut . ':00';
    $tsIn = strtotime($fullTimeIn);
    $tsOut = strtotime($fullTimeOut);
    if ($tsIn === false || $tsOut === false) {
        $errors[] = 'รูปแบบวันเวลาของรายการลงเวลาเวรไม่ถูกต้อง';
        return $errors;
    }
    if ($tsOut < $tsIn) {
        $tsOut += 86400;
        $fullTimeOut = date('Y-m-d H:i:s', $tsOut);
    }
    $overlap = app_find_overlapping_time_log($conn, (int) $existingRow['user_id'], $fullTimeIn, $fullTimeOut, (int) $existingRow['id']);
    if ($overlap) {
        $errors[] = 'ช่วงเวลาเวรชนกับรายการเดิมของเจ้าหน้าที่คนเดียวกัน';
        return $errors;
    }
    if (app_time_log_is_locked($existingRow) && !app_can('can_edit_locked_time_logs')) {
        $errors[] = 'รายการนี้อนุมัติแล้วและถูกล็อก ไม่สามารถแก้ไขได้';
        return $errors;
    }
    $payload['time_in'] = date('Y-m-d H:i:s', $tsIn);
    $payload['time_out'] = $fullTimeOut;
    $payload['work_hours'] = number_format(($tsOut - $tsIn) / 3600, 2, '.', '');
    $payload['checked_by'] = null;
    $payload['checked_at'] = null;
    $payload['signature'] = null;
    return $errors;
}

function app_db_admin_insert_row(PDO $conn, string $table, array $config, array $payload, int $actorUserId, string $actorName): int
{
    $columns = array_keys($payload);
    $placeholders = implode(', ', array_fill(0, count($columns), '?'));
    $stmt = $conn->prepare('INSERT INTO ' . $table . ' (' . implode(', ', $columns) . ') VALUES (' . $placeholders . ')');
    $stmt->execute(array_values($payload));
    $newId = (int) $conn->lastInsertId();
    $newRow = app_db_admin_fetch_row($conn, $config, $newId);
    app_db_admin_write_audit_log($conn, $table, (string) $newId, 'create', null, $newRow, $actorUserId, $actorName, 'สร้างข้อมูลจากโมดูลจัดการระบบหลังบ้าน');
    return $newId;
}

function app_db_admin_update_row(PDO $conn, string $table, array $config, int $id, array $payload, int $actorUserId, string $actorName): void
{
    $existingRow = app_db_admin_fetch_row($conn, $config, $id);
    if (!$existingRow) {
        throw new RuntimeException('ไม่พบข้อมูลที่ต้องการแก้ไข');
    }
    if ($table === 'users') {
        $errors = app_db_admin_protect_user_update($conn, $id, $payload);
        if ($errors) {
            throw new RuntimeException(implode(' ', $errors));
        }
    }
    if ($table === 'time_logs') {
        $errors = app_db_admin_before_time_log_update($conn, $existingRow, $payload);
        if ($errors) {
            throw new RuntimeException(implode(' ', $errors));
        }
    }
    $sets = [];
    $values = [];
    foreach ($payload as $column => $value) {
        $sets[] = $column . ' = ?';
        $values[] = $value;
    }
    $values[] = $id;
    $stmt = $conn->prepare('UPDATE ' . $table . ' SET ' . implode(', ', $sets) . ' WHERE ' . $config['primary_key'] . ' = ?');
    $stmt->execute($values);
    $newRow = app_db_admin_fetch_row($conn, $config, $id);
    app_db_admin_write_audit_log($conn, $table, (string) $id, 'update', $existingRow, $newRow, $actorUserId, $actorName, 'แก้ไขข้อมูลจากโมดูลจัดการระบบหลังบ้าน');
    if ($table === 'time_logs') {
        app_insert_time_log_audit($conn, $id, 'admin_edit', $existingRow, $newRow, $actorUserId, $actorName, 'แก้ไขข้อมูลจากหน้าจัดการข้อมูลฐานข้อมูล และรีเซ็ตสถานะอนุมัติเดิม');
    }
    if ($table === 'users') {
        app_insert_user_permission_audit($conn, $id, 'db_admin_update', $existingRow, $newRow, $actorUserId, $actorName, 'แก้ไขข้อมูลผู้ใช้จากโมดูลจัดการข้อมูลฐานข้อมูล');
    }
}

function app_db_admin_delete_row(PDO $conn, string $table, array $config, int $id, int $actorUserId, string $actorName): void
{
    if (empty($config['delete_allowed'])) {
        throw new RuntimeException('ตารางนี้ไม่อนุญาตให้ลบข้อมูล');
    }
    $existingRow = app_db_admin_fetch_row($conn, $config, $id);
    if (!$existingRow) {
        throw new RuntimeException('ไม่พบข้อมูลที่ต้องการลบ');
    }
    if ($table === 'users') {
        throw new RuntimeException('ตารางผู้ใช้งานไม่อนุญาตให้ลบจากโมดูลนี้');
    }
    if ($table === 'departments') {
        $stmt = $conn->prepare('SELECT COUNT(*) FROM users WHERE department_id = ?');
        $stmt->execute([$id]);
        $userRefCount = (int) $stmt->fetchColumn();
        $stmt = $conn->prepare('SELECT COUNT(*) FROM time_logs WHERE department_id = ?');
        $stmt->execute([$id]);
        $timeLogRefCount = (int) $stmt->fetchColumn();
        if ($userRefCount > 0 || $timeLogRefCount > 0) {
            throw new RuntimeException('ไม่สามารถลบแผนกที่ยังมีผู้ใช้งานหรือข้อมูลลงเวลาเวรอ้างอิงอยู่');
        }
    }
    $stmt = $conn->prepare('DELETE FROM ' . $table . ' WHERE ' . $config['primary_key'] . ' = ?');
    $stmt->execute([$id]);
    if ($stmt->rowCount() !== 1) {
        throw new RuntimeException('ไม่สามารถลบข้อมูลได้ หรือข้อมูลถูกเปลี่ยนแปลงระหว่างดำเนินการ');
    }
    app_db_admin_write_audit_log($conn, $table, (string) $id, 'delete', $existingRow, null, $actorUserId, $actorName, 'ลบข้อมูลจากโมดูลจัดการระบบหลังบ้าน');
    if ($table === 'time_logs') {
        app_insert_time_log_audit($conn, $id, 'admin_delete', $existingRow, null, $actorUserId, $actorName, 'ลบรายการลงเวลาเวรจากโมดูลจัดการข้อมูลฐานข้อมูล');
    }
}

function app_db_admin_table_counts(PDO $conn): array
{
    $result = [];
    foreach (app_db_admin_tables() as $table => $config) {
        if (!app_table_exists($conn, $table)) {
            $result[$table] = 0;
            continue;
        }
        $stmt = $conn->query('SELECT COUNT(*) FROM ' . $table);
        $result[$table] = (int) $stmt->fetchColumn();
    }
    return $result;
}
