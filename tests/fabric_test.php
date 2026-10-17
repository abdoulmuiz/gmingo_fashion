<?php

declare(strict_types=1);

require_once __DIR__ . '/support.php';
require_once dirname(__DIR__) . '/app/domain/accounts.php';
require_once dirname(__DIR__) . '/app/domain/customers.php';
require_once dirname(__DIR__) . '/app/domain/orders.php';
require_once dirname(__DIR__) . '/app/domain/fabric.php';
require_once dirname(__DIR__) . '/app/domain/stages.php';
require_once dirname(__DIR__) . '/app/fashion/controllers/orders.php';

tests_reset();

function fabric_test_order(string $customerId, string $garmentId, string $dueDate): array
{
    return order_create([
        'customer_id' => $customerId,
        'due_date' => $dueDate,
        'urgent' => '',
        'items' => [[
            'garment_type_id' => $garmentId,
            'quantity' => '1',
            'price' => '1000',
            'fabric' => 'ankara',
            'notes' => '',
        ]],
    ]);
}

function fabric_test_card(string $html, string $orderNumber): string
{
    $start = strpos($html, $orderNumber);
    if ($start === false) {
        return '';
    }
    $next = strpos($html, 'class="card link-card"', $start);
    if ($next === false) {
        return substr($html, $start);
    }
    return substr($html, $start, $next - $start);
}

check_eq(yards_hundredths('12.5'), 1250, '12.5 stores as 1250');
check_eq(yards_hundredths('12.50'), 1250, '12.50 stores as 1250');
check_eq(yards_format(1250), '12.50', '1250 shows as 12.50');
check(yards_hundredths('-1') === null, 'negative yards are rejected');
check(yards_hundredths('abc') === null, 'letters are rejected');
check_eq(yards_left(1250, 250), 1000, 'leftover is received minus used');

