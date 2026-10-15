<?php

declare(strict_types=1);

require_once __DIR__ . '/support.php';
require_once dirname(__DIR__) . '/app/domain/accounts.php';
require_once dirname(__DIR__) . '/app/domain/orders.php';
require_once dirname(__DIR__) . '/app/fashion/controllers/orders.php';

tests_reset();
date_default_timezone_set('Africa/Lagos');

function orders_test_stage(string $orderId, string $code): void
{
    $stage = db_select('fashion_stage_definitions', ['code' => $code])[0];
    db_insert('fashion_stage_events', [
        'order_id' => $orderId,
        'stage_id' => $stage['id'],
        'user_id' => (string) current_user_id(),
        'note' => null,
        'created_at' => date('Y-m-d H:i:s'),
    ]);
}

function orders_test_pay(string $orderId, int $amount): void
{
    db_insert('fashion_payments', [
        'order_id' => $orderId,
        'amount_kobo' => $amount,
        'method' => 'transfer',
        'reverses_payment_id' => null,
        'kind' => 'payment',
        'user_id' => (string) current_user_id(),
        'created_at' => date('Y-m-d H:i:s'),
    ]);
}

function orders_test_make(string $customerId, string $garmentId, string $due, bool $urgent): array
{
    return order_create([
        'customer_id' => $customerId,
        'due_date' => $due,
        'urgent' => $urgent ? '1' : '',
        'items' => [[
            'garment_type_id' => $garmentId,
            'quantity' => '1',
            'price' => '1000',
            'fabric' => '',
            'notes' => '',
        ]],
    ]);
}

function orders_test_numbers(string $filter, string $search = ''): array
{
    return array_column(order_list($filter, $search), 'order_number');
}

$shop = business_register([
    'business_name' => 'Order Shop',
    'owner_name' => 'Amina Bello',
    'email' => 'orders@example.com',
    'password' => 'password',
    'phone' => '08031234567',
    'company_type' => 'tailor',
]);
check(isset($shop['user_id']), 'order tests start from a signed-in shop');
$counter = db_select('fashion_order_counters')[0];
check_eq($counter['prefix'], 'TK', 'a tailor shop starts with the TK prefix');
db_update('fashion_order_counters', ['last_number' => 1047], ['id' => $counter['id']]);

$amina = customer_create([
    'name' => 'Amina Bello',
    'phone' => '08031112233',
    'gender' => 'female',
    'customer_type' => 'vip',
]);
$musa = customer_create([
    'name' => 'Musa Search',
    'phone' => '07039876543',
    'gender' => 'male',
    'customer_type' => 'regular',
]);
$kaftan = db_select('fashion_garment_types', ['code' => 'kaftan'])[0];
$shirt = db_select('fashion_garment_types', ['code' => 'shirt'])[0];
$lagos = new DateTimeZone('Africa/Lagos');
$todayDate = new DateTimeImmutable(app_today(), $lagos);
$today = $todayDate->format('Y-m-d');
$yesterday = $todayDate->modify('-1 day')->format('Y-m-d');
$nextWeek = $todayDate->modify('+7 days')->format('Y-m-d');

$rejected = order_create([
    'customer_id' => '',
    'due_date' => '15/10/2026',
    'items' => [[
        'garment_type_id' => '',
        'quantity' => '',
        'price' => '',
        'fabric' => '',
        'notes' => '',
    ]],
]);
check(isset($rejected['errors']['customer_id'], $rejected['errors']['due_date'], $rejected['errors']['items']), 'an order needs a customer, a due date and an item');
check_eq((int) db_select('fashion_order_counters')[0]['last_number'], 1047, 'a rejected order does not take a number');

