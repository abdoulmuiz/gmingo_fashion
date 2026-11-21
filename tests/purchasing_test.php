<?php

declare(strict_types=1);

require_once __DIR__ . '/support.php';
require_once dirname(__DIR__) . '/app/domain/accounts.php';
require_once dirname(__DIR__) . '/app/domain/modules.php';
require_once dirname(__DIR__) . '/app/domain/stock.php';
require_once dirname(__DIR__) . '/app/domain/purchasing.php';
require_once dirname(__DIR__) . '/app/domain/access.php';

$connected = db()->query('SELECT DATABASE()')->fetchColumn();
if (!is_string($connected) || !str_ends_with($connected, '_test')) {
    fwrite(STDERR, "Refusing to run purchasing tests: database name must end with _test.\n");
    exit(1);
}

tests_reset();

$shop = business_register([
    'business_name' => 'Buy Shop',
    'owner_name' => 'Owner Buy',
    'email' => 'buy-shop@example.com',
    'password' => 'password',
    'phone' => '08035550002',
    'company_type' => 'factory',
]);
check(isset($shop['tenant_id']), 'purchasing shop registers');
module_save(['fashion.production', 'fashion.finance', 'fashion.inventory', 'fashion.staff']);

$item = stock_create([
    'name' => 'Thread cone',
    'category' => 'thread',
    'unit' => 'cones',
    'kind' => 'raw',
    'unit_cost_kobo' => '0',
    'colour' => '',
    'minimum' => '',
]);
check(isset($item['id']), 'stock item for PO created');

$supplier = supplier_create([
    'name' => 'Lagos Textiles',
    'phone' => '08035550099',
    'email' => 'supplier@example.com',
    'note' => '',
]);
check(isset($supplier['supplier_id']), 'supplier created');
check(can_access('/purchasing') === true, 'owner can open purchasing');
check(can_access('/expenses') === true, 'owner can open expenses');

$po = purchase_order_create([
    'supplier_id' => (string) $supplier['supplier_id'],
    'stock_item_id' => (string) $item['id'],
    'quantity' => '10',
    'unit_cost_kobo' => '25000',
    'note' => 'First buy',
]);
check(isset($po['purchase_order_id']), 'purchase order created');
$received = purchase_order_receive((string) $po['purchase_order_id']);
check(isset($received['ok']), 'purchase order received');
$found = stock_find((string) $item['id']);
check_eq((int) ($found['quantity_hundredths'] ?? 0), 1000, 'receiving posts stock movement');
check_eq((int) ($found['unit_cost_kobo'] ?? 0), 25000, 'unit cost updates on receive');

$expense = expense_create([
    'category' => 'fuel',
    'amount_kobo' => '500000',
    'spent_on' => app_today(),
    'note' => 'Generator',
]);
check(isset($expense['expense_id']), 'expense created');
check(count(expense_list()) === 1, 'expense listed');
