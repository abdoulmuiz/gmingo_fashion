<?php

declare(strict_types=1);

require_once __DIR__ . '/support.php';
require_once dirname(__DIR__) . '/app/domain/accounts.php';
require_once dirname(__DIR__) . '/app/domain/customers.php';
require_once dirname(__DIR__) . '/app/domain/orders.php';
require_once dirname(__DIR__) . '/app/domain/groups.php';
require_once dirname(__DIR__) . '/app/domain/payments.php';
require_once dirname(__DIR__) . '/app/fashion/controllers/groups.php';
require_once dirname(__DIR__) . '/app/fashion/controllers/orders.php';

tests_reset();

function groups_test_customer(string $name, string $phone): string
{
    $created = customer_create([
        'name' => $name,
        'phone' => $phone,
        'gender' => 'female',
        'customer_type' => 'regular',
    ]);
    return (string) $created['id'];
}

function groups_test_order(string $customerId, string $garmentId, string $price, string $dueDate): array
{
    return order_create([
        'customer_id' => $customerId,
        'due_date' => $dueDate,
        'urgent' => '',
        'items' => [[
            'garment_type_id' => $garmentId,
            'quantity' => '1',
            'price' => $price,
            'fabric' => 'ankara',
            'notes' => '',
        ]],
    ]);
}

$shop = business_register([
    'business_name' => 'Group Shop',
    'owner_name' => 'Amina Bello',
    'email' => 'group-a@example.com',
    'password' => 'password',
    'phone' => '08032100001',
    'company_type' => 'tailor',
]);
$ownerId = (string) $shop['user_id'];
$tenantId = (string) $shop['tenant_id'];
$hostId = groups_test_customer('Party Host', '08032100002');
$olderCustomer = groups_test_customer('Older Guest', '08032100003');
$newerCustomer = groups_test_customer('Newer Guest', '08032100004');
$garment = db_select('fashion_garment_types', ['code' => 'kaftan'])[0];
$garmentId = (string) $garment['id'];
$older = groups_test_order($olderCustomer, $garmentId, '100', '2026-11-01');
$newer = groups_test_order($newerCustomer, $garmentId, '250', '2026-11-02');
db_update('fashion_orders', ['created_at' => '2026-10-01 09:00:00'], ['id' => $older['id']]);
db_update('fashion_orders', ['created_at' => '2026-10-02 09:00:00'], ['id' => $newer['id']]);
$created = group_create([
    'name' => 'Wedding party',
    'event_date' => '2026-12-01',
    'pay_mode' => 'one_pays',
    'payer_customer_id' => $hostId,
]);
check(isset($created['id']), 'a group is created');
$groupId = (string) $created['id'];
$storedGroup = db_select('fashion_order_groups', ['id' => $groupId])[0];
check_eq((string) $storedGroup['pay_mode'], 'one_pays', 'payer mode is one_pays');
check_eq((string) $storedGroup['payer_customer_id'], $hostId, 'one person paying is the paying customer');
check_eq((string) $storedGroup['event_date'], '2026-12-01', 'the group stores the event date');
check(group_add_order($older['id'], $groupId) === [], 'the older order joins the group');
check(group_add_order($newer['id'], $groupId) === [], 'the newer order joins the group');
$second = group_create([
    'name' => 'Second group',
    'event_date' => '',
    'pay_mode' => 'each_pays',
    'payer_customer_id' => '',
]);
check_eq(group_add_order($older['id'], (string) $second['id'])['error'], 'order_already_in_group', 'an order belongs to one group');
check_eq((string) db_select('fashion_orders', ['id' => $older['id']])[0]['group_id'], $groupId, 'the order stays in the first group');

