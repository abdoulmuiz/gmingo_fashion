<?php

declare(strict_types=1);

require_once __DIR__ . '/modules.php';

function stage_definitions(): array
{
    require_module('fashion.production');
    return db_select('fashion_stage_definitions', [], ['order' => 'sort_order ASC, name ASC', 'limit' => 100]);
}

function stage_active(): array
{
    $rows = [];
    foreach (stage_definitions() as $stage) {
        if ((int) $stage['active'] === 1) {
            $rows[] = $stage;
        }
    }
    return $rows;
}

function stage_latest(string $orderId): ?array
{
    $rows = db_query(
        'SELECT sd.id, sd.code, sd.name, sd.sort_order, sd.active
         FROM fashion_stage_events se
         INNER JOIN fashion_stage_definitions sd ON sd.id = se.stage_id AND sd.tenant_id = :tenant_id
         WHERE se.tenant_id = :tenant_id AND se.order_id = :order_id
         ORDER BY se.seq DESC
         LIMIT 1',
        ['order_id' => $orderId]
    );
    if ($rows === []) {
        return null;
    }
    return $rows[0];
}

function stage_next(string $orderId): ?array
{
    require_module('fashion.production');
    $active = stage_active();
    if ($active === []) {
        return null;
    }
    $current = stage_latest($orderId);
    if ($current === null) {
        return $active[1] ?? null;
    }
    $sort = (int) $current['sort_order'];
    foreach ($active as $stage) {
        if ((int) $stage['sort_order'] > $sort) {
            return $stage;
        }
    }
    return null;
}

function stage_advance(string $orderId): array
{
    require_module('fashion.production');
    if (!is_uuid($orderId) || db_select('fashion_orders', ['id' => $orderId]) === []) {
        http_response_code(404);
        throw new HttpException(t('not_found'), 404);
    }
    $next = stage_next($orderId);
    if ($next === null) {
        return ['error' => 'no_next_stage'];
    }
    $userId = current_user_id();
    if ($userId === null) {
        throw new RuntimeException('Missing session.');
    }
    db_insert('fashion_stage_events', [
        'order_id' => $orderId,
        'stage_id' => (string) $next['id'],
        'user_id' => $userId,
        'note' => null,
        'created_at' => date('Y-m-d H:i:s'),
    ]);
    return ['stage_id' => (string) $next['id'], 'code' => (string) $next['code']];
}

function stage_board(): array
{
    require_module('fashion.production');
    if (!module_enabled('fashion.orders')) {
        return ['orders_off' => true, 'groups' => []];
    }
    $stages = stage_definitions();
    $firstId = null;
    foreach ($stages as $stage) {
        if ((int) $stage['active'] === 1) {
            $firstId = (string) $stage['id'];
            break;
        }
    }
    if ($firstId === null && $stages !== []) {
        $firstId = (string) $stages[0]['id'];
    }
    $orders = db_query(
        'SELECT o.id, o.order_number, o.due_date, c.name AS customer_name, u.name AS assigned_name,
            (SELECT se.stage_id FROM fashion_stage_events se
             WHERE se.tenant_id = :tenant_id AND se.order_id = o.id
             ORDER BY se.seq DESC LIMIT 1) AS stage_id
         FROM fashion_orders o
         INNER JOIN fashion_customers c ON c.id = o.customer_id AND c.tenant_id = :tenant_id
         LEFT JOIN fashion_users u ON u.id = o.assigned_user_id AND u.tenant_id = :tenant_id
         WHERE o.tenant_id = :tenant_id
         ORDER BY o.due_date ASC, o.order_number ASC
         LIMIT 300'
    );
    $byStage = [];
    foreach ($orders as $order) {
        $stageId = is_string($order['stage_id']) && $order['stage_id'] !== '' ? $order['stage_id'] : $firstId;
        if ($stageId === null) {
            continue;
        }
        $byStage[$stageId][] = [
            'id' => (string) $order['id'],
            'order_number' => (string) $order['order_number'],
            'customer_name' => (string) $order['customer_name'],
            'due_date' => (string) $order['due_date'],
            'assigned_name' => is_string($order['assigned_name']) && $order['assigned_name'] !== '' ? $order['assigned_name'] : null,
        ];
    }
    $groups = [];
    foreach ($stages as $stage) {
        $cards = $byStage[(string) $stage['id']] ?? [];
        if ((int) $stage['active'] !== 1 && $cards === []) {
            continue;
        }
        $groups[] = [
            'name' => (string) $stage['name'],
            'orders' => $cards,
        ];
    }
    return ['orders_off' => false, 'groups' => $groups];
}

function stage_rename(string $id, string $name): array
{
    require_module('fashion.production');
    $name = trim($name);
    if (!is_uuid($id) || $name === '' || strlen($name) > 80) {
        return ['error' => 'required'];
    }
    db_update('fashion_stage_definitions', ['name' => $name], ['id' => $id]);
    return [];
}

function stage_reorder(string $id, string $direction): void
{
    require_module('fashion.production');
    if (!is_uuid($id) || !in_array($direction, ['up', 'down'], true)) {
        return;
    }
    $stages = stage_definitions();
    $index = null;
    foreach ($stages as $i => $stage) {
        if ((string) $stage['id'] === $id) {
            $index = $i;
            break;
        }
    }
    if ($index === null) {
        return;
    }
    $neighbor = $direction === 'up' ? $index - 1 : $index + 1;
    if (!isset($stages[$neighbor])) {
        return;
    }
    $here = $stages[$index];
    $there = $stages[$neighbor];
    db_begin();
    try {
        db_update('fashion_stage_definitions', ['sort_order' => (int) $there['sort_order']], ['id' => (string) $here['id']]);
        db_update('fashion_stage_definitions', ['sort_order' => (int) $here['sort_order']], ['id' => (string) $there['id']]);
        db_commit();
    } catch (Throwable $e) {
        db_rollback();
        throw $e;
    }
}

function stage_add(string $name): array
{
    require_module('fashion.production');
    $name = trim($name);
    if ($name === '' || strlen($name) > 80) {
        return ['error' => 'required'];
    }
    $sort = 1;
    foreach (stage_definitions() as $stage) {
        $sort = max($sort, (int) $stage['sort_order'] + 1);
    }
    db_insert('fashion_stage_definitions', [
        'code' => stage_code($name),
        'name' => $name,
        'sort_order' => $sort,
        'active' => 1,
    ]);
    return [];
}

function stage_set_active(string $id, bool $active): void
{
    require_module('fashion.production');
    if (!is_uuid($id)) {
        return;
    }
    db_update('fashion_stage_definitions', ['active' => $active ? 1 : 0], ['id' => $id]);
}

function stage_code(string $name): string
{
    $code = strtolower($name);
    $code = preg_replace('/[^a-z0-9]+/', '_', $code);
    $code = is_string($code) ? trim($code, '_') : '';
    if ($code === '') {
        $code = 'stage';
    }
    $code = substr($code, 0, 30);
    $base = $code;
    $number = 2;
    while (db_select('fashion_stage_definitions', ['code' => $code]) !== []) {
        $code = substr($base, 0, 28) . '_' . (string) $number;
        $number++;
        if ($number > 50) {
            $code = 'stage_' . substr(uuid(), 0, 8);
            break;
        }
    }
    return $code;
}
