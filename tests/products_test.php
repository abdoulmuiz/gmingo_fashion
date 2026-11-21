<?php

declare(strict_types=1);

require_once __DIR__ . '/support.php';
require_once dirname(__DIR__) . '/app/domain/accounts.php';
require_once dirname(__DIR__) . '/app/domain/modules.php';
require_once dirname(__DIR__) . '/app/domain/stock.php';
require_once dirname(__DIR__) . '/app/domain/products.php';
require_once dirname(__DIR__) . '/app/domain/access.php';

$connected = db()->query('SELECT DATABASE()')->fetchColumn();
if (!is_string($connected) || !str_ends_with($connected, '_test')) {
    fwrite(STDERR, "Refusing to run products tests: database name must end with _test.\n");
    exit(1);
}

tests_reset();

$shop = business_register([
    'business_name' => 'Catalogue Shop',
    'owner_name' => 'Owner Cat',
    'email' => 'catalogue@example.com',
    'password' => 'password',
    'phone' => '08034440001',
    'company_type' => 'brand',
]);
check(isset($shop['tenant_id']), 'catalogue shop registers');
module_save(['fashion.orders', 'fashion.finance', 'fashion.communication', 'fashion.inventory', 'fashion.sales']);

$fabric = stock_create([
    'name' => 'Ankara bolt',
    'category' => 'fabric',
    'unit' => 'yards',
    'kind' => 'raw',
    'unit_cost_kobo' => '50000',
    'colour' => 'Blue',
    'minimum' => '2',
]);
check(isset($fabric['id']), 'fabric stock item created');
$item = stock_find((string) $fabric['id']);
check_eq($item['kind'] ?? '', 'raw', 'stock kind is raw');
check_eq($item['unit_cost_kobo'] ?? null, 50000, 'stock unit cost stored');

$created = product_create([
    'name' => 'Summer Dress',
    'sku' => 'DR-SUM-01',
    'description' => 'Ready-to-wear dress',
    'price_kobo' => '2500000',
    'sizes' => 'S, M, L',
    'colours' => 'Blue, Cream',
]);
check(isset($created['product']['id']), 'product is created');
$productId = (string) $created['product']['id'];
check_eq($created['product']['sizes'], ['S', 'M', 'L'], 'sizes parsed');
check_eq($created['product']['price_kobo'], 2500000, 'price is whole kobo');

$bom = product_bom_add($productId, [
    'stock_item_id' => (string) $fabric['id'],
    'quantity' => '2.50',
]);
check(isset($bom['ok']), 'bom line added');
$lines = product_bom_list($productId);
check(count($lines) === 1, 'one bom line listed');
check_eq($lines[0]['quantity_hundredths'], 250, 'bom quantity is hundredths');
$cost = product_estimated_cost_kobo($productId);
check_eq($cost, 125000, 'estimated cost uses unit cost times quantity');

$updated = product_update($productId, [
    'name' => 'Summer Dress Updated',
    'sku' => 'DR-SUM-01',
    'description' => 'Updated',
    'price_kobo' => '2600000',
    'sizes' => 'M, L, XL',
    'colours' => 'Blue',
    'active' => true,
]);
check_eq($updated['product']['name'] ?? '', 'Summer Dress Updated', 'product name updates');
check(can_access('/products') === true, 'owner can access products menu route');
