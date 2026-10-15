<?php

declare(strict_types=1);

require_once __DIR__ . '/support.php';
require_once dirname(__DIR__) . '/app/domain/accounts.php';
require_once dirname(__DIR__) . '/app/domain/customers.php';
require_once dirname(__DIR__) . '/app/domain/orders.php';
require_once dirname(__DIR__) . '/app/domain/dashboard.php';
require_once dirname(__DIR__) . '/app/domain/payments.php';
require_once dirname(__DIR__) . '/app/fashion/controllers/orders.php';
require_once dirname(__DIR__) . '/app/fashion/controllers/payments.php';

tests_reset();

$shop = business_register([
    'business_name' => 'Paging Shop',
    'owner_name' => 'Amina Bello',
    'email' => 'pages@example.com',
    'password' => 'password',
    'phone' => '08031234567',
    'company_type' => 'tailor',
]);
check(isset($shop['user_id']), 'page tests start from a signed-in shop');

$customer = customer_create([
    'name' => 'Paging Customer',
    'phone' => '08031112233',
    'gender' => 'female',
    'customer_type' => 'regular',
]);
$garment = db_select('fashion_garment_types', ['code' => 'kaftan'])[0];
$readyStage = db_select('fashion_stage_definitions', ['code' => 'ready'])[0];
$finishedStage = db_select('fashion_stage_definitions', ['code' => 'delivered'])[0];
$today = date('Y-m-d');
$yesterday = date('Y-m-d', strtotime($today . ' -1 day'));
[$weekStart, $weekEnd] = dashboard_week($today);
$beforeWeek = date('Y-m-d', strtotime($weekStart . ' -1 day'));
$afterWeek = date('Y-m-d', strtotime($weekEnd . ' +1 day'));
$userId = (string) current_user_id();
$now = date('Y-m-d H:i:s');

$buckets = [
    ['count' => 50, 'due' => $beforeWeek, 'stage' => null, 'urgent' => 0, 'price' => 200000, 'paid' => 0],
    ['count' => 80, 'due' => $beforeWeek, 'stage' => $finishedStage['id'], 'urgent' => 0, 'price' => 500000, 'paid' => 0],
    ['count' => 20, 'due' => $yesterday, 'stage' => $readyStage['id'], 'urgent' => 0, 'price' => 100000, 'paid' => 0],
    ['count' => 100, 'due' => $today, 'stage' => null, 'urgent' => 0, 'price' => 100000, 'paid' => 0],
    ['count' => 40, 'due' => $today, 'stage' => $finishedStage['id'], 'urgent' => 0, 'price' => 100000, 'paid' => 100000],
    ['count' => 40, 'due' => $weekEnd, 'stage' => null, 'urgent' => 0, 'price' => 100000, 'paid' => 0],
    ['count' => 30, 'due' => $afterWeek, 'stage' => $readyStage['id'], 'urgent' => 0, 'price' => 100000, 'paid' => 0],
    ['count' => 40, 'due' => $afterWeek, 'stage' => null, 'urgent' => 1, 'price' => 100000, 'paid' => 50000],
];
$number = 1000;
foreach ($buckets as $bucket) {
    for ($i = 0; $i < $bucket['count']; $i++) {
        $number++;
        $orderId = db_insert('fashion_orders', [
            'customer_id' => $customer['id'],
            'order_number' => 'TK' . (string) $number,
            'due_date' => $bucket['due'],
            'urgent' => $bucket['urgent'],
            'group_id' => null,
            'assigned_user_id' => null,
            'customer_supplied_fabric' => 0,
            'yards_received' => null,
            'yards_used' => null,
            'created_at' => $now,
        ]);
        db_insert('fashion_order_items', [
            'order_id' => $orderId,
            'garment_type_id' => $garment['id'],
            'quantity' => 1,
            'price_kobo' => $bucket['price'],
            'fabric' => '',
            'notes' => '',
            'product_id' => null,
        ]);
        if ($bucket['stage'] !== null) {
            db_insert('fashion_stage_events', [
                'order_id' => $orderId,
                'stage_id' => $bucket['stage'],
                'user_id' => $userId,
                'note' => null,
                'created_at' => $now,
            ]);
        }
        if ($bucket['paid'] > 0) {
            db_insert('fashion_payments', [
                'order_id' => $orderId,
                'amount_kobo' => $bucket['paid'],
                'method' => 'cash',
                'reverses_payment_id' => null,
                'kind' => 'payment',
                'user_id' => $userId,
                'created_at' => $now,
            ]);
        }
    }
}
check_eq($number, 1400, 'the page test builds 400 orders');

$week = 140;
if ($yesterday >= $weekStart && $yesterday <= $weekEnd) {
    $week += 20;
}
$expectedMoney = 71000000;

$other = business_register([
    'business_name' => 'Other Paging Shop',
    'owner_name' => 'Bala',
    'email' => 'pages-other@example.com',
    'password' => 'password',
    'phone' => '08037654321',
    'company_type' => 'tailor',
]);
$otherCustomer = customer_create([
    'name' => 'Other Paging',
    'phone' => '08130001122',
    'gender' => 'male',
    'customer_type' => 'regular',
]);
$otherGarment = db_select('fashion_garment_types', ['code' => 'kaftan'])[0];
$otherOrder = db_insert('fashion_orders', [
    'customer_id' => $otherCustomer['id'],
    'order_number' => 'TK9001',
    'due_date' => $yesterday,
    'urgent' => 1,
    'group_id' => null,
    'assigned_user_id' => null,
    'customer_supplied_fabric' => 0,
    'yards_received' => null,
    'yards_used' => null,
    'created_at' => $now,
]);
db_insert('fashion_order_items', [
    'order_id' => $otherOrder,
    'garment_type_id' => $otherGarment['id'],
    'quantity' => 1,
    'price_kobo' => 900000,
    'fabric' => '',
    'notes' => '',
    'product_id' => null,
]);
auth_login((string) $shop['user_id'], (string) $shop['tenant_id']);

