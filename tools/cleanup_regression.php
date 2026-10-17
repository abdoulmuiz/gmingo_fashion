<?php

declare(strict_types=1);

/**
 * Delete only shops created by tools/regression_test.php.
 * Refuses to run unless config environment is local.
 */

$root = dirname(__DIR__);
$configFile = $root . '/config/config.php';
if (!is_file($configFile)) {
    fwrite(STDERR, "Refusing to clean up: config/config.php is missing.\n");
    exit(1);
}
$config = require $configFile;
if (!is_array($config)) {
    fwrite(STDERR, "Refusing to clean up: config/config.php is unreadable.\n");
    exit(1);
}
$environment = (string) ($config['environment'] ?? '');
if ($environment !== 'local') {
    fwrite(STDERR, "Refusing to clean up: environment is not local.\n");
    exit(1);
}
$db = $config['db'] ?? null;
if (!is_array($db)) {
    fwrite(STDERR, "Refusing to clean up: database settings are missing.\n");
    exit(1);
}

$dsn = 'mysql:host=' . (string) $db['host'] . ';port=' . (int) $db['port'] . ';dbname=' . (string) $db['name'] . ';charset=utf8mb4';
$socket = (string) ($db['socket'] ?? '');
if ($socket !== '') {
    $dsn .= ';unix_socket=' . $socket;
}
$pdo = new PDO($dsn, (string) $db['user'], (string) ($db['password'] ?? ''), [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);

$prefix = 'ZZ Regression%';
// Unsafe: this read is not limited to the logged-in shop. It lists every tenant whose name starts with "ZZ Regression".
$find = $pdo->prepare('SELECT id, name FROM fashion_tenants WHERE name LIKE :prefix');
$find->execute(['prefix' => $prefix]);
$tenants = $find->fetchAll();
if ($tenants === []) {
    echo "No ZZ Regression businesses to delete.\n";
    exit(0);
}

$tables = [
    'fashion_activity_log',
    'fashion_payment_requests',
    'fashion_payment_settings',
    'fashion_tenant_modules',
    'fashion_expenses',
    'fashion_worker_rates',
    'fashion_measurements',
    'fashion_measurement_fields',
    'fashion_receipt_voids',
    'fashion_receipts',
    'fashion_payments',
    'fashion_stage_events',
    'fashion_stage_definitions',
        'fashion_stock_movements',
        'fashion_stock_items',
        'fashion_track_limits',
        'fashion_messages',
        'fashion_message_templates',
        'fashion_fittings',
        'fashion_order_fabric_log',
    'fashion_order_items',
    'fashion_orders',
    'fashion_garment_types',
    'fashion_receipt_counters',
    'fashion_order_counters',
    'fashion_order_groups',
    'fashion_customers',
    'fashion_users',
    'fashion_tenants',
];

$pdo->beginTransaction();
try {
    foreach ($tenants as $tenant) {
        $id = (string) $tenant['id'];
        foreach ($tables as $table) {
            if ($table === 'fashion_tenants') {
                // Unsafe: deletes the regression tenant row by id gathered from the name prefix, outside the app tenant filter.
                $delete = $pdo->prepare('DELETE FROM fashion_tenants WHERE id = :id AND name LIKE :prefix');
                $delete->execute(['id' => $id, 'prefix' => $prefix]);
                continue;
            }
            // Unsafe: deletes this regression tenant's rows from a business table without the app tenant filter.
            $delete = $pdo->prepare('DELETE FROM ' . $table . ' WHERE tenant_id = :id');
            $delete->execute(['id' => $id]);
        }
        echo 'Deleted ' . (string) $tenant['name'] . "\n";
    }
    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    fwrite(STDERR, "Cleanup failed: " . $e->getMessage() . "\n");
    exit(1);
}
