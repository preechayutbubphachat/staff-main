<?php

require_once __DIR__ . '/shift_swap_service.php';

function app_shift_cover_status_meta(string $status): array
{
    $map = [
        'pending_substitute_confirm' => ['label' => 'รอผู้แทนยืนยัน', 'class' => 'pending-target'],
        'rejected_by_substitute' => ['label' => 'ผู้แทนปฏิเสธ', 'class' => 'rejected'],
        'pending_manager_approval' => ['label' => 'รอหัวหน้าอนุมัติ', 'class' => 'pending-manager'],
        'rejected_by_manager' => ['label' => 'หัวหน้าปฏิเสธ', 'class' => 'rejected'],
        'applied' => ['label' => 'แทนเวรแล้ว', 'class' => 'applied'],
        'cancelled' => ['label' => 'ยกเลิกแล้ว', 'class' => 'cancelled'],
    ];

    return $map[$status] ?? ['label' => $status, 'class' => 'neutral'];
}

function app_shift_cover_normalize_error(Throwable $e): RuntimeException
{
    return new RuntimeException(str_replace(['แลกเวร', 'แลก'], ['แทนเวร', 'แทนเวร'], $e->getMessage()), 0, $e);
}

function app_shift_cover_tables_ready(PDO $conn): bool
{
    return app_table_exists($conn, 'shift_cover_requests') && app_table_exists($conn, 'shift_cover_documents');
}

function app_shift_cover_insert_audit(PDO $conn, int $coverRequestId, string $actionType, ?array $oldValues, ?array $newValues, int $actorUserId, ?string $note = null): void
{
    app_shift_insert_audit($conn, 'shift_cover_requests', $coverRequestId, $actionType, $oldValues, $newValues, $actorUserId, $note);
}

function app_shift_cover_notify(PDO $conn, int $userId, string $type, string $title, string $message, int $coverRequestId, int $actorUserId, array $metadata = [], string $priority = 'normal'): void
{
    if ($userId <= 0 || !app_notifications_available($conn)) {
        return;
    }

    app_create_notification($conn, [
        'user_id' => $userId,
        'type' => $type,
        'title' => $title,
        'message' => $message,
        'target_url' => 'shift-cover-requests.php?highlight=' . $coverRequestId,
        'target_entity_type' => 'shift_cover_request',
        'target_entity_id' => $coverRequestId,
        'metadata' => ['cover_request_id' => $coverRequestId] + $metadata,
        'source_type' => 'workflow',
        'actor_user_id' => $actorUserId,
        'priority' => $priority,
    ]);
}

