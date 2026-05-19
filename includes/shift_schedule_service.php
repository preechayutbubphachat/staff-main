<?php

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/report_helpers.php';
require_once __DIR__ . '/notification_helpers.php';

function app_shift_schedule_types(): array
{
    return [
        'morning' => ['label' => 'เช้า', 'start' => '08:30', 'end' => '16:30'],
        'evening' => ['label' => 'บ่าย', 'start' => '16:30', 'end' => '00:30'],
        'night' => ['label' => 'ดึก', 'start' => '00:30', 'end' => '08:30'],
        'custom' => ['label' => 'กำหนดเอง', 'start' => '08:30', 'end' => '16:30'],
    ];
}

function app_shift_access_scope(PDO $conn): array
{
    $departments = app_fetch_departments($conn);
    $role = app_current_role();
    if ($role === 'admin' || app_can('can_manage_user_permissions')) {
        return [
            'departments' => $departments,
            'ids' => array_map(static function (array $department): int {
                return (int) $department['id'];
            }, $departments),
            'is_global' => true,
        ];
    }

    $departmentId = app_get_current_user_department_id($conn);
    $filtered = array_values(array_filter(
        $departments,
        static function (array $department) use ($departmentId): bool {
            return (int) $department['id'] === $departmentId;
        }
    ));

    return [
        'departments' => $filtered,
        'ids' => $departmentId > 0 ? [$departmentId] : [],
        'is_global' => false,
    ];
}

function app_shift_assert_department_access(PDO $conn, int $departmentId): void
{
    if (!app_can_manage_shift_schedules()) {
        throw new RuntimeException('คุณไม่มีสิทธิ์จัดตารางเวร');
    }

    $scope = app_shift_access_scope($conn);
    if ($departmentId <= 0 || !in_array($departmentId, $scope['ids'], true)) {
        throw new RuntimeException('คุณไม่มีสิทธิ์จัดตารางเวรของแผนกนี้');
    }
}

function app_shift_normalize_time(string $time): string
{
    $time = trim($time);
    if (!preg_match('/^\d{2}:\d{2}$/', $time)) {
        throw new RuntimeException('รูปแบบเวลาไม่ถูกต้อง');
    }

    [$hour, $minute] = array_map('intval', explode(':', $time));
    if ($hour < 0 || $hour > 23 || $minute < 0 || $minute > 59) {
        throw new RuntimeException('ช่วงเวลาไม่ถูกต้อง');
    }

    return sprintf('%02d:%02d:00', $hour, $minute);
}

function app_shift_planned_hours(string $startTime, string $endTime): float
{
    $start = DateTimeImmutable::createFromFormat('H:i:s', $startTime);
    $end = DateTimeImmutable::createFromFormat('H:i:s', $endTime);
    if (!$start || !$end) {
        throw new RuntimeException('คำนวณชั่วโมงเวรไม่ได้');
    }

    if ($end <= $start) {
        $end = $end->modify('+1 day');
    }

    return round(($end->getTimestamp() - $start->getTimestamp()) / 3600, 2);
}

function app_shift_slot_bounds(string $date, string $startTime, string $endTime): array
{
    $start = new DateTimeImmutable($date . ' ' . $startTime);
    $end = new DateTimeImmutable($date . ' ' . $endTime);
    if ($end <= $start) {
        $end = $end->modify('+1 day');
    }

    return [$start, $end];
}

function app_shift_ranges_overlap(string $date, string $startA, string $endA, string $startB, string $endB): bool
{
    [$aStart, $aEnd] = app_shift_slot_bounds($date, $startA, $endA);
    [$bStart, $bEnd] = app_shift_slot_bounds($date, $startB, $endB);

    return $aStart < $bEnd && $bStart < $aEnd;
}

function app_shift_month_bounds(int $month, int $year): array
{
    if ($month < 1 || $month > 12 || $year < 2000 || $year > 2100) {
        throw new RuntimeException('เดือนหรือปีไม่ถูกต้อง');
    }

    $start = sprintf('%04d-%02d-01', $year, $month);
    $end = (new DateTimeImmutable($start))->modify('last day of this month')->format('Y-m-d');

    return [$start, $end];
}

