<?php

declare(strict_types=1);

require_once __DIR__ . '/support.php';

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
    'customer_type' => 'vip',
    'messages_opt_in' => 0,
    'created_at' => $now,
]);
$paymentA = db_insert('fashion_payments', [
    'order_id' => uuid(),
    'amount_kobo' => 250000,
    'method' => 'cash',
    'reverses_payment_id' => null,
    'kind' => 'payment',
    'user_id' => $userA,
    'created_at' => $now,
]);

auth_login($userB, $tenantB);
db_insert('fashion_tenants', [
    'id' => $tenantB,
    'name' => 'Shop B',
    'phone' => '08032222222',
    'created_at' => $now,
]);

check_eq(db_select('fashion_customers'), [], 'shop B cannot list shop A customers');
check_eq(db_select('fashion_payments'), [], 'shop B cannot list shop A payments');
check_eq(db_update('fashion_customers', ['name' => 'Hacked'], ['id' => $customerA]), 0, 'shop B cannot update shop A');
check_eq(db_delete('fashion_customers', ['id' => $customerA]), 0, 'shop B cannot delete shop A');
check_eq(
    db_query('SELECT id, name FROM fashion_customers WHERE tenant_id = :tenant_id AND id = :id', ['id' => $customerA]),
    [],
    'shop B query cannot see shop A by id'
);

$forged = db_insert('fashion_customers', [
    'tenant_id' => $tenantA,
    'name' => 'Forged',
    'phone' => '08033334444',
    'gender' => 'male',
    'customer_type' => 'regular',
    'messages_opt_in' => 0,
    'created_at' => $now,
]);
$stored = db()->prepare('SELECT tenant_id FROM fashion_customers WHERE id = :id');
$stored->execute(['id' => $forged]);
check_eq($stored->fetchColumn(), $tenantB, 'insert ignores a caller-supplied tenant_id');

check_throws(
    fn () => db_query('SELECT * FROM fashion_customers', []),
    RuntimeException::class,
    'Query must filter tenant_id = :tenant_id.'
);
check_throws(
    fn () => db_query('SELECT * FROM fashion_customers WHERE tenant_id = :tenant_id -- leak', []),
    RuntimeException::class,
    'SQL comments are not allowed.'
);
check_throws(
    fn () => db_query('SELECT * FROM fashion_customers WHERE tenant_id = :tenant_id; DELETE FROM fashion_customers', []),
    RuntimeException::class,
    'Semicolons are not allowed.'
);
check_throws(
    fn () => db_query('SELECT * FROM fashion_customers WHERE tenant_id = :tenant_id OR id = :id', ['id' => $customerA]),
    RuntimeException::class,
    'Top-level OR is not allowed.'
);
check_throws(
    fn () => db_query('SELECT * FROM fashion_customers WHERE tenant_id = :tenant_id', ['tenant_id' => $tenantA]),
    RuntimeException::class,
    'Caller cannot supply tenant_id.'
);
check_throws(
    fn () => db_query('SELECT * FROM fashion_customers WHERE tenant_id = :tenant_id UNION SELECT * FROM fashion_customers WHERE tenant_id = :tenant_id', []),
    RuntimeException::class,
    'This query is not allowed.'
);
check_throws(
    fn () => db_update('fashion_payments', ['amount_kobo' => 1], ['id' => $paymentA]),
    RuntimeException::class,
    'This table is append-only.'
);
check_throws(
    fn () => db_delete('fashion_stage_events', ['id' => uuid()]),
    RuntimeException::class,
    'This table is append-only.'
);
check_throws(
    fn () => db_update('fashion_measurements', ['unit' => 'cm'], ['id' => uuid()]),
    RuntimeException::class,
    'This table is append-only.'
);
check_throws(
    fn () => db_bind_float_probe(),
    RuntimeException::class,
    'Floats are not allowed. Store money as whole kobo.'
);

$orInside = db_query(
    'SELECT id FROM fashion_customers WHERE tenant_id = :tenant_id AND (name = :name OR phone = :phone)',
    ['name' => 'Forged', 'phone' => '08030000000']
);
check(count($orInside) === 1, 'OR inside parentheses is allowed');

$literal = db_query(
    "SELECT id FROM fashion_customers WHERE tenant_id = :tenant_id AND name <> 'OR'",
    []
);
check(count($literal) === 1, 'OR inside a string is not treated as a filter bypass');

auth_logout();
check_throws(
    fn () => db_select('fashion_customers'),
    RuntimeException::class,
    'Missing session.'
);
check_throws(
    fn () => db_query('SELECT * FROM fashion_customers WHERE tenant_id = :tenant_id', []),
    RuntimeException::class,
    'Missing session.'
);
check_throws(
    fn () => db_insert('fashion_customers', [
        'name' => 'Nobody',
        'phone' => '08031112222',
        'gender' => 'female',
        'customer_type' => 'regular',
        'messages_opt_in' => 0,
        'created_at' => $now,
    ]),
    RuntimeException::class,
    'Missing session.'
);

auth_login($userA, $tenantA);
$still = db_select('fashion_customers', ['id' => $customerA]);
check(count($still) === 1 && $still[0]['name'] === 'Amina', 'shop A row is unchanged after shop B attacks');
check_eq(db_select('fashion_customers', ['id' => $forged]), [], 'shop A cannot see the row shop B inserted');
check_eq((int) db_select('fashion_payments', ['id' => $paymentA])[0]['amount_kobo'], 250000, 'payment amount stays whole kobo');

function db_bind_float_probe(): void
{
    $statement = db()->prepare('SELECT 1');
    db_bind($statement, ['amount' => 1.5]);
}
