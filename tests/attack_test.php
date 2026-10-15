<?php

declare(strict_types=1);

if (!defined('GMINGO_TESTING')) {
    require __DIR__ . '/bootstrap.php';
}

$configured = defined('GMINGO_DB_NAME') ? (string) GMINGO_DB_NAME : (string) (app_config()['db']['name'] ?? '');
if (!str_ends_with($configured, '_test')) {
    fwrite(STDERR, "Refusing to run attack tests: database name must end with _test.\n");
    exit(1);
}

require_once __DIR__ . '/support.php';

try {
    $connected = db()->query('SELECT DATABASE()')->fetchColumn();
} catch (Throwable $e) {
    fwrite(STDERR, "Refusing to run attack tests: database name must end with _test.\n");
    exit(1);
}
if (!is_string($connected) || !str_ends_with($connected, '_test')) {
    fwrite(STDERR, "Refusing to run attack tests: database name must end with _test.\n");
    exit(1);
}

tests_reset();

$tenantA = uuid();
$userA = uuid();
$tenantB = uuid();
$userB = uuid();
$now = date('Y-m-d H:i:s');

auth_login($userA, $tenantA);
db_insert('fashion_tenants', [
    'id' => $tenantA,
    'name' => 'Shop A',
    'phone' => '08031111111',
    'created_at' => $now,
]);
$customerA = db_insert('fashion_customers', [
    'name' => 'Amina',
    'phone' => '08031234567',
    'gender' => 'female',
    'customer_type' => 'regular',
    'messages_opt_in' => 0,
    'created_at' => $now,
]);

auth_login($userB, $tenantB);
db_insert('fashion_tenants', [
    'id' => $tenantB,
    'name' => 'Shop B',
    'phone' => '08032222222',
    'created_at' => $now,
]);

check_eq(db_select('fashion_customers', ['id' => $customerA]), [], 'tenant B cannot select tenant A');
check_eq(db_update('fashion_customers', ['name' => 'Hacked'], ['id' => $customerA]), 0, 'tenant B cannot update tenant A');
check_eq(db_delete('fashion_customers', ['id' => $customerA]), 0, 'tenant B cannot delete tenant A');
check_eq(
    db_execute(
        'UPDATE fashion_customers SET name = :name WHERE tenant_id = :tenant_id AND id = :id',
        ['name' => 'Hacked', 'id' => $customerA]
    ),
    0,
    'tenant B execute cannot change tenant A'
);

check_throws(
    fn () => db_query('SELECT id FROM fashion_customers', []),
    'no tenant filter throws'
);
check_throws(
    fn () => db_query(
        'SELECT id FROM fashion_customers WHERE tenant_id = :tenant_id OR id = :id',
        ['id' => $customerA]
    ),
    'an OR bypass throws'
);
check_throws(
    fn () => db_query('SELECT id FROM fashion_customers WHERE 1 = 1 /* tenant_id = :tenant_id */', []),
    'a comment-hidden tenant filter throws'
);
check_throws(
    fn () => db_query('SELECT id FROM fashion_customers WHERE tenant_id = :tenant_id', ['tenant_id' => $tenantA]),
    'passing tenant_id throws'
);
check_throws(
    fn () => db_execute('DELETE FROM fashion_customers WHERE id = :id', ['id' => $customerA]),
    'execute without a tenant filter throws'
);
check_throws(
    fn () => platform_query_unsafe('SELECT id FROM fashion_users WHERE email = :email', ['email' => 'a@b.co']),
    'the platform query is only for sign-up and login'
);

auth_logout();
check_throws(
    fn () => db_select('fashion_customers'),
    'no session throws'
);
check_throws(
    fn () => db_query('SELECT id FROM fashion_customers WHERE tenant_id = :tenant_id', []),
    'no session throws for a filtered query'
);
check_throws(
    fn () => db_update('fashion_customers', ['name' => 'Hacked'], ['id' => $customerA]),
    'no session throws for update'
);
check_throws(
    fn () => db_delete('fashion_customers', ['id' => $customerA]),
    'no session throws for delete'
);

auth_login($userA, $tenantA);
$still = db_select('fashion_customers', ['id' => $customerA]);
check(count($still) === 1 && $still[0]['name'] === 'Amina', 'tenant A row is unchanged');
