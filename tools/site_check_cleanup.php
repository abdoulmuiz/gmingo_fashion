<?php

declare(strict_types=1);

/**
 * Delete only shops created by tools/site_check.php.
 * Refuses to run unless config environment is local.
 *
 * Shop names start with "ZZ Check ".
 */

function site_check_prefix(): string
{
    return 'ZZ Check ';
}

function site_check_config(): array
{
    $root = dirname(__DIR__);
    $configFile = $root . '/config/config.php';
    if (!is_file($configFile)) {
        fwrite(STDERR, "Refusing to run: config/config.php is missing.\n");
        exit(1);
    }
    $config = require $configFile;
    if (!is_array($config)) {
        fwrite(STDERR, "Refusing to run: config/config.php is unreadable.\n");
        exit(1);
    }
    $environment = (string) ($config['environment'] ?? '');
    if ($environment !== 'local') {
        fwrite(STDERR, "Refusing to run: environment is not local.\n");
        exit(1);
    }
    return $config;
}

function site_check_pdo(array $config): PDO
{
    $db = $config['db'] ?? null;
    if (!is_array($db)) {
        fwrite(STDERR, "Refusing to run: database settings are missing.\n");
        exit(1);
    }
    $dsn = 'mysql:host=' . (string) $db['host'] . ';port=' . (int) $db['port'] . ';dbname=' . (string) $db['name'] . ';charset=utf8mb4';
    $socket = (string) ($db['socket'] ?? '');
    if ($socket !== '') {
        $dsn .= ';unix_socket=' . $socket;
    }
    return new PDO($dsn, (string) $db['user'], (string) ($db['password'] ?? ''), [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
}

function site_check_fashion_tables(PDO $pdo): array
{
    $rows = $pdo->query(
        "SELECT table_name AS name FROM information_schema.tables
         WHERE table_schema = DATABASE() AND table_name LIKE 'fashion\\_%'"
    )->fetchAll();
    $names = [];
    foreach ($rows as $row) {
        $name = (string) $row['name'];
        if (preg_match('/^fashion_[a-z0-9_]+$/', $name) === 1) {
            $names[] = $name;
        }
    }
    sort($names);
    return $names;
}

function site_check_remove(PDO $pdo): array
{
    $prefix = site_check_prefix() . '%';
    $find = $pdo->prepare('SELECT id, name FROM fashion_tenants WHERE name LIKE :prefix');
    $find->execute(['prefix' => $prefix]);
    $tenants = $find->fetchAll();
    if ($tenants === []) {
        return [];
    }
    $tables = site_check_fashion_tables($pdo);
    $deleted = [];
    $pdo->beginTransaction();
    try {
        foreach ($tenants as $tenant) {
            $id = (string) $tenant['id'];
            foreach ($tables as $table) {
                if ($table === 'fashion_tenants') {
                    continue;
                }
                $hasTenant = $pdo->prepare(
                    'SELECT COUNT(*) AS n FROM information_schema.columns
                     WHERE table_schema = DATABASE() AND table_name = :table_name AND column_name = :column_name'
                );
                $hasTenant->execute(['table_name' => $table, 'column_name' => 'tenant_id']);
                if ((int) $hasTenant->fetch()['n'] !== 1) {
                    continue;
                }
                $delete = $pdo->prepare('DELETE FROM ' . $table . ' WHERE tenant_id = :id');
                $delete->execute(['id' => $id]);
            }
            $deleteTenant = $pdo->prepare('DELETE FROM fashion_tenants WHERE id = :id AND name LIKE :prefix');
            $deleteTenant->execute(['id' => $id, 'prefix' => $prefix]);
            $deleted[] = (string) $tenant['name'];
        }
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
    return $deleted;
}

if (PHP_SAPI === 'cli' && realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    try {
        $pdo = site_check_pdo(site_check_config());
        $deleted = site_check_remove($pdo);
    } catch (Throwable $e) {
        fwrite(STDERR, "Cleanup failed: " . $e->getMessage() . "\n");
        exit(1);
    }
    if ($deleted === []) {
        echo "No ZZ Check businesses to delete.\n";
        exit(0);
    }
    foreach ($deleted as $name) {
        echo 'Deleted ' . $name . "\n";
    }
}
