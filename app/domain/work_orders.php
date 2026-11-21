<?php

declare(strict_types=1);

require_once __DIR__ . '/modules.php';
require_once __DIR__ . '/staff.php';

function work_order_statuses(): array
{
    return ['planned', 'in_progress', 'done', 'cancelled'];
}

function work_order_list(): array
{
    require_module('fashion.production');
    require_permission('production.board');
    $rows = db_query(
        'SELECT id, product_id, title, quantity, status, due_on, assigned_user_id, note, created_at
         FROM fashion_work_orders
         WHERE tenant_id = :tenant_id
         ORDER BY created_at DESC
         LIMIT 100'
    );
    $out = [];
    foreach ($rows as $row) {
        if (is_array($row)) {
            $out[] = [
                'id' => (string) $row['id'],
                'title' => (string) $row['title'],
                'quantity' => (int) $row['quantity'],
                'status' => (string) $row['status'],
                'due_on' => $row['due_on'] !== null ? (string) $row['due_on'] : null,
                'assigned_user_id' => $row['assigned_user_id'] !== null ? (string) $row['assigned_user_id'] : null,
            ];
        }
    }
    return $out;
}

function work_order_create(array $input): array
{
    require_module('fashion.production');
    require_permission('production.board');
    $title = trim((string) ($input['title'] ?? ''));
    $qty = (int) ($input['quantity'] ?? 0);
    $due = trim((string) ($input['due_on'] ?? ''));
    $note = trim((string) ($input['note'] ?? ''));
    $productId = trim((string) ($input['product_id'] ?? ''));
    $assigned = trim((string) ($input['assigned_user_id'] ?? ''));
    $errors = [];
    if ($title === '' || strlen($title) > 190) {
        $errors['title'] = 'required';
    }
    if ($qty < 1 || $qty > 100000) {
        $errors['quantity'] = 'required';
    }
    $dueOn = null;
    if ($due !== '') {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $due, app_timezone());
        if (!$date instanceof DateTimeImmutable || $date->format('Y-m-d') !== $due) {
            $errors['due_on'] = 'invalid_date';
        } else {
            $dueOn = $due;
        }
    }
    if ($productId !== '' && !is_uuid($productId)) {
        $errors['product_id'] = 'required';
    }
    if ($assigned !== '' && !is_uuid($assigned)) {
        $errors['assigned_user_id'] = 'required';
    }
    if (strlen($note) > 500) {
        $errors['note'] = 'required';
    }
    $userId = current_user_id();
    if ($userId === null) {
        $errors['form'] = 'not_allowed';
    }
    if ($errors !== []) {
        return ['errors' => $errors];
    }
    $id = db_insert('fashion_work_orders', [
        'product_id' => $productId !== '' ? $productId : null,
        'title' => $title,
        'quantity' => $qty,
        'status' => 'planned',
        'due_on' => $dueOn,
        'assigned_user_id' => $assigned !== '' ? $assigned : null,
        'note' => $note,
        'created_by' => $userId,
        'created_at' => app_now()->format('Y-m-d H:i:s'),
    ]);
    return ['work_order_id' => $id];
}

function work_order_set_status(string $workOrderId, string $status): array
{
    require_module('fashion.production');
    require_permission('production.board');
    if (!is_uuid($workOrderId) || !in_array($status, work_order_statuses(), true)) {
        return ['errors' => ['form' => 'not_found']];
    }
    $rows = db_select('fashion_work_orders', ['id' => $workOrderId], ['limit' => 1]);
    if ($rows === []) {
        return ['errors' => ['form' => 'not_found']];
    }
    db_update('fashion_work_orders', ['status' => $status], ['id' => $workOrderId]);
    return ['ok' => true];
}

function quality_check_record(array $input): array
{
    require_module('fashion.production');
    require_permission('production.board');
    $result = (string) ($input['result'] ?? '');
    $reason = trim((string) ($input['reason'] ?? ''));
    $orderId = trim((string) ($input['order_id'] ?? ''));
    $workOrderId = trim((string) ($input['work_order_id'] ?? ''));
    if (!in_array($result, ['pass', 'fail'], true)) {
        return ['errors' => ['result' => 'required']];
    }
    if ($result === 'fail' && $reason === '') {
        return ['errors' => ['reason' => 'required']];
    }
    if (strlen($reason) > 500) {
        return ['errors' => ['reason' => 'required']];
    }
    if ($orderId === '' && $workOrderId === '') {
        return ['errors' => ['form' => 'required']];
    }
    if ($orderId !== '' && !is_uuid($orderId)) {
        return ['errors' => ['order_id' => 'required']];
    }
    if ($workOrderId !== '' && !is_uuid($workOrderId)) {
        return ['errors' => ['work_order_id' => 'required']];
    }
    $userId = current_user_id();
    if ($userId === null) {
        return ['errors' => ['form' => 'not_allowed']];
    }
    $id = db_insert('fashion_quality_checks', [
        'order_id' => $orderId !== '' ? $orderId : null,
        'work_order_id' => $workOrderId !== '' ? $workOrderId : null,
        'result' => $result,
        'reason' => $reason,
        'checked_by' => $userId,
        'created_at' => app_now()->format('Y-m-d H:i:s'),
    ]);
    return ['quality_check_id' => $id];
}