$first = order_create([
    'customer_id' => $amina['id'],
    'due_date' => $nextWeek,
    'urgent' => '',
    'items' => [
        [
            'garment_type_id' => $kaftan['id'],
            'quantity' => '2',
            'price' => '15000',
            'fabric' => 'ankara',
            'notes' => 'blue',
            'product_id' => 'not-used',
        ],
        [
            'garment_type_id' => $shirt['id'],
            'quantity' => '1',
            'price' => '5000',
            'fabric' => 'cotton',
            'notes' => '',
        ],
    ],
]);
check_eq($first['order_number'], 'TK1048', 'the next order number uses the prefix and counter');
$stored = db_select('fashion_orders', ['id' => $first['id']])[0];
check(!array_key_exists('total_kobo', $stored), 'the order total is not stored on the order');
$storedItem = db_select('fashion_order_items', ['order_id' => $first['id'], 'garment_type_id' => $kaftan['id']])[0];
check($storedItem['product_id'] === null, 'product id stays unused');
orders_test_stage($first['id'], 'sewing');
orders_test_pay($first['id'], 1000000);
$profile = order_find($first['id']);
check_eq($profile['total_kobo'], 3500000, 'the total is quantity times price for every item');
check_eq($profile['balance_kobo'], 2500000, 'the balance is the total minus payments');
check($profile['paid'] === false, 'a partly paid order is unpaid');
check_eq($profile['stage_code'], 'sewing', 'the current stage is the latest stage event');
check_eq($profile['stage_role'], 'open', 'sewing stays an open stage role');
check_eq(count($profile['items']), 2, 'an order keeps several items');

$other = business_register([
    'business_name' => 'Other Order Shop',
    'owner_name' => 'Bala',
    'email' => 'orders-other@example.com',
    'password' => 'password',
    'phone' => '08037654321',
    'company_type' => 'tailor',
]);
check(isset($other['tenant_id']), 'a second shop can sign up');
$otherCounter = db_select('fashion_order_counters')[0];
db_update('fashion_order_counters', ['last_number' => 1047], ['id' => $otherCounter['id']]);
$otherCustomer = customer_create([
    'name' => 'Bala Customer',
    'phone' => '08130001122',
    'gender' => 'male',
    'customer_type' => 'regular',
]);
$otherGarment = db_select('fashion_garment_types', ['code' => 'kaftan'])[0];
$otherOrder = orders_test_make($otherCustomer['id'], $otherGarment['id'], $nextWeek, false);
check_eq($otherOrder['order_number'], 'TK1048', 'each business has its own order numbers');
check(order_find($first['id']) === null, 'another shop cannot open the order');
check(order_list('all', 'Amina') === [], 'another shop cannot search the order');

auth_login((string) $shop['user_id'], (string) $shop['tenant_id']);
$overdue = orders_test_make($amina['id'], $kaftan['id'], $yesterday, false);
$dueToday = orders_test_make($amina['id'], $kaftan['id'], $today, false);
orders_test_pay($dueToday['id'], 100000);
$urgent = orders_test_make($amina['id'], $kaftan['id'], $nextWeek, true);
orders_test_pay($urgent['id'], 100000);
$ready = orders_test_make($amina['id'], $kaftan['id'], $nextWeek, false);
orders_test_stage($ready['id'], 'ready');
orders_test_pay($ready['id'], 100000);
$delivered = orders_test_make($amina['id'], $kaftan['id'], $yesterday, false);
orders_test_stage($delivered['id'], 'delivered');
orders_test_pay($delivered['id'], 100000);
$readyLate = orders_test_make($amina['id'], $kaftan['id'], $yesterday, false);
orders_test_stage($readyLate['id'], 'ready');
orders_test_pay($readyLate['id'], 100000);
$musaOrder = orders_test_make($musa['id'], $shirt['id'], $nextWeek, false);
$completedStage = db_insert('fashion_stage_definitions', [
    'code' => 'completed',
    'name' => 'Completed',
    'sort_order' => 20,
    'active' => 1,
    'role' => 'open',
]);
$completed = orders_test_make($amina['id'], $kaftan['id'], $nextWeek, false);
orders_test_stage($completed['id'], 'completed');
orders_test_pay($completed['id'], 100000);
$savedStage = db_select('fashion_stage_definitions', ['id' => $completedStage]);
check(count($savedStage) === 1, 'the completed stage is stored for this business');
check_eq((string) ($savedStage[0]['code'] ?? ''), 'completed', 'the new stage code is completed');
check_eq((string) ($savedStage[0]['tenant_id'] ?? ''), (string) $shop['tenant_id'], 'the new stage belongs to this business');

