<?php

declare(strict_types=1);

require_once __DIR__ . '/support.php';
require_once dirname(__DIR__) . '/app/domain/accounts.php';
require_once dirname(__DIR__) . '/app/domain/modules.php';
require_once dirname(__DIR__) . '/app/domain/branches.php';
require_once dirname(__DIR__) . '/app/domain/reports.php';
require_once dirname(__DIR__) . '/app/domain/broadcasts.php';
require_once dirname(__DIR__) . '/app/domain/customers.php';
require_once dirname(__DIR__) . '/app/domain/access.php';

$connected = db()->query('SELECT DATABASE()')->fetchColumn();
if (!is_string($connected) || !str_ends_with($connected, '_test')) {
    fwrite(STDERR, "Refusing to run branch/report/broadcast tests: database name must end with _test.\n");
    exit(1);
}

tests_reset();

$shop = business_register([
    'business_name' => 'Branch Shop',
    'owner_name' => 'Owner Branch',
    'email' => 'branch-shop@example.com',
    'password' => 'password',
    'phone' => '08035550004',
    'company_type' => 'brand',
]);
check(isset($shop['tenant_id']), 'branch shop registers');
module_save(['fashion.orders', 'fashion.finance', 'fashion.communication', 'fashion.inventory', 'fashion.sales']);

check(can_access('/branches') === true, 'owner can open branches');
$branch = branch_create([
    'name' => 'Ikeja',
    'phone' => '08035551111',
    'address' => 'Lagos',
]);
check(isset($branch['branch_id']), 'branch created');
check(branch_count() === 1, 'active branch counted');
$deactivated = branch_deactivate((string) $branch['branch_id']);
check(isset($deactivated['ok']), 'branch deactivated');
check(branch_count() === 0, 'inactive branch not counted');

check(can_access('/reports') === true, 'owner can open reports');
$summary = reports_summary();
check(isset($summary['sales_total_kobo']), 'reports summary returns sales total');

$customer = customer_create([
    'name' => 'Opt In',
    'phone' => '08035552222',
    'gender' => 'female',
    'customer_type' => 'regular',
]);
check(isset($customer['id']), 'broadcast customer created');
db_update('fashion_customers', ['messages_opt_in' => 1], ['id' => (string) $customer['id']]);
check(can_access('/broadcasts') === true, 'owner can open broadcasts');
$broadcast = broadcast_create([
    'title' => 'Eid offer',
    'body' => 'Come for fitting this weekend.',
]);
check(isset($broadcast['broadcast_id']), 'broadcast created');
check_eq($broadcast['recipient_count'] ?? 0, 1, 'broadcast counts opt-in customers only');
