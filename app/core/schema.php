<?php

declare(strict_types=1);

function schema_sql_is_safe(string $sql): bool
{
    $bare = db_strip_literals($sql);
    return !preg_match('/\b(drop|truncate|rename|alter\s+table\s+\S+\s+drop)\b/i', $bare);
}

function schema_split_sql(string $sql): array
{
    $statements = [];
    $buffer = '';
    $quote = null;
    $length = strlen($sql);
    for ($i = 0; $i < $length; $i++) {
        $char = $sql[$i];
        if ($quote === null && $char === '-' && ($sql[$i + 1] ?? '') === '-') {
            while ($i < $length && $sql[$i] !== "\n") {
                $i++;
            }
            continue;
        }
        if ($quote === null && ($char === "'" || $char === '"')) {
            $quote = $char;
            $buffer .= $char;
            continue;
        }
        if ($quote !== null) {
            $buffer .= $char;
            if ($char === '\\' && $i + 1 < $length) {
                $i++;
                $buffer .= $sql[$i];
                continue;
            }
            if ($char === $quote) {
                $quote = null;
            }
            continue;
        }
        if ($char === ';') {
            $statement = trim($buffer);
            if ($statement !== '') {
                $statements[] = $statement;
            }
            $buffer = '';
            continue;
        }
        $buffer .= $char;
    }
    $statement = trim($buffer);
    if ($statement !== '') {
        $statements[] = $statement;
    }
    return $statements;
}

function schema_ensure_database(): void
{
    $name = db_settings()['name'];
    $server = db_server();
    $server->exec(
        'CREATE DATABASE IF NOT EXISTS `' . $name . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'
    );
}

function schema_ensure_migrations_table(): void
{
    db()->exec(
        'CREATE TABLE IF NOT EXISTS fashion_schema_migrations (
            id CHAR(36) NOT NULL,
            tenant_id CHAR(36) NOT NULL,
            filename VARCHAR(190) NOT NULL,
            applied_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uq_fashion_schema_migrations_file (filename)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );
}

function schema_migrate(): array
{
    schema_ensure_database();
    schema_ensure_migrations_table();
    $applied = [];
    $existing = [];
    $rows = db()->query('SELECT filename FROM fashion_schema_migrations')->fetchAll();
    foreach ($rows as $row) {
        $existing[$row['filename']] = true;
    }
    $files = glob(dirname(__DIR__, 2) . '/database/migrations/*.sql') ?: [];
    sort($files);
    $insert = db()->prepare(
        'INSERT INTO fashion_schema_migrations (id, tenant_id, filename, applied_at) VALUES (:id, :tenant_id, :filename, :applied_at)'
    );
    foreach ($files as $file) {
        $filename = basename($file);
        if (isset($existing[$filename])) {
            continue;
        }
        $sql = (string) file_get_contents($file);
        if (!schema_sql_is_safe($sql)) {
            throw new RuntimeException('Migrations cannot drop or truncate anything.');
        }
        foreach (schema_split_sql($sql) as $statement) {
            db()->exec($statement);
        }
        $insert->execute([
            'id' => uuid(),
            'tenant_id' => '00000000-0000-0000-0000-000000000000',
            'filename' => $filename,
            'applied_at' => date('Y-m-d H:i:s'),
        ]);
        $applied[] = $filename;
    }
    return $applied;
}
