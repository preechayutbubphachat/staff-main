<?php

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/report_helpers.php';
require_once __DIR__ . '/notification_helpers.php';
require_once __DIR__ . '/shift_schedule_service.php';

function app_shift_swap_status_meta(string $status): array
{
    $map = [
        'pending_target_confirm' => ['label' => 'รออีกฝ่ายยืนยัน', 'class' => 'pending-target'],
        'rejected_by_target' => ['label' => 'อีกฝ่ายปฏิเสธ', 'class' => 'rejected'],
        'pending_manager_approval' => ['label' => 'รอหัวหน้าอนุมัติ', 'class' => 'pending-manager'],
        'rejected_by_manager' => ['label' => 'หัวหน้าปฏิเสธ', 'class' => 'rejected'],
        'approved' => ['label' => 'อนุมัติแล้ว', 'class' => 'approved'],
        'applied' => ['label' => 'แลกเวรแล้ว', 'class' => 'applied'],
        'cancelled' => ['label' => 'ยกเลิกแล้ว', 'class' => 'cancelled'],
    ];

    return $map[$status] ?? ['label' => $status, 'class' => 'neutral'];
}

function app_shift_swap_manager_can_access_department(PDO $conn, int $departmentId): bool
{
    if ($departmentId <= 0) {
        return false;
    }
    $role = app_current_role();
    if ($role !== 'admin' && $role !== 'checker' && !app_can('can_approve_logs') && !app_can('can_manage_time_logs')) {
        return false;
    }

    $scope = app_shift_access_scope($conn);
    return in_array($departmentId, $scope['ids'], true);
}

function app_shift_swap_assert_manager(PDO $conn, int $departmentId): void
{
    if (!app_shift_swap_manager_can_access_department($conn, $departmentId)) {
        throw new RuntimeException('คุณไม่มีสิทธิ์อนุมัติคำขอแลกเวรของแผนกนี้');
    }
}

