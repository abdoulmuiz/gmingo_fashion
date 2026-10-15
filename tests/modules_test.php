<?php

declare(strict_types=1);

require_once __DIR__ . '/support.php';
require_once dirname(__DIR__) . '/app/domain/accounts.php';
require_once dirname(__DIR__) . '/app/fashion/controllers/settings.php';

$connected = db()->query('SELECT DATABASE()')->fetchColumn();
if (!is_string($connected) || !str_ends_with($connected, '_test')) {
    fwrite(STDERR, "Refusing to run module tests: database name must end with _test.\n");
    exit(1);
}

tests_reset();

function module_shop(string $type, string $email, string $phone): array
{
    return business_register([
        'business_name' => 'Company ' . $type,
        'owner_name' => 'Amina Bello',
        'email' => $email,
        'password' => 'password',
        'phone' => $phone,
        'company_type' => $type,
    ]);
}

function enabled_module_keys(): array
{
    $keys = [];
    foreach (db_select('fashion_tenant_modules') as $row) {
        if ((int) $row['enabled'] === 1) {
            $keys[] = (string) $row['module_key'];
        }
    }
    sort($keys);
    return $keys;
}

$invalid = business_register([
    'business_name' => 'No Type',
    'owner_name' => 'Amina',
    'email' => 'notype@example.com',
    'password' => 'password',
    'phone' => '08031110000',
    'company_type' => 'school',
]);
check_eq($invalid['errors']['company_type'] ?? '', 'invalid_company_type', 'sign-up requires a known company type');

$tailor = module_shop('tailor', 'tailor@example.com', '08031111111');
check(isset($tailor['tenant_id']), 'tailor shop is created');
$tailorKeys = [
    'fashion.communication',
    'fashion.finance',
    'fashion.measurements',
    'fashion.orders',
    'fashion.production',
];
check_eq(enabled_module_keys(), $tailorKeys, 'tailor starts with orders, production, finance, measurements and communication');
check(count(db_select('fashion_tenant_modules')) === 8, 'every module has a switch');
$tailorOrders = db_select('fashion_tenant_modules', ['module_key' => 'fashion.orders']);
check(count(db_select('fashion_garment_types', ['code' => 'kaftan'])) === 1, 'tailor keeps garment settings on the business');
$tailorStages = array_column(db_select('fashion_stage_definitions', [], ['order' => 'sort_order ASC']), 'name');
check(in_array('Cutting', $tailorStages, true), 'tailor stage names are that business settings');

$factory = module_shop('factory', 'factory@example.com', '08032222222');
$factoryKeys = [
    'fashion.finance',
    'fashion.inventory',
    'fashion.production',
    'fashion.staff',
];
check_eq(enabled_module_keys(), $factoryKeys, 'factory starts with production, finance, inventory and staff');
check(db_select('fashion_garment_types') === [], 'factory is not given tailor garment types');
$factoryStages = array_column(db_select('fashion_stage_definitions'), 'name');
check(!in_array('Cutting', $factoryStages, true), 'factory stage names do not assume tailoring');

ob_start();
router_dispatch('GET', '/');
$factoryHome = (string) ob_get_clean();
check(!str_contains($factoryHome, 'href="' . url('/orders') . '"'), 'disabled orders link is hidden');
check(!str_contains($factoryHome, 'href="' . url('/customers') . '"'), 'disabled customers link is hidden');
check(str_contains($factoryHome, 'href="' . url('/board') . '"'), 'enabled production link stays');
check(str_contains($factoryHome, 'href="' . url('/') . '"'), 'dashboard stays in the navigation');
check(str_contains($factoryHome, 'href="' . url('/more') . '"'), 'more stays in the navigation');
try {
    router_dispatch('GET', '/orders');
    check(false, 'disabled orders page stays closed');
} catch (HttpException $e) {
    check($e->status() === 403, 'disabled orders returns 403');
}
ob_start();
router_dispatch('GET', '/board');
$board = (string) ob_get_clean();
check(str_contains($board, t('board')), 'enabled production page opens');

