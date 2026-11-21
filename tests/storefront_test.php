<?php

declare(strict_types=1);

require_once __DIR__ . '/support.php';
require_once dirname(__DIR__) . '/app/domain/accounts.php';
require_once dirname(__DIR__) . '/app/domain/modules.php';
require_once dirname(__DIR__) . '/app/domain/products.php';
require_once dirname(__DIR__) . '/app/domain/storefront.php';
require_once dirname(__DIR__) . '/app/domain/access.php';

$connected = db()->query('SELECT DATABASE()')->fetchColumn();
if (!is_string($connected) || !str_ends_with($connected, '_test')) {
    fwrite(STDERR, "Refusing to run storefront tests: database name must end with _test.\n");
    exit(1);
}

tests_reset();

$shop = business_register([
    'business_name' => 'Online Brand',
    'owner_name' => 'Owner Online',
    'email' => 'online-brand@example.com',
    'password' => 'password',
    'phone' => '08035550005',
    'company_type' => 'ready_to_wear',
]);
check(isset($shop['tenant_id']), 'storefront shop registers');
module_save(['fashion.orders', 'fashion.finance', 'fashion.communication', 'fashion.inventory', 'fashion.sales']);

check(can_access('/settings/storefront') === true, 'owner can open storefront settings');
$saved = storefront_settings_save([
    'slug' => 'online-brand',
    'enabled' => '1',
]);
check(isset($saved['ok']), 'storefront settings saved');
check_eq($saved['slug'] ?? '', 'online-brand', 'storefront slug normalized');

$product = product_create([
    'name' => 'Online Shirt',
    'sku' => 'ON-1',
    'description' => 'Cotton',
    'price_kobo' => '800000',
    'sizes' => 'L',
    'colours' => 'White',
]);
check(isset($product['product']['id']), 'storefront product created');

auth_logout();
auth_release_tenant();
$bound = storefront_bind('online-brand');
check($bound !== null, 'public storefront binds by slug');
$products = storefront_products();
check(count($products) === 1, 'storefront lists active products');
$order = storefront_place_order([
    'product_id' => (string) $product['product']['id'],
    'quantity' => 1,
    'size_label' => 'L',
    'colour_label' => 'White',
    'buyer_name' => 'Ada Buyer',
    'buyer_phone' => '08035553333',
]);
check(isset($order['sale_id']), 'storefront places online sale');
check_eq($order['total_kobo'] ?? 0, 800000, 'online sale total matches price');
auth_release_tenant();

$missing = storefront_bind('no-such-shop');
check($missing === null, 'unknown storefront slug is rejected');
