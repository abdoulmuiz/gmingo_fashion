<?php

declare(strict_types=1);

require_once __DIR__ . '/support.php';
require_once dirname(__DIR__) . '/app/domain/accounts.php';
require_once dirname(__DIR__) . '/app/domain/modules.php';
require_once dirname(__DIR__) . '/app/domain/work_orders.php';
require_once dirname(__DIR__) . '/app/domain/access.php';

$connected = db()->query('SELECT DATABASE()')->fetchColumn();
if (!is_string($connected) || !str_ends_with($connected, '_test')) {
    fwrite(STDERR, "Refusing to run work order tests: database name must end with _test.\n");
    exit(1);
}

tests_reset();

$shop = business_register([
    'business_name' => 'Work Shop',
    'owner_name' => 'Owner Work',
    'email' => 'work-shop@example.com',
    'password' => 'password',
    'phone' => '08035550003',
    'company_type' => 'factory',
]);
check(isset($shop['tenant_id']), 'work order shop registers');
module_save(['fashion.production', 'fashion.finance', 'fashion.inventory', 'fashion.staff']);
check(can_access('/work-orders') === true, 'owner can open work orders');

$created = work_order_create([
    'title' => 'Batch agbada',
    'quantity' => 12,
    'due_on' => app_today(),
    'note' => 'Rush',
]);
check(isset($created['work_order_id']), 'work order created');
$updated = work_order_set_status((string) $created['work_order_id'], 'in_progress');
check(isset($updated['ok']), 'work order status updates');
$qc = quality_check_record([
    'work_order_id' => (string) $created['work_order_id'],
    'result' => 'pass',
    'reason' => '',
]);
check(isset($qc['quality_check_id']), 'quality check recorded');
check(count(work_order_list()) === 1, 'work orders listed');
