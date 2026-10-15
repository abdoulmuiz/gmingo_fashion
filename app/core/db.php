<?php

declare(strict_types=1);

function db_settings(): array
{
    $config = app_config();
    $db = $config['db'] ?? null;
    if (defined('GMINGO_TESTING') && isset($config['test_db']) && is_array($config['test_db'])) {
        $db = $config['test_db'];
    }
    if (!is_array($db)) {
        throw new RuntimeException('Database settings are missing from config/config.php.');
    }
    foreach (['host', 'port', 'name', 'user'] as $key) {
        if (!array_key_exists($key, $db)) {
            throw new RuntimeException('Database ' . $key . ' must come from config/config.php.');
        }
    }
    $password = $db['password'] ?? $db['pass'] ?? null;
    if (!is_string($password)) {
        throw new RuntimeException('Database password must come from config/config.php.');
    }
    $name = (string) $db['name'];
    if (defined('GMINGO_DB_NAME')) {
        $name = (string) GMINGO_DB_NAME;
    }
    if (!preg_match('/^[A-Za-z0-9_]+$/', $name)) {
        throw new RuntimeException('Database name is not safe.');
    }
    if (defined('GMINGO_TESTING') && !str_ends_with($name, '_test')) {
        throw new RuntimeException('Refusing to run tests: database name must end with _test.');
    }
    return [
        'host' => (string) $db['host'],
        'port' => (int) $db['port'],
        'name' => $name,
        'user' => (string) $db['user'],
        'pass' => $password,
        'socket' => (string) ($db['socket'] ?? ''),
    ];
}

function db_pdo_options(): array
{
    return [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];
}

function db_dsn(bool $withDatabase): string
{
    $settings = db_settings();
    $dsn = 'mysql:host=' . $settings['host'] . ';port=' . $settings['port'] . ';charset=utf8mb4';
    if ($withDatabase) {
        $dsn .= ';dbname=' . $settings['name'];
    }
    if ($settings['socket'] !== '') {
        $dsn .= ';unix_socket=' . $settings['socket'];
    }
    return $dsn;
}

function db_server(): PDO
{
    $settings = db_settings();
    return new PDO(db_dsn(false), $settings['user'], $settings['pass'], db_pdo_options());
}

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }
    $settings = db_settings();
    $pdo = new PDO(db_dsn(true), $settings['user'], $settings['pass'], db_pdo_options());
    $current = $pdo->query('SELECT DATABASE()')->fetchColumn();
    if (defined('GMINGO_TESTING') && (!is_string($current) || !str_ends_with($current, '_test'))) {
        $pdo = null;
        throw new RuntimeException('Refusing to run tests: connected database does not end with _test.');
    }
    return $pdo;
}

function db_begin(): void
{
    if (!db()->inTransaction()) {
        db()->beginTransaction();
    }
}

function db_commit(): void
{
    if (db()->inTransaction()) {
        db()->commit();
    }
}

function db_rollback(): void
{
    if (db()->inTransaction()) {
        db()->rollBack();
    }
}

function db_table(string $table): string
{
    if (!preg_match('/^fashion_[a-z0-9_]+$/', $table)) {
        throw new RuntimeException('Invalid table.');
    }
    return $table;
}

function db_column(string $column): string
{
    if ($column === 'tenant_id' || !preg_match('/^[a-z_][a-z0-9_]*$/', $column)) {
        throw new RuntimeException('Invalid column.');
    }
    return $column;
}

function db_append_only(string $table): bool
{
    return in_array($table, [
        'fashion_payments',
        'fashion_stage_events',
        'fashion_measurements',
        'fashion_stock_movements',
    ], true);
}

function db_tenant(): string
{
    $tenantId = current_tenant_id();
    if ($tenantId === null) {
        throw new RuntimeException('Missing session.');
    }
    return $tenantId;
}

function db_bind(PDOStatement $statement, array $params): void
{
    foreach ($params as $key => $value) {
        $name = str_starts_with((string) $key, ':') ? (string) $key : ':' . $key;
        if (is_float($value)) {
            throw new RuntimeException('Floats are not allowed. Store money as whole kobo.');
        }
        if ($value === null) {
            $statement->bindValue($name, null, PDO::PARAM_NULL);
        } elseif (is_int($value)) {
            $statement->bindValue($name, $value, PDO::PARAM_INT);
        } elseif (is_string($value)) {
            $statement->bindValue($name, $value, PDO::PARAM_STR);
        } else {
            throw new RuntimeException('Unsupported value type.');
        }
    }
}

