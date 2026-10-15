<?php

declare(strict_types=1);

require_once __DIR__ . '/support.php';
require_once dirname(__DIR__) . '/app/domain/accounts.php';
require_once dirname(__DIR__) . '/app/domain/customers.php';
require_once dirname(__DIR__) . '/app/domain/orders.php';
require_once dirname(__DIR__) . '/app/domain/dashboard.php';
require_once dirname(__DIR__) . '/app/domain/stages.php';
require_once dirname(__DIR__) . '/app/fashion/controllers/stages.php';

tests_reset();

function stage_roles_test_backfill(): void
{
    $sql = (string) file_get_contents(dirname(__DIR__) . '/database/migrations/003_stage_roles.sql');
    foreach (schema_split_sql($sql) as $statement) {
        if (preg_match('/^\s*UPDATE\b/i', $statement)) {
            db()->exec($statement);
        }
    }
}

function stage_roles_test_make(string $customerId, string $garmentId, string $due, bool $urgent): array
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

function stage_roles_test_place(string $orderId, string $stageId): void
{
    db_insert('fashion_stage_events', [
        'order_id' => $orderId,
        'stage_id' => $stageId,
        'user_id' => (string) current_user_id(),
        'note' => null,
        'created_at' => date('Y-m-d H:i:s'),
    ]);
}

function stage_roles_test_pay(string $orderId): void
{
    db_insert('fashion_payments', [
        'order_id' => $orderId,
        'amount_kobo' => 100000,
        'method' => 'cash',
        'reverses_payment_id' => null,
        'kind' => 'payment',
        'user_id' => (string) current_user_id(),
        'created_at' => date('Y-m-d H:i:s'),
    ]);
}

function stage_roles_test_sorted(string $filter): array
{
    $numbers = array_column(order_list($filter, ''), 'order_number');
    sort($numbers);
    return $numbers;
}

function stage_roles_test_same(array $actual, array $expected, string $message): void
{
    sort($actual);
    sort($expected);
    check_eq(array_values($actual), array_values($expected), $message);
}

function stage_roles_test_card(array $dash, string $key): ?string
{
    foreach ($dash['cards'] as $card) {
        if ($card['key'] === $key) {
            return $card['value'];
        }
    }
    return null;
}

function stage_roles_test_check(array $tabs, int $week, int $moneyKobo, array $attention, string $label): void
{
    foreach ($tabs as $filter => $numbers) {
        stage_roles_test_same(stage_roles_test_sorted($filter), $numbers, $label . ' ' . $filter . ' tab');
    }
    $dash = dashboard_home(17);
    check_eq(stage_roles_test_card($dash, 'card_active'), (string) count($tabs['active']), $label . ' active card');
    check_eq(stage_roles_test_card($dash, 'card_week'), (string) $week, $label . ' due this week card');
    check_eq(stage_roles_test_card($dash, 'card_overdue'), (string) count($tabs['overdue']), $label . ' overdue card');
    check_eq(stage_roles_test_card($dash, 'card_ready'), (string) count($tabs['ready']), $label . ' ready card');
    check_eq(stage_roles_test_card($dash, 'card_money'), money($moneyKobo), $label . ' outstanding card');
    $listed = array_column($dash['attention'], 'order_number');
    stage_roles_test_same($listed, $attention, $label . ' overdue and urgent list');
}

$shop = business_register([
    'business_name' => 'Role Shop',
    'owner_name' => 'Amina Bello',
    'email' => 'roles@example.com',
    'password' => 'password',
    'phone' => '08031234567',
    'company_type' => 'tailor',
]);
check(isset($shop['user_id']), 'stage role tests start from a signed-in shop');

foreach (db_select('fashion_stage_definitions', [], ['order' => 'sort_order ASC', 'limit' => 20]) as $stage) {
    db_update('fashion_stage_definitions', ['role' => 'open'], ['id' => (string) $stage['id']]);
}
stage_roles_test_backfill();
$roles = array_column(
    db_select('fashion_stage_definitions', [], ['order' => 'sort_order ASC', 'limit' => 20]),
    'role'
);
check_eq(
    $roles,
    ['open', 'open', 'open', 'open', 'open', 'open', 'ready', 'finished'],
    'existing stages take ready and finished from their codes'
);

$probe = db_insert('fashion_stage_definitions', [
    'code' => 'completed',
    'name' => 'Completed',
    'sort_order' => 30,
    'active' => 1,
    'role' => 'open',
]);
stage_roles_test_backfill();
check_eq(
    (string) db_select('fashion_stage_definitions', ['id' => $probe])[0]['role'],
    'finished',
    'a completed code is backfilled as finished'
);
db_delete('fashion_stage_definitions', ['id' => $probe]);