$numbers = [
    'active' => $first['order_number'],
    'overdue' => $overdue['order_number'],
    'due_today' => $dueToday['order_number'],
    'urgent' => $urgent['order_number'],
    'ready' => $ready['order_number'],
    'delivered' => $delivered['order_number'],
    'ready_late' => $readyLate['order_number'],
    'musa' => $musaOrder['order_number'],
    'completed' => $completed['order_number'],
];
$all = orders_test_numbers('all');
check(count($all) === 9, 'all lists every order in this shop');
check(count(array_keys($all, 'TK1048', true)) === 1, 'this shop lists its own TK1048 once');
foreach (['active', 'overdue', 'due_today', 'urgent', 'ready', 'delivered', 'unpaid'] as $tab) {
    check(in_array($tab, order_tabs(), true), $tab . ' is a filter tab');
}
$active = orders_test_numbers('active');
check(in_array($numbers['active'], $active, true), 'active includes an order still in sewing');
check(in_array($numbers['overdue'], $active, true), 'active includes an overdue order that is not finished');
check(in_array($numbers['due_today'], $active, true), 'active includes an order due today');
check(in_array($numbers['urgent'], $active, true), 'active includes an urgent order');
check(in_array($numbers['musa'], $active, true), 'active includes an order with no stage event');
check(!in_array($numbers['ready'], $active, true), 'active leaves out a ready order');
check(!in_array($numbers['ready_late'], $active, true), 'active leaves out a ready order that is late');
check(!in_array($numbers['delivered'], $active, true), 'active leaves out a delivered order');
check(in_array($numbers['completed'], $active, true), 'an extra final stage stays active while its role is open');

$overdueRows = orders_test_numbers('overdue');
check(in_array($numbers['overdue'], $overdueRows, true), 'overdue includes an unfinished order past its due date');
check(in_array($numbers['ready_late'], $overdueRows, true), 'overdue includes a ready order past its due date');
check(!in_array($numbers['delivered'], $overdueRows, true), 'overdue leaves out a delivered order');
check(!in_array($numbers['due_today'], $overdueRows, true), 'overdue leaves out an order due today');

check_eq(orders_test_numbers('due_today'), [$numbers['due_today']], 'due today lists only orders due today');
check_eq(orders_test_numbers('urgent'), [$numbers['urgent']], 'urgent lists only flagged orders');
$readyRows = orders_test_numbers('ready');
check(in_array($numbers['ready'], $readyRows, true) && in_array($numbers['ready_late'], $readyRows, true), 'ready lists orders on the ready stage');
check(!in_array($numbers['delivered'], $readyRows, true), 'ready leaves out a delivered order');
$deliveredRows = orders_test_numbers('delivered');
check(in_array($numbers['delivered'], $deliveredRows, true), 'delivered lists a delivered order');
check(!in_array($numbers['completed'], $deliveredRows, true), 'delivered follows the finished role');
check(!in_array($numbers['ready'], $deliveredRows, true), 'delivered leaves out a ready order');

$unpaid = orders_test_numbers('unpaid');
check(in_array($numbers['active'], $unpaid, true), 'unpaid includes a partly paid order');
check(in_array($numbers['overdue'], $unpaid, true), 'unpaid includes an order with no payment');
check(in_array($numbers['musa'], $unpaid, true), 'unpaid includes the second customer');
check(!in_array($numbers['due_today'], $unpaid, true), 'unpaid leaves out an order paid in full');
check(order_find($dueToday['id'])['paid'] === true, 'an order paid in full is tagged paid');

check_eq(orders_test_numbers('all', 'TK1051'), [$numbers['urgent']], 'search matches an order number');
check_eq(orders_test_numbers('all', 'Musa'), [$numbers['musa']], 'search matches a customer name');
check(orders_test_numbers('all', '%') === [], 'search wildcards are not treated as match-all');
check_eq(orders_test_numbers('unpaid', 'Musa'), [$numbers['musa']], 'search keeps the selected filter');

$beforePrefix = (int) db_select('fashion_order_counters')[0]['last_number'];
db_update('fashion_order_counters', ['prefix' => 'AB'], ['id' => $counter['id']]);
$prefixed = orders_test_make($amina['id'], $kaftan['id'], $nextWeek, false);
check_eq($prefixed['order_number'], 'AB' . (string) ($beforePrefix + 1), 'a changed prefix is used for the next number');

$_SERVER['REQUEST_URI'] = base_path() . '/orders/' . $first['id'];
ob_start();
orders_show($first['id']);
$open = (string) ob_get_clean();
check(str_contains($open, 'TK1048'), 'the order page shows the order number');
check(str_contains($open, money(3500000)), 'the order page shows the calculated total');
check(str_contains($open, t('unpaid')), 'the order page shows the unpaid tag');
check(str_contains($open, t('stage')), 'the order page shows the stage when production is on');
check(str_contains($open, 'Sewing'), 'the order page names the current stage');
check(str_contains($open, t('balance')), 'the order page shows the balance when finance is on');
check(str_contains($open, money(2500000)), 'the order page shows the calculated balance');

