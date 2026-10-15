<?php

declare(strict_types=1);

require_once __DIR__ . '/support.php';
require_once dirname(__DIR__) . '/app/domain/accounts.php';
require_once dirname(__DIR__) . '/app/domain/customers.php';
require_once dirname(__DIR__) . '/app/domain/orders.php';
require_once dirname(__DIR__) . '/app/domain/dashboard.php';
require_once dirname(__DIR__) . '/app/fashion/controllers/home.php';

tests_reset();

function dashboard_test_value(array $dash, string $key): ?string
{
    foreach ($dash['cards'] as $card) {
        if ($card['key'] === $key) {
            return $card['value'];
        }
    }
    return null;
}

function dashboard_test_order(string $customerId, string $garmentId, string $due, bool $urgent, string $price): array
{
    return order_create([
        'customer_id' => $customerId,
        'due_date' => $due,
        'urgent' => $urgent ? '1' : '',
        'items' => [[
            'garment_type_id' => $garmentId,
            'quantity' => '1',
            'price' => $price,
            'fabric' => '',
            'notes' => '',
        ]],
    ]);
}

function dashboard_test_stage(string $orderId, string $code): void
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

$_COOKIE['lang'] = 'ha';
check_eq(dashboard_hello(15), 'Barka da rana', 'Hausa afternoon greeting');
check_eq(dashboard_hello(17), 'Barka da yamma', 'Hausa evening greeting');
check_eq(dashboard_hello(9), 'Ina kwana', 'Hausa morning greeting');
$_COOKIE['lang'] = 'en';
check_eq(dashboard_hello(15), 'Good afternoon', 'English afternoon greeting');
check_eq(dashboard_hello(17), 'Good evening', 'English evening greeting');

$shop = business_register([
    'business_name' => 'Dashboard Shop',
    'owner_name' => 'Amina Bello',
    'email' => 'dashboard@example.com',
    'password' => 'password',
    'phone' => '08031234567',
    'company_type' => 'tailor',
]);
check(isset($shop['user_id']), 'dashboard tests start from a signed-in shop');
check(str_contains(dashboard_greeting(17), 'Good evening, Amina Bello'), 'the greeting uses the time of day and the owner name');
$_COOKIE['lang'] = 'ha';
check(str_contains(dashboard_greeting(17), 'Barka da yamma, Amina Bello'), 'Hausa uses Barka da yamma from evening');
$_COOKIE['lang'] = 'en';

$customer = customer_create([
    'name' => 'Hauwa Sani',
    'phone' => '08031112233',
    'gender' => 'female',
    'customer_type' => 'regular',
]);
$garment = db_select('fashion_garment_types', ['code' => 'kaftan'])[0];
$today = date('Y-m-d');
$yesterday = date('Y-m-d', strtotime($today . ' -1 day'));
[$weekStart, $weekEnd] = dashboard_week($today);
$beforeWeek = date('Y-m-d', strtotime($weekStart . ' -1 day'));
$afterWeek = date('Y-m-d', strtotime($weekEnd . ' +1 day'));

$openToday = dashboard_test_order($customer['id'], $garment['id'], $today, false, '1000');
$overdueRecent = dashboard_test_order($customer['id'], $garment['id'], $yesterday, false, '1000');
$overdueOld = dashboard_test_order($customer['id'], $garment['id'], $beforeWeek, false, '1000');
$ready = dashboard_test_order($customer['id'], $garment['id'], $today, false, '1000');
$delivered = dashboard_test_order($customer['id'], $garment['id'], $yesterday, false, '1000');
$urgent = dashboard_test_order($customer['id'], $garment['id'], $afterWeek, true, '2000');
dashboard_test_stage($ready['id'], 'ready');
dashboard_test_stage($delivered['id'], 'delivered');
db_insert('fashion_payments', [
    'order_id' => $openToday['id'],
    'amount_kobo' => 40000,
    'method' => 'cash',
    'reverses_payment_id' => null,
    'kind' => 'payment',
    'user_id' => (string) current_user_id(),
    'created_at' => date('Y-m-d H:i:s'),
]);

$weekCount = 2;
if ($yesterday >= $weekStart && $yesterday <= $weekEnd) {
    $weekCount++;
}
$dash = dashboard_home(17);
check_eq(dashboard_test_value($dash, 'card_active'), '4', 'active orders leave out ready and delivered');
check_eq(dashboard_test_value($dash, 'card_week'), (string) $weekCount, 'due this week counts unfinished orders due this week');
check_eq(dashboard_test_value($dash, 'card_overdue'), '2', 'overdue counts unfinished orders past their due date');
check_eq(dashboard_test_value($dash, 'card_ready'), '1', 'ready for pickup counts the ready stage');
check_eq(dashboard_test_value($dash, 'card_money'), money(660000), 'outstanding money is the sum of balances');
check(dashboard_test_value($dash, 'card_low_stock') === null, 'low stock stays hidden without inventory');
$attention = array_column($dash['attention'], 'order_number');
check(in_array($overdueRecent['order_number'], $attention, true), 'the list includes an overdue order');
check(in_array($overdueOld['order_number'], $attention, true), 'the list includes an older overdue order');
check(in_array($urgent['order_number'], $attention, true), 'the list includes an urgent order');
check(!in_array($ready['order_number'], $attention, true), 'a ready order due today is not in the urgent list');
check(!in_array($delivered['order_number'], $attention, true), 'a delivered order is not overdue');
check(!in_array($openToday['order_number'], $attention, true), 'an open order due today is not in the urgent list');