$readyStage = db_select('fashion_stage_definitions', ['code' => 'ready'])[0];
$finishedStage = db_select('fashion_stage_definitions', ['code' => 'delivered'])[0];
$sewingStage = db_select('fashion_stage_definitions', ['code' => 'sewing'])[0];
$readyId = (string) $readyStage['id'];
$finishedId = (string) $finishedStage['id'];
$sewingId = (string) $sewingStage['id'];

$customer = customer_create([
    'name' => 'Hauwa Sani',
    'phone' => '08031112233',
    'gender' => 'female',
    'customer_type' => 'regular',
]);
$garment = db_select('fashion_garment_types', ['code' => 'kaftan'])[0];
$today = date('Y-m-d');
$yesterday = date('Y-m-d', strtotime('-1 day'));
$nextWeek = date('Y-m-d', strtotime('+7 days'));
[$weekStart, $weekEnd] = dashboard_week($today);

$openFuture = stage_roles_test_make($customer['id'], $garment['id'], $nextWeek, false);
stage_roles_test_place($openFuture['id'], $sewingId);
$openLate = stage_roles_test_make($customer['id'], $garment['id'], $yesterday, false);
stage_roles_test_place($openLate['id'], $sewingId);
$dueToday = stage_roles_test_make($customer['id'], $garment['id'], $today, false);
stage_roles_test_pay($dueToday['id']);
$urgent = stage_roles_test_make($customer['id'], $garment['id'], $nextWeek, true);
stage_roles_test_pay($urgent['id']);
$readyFuture = stage_roles_test_make($customer['id'], $garment['id'], $nextWeek, false);
stage_roles_test_place($readyFuture['id'], $readyId);
stage_roles_test_pay($readyFuture['id']);
$readyLate = stage_roles_test_make($customer['id'], $garment['id'], $yesterday, false);
stage_roles_test_place($readyLate['id'], $readyId);
stage_roles_test_pay($readyLate['id']);
$finished = stage_roles_test_make($customer['id'], $garment['id'], $yesterday, false);
stage_roles_test_place($finished['id'], $finishedId);
stage_roles_test_pay($finished['id']);

$active = [
    $openFuture['order_number'],
    $openLate['order_number'],
    $dueToday['order_number'],
    $urgent['order_number'],
];
$overdue = [$openLate['order_number'], $readyLate['order_number']];
$tabs = [
    'all' => array_merge($active, [
        $readyFuture['order_number'],
        $readyLate['order_number'],
        $finished['order_number'],
    ]),
    'active' => $active,
    'overdue' => $overdue,
    'due_today' => [$dueToday['order_number']],
    'urgent' => [$urgent['order_number']],
    'ready' => [$readyFuture['order_number'], $readyLate['order_number']],
    'delivered' => [$finished['order_number']],
    'unpaid' => [$openFuture['order_number'], $openLate['order_number']],
];
$week = 1;
if ($yesterday >= $weekStart && $yesterday <= $weekEnd) {
    $week += 2;
}
$attention = [$openLate['order_number'], $readyLate['order_number'], $urgent['order_number']];
stage_roles_test_check($tabs, $week, 200000, $attention, 'starting roles');

stage_rename($readyId, 'Collection point');
stage_rename($finishedId, 'Given out');
db_update('fashion_stage_definitions', ['code' => 'collection_point'], ['id' => $readyId]);
db_update('fashion_stage_definitions', ['code' => 'given_out'], ['id' => $finishedId]);
$renamed = order_find($readyFuture['id']);
check_eq($renamed['stage_name'], 'Collection point', 'a renamed stage keeps the name the shop chose');
check_eq($renamed['stage_code'], 'collection_point', 'renaming can change the stored code');
check_eq($renamed['stage_role'], 'ready', 'the ready role stays when the code and name change');
check_eq(order_find($finished['id'])['stage_role'], 'finished', 'the finished role stays when the code and name change');
stage_roles_test_check($tabs, $week, 200000, $attention, 'after rename');

stage_set_active($readyId, false);
check((int) db_select('fashion_stage_definitions', ['id' => $readyId])[0]['active'] === 0, 'the ready stage can be skipped');
check_eq((string) db_select('fashion_stage_definitions', ['id' => $readyId])[0]['role'], 'ready', 'skipping a stage keeps its role');
stage_roles_test_check($tabs, $week, 200000, $attention, 'after skipping ready');

