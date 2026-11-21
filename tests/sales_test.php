<?php

declare(strict_types=1);

require_once __DIR__ . '/support.php';
require_once dirname(__DIR__) . '/app/domain/accounts.php';
require_once dirname(__DIR__) . '/app/domain/modules.php';
require_once dirname(__DIR__) . '/app/domain/stock.php';
require_once dirname(__DIR__) . '/app/domain/products.php';
require_once dirname(__DIR__) . '/app/domain/sales.php';
require_once dirname(__DIR__) . '/app/domain/access.php';

$connected = db()->query('SELECT DATABASE()')->fetchColumn();
if (!is_string($connected) || !str_ends_with($connected, '_test')) {
    fwrite(STDERR, "Refusing to run sales tests: database name must end with _test.\n");
    exit(1);
}

tests_reset();

$shop = business_register([
    'business_name' => 'Sales Shop',
    'owner_name' => 'Owner Sales',
    'email' => 'sales-shop@example.com',
    'password' => 'password',
    'phone' => '08035550001',
    'company_type' => 'brand',
]);
check(isset($shop['tenant_id']), 'sales shop registers');
module_save(['fashion.orders', 'fashion.finance', 'fashion.inventory', 'fashion.sales']);

$finished = stock_create([
    'name' => 'Dress stock',
    'category' => 'other',
    'unit' => 'pieces',
    'kind' => 'finished',
    'unit_cost_kobo' => '100000',
    'colour' => '',
    'minimum' => '',
]);
check(isset($finished['id']), 'finished stock created');
stock_post_movement((string) $finished['id'], 500, 'opening', 'Open');

$product = product_create([
    'name' => 'POS Dress',
    'sku' => 'POS-1',
    'description' => 'Retail',
    'price_kobo' => '1500000',
    'sizes' => 'M',
    'colours' => 'Red',
]);
check(isset($product['product']['id']), 'sale product created');
db_update('fashion_products', ['stock_item_id' => (string) $finished['id']], ['id' => (string) $product['product']['id']]);

check(can_access('/sales') === true, 'owner can open sales');
$sale = sale_create([
    'product_id' => (string) $product['product']['id'],
    'quantity' => 2,
    'channel' => 'retail',
    'size_label' => 'M',
    'colour_label' => 'Red',
    'note' => '',
]);
check(isset($sale['sale_id']), 'sale is recorded');
check_eq($sale['total_kobo'] ?? 0, 3000000, 'sale total is quantity times price');
$list = sale_list();
check(count($list) === 1, 'sale is listed');
$item = stock_find((string) $finished['id']);
check_eq((int) ($item['quantity_hundredths'] ?? 0), 300, 'sale reduces finished stock');

$returned = sale_return((string) $sale['sale_id']);
check(isset($returned['ok']), 'sale return succeeds');
$item = stock_find((string) $finished['id']);
check_eq((int) ($item['quantity_hundredths'] ?? 0), 500, 'return restores finished stock');