$_SERVER['REQUEST_URI'] = base_path() . '/';
ob_start();
home_show();
$html = (string) ob_get_clean();
check(str_contains($html, t('home_title')), 'the home screen keeps its title');
check(str_contains($html, dashboard_greeting()), 'the home screen shows the greeting');
check(str_contains($html, t('card_active')), 'the home screen shows active orders');
check(str_contains($html, money(660000)), 'the home screen shows outstanding money');
check(str_contains($html, $urgent['order_number']), 'the home screen lists the urgent order');
check(str_contains($html, 'Hauwa Sani'), 'the home screen lists the customer');
check(!str_contains($html, t('card_low_stock')), 'the home screen has no low stock card when inventory is off');
check(!str_contains($html, t('inventory_later')), 'the low stock note stays off the page');
check(!str_contains($html, t('switch_on_module')), 'the empty message stays off while cards are showing');

module_save([
    'fashion.orders',
    'fashion.production',
    'fashion.finance',
    'fashion.measurements',
    'fashion.communication',
    'fashion.inventory',
]);
$withStock = dashboard_home(17);
check(dashboard_test_value($withStock, 'card_low_stock') === null, 'inventory on does not invent a low stock count of 0');
check_eq(dashboard_test_value($withStock, 'card_active'), '4', 'turning inventory on leaves the order counts');
ob_start();
home_show();
$stockHtml = (string) ob_get_clean();
check(!str_contains($stockHtml, t('card_low_stock')), 'inventory on still leaves the low stock card off the home screen');
check(!str_contains($stockHtml, t('inventory_later')), 'inventory on does not show the inventory later note');

module_save([
    'fashion.production',
    'fashion.finance',
    'fashion.measurements',
    'fashion.communication',
    'fashion.inventory',
]);
$ordersOff = dashboard_home(17);
check(dashboard_test_value($ordersOff, 'card_active') === null, 'active orders hide when orders is off');
check(dashboard_test_value($ordersOff, 'card_week') === null, 'due this week hides when orders is off');
check(dashboard_test_value($ordersOff, 'card_overdue') === null, 'overdue hides when orders is off');
check(dashboard_test_value($ordersOff, 'card_ready') === null, 'ready for pickup hides when orders is off');
check($ordersOff['attention'] === [], 'the urgent list hides when orders is off');
check($ordersOff['orders_on'] === false, 'the order list is closed');
check_eq(dashboard_test_value($ordersOff, 'card_money'), money(0), 'outstanding money stays on the screen without crashing');
check(dashboard_test_value($ordersOff, 'card_low_stock') === null, 'low stock stays off without a real stock count');
ob_start();
home_show();
$ordersOffHtml = (string) ob_get_clean();
check(!str_contains($ordersOffHtml, t('needs_attention')), 'the urgent list leaves the home screen');
check(str_contains($ordersOffHtml, t('card_money')), 'outstanding money stays visible');
check(!str_contains($ordersOffHtml, t('card_low_stock')), 'the low stock card stays off while inventory is on');
check(!str_contains($ordersOffHtml, t('inventory_later')), 'the inventory later note stays off while inventory is on');

module_save([
    'fashion.production',
    'fashion.measurements',
    'fashion.communication',
    'fashion.inventory',
]);
$financeOff = dashboard_home(17);
check(dashboard_test_value($financeOff, 'card_money') === null, 'outstanding money hides when finance is off');
ob_start();
home_show();
$financeOffHtml = (string) ob_get_clean();
check(!str_contains($financeOffHtml, t('card_money')), 'the money card leaves the home screen');

module_save([
    'fashion.production',
    'fashion.measurements',
    'fashion.communication',
]);
$stockOff = dashboard_home(17);
check(dashboard_test_value($stockOff, 'card_low_stock') === null, 'low stock hides when inventory is off');
ob_start();
home_show();
$stockOffHtml = (string) ob_get_clean();
check(!str_contains($stockOffHtml, t('card_low_stock')), 'the low stock card leaves the home screen');
check(!str_contains($stockOffHtml, t('inventory_later')), 'the inventory note leaves the home screen');

module_save([]);
$closed = dashboard_home(17);
check($closed['empty'] === true, 'a shop with every module off has no cards');
check($closed['cards'] === [], 'no card is built when every module is off');
ob_start();
home_show();
$closedHtml = (string) ob_get_clean();
check(str_contains($closedHtml, t('switch_on_module')), 'the empty home screen asks for a module');
check(str_contains($closedHtml, 'href="' . url('/settings') . '"'), 'the empty home screen links to settings');
check(str_contains($closedHtml, dashboard_greeting()), 'the greeting stays when every module is off');
check(!str_contains($closedHtml, t('card_active')), 'order cards stay hidden');
check(!str_contains($closedHtml, t('card_money')), 'the money card stays hidden');
check(!str_contains($closedHtml, t('card_low_stock')), 'the low stock card stays hidden');

$factory = business_register([
    'business_name' => 'Dashboard Factory',
    'owner_name' => 'Bala',
    'email' => 'dashboard-factory@example.com',
    'password' => 'password',
    'phone' => '08037654321',
    'company_type' => 'factory',
]);
check(isset($factory['tenant_id']), 'a factory can open the home screen');
$factoryHome = dashboard_home(16);
check(dashboard_test_value($factoryHome, 'card_active') === null, 'a factory without orders has no order cards');
check_eq(dashboard_test_value($factoryHome, 'card_money'), money(0), 'a factory still shows outstanding money');
check(dashboard_test_value($factoryHome, 'card_low_stock') === null, 'a factory does not show a fake low stock count');
ob_start();
home_show();
$factoryHtml = (string) ob_get_clean();
check(!str_contains($factoryHtml, t('card_low_stock')), 'a factory home screen has no low stock card');
check(!str_contains($factoryHtml, t('inventory_later')), 'a factory home screen does not show the inventory later note');
check(str_contains($factoryHome['greeting'], 'Good afternoon, Bala'), 'the factory greeting follows the afternoon');
