<?php

declare(strict_types=1);

require_once __DIR__ . '/modules.php';

function deposit_due_kobo(int $totalKobo, int $percent): int
{
    if ($percent <= 0 || $totalKobo <= 0) {
        return 0;
    }
    return intdiv($totalKobo * $percent + 99, 100);
}

function deposit_shop(): array
{
    $tenantId = current_tenant_id();
    if ($tenantId === null) {
        throw new RuntimeException('Missing session.');
    }
    $rows = db_select('fashion_tenants', ['id' => $tenantId]);
    $percent = 0;
    $stageId = null;
    if ($rows !== []) {
        $rawPercent = $rows[0]['deposit_percent'];
        if ($rawPercent !== null && $rawPercent !== '') {
            $percent = (int) $rawPercent;
        }
        $rawStage = $rows[0]['deposit_stage_id'] ?? null;
        if (is_string($rawStage) && $rawStage !== '') {
            $stageId = $rawStage;
        }
    }
    if ($percent < 0) {
        $percent = 0;
    }
    if ($percent > 100) {
        $percent = 100;
    }
    return ['percent' => $percent, 'stage_id' => $stageId];
}

function deposit_stage_choices(): array
{
    return db_select('fashion_stage_definitions', [], ['order' => 'sort_order ASC, name ASC', 'limit' => 100]);
}

function deposit_settings_save(string $percentRaw, string $stageId): array
{
    $percentRaw = trim($percentRaw);
    if (!preg_match('/^(?:0|[1-9]\d?|100)$/', $percentRaw)) {
        return ['error' => 'deposit_percent_invalid'];
    }
    $stageValue = null;
    if ($stageId !== '') {
        if (!is_uuid($stageId) || db_select('fashion_stage_definitions', ['id' => $stageId]) === []) {
            return ['error' => 'deposit_stage_invalid'];
        }
        $stageValue = $stageId;
    }
    db_update('fashion_tenants', [
        'deposit_percent' => (int) $percentRaw,
        'deposit_stage_id' => $stageValue,
    ], ['id' => (string) current_tenant_id()]);
    require_once __DIR__ . '/messages.php';
    message_queue_deposits();
    return [];
}

function deposit_money(string $orderId): array
{
    $totals = db_query(
        'SELECT COALESCE(SUM(quantity * price_kobo), 0) AS total_kobo
         FROM fashion_order_items
         WHERE tenant_id = :tenant_id AND order_id = :order_id',
        ['order_id' => $orderId]
    );
    $paid = db_query(
        'SELECT COALESCE(SUM(amount_kobo), 0) AS paid_kobo
         FROM fashion_payments
         WHERE tenant_id = :tenant_id AND order_id = :order_id',
        ['order_id' => $orderId]
    );
    return [
        'total_kobo' => (int) ($totals[0]['total_kobo'] ?? 0),
        'paid_kobo' => (int) ($paid[0]['paid_kobo'] ?? 0),
    ];
}

function deposit_override_event(string $orderId): ?array
{
    $rows = db_select(
        'fashion_stage_events',
        ['order_id' => $orderId, 'kind' => 'override'],
        ['order' => 'seq ASC', 'limit' => 1]
    );
    return $rows[0] ?? null;
}

function deposit_is_owner(): bool
{
    $userId = current_user_id();
    if ($userId === null) {
        return false;
    }
    $rows = db_select('fashion_users', ['id' => $userId]);
    return $rows !== [] && (string) $rows[0]['role'] === 'owner';
}

function deposit_move_block(string $orderId, string $nextStageId): ?array
{
    $shop = deposit_shop();
    if ($shop['percent'] <= 0 || $shop['stage_id'] === null || $shop['stage_id'] !== $nextStageId) {
        return null;
    }
    if (deposit_override_event($orderId) !== null) {
        return null;
    }
    $money = deposit_money($orderId);
    $due = deposit_due_kobo($money['total_kobo'], $shop['percent']);
    $paid = $money['paid_kobo'];
    if ($paid >= $due) {
        return null;
    }
    return [
        'error' => 'deposit_short',
        'due_kobo' => $due,
        'paid_kobo' => $paid,
        'needed_kobo' => $due - $paid,
    ];
}