$shop = business_register([
    'business_name' => 'Fabric Shop',
    'owner_name' => 'Amina Bello',
    'email' => 'fabric-a@example.com',
    'password' => 'password',
    'phone' => '08032000001',
    'company_type' => 'tailor',
]);
$ownerId = (string) $shop['user_id'];
$tenantId = (string) $shop['tenant_id'];
$customer = customer_create([
    'name' => 'Fabric Customer',
    'phone' => '08032000004',
    'gender' => 'female',
    'customer_type' => 'regular',
]);
$garment = db_select('fashion_garment_types', ['code' => 'kaftan'])[0];
$order = fabric_test_order($customer['id'], $garment['id'], date('Y-m-d', strtotime('+4 days')));
check(fabric_set_supplied($order['id'], true) === [], 'the fabric switch turns on');
$GLOBALS['gmingo_now'] = '2026-10-16 18:50:00';
$saved = fabric_save_yards($order['id'], '12.5', '', 'Brought ankara');
unset($GLOBALS['gmingo_now']);
check($saved === [], '12.5 yards is saved');
$stored = db_select('fashion_orders', ['id' => $order['id']])[0];
check_eq((int) $stored['yards_received'], 1250, '12.5 is stored as 1250 hundredths');
check($stored['yards_used'] === null, 'blank used yards stay empty');
$log = fabric_log($order['id']);
check_eq(count($log), 1, 'the first edit adds one log row');
check_eq($log[0]['old_hundredths'], null, 'the first log has no old yards');
check_eq($log[0]['new_hundredths'], 1250, 'the first log stores 1250');
check_eq($log[0]['user_id'], $ownerId, 'the log records who changed the yards');
check_eq($log[0]['created_at'], '2026-10-16 18:50:00', 'the log records when the yards changed');
check_eq($log[0]['note'], 'Brought ankara', 'the log records the note');
$firstId = $log[0]['id'];
$_SERVER['REQUEST_URI'] = base_path() . '/orders/' . $order['id'];
ob_start();
orders_show($order['id']);
$shown = (string) ob_get_clean();
check(str_contains($shown, 'value="12.50"'), '12.5 shows as 12.50');
check(str_contains($shown, t('yards_left')), 'the order shows yards left over');
check(str_contains($shown, '>12.50<'), 'leftover shows 12.50 when nothing is used');
check_eq(fabric_save_yards($order['id'], '12.50', '13', '')['error'], 'yards_used_above', 'used above received is refused');
$stored = db_select('fashion_orders', ['id' => $order['id']])[0];
check_eq((int) $stored['yards_received'], 1250, 'a refused yard edit leaves 1250 stored');
check($stored['yards_used'] === null, 'a refused yard edit does not store used yards');
check_eq(count(fabric_log($order['id'])), 1, 'a refused yard edit adds no log row');
$_POST = [
    'csrf' => csrf_token(),
    'action' => 'yards',
    'received' => '12.50',
    'used' => '13',
    'note' => '',
];
try {
    fabric_submit($order['id']);
    check(false, 'saving too many used yards redirects');
} catch (RedirectException $e) {
    check_eq($e->path(), '/orders/' . $order['id'], 'a refused yard edit returns to the order');
}
check_eq(flash_get('error'), 'Yards used cannot be more than yards received.', 'used above received shows the yards error');
check(fabric_save_yards($order['id'], '', '2.50', 'Cut the gown') === [], '2.50 used yards is saved');
$afterUse = order_find($order['id']);
check_eq($afterUse['received_hundredths'], 1250, 'received stays 1250');
check_eq($afterUse['used_hundredths'], 250, '2.50 used yards is 250 hundredths');
check_eq($afterUse['yards_left_hundredths'], 1000, 'leftover is 1000 hundredths');
$log = fabric_log($order['id']);
check_eq(count($log), 2, 'editing used yards adds a second log row');
check_eq($log[0]['id'], $firstId, 'the first log row keeps its id');
check_eq($log[0]['new_hundredths'], 1250, 'the first log row still stores 1250');
check_eq($log[0]['note'], 'Brought ankara', 'the first log note stays');
check_eq($log[1]['field_name'], 'used', 'the second log row is the used yards');
check_eq($log[1]['old_hundredths'], null, 'used yards had no old value');
check_eq($log[1]['new_hundredths'], 250, 'the second log stores 250');
check_throws(
    static fn () => db_update('fashion_order_fabric_log', ['note' => 'changed'], ['id' => $firstId]),
    RuntimeException::class,
    'This table is append-only.'
);
check_throws(
    static fn () => db_delete('fashion_order_fabric_log', ['id' => $firstId]),
    RuntimeException::class,
    'This table is append-only.'
);
check_eq(fabric_log($order['id'])[0]['note'], 'Brought ankara', 'the old log row is unchanged');
check(fabric_set_supplied($order['id'], false) === [], 'the fabric switch turns off');
check_eq((int) db_select('fashion_orders', ['id' => $order['id']])[0]['customer_supplied_fabric'], 0, 'the switch is off');
check_eq(count(fabric_log($order['id'])), 2, 'turning the switch off keeps the history');
ob_start();
fabric_history_show($order['id']);
$history = (string) ob_get_clean();
check(str_contains($history, '12.50'), 'history still shows 12.50 after the switch is off');
check(str_contains($history, 'Brought ankara'), 'history still shows the note after the switch is off');
check(str_contains($history, 'Amina Bello'), 'history shows who changed the yards');
check(str_contains($history, '16 Oct 2026 18:50'), 'history shows the Lagos time');

$other = business_register([
    'business_name' => 'Other Fabric Shop',
    'owner_name' => 'Bala',
    'email' => 'fabric-b@example.com',
    'password' => 'password',
    'phone' => '08032000002',
    'company_type' => 'tailor',
]);
$_POST = [
    'csrf' => csrf_token(),
    'action' => 'switch',
    'supplied' => '1',
];
try {
    router_dispatch('POST', '/orders/' . $order['id'] . '/fabric');
    check(false, 'the other shop fabric save is refused');
} catch (HttpException $e) {
    check_eq($e->getMessage(), 'Page not found', 'the fabric address refuses another business order id');
    check_eq($e->status(), 404, 'the fabric address returns 404 for another business order id');
}
try {
    router_dispatch('GET', '/orders/' . $order['id'] . '/fabric');
    check(false, 'the other shop fabric history is refused');
} catch (HttpException $e) {
    check_eq($e->getMessage(), 'Page not found', 'the history address refuses another business order id');
    check_eq($e->status(), 404, 'the history address returns 404 for another business order id');
}
auth_login($ownerId, $tenantId);
check_eq((int) db_select('fashion_orders', ['id' => $order['id']])[0]['customer_supplied_fabric'], 0, 'the other shop did not turn the switch on');
check_eq(count(fabric_log($order['id'])), 2, 'the other shop did not add a log row');