function app_shift_fetch_staff(PDO $conn, int $departmentId): array
{
    $profileImageSelect = app_column_exists($conn, 'users', 'profile_image_path')
        ? 'u.profile_image_path'
        : 'NULL AS profile_image_path';
    $stmt = $conn->prepare("
        SELECT u.id, u.fullname, u.position_name, u.role, u.department_id, {$profileImageSelect}, d.department_name
        FROM users u
        LEFT JOIN departments d ON d.id = u.department_id
        WHERE u.department_id = ?
          AND COALESCE(u.is_active, 1) = 1
        ORDER BY u.fullname ASC, u.id ASC
    ");
    $stmt->execute([$departmentId]);

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    foreach ($rows as &$row) {
        $name = (string) ($row['fullname'] ?? '');
        $row['profile_image_url'] = app_resolve_user_image_url($row['profile_image_path'] ?? '');
        $row['initials'] = app_shift_staff_initials($name);
    }
    unset($row);

    return $rows;
}

function app_shift_staff_initials(string $name): string
{
    $name = trim(preg_replace('/\s+/u', ' ', $name) ?? '');
    if ($name === '') {
        return 'U';
    }

    $parts = preg_split('/\s+/u', $name) ?: [];
    $initials = '';
    foreach ($parts as $part) {
        $part = trim($part);
        if ($part === '') {
            continue;
        }
        $initials .= mb_substr($part, 0, 1, 'UTF-8');
        if (mb_strlen($initials, 'UTF-8') >= 2) {
            break;
        }
    }

    return $initials !== '' ? $initials : mb_substr($name, 0, 1, 'UTF-8');
}

function app_shift_validate_department(PDO $conn, int $departmentId): void
{
    $stmt = $conn->prepare('SELECT id FROM departments WHERE id = ? LIMIT 1');
    $stmt->execute([$departmentId]);
    if (!$stmt->fetchColumn()) {
        throw new RuntimeException('ไม่พบแผนกที่เลือก');
    }
}

function app_shift_validate_staff(PDO $conn, int $departmentId, array $staffIds): array
{
    $staffIds = array_values(array_unique(array_filter(array_map('intval', $staffIds), static function (int $id): bool {
        return $id > 0;
    })));
    if (!$staffIds) {
        throw new RuntimeException('กรุณาเลือกเจ้าหน้าที่อย่างน้อย 1 คน');
    }

    $placeholders = implode(',', array_fill(0, count($staffIds), '?'));
    $stmt = $conn->prepare("
        SELECT id, fullname, department_id
        FROM users
        WHERE id IN ({$placeholders})
          AND department_id = ?
          AND COALESCE(is_active, 1) = 1
    ");
    $stmt->execute(array_merge($staffIds, [$departmentId]));
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    if (count($rows) !== count($staffIds)) {
        throw new RuntimeException('มีเจ้าหน้าที่ที่ไม่อยู่ในแผนกนี้ หรือไม่มีสิทธิ์จัดเวรให้');
    }

    return $staffIds;
}

function app_shift_actor_name(PDO $conn, int $actorUserId): string
{
    $stmt = $conn->prepare('SELECT fullname FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([$actorUserId]);
    $name = trim((string) $stmt->fetchColumn());

    return $name !== '' ? $name : (string) ($_SESSION['fullname'] ?? 'System');
}

function app_shift_insert_audit(PDO $conn, string $tableName, $rowPrimaryKey, string $actionType, ?array $oldValues, ?array $newValues, int $actorUserId, ?string $note = null): void
{
    if (!app_table_exists($conn, 'db_admin_audit_logs')) {
        return;
    }

    $stmt = $conn->prepare("
        INSERT INTO db_admin_audit_logs (
            table_name,
            row_primary_key,
            action_type,
            old_values_json,
            new_values_json,
            actor_user_id,
            actor_name_snapshot,
            note,
            request_context
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $tableName,
        (string) $rowPrimaryKey,
        $actionType,
        $oldValues !== null ? json_encode($oldValues, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
        $newValues !== null ? json_encode($newValues, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
        $actorUserId,
        app_shift_actor_name($conn, $actorUserId),
        $note,
        json_encode([
            'source' => 'shift_schedule_phase1',
            'method' => $_SERVER['REQUEST_METHOD'] ?? 'CLI',
            'uri' => $_SERVER['REQUEST_URI'] ?? '',
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
    ]);
}

function app_get_monthly_schedules(PDO $conn, int $departmentId, int $month, int $year): array
{
    [$startDate, $endDate] = app_shift_month_bounds($month, $year);
    app_shift_assert_department_access($conn, $departmentId);
    $profileImageSelect = app_column_exists($conn, 'users', 'profile_image_path')
        ? 'u.profile_image_path AS staff_profile_image_path'
        : 'NULL AS staff_profile_image_path';

    $stmt = $conn->prepare("
        SELECT
            s.*,
            d.department_name,
            a.id AS assignment_id,
            a.staff_id,
            a.assignment_status,
            u.fullname AS staff_name,
            u.position_name AS staff_position,
            {$profileImageSelect}
        FROM shift_schedules s
        LEFT JOIN departments d ON d.id = s.department_id
        LEFT JOIN shift_assignments a ON a.schedule_id = s.id
        LEFT JOIN users u ON u.id = a.staff_id
        WHERE s.department_id = ?
          AND s.schedule_date BETWEEN ? AND ?
          AND s.status <> 'cancelled'
        ORDER BY s.schedule_date ASC, s.start_time ASC, s.id ASC, u.fullname ASC
    ");
    $stmt->execute([$departmentId, $startDate, $endDate]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $schedules = [];
    foreach ($rows as $row) {
        $scheduleId = (int) $row['id'];
        if (!isset($schedules[$scheduleId])) {
            $schedules[$scheduleId] = [
                'id' => $scheduleId,
                'department_id' => (int) $row['department_id'],
                'department_name' => (string) ($row['department_name'] ?? ''),
                'schedule_date' => (string) $row['schedule_date'],
                'shift_type' => (string) $row['shift_type'],
                'start_time' => substr((string) $row['start_time'], 0, 5),
                'end_time' => substr((string) $row['end_time'], 0, 5),
                'planned_hours' => (float) $row['planned_hours'],
                'status' => (string) $row['status'],
                'note' => (string) ($row['note'] ?? ''),
                'assignments' => [],
            ];
        }

        if (!empty($row['assignment_id']) && (string) $row['assignment_status'] !== 'cancelled') {
            $schedules[$scheduleId]['assignments'][] = [
                'id' => (int) $row['assignment_id'],
                'staff_id' => (int) $row['staff_id'],
                'staff_name' => (string) ($row['staff_name'] ?? '-'),
                'staff_position' => (string) ($row['staff_position'] ?? ''),
                'staff_initials' => app_shift_staff_initials((string) ($row['staff_name'] ?? '')),
                'staff_profile_image_url' => app_resolve_user_image_url($row['staff_profile_image_path'] ?? ''),
                'assignment_status' => (string) $row['assignment_status'],
            ];
        }
    }

    return array_values($schedules);
}

function app_shift_find_overlap(PDO $conn, int $staffId, int $departmentId, string $date, string $startTime, string $endTime, ?int $excludeScheduleId = null): ?array
{
    $sql = "
        SELECT s.id, s.shift_type, s.start_time, s.end_time, u.fullname
        FROM shift_assignments a
        INNER JOIN shift_schedules s ON s.id = a.schedule_id
        INNER JOIN users u ON u.id = a.staff_id
        WHERE a.staff_id = ?
          AND s.department_id = ?
          AND s.schedule_date = ?
          AND a.assignment_status <> 'cancelled'
          AND s.status <> 'cancelled'
    ";
    $params = [$staffId, $departmentId, $date];
    if ($excludeScheduleId !== null) {
        $sql .= ' AND s.id <> ?';
        $params[] = $excludeScheduleId;
    }
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);

    foreach (($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []) as $row) {
        if (app_shift_ranges_overlap($date, $startTime, $endTime, (string) $row['start_time'], (string) $row['end_time'])) {
            return $row;
        }
    }

    return null;
}

function app_create_or_update_schedule(PDO $conn, int $departmentId, string $date, string $shiftType, string $startTime, string $endTime, array $staffIds, ?string $note, int $currentUserId): array
{
    app_shift_assert_department_access($conn, $departmentId);
    app_shift_validate_department($conn, $departmentId);

    $types = app_shift_schedule_types();
    if (!isset($types[$shiftType])) {
        throw new RuntimeException('ประเภทกะไม่ถูกต้อง');
    }
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || strtotime($date) === false) {
        throw new RuntimeException('วันที่ไม่ถูกต้อง');
    }

    $startTime = app_shift_normalize_time($startTime);
    $endTime = app_shift_normalize_time($endTime);
    $plannedHours = app_shift_planned_hours($startTime, $endTime);
    $staffIds = app_shift_validate_staff($conn, $departmentId, $staffIds);
    $note = trim((string) $note);

    $conn->beginTransaction();
    try {
        $findStmt = $conn->prepare("
            SELECT *
            FROM shift_schedules
            WHERE department_id = ?
              AND schedule_date = ?
              AND shift_type = ?
              AND start_time = ?
              AND end_time = ?
              AND status <> 'cancelled'
            LIMIT 1
        ");
        $findStmt->execute([$departmentId, $date, $shiftType, $startTime, $endTime]);
        $schedule = $findStmt->fetch(PDO::FETCH_ASSOC) ?: null;
        $scheduleId = $schedule ? (int) $schedule['id'] : 0;

        foreach ($staffIds as $staffId) {
            $overlap = app_shift_find_overlap($conn, $staffId, $departmentId, $date, $startTime, $endTime, $scheduleId ?: null);
            if ($overlap) {
                throw new RuntimeException(sprintf(
                    '%s มีเวรชนกับกะ %s เวลา %s-%s แล้ว',
                    (string) ($overlap['fullname'] ?? 'เจ้าหน้าที่'),
                    (string) ($types[(string) $overlap['shift_type']]['label'] ?? $overlap['shift_type']),
                    substr((string) $overlap['start_time'], 0, 5),
                    substr((string) $overlap['end_time'], 0, 5)
                ));
            }
        }

        if ($schedule) {
            $updateStmt = $conn->prepare("
                UPDATE shift_schedules
                SET planned_hours = ?, status = 'draft', note = ?, updated_at = NOW()
                WHERE id = ?
            ");
            $updateStmt->execute([$plannedHours, $note !== '' ? $note : null, $scheduleId]);
            app_shift_insert_audit($conn, 'shift_schedules', $scheduleId, 'update_schedule', $schedule, [
                'planned_hours' => $plannedHours,
                'status' => 'draft',
                'note' => $note,
            ], $currentUserId, 'บันทึกแผนเวรแบบ draft');
        } else {
            $insertStmt = $conn->prepare("
                INSERT INTO shift_schedules (
                    department_id, schedule_date, shift_type, start_time, end_time,
                    planned_hours, status, note, created_by
                ) VALUES (?, ?, ?, ?, ?, ?, 'draft', ?, ?)
            ");
            $insertStmt->execute([$departmentId, $date, $shiftType, $startTime, $endTime, $plannedHours, $note !== '' ? $note : null, $currentUserId]);
            $scheduleId = (int) $conn->lastInsertId();
            app_shift_insert_audit($conn, 'shift_schedules', $scheduleId, 'create_schedule', null, [
                'department_id' => $departmentId,
                'schedule_date' => $date,
                'shift_type' => $shiftType,
                'start_time' => $startTime,
                'end_time' => $endTime,
                'planned_hours' => $plannedHours,
                'status' => 'draft',
                'note' => $note,
            ], $currentUserId, 'สร้างแผนเวรแบบ draft');
        }

        $currentStmt = $conn->prepare('SELECT * FROM shift_assignments WHERE schedule_id = ?');
        $currentStmt->execute([$scheduleId]);
        $currentAssignments = [];
        foreach (($currentStmt->fetchAll(PDO::FETCH_ASSOC) ?: []) as $row) {
            $currentAssignments[(int) $row['staff_id']] = $row;
        }

        foreach ($currentAssignments as $staffId => $assignment) {
            if ((string) $assignment['assignment_status'] !== 'cancelled' && !in_array($staffId, $staffIds, true)) {
                $cancelStmt = $conn->prepare("UPDATE shift_assignments SET assignment_status = 'cancelled', updated_at = NOW() WHERE id = ?");
                $cancelStmt->execute([(int) $assignment['id']]);
                app_shift_insert_audit($conn, 'shift_assignments', (int) $assignment['id'], 'cancel_assignment', $assignment, [
                    'assignment_status' => 'cancelled',
                ], $currentUserId, 'ยกเลิกเจ้าหน้าที่จากแผนเวร');
            }
        }

        foreach ($staffIds as $staffId) {
            if (isset($currentAssignments[$staffId])) {
                $assignment = $currentAssignments[$staffId];
                if ((string) $assignment['assignment_status'] === 'cancelled') {
                    $reviveStmt = $conn->prepare("UPDATE shift_assignments SET assignment_status = 'assigned', updated_at = NOW() WHERE id = ?");
                    $reviveStmt->execute([(int) $assignment['id']]);
                    app_shift_insert_audit($conn, 'shift_assignments', (int) $assignment['id'], 'add_assignment', $assignment, [
                        'assignment_status' => 'assigned',
                    ], $currentUserId, 'เพิ่มเจ้าหน้าที่กลับเข้าแผนเวร');
                }
                continue;
            }

            $assignmentStmt = $conn->prepare("
                INSERT INTO shift_assignments (schedule_id, staff_id, assignment_status, created_by)
                VALUES (?, ?, 'assigned', ?)
            ");
            $assignmentStmt->execute([$scheduleId, $staffId, $currentUserId]);
            $assignmentId = (int) $conn->lastInsertId();
            app_shift_insert_audit($conn, 'shift_assignments', $assignmentId, 'add_assignment', null, [
                'schedule_id' => $scheduleId,
                'staff_id' => $staffId,
                'assignment_status' => 'assigned',
            ], $currentUserId, 'เพิ่มเจ้าหน้าที่เข้าแผนเวร');
        }

        $conn->commit();
        return ['schedule_id' => $scheduleId, 'message' => 'บันทึก draft ตารางเวรเรียบร้อย'];
    } catch (Throwable $e) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        throw $e;
    }
}

function app_publish_monthly_schedule(PDO $conn, int $departmentId, int $month, int $year, int $currentUserId): array
{
    app_shift_assert_department_access($conn, $departmentId);
    [$startDate, $endDate] = app_shift_month_bounds($month, $year);
    $startedTransaction = !$conn->inTransaction();

    try {
        if ($startedTransaction) {
            $conn->beginTransaction();
        }

    $countStmt = $conn->prepare("
        SELECT COUNT(*) AS schedule_count, COUNT(DISTINCT a.staff_id) AS staff_count
        FROM shift_schedules s
        LEFT JOIN shift_assignments a ON a.schedule_id = s.id AND a.assignment_status <> 'cancelled'
        WHERE s.department_id = ?
          AND s.schedule_date BETWEEN ? AND ?
          AND s.status = 'draft'
    ");
    $countStmt->execute([$departmentId, $startDate, $endDate]);
    $summary = $countStmt->fetch(PDO::FETCH_ASSOC) ?: ['schedule_count' => 0, 'staff_count' => 0];
    if ((int) $summary['schedule_count'] <= 0) {
        throw new RuntimeException('ไม่มี draft สำหรับเผยแพร่ในเดือนนี้');
    }

    $stmt = $conn->prepare("
        UPDATE shift_schedules
        SET status = 'published',
            published_by = ?,
            published_at = NOW(),
            updated_at = NOW()
        WHERE department_id = ?
          AND schedule_date BETWEEN ? AND ?
          AND status = 'draft'
    ");
    $stmt->execute([$currentUserId, $departmentId, $startDate, $endDate]);
    $updated = (int) $stmt->rowCount();
    $notified = $updated > 0 ? app_notify_monthly_schedule_published($conn, $departmentId, $month, $year, $currentUserId) : 0;

    app_shift_insert_audit($conn, 'shift_schedules', $departmentId . ':' . $year . '-' . sprintf('%02d', $month), 'publish_monthly_schedule', [
        'status' => 'draft',
        'date_from' => $startDate,
        'date_to' => $endDate,
    ], [
        'status' => 'published',
        'published_by' => $currentUserId,
        'updated_count' => $updated,
        'staff_count' => (int) $summary['staff_count'],
        'notification_count' => $notified,
    ], $currentUserId, 'เผยแพร่ตารางเวรประจำเดือน');

    if ($startedTransaction) {
        $conn->commit();
    }

    return [
        'updated_count' => $updated,
        'staff_count' => (int) $summary['staff_count'],
        'notification_count' => $notified,
        'message' => "เผยแพร่ตารางเวร {$updated} รายการเรียบร้อย",
    ];
    } catch (Throwable $e) {
        if ($startedTransaction && $conn->inTransaction()) {
            $conn->rollBack();
        }
        throw $e;
    }
}

function app_cancel_shift_assignment(PDO $conn, int $assignmentId, int $currentUserId): array
{
    if ($assignmentId <= 0) {
        throw new RuntimeException('ไม่พบรายการเจ้าหน้าที่ในเวร');
    }

    $conn->beginTransaction();
    try {
        $stmt = $conn->prepare("
            SELECT
                a.*,
                s.department_id,
                s.status AS schedule_status
            FROM shift_assignments a
            INNER JOIN shift_schedules s ON s.id = a.schedule_id
            WHERE a.id = ?
            LIMIT 1
            FOR UPDATE
        ");
        $stmt->execute([$assignmentId]);
        $assignment = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$assignment) {
            throw new RuntimeException('ไม่พบรายการเจ้าหน้าที่ในเวร');
        }

        app_shift_assert_department_access($conn, (int) $assignment['department_id']);
        $scheduleId = (int) $assignment['schedule_id'];
        $scheduleStatus = (string) ($assignment['schedule_status'] ?? '');
        if ($scheduleStatus !== 'draft') {
            throw new RuntimeException('ไม่สามารถลบเจ้าหน้าที่จากเวรที่เผยแพร่แล้วได้');
        }

        if ((string) $assignment['assignment_status'] !== 'cancelled') {
            $updateStmt = $conn->prepare("UPDATE shift_assignments SET assignment_status = 'cancelled', updated_at = NOW() WHERE id = ?");
            $updateStmt->execute([$assignmentId]);
            app_shift_insert_audit($conn, 'shift_assignments', $assignmentId, 'cancel_assignment', $assignment, [
                'assignment_status' => 'cancelled',
            ], $currentUserId, 'ยกเลิกเจ้าหน้าที่จากแผนเวร draft');
        }

        $countStmt = $conn->prepare("
            SELECT COUNT(*) FROM shift_assignments
            WHERE schedule_id = ?
              AND assignment_status <> 'cancelled'
        ");
        $countStmt->execute([$scheduleId]);
        $remainingAssignments = (int) $countStmt->fetchColumn();
        $draftDeleted = false;

        if ($remainingAssignments === 0) {
            $scheduleStmt = $conn->prepare("SELECT * FROM shift_schedules WHERE id = ? AND status = 'draft' LIMIT 1 FOR UPDATE");
            $scheduleStmt->execute([$scheduleId]);
            $schedule = $scheduleStmt->fetch(PDO::FETCH_ASSOC);
            if ($schedule) {
                $cancelScheduleStmt = $conn->prepare("UPDATE shift_schedules SET status = 'cancelled', updated_at = NOW() WHERE id = ? AND status = 'draft'");
                $cancelScheduleStmt->execute([$scheduleId]);
                $draftDeleted = $cancelScheduleStmt->rowCount() > 0;
                if ($draftDeleted) {
                    app_shift_insert_audit($conn, 'shift_schedules', $scheduleId, 'cancel_empty_draft_schedule', $schedule, [
                        'status' => 'cancelled',
                        'remaining_assignments' => 0,
                    ], $currentUserId, 'ยกเลิก draft อัตโนมัติเมื่อไม่มีเจ้าหน้าที่เหลือ');
                }
            }
        }

        $conn->commit();

        return [
            'assignment_removed' => true,
            'draft_deleted' => $draftDeleted,
            'schedule_id' => $scheduleId,
            'remaining_assignments' => $remainingAssignments,
            'message' => $draftDeleted ? 'ลบเจ้าหน้าที่คนสุดท้ายและลบดราฟเรียบร้อยแล้ว' : 'ลบเจ้าหน้าที่ออกจากดราฟเรียบร้อยแล้ว',
        ];
    } catch (Throwable $e) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        throw $e;
    }
}

function app_delete_shift_schedule_draft(PDO $conn, int $scheduleId, int $currentUserId): array
{
    if ($scheduleId <= 0) {
        throw new RuntimeException('ไม่พบดราฟที่ต้องการลบ');
    }

    $conn->beginTransaction();
    try {
        $stmt = $conn->prepare("
            SELECT *
            FROM shift_schedules
            WHERE id = ?
            LIMIT 1
            FOR UPDATE
        ");
        $stmt->execute([$scheduleId]);
        $schedule = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$schedule) {
            throw new RuntimeException('ไม่พบดราฟที่ต้องการลบ');
        }

        app_shift_assert_department_access($conn, (int) $schedule['department_id']);
        if ((string) $schedule['status'] !== 'draft') {
            throw new RuntimeException('ไม่สามารถลบเวรที่เผยแพร่แล้วได้');
        }

        $assignmentStmt = $conn->prepare("SELECT * FROM shift_assignments WHERE schedule_id = ? FOR UPDATE");
        $assignmentStmt->execute([$scheduleId]);
        $assignments = $assignmentStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $cancelAssignmentsStmt = $conn->prepare("
            UPDATE shift_assignments
            SET assignment_status = 'cancelled', updated_at = NOW()
            WHERE schedule_id = ?
              AND assignment_status <> 'cancelled'
        ");
        $cancelAssignmentsStmt->execute([$scheduleId]);

        $cancelScheduleStmt = $conn->prepare("UPDATE shift_schedules SET status = 'cancelled', updated_at = NOW() WHERE id = ? AND status = 'draft'");
        $cancelScheduleStmt->execute([$scheduleId]);
        if ($cancelScheduleStmt->rowCount() <= 0) {
            throw new RuntimeException('ไม่สามารถลบดราฟนี้ได้');
        }

        foreach ($assignments as $assignment) {
            if ((string) ($assignment['assignment_status'] ?? '') === 'cancelled') {
                continue;
            }
            app_shift_insert_audit($conn, 'shift_assignments', (int) $assignment['id'], 'cancel_assignment', $assignment, [
                'assignment_status' => 'cancelled',
            ], $currentUserId, 'ลบเจ้าหน้าที่จากดราฟทั้งก้อน');
        }
        app_shift_insert_audit($conn, 'shift_schedules', $scheduleId, 'cancel_draft_schedule', $schedule, [
            'status' => 'cancelled',
            'cancelled_assignments' => $cancelAssignmentsStmt->rowCount(),
        ], $currentUserId, 'ลบดราฟเวรทั้งก้อน');

        $conn->commit();

        return [
            'success' => true,
            'draft_deleted' => true,
            'schedule_id' => $scheduleId,
            'message' => 'ลบดราฟเรียบร้อยแล้ว',
        ];
    } catch (Throwable $e) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        throw $e;
    }
}

function app_shift_schedule_stats(array $schedules): array
{
    $staff = [];
    $draft = 0;
    $published = 0;
    foreach ($schedules as $schedule) {
        if (($schedule['status'] ?? '') === 'draft') {
            $draft++;
        } elseif (($schedule['status'] ?? '') === 'published') {
            $published++;
        }
        foreach (($schedule['assignments'] ?? []) as $assignment) {
            $staff[(int) $assignment['staff_id']] = true;
        }
    }

    return [
        'schedule_count' => count($schedules),
        'staff_count' => count($staff),
        'draft_count' => $draft,
        'published_count' => $published,
    ];
}

function app_my_shifts_month_filter(array $input): array
{
    $month = (int) ($input['month'] ?? date('n'));
    $yearInput = (int) ($input['year'] ?? ($input['year_be'] ?? ((int) date('Y') + 543)));
    $year = $yearInput > 2400 ? $yearInput - 543 : $yearInput;
    $view = (string) ($input['view'] ?? 'my');
    $display = (string) ($input['display'] ?? 'calendar');
    if (in_array($view, ['calendar', 'list'], true)) {
        $display = $view;
        $view = 'my';
    }

    if ($month < 1 || $month > 12) {
        $month = (int) date('n');
    }
    if ($year < 2000 || $year > 2100) {
        $year = (int) date('Y');
    }
    if (!in_array($view, ['my', 'department'], true)) {
        $view = 'my';
    }
    if (!in_array($display, ['calendar', 'list'], true)) {
        $display = 'calendar';
    }

    return [
        'month' => $month,
        'year' => $year,
        'year_be' => $year + 543,
        'view' => $view,
        'display' => $display,
    ];
}

function app_my_shift_status_meta(array $row): array
{
    if (empty($row['time_log_id'])) {
        return [
            'key' => 'not_logged',
            'label' => 'ยังไม่ลงเวร',
            'class' => 'not-logged',
        ];
    }

    if (!empty($row['time_log_checked_at'])) {
        return [
            'key' => 'approved',
            'label' => 'อนุมัติแล้ว',
            'class' => 'approved',
        ];
    }

    if (!empty($row['time_log_checked_by'])) {
        return [
            'key' => 'returned',
            'label' => 'ตีกลับ',
            'class' => 'returned',
        ];
    }

    return [
        'key' => 'pending',
        'label' => 'รอตรวจ',
        'class' => 'pending',
    ];
}

function app_get_my_shift_assignments(PDO $conn, int $userId, int $month, int $year): array
{
    [$startDate, $endDate] = app_shift_month_bounds($month, $year);
    if ($userId <= 0) {
        return [];
    }

    $sourceSelect = app_column_exists($conn, 'time_logs', 'source') ? 'tl.source AS time_log_source,' : "NULL AS time_log_source,";

    // --- Part 1: published shift_assignments ---
    $stmt = $conn->prepare("
        SELECT
            a.id AS assignment_id,
            a.schedule_id,
            a.staff_id,
            a.assignment_status,
            a.role_note,
            s.department_id,
            s.schedule_date,
            s.shift_type,
            s.start_time,
            s.end_time,
            s.planned_hours,
            s.status AS schedule_status,
            s.note AS schedule_note,
            d.department_name,
            tl.id AS time_log_id,
            tl.status AS time_log_status,
            tl.work_hours AS time_log_total_hours,
            {$sourceSelect}
            tl.note AS time_log_note,
            tl.checked_by AS time_log_checked_by,
            tl.checked_at AS time_log_checked_at,
            tl.time_in AS time_log_time_in,
            tl.time_out AS time_log_time_out
        FROM shift_assignments a
        INNER JOIN shift_schedules s ON s.id = a.schedule_id
        LEFT JOIN departments d ON d.id = s.department_id
        LEFT JOIN time_logs tl ON tl.schedule_assignment_id = a.id
        WHERE a.staff_id = ?
          AND a.assignment_status <> 'cancelled'
          AND s.status = 'published'
          AND s.status <> 'cancelled'
          AND s.schedule_date BETWEEN ? AND ?
        ORDER BY s.schedule_date ASC, s.start_time ASC, a.id ASC, tl.id DESC
    ");
    $stmt->execute([$userId, $startDate, $endDate]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $assignments = [];
    $coveredTimeLogIds = [];
    foreach ($rows as $row) {
        $assignmentId = (int) $row['assignment_id'];
        if (isset($assignments[$assignmentId])) {
            continue;
        }
        $row['staff_name'] = $row['staff_name'] ?? (string) ($_SESSION['fullname'] ?? '');
        $row['is_mine'] = true;
        $row['status_meta'] = app_my_shift_status_meta($row);
        $row['start_time_label'] = substr((string) $row['start_time'], 0, 5);
        $row['end_time_label'] = substr((string) $row['end_time'], 0, 5);
        $assignments[$assignmentId] = $row;
        if (!empty($row['time_log_id'])) {
            $coveredTimeLogIds[(int) $row['time_log_id']] = true;
        }
    }

    // --- Part 2: standalone time_logs (no schedule_assignment_id) — req. 3 ---
    $hasAssignmentCol = app_column_exists($conn, 'time_logs', 'schedule_assignment_id');
    $standaloneWhere = $hasAssignmentCol
        ? "(tl.schedule_assignment_id IS NULL OR tl.schedule_assignment_id = 0)"
        : "1 = 1";
    $tlSourceSelect = app_column_exists($conn, 'time_logs', 'source') ? 'tl.source AS time_log_source,' : "NULL AS time_log_source,";
    $tlStmt = $conn->prepare("
        SELECT
            tl.id,
            tl.user_id,
            tl.department_id,
            tl.work_date,
            tl.time_in,
            tl.time_out,
            tl.work_hours,
            tl.note,
            tl.status,
            tl.checked_by,
            tl.checked_at,
            {$tlSourceSelect}
            d.department_name
        FROM time_logs tl
        LEFT JOIN departments d ON d.id = tl.department_id
        WHERE tl.user_id = ?
          AND {$standaloneWhere}
          AND tl.work_date BETWEEN ? AND ?
        ORDER BY tl.work_date ASC, tl.time_in ASC, tl.id ASC
    ");
    $tlStmt->execute([$userId, $startDate, $endDate]);
    $tlRows = $tlStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    foreach ($tlRows as $tlRow) {
        $tlId = (int) $tlRow['id'];
        if (isset($coveredTimeLogIds[$tlId])) {
            continue;
        }
        $timeIn = (string) ($tlRow['time_in'] ?? '');
        $timeOut = (string) ($tlRow['time_out'] ?? '');
        // time_in/time_out may be full datetime (Y-m-d H:i:s) or just time (H:i:s)
        $startTimeLabel = strlen($timeIn) > 10 ? substr($timeIn, 11, 5) : (strlen($timeIn) >= 5 ? substr($timeIn, 0, 5) : '--:--');
        $endTimeLabel   = strlen($timeOut) > 10 ? substr($timeOut, 11, 5) : (strlen($timeOut) >= 5 ? substr($timeOut, 0, 5) : '--:--');
        $startTimeFull  = $startTimeLabel !== '--:--' ? $startTimeLabel . ':00' : '00:00:00';
        $endTimeFull    = $endTimeLabel   !== '--:--' ? $endTimeLabel   . ':00' : '00:00:00';

        $synthetic = [
            'assignment_id'        => 0,
            'schedule_id'          => 0,
            'staff_id'             => (int) $tlRow['user_id'],
            'assignment_status'    => 'standalone',
            'role_note'            => '',
            'department_id'        => (int) ($tlRow['department_id'] ?? 0),
            'schedule_date'        => (string) $tlRow['work_date'],
            'shift_type'           => 'custom',
            'start_time'           => $startTimeFull,
            'end_time'             => $endTimeFull,
            'planned_hours'        => (float) $tlRow['work_hours'],
            'schedule_status'      => 'standalone',
            'schedule_note'        => '',
            'department_name'      => (string) ($tlRow['department_name'] ?? '-'),
            'time_log_id'          => $tlId,
            'time_log_status'      => (string) ($tlRow['status'] ?? ''),
            'time_log_total_hours' => (float) $tlRow['work_hours'],
            'time_log_source'      => (string) ($tlRow['time_log_source'] ?? 'manual'),
            'time_log_note'        => (string) ($tlRow['note'] ?? ''),
            'time_log_checked_by'  => $tlRow['checked_by'] ?? null,
            'time_log_checked_at'  => $tlRow['checked_at'] ?? null,
            'time_log_time_in'     => $timeIn,
            'time_log_time_out'    => $timeOut,
            'staff_name'           => (string) ($_SESSION['fullname'] ?? ''),
            'is_mine'              => true,
        ];
        $synthetic['status_meta']    = app_my_shift_status_meta($synthetic);
        $synthetic['start_time_label'] = $startTimeLabel;
        $synthetic['end_time_label']   = $endTimeLabel;
        // Use prefixed string key to avoid clashing with real assignment integer keys
        $assignments['tl_' . $tlId] = $synthetic;
    }

    // Re-sort merged result by date then start_time
    $result = array_values($assignments);
    usort($result, static function (array $a, array $b): int {
        $d = strcmp((string) $a['schedule_date'], (string) $b['schedule_date']);
        if ($d !== 0) {
            return $d;
        }
        return strcmp((string) $a['start_time'], (string) $b['start_time']);
    });

    return $result;
}

function app_get_department_shift_assignments(PDO $conn, int $currentUserId, int $departmentId, int $month, int $year): array
{
    [$startDate, $endDate] = app_shift_month_bounds($month, $year);
    if ($currentUserId <= 0 || $departmentId <= 0) {
        return [];
    }

    $currentDepartmentId = app_get_current_user_department_id($conn);
    $role = app_current_role();
    $canGlobal = $role === 'admin' || app_can('can_manage_user_permissions');
    if (!$canGlobal && $currentDepartmentId !== $departmentId) {
        return [];
    }

    $sourceSelect = app_column_exists($conn, 'time_logs', 'source') ? 'tl.source AS time_log_source,' : "NULL AS time_log_source,";
    $stmt = $conn->prepare("
        SELECT
            a.id AS assignment_id,
            a.schedule_id,
            a.staff_id,
            a.assignment_status,
            a.role_note,
            s.department_id,
            s.schedule_date,
            s.shift_type,
            s.start_time,
            s.end_time,
            s.planned_hours,
            s.status AS schedule_status,
            s.note AS schedule_note,
            d.department_name,
            u.fullname AS staff_name,
            tl.id AS time_log_id,
            tl.status AS time_log_status,
            tl.work_hours AS time_log_total_hours,
            {$sourceSelect}
            tl.note AS time_log_note,
            tl.checked_by AS time_log_checked_by,
            tl.checked_at AS time_log_checked_at,
            tl.time_in AS time_log_time_in,
            tl.time_out AS time_log_time_out
        FROM shift_assignments a
        INNER JOIN shift_schedules s ON s.id = a.schedule_id
        INNER JOIN users u ON u.id = a.staff_id
        LEFT JOIN departments d ON d.id = s.department_id
        LEFT JOIN time_logs tl ON tl.schedule_assignment_id = a.id
        WHERE s.department_id = ?
          AND a.assignment_status <> 'cancelled'
          AND s.status = 'published'
          AND s.status <> 'cancelled'
          AND s.schedule_date BETWEEN ? AND ?
          AND COALESCE(u.is_active, 1) = 1
        ORDER BY s.schedule_date ASC, CASE WHEN a.staff_id = ? THEN 0 ELSE 1 END ASC, s.start_time ASC, u.fullname ASC, a.id ASC, tl.id DESC
    ");
    $stmt->execute([$departmentId, $startDate, $endDate, $currentUserId]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $assignments = [];
    foreach ($rows as $row) {
        $assignmentId = (int) $row['assignment_id'];
        if (isset($assignments[$assignmentId])) {
            continue;
        }
        $row['is_mine'] = (int) $row['staff_id'] === $currentUserId;
        $row['status_meta'] = app_my_shift_status_meta($row);
        $row['start_time_label'] = substr((string) $row['start_time'], 0, 5);
        $row['end_time_label'] = substr((string) $row['end_time'], 0, 5);
        $assignments[$assignmentId] = $row;
    }

    return array_values($assignments);
}

function app_my_shift_stats(array $assignments): array
{
    $stats = [
        'total' => count($assignments),
        'not_logged' => 0,
        'pending' => 0,
        'approved' => 0,
        'returned' => 0,
    ];

    foreach ($assignments as $assignment) {
        $key = (string) (($assignment['status_meta']['key'] ?? 'not_logged'));
        if (!array_key_exists($key, $stats)) {
            $key = 'not_logged';
        }
        $stats[$key]++;
    }

    return $stats;
}

function app_my_shifts_group_by_date(array $assignments): array
{
    $grouped = [];
    foreach ($assignments as $assignment) {
        $date = (string) ($assignment['schedule_date'] ?? '');
        if ($date === '') {
            continue;
        }
        $grouped[$date][] = $assignment;
    }

    return $grouped;
}

function app_shift_build_time_range(string $date, string $startTime, string $endTime): array
{
    $startTime = app_shift_normalize_time(substr($startTime, 0, 5));
    $endTime = app_shift_normalize_time(substr($endTime, 0, 5));
    [$start, $end] = app_shift_slot_bounds($date, $startTime, $endTime);

    return [
        'time_in' => $start->format('Y-m-d H:i:s'),
        'time_out' => $end->format('Y-m-d H:i:s'),
        'hours' => round(($end->getTimestamp() - $start->getTimestamp()) / 3600, 2),
    ];
}

function app_create_time_log_from_assignment(PDO $conn, int $assignmentId, int $currentUserId, ?string $note = null): array
{
    if ($currentUserId <= 0) {
        throw new RuntimeException('กรุณาเข้าสู่ระบบอีกครั้ง');
    }
    if ($assignmentId <= 0) {
        throw new RuntimeException('ไม่พบรายการแผนเวรที่ต้องการลงเวร');
    }
    if (!app_column_exists($conn, 'time_logs', 'schedule_assignment_id')) {
        throw new RuntimeException('ฐานข้อมูลยังไม่มี time_logs.schedule_assignment_id กรุณารัน migration เฟส 1 ก่อน');
    }

    $stmt = $conn->prepare("
        SELECT
            a.id AS assignment_id,
            a.schedule_id,
            a.staff_id,
            a.assignment_status,
            a.role_note,
            s.department_id,
            s.schedule_date,
            s.shift_type,
            s.start_time,
            s.end_time,
            s.planned_hours,
            s.status AS schedule_status,
            s.note AS schedule_note,
            d.department_name
        FROM shift_assignments a
        INNER JOIN shift_schedules s ON s.id = a.schedule_id
        LEFT JOIN departments d ON d.id = s.department_id
        WHERE a.id = ?
        LIMIT 1
    ");
    $stmt->execute([$assignmentId]);
    $assignment = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$assignment) {
        throw new RuntimeException('ไม่พบรายการแผนเวรที่ต้องการลงเวร');
    }
    if ((int) $assignment['staff_id'] !== $currentUserId) {
        throw new RuntimeException('ไม่สามารถลงเวรจาก assignment ของผู้อื่นได้');
    }
    if ((string) $assignment['assignment_status'] === 'cancelled') {
        throw new RuntimeException('assignment นี้ถูกยกเลิกแล้ว');
    }
    if ((string) $assignment['schedule_status'] !== 'published') {
        throw new RuntimeException('แผนเวรนี้ยังไม่ถูกเผยแพร่หรือถูกยกเลิกแล้ว');
    }

    $duplicateStmt = $conn->prepare('SELECT id FROM time_logs WHERE schedule_assignment_id = ? LIMIT 1');
    $duplicateStmt->execute([$assignmentId]);
    if ($duplicateStmt->fetchColumn()) {
        throw new RuntimeException('รายการนี้ถูกลงเวรแล้ว ไม่สามารถสร้างซ้ำได้');
    }

    $range = app_shift_build_time_range(
        (string) $assignment['schedule_date'],
        (string) $assignment['start_time'],
        (string) $assignment['end_time']
    );
    $overlap = app_find_overlapping_time_log($conn, $currentUserId, $range['time_in'], $range['time_out']);
    if ($overlap) {
        throw new RuntimeException(sprintf(
            'ช่วงเวลานี้ซ้อนกับรายการลงเวรวันที่ %s เวลา %s-%s แล้ว',
            app_format_thai_date((string) $overlap['work_date']),
            date('H:i', strtotime((string) $overlap['time_in'])),
            date('H:i', strtotime((string) $overlap['time_out']))
        ));
    }

    $note = trim((string) $note);
    if ($note === '') {
        $note = trim((string) ($assignment['schedule_note'] ?? ''));
    }
    $hours = (float) $assignment['planned_hours'] > 0 ? (float) $assignment['planned_hours'] : (float) $range['hours'];
    $actorName = app_shift_actor_name($conn, $currentUserId);

    $columns = [
        'schedule_assignment_id',
        'user_id',
        'department_id',
        'work_date',
        'time_in',
        'time_out',
        'work_hours',
        'note',
        'status',
        'checked_by',
        'checked_at',
        'signature',
        'approval_note',
    ];
    $values = [
        $assignmentId,
        $currentUserId,
        (int) $assignment['department_id'],
        (string) $assignment['schedule_date'],
        $range['time_in'],
        $range['time_out'],
        number_format($hours, 2, '.', ''),
        $note,
        'submitted',
        null,
        null,
        null,
        null,
    ];

    if (app_column_exists($conn, 'time_logs', 'source')) {
        $columns[] = 'source';
        $values[] = 'planned';
    }

    try {
        $conn->beginTransaction();

        $duplicateStmt = $conn->prepare('SELECT id FROM time_logs WHERE schedule_assignment_id = ? LIMIT 1');
        $duplicateStmt->execute([$assignmentId]);
        if ($duplicateStmt->fetchColumn()) {
            throw new RuntimeException('รายการนี้ถูกลงเวรแล้ว ไม่สามารถสร้างซ้ำได้');
        }

        $placeholders = implode(', ', array_fill(0, count($columns), '?'));
        $columnSql = implode(', ', $columns);
        $insertStmt = $conn->prepare("INSERT INTO time_logs ({$columnSql}) VALUES ({$placeholders})");
        $insertStmt->execute($values);
        $timeLogId = (int) $conn->lastInsertId();
        $afterRow = app_get_time_log_by_id($conn, $timeLogId);

        app_insert_time_log_audit(
            $conn,
            $timeLogId,
            'create_time_log_from_schedule',
            null,
            $afterRow ?: [
                'id' => $timeLogId,
                'schedule_assignment_id' => $assignmentId,
                'schedule_id' => (int) $assignment['schedule_id'],
            ],
            $currentUserId,
            $actorName,
            'สร้างรายการลงเวรจริงจากแผนเวร'
        );
        app_sync_reviewer_queue_notifications($conn);
        $conn->commit();

        return [
            'time_log_id' => $timeLogId,
            'message' => 'ลงเวรตามแผนเรียบร้อย ส่งรายการเข้าคิวรอตรวจแล้ว',
        ];
    } catch (Throwable $e) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        throw $e;
    }
}