function app_shift_cover_get_request(PDO $conn, int $coverRequestId): ?array
{
    if ($coverRequestId <= 0 || !app_table_exists($conn, 'shift_cover_requests')) {
        return null;
    }
    $stmt = $conn->prepare("
        SELECT cr.*, requester.fullname AS requester_name, substitute.fullname AS substitute_name, d.department_name
        FROM shift_cover_requests cr
        INNER JOIN users requester ON requester.id = cr.requester_id
        INNER JOIN users substitute ON substitute.id = cr.substitute_staff_id
        LEFT JOIN departments d ON d.id = cr.department_id
        WHERE cr.id = ?
        LIMIT 1
    ");
    $stmt->execute([$coverRequestId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return $row ?: null;
}

function app_shift_cover_document_table_ready(PDO $conn): bool
{
    return app_table_exists($conn, 'shift_cover_documents');
}

function app_shift_cover_signature_storage_dir(): string
{
    return dirname(__DIR__) . '/uploads/shift_cover_signatures';
}

function app_shift_cover_store_signature_blob(int $coverRequestId, int $actorUserId, string $role, string $binary): string
{
    $dir = app_shift_cover_signature_storage_dir();
    if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
        throw new RuntimeException('ไม่สามารถสร้างโฟลเดอร์ลายเซ็นแทนเวรได้');
    }
    $fileName = sprintf('cover_%d_%s_%d_%s.png', $coverRequestId, preg_replace('/[^a-z_]/', '', $role), $actorUserId, bin2hex(random_bytes(6)));
    $path = $dir . '/' . $fileName;
    if (file_put_contents($path, $binary) === false) {
        throw new RuntimeException('ไม่สามารถบันทึกลายเซ็นแทนเวรได้');
    }

    return $fileName;
}

function app_shift_cover_copy_profile_signature(PDO $conn, int $coverRequestId, int $actorUserId, string $role): string
{
    $actor = app_shift_swap_user_snapshot($conn, $actorUserId);
    $profilePath = (string) ($actor['signature_path'] ?? '');
    if ($profilePath === '') {
        throw new RuntimeException('ยังไม่มีลายเซ็นในโปรไฟล์ กรุณาวาดลายเซ็นก่อนดำเนินการ');
    }
    $source = app_shift_swap_profile_signature_storage_dir() . '/' . basename($profilePath);
    if (!is_file($source)) {
        throw new RuntimeException('ไม่พบไฟล์ลายเซ็นในโปรไฟล์ กรุณาวาดลายเซ็นใหม่');
    }
    $binary = file_get_contents($source);
    if ($binary === false || $binary === '') {
        throw new RuntimeException('ไม่สามารถอ่านลายเซ็นจากโปรไฟล์ได้');
    }

    return app_shift_cover_store_signature_blob($coverRequestId, $actorUserId, $role, $binary);
}

function app_shift_cover_capture_signature(PDO $conn, int $coverRequestId, int $actorUserId, string $role, string $signatureData, bool $useProfileSignature): string
{
    if ($useProfileSignature) {
        return app_shift_cover_copy_profile_signature($conn, $coverRequestId, $actorUserId, $role);
    }

    $binary = app_shift_swap_decode_signature_data($signatureData);
    $fileName = app_shift_cover_store_signature_blob($coverRequestId, $actorUserId, $role, $binary);
    app_shift_swap_update_profile_signature_from_data($conn, $actorUserId, $signatureData);

    return $fileName;
}

function app_shift_cover_shift_snapshot_text(array $assignment): string
{
    $summary = app_shift_swap_assignment_summary($assignment);
    return trim(sprintf(
        '%s | %s | %s | %s | %s',
        $summary['date'],
        $summary['shift_label'],
        $summary['time'],
        $summary['department_name'],
        $summary['staff_name']
    ));
}

function app_shift_cover_find_staff_overlap(PDO $conn, int $staffId, array $sourceAssignment, array $excludeAssignmentIds = []): ?array
{
    $excludeAssignmentIds = array_values(array_unique(array_filter(array_map('intval', $excludeAssignmentIds), static function (int $id): bool {
        return $id > 0;
    })));
    $sourceDate = (string) $sourceAssignment['schedule_date'];
    $startWindow = (new DateTimeImmutable($sourceDate))->modify('-1 day')->format('Y-m-d');
    $endWindow = (new DateTimeImmutable($sourceDate))->modify('+1 day')->format('Y-m-d');
    $sql = "
        SELECT a.id AS assignment_id, s.id AS schedule_id, s.shift_type, s.schedule_date, s.start_time, s.end_time
        FROM shift_assignments a
        INNER JOIN shift_schedules s ON s.id = a.schedule_id
        LEFT JOIN time_logs tl ON tl.schedule_assignment_id = a.id
        WHERE a.staff_id = ?
          AND a.assignment_status = 'assigned'
          AND s.status <> 'cancelled'
          AND s.schedule_date BETWEEN ? AND ?
          AND tl.id IS NULL
    ";
    $params = [$staffId, $startWindow, $endWindow];
    if ($excludeAssignmentIds) {
        $sql .= ' AND a.id NOT IN (' . implode(',', array_fill(0, count($excludeAssignmentIds), '?')) . ')';
        $params = array_merge($params, $excludeAssignmentIds);
    }
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    [$sourceStart, $sourceEnd] = app_shift_slot_bounds((string) $sourceAssignment['schedule_date'], (string) $sourceAssignment['start_time'], (string) $sourceAssignment['end_time']);
    foreach (($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []) as $row) {
        [$rowStart, $rowEnd] = app_shift_slot_bounds((string) $row['schedule_date'], (string) $row['start_time'], (string) $row['end_time']);
        if ($sourceStart < $rowEnd && $rowStart < $sourceEnd) {
            return $row;
        }
    }

    return null;
}

function app_shift_cover_has_active_request(PDO $conn, int $sourceAssignmentId, ?int $excludeCoverId = null): bool
{
    if (!app_table_exists($conn, 'shift_cover_requests')) {
        return false;
    }
    $sql = "
        SELECT id
        FROM shift_cover_requests
        WHERE source_assignment_id = ?
          AND status IN ('pending_substitute_confirm', 'pending_manager_approval')
    ";
    $params = [$sourceAssignmentId];
    if ($excludeCoverId !== null) {
        $sql .= ' AND id <> ?';
        $params[] = $excludeCoverId;
    }
    $sql .= ' LIMIT 1';
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);

    return (bool) $stmt->fetchColumn();
}

function app_shift_cover_assert_substitute_available(PDO $conn, array $sourceAssignment, int $requesterId, int $substituteStaffId, ?int $excludeCoverId = null): array
{
    try {
        app_shift_swap_assert_assignment_swappable($conn, $sourceAssignment, $requesterId);
    } catch (Throwable $e) {
        throw app_shift_cover_normalize_error($e);
    }
    if ($substituteStaffId <= 0 || $substituteStaffId === $requesterId) {
        throw new RuntimeException('ต้องเลือกเจ้าหน้าที่ผู้แทนเวรคนละคนกับผู้ขอ');
    }
    if (app_shift_swap_has_active_request($conn, [(int) $sourceAssignment['id']])) {
        throw new RuntimeException('เวรนี้มีคำขอแลกเวรที่กำลังดำเนินการอยู่แล้ว');
    }
    if (app_shift_cover_has_active_request($conn, (int) $sourceAssignment['id'], $excludeCoverId)) {
        throw new RuntimeException('เวรนี้มีคำขอแทนเวรที่กำลังดำเนินการอยู่แล้ว');
    }

    $stmt = $conn->prepare("
        SELECT u.id, u.fullname, u.position_name, u.department_id, u.signature_path, d.department_name
        FROM users u
        LEFT JOIN departments d ON d.id = u.department_id
        WHERE u.id = ? AND COALESCE(u.is_active, 1) = 1
        LIMIT 1
    ");
    $stmt->execute([$substituteStaffId]);
    $substitute = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$substitute) {
        throw new RuntimeException('ไม่พบเจ้าหน้าที่ผู้แทนเวร หรือบัญชีไม่พร้อมใช้งาน');
    }
    if ((int) $substitute['department_id'] !== (int) $sourceAssignment['department_id']) {
        throw new RuntimeException('เฟสนี้รองรับการแทนเวรในแผนกเดียวกันเท่านั้น');
    }
    $overlap = app_shift_cover_find_staff_overlap($conn, $substituteStaffId, $sourceAssignment, [(int) $sourceAssignment['id']]);
    if ($overlap) {
        throw new RuntimeException('เจ้าหน้าที่ผู้แทนมีเวรชนกันในช่วงเวลานี้');
    }

    return $substitute;
}

function app_shift_cover_available_substitutes(PDO $conn, int $requesterId, int $sourceAssignmentId): array
{
    $source = app_shift_swap_get_assignment($conn, $sourceAssignmentId);
    if (!$source) {
        return [];
    }
    try {
        app_shift_swap_assert_assignment_swappable($conn, $source, $requesterId);
    } catch (Throwable) {
        return [];
    }

    $staffRows = app_shift_fetch_staff($conn, (int) $source['department_id']);
    $result = [];
    foreach ($staffRows as $staff) {
        $staffId = (int) $staff['id'];
        if ($staffId === $requesterId) {
            continue;
        }
        $blockedReason = '';
        try {
            app_shift_cover_assert_substitute_available($conn, $source, $requesterId, $staffId);
        } catch (Throwable $e) {
            $blockedReason = $e->getMessage();
        }
        $result[] = [
            'staff_id' => $staffId,
            'name' => (string) ($staff['fullname'] ?? ''),
            'position' => (string) ($staff['position_name'] ?? ''),
            'department' => (string) ($staff['department_name'] ?? ''),
            'avatar_url' => (string) ($staff['profile_image_url'] ?? ''),
            'initials' => (string) ($staff['initials'] ?? app_shift_staff_initials((string) ($staff['fullname'] ?? ''))),
            'available' => $blockedReason === '',
            'blocked_reason' => $blockedReason !== '' ? $blockedReason : 'ว่างในช่วงเวลานี้',
        ];
    }

    return $result;
}

function app_shift_cover_create_request(PDO $conn, int $requesterId, int $sourceAssignmentId, int $substituteStaffId, string $reason, string $signatureData, bool $useProfileSignature = false): array
{
    if (!app_shift_cover_tables_ready($conn)) {
        throw new RuntimeException('ยังไม่ได้ติดตั้งตารางคำขอแทนเวร กรุณารัน migration 2026_05_21_create_shift_cover_requests.sql');
    }
    $source = app_shift_swap_get_assignment($conn, $sourceAssignmentId);
    if (!$source) {
        throw new RuntimeException('ไม่พบเวรต้นทาง');
    }
    $substitute = app_shift_cover_assert_substitute_available($conn, $source, $requesterId, $substituteStaffId);
    $requester = app_shift_swap_user_snapshot($conn, $requesterId);
    $reason = trim($reason);
    if ($reason === '') {
        $reason = 'ขอให้เจ้าหน้าที่อื่นปฏิบัติหน้าที่แทนเวร';
    }

    $conn->beginTransaction();
    try {
        $stmt = $conn->prepare("
            INSERT INTO shift_cover_requests (
                requester_id, substitute_staff_id, source_assignment_id, department_id, reason, status, created_at
            ) VALUES (?, ?, ?, ?, ?, 'pending_substitute_confirm', NOW())
        ");
        $stmt->execute([$requesterId, $substituteStaffId, $sourceAssignmentId, (int) $source['department_id'], $reason]);
        $coverRequestId = (int) $conn->lastInsertId();
        $signaturePath = app_shift_cover_capture_signature($conn, $coverRequestId, $requesterId, 'requester', $signatureData, $useProfileSignature);
        $docStmt = $conn->prepare("
            INSERT INTO shift_cover_documents (
                cover_request_id, document_status, requester_signature_path, requester_signed_at,
                requester_name_snapshot, requester_position_snapshot, requester_department_snapshot,
                substitute_name_snapshot, substitute_position_snapshot, substitute_department_snapshot,
                source_shift_snapshot, reason_snapshot, created_at
            ) VALUES (?, 'requester_signed', ?, NOW(), ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        $docStmt->execute([
            $coverRequestId,
            $signaturePath,
            $requester['name'],
            $requester['position'],
            $requester['department'],
            (string) ($substitute['fullname'] ?? ''),
            (string) ($substitute['position_name'] ?? ''),
            (string) ($substitute['department_name'] ?? ''),
            app_shift_cover_shift_snapshot_text($source),
            $reason,
        ]);
        app_shift_cover_insert_audit($conn, $coverRequestId, 'requester_create_cover_request', null, [
            'source_assignment_id' => $sourceAssignmentId,
            'requester_id' => $requesterId,
            'substitute_staff_id' => $substituteStaffId,
        ], $requesterId, 'ผู้ขอส่งคำขอแทนเวร');
        app_shift_cover_notify($conn, $substituteStaffId, 'cover_request_created', 'มีคำขอแทนเวรรอพิจารณา', 'มีเจ้าหน้าที่ขอให้คุณปฏิบัติหน้าที่แทนเวร', $coverRequestId, $requesterId, ['source_assignment_id' => $sourceAssignmentId], 'high');
        $conn->commit();

        return ['message' => 'ส่งคำขอแทนเวรแล้ว รอผู้แทนยืนยัน', 'cover_request_id' => $coverRequestId];
    } catch (Throwable $e) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        throw $e;
    }
}

function app_shift_cover_update_document_signature(PDO $conn, int $coverRequestId, int $actorUserId, string $role, string $signatureData, bool $useProfileSignature, string $status, ?string $note = null): void
{
    $signaturePath = app_shift_cover_capture_signature($conn, $coverRequestId, $actorUserId, $role, $signatureData, $useProfileSignature);
    $actor = app_shift_swap_user_snapshot($conn, $actorUserId);
    if ($role === 'substitute') {
        $stmt = $conn->prepare("
            UPDATE shift_cover_documents
            SET substitute_signature_path = ?,
                substitute_signed_at = NOW(),
                substitute_name_snapshot = COALESCE(NULLIF(substitute_name_snapshot, ''), ?),
                substitute_position_snapshot = COALESCE(NULLIF(substitute_position_snapshot, ''), ?),
                substitute_department_snapshot = COALESCE(NULLIF(substitute_department_snapshot, ''), ?),
                substitute_response_note_snapshot = ?,
                document_status = ?,
                updated_at = NOW()
            WHERE cover_request_id = ?
        ");
        $stmt->execute([$signaturePath, $actor['name'], $actor['position'], $actor['department'], $note, $status, $coverRequestId]);
        return;
    }

    $stmt = $conn->prepare("
        UPDATE shift_cover_documents
        SET approver_signature_path = ?,
            approver_signed_at = NOW(),
            approver_name_snapshot = ?,
            approver_position_snapshot = ?,
            approver_department_snapshot = ?,
            manager_response_note_snapshot = ?,
            document_status = ?,
            updated_at = NOW()
        WHERE cover_request_id = ?
    ");
    $stmt->execute([$signaturePath, $actor['name'], $actor['position'], $actor['department'], $note, $status, $coverRequestId]);
}

function app_shift_cover_update_substitute_response(PDO $conn, int $coverRequestId, int $substituteUserId, string $decision, string $note, string $signatureData = '', bool $useProfileSignature = false): array
{
    $request = app_shift_cover_get_request($conn, $coverRequestId);
    if (!$request) {
        throw new RuntimeException('ไม่พบคำขอแทนเวร');
    }
    if ((int) $request['substitute_staff_id'] !== $substituteUserId) {
        throw new RuntimeException('คุณไม่มีสิทธิ์ตอบคำขอนี้');
    }
    if ((string) $request['status'] !== 'pending_substitute_confirm') {
        throw new RuntimeException('คำขอนี้ไม่ได้อยู่ในขั้นรอผู้แทนยืนยัน');
    }
    $source = app_shift_swap_get_assignment($conn, (int) $request['source_assignment_id']);
    if (!$source) {
        throw new RuntimeException('ไม่พบเวรต้นทาง');
    }

    $note = trim($note);
    $conn->beginTransaction();
    try {
        if ($decision === 'confirm') {
            app_shift_cover_assert_substitute_available($conn, $source, (int) $request['requester_id'], $substituteUserId, $coverRequestId);
            $stmt = $conn->prepare("
                UPDATE shift_cover_requests
                SET status = 'pending_manager_approval',
                    substitute_response_note = ?,
                    substitute_confirmed_at = NOW(),
                    updated_at = NOW()
                WHERE id = ? AND status = 'pending_substitute_confirm' AND substitute_staff_id = ?
            ");
            $stmt->execute([$note !== '' ? $note : null, $coverRequestId, $substituteUserId]);
            app_shift_cover_update_document_signature($conn, $coverRequestId, $substituteUserId, 'substitute', $signatureData, $useProfileSignature, 'substitute_signed', $note);
            app_shift_cover_insert_audit($conn, $coverRequestId, 'substitute_confirm_cover_request', ['status' => $request['status']], ['status' => 'pending_manager_approval'], $substituteUserId, 'ผู้แทนยืนยันคำขอแทนเวร');
            foreach (app_shift_swap_manager_recipients($conn, (int) $request['department_id']) as $managerId) {
                app_shift_cover_notify($conn, $managerId, 'cover_substitute_confirmed', 'มีคำขอแทนเวรรออนุมัติ', 'ผู้แทนเวรยืนยันแล้ว รอหัวหน้าพิจารณา', $coverRequestId, $substituteUserId, ['department_id' => (int) $request['department_id']], 'high');
            }
            $message = 'ยืนยันคำขอแทนเวรแล้ว รอหัวหน้าอนุมัติ';
        } else {
            if ($note === '') {
                throw new RuntimeException('กรุณาระบุเหตุผลการปฏิเสธ');
            }
            $stmt = $conn->prepare("
                UPDATE shift_cover_requests
                SET status = 'rejected_by_substitute',
                    substitute_response_note = ?,
                    substitute_rejected_at = NOW(),
                    updated_at = NOW()
                WHERE id = ? AND status = 'pending_substitute_confirm' AND substitute_staff_id = ?
            ");
            $stmt->execute([$note, $coverRequestId, $substituteUserId]);
            $docStmt = $conn->prepare('UPDATE shift_cover_documents SET document_status = ?, substitute_response_note_snapshot = ?, updated_at = NOW() WHERE cover_request_id = ?');
            $docStmt->execute(['substitute_rejected', $note, $coverRequestId]);
            app_shift_cover_insert_audit($conn, $coverRequestId, 'substitute_reject_cover_request', ['status' => $request['status']], ['status' => 'rejected_by_substitute', 'note' => $note], $substituteUserId, 'ผู้แทนปฏิเสธคำขอแทนเวร');
            app_shift_cover_notify($conn, (int) $request['requester_id'], 'cover_substitute_rejected', 'คำขอแทนเวรถูกปฏิเสธ', 'ผู้แทนเวรปฏิเสธคำขอแทนเวร', $coverRequestId, $substituteUserId, ['note' => $note], 'high');
            $message = 'ปฏิเสธคำขอแทนเวรแล้ว';
        }
        $conn->commit();

        return ['message' => $message];
    } catch (Throwable $e) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        throw $e;
    }
}

function app_shift_cover_manager_decision(PDO $conn, int $coverRequestId, int $managerUserId, string $decision, string $note, string $signatureData = '', bool $useProfileSignature = false): array
{
    $request = app_shift_cover_get_request($conn, $coverRequestId);
    if (!$request) {
        throw new RuntimeException('ไม่พบคำขอแทนเวร');
    }
    app_shift_swap_assert_manager($conn, (int) $request['department_id']);
    if ((string) $request['status'] !== 'pending_manager_approval') {
        throw new RuntimeException('คำขอนี้ไม่ได้อยู่ในขั้นรอหัวหน้าอนุมัติ');
    }
    $source = app_shift_swap_get_assignment($conn, (int) $request['source_assignment_id']);
    if (!$source) {
        throw new RuntimeException('ไม่พบเวรต้นทาง');
    }
    $note = trim($note);

    $conn->beginTransaction();
    try {
        if ($decision === 'reject') {
            if ($note === '') {
                throw new RuntimeException('กรุณาระบุเหตุผลไม่อนุมัติ');
            }
            $stmt = $conn->prepare("
                UPDATE shift_cover_requests
                SET status = 'rejected_by_manager',
                    manager_response_note = ?,
                    manager_rejected_by = ?,
                    manager_rejected_at = NOW(),
                    updated_at = NOW()
                WHERE id = ? AND status = 'pending_manager_approval'
            ");
            $stmt->execute([$note, $managerUserId, $coverRequestId]);
            app_shift_cover_update_document_signature($conn, $coverRequestId, $managerUserId, 'approver', $signatureData, $useProfileSignature, 'approver_rejected', $note);
            app_shift_cover_insert_audit($conn, $coverRequestId, 'manager_reject_cover_request', ['status' => $request['status']], ['status' => 'rejected_by_manager', 'note' => $note], $managerUserId, 'หัวหน้าไม่อนุมัติคำขอแทนเวร');
            app_shift_cover_notify($conn, (int) $request['requester_id'], 'cover_manager_rejected', 'คำขอแทนเวรถูกไม่อนุมัติ', 'หัวหน้าไม่อนุมัติคำขอแทนเวร', $coverRequestId, $managerUserId, ['note' => $note], 'high');
            app_shift_cover_notify($conn, (int) $request['substitute_staff_id'], 'cover_manager_rejected', 'คำขอแทนเวรถูกไม่อนุมัติ', 'หัวหน้าไม่อนุมัติคำขอแทนเวร', $coverRequestId, $managerUserId, ['note' => $note], 'high');
            $conn->commit();

            return ['message' => 'ไม่อนุมัติคำขอแทนเวรแล้ว'];
        }

        app_shift_cover_assert_substitute_available($conn, $source, (int) $request['requester_id'], (int) $request['substitute_staff_id'], $coverRequestId);
        $lockStmt = $conn->prepare('SELECT id, staff_id, assignment_status FROM shift_assignments WHERE id = ? FOR UPDATE');
        $lockStmt->execute([(int) $source['id']]);
        $locked = $lockStmt->fetch(PDO::FETCH_ASSOC);
        if (!$locked || (int) $locked['staff_id'] !== (int) $request['requester_id'] || (string) $locked['assignment_status'] !== 'assigned') {
            throw new RuntimeException('เวรต้นทางเปลี่ยนแปลงระหว่างรออนุมัติ กรุณาสร้างคำขอใหม่');
        }
        $update = $conn->prepare("UPDATE shift_assignments SET staff_id = ?, updated_at = NOW() WHERE id = ? AND staff_id = ? AND assignment_status = 'assigned'");
        $update->execute([(int) $request['substitute_staff_id'], (int) $source['id'], (int) $request['requester_id']]);
        if ($update->rowCount() !== 1) {
            throw new RuntimeException('ไม่สามารถเปลี่ยนผู้รับเวรได้ กรุณาลองใหม่');
        }
        $coverUpdate = $conn->prepare("
            UPDATE shift_cover_requests
            SET status = 'applied',
                manager_response_note = ?,
                manager_approved_by = ?,
                manager_approved_at = NOW(),
                applied_at = NOW(),
                updated_at = NOW()
            WHERE id = ? AND status = 'pending_manager_approval'
        ");
        $coverUpdate->execute([$note !== '' ? $note : null, $managerUserId, $coverRequestId]);
        app_shift_cover_update_document_signature($conn, $coverRequestId, $managerUserId, 'approver', $signatureData, $useProfileSignature, 'complete', $note);
        $summary = [
            'status' => 'applied',
            'source_assignment_id' => (int) $source['id'],
            'old_staff_id' => (int) $request['requester_id'],
            'new_staff_id' => (int) $request['substitute_staff_id'],
        ];
        app_shift_cover_insert_audit($conn, $coverRequestId, 'manager_approve_and_apply_cover', ['status' => $request['status']], $summary, $managerUserId, 'หัวหน้าอนุมัติและระบบเปลี่ยน assignment เป็นผู้แทนเวร');
        app_shift_insert_audit($conn, 'shift_assignments', (int) $source['id'], 'apply_cover_assignment_staff', ['staff_id' => (int) $request['requester_id'], 'cover_request_id' => $coverRequestId], ['staff_id' => (int) $request['substitute_staff_id'], 'cover_request_id' => $coverRequestId], $managerUserId, 'เปลี่ยน staff_id จากระบบแทนเวร');
        app_shift_cover_notify($conn, (int) $request['requester_id'], 'cover_manager_approved', 'คำขอแทนเวรได้รับอนุมัติ', 'คำขอแทนเวรได้รับอนุมัติและปรับตารางเวรแล้ว', $coverRequestId, $managerUserId, $summary, 'high');
        app_shift_cover_notify($conn, (int) $request['substitute_staff_id'], 'cover_manager_approved', 'คำขอแทนเวรได้รับอนุมัติ', 'คุณได้รับมอบหมายให้ปฏิบัติหน้าที่แทนเวรแล้ว', $coverRequestId, $managerUserId, $summary, 'high');
        $conn->commit();

        return ['message' => 'อนุมัติและเปลี่ยนเวรเป็นของผู้แทนแล้ว'];
    } catch (Throwable $e) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        throw $e;
    }
}

function app_shift_cover_page_data(PDO $conn, int $userId): array
{
    if (!app_table_exists($conn, 'shift_cover_requests')) {
        return ['sent' => [], 'incoming' => [], 'manager' => [], 'history' => []];
    }
    $select = "
        SELECT cr.*, requester.fullname AS requester_name, substitute.fullname AS substitute_name, d.department_name
        FROM shift_cover_requests cr
        INNER JOIN users requester ON requester.id = cr.requester_id
        INNER JOIN users substitute ON substitute.id = cr.substitute_staff_id
        LEFT JOIN departments d ON d.id = cr.department_id
    ";
    $sentStmt = $conn->prepare($select . ' WHERE cr.requester_id = ? ORDER BY cr.id DESC LIMIT 80');
    $sentStmt->execute([$userId]);
    $incomingStmt = $conn->prepare($select . " WHERE cr.substitute_staff_id = ? AND cr.status = 'pending_substitute_confirm' ORDER BY cr.id DESC LIMIT 80");
    $incomingStmt->execute([$userId]);

    $managerRows = [];
    $scope = app_shift_access_scope($conn);
    if (($scope['ids'] ?? []) && (app_current_role() === 'admin' || app_current_role() === 'checker' || app_can('can_approve_logs') || app_can('can_manage_time_logs'))) {
        $placeholders = implode(',', array_fill(0, count($scope['ids']), '?'));
        $managerStmt = $conn->prepare($select . " WHERE cr.department_id IN ({$placeholders}) AND cr.status = 'pending_manager_approval' ORDER BY cr.id DESC LIMIT 120");
        $managerStmt->execute($scope['ids']);
        $managerRows = $managerStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    $historyStmt = $conn->prepare($select . " WHERE (cr.requester_id = ? OR cr.substitute_staff_id = ? OR cr.manager_approved_by = ? OR cr.manager_rejected_by = ?) AND cr.status NOT IN ('pending_substitute_confirm', 'pending_manager_approval') ORDER BY cr.id DESC LIMIT 80");
    $historyStmt->execute([$userId, $userId, $userId, $userId]);

    return [
        'sent' => $sentStmt->fetchAll(PDO::FETCH_ASSOC) ?: [],
        'incoming' => $incomingStmt->fetchAll(PDO::FETCH_ASSOC) ?: [],
        'manager' => $managerRows,
        'history' => $historyStmt->fetchAll(PDO::FETCH_ASSOC) ?: [],
    ];
}

function app_shift_cover_get_document(PDO $conn, int $coverRequestId): ?array
{
    if (!app_shift_cover_document_table_ready($conn)) {
        return null;
    }
    $stmt = $conn->prepare('SELECT * FROM shift_cover_documents WHERE cover_request_id = ? LIMIT 1');
    $stmt->execute([$coverRequestId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return $row ?: null;
}

function app_shift_cover_can_view(PDO $conn, array $request, int $userId): bool
{
    if ($userId <= 0) {
        return false;
    }
    if ((int) $request['requester_id'] === $userId || (int) $request['substitute_staff_id'] === $userId || (int) ($request['manager_approved_by'] ?? 0) === $userId || (int) ($request['manager_rejected_by'] ?? 0) === $userId) {
        return true;
    }

    return app_shift_swap_manager_can_access_department($conn, (int) $request['department_id']);
}