$GLOBALS['gmingo_fail_group_pay'] = true;
try {
    group_pay($groupId, '150', 'cash');
    check(false, 'a group payment failure is raised');
} catch (RuntimeException $e) {
    check_eq($e->getMessage(), 'Simulated group payment failure.', 'a group payment failure stops the split');
}
unset($GLOBALS['gmingo_fail_group_pay']);
check_eq(count(db_select('fashion_payments', ['group_id' => $groupId], ['limit' => 10])), 0, 'a failure midway leaves no partial payments');

check_eq(group_pay($groupId, '350.01', 'cash')['error'], 'group_amount_above', 'an amount above the group balance is refused');
check_eq(count(db_select('fashion_payments', ['group_id' => $groupId], ['limit' => 10])), 0, 'a refused group payment stores nothing');

$paid = group_pay($groupId, '150', 'cash');
check_eq($paid['amount_kobo'], 15000, '150 naira is 15000 kobo');
check_eq($paid['rows'], 2, 'the split writes two payment rows');
$splits = db_query(
    'SELECT order_id, amount_kobo, group_payment_id, payer_customer_id
     FROM fashion_payments
     WHERE tenant_id = :tenant_id AND group_id = :group_id AND kind = :kind
     ORDER BY seq ASC',
    ['group_id' => $groupId, 'kind' => 'payment']
);
check_eq(count($splits), 2, 'two split rows are stored');
check_eq((string) $splits[0]['order_id'], $older['id'], 'the split starts with the oldest order');
check_eq((int) $splits[0]['amount_kobo'], 10000, 'the oldest order is paid up to its balance');
check_eq((string) $splits[1]['order_id'], $newer['id'], 'the remainder goes to the next order');
check_eq((int) $splits[1]['amount_kobo'], 5000, 'the newer order receives the remainder');
check((int) $splits[1]['amount_kobo'] <= 25000, 'the newer order is not paid above its balance');
check_eq((int) $splits[0]['amount_kobo'] + (int) $splits[1]['amount_kobo'], 15000, 'the sum of the split rows equals the amount entered');
check_eq((string) $splits[0]['group_payment_id'], (string) $splits[1]['group_payment_id'], 'the split rows share one group payment id');
check_eq((string) $splits[0]['payer_customer_id'], $hostId, 'the paying customer is saved on the first split');
check_eq((string) $splits[1]['payer_customer_id'], $hostId, 'the paying customer is saved on the second split');
$money = group_money($groupId);
check_eq($money['total_kobo'], 35000, 'group total equals the sum of orders');
check_eq($money['paid_kobo'], 15000, 'group paid equals the split');
check_eq($money['balance_kobo'], 20000, 'group balance is total minus paid');
$_SERVER['REQUEST_URI'] = base_path() . '/groups/' . $groupId;
ob_start();
groups_show($groupId);
$groupPage = (string) ob_get_clean();
check(str_contains($groupPage, money(35000)), 'the group page shows the group total');
check(str_contains($groupPage, money(10000)), 'the group page shows the older order total');
check(str_contains($groupPage, money(25000)), 'the group page shows the newer order total');
check(str_contains($groupPage, '1 Dec 2026'), 'the group page shows the event date');
check(str_contains($groupPage, 'Pay for the group'), 'one person paying can pay for the group');

$firstPaymentId = (string) db_select('fashion_payments', ['order_id' => $older['id'], 'kind' => 'payment'], ['limit' => 1])[0]['id'];
$GLOBALS['gmingo_fail_group_reverse'] = true;
try {
    payment_reverse($older['id'], $firstPaymentId, 'Party cancelled');
    check(false, 'a group reversal failure is raised');
} catch (RuntimeException $e) {
    check_eq($e->getMessage(), 'Simulated group reversal failure.', 'a group reversal failure stops the reversal');
}
unset($GLOBALS['gmingo_fail_group_reverse']);
check_eq(count(db_select('fashion_payments', ['kind' => 'reversal', 'group_id' => $groupId], ['limit' => 10])), 0, 'a reversal failure leaves no reversing rows');