$brand = module_shop('brand', 'brand@example.com', '08033333333');
$brandKeys = [
    'fashion.communication',
    'fashion.finance',
    'fashion.inventory',
    'fashion.orders',
    'fashion.sales',
];
check_eq(enabled_module_keys(), $brandKeys, 'brand starts with orders, finance, communication, inventory and sales');
$brandOrders = db_select('fashion_tenant_modules', ['module_key' => 'fashion.orders']);
check((int) $brandOrders[0]['enabled'] === 1, 'brand orders start on');
try {
    router_dispatch('GET', '/board');
    check(false, 'brand production stays closed');
} catch (HttpException $e) {
    check($e->status() === 403, 'disabled production returns 403');
}

auth_login((string) $tailor['user_id'], (string) $tailor['tenant_id']);
module_save(['fashion.production', 'fashion.finance', 'fashion.measurements', 'fashion.communication']);
check(module_enabled('fashion.orders') === false, 'owner can switch orders off');
$log = db_select('fashion_activity_log', ['module_key' => 'fashion.orders']);
check(count($log) === 1 && (int) $log[0]['enabled'] === 0, 'the switch is written to the activity log');
check_throws(
    fn () => db_update('fashion_activity_log', ['enabled' => 1], ['id' => $log[0]['id']]),
    'activity log is append-only'
);

auth_login((string) $brand['user_id'], (string) $brand['tenant_id']);
check(module_enabled('fashion.orders') === true, 'brand orders stay on after the tailor switch');
check(db_select('fashion_activity_log') === [], 'brand cannot read the tailor activity log');
check_eq(
    db_update('fashion_tenant_modules', ['enabled' => 0], ['id' => $tailorOrders[0]['id']]),
    0,
    'brand cannot update the tailor module switch'
);
check(db_select('fashion_tenant_modules', ['id' => $tailorOrders[0]['id']]) === [], 'brand cannot select the tailor switch');

auth_login((string) $tailor['user_id'], (string) $tailor['tenant_id']);
$still = db_select('fashion_tenant_modules', ['module_key' => 'fashion.orders']);
check((int) $still[0]['enabled'] === 0, 'tailor switch is unchanged');
try {
    router_dispatch('GET', '/orders');
    check(false, 'tailor orders stay closed after the switch');
} catch (HttpException $e) {
    check($e->status() === 403, 'switched-off orders return 403');
}
ob_start();
router_dispatch('GET', '/');
$tailorHome = (string) ob_get_clean();
check(!str_contains($tailorHome, 'href="' . url('/orders') . '"'), 'switched-off orders leave the navigation');

$_POST = [
    'csrf' => csrf_token(),
    'modules' => ['fashion.orders', 'fashion.production'],
];
try {
    settings_submit();
    check(false, 'saving modules redirects');
} catch (RedirectException $e) {
    check_eq($e->path(), '/settings', 'settings returns to the settings page');
}
check(module_enabled('fashion.orders') === true, 'settings turns orders back on');
check(module_enabled('fashion.production') === true, 'settings turns production on');
check(module_enabled('fashion.finance') === false, 'settings turns finance off');

db_update('fashion_users', ['role' => 'staff'], ['id' => (string) current_user_id()]);
try {
    settings_form();
    check(false, 'staff cannot open module settings');
} catch (HttpException $e) {
    check($e->status() === 403, 'staff settings return 403');
}

$column = db()->prepare(
    'SELECT IS_NULLABLE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table_name AND COLUMN_NAME = :column_name'
);
$column->execute(['table_name' => 'fashion_order_items', 'column_name' => 'product_id']);
$product = $column->fetch();
check(is_array($product) && $product['IS_NULLABLE'] === 'YES', 'order items can point at a product');
$column->execute(['table_name' => 'fashion_tenants', 'column_name' => 'company_type']);
$type = $column->fetch();
check(is_array($type), 'tenants store a company type');
