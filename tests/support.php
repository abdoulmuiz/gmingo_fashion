<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/core/schema.php';
require_once dirname(__DIR__) . '/app/domain/modules.php';

function tests_table_count(string $schema): int
{
    if (!preg_match('/^[A-Za-z0-9_]+$/', $schema)) {
        throw new RuntimeException('Unsafe schema name.');
    }
    $statement = db_server()->prepare(
        'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = :schema AND table_name LIKE :prefix'
    );
    $statement->execute([
        'schema' => $schema,
        'prefix' => 'fashion_%',
    ]);
    return (int) $statement->fetchColumn();
}

function tests_reset(): void
{
    schema_migrate();
    $current = db()->query('SELECT DATABASE()')->fetchColumn();
    if (!is_string($current) || !str_ends_with($current, '_test')) {
        throw new RuntimeException('Refusing to change a database whose name does not end with _test.');
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
        'fashion_payments',
        'fashion_stage_events',
        'fashion_stage_definitions',
        'fashion_order_items',
        'fashion_orders',
        'fashion_garment_types',
        'fashion_order_counters',
        'fashion_order_groups',
        'fashion_customers',
        'fashion_users',
        'fashion_tenants',
    ];
    foreach ($tables as $table) {
        db()->exec('DELETE FROM ' . $table);
    }
    module_cache_clear();
    auth_release_tenant();
    auth_logout();
}