function db_strip_literals(string $sql): string
{
    $out = '';
    $length = strlen($sql);
    for ($i = 0; $i < $length; $i++) {
        $char = $sql[$i];
        if ($char === "'" || $char === '"') {
            $quote = $char;
            $i++;
            while ($i < $length) {
                if ($sql[$i] === '\\') {
                    $i += 2;
                    continue;
                }
                if ($sql[$i] === $quote) {
                    if ($quote === "'" && ($sql[$i + 1] ?? '') === "'") {
                        $i += 2;
                        continue;
                    }
                    break;
                }
                $i++;
            }
            $out .= "''";
            continue;
        }
        $out .= $char;
    }
    return $out;
}

function db_has_top_level_or(string $sql): bool
{
    $depth = 0;
    $length = strlen($sql);
    for ($i = 0; $i < $length; $i++) {
        $char = $sql[$i];
        if ($char === '(') {
            $depth++;
            continue;
        }
        if ($char === ')') {
            $depth = max(0, $depth - 1);
            continue;
        }
        if ($depth !== 0) {
            continue;
        }
        if (!preg_match('/^or\b/i', substr($sql, $i))) {
            continue;
        }
        $previous = $i > 0 ? $sql[$i - 1] : ' ';
        if (!ctype_alnum($previous) && $previous !== '_') {
            return true;
        }
    }
    return false;
}

function db_guard_select(string $sql, array $params): string
{
    db_tenant();
    if (str_contains($sql, ';')) {
        throw new RuntimeException('Semicolons are not allowed.');
    }
    if (preg_match('/--|\/\*|\*\/|#/', $sql)) {
        throw new RuntimeException('SQL comments are not allowed.');
    }
    if (!preg_match('/^\s*select\b/i', $sql)) {
        throw new RuntimeException('db_query only runs SELECT.');
    }
    if (preg_match('/\b(union|into|outfile|dumpfile|sleep|benchmark|information_schema)\b/i', $sql)) {
        throw new RuntimeException('This query is not allowed.');
    }
    foreach ($params as $key => $_value) {
        if (preg_match('/tenant_id/i', (string) $key)) {
            throw new RuntimeException('Caller cannot supply tenant_id.');
        }
    }
    $bare = db_strip_literals($sql);
    if (!preg_match('/\btenant_id\s*=\s*:tenant_id\b/i', $bare)) {
        throw new RuntimeException('Query must filter tenant_id = :tenant_id.');
    }
    if (db_has_top_level_or($bare)) {
        throw new RuntimeException('Top-level OR is not allowed.');
    }
    if (preg_match_all('/\b(?:from|join)\s+`?([a-z0-9_]+)/i', $bare, $matches)) {
        foreach ($matches[1] as $table) {
            if (!str_starts_with(strtolower($table), 'fashion_')) {
                throw new RuntimeException('Only fashion tables can be queried.');
            }
        }
    }
    return $bare;
}

function db_apply_tenant(string $sql): array
{
    $tenantId = db_tenant();
    $bound = [];
    $out = '';
    $count = 0;
    $length = strlen($sql);
    $quote = null;
    for ($i = 0; $i < $length; $i++) {
        $char = $sql[$i];
        if ($quote === null && ($char === "'" || $char === '"')) {
            $quote = $char;
            $out .= $char;
            continue;
        }
        if ($quote !== null) {
            $out .= $char;
            if ($char === '\\' && $i + 1 < $length) {
                $i++;
                $out .= $sql[$i];
                continue;
            }
            if ($char === $quote) {
                $quote = null;
            }
            continue;
        }
        $marker = ':tenant_id';
        $markerLength = strlen($marker);
        if (substr($sql, $i, $markerLength) === $marker) {
            $next = $sql[$i + $markerLength] ?? '';
            if (!preg_match('/^[A-Za-z0-9_]/', $next)) {
                $count++;
                $name = 'tenant_id_' . $count;
                $bound[$name] = $tenantId;
                $out .= ':' . $name;
                $i += $markerLength - 1;
                continue;
            }
        }
        $out .= $char;
    }
    if ($count < 1) {
        throw new RuntimeException('Query must filter tenant_id = :tenant_id.');
    }
    return [$out, $bound];
}