db_update('fashion_users', ['role' => 'staff'], ['id' => $ownerId]);
try {
    payment_reverse($older['id'], $firstPaymentId, 'Party cancelled');
    check(false, 'staff reversing a group payment is refused');
} catch (HttpException $e) {
    check_eq($e->getMessage(), 'Only the owner can reverse a group payment.', 'only the owner can reverse a group payment');
    check_eq($e->status(), 403, 'a staff group reversal returns 403');
}
check_eq(count(db_select('fashion_payments', ['kind' => 'reversal', 'group_id' => $groupId], ['limit' => 10])), 0, 'staff do not store a group reversal');
db_update('fashion_users', ['role' => 'owner'], ['id' => $ownerId]);
$reversed = payment_reverse($older['id'], $firstPaymentId, 'Party cancelled');
check_eq($reversed['amount_kobo'], -15000, 'reversing one group payment reverses the whole amount');
$originals = db_query(
    'SELECT id, order_id, amount_kobo, kind FROM fashion_payments
     WHERE tenant_id = :tenant_id AND group_payment_id = :group_payment_id AND kind = :kind
     ORDER BY seq ASC',
    ['group_payment_id' => (string) $splits[0]['group_payment_id'], 'kind' => 'payment']
);
check_eq((int) $originals[0]['amount_kobo'], 10000, 'the first split row stays 10000');
check_eq((int) $originals[1]['amount_kobo'], 5000, 'the second split row stays 5000');
$reversals = db_query(
    'SELECT order_id, amount_kobo, reverses_payment_id, reason, kind
     FROM fashion_payments
     WHERE tenant_id = :tenant_id AND group_payment_id = :group_payment_id AND kind = :kind
     ORDER BY seq ASC',
    ['group_payment_id' => (string) $splits[0]['group_payment_id'], 'kind' => 'reversal']
);
check_eq(count($reversals), 2, 'reversing one group payment reverses all its rows');
check_eq((int) $reversals[0]['amount_kobo'], -10000, 'the first reversing row is minus 10000');
check_eq((int) $reversals[1]['amount_kobo'], -5000, 'the second reversing row is minus 5000');
check_eq((int) $reversals[0]['amount_kobo'] + (int) $reversals[1]['amount_kobo'], -15000, 'the reversing rows add up to the original amount');
check_eq((string) $reversals[0]['reverses_payment_id'], (string) $originals[0]['id'], 'the first reversing row points at the first split');
check_eq((string) $reversals[1]['reverses_payment_id'], (string) $originals[1]['id'], 'the second reversing row points at the second split');
check_eq((string) $reversals[0]['reason'], 'Party cancelled', 'the first reversing row stores the reason');
check_eq((string) $reversals[1]['reason'], 'Party cancelled', 'the second reversing row stores the reason');
check_throws(
    static fn () => db_update('fashion_payments', ['amount_kobo' => 1], ['id' => (string) $originals[0]['id']]),
    RuntimeException::class,
    'This table is append-only.'
);
check_eq(payment_reverse($older['id'], $firstPaymentId, 'Party cancelled')['error'], 'already_reversed', 'a group payment is reversed once');

check_eq(group_update($groupId, [
    'name' => 'Wedding party',
    'event_date' => '2026-12-01',
    'pay_mode' => 'each_pays',
    'payer_customer_id' => '',
])['error'], 'group_mode_locked', 'the payer mode cannot change after payments exist');
$_POST = [
    'csrf' => csrf_token(),
    'name' => 'Wedding party',
    'event_date' => '2026-12-01',
    'pay_mode' => 'each_pays',
    'payer_customer_id' => '',
];
try {
    router_dispatch('POST', '/groups/' . $groupId);
    check(false, 'changing payer mode redirects');
} catch (RedirectException $e) {
    check_eq($e->path(), '/groups/' . $groupId, 'a locked payer mode returns to the group');
}
check_eq(flash_get('error'), 'Payer mode cannot change after payments exist.', 'changing payer mode shows the locked message');
check_eq((string) db_select('fashion_order_groups', ['id' => $groupId])[0]['pay_mode'], 'one_pays', 'the payer mode stays one_pays');

