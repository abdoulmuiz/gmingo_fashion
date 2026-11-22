<?php

declare(strict_types=1);

require_once __DIR__ . '/support.php';
require_once dirname(__DIR__) . '/app/domain/accounts.php';
require_once dirname(__DIR__) . '/app/domain/customers.php';
require_once dirname(__DIR__) . '/app/domain/orders.php';
require_once dirname(__DIR__) . '/app/domain/stages.php';
require_once dirname(__DIR__) . '/app/fashion/controllers/orders.php';
require_once dirname(__DIR__) . '/app/fashion/controllers/stages.php';

tests_reset();

function stages_test_order(string $customerId, string $garmentId): array
{
    return order_create([
        'customer_id' => $customerId,
        'due_date' => date('Y-m-d', strtotime('+5 days')),
        'urgent' => '',
        'items' => [[
            'garment_type_id' => $garmentId,
            'quantity' => '1',
            'price' => '2000',
            'fabric' => 'ankara',
            'notes' => '',
        ]],
    ]);
}

function stages_test_codes(string $orderId): array
{
    $codes = [];
    $events = db_select('fashion_stage_events', ['order_id' => $orderId], ['order' => 'seq ASC', 'limit' => 20]);
    foreach ($events as $event) {
        $stage = db_select('fashion_stage_definitions', ['id' => $event['stage_id']])[0];
        $codes[] = (string) $stage['code'];
    }
    return $codes;
}

$shop = business_register([
    'business_name' => 'Stage Shop',
    'owner_name' => 'Amina Bello',
    'email' => 'stages@example.com',
    'password' => 'password',
    'phone' => '08031234567',
    'company_type' => 'tailor',
]);
check(isset($shop['user_id']), 'stage tests start from a signed-in shop');
$names = array_column(
    db_select('fashion_stage_definitions', [], ['order' => 'sort_order ASC', 'limit' => 20]),
    'name'
);
check_eq(
    $names,
    ['New', 'Cutting', 'Sewing', 'Embroidery', 'Finishing', 'Fitting', 'Ready', 'Delivered'],
    'a tailor shop starts with its own stage order'
);

$customer = customer_create([
    'name' => 'Amina Bello',
    'phone' => '08031112233',
    'gender' => 'female',
    'customer_type' => 'regular',
]);
$garment = db_select('fashion_garment_types', ['code' => 'kaftan'])[0];
$order = stages_test_order($customer['id'], $garment['id']);
$first = stage_advance($order['id']);
check_eq($first['code'], 'cutting', 'the first move leaves New and records Cutting');
$events = db_select('fashion_stage_events', ['order_id' => $order['id']]);
check_eq(count($events), 1, 'a move appends one stage event');
check_eq((string) $events[0]['user_id'], (string) current_user_id(), 'the stage event records who moved it');
check((string) $events[0]['created_at'] !== '', 'the stage event records when it moved');
check_eq(stage_latest($order['id'])['code'], 'cutting', 'the current stage is the latest event');

$second = stage_advance($order['id']);
check_eq($second['code'], 'sewing', 'the next move follows the shop stage order');
$embroidery = db_select('fashion_stage_definitions', ['code' => 'embroidery'])[0];
stage_set_active((string) $embroidery['id'], false);
$third = stage_advance($order['id']);
check_eq($third['code'], 'finishing', 'an inactive stage is skipped');
check_eq(stages_test_codes($order['id']), ['cutting', 'sewing', 'finishing'], 'stage history stays in order');
check_throws(
    static fn () => db_update('fashion_stage_events', ['note' => 'changed'], ['id' => $events[0]['id']]),
    RuntimeException::class,
    'This table is append-only.'
);
check_throws(
    static fn () => db_delete('fashion_stage_events', ['id' => $events[0]['id']]),
    RuntimeException::class,
    'This table is append-only.'
);

$ourStageIds = array_column(db_select('fashion_stage_definitions', [], ['limit' => 20]), 'id');
$other = business_register([
    'business_name' => 'Other Stage Shop',
    'owner_name' => 'Bala',
    'email' => 'stages-other@example.com',
    'password' => 'password',
    'phone' => '08037654321',
    'company_type' => 'tailor',
]);
check(isset($other['tenant_id']), 'a second shop can sign up');
$theirStageIds = array_column(db_select('fashion_stage_definitions', [], ['limit' => 20]), 'id');
check(array_intersect($ourStageIds, $theirStageIds) === [], 'each shop has its own stages');
try {
    stage_advance($order['id']);
    check(false, 'another shop cannot move the order');
} catch (HttpException $e) {
    check($e->status() === 404, 'another shop cannot move the order');
}
$theirCustomer = customer_create([
    'name' => 'Bala Customer',
    'phone' => '08130001122',
    'gender' => 'male',
    'customer_type' => 'regular',
]);
$theirGarment = db_select('fashion_garment_types', ['code' => 'kaftan'])[0];
$theirOrder = stages_test_order($theirCustomer['id'], $theirGarment['id']);
$theirMove = stage_advance($theirOrder['id']);
check(in_array($theirMove['stage_id'], $theirStageIds, true), 'a shop moves onto its own stage');
check(!in_array($theirMove['stage_id'], $ourStageIds, true), 'a shop does not use another shop stage');

