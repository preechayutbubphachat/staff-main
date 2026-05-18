<?php

function app_notification_event_matrix(): array
{
    return [
        'attendance_log_submitted' => [
            'event_key' => 'attendance_log_submitted',
            'business_description' => 'ผู้ใช้สร้างหรือส่งรายการลงเวลาเวรใหม่เข้าสู่ขั้นตอนรอตรวจสอบ',
            'recipients' => 'ผู้มีสิทธิ์ can_approve_logs ตามขอบเขตแผนกที่เข้าถึงได้',
            'excluded_roles' => 'staff ทั่วไป, finance ที่ไม่มี can_approve_logs, ผู้ใช้นอก reviewer scope',
            'trigger_source' => 'การบันทึกลงเวลาเวรใหม่จากหน้า time.php หรือ flow ที่ทำให้รายการกลายเป็น pending',
            'title_template' => 'มีรายการลงเวลาเวรรอตรวจสอบ',
            'message_template' => 'มีรายการลงเวลาเวรใหม่เข้าสู่คิวรอตรวจสอบ',
            'target_url_pattern' => 'approval_queue.php?status=pending',
            'target_entity_type' => 'approval_queue',
            'target_entity_id_behavior' => 'ไม่ผูกกับรายการเดี่ยว ใช้ target_entity_id เป็น null',
            'priority' => 'normal',
            'dedup_strategy' => 'ไม่สร้างรายแถวให้ reviewer แต่ถูกรวมใน reviewer_queue_pending_summary',
            'read_unread_notes' => 'คง unread จนกว่าจะเปิดอ่านหรือคิวกลับเป็นศูนย์',
            'notes' => 'ใช้เป็น event ต้นทางสำหรับ sync queue aggregate เท่านั้น',
        ],
        'attendance_log_approved' => [
            'event_key' => 'attendance_log_approved',
            'business_description' => 'รายการลงเวลาเวรของเจ้าของรายการได้รับการอนุมัติแล้ว',
            'recipients' => 'เจ้าของรายการเท่านั้น',
            'excluded_roles' => 'checker คนอื่น, staff คนอื่น, finance, ผู้ใช้ที่ไม่ใช่เจ้าของรายการ',
            'trigger_source' => 'การอนุมัติรายการจาก approval queue หรือ flow อนุมัติที่เทียบเท่า',
            'title_template' => 'รายการลงเวลาเวรได้รับการอนุมัติแล้ว',
            'message_template' => 'รายการลงเวลาเวรของคุณได้รับการอนุมัติแล้ว',
            'target_url_pattern' => 'time.php?highlight_log={time_log_id}',
            'target_entity_type' => 'time_log',
            'target_entity_id_behavior' => 'ใช้ time_log_id ของรายการที่เพิ่งอนุมัติ',
            'priority' => 'normal',
            'dedup_strategy' => 'หนึ่งรายการที่อนุมัติ = หนึ่ง notification ต่อเจ้าของรายการ',
            'read_unread_notes' => 'เริ่มเป็น unread และ mark read เมื่อผู้ใช้กดเปิดหรือ mark all',
            'notes' => 'ไม่ยิงให้ reviewer เพื่อลด noise',
        ],
        'attendance_log_rejected_or_returned' => [
            'event_key' => 'attendance_log_rejected_or_returned',
            'business_description' => 'รายการลงเวลาเวรถูกส่งกลับหรือไม่ผ่านการอนุมัติและต้องการการแก้ไขจากเจ้าของรายการ',
            'recipients' => 'เจ้าของรายการเท่านั้น',
            'excluded_roles' => 'reviewer คนอื่น, staff คนอื่น, finance, ผู้ใช้อื่นที่ไม่เกี่ยวข้อง',
            'trigger_source' => 'workflow ส่งกลับ/ปฏิเสธรายการใน approval queue หรือ flow อนาคตที่มีเหตุผลการแก้ไข',
            'title_template' => 'รายการลงเวลาเวรต้องแก้ไขเพิ่มเติม',
            'message_template' => 'รายการลงเวลาเวรของคุณถูกส่งกลับเพื่อแก้ไข กรุณาตรวจสอบรายละเอียด',
            'target_url_pattern' => 'time.php?highlight_log={time_log_id}',
            'target_entity_type' => 'time_log',
            'target_entity_id_behavior' => 'ใช้ time_log_id ของรายการที่ถูกส่งกลับ',
            'priority' => 'high',
            'dedup_strategy' => 'หนึ่งเหตุการณ์ส่งกลับ = หนึ่ง notification',
            'read_unread_notes' => 'ควรเด่นกว่า notification ปกติ เพราะมีงานที่ผู้ใช้ต้องทำต่อ',
            'notes' => 'ยังเป็น future-ready event ถ้า workflow ส่งกลับถูกเปิดใช้',
        ],
        'approved_log_reopened_for_review' => [
            'event_key' => 'approved_log_reopened_for_review',
            'business_description' => 'รายการที่เคยอนุมัติแล้วถูกแก้ไขจนสถานะกลับมา pending และต้องตรวจสอบอีกครั้ง',
            'recipients' => 'ผู้มีสิทธิ์ can_approve_logs ตามขอบเขตแผนก',
            'excluded_roles' => 'staff ทั่วไป, finance ที่ไม่มีสิทธิ์อนุมัติ',
            'trigger_source' => 'การแก้ไขรายการที่ reset checked_at หรือ checked_by กลับเป็น null',
            'title_template' => 'มีรายการลงเวลาเวรกลับเข้าสู่คิวตรวจสอบ',
            'message_template' => 'มีรายการที่แก้ไขหลังอนุมัติและต้องตรวจสอบอีกครั้ง',
            'target_url_pattern' => 'approval_queue.php?status=pending',
            'target_entity_type' => 'approval_queue',
            'target_entity_id_behavior' => 'ไม่ผูก target_entity_id รายแถว ใช้ aggregate queue',
            'priority' => 'normal',
            'dedup_strategy' => 'รวมเข้ากับ reviewer_queue_pending_summary เพื่อไม่ให้ reviewer ถูกแจ้งซ้ำหลายรายการ',
            'read_unread_notes' => 'อัปเดต count ของ queue summary เดิมแทนการสร้างแถวใหม่ทุกรายการ',
            'notes' => 'สำหรับเจ้าของรายการ ไม่แจ้งซ้ำถ้าไม่มีความหมายเชิงปฏิบัติการเพิ่ม',
        ],
        'reviewer_queue_pending_summary' => [
            'event_key' => 'reviewer_queue_pending_summary',
            'business_description' => 'สรุปจำนวนรายการลงเวลาเวรที่รอตรวจสอบใน scope ของ reviewer',
            'recipients' => 'checker, admin, หรือผู้ใช้ใดก็ตามที่มี can_approve_logs',
            'excluded_roles' => 'staff, finance ที่ไม่มีสิทธิ์อนุมัติ',
            'trigger_source' => 'sync queue notification หลัง event สำคัญและเมื่อ UI ต้องแสดง badge ล่าสุด',
            'title_template' => 'มีคิวลงเวลาเวรรอตรวจสอบ',
            'message_template' => 'มีรายการลงเวลาเวรรอตรวจสอบ {pending_count} รายการ',
            'target_url_pattern' => 'approval_queue.php?status=pending',
            'target_entity_type' => 'approval_queue',
            'target_entity_id_behavior' => 'ไม่ใช้ target_entity_id เพราะเป็น notification ระดับคิว',
            'priority' => 'normal',
            'dedup_strategy' => 'single rolling summary ต่อ reviewer; update unread ตัวล่าสุดเมื่อ count เปลี่ยน และ mark read อัตโนมัติเมื่อ count = 0',
            'read_unread_notes' => 'ถ้าคิวยังไม่ว่างและ count เปลี่ยน ให้อัปเดตข้อความเดิมโดยคง unread ไว้',
            'notes' => 'เป็น notification แบบ aggregate หลักสำหรับ reviewer side',
        ],
        'admin_permission_changed_user' => [
            'event_key' => 'admin_permission_changed_user',
            'business_description' => 'ผู้ดูแลระบบเปลี่ยน role หรือสิทธิ์การใช้งานของผู้ใช้',
            'recipients' => 'ผู้ใช้ที่ถูกเปลี่ยนสิทธิ์เท่านั้น',
            'excluded_roles' => 'ผู้ใช้อื่นทั้งหมด รวมถึง admin actor หาก audit เพียงพอแล้ว',
            'trigger_source' => 'หน้าจัดการผู้ใช้งานหรือหน้าจัดการสิทธิ์ที่มีการเปลี่ยน role หรือ permission จริง',
            'title_template' => 'สิทธิ์การใช้งานของคุณมีการเปลี่ยนแปลง',
            'message_template' => 'ผู้ดูแลระบบได้ปรับสิทธิ์การใช้งานของคุณ กรุณาตรวจสอบเมนูที่ใช้งานได้',
            'target_url_pattern' => 'profile.php',
            'target_entity_type' => 'user',
            'target_entity_id_behavior' => 'ใช้ user_id ของผู้ใช้ที่ได้รับผลกระทบ',
            'priority' => 'high',
            'dedup_strategy' => 'หนึ่ง action เปลี่ยนสิทธิ์ = หนึ่ง notification',
            'read_unread_notes' => 'ควรเป็น unread จนผู้ใช้เข้ามาอ่าน เพราะอาจกระทบการใช้งานทันที',
            'notes' => 'ไม่ควรยิงเมื่อบันทึกแบบ no-op หรือไม่มีสิทธิ์เปลี่ยนจริง',
        ],
        'admin_updated_user_profile' => [
            'event_key' => 'admin_updated_user_profile',
            'business_description' => 'ผู้ดูแลระบบปรับข้อมูลบัญชีหรือข้อมูลการทำงานสำคัญของผู้ใช้',
            'recipients' => 'ผู้ใช้ที่ถูกแก้ไขข้อมูล',
            'excluded_roles' => 'ผู้ใช้อื่นทั้งหมด',
            'trigger_source' => 'หน้าจัดการผู้ใช้งานของ admin เมื่อแก้ username, department, position, avatar, signature หรือข้อมูลสำคัญอื่น',
            'title_template' => 'ข้อมูลผู้ใช้งานของคุณมีการเปลี่ยนแปลง',
            'message_template' => 'ข้อมูลบัญชีหรือข้อมูลการทำงานของคุณถูกปรับปรุงโดยผู้ดูแลระบบ',
            'target_url_pattern' => 'profile.php',
            'target_entity_type' => 'user',
            'target_entity_id_behavior' => 'ใช้ user_id ของผู้ใช้ที่ถูกแก้ไข',
            'priority' => 'normal',
            'dedup_strategy' => 'หนึ่ง meaningful admin edit = หนึ่ง notification',
            'read_unread_notes' => 'ไม่ควรสร้างจากการแก้ไข field ภายในที่ผู้ใช้ไม่จำเป็นต้องรับรู้',
            'notes' => 'เหมาะกับการเปลี่ยนข้อมูลที่ส่งผลต่อการทำงานจริง เช่น แผนก ตำแหน่ง หรือ username',
        ],
        'system_announcement_scoped' => [
            'event_key' => 'system_announcement_scoped',
            'business_description' => 'ประกาศระบบหรือประกาศจากผู้ดูแลที่เจาะจงกลุ่มเป้าหมายตาม role, permission หรือ scope',
            'recipients' => 'ผู้ใช้ที่อยู่ในกลุ่มเป้าหมายของประกาศเท่านั้น',
            'excluded_roles' => 'ผู้ใช้นอกกลุ่มเป้าหมาย',
            'trigger_source' => 'future admin announcement flow หรือ scheduled operational notice',
            'title_template' => 'ประกาศจากระบบ',
            'message_template' => '{announcement_text}',
            'target_url_pattern' => 'notifications.php',
            'target_entity_type' => 'announcement',
            'target_entity_id_behavior' => 'ใช้ announcement_id ต่อผู้ใช้แต่ละคนเพื่อ dedupe ได้',
            'priority' => 'low',
            'dedup_strategy' => 'หนึ่ง announcement id ต่อหนึ่งผู้ใช้เป้าหมาย',
            'read_unread_notes' => 'ไม่ควรถูกสร้างซ้ำทุก page load หรือทุก polling รอบ',
            'notes' => 'เหมาะกับประกาศ maintenance หรือกติกาใหม่ที่มีผลกับกลุ่มเฉพาะ',
        ],
        'export_or_report_job_completed' => [
            'event_key' => 'export_or_report_job_completed',
            'business_description' => 'งานสร้างรายงานแบบ async เสร็จและพร้อมให้ผู้ใช้ดาวน์โหลด',
            'recipients' => 'ผู้ใช้ที่เป็นผู้ร้องขอรายงานเท่านั้น',
            'excluded_roles' => 'ผู้ใช้อื่นทั้งหมด',
            'trigger_source' => 'future async report หรือ export job worker',
            'title_template' => 'รายงานของคุณพร้อมแล้ว',
            'message_template' => 'ระบบจัดเตรียมรายงานเรียบร้อยแล้ว กรุณาเข้าดาวน์โหลด',
            'target_url_pattern' => 'my_reports.php',
            'target_entity_type' => 'report_job',
            'target_entity_id_behavior' => 'ใช้ report_job_id หรือ export_job_id เมื่อระบบ async พร้อมใช้งาน',
            'priority' => 'normal',
            'dedup_strategy' => 'หนึ่ง report job ต่อหนึ่ง notification',
            'read_unread_notes' => 'เหมาะสำหรับงานที่ใช้เวลานาน ไม่จำเป็นถ้ายัง export แบบ sync ทันที',
            'notes' => 'เป็น future event เท่านั้นในรอบปัจจุบัน',
        ],
    ];
}

