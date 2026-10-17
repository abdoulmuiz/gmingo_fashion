<?php

declare(strict_types=1);

require_once __DIR__ . '/support.php';
require_once dirname(__DIR__) . '/app/domain/seed.php';

check(seed_is_local(['environment' => 'local']) === true, 'local environment may seed');
check(seed_is_local(['environment' => 'dev']) === true, 'dev environment may seed');
check(seed_is_local(['environment' => 'production']) === false, 'production environment may not seed');
check(seed_is_local([]) === false, 'a missing environment may not seed');
check(str_contains(seed_refusal_message(), 'environment is local'), 'the seed refusal says the environment must be local');

$example = require dirname(__DIR__) . '/config/config.example.php';
check(($example['environment'] ?? '') === 'local', 'example config environment is local');

$root = dirname(__DIR__);
$seedScript = (string) file_get_contents($root . '/tools/seed.php');
check(str_contains($seedScript, 'seed_is_local'), 'the seed script checks the environment');
check(str_contains($seedScript, 'seed_refusal_message'), 'the seed script refuses when it is not local');

foreach (['setup-windows.bat', 'setup-mac.command'] as $name) {
    $script = (string) file_get_contents($root . '/' . $name);
    check(str_contains($script, 'PHP 8.1'), $name . ' requires PHP 8.1');
    check(str_contains($script, 'tools/migrate.php'), $name . ' runs migrations');
    check(str_contains($script, 'tools/seed.php'), $name . ' runs the seed');
    check(str_contains($script, 'MySQL is not running'), $name . ' explains a stopped database');
}
$mac = (string) file_get_contents($root . '/setup-mac.command');
check(str_contains($mac, '/Applications/MAMP'), 'Mac setup looks for MAMP');
check(str_contains($mac, '/Applications/XAMPP'), 'Mac setup looks for XAMPP');
check(str_contains($mac, '8889'), 'Mac setup knows the MAMP MySQL port');

foreach (['update-windows.bat', 'update-mac.command'] as $name) {
    $script = (string) file_get_contents($root . '/' . $name);
    check(str_contains($script, 'tools/migrate.php'), $name . ' runs migrations');
    check(!str_contains($script, 'seed.php'), $name . ' does not seed');
    check(str_contains($script, 'PHP 8.1'), $name . ' requires PHP 8.1');
}

$readme = (string) file_get_contents($root . '/README.md');
check(str_contains($readme, 'XAMPP'), 'README explains XAMPP');
check(str_contains($readme, 'MAMP'), 'README explains MAMP');
check(str_contains($readme, '8889'), 'README gives the MAMP port');
check(str_contains($readme, 'root'), 'README gives the MAMP password');

$merge = (string) file_get_contents($root . '/MERGE.md');
check(str_contains($merge, 'current_user_id'), 'merge notes name current_user_id');
check(str_contains($merge, 'current_tenant_id'), 'merge notes name current_tenant_id');
check(str_contains($merge, 'require_login'), 'merge notes name require_login');
check(str_contains($merge, 'fashion_customers'), 'merge notes name the customers table');
check(str_contains($merge, 'fashion_payments'), 'merge notes name the payments table');

tests_reset();
$demo = seed_demo();
check($demo['status'] === 'created', 'the demo shop is created');

$tenants = db_select('fashion_tenants', [], ['limit' => 5]);
check($tenants[0]['name'] === 'Kano Stitches Tailoring', 'demo business name');
check($tenants[0]['company_type'] === 'tailor', 'demo company type is tailor');
$users = db_select('fashion_users', ['email' => 'demo@gmingo.test'], ['limit' => 5]);
check($users[0]['email'] === 'demo@gmingo.test', 'demo owner email');
check(password_verify('password', (string) $users[0]['password_hash']), 'demo password is password');
check($users[0]['role'] === 'owner', 'demo user is the owner');

foreach (module_defaults('tailor') as $key) {
    check(module_enabled($key), $key . ' is on for the tailor demo');
}
check(module_enabled('fashion.inventory') === false, 'inventory stays off for the tailor demo');
check(module_enabled('fashion.sales') === false, 'sales stays off for the tailor demo');
check(module_enabled('fashion.staff') === false, 'staff stays off for the tailor demo');
check(module_enabled('fashion.online_payments') === false, 'online payments stay off for the tailor demo');

check(count(db_select('fashion_customers', [], ['limit' => 20])) === 5, 'demo has 5 customers');
$orders = order_list('all', '');
check(count($orders) === 8, 'demo has 8 orders');
$stages = [];
$paidOrders = 0;
foreach ($orders as $order) {
    $code = $order['stage_code'] === null ? 'new' : $order['stage_code'];
    $stages[$code] = true;
    check(str_starts_with($order['order_number'], 'TK'), 'demo order numbers use the tailor prefix');
    if ($order['paid_kobo'] > 0) {
        $paidOrders++;
    }
}
foreach (['new', 'cutting', 'sewing', 'embroidery', 'finishing', 'fitting', 'ready', 'delivered'] as $code) {
    check(isset($stages[$code]), 'demo orders include stage ' . $code);
}
check($paidOrders >= 1, 'demo orders include payments');
check(count(db_select('fashion_payments', [], ['limit' => 50])) >= 1, 'demo payment rows exist');
$partialPayments = 0;
$fullPayments = 0;
foreach ($orders as $order) {
    $total = (int) $order['total_kobo'];
    $paid = (int) $order['paid_kobo'];
    if ($paid > 0 && $paid < $total) {
        $partialPayments++;
    }
    if ($total > 0 && $paid >= $total) {
        $fullPayments++;
    }
}
check($partialPayments === 3, 'three demo orders are partly paid');
check($fullPayments === 1, 'one demo order is fully paid');
$overdue = 0;
foreach ($orders as $order) {
    if ((string) $order['due_date'] < app_today() && (string) ($order['stage_role'] ?? '') !== 'finished') {
        $overdue++;
    }
}
check($overdue >= 1, 'one demo order is overdue');
$measurements = db_select('fashion_measurements', [], ['limit' => 50]);
check(count($measurements) >= 5, 'demo measurements include a later version');
$versions = [];
foreach ($measurements as $measurement) {
    $versions[(string) $measurement['customer_id']] = ($versions[(string) $measurement['customer_id']] ?? 0) + 1;
}
check(max($versions) >= 2, 'one customer has more than one measurement version');
$measuredGarments = [];
foreach ($measurements as $measurement) {
    $measuredGarments[(string) $measurement['customer_id']][(string) $measurement['garment_type_id']] = true;
}
check(count($measuredGarments) === 2, 'two customers have saved measurements');
foreach ($measuredGarments as $garmentIds) {
    check(count($garmentIds) === 2, 'a measured customer has two garment types');
}