function deposit_current_sort(string $orderId): ?int
{
    $rows = db_query(
        'SELECT sd.sort_order
         FROM fashion_stage_events se
         INNER JOIN fashion_stage_definitions sd ON sd.id = se.stage_id AND sd.tenant_id = :tenant_id
         WHERE se.tenant_id = :tenant_id AND se.order_id = :order_id
         ORDER BY se.seq DESC
         LIMIT 1',
        ['order_id' => $orderId]
    );
    if ($rows !== []) {
        return (int) $rows[0]['sort_order'];
    }
    $first = db_select('fashion_stage_definitions', ['active' => 1], ['order' => 'sort_order ASC', 'limit' => 1]);
    if ($first === []) {
        return null;
    }
    return (int) $first[0]['sort_order'];
}

function deposit_status(string $orderId): array
{
    $shop = deposit_shop();
    $money = deposit_money($orderId);
    $due = deposit_due_kobo($money['total_kobo'], $shop['percent']);
    $paid = $money['paid_kobo'];
    $stageName = null;
    $warning = false;
    if ($shop['stage_id'] !== null) {
        $stages = db_select('fashion_stage_definitions', ['id' => $shop['stage_id']]);
        if ($stages !== []) {
            $stageName = (string) $stages[0]['name'];
            $currentSort = deposit_current_sort($orderId);
            $warning = $shop['percent'] > 0
                && $currentSort !== null
                && $currentSort >= (int) $stages[0]['sort_order']
                && $paid < $due;
        }
    }
    $override = null;
    $event = deposit_override_event($orderId);
    if ($event !== null) {
        $users = db_select('fashion_users', ['id' => (string) $event['user_id']]);
        $override = [
            'reason' => (string) ($event['note'] ?? ''),
            'user_name' => $users !== [] ? (string) $users[0]['name'] : '',
            'created_at' => (string) $event['created_at'],
        ];
    }
    return [
        'percent' => $shop['percent'],
        'stage_id' => $shop['stage_id'],
        'stage_name' => $stageName,
        'due_kobo' => $due,
        'paid_kobo' => $paid,
        'remaining_kobo' => $paid >= $due ? 0 : $due - $paid,
        'warning' => $warning,
        'override' => $override,
    ];
}

function deposit_override(string $orderId, string $reason): array
{
    require_module('fashion.production');
    if (!deposit_is_owner()) {
        http_response_code(403);
        throw new HttpException(t('not_allowed'), 403);
    }
    if (!is_uuid($orderId) || db_select('fashion_orders', ['id' => $orderId]) === []) {
        http_response_code(404);
        throw new HttpException(t('not_found'), 404);
    }
    $reason = trim($reason);
    if ($reason === '' || strlen($reason) > 500) {
        return ['error' => 'deposit_override_reason_required'];
    }
    if (deposit_override_event($orderId) !== null) {
        return ['error' => 'deposit_override_once'];
    }
    $next = stage_next($orderId);
    if ($next === null) {
        return ['error' => 'no_next_stage'];
    }
    if (deposit_move_block($orderId, (string) $next['id']) === null) {
        return ['error' => 'deposit_override_not_needed'];
    }
    $userId = current_user_id();
    if ($userId === null) {
        throw new RuntimeException('Missing session.');
    }
    db_insert('fashion_stage_events', [
        'order_id' => $orderId,
        'stage_id' => (string) $next['id'],
        'user_id' => $userId,
        'note' => $reason,
        'kind' => 'override',
        'created_at' => app_now()->format('Y-m-d H:i:s'),
    ]);
    if ((string) ($next['role'] ?? '') === 'ready') {
        require_once __DIR__ . '/messages.php';
        message_queue_ready($orderId);
    }
    return ['stage_id' => (string) $next['id'], 'code' => (string) $next['code']];
}
