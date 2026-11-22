<?php

declare(strict_types=1);

require_once __DIR__ . '/support.php';

$main = (string) app_config()['db']['name'];
$before = tests_table_count($main);
$applied = schema_migrate();
$again = schema_migrate();
$after = tests_table_count($main);

check(str_ends_with((string) db()->query('SELECT DATABASE()')->fetchColumn(), '_test'), 'migration connects to the test database');
$knownMigrations = ['001_init.sql', '002_company_modules.sql', '003_stage_roles.sql', '004_payment_settings.sql', '005_paystack.sql', '006_payment_reason.sql', '007_field_unit.sql', '008_deposit_stage.sql', '009_fabric_log.sql', '010_order_groups.sql', '011_stock.sql', '012_fittings.sql', '013_messages.sql', '014_tracking.sql', '015_receipts.sql', '016_customer_lang.sql', '017_subscription_plans.sql', '018_tenant_subscriptions.sql', '019_onboarding.sql', '020_platform_admin.sql', '021_subscription_billing.sql', '022_staff_rbac.sql', '023_products_catalogue.sql', '024_sales.sql', '025_purchasing.sql', '026_work_orders.sql', '027_branches.sql', '028_broadcasts.sql', '029_storefront.sql', '030_platform_admin_security.sql', '031_saas_gateway.sql', '032_dynamic_pricing.sql'];
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
    'fashion_order_fabric_log',
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
    'fashion_payment_requests',
    'fashion_stock_items',
    'fashion_stock_movements',
    'fashion_fittings',
    'fashion_message_templates',
    'fashion_messages',
    'fashion_track_limits',
    'fashion_receipt_counters',
    'fashion_receipts',
    'fashion_receipt_voids',
    'fashion_plans',
    'fashion_subscriptions',
    'fashion_subscription_payments',
    'fashion_subscription_events',
    'fashion_subscription_checkouts',
    'fashion_saas_gateway_settings',
    'fashion_saas_webhook_receipts',
    'fashion_products',
    'fashion_product_bom',
    'fashion_sales',
    'fashion_sale_lines',
    'fashion_sale_counters',
    'fashion_suppliers',
    'fashion_purchase_orders',
    'fashion_purchase_order_lines',
    'fashion_work_orders',
    'fashion_quality_checks',
    'fashion_branches',
    'fashion_broadcasts',
];
$present = db()->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
foreach ($expected as $table) {
    check(in_array($table, $present, true), $table . ' exists');
}

$storefrontSlug = db()->prepare(
    'SELECT DATA_TYPE, IS_NULLABLE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table_name AND COLUMN_NAME = :column_name'
);
$storefrontSlug->execute(['table_name' => 'fashion_tenants', 'column_name' => 'storefront_slug']);
$storefrontSlugRow = $storefrontSlug->fetch();
check(is_array($storefrontSlugRow) && $storefrontSlugRow['DATA_TYPE'] === 'varchar' && $storefrontSlugRow['IS_NULLABLE'] === 'YES', 'tenant storefront slug is optional varchar');

$planPrice = db()->prepare(
    'SELECT DATA_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table_name AND COLUMN_NAME = :column_name'
);
$planPrice->execute(['table_name' => 'fashion_plans', 'column_name' => 'price_monthly_kobo']);
$planPriceRow = $planPrice->fetch();
check(is_array($planPriceRow) && $planPriceRow['DATA_TYPE'] === 'bigint', 'plan monthly price is bigint kobo');
$subPay = db()->prepare(
    'SELECT DATA_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table_name AND COLUMN_NAME = :column_name'
);
$subPay->execute(['table_name' => 'fashion_subscription_payments', 'column_name' => 'amount_kobo']);
$subPayRow = $subPay->fetch();
check(is_array($subPayRow) && $subPayRow['DATA_TYPE'] === 'bigint', 'subscription payment amount is bigint kobo');

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

$reasonColumn = db()->prepare(
    'SELECT DATA_TYPE, CHARACTER_MAXIMUM_LENGTH, IS_NULLABLE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table_name AND COLUMN_NAME = :column_name'
);
$reasonColumn->execute(['table_name' => 'fashion_payments', 'column_name' => 'reason']);
$reasonColumnRow = $reasonColumn->fetch();
check(is_array($reasonColumnRow) && $reasonColumnRow['DATA_TYPE'] === 'varchar' && (int) $reasonColumnRow['CHARACTER_MAXIMUM_LENGTH'] === 500 && $reasonColumnRow['IS_NULLABLE'] === 'YES', 'a payment can store a reversal reason');

$eventDate = db()->prepare(
    'SELECT DATA_TYPE, IS_NULLABLE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table_name AND COLUMN_NAME = :column_name'
);
$eventDate->execute(['table_name' => 'fashion_order_groups', 'column_name' => 'event_date']);
$eventDateRow = $eventDate->fetch();
check(is_array($eventDateRow) && $eventDateRow['DATA_TYPE'] === 'date' && $eventDateRow['IS_NULLABLE'] === 'YES', 'a group can store an optional event date');
$sharedPayment = db()->prepare(
    'SELECT DATA_TYPE, CHARACTER_MAXIMUM_LENGTH, IS_NULLABLE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table_name AND COLUMN_NAME = :column_name'
);
$sharedPayment->execute(['table_name' => 'fashion_payments', 'column_name' => 'group_payment_id']);
$sharedPaymentRow = $sharedPayment->fetch();
check(is_array($sharedPaymentRow) && $sharedPaymentRow['DATA_TYPE'] === 'char' && (int) $sharedPaymentRow['CHARACTER_MAXIMUM_LENGTH'] === 36 && $sharedPaymentRow['IS_NULLABLE'] === 'YES', 'split payments share a group payment id');

$unitColumn = db()->prepare(
    'SELECT DATA_TYPE, CHARACTER_MAXIMUM_LENGTH, IS_NULLABLE, COLUMN_DEFAULT FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table_name AND COLUMN_NAME = :column_name'
);
$unitColumn->execute(['table_name' => 'fashion_measurement_fields', 'column_name' => 'unit']);
$unitColumnRow = $unitColumn->fetch();
check(is_array($unitColumnRow) && $unitColumnRow['DATA_TYPE'] === 'varchar' && (int) $unitColumnRow['CHARACTER_MAXIMUM_LENGTH'] === 20 && $unitColumnRow['IS_NULLABLE'] === 'NO', 'a measurement field can store a unit');

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
