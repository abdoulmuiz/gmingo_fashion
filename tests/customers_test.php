<?php

declare(strict_types=1);

require_once __DIR__ . '/support.php';
require_once dirname(__DIR__) . '/app/domain/accounts.php';
require_once dirname(__DIR__) . '/app/domain/customers.php';
require_once dirname(__DIR__) . '/app/fashion/controllers/customers.php';

tests_reset();

$shop = business_register([
    'business_name' => 'Customer Shop',
    'owner_name' => 'Amina Bello',
    'email' => 'customers@example.com',
    'password' => 'password',
    'phone' => '08031234567',
    'company_type' => 'tailor',
]);
check(isset($shop['user_id']), 'customer tests start from a signed-in shop');

$bad = customer_create([
    'name' => '',
    'phone' => '12345',
    'gender' => 'male',
    'customer_type' => 'regular',
]);
check(isset($bad['errors']['name'], $bad['errors']['phone']), 'blank name and a bad phone are rejected');

$amina = customer_create([
    'name' => 'Amina <script>',
    'phone' => '+234 803 123 4567',
    'gender' => 'female',
    'customer_type' => 'vip',
]);
$musa = customer_create([
    'name' => 'Musa',
    'phone' => '07039876543',
    'gender' => 'male',
    'customer_type' => 'regular',
]);
check(isset($amina['id'], $musa['id']), 'customers are saved');

check(count(customer_search('Amina')) === 1, 'search matches a name');
$byPhone = customer_search('07039876543');
check(count($byPhone) === 1 && $byPhone[0]['name'] === 'Musa', 'search matches a phone');
check(customer_search('%') === [], 'search wildcards are not treated as match-all');

$garment = db_select('fashion_garment_types', ['code' => 'kaftan'])[0];
$orderId = db_insert('fashion_orders', [
    'customer_id' => $amina['id'],
    'order_number' => 'TK1001',
    'due_date' => date('Y-m-d'),
    'urgent' => 0,
    'group_id' => null,
    'assigned_user_id' => null,
    'customer_supplied_fabric' => 0,
    'yards_received' => null,
    'yards_used' => null,
    'created_at' => date('Y-m-d H:i:s'),
]);
db_insert('fashion_order_items', [
    'order_id' => $orderId,
    'garment_type_id' => $garment['id'],
    'quantity' => 2,
    'price_kobo' => 1500000,
    'fabric' => 'ankara',
    'notes' => '',
]);
db_insert('fashion_payments', [
    'order_id' => $orderId,
    'amount_kobo' => 1000000,
    'method' => 'transfer',
    'reverses_payment_id' => null,
    'kind' => 'payment',
    'user_id' => current_user_id(),
    'created_at' => date('Y-m-d H:i:s'),
]);

$profile = customer_profile($amina['id']);
check_eq($profile['order_count'], 1, 'profile counts orders');
check_eq($profile['spent_kobo'], 1000000, 'total spent is the sum of payments');
check_eq($profile['owed_kobo'], 2000000, 'amount owed is total minus payments');
check_eq($profile['phone'], '08031234567', 'phone is stored in Nigerian local form');
check_eq(whatsapp_href($profile['phone']), 'https://wa.me/2348031234567', 'WhatsApp uses the 234 number');
check_eq(tel_href($profile['phone']), 'tel:+2348031234567', 'Call uses the 234 number');

$_SERVER['REQUEST_URI'] = base_path() . '/customers';
$_GET['q'] = '';
ob_start();
customers_index();
$list = (string) ob_get_clean();
check(!str_contains($list, '<script>'), 'customer list escapes HTML');
check(str_contains($list, '&lt;script&gt;'), 'customer list shows the escaped name');
check(str_contains($list, 'name="csrf"'), 'customer search form has a CSRF token');

ob_start();
customers_show($amina['id']);
$show = (string) ob_get_clean();
check(str_contains($show, 'https://wa.me/2348031234567'), 'profile has a WhatsApp button');
check(str_contains($show, 'tel:+2348031234567'), 'profile has a Call button');
check(!str_contains($show, '/orders/new'), 'New Order stays hidden until that step exists');
check(str_contains($show, 'TK1001'), 'profile lists the order');
check(str_contains($show, money(2000000)), 'profile shows the calculated balance');
check(str_contains($show, t('customer_since')), 'profile shows customer since');

$routesBefore = $GLOBALS['gmingo_routes'];
route('GET', '/orders/new', static function (): void {
});
ob_start();
customers_show($amina['id']);
$withNewOrder = (string) ob_get_clean();
$GLOBALS['gmingo_routes'] = $routesBefore;
check(str_contains($withNewOrder, '/orders/new?customer=' . $amina['id']), 'New Order appears when the orders step exists');
check(!route_defined('GET', '/orders/new'), 'the temporary orders route is removed');

$storedColumns = db()->prepare(
    'SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table_name'
);
$storedColumns->execute(['table_name' => 'fashion_customers']);
$columnNames = $storedColumns->fetchAll(PDO::FETCH_COLUMN);
foreach (['spent_kobo', 'owed_kobo', 'total_spent', 'amount_owed', 'balance_kobo'] as $banned) {
    check(!in_array($banned, $columnNames, true), 'customers do not store ' . $banned);
}

$other = business_register([
    'business_name' => 'Other Customer Shop',
    'owner_name' => 'Bala',
    'email' => 'customers-other@example.com',
    'password' => 'password',
    'phone' => '08037654321',
    'company_type' => 'tailor',
]);
check(isset($other['tenant_id']), 'a second shop can sign up');
check(customer_profile($amina['id']) === null, 'another shop cannot open the customer');
check(customer_search('Amina') === [], 'another shop cannot search the customer');

$_POST = [
    'csrf' => 'not-the-token',
    'name' => 'Zainab',
    'phone' => '08134567890',
    'gender' => 'female',
    'customer_type' => 'regular',
];
check_throws(static fn () => customers_save(), 'saving a customer requires the CSRF token');

auth_login((string) $shop['user_id'], (string) $shop['tenant_id']);
module_save([
    'fashion.production',
    'fashion.finance',
    'fashion.measurements',
    'fashion.communication',
]);
check(module_enabled('fashion.orders') === false, 'orders module is switched off');
ob_start();
router_dispatch('GET', '/');
$closedHome = (string) ob_get_clean();
check(!str_contains($closedHome, 'href="' . url('/customers') . '"'), 'Customers link is hidden when orders is off');
foreach (['/customers', '/customers/new', '/customers/' . $amina['id']] as $path) {
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
    router_dispatch('POST', '/customers');
    check(false, 'saving a customer stays closed');
} catch (HttpException $e) {
    check($e->status() === 403, 'saving a customer returns 403 when orders is off');
}
try {
    customer_create([
        'name' => 'Zainab',
        'phone' => '08134567890',
        'gender' => 'female',
        'customer_type' => 'regular',
    ]);
    check(false, 'creating a customer stays closed');
} catch (HttpException $e) {
    check($e->status() === 403, 'creating a customer returns 403 when orders is off');
}
check_throws(static fn () => customers_index(), 'the customer list returns 403 when orders is off');