function db_query(string $sql, array $params = []): array
{
    db_guard_select($sql, $params);
    [$sql, $tenantParams] = db_apply_tenant($sql);
    $statement = db()->prepare($sql);
    db_bind($statement, $tenantParams + $params);
    $statement->execute();
    return $statement->fetchAll();
}

function db_guard_write(string $sql, array $params): void
{
    db_tenant();
    if (str_contains($sql, ';')) {
        throw new RuntimeException('Semicolons are not allowed.');
    }
    if (preg_match('/--|\/\*|\*\/|#/', $sql)) {
        throw new RuntimeException('SQL comments are not allowed.');
    }
    if (!preg_match('/^\s*(insert|update|delete)\b/i', $sql)) {
        throw new RuntimeException('db_execute only runs INSERT, UPDATE, or DELETE.');
    }
    if (preg_match('/\b(union|outfile|dumpfile|sleep|benchmark|information_schema)\b/i', $sql)) {
        throw new RuntimeException('This query is not allowed.');
    }
    foreach ($params as $key => $_value) {
        if (preg_match('/tenant_id/i', (string) $key)) {
            throw new RuntimeException('Caller cannot supply tenant_id.');
        }
    }
    $bare = db_strip_literals($sql);
    if (!preg_match('/\btenant_id\s*=\s*:tenant_id\b/i', $bare)) {
        throw new RuntimeException('Query must filter tenant_id = :tenant_id.');
    }
    if (db_has_top_level_or($bare)) {
        throw new RuntimeException('Top-level OR is not allowed.');
    }
    if (preg_match_all('/\b(?:from|join|update|into)\s+`?([a-z0-9_]+)/i', $bare, $matches)) {
        foreach ($matches[1] as $table) {
            if (!str_starts_with(strtolower($table), 'fashion_')) {
                throw new RuntimeException('Only fashion tables can be queried.');
            }
        }
    }
    if (preg_match('/^\s*(update|delete)\b/i', $bare)
        && preg_match('/^\s*(?:update|delete\s+from)\s+`?([a-z0-9_]+)/i', $bare, $tableMatch)
        && db_append_only(strtolower($tableMatch[1]))) {
        throw new RuntimeException('This table is append-only.');
    }
}

function db_execute(string $sql, array $params = []): int
{
    db_guard_write($sql, $params);
    [$sql, $tenantParams] = db_apply_tenant($sql);
    $statement = db()->prepare($sql);
    db_bind($statement, $tenantParams + $params);
    $statement->execute();
    return $statement->rowCount();
}

function platform_query_unsafe(string $sql, array $params = []): array
{
    $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3);
    $callFile = str_replace('\\', '/', (string) ($trace[0]['file'] ?? ''));
    $lookupFunction = (string) ($trace[1]['function'] ?? '');
    $lookupFile = str_replace('\\', '/', (string) ($trace[1]['file'] ?? ''));
    $ownerFunction = (string) ($trace[2]['function'] ?? '');
    $fromSignupOrLogin = str_ends_with($callFile, '/app/domain/accounts.php')
        && $lookupFunction === 'auth_find_user_by_email'
        && str_ends_with($lookupFile, '/app/domain/accounts.php')
        && in_array($ownerFunction, ['business_register', 'login_user'], true);
    if (!$fromSignupOrLogin) {
        throw new RuntimeException('This query is only for sign-up and login.');
    }
    if (str_contains($sql, ';') || preg_match('/--|\/\*|\*\/|#/', $sql)) {
        throw new RuntimeException('This query is not allowed.');
    }
    $statement = db()->prepare($sql);
    db_bind($statement, $params);
    $statement->execute();
    return $statement->fetchAll();
}