$added = stage_add('Packed');
check($added === [], 'an extra stage can be added');
$packed = db_select('fashion_stage_definitions', ['code' => 'packed'])[0];
$packedId = (string) $packed['id'];
check_eq((string) $packed['role'], 'open', 'an added stage starts open');
$extra = stage_roles_test_make($customer['id'], $garment['id'], $nextWeek, false);
stage_roles_test_place($extra['id'], $packedId);
$tabs['all'][] = $extra['order_number'];
$tabs['active'][] = $extra['order_number'];
$tabs['unpaid'][] = $extra['order_number'];
stage_roles_test_check($tabs, $week, 300000, $attention, 'after an extra final stage');
check(!in_array($extra['order_number'], stage_roles_test_sorted('delivered'), true), 'the extra final stage is not delivered');
check(!in_array($extra['order_number'], stage_roles_test_sorted('ready'), true), 'the extra final stage is not ready');

$current = [];
foreach (db_select('fashion_stage_definitions', [], ['limit' => 20]) as $stage) {
    $current[(string) $stage['id']] = (string) $stage['role'];
}
$twoReady = $current;
$twoReady[$sewingId] = 'ready';
check_eq(stage_save_roles($twoReady)['error'] ?? '', 'stage_one_ready', 'two ready stages are rejected');
$noFinished = $current;
$noFinished[$finishedId] = 'open';
check_eq(stage_save_roles($noFinished)['error'] ?? '', 'stage_need_finished', 'zero finished stages are rejected');
$badRole = $current;
$badRole[$sewingId] = 'delivered';
check_eq(stage_save_roles($badRole)['error'] ?? '', 'required', 'a role must be open, ready or finished');
foreach (db_select('fashion_stage_definitions', [], ['limit' => 20]) as $stage) {
    check_eq((string) $stage['role'], $current[(string) $stage['id']], 'a rejected role save changes nothing');
}

$_POST = [
    'csrf' => csrf_token(),
    'action' => 'roles',
    'roles' => $noFinished,
];
try {
    stages_submit();
    check(false, 'saving stage roles redirects');
} catch (RedirectException $e) {
    check_eq($e->path(), '/settings/stages', 'saving stage roles returns to settings');
}
check_eq(flash_get('error'), t('stage_need_finished'), 'the shop sees why the roles were rejected');

$_SERVER['REQUEST_URI'] = base_path() . '/settings/stages';
ob_start();
stages_form();
$settings = (string) ob_get_clean();
check(str_contains($settings, 'name="roles[' . $readyId . ']"'), 'settings lets the owner choose a stage role');
check(str_contains($settings, t('role_open')) && str_contains($settings, t('role_ready')) && str_contains($settings, t('role_finished')), 'each role has a label');
check(str_contains($settings, 'Collection point'), 'a skipped stage stays in the role list');
check(str_contains($settings, t('stage_inactive')), 'a skipped stage is marked off');

$swap = $current;
$swap[$finishedId] = 'open';
$swap[$packedId] = 'finished';
check(stage_save_roles($swap) === [], 'one finished stage can move to the new final stage');
$tabs['delivered'] = [$extra['order_number']];
$tabs['active'] = array_values(array_filter(
    $tabs['active'],
    static fn (string $number): bool => $number !== $extra['order_number']
));
$tabs['active'][] = $finished['order_number'];
$tabs['overdue'][] = $finished['order_number'];
$attention[] = $finished['order_number'];
if ($yesterday >= $weekStart && $yesterday <= $weekEnd) {
    $week++;
}
stage_roles_test_check($tabs, $week, 300000, $attention, 'after the finished role moves');

$factory = business_register([
    'business_name' => 'Role Factory',
    'owner_name' => 'Bala',
    'email' => 'roles-factory@example.com',
    'password' => 'password',
    'phone' => '08037654321',
    'company_type' => 'factory',
]);
check(isset($factory['tenant_id']), 'a factory shop can sign up');
$factoryRoles = [];
foreach (db_select('fashion_stage_definitions', [], ['order' => 'sort_order ASC', 'limit' => 20]) as $stage) {
    $factoryRoles[(string) $stage['code']] = (string) $stage['role'];
}
check_eq($factoryRoles, [
    'new' => 'open',
    'in_progress' => 'open',
    'ready' => 'ready',
    'completed' => 'finished',
], 'a new shop takes roles from stage position');