module_save(['fashion.orders', 'fashion.measurements', 'fashion.communication']);
check(module_enabled('fashion.production') === false && module_enabled('fashion.finance') === false, 'production and finance can be switched off');
ob_start();
orders_show($first['id']);
$plain = (string) ob_get_clean();
check(str_contains($plain, 'TK1048'), 'the order page still opens without production and finance');
check(str_contains($plain, money(3500000)), 'the total stays on the order page');
check(str_contains($plain, t('unpaid')), 'the paid tag stays on the order page');
check(!str_contains($plain, t('stage')) && !str_contains($plain, 'Sewing'), 'the stage stays off the order page');
check(!str_contains($plain, t('balance')) && !str_contains($plain, money(2500000)), 'the balance stays off the order page');

module_save(['fashion.production', 'fashion.finance', 'fashion.measurements', 'fashion.communication']);
check(module_enabled('fashion.orders') === false, 'orders module is switched off');
$_SERVER['REQUEST_URI'] = base_path() . '/';
ob_start();
router_dispatch('GET', '/');
$closedHome = (string) ob_get_clean();
check(!str_contains($closedHome, 'href="' . url('/orders') . '"'), 'Orders link is hidden when orders is off');
foreach (['/orders', '/orders/new', '/orders/' . $first['id']] as $path) {
    try {
        ob_start();
        router_dispatch('GET', $path);
        ob_end_clean();
        check(false, $path . ' stays closed');
    } catch (HttpException $e) {
        ob_end_clean();
        check($e->status() === 403, $path . ' returns 403');
    }
}
try {
    router_dispatch('POST', '/orders');
    check(false, 'saving an order stays closed');
} catch (HttpException $e) {
    check($e->status() === 403, 'saving an order returns 403 when orders is off');
}
try {
    order_create([
        'customer_id' => $amina['id'],
        'due_date' => $nextWeek,
        'items' => [[
            'garment_type_id' => $kaftan['id'],
            'quantity' => '1',
            'price' => '1000',
            'fabric' => '',
            'notes' => '',
        ]],
    ]);
    check(false, 'creating an order stays closed');
} catch (HttpException $e) {
    check($e->status() === 403, 'creating an order returns 403 when orders is off');
}

$raceShop = business_register([
    'business_name' => 'Race Shop',
    'owner_name' => 'Race Owner',
    'email' => 'orders-race@example.com',
    'password' => 'password',
    'phone' => '08039998877',
    'company_type' => 'tailor',
]);
$raceCustomer = customer_create([
    'name' => 'Race Customer',
    'phone' => '08132223344',
    'gender' => 'female',
    'customer_type' => 'regular',
]);
$raceGarment = db_select('fashion_garment_types', ['code' => 'kaftan'])[0];
$start = microtime(true) + 2.5;
$workers = 8;
$processes = [];
for ($i = 0; $i < $workers; $i++) {
    $pipes = [];
    $process = proc_open(
        [
            PHP_BINARY,
            __DIR__ . '/order_race_worker.php',
            (string) $raceShop['tenant_id'],
            (string) $raceShop['user_id'],
            (string) $raceCustomer['id'],
            (string) $raceGarment['id'],
            sprintf('%.6F', $start),
        ],
        [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ],
        $pipes
    );
    check(is_resource($process), 'a race worker starts');
    fclose($pipes[0]);
    $processes[] = ['process' => $process, 'out' => $pipes[1], 'err' => $pipes[2]];
}
$issued = [];
foreach ($processes as $worker) {
    $issued[] = trim((string) stream_get_contents($worker['out']));
    $error = trim((string) stream_get_contents($worker['err']));
    $code = proc_close($worker['process']);
    check($code === 0, 'a race worker finishes' . ($error === '' ? '' : ' (' . $error . ')'));
}
$expected = [];
for ($n = 1001; $n <= 1008; $n++) {
    $expected[] = 'TK' . (string) $n;
}
sort($issued);
sort($expected);
check_eq($issued, $expected, 'concurrent orders receive eight different numbers');
check_eq((int) db_select('fashion_order_counters')[0]['last_number'], 1008, 'the counter lands on the last issued number');