function db_order(string $order): string
{
    $parts = preg_split('/\s*,\s*/', trim($order)) ?: [];
    $safe = [];
    foreach ($parts as $part) {
        if (!preg_match('/^([a-z_][a-z0-9_]*)(?:\s+(asc|desc))?$/i', $part, $matches)) {
            throw new RuntimeException('Invalid order.');
        }
        $safe[] = strtolower($matches[1]) . (isset($matches[2]) ? ' ' . strtoupper($matches[2]) : '');
    }
    if ($safe === []) {
        throw new RuntimeException('Invalid order.');
    }
    return implode(', ', $safe);
}

function db_select(string $table, array $where = [], array $options = []): array
{
    $table = db_table($table);
    $sql = 'SELECT * FROM ' . $table . ' WHERE tenant_id = :tenant_id';
    $params = [];
    foreach ($where as $column => $value) {
        $column = db_column((string) $column);
        if ($value === null) {
            $sql .= ' AND ' . $column . ' IS NULL';
            continue;
        }
        $sql .= ' AND ' . $column . ' = :w_' . $column;
        $params['w_' . $column] = $value;
    }
    if (isset($options['order'])) {
        $sql .= ' ORDER BY ' . db_order((string) $options['order']);
    }
    if (isset($options['limit'])) {
        $limit = (int) $options['limit'];
        if ($limit < 1 || $limit > 500) {
            throw new RuntimeException('Invalid limit.');
        }
        $sql .= ' LIMIT ' . $limit;
    }
    return db_query($sql, $params);
}

function db_insert(string $table, array $data): string
{
    $table = db_table($table);
    $tenantId = db_tenant();
    unset($data['tenant_id']);
    $id = $data['id'] ?? '';
    if (!is_string($id) || !is_uuid($id)) {
        $id = uuid();
    }
    unset($data['id']);
    $clean = [
        'id' => $id,
        'tenant_id' => $tenantId,
    ];
    foreach ($data as $column => $value) {
        $clean[db_column((string) $column)] = $value;
    }
    $columns = array_keys($clean);
    $marks = array_map(static fn (string $column): string => ':' . $column, $columns);
    $sql = 'INSERT INTO ' . $table . ' (' . implode(', ', $columns) . ') VALUES (' . implode(', ', $marks) . ')';
    $statement = db()->prepare($sql);
    db_bind($statement, $clean);
    $statement->execute();
    return $id;
}

function db_where(array $where, array &$params, string $prefix): string
{
    if ($where === []) {
        throw new RuntimeException('A where clause is required.');
    }
    $sql = '';
    foreach ($where as $column => $value) {
        $column = db_column((string) $column);
        if ($value === null) {
            $sql .= ' AND ' . $column . ' IS NULL';
            continue;
        }
        $name = $prefix . $column;
        $sql .= ' AND ' . $column . ' = :' . $name;
        $params[$name] = $value;
    }
    return $sql;
}

function db_update(string $table, array $data, array $where): int
{
    $table = db_table($table);
    if (db_append_only($table)) {
        throw new RuntimeException('This table is append-only.');
    }
    $tenantId = db_tenant();
    unset($data['tenant_id'], $data['id']);
    if ($data === []) {
        throw new RuntimeException('Update needs data.');
    }
    $params = ['tenant_id' => $tenantId];
    $sets = [];
    foreach ($data as $column => $value) {
        $column = db_column((string) $column);
        $sets[] = $column . ' = :s_' . $column;
        $params['s_' . $column] = $value;
    }
    $sql = 'UPDATE ' . $table . ' SET ' . implode(', ', $sets) . ' WHERE tenant_id = :tenant_id';
    $sql .= db_where($where, $params, 'wh_');
    $statement = db()->prepare($sql);
    db_bind($statement, $params);
    $statement->execute();
    return $statement->rowCount();
}

function db_delete(string $table, array $where): int
{
    $table = db_table($table);
    if (db_append_only($table)) {
        throw new RuntimeException('This table is append-only.');
    }
    $tenantId = db_tenant();
    $params = ['tenant_id' => $tenantId];
    $sql = 'DELETE FROM ' . $table . ' WHERE tenant_id = :tenant_id';
    $sql .= db_where($where, $params, 'wh_');
    $statement = db()->prepare($sql);
    db_bind($statement, $params);
    $statement->execute();
    return $statement->rowCount();
}