function app_notification_rule(string $eventKey): array
{
    $matrix = app_notification_event_matrix();
    return $matrix[$eventKey] ?? [];
}

function app_notification_render_template(string $template, array $vars = []): string
{
    if ($template === '' || !$vars) {
        return $template;
    }

    $replacements = [];
    foreach ($vars as $key => $value) {
        $replacements['{' . $key . '}'] = (string) $value;
    }

    return strtr($template, $replacements);
}

function app_notification_payload_from_rule(string $eventKey, array $vars = []): array
{
    $rule = app_notification_rule($eventKey);
    if (!$rule) {
        return [];
    }

    return [
        'type' => $eventKey,
        'title' => app_notification_render_template((string) ($rule['title_template'] ?? ''), $vars),
        'message' => app_notification_render_template((string) ($rule['message_template'] ?? ''), $vars),
        'target_url' => app_notification_render_template((string) ($rule['target_url_pattern'] ?? ''), $vars),
        'target_entity_type' => $rule['target_entity_type'] ?? null,
        'priority' => $rule['priority'] ?? 'normal',
    ];
}

function app_notifications_available(PDO $conn): bool
{
    return app_table_exists($conn, 'notifications');
}

function app_notification_encode_metadata(?array $metadata): ?string
{
    if ($metadata === null) {
        return null;
    }

    return json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

function app_notification_decode_metadata($json): array
{
    if (!is_string($json) || trim($json) === '') {
        return [];
    }

    $decoded = json_decode($json, true);
    return is_array($decoded) ? $decoded : [];
}

function app_create_notification(PDO $conn, array $data): int
{
    if (!app_notifications_available($conn)) {
        return 0;
    }

    $stmt = $conn->prepare("
        INSERT INTO notifications (
            user_id,
            type,
            title,
            message,
            target_url,
            target_entity_type,
            target_entity_id,
            is_read,
            read_at,
            metadata_json,
            source_type,
            actor_user_id,
            priority
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    $stmt->execute([
        (int) ($data['user_id'] ?? 0),
        (string) ($data['type'] ?? 'system_notice'),
        (string) ($data['title'] ?? ''),
        (string) ($data['message'] ?? ''),
        (string) ($data['target_url'] ?? ''),
        array_key_exists('target_entity_type', $data) && $data['target_entity_type'] !== null ? (string) $data['target_entity_type'] : null,
        array_key_exists('target_entity_id', $data) && $data['target_entity_id'] !== null ? (int) $data['target_entity_id'] : null,
        !empty($data['is_read']) ? 1 : 0,
        !empty($data['is_read']) ? date('Y-m-d H:i:s') : null,
        app_notification_encode_metadata($data['metadata'] ?? null),
        (string) ($data['source_type'] ?? 'system'),
        isset($data['actor_user_id']) ? (int) $data['actor_user_id'] : null,
        (string) ($data['priority'] ?? 'normal'),
    ]);

    return (int) $conn->lastInsertId();
}

function app_find_unread_notification_for_entity(
    PDO $conn,
    int $userId,
    string $type,
    ?string $entityType = null,
    ?int $entityId = null
): ?array {
    if (!app_notifications_available($conn) || $userId <= 0 || $type === '') {
        return null;
    }

    $sql = "
        SELECT *
        FROM notifications
        WHERE user_id = ?
          AND type = ?
          AND is_read = 0
    ";
    $params = [$userId, $type];

    if ($entityType !== null) {
        $sql .= " AND target_entity_type = ?";
        $params[] = $entityType;
    }

    if ($entityId !== null) {
        $sql .= " AND target_entity_id = ?";
        $params[] = $entityId;
    }

    $sql .= " ORDER BY id DESC LIMIT 1";
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return $row ?: null;
}

function app_find_notification_for_entity(
    PDO $conn,
    int $userId,
    string $type,
    ?string $entityType = null,
    ?int $entityId = null
): ?array {
    if (!app_notifications_available($conn) || $userId <= 0 || $type === '') {
        return null;
    }

    $sql = "
        SELECT *
        FROM notifications
        WHERE user_id = ?
          AND type = ?
    ";
    $params = [$userId, $type];

    if ($entityType !== null) {
        $sql .= " AND target_entity_type = ?";
        $params[] = $entityType;
    }

    if ($entityId !== null) {
        $sql .= " AND target_entity_id = ?";
        $params[] = $entityId;
    }

    $sql .= " ORDER BY id DESC LIMIT 1";
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return $row ?: null;
}

function app_update_notification_row(PDO $conn, int $notificationId, array $data): void
{
    if (!app_notifications_available($conn) || $notificationId <= 0) {
        return;
    }

    $stmt = $conn->prepare("
        UPDATE notifications
        SET title = ?,
            message = ?,
            target_url = ?,
            metadata_json = ?,
            actor_user_id = ?,
            priority = ?,
            is_read = 0,
            read_at = NULL
        WHERE id = ?
    ");
    $stmt->execute([
        (string) ($data['title'] ?? ''),
        (string) ($data['message'] ?? ''),
        (string) ($data['target_url'] ?? ''),
        app_notification_encode_metadata($data['metadata'] ?? null),
        isset($data['actor_user_id']) ? (int) $data['actor_user_id'] : null,
        (string) ($data['priority'] ?? 'normal'),
        $notificationId,
    ]);
}

function app_notification_mark_queue_read(PDO $conn, int $userId): void
{
    if (!app_notifications_available($conn)) {
        return;
    }

    $stmt = $conn->prepare("
        UPDATE notifications
        SET is_read = 1, read_at = COALESCE(read_at, NOW())
        WHERE user_id = ? AND type = 'approval_queue_pending' AND is_read = 0
    ");
    $stmt->execute([$userId]);
}

function app_notification_user_scope(PDO $conn, array $user): array
{
    $permissions = app_user_permissions($user);

    if (!empty($permissions['can_view_all_staff']) || !empty($permissions['can_manage_user_permissions']) || !empty($permissions['can_manage_database'])) {
        $ids = $conn->query('SELECT id FROM departments ORDER BY id')->fetchAll(PDO::FETCH_COLUMN);
        return array_map('intval', $ids ?: []);
    }

    $departmentId = (int) ($user['department_id'] ?? 0);
    return $departmentId > 0 ? [$departmentId] : [];
}

function app_notification_pending_count_for_scope(PDO $conn, array $departmentIds): int
{
    $snapshot = app_notification_pending_snapshot_for_scope($conn, $departmentIds);
    return (int) ($snapshot['pending_count'] ?? 0);
}

function app_notification_pending_snapshot_for_scope(PDO $conn, array $departmentIds): array
{
    if (!$departmentIds) {
        return [
            'pending_count' => 0,
            'latest_pending_id' => 0,
            'pending_id_checksum' => 0,
        ];
    }

    $placeholders = implode(', ', array_fill(0, count($departmentIds), '?'));
    $stmt = $conn->prepare("
        SELECT
            COUNT(*) AS pending_count,
            COALESCE(MAX(id), 0) AS latest_pending_id,
            COALESCE(SUM(id), 0) AS pending_id_checksum
        FROM time_logs
        WHERE " . app_time_log_pending_condition('') . "
          AND department_id IN ({$placeholders})
    ");
    $stmt->execute(array_map('intval', $departmentIds));
    $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

    return [
        'pending_count' => (int) ($row['pending_count'] ?? 0),
        'latest_pending_id' => (int) ($row['latest_pending_id'] ?? 0),
        'pending_id_checksum' => (int) ($row['pending_id_checksum'] ?? 0),
    ];
}

function app_notify_reviewer_queue_if_needed(PDO $conn): void
{
    if (!app_notifications_available($conn) || !app_column_exists($conn, 'users', 'can_approve_logs')) {
        return;
    }

    $reviewerStmt = $conn->query("
        SELECT *
        FROM users
        WHERE is_active = 1 AND can_approve_logs = 1
        ORDER BY fullname
    ");
    $reviewers = $reviewerStmt->fetchAll(PDO::FETCH_ASSOC);

    $rulePayload = app_notification_payload_from_rule('reviewer_queue_pending_summary');

    foreach ($reviewers as $reviewer) {
        $reviewerId = (int) ($reviewer['id'] ?? 0);
        if ($reviewerId <= 0) {
            continue;
        }

        $snapshot = app_notification_pending_snapshot_for_scope(
            $conn,
            app_notification_user_scope($conn, $reviewer)
        );
        $pendingCount = (int) ($snapshot['pending_count'] ?? 0);

        $existingStmt = $conn->prepare("
            SELECT id, is_read, metadata_json, target_url
            FROM notifications
            WHERE user_id = ? AND type = 'approval_queue_pending'
            ORDER BY id DESC
            LIMIT 1
        ");
        $existingStmt->execute([$reviewerId]);
        $existing = $existingStmt->fetch(PDO::FETCH_ASSOC) ?: null;
        $existingMeta = app_notification_decode_metadata($existing['metadata_json'] ?? null);
        $existingCount = (int) ($existingMeta['pending_count'] ?? 0);
        $existingLatestId = (int) ($existingMeta['latest_pending_id'] ?? 0);
        $existingChecksum = (int) ($existingMeta['pending_id_checksum'] ?? 0);
        $latestPendingId = (int) ($snapshot['latest_pending_id'] ?? 0);
        $pendingChecksum = (int) ($snapshot['pending_id_checksum'] ?? 0);
        $snapshotChanged = $existingCount !== $pendingCount
            || $existingLatestId !== $latestPendingId
            || $existingChecksum !== $pendingChecksum;

        if ($pendingCount <= 0) {
            app_notification_mark_queue_read($conn, $reviewerId);
            continue;
        }

        $title = $rulePayload['title'] ?? 'มีคิวลงเวลาเวรรอตรวจสอบ';
        $message = app_notification_render_template(
            (string) (($rulePayload['message'] ?? 'มีรายการลงเวลาเวรรอตรวจสอบ {pending_count} รายการ')),
            ['pending_count' => $pendingCount]
        );
        $targetUrl = $rulePayload['target_url'] ?? 'approval_queue.php';
        $metadataJson = app_notification_encode_metadata([
            'pending_count' => $pendingCount,
            'latest_pending_id' => $latestPendingId,
            'pending_id_checksum' => $pendingChecksum,
            'event_key' => 'reviewer_queue_pending_summary',
        ]);
        $targetUrlChanged = (string) ($existing['target_url'] ?? '') !== $targetUrl;

        if (!$existing) {
            app_create_notification($conn, [
                'user_id' => $reviewerId,
                'type' => 'approval_queue_pending',
                'title' => $title,
                'message' => $message,
                'target_url' => $targetUrl,
                'target_entity_type' => $rulePayload['target_entity_type'] ?? 'approval_queue',
                'target_entity_id' => null,
                'metadata' => [
                    'pending_count' => $pendingCount,
                    'latest_pending_id' => $latestPendingId,
                    'pending_id_checksum' => $pendingChecksum,
                    'event_key' => 'reviewer_queue_pending_summary',
                ],
                'source_type' => 'system',
                'priority' => $rulePayload['priority'] ?? 'normal',
            ]);
            continue;
        }

        if (empty($existing['is_read'])) {
            if ($snapshotChanged || $targetUrlChanged) {
                $updateStmt = $conn->prepare("
                    UPDATE notifications
                    SET title = ?, message = ?, target_url = ?, metadata_json = ?, priority = ?, read_at = NULL, is_read = 0
                    WHERE id = ?
                ");
                $updateStmt->execute([
                    $title,
                    $message,
                    $targetUrl,
                    $metadataJson,
                    $rulePayload['priority'] ?? 'normal',
                    (int) $existing['id'],
                ]);
            }
            continue;
        }

        if ($snapshotChanged) {
            app_create_notification($conn, [
                'user_id' => $reviewerId,
                'type' => 'approval_queue_pending',
                'title' => $title,
                'message' => $message,
                'target_url' => $targetUrl,
                'target_entity_type' => $rulePayload['target_entity_type'] ?? 'approval_queue',
                'target_entity_id' => null,
                'metadata' => [
                    'pending_count' => $pendingCount,
                    'latest_pending_id' => $latestPendingId,
                    'pending_id_checksum' => $pendingChecksum,
                    'event_key' => 'reviewer_queue_pending_summary',
                ],
                'source_type' => 'system',
                'priority' => $rulePayload['priority'] ?? 'normal',
            ]);
        }
    }
}

function app_sync_reviewer_queue_notifications(PDO $conn): void
{
    app_notify_reviewer_queue_if_needed($conn);
}

function app_notify_log_approved(PDO $conn, array $timeLog, string $checkerName = ''): void
{
    if (!app_notifications_available($conn)) {
        return;
    }

    $userId = (int) ($timeLog['user_id'] ?? 0);
    $timeLogId = (int) ($timeLog['id'] ?? 0);
    if ($userId <= 0 || $timeLogId <= 0) {
        return;
    }

    $existsStmt = $conn->prepare("
        SELECT id
        FROM notifications
        WHERE user_id = ?
          AND type = 'approval_completed'
          AND target_entity_type = 'time_log'
          AND target_entity_id = ?
        LIMIT 1
    ");
    $existsStmt->execute([$userId, $timeLogId]);
    if ($existsStmt->fetchColumn()) {
        return;
    }

    $payload = app_notification_payload_from_rule('attendance_log_approved', [
        'time_log_id' => $timeLogId,
    ]);

    $message = $payload['message'] ?? 'รายการลงเวลาเวรของคุณได้รับการอนุมัติแล้ว';
    if ($checkerName !== '') {
        $message .= ' โดย ' . $checkerName;
    }

    app_create_notification($conn, [
        'user_id' => $userId,
        'type' => 'approval_completed',
        'title' => $payload['title'] ?? 'รายการลงเวลาเวรได้รับการอนุมัติแล้ว',
        'message' => $message,
        'target_url' => $payload['target_url'] ?? ('time.php?highlight_log=' . $timeLogId),
        'target_entity_type' => $payload['target_entity_type'] ?? 'time_log',
        'target_entity_id' => $timeLogId,
        'metadata' => [
            'work_date' => (string) ($timeLog['work_date'] ?? ''),
            'event_key' => 'attendance_log_approved',
        ],
        'source_type' => 'workflow',
        'actor_user_id' => isset($timeLog['checked_by']) ? (int) $timeLog['checked_by'] : null,
        'priority' => $payload['priority'] ?? 'normal',
    ]);
}

function app_create_approval_completed_notification(PDO $conn, array $timeLog, string $checkerName = ''): void
{
    app_notify_log_approved($conn, $timeLog, $checkerName);
}

function app_notify_log_returned(PDO $conn, array $timeLog, ?int $actorUserId = null): void
{
    if (!app_notifications_available($conn)) {
        return;
    }

    $userId = (int) ($timeLog['user_id'] ?? 0);
    $timeLogId = (int) ($timeLog['id'] ?? 0);
    if ($userId <= 0 || $timeLogId <= 0) {
        return;
    }

    $payload = app_notification_payload_from_rule('attendance_log_rejected_or_returned', [
        'time_log_id' => $timeLogId,
    ]);
    $notificationData = [
        'title' => $payload['title'] ?? 'รายการลงเวลาเวรต้องแก้ไขเพิ่มเติม',
        'message' => $payload['message'] ?? 'รายการลงเวลาเวรของคุณถูกส่งกลับเพื่อแก้ไข กรุณาตรวจสอบรายละเอียด',
        'target_url' => $payload['target_url'] ?? ('time.php?highlight_log=' . $timeLogId),
        'metadata' => [
            'event_key' => 'attendance_log_rejected_or_returned',
            'work_date' => (string) ($timeLog['work_date'] ?? ''),
        ],
        'actor_user_id' => $actorUserId,
        'priority' => $payload['priority'] ?? 'high',
    ];
    $existing = app_find_unread_notification_for_entity($conn, $userId, 'attendance_log_returned', 'time_log', $timeLogId);
    if ($existing) {
        app_update_notification_row($conn, (int) $existing['id'], $notificationData);
        return;
    }

    app_create_notification($conn, [
        'user_id' => $userId,
        'type' => 'attendance_log_returned',
        'title' => $notificationData['title'],
        'message' => $notificationData['message'],
        'target_url' => $notificationData['target_url'],
        'target_entity_type' => $payload['target_entity_type'] ?? 'time_log',
        'target_entity_id' => $timeLogId,
        'metadata' => $notificationData['metadata'],
        'source_type' => 'workflow',
        'actor_user_id' => $notificationData['actor_user_id'],
        'priority' => $notificationData['priority'],
    ]);
    return;

    app_create_notification($conn, [
        'user_id' => $userId,
        'type' => 'attendance_log_returned',
        'title' => $payload['title'] ?? 'รายการลงเวลาเวรต้องแก้ไขเพิ่มเติม',
        'message' => $payload['message'] ?? 'รายการลงเวลาเวรของคุณถูกส่งกลับเพื่อแก้ไข กรุณาตรวจสอบรายละเอียด',
        'target_url' => $payload['target_url'] ?? ('time.php?highlight_log=' . $timeLogId),
        'target_entity_type' => $payload['target_entity_type'] ?? 'time_log',
        'target_entity_id' => $timeLogId,
        'metadata' => [
            'event_key' => 'attendance_log_rejected_or_returned',
            'work_date' => (string) ($timeLog['work_date'] ?? ''),
        ],
        'source_type' => 'workflow',
        'actor_user_id' => $actorUserId,
        'priority' => $payload['priority'] ?? 'high',
    ]);
}

function app_notify_permission_changed(PDO $conn, int $affectedUserId, ?int $actorUserId = null): void
{
    if (!app_notifications_available($conn) || $affectedUserId <= 0) {
        return;
    }

    $payload = app_notification_payload_from_rule('admin_permission_changed_user');
    $notificationData = [
        'title' => $payload['title'] ?? 'สิทธิ์การใช้งานของคุณมีการเปลี่ยนแปลง',
        'message' => $payload['message'] ?? 'ผู้ดูแลระบบได้ปรับสิทธิ์การใช้งานของคุณ กรุณาตรวจสอบเมนูที่ใช้งานได้',
        'target_url' => $payload['target_url'] ?? 'profile.php',
        'metadata' => [
            'event_key' => 'admin_permission_changed_user',
        ],
        'actor_user_id' => $actorUserId,
        'priority' => $payload['priority'] ?? 'high',
    ];
    $existing = app_find_unread_notification_for_entity($conn, $affectedUserId, 'admin_permission_changed', 'user', $affectedUserId);
    if ($existing) {
        app_update_notification_row($conn, (int) $existing['id'], $notificationData);
        return;
    }
    app_create_notification($conn, [
        'user_id' => $affectedUserId,
        'type' => 'admin_permission_changed',
        'title' => $notificationData['title'],
        'message' => $notificationData['message'],
        'target_url' => $notificationData['target_url'],
        'target_entity_type' => $payload['target_entity_type'] ?? 'user',
        'target_entity_id' => $affectedUserId,
        'metadata' => $notificationData['metadata'],
        'source_type' => 'admin',
        'actor_user_id' => $notificationData['actor_user_id'],
        'priority' => $notificationData['priority'],
    ]);
    return;

    app_create_notification($conn, [
        'user_id' => $affectedUserId,
        'type' => 'admin_permission_changed',
        'title' => $payload['title'] ?? 'สิทธิ์การใช้งานของคุณมีการเปลี่ยนแปลง',
        'message' => $payload['message'] ?? 'ผู้ดูแลระบบได้ปรับสิทธิ์การใช้งานของคุณ กรุณาตรวจสอบเมนูที่ใช้งานได้',
        'target_url' => $payload['target_url'] ?? 'profile.php',
        'target_entity_type' => $payload['target_entity_type'] ?? 'user',
        'target_entity_id' => $affectedUserId,
        'metadata' => [
            'event_key' => 'admin_permission_changed_user',
        ],
        'source_type' => 'admin',
        'actor_user_id' => $actorUserId,
        'priority' => $payload['priority'] ?? 'high',
    ]);
}

function app_notify_user_profile_updated(PDO $conn, int $affectedUserId, ?int $actorUserId = null): void
{
    if (!app_notifications_available($conn) || $affectedUserId <= 0) {
        return;
    }

    $payload = app_notification_payload_from_rule('admin_updated_user_profile');
    $notificationData = [
        'title' => $payload['title'] ?? 'ข้อมูลผู้ใช้งานของคุณมีการเปลี่ยนแปลง',
        'message' => $payload['message'] ?? 'ข้อมูลบัญชีหรือข้อมูลการทำงานของคุณถูกปรับปรุงโดยผู้ดูแลระบบ',
        'target_url' => $payload['target_url'] ?? 'profile.php',
        'metadata' => [
            'event_key' => 'admin_updated_user_profile',
        ],
        'actor_user_id' => $actorUserId,
        'priority' => $payload['priority'] ?? 'normal',
    ];
    $existing = app_find_unread_notification_for_entity($conn, $affectedUserId, 'admin_updated_user_profile', 'user', $affectedUserId);
    if ($existing) {
        app_update_notification_row($conn, (int) $existing['id'], $notificationData);
        return;
    }
    app_create_notification($conn, [
        'user_id' => $affectedUserId,
        'type' => 'admin_updated_user_profile',
        'title' => $notificationData['title'],
        'message' => $notificationData['message'],
        'target_url' => $notificationData['target_url'],
        'target_entity_type' => $payload['target_entity_type'] ?? 'user',
        'target_entity_id' => $affectedUserId,
        'metadata' => $notificationData['metadata'],
        'source_type' => 'admin',
        'actor_user_id' => $notificationData['actor_user_id'],
        'priority' => $notificationData['priority'],
    ]);
    return;

    app_create_notification($conn, [
        'user_id' => $affectedUserId,
        'type' => 'admin_updated_user_profile',
        'title' => $payload['title'] ?? 'ข้อมูลผู้ใช้งานของคุณมีการเปลี่ยนแปลง',
        'message' => $payload['message'] ?? 'ข้อมูลบัญชีหรือข้อมูลการทำงานของคุณถูกปรับปรุงโดยผู้ดูแลระบบ',
        'target_url' => $payload['target_url'] ?? 'profile.php',
        'target_entity_type' => $payload['target_entity_type'] ?? 'user',
        'target_entity_id' => $affectedUserId,
        'metadata' => [
            'event_key' => 'admin_updated_user_profile',
        ],
        'source_type' => 'admin',
        'actor_user_id' => $actorUserId,
        'priority' => $payload['priority'] ?? 'normal',
    ]);
}

function app_notify_system_announcement(PDO $conn, array $userIds, string $announcementText, ?int $announcementId = null, ?string $targetUrl = null, ?int $actorUserId = null): void
{
    if (!app_notifications_available($conn) || !$userIds) {
        return;
    }

    $payload = app_notification_payload_from_rule('system_announcement_scoped', [
        'announcement_text' => $announcementText,
    ]);

    foreach (array_unique(array_map('intval', $userIds)) as $userId) {
        if ($userId <= 0) {
            continue;
        }

        if ($announcementId !== null) {
            $existsStmt = $conn->prepare("
                SELECT id
                FROM notifications
                WHERE user_id = ?
                  AND type = 'system_announcement'
                  AND target_entity_type = 'announcement'
                  AND target_entity_id = ?
                LIMIT 1
            ");
            $existsStmt->execute([$userId, $announcementId]);
            if ($existsStmt->fetchColumn()) {
                continue;
            }
        }

        app_create_notification($conn, [
            'user_id' => $userId,
            'type' => 'system_announcement',
            'title' => $payload['title'] ?? 'ประกาศจากระบบ',
            'message' => $payload['message'] ?? $announcementText,
            'target_url' => $targetUrl ?: ($payload['target_url'] ?? 'notifications.php'),
            'target_entity_type' => $payload['target_entity_type'] ?? 'announcement',
            'target_entity_id' => $announcementId,
            'metadata' => [
                'event_key' => 'system_announcement_scoped',
            ],
            'source_type' => 'admin',
            'actor_user_id' => $actorUserId,
            'priority' => $payload['priority'] ?? 'low',
        ]);
    }
}

function app_get_unread_notification_count(PDO $conn, int $userId): int
{
    if (!app_notifications_available($conn) || $userId <= 0) {
        return 0;
    }

    $stmt = $conn->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0');
    $stmt->execute([$userId]);
    return (int) $stmt->fetchColumn();
}

function app_get_recent_notifications(PDO $conn, int $userId, int $limit = 10): array
{
    if (!app_notifications_available($conn) || $userId <= 0) {
        return [];
    }

    $limit = max(1, min(20, $limit));
    $stmt = $conn->prepare("
        SELECT *
        FROM notifications
        WHERE user_id = ?
        ORDER BY is_read ASC, created_at DESC, id DESC
        LIMIT {$limit}
    ");
    $stmt->execute([$userId]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    return array_map('app_notification_present', $rows);
}

function app_get_notifications_page_data(PDO $conn, int $userId, string $status, int $limit, int $offset): array
{
    if (!app_notifications_available($conn) || $userId <= 0) {
        return ['rows' => [], 'total' => 0];
    }

    $status = in_array($status, ['all', 'unread', 'read'], true) ? $status : 'all';
    $limit = max(1, (int) $limit);
    $offset = max(0, (int) $offset);

    $where = 'WHERE user_id = :user_id';
    $params = [':user_id' => $userId];

    if ($status === 'unread') {
        $where .= ' AND is_read = 0';
    } elseif ($status === 'read') {
        $where .= ' AND is_read = 1';
    }

    $countStmt = $conn->prepare("SELECT COUNT(*) FROM notifications {$where}");
    $countStmt->execute($params);
    $total = (int) $countStmt->fetchColumn();

    $stmt = $conn->prepare("
        SELECT *
        FROM notifications
        {$where}
        ORDER BY created_at DESC, id DESC
        LIMIT :limit OFFSET :offset
    ");
    foreach ($params as $name => $value) {
        $stmt->bindValue($name, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    return [
        'rows' => array_map('app_notification_present', $rows),
        'total' => $total,
    ];
}

function app_mark_notification_read(PDO $conn, int $userId, int $notificationId): bool
{
    if (!app_notifications_available($conn) || $userId <= 0 || $notificationId <= 0) {
        return false;
    }

    $stmt = $conn->prepare("
        UPDATE notifications
        SET is_read = 1, read_at = COALESCE(read_at, NOW())
        WHERE id = ? AND user_id = ?
    ");
    $stmt->execute([$notificationId, $userId]);
    return $stmt->rowCount() > 0;
}

function app_mark_all_notifications_read(PDO $conn, int $userId): int
{
    if (!app_notifications_available($conn) || $userId <= 0) {
        return 0;
    }

    $stmt = $conn->prepare("
        UPDATE notifications
        SET is_read = 1, read_at = COALESCE(read_at, NOW())
        WHERE user_id = ? AND is_read = 0
    ");
    $stmt->execute([$userId]);
    return (int) $stmt->rowCount();
}

function app_notification_relative_time(?string $createdAt): string
{
    if (!$createdAt) {
        return '-';
    }

    $timestamp = strtotime($createdAt);
    if ($timestamp === false) {
        return '-';
    }

    $diff = time() - $timestamp;
    if ($diff < 60) {
        return 'เมื่อสักครู่';
    }
    if ($diff < 3600) {
        return floor($diff / 60) . ' นาทีที่แล้ว';
    }
    if ($diff < 86400) {
        return floor($diff / 3600) . ' ชั่วโมงที่แล้ว';
    }

    return app_format_thai_datetime($createdAt);
}

function app_notification_present(array $row): array
{
    return [
        'id' => (int) ($row['id'] ?? 0),
        'type' => (string) ($row['type'] ?? 'system_notice'),
        'title' => (string) ($row['title'] ?? ''),
        'message' => (string) ($row['message'] ?? ''),
        'target_url' => (string) ($row['target_url'] ?? ''),
        'target_entity_type' => (string) ($row['target_entity_type'] ?? ''),
        'target_entity_id' => isset($row['target_entity_id']) ? (int) $row['target_entity_id'] : null,
        'is_read' => !empty($row['is_read']),
        'created_at' => (string) ($row['created_at'] ?? ''),
        'created_at_label' => app_notification_relative_time((string) ($row['created_at'] ?? '')),
        'priority' => (string) ($row['priority'] ?? 'normal'),
        'metadata' => app_notification_decode_metadata($row['metadata_json'] ?? null),
    ];
}

function app_notification_sidebar_mapping(): array
{
    return [
        'time' => [
            'pages' => ['time.php'],
            'types' => ['approval_completed', 'attendance_log_returned'],
            'entity_types' => ['time_log'],
            'url_contains' => ['time.php'],
        ],
        'my_shifts' => [
            'pages' => ['my-shifts.php'],
            'types' => ['monthly_schedule_published', 'my_shift', 'shift_assignment', 'schedule_published'],
            'entity_types' => ['monthly_schedule', 'shift_assignment'],
            'url_contains' => ['my-shifts.php'],
        ],
        'shift_swaps' => [
            'pages' => ['shift-swap-requests.php'],
            'types' => [
                'swap_request_created',
                'swap_target_confirmed',
                'swap_target_rejected',
                'swap_manager_approved',
                'swap_manager_rejected',
                'swap_cancelled',
                'shift_swap_request_created',
                'shift_swap_target_confirmed',
                'shift_swap_target_rejected',
                'shift_swap_manager_approved',
                'shift_swap_manager_rejected',
                'shift_swap_cancelled',
            ],
            'entity_types' => ['shift_swap_request'],
            'url_contains' => ['shift-swap-requests.php'],
        ],
        'review' => [
            'pages' => ['approval_queue.php'],
            'types' => ['approval_queue_pending', 'approval_queue_ready', 'reviewer_queue_pending_summary'],
            'entity_types' => ['approval_queue'],
            'url_contains' => ['approval_queue.php'],
        ],
        'today' => [
            'pages' => ['daily_schedule.php'],
            'types' => ['today_shift', 'shift_today', 'assigned_today'],
            'entity_types' => ['daily_schedule', 'today_shift'],
            'url_contains' => ['daily_schedule.php'],
        ],
        'shift_schedules' => [
            'pages' => ['shift_schedules.php'],
            'types' => ['monthly_schedule', 'schedule_publish', 'schedule_management'],
            'entity_types' => ['shift_schedule_management'],
            'url_contains' => ['shift_schedules.php'],
        ],
        'my_reports' => [
            'pages' => ['my_reports.php'],
            'types' => ['report_ready', 'export_or_report_job_completed'],
            'entity_types' => ['report_job'],
            'url_contains' => ['my_reports.php'],
        ],
        'department_reports' => [
            'pages' => ['department_reports.php'],
            'types' => ['department_report', 'department_report_ready'],
            'entity_types' => ['department_report'],
            'url_contains' => ['department_reports.php'],
        ],
    ];
}

function app_notification_sidebar_key_for_page(string $page): ?string
{
    $page = strtolower(trim(basename(parse_url($page, PHP_URL_PATH) ?: $page)));
    if ($page === '') {
        return null;
    }

    foreach (app_notification_sidebar_mapping() as $key => $rule) {
        if (in_array($page, array_map('strtolower', $rule['pages'] ?? []), true)) {
            return $key;
        }
    }

    return null;
}

function app_notification_sidebar_key_for_row(array $row): ?string
{
    $type = strtolower((string) ($row['type'] ?? ''));
    $entityType = strtolower((string) ($row['target_entity_type'] ?? ''));
    $targetUrl = strtolower((string) ($row['target_url'] ?? ''));
    $targetPage = strtolower(basename(parse_url($targetUrl, PHP_URL_PATH) ?: $targetUrl));

    foreach (app_notification_sidebar_mapping() as $key => $rule) {
        if ($type !== '' && in_array($type, array_map('strtolower', $rule['types'] ?? []), true)) {
            return $key;
        }
        if ($entityType !== '' && in_array($entityType, array_map('strtolower', $rule['entity_types'] ?? []), true)) {
            return $key;
        }
        if ($targetPage !== '' && in_array($targetPage, array_map('strtolower', $rule['pages'] ?? []), true)) {
            return $key;
        }
        foreach ($rule['url_contains'] ?? [] as $needle) {
            if ($needle !== '' && $targetUrl !== '' && strpos($targetUrl, strtolower($needle)) !== false) {
                return $key;
            }
        }
    }

    return null;
}

function app_get_sidebar_notification_counts(PDO $conn, int $userId): array
{
    if (!app_notifications_available($conn) || $userId <= 0) {
        return [];
    }

    $stmt = $conn->prepare("
        SELECT id, type, target_url, target_entity_type, target_entity_id, metadata_json
        FROM notifications
        WHERE user_id = ? AND is_read = 0
    ");
    $stmt->execute([$userId]);

    $counts = [];
    foreach (($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []) as $row) {
        $key = app_notification_sidebar_key_for_row($row);
        if ($key === null) {
            continue;
        }
        $counts[$key] = ($counts[$key] ?? 0) + 1;
    }

    return $counts;
}

function app_notification_target_section_for_row(array $row, string $moduleKey): string
{
    $type = strtolower((string) ($row['type'] ?? ''));

    if ($moduleKey === 'shift_swaps') {
        if (strpos($type, 'request_created') !== false) {
            return 'incoming';
        }
        if (strpos($type, 'target_confirmed') !== false) {
            return 'manager';
        }
        if (
            strpos($type, 'target_rejected') !== false
            || strpos($type, 'manager_approved') !== false
            || strpos($type, 'manager_rejected') !== false
            || strpos($type, 'cancelled') !== false
        ) {
            return 'history';
        }

        return 'sent';
    }

    return $moduleKey;
}

function app_notification_target_id_from_row(array $row): ?int
{
    $entityId = isset($row['target_entity_id']) ? (int) $row['target_entity_id'] : 0;
    if ($entityId > 0) {
        return $entityId;
    }

    $metadata = app_notification_decode_metadata($row['metadata_json'] ?? null);
    foreach (['swap_request_id', 'request_id', 'target_id'] as $key) {
        $value = isset($metadata[$key]) ? (int) $metadata[$key] : 0;
        if ($value > 0) {
            return $value;
        }
    }

    $targetUrl = (string) ($row['target_url'] ?? '');
    $query = parse_url($targetUrl, PHP_URL_QUERY);
    if (is_string($query) && $query !== '') {
        parse_str($query, $params);
        foreach (['highlight', 'swap_request_id', 'request_id'] as $key) {
            $value = isset($params[$key]) ? (int) $params[$key] : 0;
            if ($value > 0) {
                return $value;
            }
        }
    }

    return null;
}

function app_notification_focus_target_for_row(array $row): ?array
{
    $moduleKey = app_notification_sidebar_key_for_row($row);
    if ($moduleKey === null) {
        return null;
    }

    $targetId = app_notification_target_id_from_row($row);
    $section = app_notification_target_section_for_row($row, $moduleKey);
    $targetType = (string) ($row['target_entity_type'] ?? '');
    $selector = null;

    if ($moduleKey === 'shift_swaps' && $targetId !== null) {
        $targetType = $targetType !== '' ? $targetType : 'shift_swap_request';
        $selector = '[data-notification-target="shift-swap-request-' . $targetId . '"]';
    }

    return [
        'notification_id' => (int) ($row['id'] ?? 0),
        'module' => $moduleKey,
        'target_type' => $targetType,
        'target_id' => $targetId,
        'section' => $section,
        'selector' => $selector,
        'target_url' => (string) ($row['target_url'] ?? ''),
        'message' => (string) ($row['message'] ?? ''),
        'created_at' => (string) ($row['created_at'] ?? ''),
    ];
}

function app_get_unread_notification_targets_for_sidebar_key(PDO $conn, int $userId, string $moduleKey, int $limit = 5): array
{
    $moduleKey = trim($moduleKey);
    if (!app_notifications_available($conn) || $userId <= 0 || !isset(app_notification_sidebar_mapping()[$moduleKey])) {
        return [];
    }

    $stmt = $conn->prepare("
        SELECT id, type, message, target_url, target_entity_type, target_entity_id, metadata_json, created_at
        FROM notifications
        WHERE user_id = ? AND is_read = 0
        ORDER BY created_at DESC, id DESC
        LIMIT 100
    ");
    $stmt->execute([$userId]);

    $targets = [];
    foreach (($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []) as $row) {
        if (app_notification_sidebar_key_for_row($row) !== $moduleKey) {
            continue;
        }

        $target = app_notification_focus_target_for_row($row);
        if ($target === null) {
            continue;
        }

        $targets[] = $target;
        if (count($targets) >= max(1, $limit)) {
            break;
        }
    }

    return $targets;
}

function app_mark_notifications_read_for_sidebar_key(PDO $conn, int $userId, string $moduleKey): int
{
    $moduleKey = trim($moduleKey);
    if (!app_notifications_available($conn) || $userId <= 0 || !isset(app_notification_sidebar_mapping()[$moduleKey])) {
        return 0;
    }

    $stmt = $conn->prepare("
        SELECT id, type, target_url, target_entity_type, target_entity_id, metadata_json
        FROM notifications
        WHERE user_id = ? AND is_read = 0
    ");
    $stmt->execute([$userId]);

    $ids = [];
    foreach (($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []) as $row) {
        if (app_notification_sidebar_key_for_row($row) === $moduleKey) {
            $ids[] = (int) $row['id'];
        }
    }

    if (!$ids) {
        return 0;
    }

    $updated = 0;
    foreach (array_chunk($ids, 100) as $chunk) {
        $placeholders = implode(',', array_fill(0, count($chunk), '?'));
        $update = $conn->prepare("
            UPDATE notifications
            SET is_read = 1, read_at = COALESCE(read_at, NOW())
            WHERE user_id = ? AND is_read = 0 AND id IN ({$placeholders})
        ");
        $update->execute(array_merge([$userId], $chunk));
        $updated += (int) $update->rowCount();
    }

    return $updated;
}

/**
 * Notify every staff member who has at least one published shift assignment in
 * the given department/month/year.
 *
 * Dedup strategy: a synthetic entity_id = dept_id * 100000 + (year%100)*12 + month
 * is stored per user. If any notification with that entity_id already exists for
 * the user, we update and re-surface that row instead of inserting a duplicate.
 *
 * Returns the number of notifications inserted or refreshed.
 */
function app_notify_monthly_schedule_published(
    PDO $conn,
    int $departmentId,
    int $month,
    int $year,
    int $actorUserId
): int {
    if (!app_notifications_available($conn) || $departmentId <= 0) {
        return 0;
    }

    // --- Find all users who have assignments in this dept/month that are now published ---
    $firstDay = sprintf('%04d-%02d-01', $year, $month);
    $lastDay  = date('Y-m-t', strtotime($firstDay));

    $stmt = $conn->prepare("
        SELECT DISTINCT sa.staff_id
        FROM shift_assignments sa
        INNER JOIN shift_schedules ss ON ss.id = sa.schedule_id
        INNER JOIN users u ON u.id = sa.staff_id
        WHERE ss.department_id = ?
          AND ss.status = 'published'
          AND ss.schedule_date >= ?
          AND ss.schedule_date <= ?
          AND sa.assignment_status <> 'cancelled'
          AND sa.staff_id > 0
          AND COALESCE(u.is_active, 1) = 1
    ");
    $stmt->execute([$departmentId, $firstDay, $lastDay]);
    $userIds = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);

    if (empty($userIds)) {
        return 0;
    }

    // --- Build notification payload ---
    $thaiMonths = [
        1 => 'มกราคม', 2 => 'กุมภาพันธ์', 3 => 'มีนาคม', 4 => 'เมษายน',
        5 => 'พฤษภาคม', 6 => 'มิถุนายน', 7 => 'กรกฎาคม', 8 => 'สิงหาคม',
        9 => 'กันยายน', 10 => 'ตุลาคม', 11 => 'พฤศจิกายน', 12 => 'ธันวาคม',
    ];
    $monthName = $thaiMonths[$month] ?? $month;
    $yearBe    = $year + 543;

    // Fetch department name for the message
    $deptStmt = $conn->prepare("SELECT department_name FROM departments WHERE id = ? LIMIT 1");
    $deptStmt->execute([$departmentId]);
    $deptName = (string) ($deptStmt->fetchColumn() ?: 'แผนกของคุณ');

    $title   = 'ตารางเวรรายเดือนเผยแพร่แล้ว';
    $message = "ตารางเวรประจำเดือน {$monthName} {$yearBe} ของแผนก {$deptName} ได้รับการเผยแพร่แล้ว กรุณาตรวจสอบเวรของคุณ";
    $url     = "my-shifts.php?month={$month}&year={$yearBe}&view=my&display=calendar";

    // Synthetic entity_id: unique per dept + year + month combination
    $entityId   = $departmentId * 100000 + ($year % 100) * 12 + $month;
    $entityType = 'monthly_schedule';
    $eventKey = "monthly_schedule_published:{$departmentId}:{$year}:{$month}";

    $notified = 0;

    foreach ($userIds as $userId) {
        $userId = (int) $userId;
        if ($userId <= 0) {
            continue;
        }

        // Dedup across read/unread rows so re-publishing the same month never spams inboxes.
        $existing = app_find_notification_for_entity(
            $conn,
            $userId,
            'monthly_schedule_published',
            $entityType,
            $entityId
        );

        if ($existing) {
            // Re-surface existing notification (update content + mark unread again)
            app_update_notification_row($conn, (int) $existing['id'], [
                'title'        => $title,
                'message'      => $message,
                'target_url'   => $url,
                'actor_user_id'=> $actorUserId,
                'priority'     => 'normal',
                'metadata'     => [
                    'event_key' => $eventKey,
                    'department_id' => $departmentId,
                    'month' => $month,
                    'year' => $year,
                    'year_be' => $yearBe,
                    'target_url' => $url,
                ],
            ]);
        } else {
            app_create_notification($conn, [
                'user_id'            => $userId,
                'type'               => 'monthly_schedule_published',
                'title'              => $title,
                'message'            => $message,
                'target_url'         => $url,
                'target_entity_type' => $entityType,
                'target_entity_id'   => $entityId,
                'source_type'        => 'shift_schedule',
                'actor_user_id'      => $actorUserId,
                'priority'           => 'normal',
                'metadata'           => [
                    'event_key' => $eventKey,
                    'department_id' => $departmentId,
                    'month' => $month,
                    'year' => $year,
                    'year_be' => $yearBe,
                    'target_url' => $url,
                ],
            ]);
        }

        $notified++;
    }

    return $notified;
}
