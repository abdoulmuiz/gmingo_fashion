<?php

declare(strict_types=1);

require_once __DIR__ . '/orders.php';
require_once __DIR__ . '/payments.php';
require_once __DIR__ . '/stock.php';

function dashboard_hello(int $hour): string
{
    if ($hour < 12) {
        return t('good_morning');
    }
    if ($hour < 17) {
        return t('good_afternoon');
    }
    return t('good_evening');
}

function dashboard_greeting(?int $hour = null): string
{
    require_login();
    $name = '';
    $userId = current_user_id();
    if ($userId !== null) {
        $rows = db_select('fashion_users', ['id' => $userId]);
        if ($rows !== []) {
            $name = (string) $rows[0]['name'];
        }
    }
    if ($hour === null) {
        $hour = (int) date('G');
    }
    return t('greeting', ['hello' => dashboard_hello($hour), 'name' => $name]);
}

function dashboard_week(string $today): array
{
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $today);
    if ($date === false) {
        $date = new DateTimeImmutable('today');
    }
    $start = $date->modify('-' . ((int) $date->format('N') - 1) . ' days');
    return [$start->format('Y-m-d'), $start->modify('+6 days')->format('Y-m-d')];
}

function dashboard_home(?int $hour = null): array
{
    require_login();
    $cards = [];
    $attention = [];
    $ordersOn = module_enabled('fashion.orders');
    if ($ordersOn) {
        $counts = dashboard_order_counts();
        $cards[] = dashboard_card('card_active', (string) $counts['active']);
        $cards[] = dashboard_card('card_week', (string) $counts['week']);
        $cards[] = dashboard_card('card_overdue', (string) $counts['overdue']);
        $cards[] = dashboard_card('card_ready', (string) $counts['ready']);
        $attention = $counts['attention'];
    }
    if (module_enabled('fashion.finance')) {
        $cards[] = dashboard_card('card_money', money((int) payment_outstanding()['total_kobo']));
    }
    if (module_enabled('fashion.inventory')) {
        $cards[] = dashboard_card('card_low_stock', (string) stock_low_count(), '', '/stock');
    }
    return [
        'greeting' => dashboard_greeting($hour),
        'cards' => $cards,
        'attention' => $attention,
        'orders_on' => $ordersOn,
        'empty' => $cards === [],
    ];
}

function dashboard_card(string $key, string $value, string $note = '', string $href = ''): array
{
    return [
        'key' => $key,
        'label' => t($key),
        'value' => $value,
        'note' => $note,
        'href' => $href,
    ];
}

function dashboard_order_counts(): array
{
    require_module('fashion.orders');
    $today = app_today();
    [$weekStart, $weekEnd] = dashboard_week($today);
    $stageRole = order_stage_sql('role');
    $open = '(' . $stageRole . ' IS NULL OR ' . $stageRole . ' NOT IN (\'ready\', \'finished\'))';
    $unfinished = '(' . $stageRole . ' IS NULL OR ' . $stageRole . ' <> \'finished\')';
    $rows = db_query(
        'SELECT
            COALESCE(SUM(CASE WHEN ' . $open . ' THEN 1 ELSE 0 END), 0) AS active_count,
            COALESCE(SUM(CASE WHEN o.due_date >= :week_start AND o.due_date <= :week_end AND ' . $unfinished . ' THEN 1 ELSE 0 END), 0) AS week_count,
            COALESCE(SUM(CASE WHEN o.due_date < :today AND ' . $unfinished . ' THEN 1 ELSE 0 END), 0) AS overdue_count,
            COALESCE(SUM(CASE WHEN ' . $stageRole . ' = \'ready\' THEN 1 ELSE 0 END), 0) AS ready_count
         FROM fashion_orders o
         WHERE o.tenant_id = :tenant_id',
        ['week_start' => $weekStart, 'week_end' => $weekEnd, 'today' => $today]
    );
    $counts = $rows[0] ?? [];
    $attentionRows = db_query(
        'SELECT o.id, o.order_number, o.due_date, o.urgent, c.name AS customer_name, '
            . $stageRole . ' AS stage_role
         FROM fashion_orders o
         INNER JOIN fashion_customers c ON c.id = o.customer_id AND c.tenant_id = :tenant_id
         WHERE o.tenant_id = :tenant_id
           AND ((o.due_date < :today AND ' . $unfinished . ') OR o.urgent = 1)
         ORDER BY o.due_date ASC, o.order_number ASC',
        ['today' => $today]
    );
    $attention = [];
    foreach ($attentionRows as $row) {
        $role = $row['stage_role'];
        $finished = is_string($role) && $role === 'finished';
        $attention[] = [
            'id' => (string) $row['id'],
            'order_number' => (string) $row['order_number'],
            'customer_name' => (string) $row['customer_name'],
            'due_date' => (string) $row['due_date'],
            'urgent' => (int) $row['urgent'] === 1,
            'overdue' => (string) $row['due_date'] < $today && !$finished,
        ];
    }
    return [
        'active' => (int) ($counts['active_count'] ?? 0),
        'week' => (int) ($counts['week_count'] ?? 0),
        'overdue' => (int) ($counts['overdue_count'] ?? 0),
        'ready' => (int) ($counts['ready_count'] ?? 0),
        'attention' => $attention,
    ];
}
