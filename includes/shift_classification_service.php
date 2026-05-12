<?php

function app_shift_classification_options(): array
{
    return [
        'all' => [
            'label' => 'ทั้งหมด',
            'class' => 'status-chip neutral',
        ],
        'planned_match' => [
            'label' => 'ตรงตามแผน',
            'class' => 'status-chip success',
        ],
        'outside_plan' => [
            'label' => 'นอกแผน',
            'class' => 'status-chip warning',
        ],
        'swapped' => [
            'label' => 'แลกเวรแล้ว',
            'class' => 'status-chip info',
        ],
        'swap_pending' => [
            'label' => 'รอแลกเวร',
            'class' => 'status-chip warning',
        ],
        'manager_changed' => [
            'label' => 'เปลี่ยนเวรโดยหัวหน้า',
            'class' => 'status-chip neutral',
        ],
    ];
}

function app_shift_classification_normalize(?string $value): string
{
    $value = trim((string) $value);
    return array_key_exists($value, app_shift_classification_options()) ? $value : 'all';
}

function app_shift_classification_labels(): array
{
    return array_map(static fn(array $meta): string => $meta['label'], app_shift_classification_options());
}

function app_shift_report_dataset_options(): array
{
    return [
        'actual' => 'จากเวรที่ลงจริง',
        'planned' => 'จากแผนเวร',
    ];
}

function app_shift_report_dataset_normalize(?string $value): string
{
    $value = trim((string) $value);
    return array_key_exists($value, app_shift_report_dataset_options()) ? $value : 'actual';
}

function app_shift_review_status_options(): array
{
    return [
        'all' => 'ทั้งหมด',
        'reviewed' => 'เฉพาะรายการที่ตรวจแล้ว',
        'pending' => 'รอตรวจ',
        'rejected' => 'ตีกลับ',
    ];
}

function app_shift_review_status_normalize(?string $value): string
{
    $value = trim((string) $value);
    return array_key_exists($value, app_shift_review_status_options()) ? $value : 'all';
}

function app_shift_status_to_review_status(string $status): string
{
    return match ($status) {
        'checked' => 'reviewed',
        'pending' => 'pending',
        'rejected' => 'rejected',
        default => 'all',
    };
}

function app_shift_review_status_to_status(string $reviewStatus): string
{
    return match ($reviewStatus) {
        'reviewed' => 'checked',
        'pending' => 'pending',
        'rejected' => 'rejected',
        default => 'all',
    };
}

function app_shift_classification_actual_filter_clause(string $classification, string $timeLogAlias = 't'): string
{
    $classification = app_shift_classification_normalize($classification);
    $assignmentExpr = $timeLogAlias . '.schedule_assignment_id';

    return match ($classification) {
        'planned_match' => $assignmentExpr . ' IS NOT NULL',
        'outside_plan' => $assignmentExpr . ' IS NULL',
        'swapped' => "EXISTS (
            SELECT 1
            FROM shift_swap_requests csr
            WHERE csr.status = 'applied'
              AND (csr.requester_assignment_id = {$assignmentExpr} OR csr.target_assignment_id = {$assignmentExpr})
        )",
        'swap_pending' => "EXISTS (
            SELECT 1
            FROM shift_swap_requests csr
            WHERE csr.status IN ('pending_target_confirm', 'pending_manager_approval')
              AND (csr.requester_assignment_id = {$assignmentExpr} OR csr.target_assignment_id = {$assignmentExpr})
        )",
        'manager_changed' => "EXISTS (
            SELECT 1
            FROM db_admin_audit_logs cal
            WHERE cal.table_name = 'shift_assignments'
              AND cal.row_primary_key = CAST({$assignmentExpr} AS CHAR)
              AND cal.action_type IN ('manager_assignment_change', 'manual_assignment_update', 'change_assignment_staff_by_manager')
        )",
        default => '',
    };
}