$finished = db_select('fashion_stage_definitions', ['role' => 'finished'], ['limit' => 1])[0];
stage_rename((string) $finished['id'], 'Packed');
$ready = db_select('fashion_stage_definitions', ['role' => 'ready'], ['limit' => 1])[0];
check(stage_add('Delivered') === [], 'a stage named Delivered can be added');
$openDelivered = db_select('fashion_stage_definitions', ['name' => 'Delivered'], ['limit' => 1])[0];
check_eq((string) $openDelivered['role'], 'open', 'the stage named Delivered has the open role');
$packedOrder = fabric_test_order($customer['id'], $garment['id'], date('Y-m-d', strtotime('+1 day')));
$namedOrder = fabric_test_order($customer['id'], $garment['id'], date('Y-m-d', strtotime('+2 days')));
$readyOrder = fabric_test_order($customer['id'], $garment['id'], date('Y-m-d', strtotime('+3 days')));
foreach ([[$packedOrder['id'], '12.5'], [$namedOrder['id'], '1'], [$readyOrder['id'], '3']] as [$orderId, $yards]) {
    fabric_set_supplied($orderId, true);
    check(fabric_save_yards($orderId, $yards, '', '') === [], $yards . ' yards is saved for the badge order');
}
db_insert('fashion_stage_events', [
    'order_id' => $packedOrder['id'],
    'stage_id' => (string) $finished['id'],
    'user_id' => $ownerId,
    'note' => null,
    'kind' => 'move',
    'created_at' => app_now()->format('Y-m-d H:i:s'),
]);
db_insert('fashion_stage_events', [
    'order_id' => $namedOrder['id'],
    'stage_id' => (string) $openDelivered['id'],
    'user_id' => $ownerId,
    'note' => null,
    'kind' => 'move',
    'created_at' => app_now()->format('Y-m-d H:i:s'),
]);
db_insert('fashion_stage_events', [
    'order_id' => $readyOrder['id'],
    'stage_id' => (string) $ready['id'],
    'user_id' => $ownerId,
    'note' => null,
    'kind' => 'move',
    'created_at' => app_now()->format('Y-m-d H:i:s'),
]);
$packed = order_find($packedOrder['id']);
$named = order_find($namedOrder['id']);
$readyRow = order_find($readyOrder['id']);
check_eq($packed['stage_name'], 'Packed', 'the finished stage was renamed Packed');
check_eq($packed['stage_role'], 'finished', 'Packed keeps the finished role');
check($packed['fabric_badge'] === true, 'leftover on a finished role shows the badge');
check_eq($named['stage_name'], 'Delivered', 'the open stage is named Delivered');
check_eq($named['stage_role'], 'open', 'the stage named Delivered stays open');
check($named['fabric_badge'] === false, 'leftover on an open role does not show the badge');
check_eq($readyRow['stage_role'], 'ready', 'the ready order uses the ready role');
check($readyRow['fabric_badge'] === false, 'leftover on a ready role does not show the badge');
$_GET = ['tab' => 'all', 'q' => 'Fabric Customer'];
$_SERVER['REQUEST_URI'] = base_path() . '/orders?tab=all&q=Fabric%20Customer';
ob_start();
orders_index();
$list = (string) ob_get_clean();
$packedCard = fabric_test_card($list, $packed['order_number']);
$namedCard = fabric_test_card($list, $named['order_number']);
$readyCard = fabric_test_card($list, $readyRow['order_number']);
check(str_contains($packedCard, '12.50 yd left'), 'the list badge shows 12.50 yd left on the finished role');
check(!str_contains($namedCard, '1.00 yd left'), 'the list has no leftover badge on the stage named Delivered');
check(!str_contains($readyCard, '3.00 yd left'), 'the list has no leftover badge on the ready role');
