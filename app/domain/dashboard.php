<?php

declare(strict_types=1);

require_once __DIR__ . '/orders.php';
require_once __DIR__ . '/payments.php';

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
        $cards[] = dashboard_card('card_low_stock', '0', t('inventory_later'));
    }
    return [
        'greeting' => dashboard_greeting($hour),
        'cards' => $cards,
        'attention' => $attention,
        'orders_on' => $ordersOn,
        'empty' => $cards === [],
    ];
}

function dashboard_card(string $key, string $value, string $note = ''): array
{
    return [
        'key' => $key,
        'label' => t($key),
        'value' => $value,
        'note' => $note,
    ];
}

function dashboard_order_counts(): array
{
    $today = date('Y-m-d');
    [$weekStart, $weekEnd] = dashboard_week($today);
    $active = 0;
    $week = 0;
    $overdue = 0;
    $ready = 0;
    $attention = [];
    foreach (order_list('all', '') as $order) {
        $code = $order['stage_code'];
        $finished = in_array($code, ['delivered', 'completed'], true);
        $due = (string) $order['due_date'];
        $isOverdue = $due < $today && !$finished;
        $isReady = $code === 'ready';
        if (!$isReady && !$finished) {
            $active++;
        }
        if ($due >= $weekStart && $due <= $weekEnd && !$finished) {
            $week++;
        }
        if ($isOverdue) {
            $overdue++;
        }
        if ($isReady) {
            $ready++;
        }
        if ($isOverdue || $order['urgent']) {
            $attention[] = [
                'id' => (string) $order['id'],
                'order_number' => (string) $order['order_number'],
                'customer_name' => (string) $order['customer_name'],
                'due_date' => $due,
                'urgent' => $order['urgent'] === true,
                'overdue' => $isOverdue,
            ];
        }
    }
    return [
        'active' => $active,
        'week' => $week,
        'overdue' => $overdue,
        'ready' => $ready,
        'attention' => $attention,
    ];
}
