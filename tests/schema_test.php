<?php

declare(strict_types=1);

require_once __DIR__ . '/support.php';

$main = (string) app_config()['db']['name'];
$before = tests_table_count($main);
$applied = schema_migrate();
$again = schema_migrate();
$after = tests_table_count($main);

check(str_ends_with((string) db()->query('SELECT DATABASE()')->fetchColumn(), '_test'), 'migration connects to the test database');
$knownMigrations = ['001_init.sql', '002_company_modules.sql', '003_stage_roles.sql', '004_payment_settings.sql'];
foreach ($applied as $file) {
    check(in_array($file, $knownMigrations, true), $file . ' is a fashion migration');
}
check_eq($again, [], 'migrate does not repeat work');
check_eq($after, $before, 'tests do not create or drop tables in the main database');
check(schema_sql_is_safe((string) file_get_contents(dirname(__DIR__) . '/database/migrations/001_init.sql')), 'init migration has no drop');
check(!schema_sql_is_safe('DROP TABLE fashion_customers'), 'drop is rejected');
check(!schema_sql_is_safe('TRUNCATE fashion_orders'), 'truncate is rejected');
check(!preg_match('/\bdrop\s+database\b/i', (string) file_get_contents(dirname(__DIR__) . '/tools/migrate.php')), 'migrate tool has no drop database');

$expected = [
    'fashion_tenants',
    'fashion_users',
    'fashion_customers',
    'fashion_orders',
    'fashion_order_items',
    'fashion_order_groups',
    'fashion_order_counters',
    'fashion_stage_definitions',
    'fashion_stage_events',
    'fashion_payments',
    'fashion_garment_types',
    'fashion_measurement_fields',
    'fashion_measurements',
    'fashion_worker_rates',
    'fashion_expenses',
    'fashion_tenant_modules',
    'fashion_activity_log',
    'fashion_payment_settings',
];
$present = db()->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
foreach ($expected as $table) {
    check(in_array($table, $present, true), $table . ' exists');
}

$payment = db()->prepare(
    'SELECT DATA_TYPE, COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table_name AND COLUMN_NAME = :column_name'
);
$payment->execute(['table_name' => 'fashion_payments', 'column_name' => 'amount_kobo']);
$amount = $payment->fetch();
check(is_array($amount) && $amount['DATA_TYPE'] === 'bigint', 'payment amount is bigint kobo');

$idColumn = db()->prepare(
    'SELECT DATA_TYPE, CHARACTER_MAXIMUM_LENGTH FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table_name AND COLUMN_NAME = :column_name'
);
$idColumn->execute(['table_name' => 'fashion_orders', 'column_name' => 'id']);
$id = $idColumn->fetch();
check(is_array($id) && $id['DATA_TYPE'] === 'char' && (int) $id['CHARACTER_MAXIMUM_LENGTH'] === 36, 'order id is a UUID char(36)');

$group = db()->prepare(
    'SELECT IS_NULLABLE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table_name AND COLUMN_NAME = :column_name'
);
$group->execute(['table_name' => 'fashion_orders', 'column_name' => 'group_id']);
$groupRow = $group->fetch();
check(is_array($groupRow) && $groupRow['IS_NULLABLE'] === 'YES', 'group_id is nullable for later group orders');

$reverse = db()->prepare(
    'SELECT IS_NULLABLE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table_name AND COLUMN_NAME = :column_name'
);
$reverse->execute(['table_name' => 'fashion_payments', 'column_name' => 'reverses_payment_id']);
$reverseRow = $reverse->fetch();
check(is_array($reverseRow) && $reverseRow['IS_NULLABLE'] === 'YES', 'payments can point at the row they reverse');

$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(dirname(__DIR__) . '/app'));
foreach ($iterator as $file) {
    if (!$file->isFile() || $file->getExtension() !== 'php') {
        continue;
    }
    $text = (string) file_get_contents($file->getPathname());
    $base = $file->getBasename();
    if (preg_match('/->(query|exec|prepare)\s*\(/', $text)) {
        check(in_array($base, ['db.php', 'schema.php'], true), $base . ' is allowed to prepare SQL');
    }
    if ($base !== 'auth.php') {
        check(!str_contains($text, '$_SESSION'), $base . ' does not read the session');
    }
}

$auth = (string) file_get_contents(dirname(__DIR__) . '/app/core/auth.php');
$accounts = (string) file_get_contents(dirname(__DIR__) . '/app/domain/accounts.php');
check(str_contains($accounts, 'platform_query_unsafe'), 'sign-up and login use the platform query');
check(str_contains($accounts, 'WHERE email = :email LIMIT 1'), 'the platform lookup is the email check');
check(!preg_match('/->prepare\s*\(/', $auth), 'auth does not prepare SQL itself');