$before = [
    'tailor' => seed_test_rows('demo@gmingo.test'),
    'factory' => seed_test_rows('factory@gmingo.test'),
    'brand' => seed_test_rows('brand@gmingo.test'),
];
$again = seed_demo();
check($again['status'] === 'exists', 'running the seed again does not create a second shop');
check(count(db_select('fashion_customers', [], ['limit' => 20])) === 5, 'a second seed does not add customers');
check(count(order_list('all', '')) === 8, 'a second seed does not add orders');
$after = [
    'tailor' => seed_test_rows('demo@gmingo.test'),
    'factory' => seed_test_rows('factory@gmingo.test'),
    'brand' => seed_test_rows('brand@gmingo.test'),
];
check_eq($after, $before, 'running the seed twice gives the same row counts');

foreach (['tailor' => 'demo@gmingo.test', 'factory' => 'factory@gmingo.test', 'brand' => 'brand@gmingo.test'] as $type => $email) {
    check(login_user($email, 'password') === null, $type . ' owner signs in');
    $enabled = [];
    foreach (module_keys() as $key) {
        if (module_enabled($key)) {
            $enabled[] = $key;
        }
    }
    $expected = module_defaults($type);
    sort($enabled);
    sort($expected);
    check_eq($enabled, $expected, $type . ' has exactly its default modules on');
    $tenants = db_select('fashion_tenants', [], ['limit' => 5]);
    check($tenants[0]['company_type'] === $type, $type . ' company type is stored');
}

check(login_user('factory@gmingo.test', 'password') === null, 'factory owner signs in for isolation');
$factoryNames = array_column(db_select('fashion_customers', [], ['limit' => 20]), 'name');
check($factoryNames === [], 'the factory cannot see tailor or brand customers');
check(db_select('fashion_orders', [], ['limit' => 20]) === [], 'the factory cannot see tailor or brand orders');
$factoryStages = array_column(db_select('fashion_stage_definitions', [], ['limit' => 20]), 'name');
check(in_array('Sample line', $factoryStages, true), 'the factory has a sample production stage');

check(login_user('brand@gmingo.test', 'password') === null, 'brand owner signs in for isolation');
$brandNames = array_column(db_select('fashion_customers', [], ['limit' => 20]), 'name');
check(in_array('Demo Buyer 1', $brandNames, true), 'the brand has its own customer');
check(!in_array('Demo Customer 1', $brandNames, true), 'the brand cannot see a tailor customer');
check(count(order_list('all', '')) === 4, 'the brand has its sample orders');
check(count(db_select('fashion_payments', [], ['limit' => 20])) === 3, 'the brand has sample payments');

check(login_user('demo@gmingo.test', 'password') === null, 'tailor owner signs in for isolation');
$tailorNames = array_column(db_select('fashion_customers', [], ['limit' => 20]), 'name');
check(!in_array('Demo Buyer 1', $tailorNames, true), 'the tailor cannot see a brand customer');

check(login_user('tailor-staff@gmingo.test', 'password') === null, 'tailor staff can sign in');
check((string) db_select('fashion_users', ['id' => (string) current_user_id()])[0]['role'] === 'staff', 'the extra login is staff');
foreach (['settings_form', 'stages_form', 'garments_form'] as $page) {
    try {
        ob_start();
        $page();
        ob_end_clean();
        check(false, 'staff cannot open ' . $page);
    } catch (HttpException $e) {
        ob_end_clean();
        check($e->status() === 403, 'staff ' . $page . ' returns 403');
    }
}

function seed_test_rows(string $email): array
{
    check(login_user($email, 'password') === null, $email . ' signs in for the row count');
    return [
        count(db_select('fashion_customers', [], ['limit' => 50])),
        count(db_select('fashion_orders', [], ['limit' => 50])),
        count(db_select('fashion_payments', [], ['limit' => 50])),
        count(db_select('fashion_measurements', [], ['limit' => 50])),
        count(db_select('fashion_users', [], ['limit' => 20])),
        count(db_select('fashion_stage_definitions', [], ['limit' => 50])),
    ];
}

$demoName = 'Amina Yusuf';
business_register([
    'business_name' => 'Other Seed Shop',
    'owner_name' => 'Bala Tanko',
    'email' => 'other-seed@example.com',
    'password' => 'password123',
    'phone' => '08032220000',
    'company_type' => 'tailor',
]);
$otherCustomers = db_select('fashion_customers', [], ['limit' => 20]);
check($otherCustomers === [], 'another shop cannot see the demo customers');
$seen = false;
foreach ($otherCustomers as $customer) {
    if ($customer['name'] === $demoName) {
        $seen = true;
    }
}
check($seen === false, 'the demo customer name is hidden from the other shop');