function app_shift_swap_get_assignment(PDO $conn, int $assignmentId): ?array
{
    if ($assignmentId <= 0) {
        return null;
    }

    $stmt = $conn->prepare("
        SELECT
            a.*,
            s.department_id,
            s.schedule_date,
            s.shift_type,
            s.start_time,
            s.end_time,
            s.planned_hours,
            s.status AS schedule_status,
            s.note AS schedule_note,
            u.fullname AS staff_name,
            u.department_id AS staff_department_id,
            d.department_name,
            tl.id AS time_log_id,
            tl.checked_at AS time_log_checked_at
        FROM shift_assignments a
        INNER JOIN shift_schedules s ON s.id = a.schedule_id
        INNER JOIN users u ON u.id = a.staff_id
        LEFT JOIN departments d ON d.id = s.department_id
        LEFT JOIN time_logs tl ON tl.schedule_assignment_id = a.id
        WHERE a.id = ?
        ORDER BY tl.id DESC
        LIMIT 1
    ");
    $stmt->execute([$assignmentId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return $row ?: null;
}

function app_shift_swap_assignment_summary(array $assignment): array
{
    $types = app_shift_schedule_types();
    $shiftType = (string) ($assignment['shift_type'] ?? '');

    return [
        'assignment_id' => (int) ($assignment['id'] ?? $assignment['assignment_id'] ?? 0),
        'schedule_id' => (int) ($assignment['schedule_id'] ?? 0),
        'staff_id' => (int) ($assignment['staff_id'] ?? 0),
        'staff_name' => (string) ($assignment['staff_name'] ?? ''),
        'department_id' => (int) ($assignment['department_id'] ?? 0),
        'department_name' => (string) ($assignment['department_name'] ?? ''),
        'date' => (string) ($assignment['schedule_date'] ?? ''),
        'shift_type' => $shiftType,
        'shift_label' => (string) ($types[$shiftType]['label'] ?? $shiftType),
        'time' => substr((string) ($assignment['start_time'] ?? ''), 0, 5) . '-' . substr((string) ($assignment['end_time'] ?? ''), 0, 5),
        'hours' => (float) ($assignment['planned_hours'] ?? 0),
    ];
}

function app_shift_swap_start_at(array $assignment): DateTimeImmutable
{
    $date = (string) ($assignment['schedule_date'] ?? '');
    $startTime = substr((string) ($assignment['start_time'] ?? '00:00:00'), 0, 8);
    if (strlen($startTime) === 5) {
        $startTime .= ':00';
    }

    return new DateTimeImmutable(trim($date . ' ' . $startTime));
}

function app_shift_swap_assert_assignment_swappable(PDO $conn, array $assignment, ?int $expectedStaffId = null): void
{
    if ($expectedStaffId !== null && (int) $assignment['staff_id'] !== $expectedStaffId) {
        throw new RuntimeException('assignment นี้ไม่ใช่ของผู้ใช้ที่ระบุ');
    }
    if ((string) $assignment['assignment_status'] !== 'assigned') {
        throw new RuntimeException('assignment นี้ไม่พร้อมสำหรับแลกเวร');
    }
    if ((string) $assignment['schedule_status'] !== 'published') {
        throw new RuntimeException('แลกได้เฉพาะเวรที่เผยแพร่แล้วเท่านั้น');
    }
    if (app_shift_swap_start_at($assignment) <= new DateTimeImmutable('now')) {
        throw new RuntimeException('ไม่สามารถแลกเวรย้อนหลังได้');
    }
    if (!empty($assignment['time_log_id'])) {
        throw new RuntimeException('assignment นี้มีรายการลงเวรจริงแล้ว จึงไม่สามารถแลกเวรได้');
    }
}

function app_shift_swap_has_active_request(PDO $conn, array $assignmentIds, ?int $excludeSwapId = null): bool
{
    $ids = array_values(array_unique(array_filter(array_map('intval', $assignmentIds), static fn(int $id): bool => $id > 0)));
    if (!$ids) {
        return false;
    }

    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $sql = "
        SELECT id
        FROM shift_swap_requests
        WHERE status IN ('pending_target_confirm', 'pending_manager_approval', 'approved')
          AND (
            requester_assignment_id IN ({$placeholders})
            OR target_assignment_id IN ({$placeholders})
          )
    ";
    $params = [...$ids, ...$ids];
    if ($excludeSwapId !== null) {
        $sql .= ' AND id <> ?';
        $params[] = $excludeSwapId;
    }
    $sql .= ' LIMIT 1';
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);

    return (bool) $stmt->fetchColumn();
}

function app_shift_swap_find_assignment_overlap(PDO $conn, int $staffId, array $incomingAssignment, array $excludeAssignmentIds = []): ?array
{
    $excludeAssignmentIds = array_values(array_unique(array_filter(array_map('intval', $excludeAssignmentIds), static fn(int $id): bool => $id > 0)));
    $sql = "
        SELECT a.id AS assignment_id, s.id AS schedule_id, s.shift_type, s.schedule_date, s.start_time, s.end_time
        FROM shift_assignments a
        INNER JOIN shift_schedules s ON s.id = a.schedule_id
        LEFT JOIN time_logs tl ON tl.schedule_assignment_id = a.id
        WHERE a.staff_id = ?
          AND a.assignment_status = 'assigned'
          AND s.status <> 'cancelled'
          AND s.schedule_date = ?
          AND tl.id IS NULL
    ";
    $params = [$staffId, (string) $incomingAssignment['schedule_date']];
    if ($excludeAssignmentIds) {
        $sql .= ' AND a.id NOT IN (' . implode(',', array_fill(0, count($excludeAssignmentIds), '?')) . ')';
        $params = [...$params, ...$excludeAssignmentIds];
    }
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    foreach (($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []) as $row) {
        if (app_shift_ranges_overlap(
            (string) $incomingAssignment['schedule_date'],
            (string) $incomingAssignment['start_time'],
            (string) $incomingAssignment['end_time'],
            (string) $row['start_time'],
            (string) $row['end_time']
        )) {
            return $row;
        }
    }

    return null;
}

function app_shift_swap_revalidate_pair(PDO $conn, array $requesterAssignment, array $targetAssignment, ?int $excludeSwapId = null): void
{
    app_shift_swap_assert_assignment_swappable($conn, $requesterAssignment);
    app_shift_swap_assert_assignment_swappable($conn, $targetAssignment);
    if ((int) $requesterAssignment['id'] === (int) $targetAssignment['id']) {
        throw new RuntimeException('ไม่สามารถแลก assignment เดียวกันได้');
    }
    if ((int) $requesterAssignment['staff_id'] === (int) $targetAssignment['staff_id']) {
        throw new RuntimeException('ต้องเลือกเจ้าหน้าที่ปลายทางคนละคน');
    }
    if ((int) $requesterAssignment['department_id'] !== (int) $targetAssignment['department_id']) {
        throw new RuntimeException('เฟสนี้รองรับการแลกเวรในแผนกเดียวกันเท่านั้น');
    }
    if (app_shift_swap_has_active_request($conn, [(int) $requesterAssignment['id'], (int) $targetAssignment['id']], $excludeSwapId)) {
        throw new RuntimeException('มีคำขอแลกเวรที่กำลังดำเนินการสำหรับ assignment นี้แล้ว');
    }

    $requesterGetsTarget = app_shift_swap_find_assignment_overlap(
        $conn,
        (int) $requesterAssignment['staff_id'],
        $targetAssignment,
        [(int) $requesterAssignment['id'], (int) $targetAssignment['id']]
    );
    if ($requesterGetsTarget) {
        throw new RuntimeException('ผู้ขอจะมีเวรชนกันหลังแลก กรุณาเลือกเวรอื่น');
    }

    $targetGetsRequester = app_shift_swap_find_assignment_overlap(
        $conn,
        (int) $targetAssignment['staff_id'],
        $requesterAssignment,
        [(int) $requesterAssignment['id'], (int) $targetAssignment['id']]
    );
    if ($targetGetsRequester) {
        throw new RuntimeException('เจ้าหน้าที่ปลายทางจะมีเวรชนกันหลังแลก กรุณาเลือกเวรอื่น');
    }
}

function app_shift_swap_insert_audit(PDO $conn, int $swapRequestId, string $actionType, ?array $oldValues, ?array $newValues, int $actorUserId, ?string $note = null): void
{
    app_shift_insert_audit($conn, 'shift_swap_requests', $swapRequestId, $actionType, $oldValues, $newValues, $actorUserId, $note);
}

function app_shift_swap_notify(PDO $conn, int $userId, string $type, string $title, string $message, int $swapRequestId, int $actorUserId, array $metadata = [], string $priority = 'normal'): void
{
    if ($userId <= 0 || !app_notifications_available($conn)) {
        return;
    }

    app_create_notification($conn, [
        'user_id' => $userId,
        'type' => $type,
        'title' => $title,
        'message' => $message,
        'target_url' => 'shift-swap-requests.php?highlight=' . $swapRequestId,
        'target_entity_type' => 'shift_swap_request',
        'target_entity_id' => $swapRequestId,
        'metadata' => ['swap_request_id' => $swapRequestId] + $metadata,
        'source_type' => 'workflow',
        'actor_user_id' => $actorUserId,
        'priority' => $priority,
    ]);
}

function app_shift_swap_manager_recipients(PDO $conn, int $departmentId): array
{
    $stmt = $conn->prepare("
        SELECT id
        FROM users
        WHERE COALESCE(is_active, 1) = 1
          AND (
            role = 'admin'
            OR can_approve_logs = 1
            OR can_manage_time_logs = 1
          )
          AND (role = 'admin' OR department_id = ?)
        ORDER BY id ASC
    ");
    $stmt->execute([$departmentId]);
    return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN) ?: []);
}

function app_shift_swap_create_request(PDO $conn, int $requesterId, int $requesterAssignmentId, int $targetAssignmentId, string $reason): array
{
    $requesterAssignment = app_shift_swap_get_assignment($conn, $requesterAssignmentId);
    $targetAssignment = app_shift_swap_get_assignment($conn, $targetAssignmentId);
    if (!$requesterAssignment || !$targetAssignment) {
        throw new RuntimeException('ไม่พบเวรที่ต้องการแลก');
    }
    app_shift_swap_assert_assignment_swappable($conn, $requesterAssignment, $requesterId);
    app_shift_swap_revalidate_pair($conn, $requesterAssignment, $targetAssignment);

    $reason = trim($reason);
    if ($reason === '') {
        throw new RuntimeException('กรุณาระบุเหตุผลการแลกเวร');
    }

    $conn->beginTransaction();
    try {
        if (app_shift_swap_has_active_request($conn, [$requesterAssignmentId, $targetAssignmentId])) {
            throw new RuntimeException('มีคำขอแลกเวรที่กำลังดำเนินการสำหรับ assignment นี้แล้ว');
        }

        $stmt = $conn->prepare("
            INSERT INTO shift_swap_requests (
                requester_id, target_staff_id, requester_assignment_id, target_assignment_id,
                department_id, swap_type, reason, status
            ) VALUES (?, ?, ?, ?, ?, 'swap_with_person', ?, 'pending_target_confirm')
        ");
        $stmt->execute([
            $requesterId,
            (int) $targetAssignment['staff_id'],
            $requesterAssignmentId,
            $targetAssignmentId,
            (int) $requesterAssignment['department_id'],
            $reason,
        ]);
        $swapRequestId = (int) $conn->lastInsertId();
        $newValues = [
            'status' => 'pending_target_confirm',
            'requester_assignment' => app_shift_swap_assignment_summary($requesterAssignment),
            'target_assignment' => app_shift_swap_assignment_summary($targetAssignment),
            'reason' => $reason,
        ];
        app_shift_swap_insert_audit($conn, $swapRequestId, 'create_swap_request', null, $newValues, $requesterId, 'สร้างคำขอแลกเวร');
        app_shift_swap_notify(
            $conn,
            (int) $targetAssignment['staff_id'],
            'swap_request_created',
            'มีคำขอแลกเวรใหม่',
            'มีคำขอแลกเวรจาก ' . (string) $requesterAssignment['staff_name'],
            $swapRequestId,
            $requesterId,
            $newValues,
            'high'
        );
        $conn->commit();

        return ['swap_request_id' => $swapRequestId, 'message' => 'ส่งคำขอแลกเวรเรียบร้อยแล้ว รออีกฝ่ายยืนยัน'];
    } catch (Throwable $e) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        throw $e;
    }
}

function app_shift_swap_get_request(PDO $conn, int $swapRequestId): ?array
{
    $stmt = $conn->prepare("
        SELECT
            sr.*,
            requester.fullname AS requester_name,
            target.fullname AS target_name,
            d.department_name,
            ra.schedule_id AS requester_schedule_id,
            rs.schedule_date AS requester_date,
            rs.shift_type AS requester_shift_type,
            rs.start_time AS requester_start_time,
            rs.end_time AS requester_end_time,
            rs.planned_hours AS requester_hours,
            ta.schedule_id AS target_schedule_id,
            ts.schedule_date AS target_date,
            ts.shift_type AS target_shift_type,
            ts.start_time AS target_start_time,
            ts.end_time AS target_end_time,
            ts.planned_hours AS target_hours
        FROM shift_swap_requests sr
        INNER JOIN users requester ON requester.id = sr.requester_id
        INNER JOIN users target ON target.id = sr.target_staff_id
        LEFT JOIN departments d ON d.id = sr.department_id
        INNER JOIN shift_assignments ra ON ra.id = sr.requester_assignment_id
        INNER JOIN shift_schedules rs ON rs.id = ra.schedule_id
        INNER JOIN shift_assignments ta ON ta.id = sr.target_assignment_id
        INNER JOIN shift_schedules ts ON ts.id = ta.schedule_id
        WHERE sr.id = ?
        LIMIT 1
    ");
    $stmt->execute([$swapRequestId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return $row ?: null;
}

function app_shift_swap_update_target_response(PDO $conn, int $swapRequestId, int $targetUserId, string $decision, string $note): array
{
    $request = app_shift_swap_get_request($conn, $swapRequestId);
    if (!$request) {
        throw new RuntimeException('ไม่พบคำขอแลกเวร');
    }
    if ((int) $request['target_staff_id'] !== $targetUserId) {
        throw new RuntimeException('คุณไม่มีสิทธิ์ตอบคำขอนี้');
    }
    if ((string) $request['status'] !== 'pending_target_confirm') {
        throw new RuntimeException('คำขอนี้ไม่ได้อยู่ในขั้นรออีกฝ่ายยืนยัน');
    }

    $note = trim($note);
    $old = $request;
    $conn->beginTransaction();
    try {
        if ($decision === 'confirm') {
            $stmt = $conn->prepare("
                UPDATE shift_swap_requests
                SET status = 'pending_manager_approval',
                    target_response_note = ?,
                    target_confirmed_at = NOW(),
                    updated_at = NOW()
                WHERE id = ? AND status = 'pending_target_confirm' AND target_staff_id = ?
            ");
            $stmt->execute([$note !== '' ? $note : null, $swapRequestId, $targetUserId]);
            if ($stmt->rowCount() !== 1) {
                throw new RuntimeException('ไม่สามารถยืนยันคำขอซ้ำได้');
            }
            $newStatus = 'pending_manager_approval';
            app_shift_swap_insert_audit($conn, $swapRequestId, 'target_confirm_swap_request', ['status' => $old['status']], ['status' => $newStatus, 'note' => $note], $targetUserId, 'อีกฝ่ายยืนยันคำขอแลกเวร');
            foreach (app_shift_swap_manager_recipients($conn, (int) $request['department_id']) as $managerId) {
                app_shift_swap_notify($conn, $managerId, 'swap_target_confirmed', 'มีคำขอแลกเวรรออนุมัติ', 'มีคำขอแลกเวรรอหัวหน้าอนุมัติ', $swapRequestId, $targetUserId, ['department_id' => (int) $request['department_id']], 'high');
            }
            $message = 'ยืนยันคำขอแลกเวรแล้ว รอหัวหน้าอนุมัติ';
        } else {
            $stmt = $conn->prepare("
                UPDATE shift_swap_requests
                SET status = 'rejected_by_target',
                    target_response_note = ?,
                    target_rejected_at = NOW(),
                    updated_at = NOW()
                WHERE id = ? AND status = 'pending_target_confirm' AND target_staff_id = ?
            ");
            $stmt->execute([$note !== '' ? $note : null, $swapRequestId, $targetUserId]);
            if ($stmt->rowCount() !== 1) {
                throw new RuntimeException('ไม่สามารถปฏิเสธคำขอซ้ำได้');
            }
            $newStatus = 'rejected_by_target';
            app_shift_swap_insert_audit($conn, $swapRequestId, 'target_reject_swap_request', ['status' => $old['status']], ['status' => $newStatus, 'note' => $note], $targetUserId, 'อีกฝ่ายปฏิเสธคำขอแลกเวร');
            app_shift_swap_notify($conn, (int) $request['requester_id'], 'swap_target_rejected', 'คำขอแลกเวรถูกปฏิเสธ', 'คำขอแลกเวรถูกปฏิเสธโดยอีกฝ่าย', $swapRequestId, $targetUserId, ['note' => $note], 'high');
            $message = 'ปฏิเสธคำขอแลกเวรแล้ว';
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

function app_shift_swap_cancel_request(PDO $conn, int $swapRequestId, int $requesterId): array
{
    $request = app_shift_swap_get_request($conn, $swapRequestId);
    if (!$request) {
        throw new RuntimeException('ไม่พบคำขอแลกเวร');
    }
    if ((int) $request['requester_id'] !== $requesterId) {
        throw new RuntimeException('ยกเลิกได้เฉพาะคำขอของตัวเอง');
    }
    if ((string) $request['status'] !== 'pending_target_confirm') {
        throw new RuntimeException('ยกเลิกได้เฉพาะคำขอที่ยังรออีกฝ่ายยืนยัน');
    }

    $stmt = $conn->prepare("
        UPDATE shift_swap_requests
        SET status = 'cancelled', cancelled_by = ?, cancelled_at = NOW(), updated_at = NOW()
        WHERE id = ? AND status = 'pending_target_confirm' AND requester_id = ?
    ");
    $stmt->execute([$requesterId, $swapRequestId, $requesterId]);
    if ($stmt->rowCount() !== 1) {
        throw new RuntimeException('ไม่สามารถยกเลิกคำขอซ้ำได้');
    }
    app_shift_swap_insert_audit($conn, $swapRequestId, 'cancel_swap_request', ['status' => $request['status']], ['status' => 'cancelled'], $requesterId, 'ผู้ขอยกเลิกคำขอแลกเวร');
    app_shift_swap_notify($conn, (int) $request['target_staff_id'], 'swap_cancelled', 'คำขอแลกเวรถูกยกเลิก', 'คำขอแลกเวรถูกยกเลิกโดยผู้ขอ', $swapRequestId, $requesterId);

    return ['message' => 'ยกเลิกคำขอแลกเวรเรียบร้อยแล้ว'];
}

function app_shift_swap_manager_decision(PDO $conn, int $swapRequestId, int $managerUserId, string $decision, string $note): array
{
    $request = app_shift_swap_get_request($conn, $swapRequestId);
    if (!$request) {
        throw new RuntimeException('ไม่พบคำขอแลกเวร');
    }
    app_shift_swap_assert_manager($conn, (int) $request['department_id']);
    if ((string) $request['status'] !== 'pending_manager_approval') {
        throw new RuntimeException('คำขอนี้ไม่ได้อยู่ในขั้นรอหัวหน้าอนุมัติ');
    }

    $note = trim($note);
    if ($decision === 'reject') {
        $stmt = $conn->prepare("
            UPDATE shift_swap_requests
            SET status = 'rejected_by_manager',
                manager_response_note = ?,
                manager_rejected_by = ?,
                manager_rejected_at = NOW(),
                updated_at = NOW()
            WHERE id = ? AND status = 'pending_manager_approval'
        ");
        $stmt->execute([$note !== '' ? $note : null, $managerUserId, $swapRequestId]);
        if ($stmt->rowCount() !== 1) {
            throw new RuntimeException('ไม่สามารถปฏิเสธคำขอซ้ำได้');
        }
        app_shift_swap_insert_audit($conn, $swapRequestId, 'manager_reject_swap_request', ['status' => $request['status']], ['status' => 'rejected_by_manager', 'note' => $note], $managerUserId, 'หัวหน้าปฏิเสธคำขอแลกเวร');
        app_shift_swap_notify($conn, (int) $request['requester_id'], 'swap_manager_rejected', 'คำขอแลกเวรถูกปฏิเสธโดยหัวหน้า', 'คำขอแลกเวรถูกปฏิเสธโดยหัวหน้า', $swapRequestId, $managerUserId, ['note' => $note], 'high');
        app_shift_swap_notify($conn, (int) $request['target_staff_id'], 'swap_manager_rejected', 'คำขอแลกเวรถูกปฏิเสธโดยหัวหน้า', 'คำขอแลกเวรถูกปฏิเสธโดยหัวหน้า', $swapRequestId, $managerUserId, ['note' => $note], 'high');
        return ['message' => 'ปฏิเสธคำขอแลกเวรแล้ว'];
    }

    return app_shift_swap_apply($conn, $swapRequestId, $managerUserId, $note);
}

function app_shift_swap_apply(PDO $conn, int $swapRequestId, int $managerUserId, string $note = ''): array
{
    $conn->beginTransaction();
    try {
        $request = app_shift_swap_get_request($conn, $swapRequestId);
        if (!$request || (string) $request['status'] !== 'pending_manager_approval') {
            throw new RuntimeException('คำขอนี้ไม่พร้อมสำหรับอนุมัติหรือถูกดำเนินการแล้ว');
        }
        app_shift_swap_assert_manager($conn, (int) $request['department_id']);

        $requesterAssignment = app_shift_swap_get_assignment($conn, (int) $request['requester_assignment_id']);
        $targetAssignment = app_shift_swap_get_assignment($conn, (int) $request['target_assignment_id']);
        if (!$requesterAssignment || !$targetAssignment) {
            throw new RuntimeException('ไม่พบ assignment สำหรับ apply swap');
        }
        app_shift_swap_revalidate_pair($conn, $requesterAssignment, $targetAssignment, $swapRequestId);

        $requesterStaffId = (int) $requesterAssignment['staff_id'];
        $targetStaffId = (int) $targetAssignment['staff_id'];
        if ($requesterStaffId !== (int) $request['requester_id'] || $targetStaffId !== (int) $request['target_staff_id']) {
            throw new RuntimeException('เจ้าของ assignment เปลี่ยนไประหว่างรออนุมัติ กรุณาสร้างคำขอใหม่');
        }

        $updateA = $conn->prepare('UPDATE shift_assignments SET staff_id = ?, updated_at = NOW() WHERE id = ? AND staff_id = ? AND assignment_status = "assigned"');
        $updateA->execute([$targetStaffId, (int) $requesterAssignment['id'], $requesterStaffId]);
        $updateB = $conn->prepare('UPDATE shift_assignments SET staff_id = ?, updated_at = NOW() WHERE id = ? AND staff_id = ? AND assignment_status = "assigned"');
        $updateB->execute([$requesterStaffId, (int) $targetAssignment['id'], $targetStaffId]);
        if ($updateA->rowCount() !== 1 || $updateB->rowCount() !== 1) {
            throw new RuntimeException('ไม่สามารถสลับ assignment ได้ครบถ้วน จึงยกเลิกการทำรายการ');
        }

        $swapUpdate = $conn->prepare("
            UPDATE shift_swap_requests
            SET status = 'applied',
                manager_response_note = ?,
                manager_approved_by = ?,
                manager_approved_at = NOW(),
                applied_at = NOW(),
                updated_at = NOW()
            WHERE id = ? AND status = 'pending_manager_approval'
        ");
        $swapUpdate->execute([$note !== '' ? $note : null, $managerUserId, $swapRequestId]);
        if ($swapUpdate->rowCount() !== 1) {
            throw new RuntimeException('คำขอนี้ถูกอนุมัติหรือเปลี่ยนสถานะแล้ว');
        }

        $applySummary = [
            'status' => 'applied',
            'assignment_a' => [
                'assignment_id' => (int) $requesterAssignment['id'],
                'old_staff_id' => $requesterStaffId,
                'new_staff_id' => $targetStaffId,
            ],
            'assignment_b' => [
                'assignment_id' => (int) $targetAssignment['id'],
                'old_staff_id' => $targetStaffId,
                'new_staff_id' => $requesterStaffId,
            ],
        ];
        app_shift_swap_insert_audit($conn, $swapRequestId, 'manager_approve_and_apply_swap', ['status' => $request['status']], $applySummary, $managerUserId, 'หัวหน้าอนุมัติและระบบสลับ assignment แล้ว');
        app_shift_insert_audit($conn, 'shift_assignments', (int) $requesterAssignment['id'], 'apply_swap_assignment_staff', ['staff_id' => $requesterStaffId, 'swap_request_id' => $swapRequestId], ['staff_id' => $targetStaffId, 'swap_request_id' => $swapRequestId], $managerUserId, 'สลับ staff_id จากระบบแลกเวร');
        app_shift_insert_audit($conn, 'shift_assignments', (int) $targetAssignment['id'], 'apply_swap_assignment_staff', ['staff_id' => $targetStaffId, 'swap_request_id' => $swapRequestId], ['staff_id' => $requesterStaffId, 'swap_request_id' => $swapRequestId], $managerUserId, 'สลับ staff_id จากระบบแลกเวร');
        app_shift_swap_notify($conn, (int) $request['requester_id'], 'swap_manager_approved', 'คำขอแลกเวรได้รับอนุมัติ', 'คำขอแลกเวรได้รับอนุมัติและปรับตารางเวรแล้ว', $swapRequestId, $managerUserId, $applySummary, 'high');
        app_shift_swap_notify($conn, (int) $request['target_staff_id'], 'swap_manager_approved', 'คำขอแลกเวรได้รับอนุมัติ', 'คำขอแลกเวรได้รับอนุมัติและปรับตารางเวรแล้ว', $swapRequestId, $managerUserId, $applySummary, 'high');
        $conn->commit();

        return ['message' => 'อนุมัติและสลับเวรเรียบร้อยแล้ว'];
    } catch (Throwable $e) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        throw $e;
    }
}

function app_shift_swap_available_target_assignments(PDO $conn, int $requesterId, int $requesterAssignmentId): array
{
    $requesterAssignment = app_shift_swap_get_assignment($conn, $requesterAssignmentId);
    if (!$requesterAssignment) {
        return [];
    }
    try {
        app_shift_swap_assert_assignment_swappable($conn, $requesterAssignment, $requesterId);
    } catch (Throwable) {
        return [];
    }

    $stmt = $conn->prepare("
        SELECT
            a.id AS assignment_id,
            a.staff_id,
            u.fullname AS staff_name,
            u.position_name,
            s.schedule_date,
            s.shift_type,
            s.start_time,
            s.end_time,
            s.planned_hours,
            d.department_name
        FROM shift_assignments a
        INNER JOIN shift_schedules s ON s.id = a.schedule_id
        INNER JOIN users u ON u.id = a.staff_id
        LEFT JOIN departments d ON d.id = s.department_id
        LEFT JOIN time_logs tl ON tl.schedule_assignment_id = a.id
        WHERE a.assignment_status = 'assigned'
          AND a.staff_id <> ?
          AND s.department_id = ?
          AND s.status = 'published'
          AND s.schedule_date >= CURDATE()
          AND tl.id IS NULL
        ORDER BY u.fullname ASC, s.schedule_date ASC, s.start_time ASC
        LIMIT 250
    ");
    $stmt->execute([$requesterId, (int) $requesterAssignment['department_id']]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    return array_values(array_filter($rows, static function (array $row) use ($conn, $requesterAssignment): bool {
        $target = app_shift_swap_get_assignment($conn, (int) $row['assignment_id']);
        if (!$target) {
            return false;
        }
        try {
            app_shift_swap_revalidate_pair($conn, $requesterAssignment, $target);
            return true;
        } catch (Throwable) {
            return false;
        }
    }));
}

function app_shift_swap_can_request_for_assignment(PDO $conn, int $currentUserId, int $assignmentId): bool
{
    $assignment = app_shift_swap_get_assignment($conn, $assignmentId);
    if (!$assignment) {
        return false;
    }
    try {
        app_shift_swap_assert_assignment_swappable($conn, $assignment, $currentUserId);
    } catch (Throwable) {
        return false;
    }

    return !app_shift_swap_has_active_request($conn, [$assignmentId]);
}

function app_shift_swap_page_data(PDO $conn, int $userId): array
{
    $sentStmt = $conn->prepare("
        SELECT sr.*, requester.fullname AS requester_name, target.fullname AS target_name, d.department_name
        FROM shift_swap_requests sr
        INNER JOIN users requester ON requester.id = sr.requester_id
        INNER JOIN users target ON target.id = sr.target_staff_id
        LEFT JOIN departments d ON d.id = sr.department_id
        WHERE sr.requester_id = ?
        ORDER BY sr.id DESC
        LIMIT 80
    ");
    $sentStmt->execute([$userId]);

    $incomingStmt = $conn->prepare("
        SELECT sr.*, requester.fullname AS requester_name, target.fullname AS target_name, d.department_name
        FROM shift_swap_requests sr
        INNER JOIN users requester ON requester.id = sr.requester_id
        INNER JOIN users target ON target.id = sr.target_staff_id
        LEFT JOIN departments d ON d.id = sr.department_id
        WHERE sr.target_staff_id = ?
          AND sr.status = 'pending_target_confirm'
        ORDER BY sr.id DESC
        LIMIT 80
    ");
    $incomingStmt->execute([$userId]);

    $managerRows = [];
    $scope = app_shift_access_scope($conn);
    if (($scope['ids'] ?? []) && (app_current_role() === 'admin' || app_current_role() === 'checker' || app_can('can_approve_logs') || app_can('can_manage_time_logs'))) {
        $placeholders = implode(',', array_fill(0, count($scope['ids']), '?'));
        $managerStmt = $conn->prepare("
            SELECT sr.*, requester.fullname AS requester_name, target.fullname AS target_name, d.department_name
            FROM shift_swap_requests sr
            INNER JOIN users requester ON requester.id = sr.requester_id
            INNER JOIN users target ON target.id = sr.target_staff_id
            LEFT JOIN departments d ON d.id = sr.department_id
            WHERE sr.status = 'pending_manager_approval'
              AND sr.department_id IN ({$placeholders})
            ORDER BY sr.target_confirmed_at ASC, sr.id ASC
            LIMIT 120
        ");
        $managerStmt->execute($scope['ids']);
        $managerRows = $managerStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    return [
        'sent' => $sentStmt->fetchAll(PDO::FETCH_ASSOC) ?: [],
        'incoming' => $incomingStmt->fetchAll(PDO::FETCH_ASSOC) ?: [],
        'manager' => $managerRows,
    ];
}