$orderCount = count(db_select('fashion_orders', ['group_id' => $groupId], ['limit' => 10]));
check(group_remove_order($newer['id']) === [], 'an order can leave the group');
check_eq(count(db_select('fashion_orders', ['id' => $newer['id']])), 1, 'removing from the group keeps the order');
check(db_select('fashion_orders', ['id' => $newer['id']])[0]['group_id'] === null, 'the order no longer points at the group');
check_eq(count(db_select('fashion_orders', ['group_id' => $groupId], ['limit' => 10])), $orderCount - 1, 'the group loses only that member');

$ownPays = group_create([
    'name' => 'Aso ebi',
    'event_date' => '',
    'pay_mode' => 'each_pays',
    'payer_customer_id' => '',
]);
check(group_add_order($newer['id'], (string) $ownPays['id']) === [], 'the removed order can join an each-pays group');
$ownPayment = payment_record($newer['id'], '10', 'cash');
check(isset($ownPayment['id']), 'each person pays on their own order');
check(db_select('fashion_payments', ['id' => $ownPayment['id']])[0]['group_payment_id'] === null, 'an individual payment has no group payment id');
check_eq(group_pay((string) $ownPays['id'], '10', 'cash')['error'], 'group_each_pays_note', 'each person pays is not a group split');

$crowd = group_create([
    'name' => 'Uniform',
    'event_date' => '',
    'pay_mode' => 'each_pays',
    'payer_customer_id' => '',
]);
$crowdSum = 0;
for ($i = 1; $i <= 26; $i++) {
    $member = groups_test_order($olderCustomer, $garmentId, '100', '2026-11-20');
    check(group_add_order($member['id'], (string) $crowd['id']) === [], 'uniform member ' . (string) $i . ' joins');
    $crowdSum += 10000;
}
$crowdPage = group_page((string) $crowd['id'], 1);
$crowdNext = group_page((string) $crowd['id'], 2);
check_eq($crowdPage['total_kobo'], $crowdSum, 'the group total counts every order');
check_eq(count($crowdPage['members']), 25, 'a long group shows the first page of members');
check_eq(count($crowdNext['members']), 1, 'the next page shows the remaining member');
check_eq($crowdNext['total_kobo'], $crowdSum, 'the next page still shows the full group total');

$other = business_register([
    'business_name' => 'Other Group Shop',
    'owner_name' => 'Bala',
    'email' => 'group-b@example.com',
    'password' => 'password',
    'phone' => '08032100005',
    'company_type' => 'tailor',
]);
$otherCustomer = groups_test_customer('Other Guest', '08032100006');
$otherGarment = db_select('fashion_garment_types', ['code' => 'kaftan'])[0];
$otherOrder = groups_test_order($otherCustomer, (string) $otherGarment['id'], '80', '2026-11-03');
try {
    router_dispatch('GET', '/groups/' . $groupId);
    check(false, 'the other shop group page is refused');
} catch (HttpException $e) {
    check_eq($e->getMessage(), 'Page not found', 'the group address refuses another business group id');
    check_eq($e->status(), 404, 'the group address returns 404 for another business group id');
}
auth_login($ownerId, $tenantId);
$_POST = [
    'csrf' => csrf_token(),
    'action' => 'add',
    'group_id' => $groupId,
];
try {
    router_dispatch('POST', '/orders/' . $otherOrder['id'] . '/group');
    check(false, 'another business order can be added');
} catch (HttpException $e) {
    check_eq($e->getMessage(), 'Page not found', 'another business order cannot be added to this group');
    check_eq($e->status(), 404, 'adding another business order returns 404');
}
check_eq(count(db_select('fashion_orders', ['group_id' => $groupId], ['limit' => 10])), 1, 'the other order was not added');