check_eq(order_count('all', ''), 400, 'the order count includes every order');
check_eq(order_count('active', ''), 230, 'the active count includes every open order');
$counts = dashboard_order_counts();
check_eq($counts['active'], 230, 'the active card counts every open order');
check_eq($counts['week'], $week, 'the week card counts every unfinished order due this week');
check_eq($counts['overdue'], 70, 'the overdue card counts every unfinished order past its due date');
check_eq($counts['ready'], 50, 'the ready card counts every order on the ready stage');
check_eq(count($counts['attention']), 110, 'needs attention includes every overdue or urgent order');
$owing = payment_outstanding();
check_eq($owing['total_kobo'], $expectedMoney, 'outstanding adds every balance');
check_eq(count($owing['rows']), 25, 'the outstanding list shows one page');
$pageSum = 0;
foreach ($owing['rows'] as $row) {
    $pageSum += $row['balance_kobo'];
}
check($pageSum !== $expectedMoney, 'the outstanding total is not the sum of one page');
$dash = dashboard_home(17);
foreach ($dash['cards'] as $card) {
    if ($card['key'] === 'card_active') {
        check_eq($card['value'], '230', 'the home screen shows the full active count');
    }
    if ($card['key'] === 'card_week') {
        check_eq($card['value'], (string) $week, 'the home screen shows the full week count');
    }
    if ($card['key'] === 'card_overdue') {
        check_eq($card['value'], '70', 'the home screen shows the full overdue count');
    }
    if ($card['key'] === 'card_ready') {
        check_eq($card['value'], '50', 'the home screen shows the full ready count');
    }
    if ($card['key'] === 'card_money') {
        check_eq($card['value'], money($expectedMoney), 'the home screen shows the full outstanding total');
    }
}

$seen = [];
$previousNumber = '';
$previousDue = '';
for ($page = 1; $page <= 16; $page++) {
    $orders = order_list('all', '', $page);
    check_eq(count($orders), 25, 'page ' . (string) $page . ' of all orders has 25 rows');
    foreach ($orders as $order) {
        check(!isset($seen[$order['order_number']]), 'pages do not repeat ' . $order['order_number']);
        $seen[$order['order_number']] = true;
        if ($previousDue !== '') {
            $inOrder = $order['due_date'] > $previousDue
                || ($order['due_date'] === $previousDue && $order['order_number'] > $previousNumber);
            check($inOrder, 'pages stay in due date order');
        }
        $previousDue = $order['due_date'];
        $previousNumber = $order['order_number'];
    }
}
check_eq(count($seen), 400, 'the pages together list every order');
check(order_list('all', '', 17) === [], 'a page past the end is empty');

$activeSeen = [];
for ($page = 1; $page <= 9; $page++) {
    $orders = order_list('active', 'Paging', $page);
    check_eq(count($orders), 25, 'filtered page ' . (string) $page . ' has 25 rows');
    foreach ($orders as $order) {
        $activeSeen[$order['order_number']] = true;
    }
}
$lastActive = order_list('active', 'Paging', 10);
check_eq(count($lastActive), 5, 'the last filtered page keeps the leftover rows');
foreach ($lastActive as $order) {
    $activeSeen[$order['order_number']] = true;
}
check_eq(count($activeSeen), 230, 'search and the active filter stay in force on every page');

$savedGet = $_GET;
$savedUri = $_SERVER['REQUEST_URI'] ?? '';
$_GET = ['tab' => 'active', 'q' => 'Paging', 'page' => '2'];
$_SERVER['REQUEST_URI'] = base_path() . '/orders?tab=active&q=Paging&page=2';
ob_start();
orders_index();
$listPage = (string) ob_get_clean();
check(str_contains($listPage, 'href="' . e(url('/orders?tab=active&q=Paging')) . '"'), 'previous keeps the filter and search');
check(str_contains($listPage, 'href="' . e(url('/orders?tab=active&q=Paging&page=3')) . '"'), 'next keeps the filter and search');
check_eq(substr_count($listPage, 'class="card link-card"'), 25, 'the order page shows 25 orders');

$_GET = ['page' => '2'];
$_SERVER['REQUEST_URI'] = base_path() . '/outstanding?page=2';
ob_start();
outstanding_show();
$balancePage = (string) ob_get_clean();
check(str_contains($balancePage, money($expectedMoney)), 'the outstanding page shows the full total');
check(str_contains($balancePage, 'href="' . e(url('/outstanding?page=1')) . '"'), 'outstanding has a previous page');
check(str_contains($balancePage, 'href="' . e(url('/outstanding?page=3')) . '"'), 'outstanding has a next page');
check_eq(substr_count($balancePage, 'class="line"'), 25, 'the outstanding page shows 25 balances');
$_GET = $savedGet;
$_SERVER['REQUEST_URI'] = $savedUri;