function app_shift_classification_assignment_filter_clause(string $classification, string $assignmentAlias = 'sa'): string
{
    $classification = app_shift_classification_normalize($classification);
    $assignmentExpr = $assignmentAlias . '.id';

    return match ($classification) {
        'planned_match' => $assignmentExpr . ' IS NOT NULL',
        'outside_plan' => '1 = 0',
        'swapped' => "EXISTS (
            SELECT 1
            FROM shift_swap_requests csr
            WHERE csr.status = 'applied'
              AND (csr.requester_assignment_id = {$assignmentExpr} OR csr.target_assignment_id = {$assignmentExpr})
        )",
        'swap_pending' => "EXISTS (
            SELECT 1
            FROM shift_swap_requests csr
            WHERE csr.status IN ('pending_target_confirm', 'pending_manager_approval')
              AND (csr.requester_assignment_id = {$assignmentExpr} OR csr.target_assignment_id = {$assignmentExpr})
        )",
        'manager_changed' => "EXISTS (
            SELECT 1
            FROM db_admin_audit_logs cal
            WHERE cal.table_name = 'shift_assignments'
              AND cal.row_primary_key = CAST({$assignmentExpr} AS CHAR)
              AND cal.action_type IN ('manager_assignment_change', 'manual_assignment_update', 'change_assignment_staff_by_manager')
        )",
        default => '',
    };
}

function app_shift_classification_select_sql(string $assignmentExpr): string
{
    return "
        (SELECT sr.id
         FROM shift_swap_requests sr
         WHERE sr.status = 'applied'
           AND (sr.requester_assignment_id = {$assignmentExpr} OR sr.target_assignment_id = {$assignmentExpr})
         ORDER BY sr.id DESC
         LIMIT 1) AS applied_swap_request_id,
        (SELECT sr.id
         FROM shift_swap_requests sr
         WHERE sr.status IN ('pending_target_confirm', 'pending_manager_approval')
           AND (sr.requester_assignment_id = {$assignmentExpr} OR sr.target_assignment_id = {$assignmentExpr})
         ORDER BY sr.id DESC
         LIMIT 1) AS pending_swap_request_id,
        (SELECT al.id
         FROM db_admin_audit_logs al
         WHERE al.table_name = 'shift_assignments'
           AND al.row_primary_key = CAST({$assignmentExpr} AS CHAR)
           AND al.action_type IN ('manager_assignment_change', 'manual_assignment_update', 'change_assignment_staff_by_manager')
         ORDER BY al.id DESC
         LIMIT 1) AS manager_change_audit_id
    ";
}

function app_shift_classification_badges(array $row): array
{
    $options = app_shift_classification_options();
    $badges = [];
    $assignmentId = (int) ($row['schedule_assignment_id'] ?? $row['classification_assignment_id'] ?? $row['assignment_id'] ?? 0);

    if ($assignmentId > 0) {
        $badges['planned_match'] = $options['planned_match'];
    } else {
        $badges['outside_plan'] = $options['outside_plan'];
    }

    if (!empty($row['applied_swap_request_id'])) {
        $badges['swapped'] = $options['swapped'];
    }
    if (!empty($row['pending_swap_request_id'])) {
        $badges['swap_pending'] = $options['swap_pending'];
    }
    if (!empty($row['manager_change_audit_id'])) {
        $badges['manager_changed'] = $options['manager_changed'];
    }

    return $badges;
}

function app_shift_classification_enrich_row(array $row): array
{
    $badges = app_shift_classification_badges($row);
    $row['classification_badges'] = $badges;
    $row['classification_codes'] = array_keys($badges);
    $row['classification_labels'] = array_map(static fn(array $badge): string => $badge['label'], $badges);
    $row['classification_label'] = implode(', ', $row['classification_labels']);

    return $row;
}

function app_shift_classification_enrich_rows(array $rows): array
{
    return array_map('app_shift_classification_enrich_row', $rows);
}

function app_shift_classification_summary(array $rows): array
{
    $summary = [
        'planned_match_count' => 0,
        'outside_plan_count' => 0,
        'swapped_count' => 0,
        'swap_pending_count' => 0,
        'manager_changed_count' => 0,
    ];

    foreach ($rows as $row) {
        foreach ((array) ($row['classification_codes'] ?? array_keys(app_shift_classification_badges($row))) as $code) {
            $key = $code . '_count';
            if (array_key_exists($key, $summary)) {
                $summary[$key]++;
            }
        }
    }

    return $summary;
}