auth_login((string) $shop['user_id'], (string) $shop['tenant_id']);
check_eq(count(db_select('fashion_stage_events', ['order_id' => $order['id']])), 3, 'the other shop did not add a stage event');
$_POST = [
    'csrf' => csrf_token(),
    'stage_id' => (string) $theirStageIds[0],
];
try {
    router_dispatch('POST', '/orders/' . $order['id'] . '/stage');
    check(false, 'moving a stage redirects');
} catch (RedirectException $e) {
    check_eq($e->path(), '/orders/' . $order['id'], 'moving a stage returns to the order');
}
check_eq(stage_latest($order['id'])['code'], 'fitting', 'a posted stage id is ignored');
check(in_array((string) stage_latest($order['id'])['id'], $ourStageIds, true), 'the move stays on this shop stage list');

$board = stage_board();
$fitting = null;
foreach ($board['groups'] as $group) {
    if ($group['name'] === 'Fitting') {
        $fitting = $group;
    }
}
check($fitting !== null && $fitting['orders'] !== [], 'the board groups the order under its current stage');
check_eq($fitting['orders'][0]['order_number'], $order['order_number'], 'the board card shows the order number');
check_eq($fitting['orders'][0]['customer_name'], 'Amina Bello', 'the board card shows the customer');
check_eq($fitting['orders'][0]['due_date'], date('Y-m-d', strtotime('+5 days')), 'the board card shows the due date');
check($fitting['orders'][0]['assigned_name'] === null, 'an order with nobody assigned has no name');
$_SERVER['REQUEST_URI'] = base_path() . '/board';
ob_start();
board_show();
$boardHtml = (string) ob_get_clean();
check(str_contains($boardHtml, $order['order_number']), 'the board page shows the order number');
check(str_contains($boardHtml, 'Amina Bello'), 'the board page shows the customer');
check(!str_contains($boardHtml, t('unassigned')), 'an order with nobody assigned does not say Unassigned');
check(!str_contains($boardHtml, 'board-assignee'), 'an order with nobody assigned omits the assignee chip');

db_update('fashion_orders', ['assigned_user_id' => (string) $shop['user_id']], ['id' => $order['id']]);
$assignedBoard = stage_board();
$assignedFitting = null;
foreach ($assignedBoard['groups'] as $group) {
    if ($group['name'] === 'Fitting') {
        $assignedFitting = $group;
    }
}
check(
    $assignedFitting !== null && $assignedFitting['orders'][0]['assigned_name'] === 'Amina Bello',
    'the board shows the assigned person'
);
ob_start();
board_show();
$assignedHtml = (string) ob_get_clean();
check(str_contains($assignedHtml, 'board-assignee'), 'the board page shows the assigned initials chip');
check(str_contains($assignedHtml, 'title="Amina Bello"'), 'the board page keeps the assigned full name');
check(!str_contains($assignedHtml, t('unassigned')), 'an assigned order does not say Unassigned');

$newStage = db_select('fashion_stage_definitions', ['code' => 'new'])[0];
stage_rename((string) $newStage['id'], 'Fresh');
check_eq(db_select('fashion_stage_definitions', ['id' => $newStage['id']])[0]['name'], 'Fresh', 'a shop can rename a stage');
stage_reorder((string) $newStage['id'], 'down');
$reordered = array_column(
    db_select('fashion_stage_definitions', [], ['order' => 'sort_order ASC', 'limit' => 20]),
    'name'
);
check_eq($reordered[0], 'Cutting', 'moving a stage down changes the shop order');
check_eq($reordered[1], 'Fresh', 'the renamed stage keeps its new name');
stage_add('Beading');
$added = db_select('fashion_stage_definitions', ['code' => 'beading']);
check(count($added) === 1 && (int) $added[0]['active'] === 1, 'a shop can add a stage');
stage_set_active((string) $added[0]['id'], false);
check((int) db_select('fashion_stage_definitions', ['id' => $added[0]['id']])[0]['active'] === 0, 'a shop can deactivate a stage');

auth_login((string) $other['user_id'], (string) $other['tenant_id']);
$otherNames = array_column(db_select('fashion_stage_definitions', [], ['limit' => 20]), 'name');
check(!in_array('Fresh', $otherNames, true), 'another shop does not see the renamed stage');
check(!in_array('Beading', $otherNames, true), 'another shop does not see the added stage');
check_eq(db_update('fashion_stage_definitions', ['name' => 'Stolen'], ['id' => (string) $newStage['id']]), 0, 'another shop cannot rename this stage');

auth_login((string) $shop['user_id'], (string) $shop['tenant_id']);
check_eq(db_select('fashion_stage_definitions', ['id' => $newStage['id']])[0]['name'], 'Fresh', 'the stage name stays after the other shop');
$_SERVER['REQUEST_URI'] = base_path() . '/orders/' . $order['id'];
ob_start();
orders_show($order['id']);
$withButton = (string) ob_get_clean();
check(str_contains($withButton, t('move_next')), 'the order page has the stage button when production is on');

module_save([
    'fashion.orders',
    'fashion.finance',
    'fashion.measurements',
    'fashion.communication',
]);
check(module_enabled('fashion.production') === false, 'production is switched off');
$_SERVER['REQUEST_URI'] = base_path() . '/';
ob_start();
router_dispatch('GET', '/');
$closedHome = (string) ob_get_clean();
check(!str_contains($closedHome, 'href="' . url('/board') . '"'), 'Board link is hidden when production is off');
foreach (['/board', '/settings/stages'] as $path) {
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
    stage_advance($order['id']);
    check(false, 'moving a stage stays closed');
} catch (HttpException $e) {
    check($e->status() === 403, 'moving a stage returns 403 when production is off');
}
ob_start();
orders_show($order['id']);
$withoutButton = (string) ob_get_clean();
check(!str_contains($withoutButton, t('move_next')), 'the stage button leaves the order page');
check(str_contains($withoutButton, $order['order_number']), 'the order page still opens');
