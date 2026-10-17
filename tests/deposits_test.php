<?php

declare(strict_types=1);

require_once __DIR__ . '/support.php';
require_once dirname(__DIR__) . '/app/domain/accounts.php';
require_once dirname(__DIR__) . '/app/domain/customers.php';
require_once dirname(__DIR__) . '/app/domain/orders.php';
require_once dirname(__DIR__) . '/app/domain/payments.php';
require_once dirname(__DIR__) . '/app/domain/deposits.php';
require_once dirname(__DIR__) . '/app/domain/stages.php';
require_once dirname(__DIR__) . '/app/fashion/controllers/settings.php';
require_once dirname(__DIR__) . '/app/fashion/controllers/orders.php';

tests_reset();

function deposits_test_order(string $customerId, string $garmentId, string $price): array
{
    return order_create([
        'customer_id' => $customerId,
        'due_date' => date('Y-m-d', strtotime('+5 days')),
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

check_eq(deposit_due_kobo(100000, 0), 0, 'percent 0 is no deposit');
check_eq(deposit_due_kobo(100000, 30), 30000, '30% of 100,000 kobo is 30,000');
check_eq(deposit_due_kobo(100001, 30), 30001, 'an odd total rounds up to the next kobo');

$shop = business_register([
    'business_name' => 'Deposit Shop',
    'owner_name' => 'Amina Bello',
    'email' => 'deposit-a@example.com',
    'password' => 'password',
    'phone' => '08031000001',
    'company_type' => 'tailor',
]);
$ownerId = (string) $shop['user_id'];
$tenantId = (string) $shop['tenant_id'];
$row = db_select('fashion_tenants', ['id' => $tenantId])[0];
check_eq((int) $row['deposit_percent'], 0, 'a new shop starts at deposit percent 0');
$cutting = db_select('fashion_stage_definitions', ['name' => 'Cutting'], ['limit' => 1])[0];
check_eq((string) $row['deposit_stage_id'], (string) $cutting['id'], 'a new tailor shop defaults the deposit stage to the stage named Cutting');

$factory = business_register([
    'business_name' => 'Deposit Factory',
    'owner_name' => 'Factory Owner',
    'email' => 'deposit-factory@example.com',
    'password' => 'password',
    'phone' => '08031000002',
    'company_type' => 'factory',
]);
$factoryRow = db_select('fashion_tenants', ['id' => (string) $factory['tenant_id']])[0];
check_eq((int) $factoryRow['deposit_percent'], 0, 'a new factory starts at deposit percent 0');
check($factoryRow['deposit_stage_id'] === null, 'a new shop with no stage named Cutting leaves the deposit stage empty');

$other = business_register([
    'business_name' => 'Other Deposit Shop',
    'owner_name' => 'Bala',
    'email' => 'deposit-b@example.com',
    'password' => 'password',
    'phone' => '08031000003',
    'company_type' => 'tailor',
]);
$otherCutting = db_select('fashion_stage_definitions', ['name' => 'Cutting'], ['limit' => 1])[0];
$savedOther = deposit_settings_save('80', (string) $otherCutting['id']);
check($savedOther === [], 'the other shop can save its own deposit');
check_eq(deposit_shop()['percent'], 80, 'the other shop stores 80');
check_eq(deposit_shop()['stage_id'], (string) $otherCutting['id'], 'the other shop stores its own Cutting stage');

auth_login($ownerId, $tenantId);
$ourCutting = db_select('fashion_stage_definitions', ['name' => 'Cutting'], ['limit' => 1])[0];
check_eq(deposit_shop()['percent'], 0, 'another shop percent does not change this shop');
check_eq(deposit_shop()['stage_id'], (string) $ourCutting['id'], 'another shop stage does not change this shop');
check((string) $ourCutting['id'] !== (string) $otherCutting['id'], 'each shop has its own Cutting stage');

$customer = customer_create([
    'name' => 'Amina Bello',
    'phone' => '08031000004',
    'gender' => 'female',
    'customer_type' => 'regular',
]);
$garment = db_select('fashion_garment_types', ['code' => 'kaftan'])[0];
$openOrder = deposits_test_order($customer['id'], $garment['id'], '1000');
$openMove = stage_advance($openOrder['id']);
check_eq($openMove['code'], 'cutting', 'percent 0 never blocks the deposit stage');

$_POST = [
    'csrf' => csrf_token(),
    'action' => 'deposit',
    'deposit_percent' => '30',
    'deposit_stage_id' => (string) $ourCutting['id'],
];
try {
    settings_submit();
    check(false, 'saving the deposit redirects');
} catch (RedirectException $e) {
    check_eq($e->path(), '/settings', 'saving the deposit returns to settings');
}
check_eq(deposit_shop()['percent'], 30, 'the shop stores deposit percent 30');
check_eq(deposit_shop()['stage_id'], (string) $ourCutting['id'], 'the shop stores its own deposit stage');
check_eq(deposit_settings_save('101', (string) $ourCutting['id'])['error'], 'deposit_percent_invalid', '101 is rejected');
check_eq(deposit_settings_save('30.5', (string) $ourCutting['id'])['error'], 'deposit_percent_invalid', 'a fraction is rejected');
check_eq(deposit_shop()['percent'], 30, 'a rejected percent leaves 30 in place');
check_eq(
    deposit_settings_save('40', (string) $otherCutting['id'])['error'],
    'deposit_stage_invalid',
    'another shop stage cannot be chosen'
);
check_eq(deposit_shop()['percent'], 30, 'a rejected stage leaves this shop percent at 30');
check_eq(deposit_shop()['stage_id'], (string) $ourCutting['id'], 'a rejected stage leaves this shop stage in place');

$_SERVER['REQUEST_URI'] = base_path() . '/settings';
ob_start();
settings_form();
$settingsHtml = (string) ob_get_clean();
check(str_contains($settingsHtml, 'value="' . $ourCutting['id'] . '"'), 'settings lists this shop stage');
check(!str_contains($settingsHtml, 'value="' . $otherCutting['id'] . '"'), 'settings does not list the other shop stage');

$sewing = db_select('fashion_stage_definitions', ['name' => 'Sewing'], ['limit' => 1])[0];
check($deposit_settings = deposit_settings_save('30', (string) $sewing['id']) === [], 'the deposit stage can be Sewing');
unset($deposit_settings);
$chosen = deposits_test_order($customer['id'], $garment['id'], '1000');
$toCutting = stage_advance($chosen['id']);
check_eq($toCutting['code'], 'cutting', 'the rule follows the chosen stage and does not hard-code Cutting');
$toSewing = stage_advance($chosen['id']);
check_eq($toSewing['error'], 'deposit_short', 'moving into the chosen stage is refused');
check_eq($toSewing['due_kobo'], 30000, 'the chosen stage still uses 30,000 kobo');
check(stage_latest($chosen['id'])['code'] === 'cutting', 'the refused move does not leave Cutting');
check(deposit_settings_save('30', (string) $ourCutting['id']) === [], 'the deposit stage returns to Cutting');

$short = deposits_test_order($customer['id'], $garment['id'], '1000');
$part = payment_record($short['id'], '299.99', 'cash');
check_eq($part['amount_kobo'], 29999, '299.99 naira is 29,999 kobo');
$shortBlock = stage_advance($short['id']);
check_eq($shortBlock['error'], 'deposit_short', 'one kobo less blocks the move');
check_eq($shortBlock['due_kobo'], 30000, 'the short order deposit due is 30,000 kobo');
check_eq($shortBlock['paid_kobo'], 29999, 'the short order counts 29,999 kobo paid');
check_eq($shortBlock['needed_kobo'], 1, 'the short order still needs 1 kobo');
$_POST = ['csrf' => csrf_token()];
$_SERVER['REQUEST_URI'] = base_path() . '/orders/' . $short['id'] . '/stage';
try {
    router_dispatch('POST', '/orders/' . $short['id'] . '/stage');
    check(false, 'the move endpoint redirects');
} catch (RedirectException $e) {
    check_eq($e->path(), '/orders/' . $short['id'], 'the move endpoint returns to the order');
}
check_eq(
    flash_get('error'),
    'Deposit due is ₦300.00. Paid so far is ₦299.99. Still needed is ₦0.01.',
    'the move endpoint states the deposit due, paid so far, and still needed'
);
check(stage_latest($short['id']) === null, 'calling the move endpoint directly does not change the stage');
$_SERVER['REQUEST_URI'] = base_path() . '/orders/' . $short['id'];
ob_start();
orders_show($short['id']);
$shortHtml = (string) ob_get_clean();
check(str_contains($shortHtml, t('deposit_due')), 'the order page shows deposit due');
check(str_contains($shortHtml, t('deposit_paid')), 'the order page shows deposit paid');
check(str_contains($shortHtml, t('deposit_remaining')), 'the order page shows deposit remaining');
check(str_contains($shortHtml, '₦300.00'), 'the order page shows ₦300.00 due');
check(str_contains($shortHtml, '₦299.99'), 'the order page shows ₦299.99 paid');
check(str_contains($shortHtml, '₦0.01'), 'the order page shows ₦0.01 still needed');
check(str_contains($shortHtml, t('move_next')), 'the move button stays on the page');
$exact = payment_record($short['id'], '0.01', 'cash');
check_eq($exact['amount_kobo'], 1, '0.01 naira is 1 kobo');
$exactMove = stage_advance($short['id']);
check_eq($exactMove['code'], 'cutting', 'paying exactly the deposit allows the move');

$pending = deposits_test_order($customer['id'], $garment['id'], '1000');
db_insert('fashion_payment_requests', [
    'order_id' => $pending['id'],
    'amount_kobo' => 100000,
    'reference' => 'dep-pending-' . substr(uuid(), 0, 12),
    'authorization_url' => 'https://pay.example.test/pending',
    'status' => 'pending',
    'needs_review' => 0,
    'used_placeholder' => 0,
    'created_by' => $ownerId,
    'created_at' => app_now()->format('Y-m-d H:i:s'),
]);
$pendingBlock = stage_advance($pending['id']);
check_eq($pendingBlock['error'], 'deposit_short', 'a pending payment still blocks');
check_eq($pendingBlock['paid_kobo'], 0, 'a pending payment does not count');
check_eq($pendingBlock['due_kobo'], 30000, 'the pending order deposit due stays 30,000');
db_insert('fashion_payments', [
    'order_id' => $pending['id'],
    'amount_kobo' => 30000,
    'method' => 'transfer',
    'reverses_payment_id' => null,
    'kind' => 'payment',
    'user_id' => $ownerId,
    'created_at' => app_now()->format('Y-m-d H:i:s'),
    'provider' => 'paystack',
    'provider_reference' => 'psk-' . substr(uuid(), 0, 12),
    'source' => 'webhook',
]);
$paystackMove = stage_advance($pending['id']);
check_eq($paystackMove['code'], 'cutting', 'a recorded Paystack payment allows the move');

$odd = deposits_test_order($customer['id'], $garment['id'], '1000.01');
check_eq(order_find($odd['id'])['total_kobo'], 100001, '1000.01 naira is 100,001 kobo');
$oddShort = payment_record($odd['id'], '300', 'cash');
check_eq($oddShort['amount_kobo'], 30000, '300 naira is 30,000 kobo');
$oddBlock = stage_advance($odd['id']);
check_eq($oddBlock['due_kobo'], 30001, '100,001 kobo at 30% is 30,001 kobo due');
check_eq($oddBlock['needed_kobo'], 1, '30,000 kobo is still 1 kobo short on the odd total');
check_eq($oddBlock['error'], 'deposit_short', 'the odd total stays blocked until it rounds up');
payment_record($odd['id'], '0.01', 'cash');
check_eq(stage_advance($odd['id'])['code'], 'cutting', 'the rounded-up kobo allows the odd total');

$skip = deposits_test_order($customer['id'], $garment['id'], '1000');
$staffId = db_insert('fashion_users', [
    'name' => 'Shop Staff',
    'email' => 'deposit-staff@example.com',
    'password_hash' => password_hash('password', PASSWORD_DEFAULT),
    'role' => 'staff',
    'lang' => 'en',
    'active' => 1,
    'created_at' => app_now()->format('Y-m-d H:i:s'),
]);
auth_login($staffId, $tenantId);
check_throws(
    static fn () => deposit_override($skip['id'], 'Staff wants this'),
    HttpException::class,
    'That record is not in this shop.'
);
$_POST = ['csrf' => csrf_token(), 'reason' => 'Staff wants this'];
try {
    router_dispatch('POST', '/orders/' . $skip['id'] . '/deposit-override');
    check(false, 'a non-owner override is refused');
} catch (HttpException $e) {
    check_eq($e->getMessage(), 'That record is not in this shop.', 'a non-owner cannot override');
    check_eq($e->status(), 403, 'a non-owner override returns 403');
}
check(db_select('fashion_stage_events', ['order_id' => $skip['id']]) === [], 'a non-owner override writes no stage event');
auth_login($ownerId, $tenantId);
check_eq(deposit_override($skip['id'], '   ')['error'], 'deposit_override_reason_required', 'override needs a reason');
check(db_select('fashion_stage_events', ['order_id' => $skip['id']]) === [], 'a blank reason writes no stage event');
$GLOBALS['gmingo_now'] = '2026-10-16 18:30:00';
$overrode = deposit_override($skip['id'], 'Customer is family');
unset($GLOBALS['gmingo_now']);
check_eq($overrode['code'], 'cutting', 'an owner override moves the order');
$overrideEvents = db_select('fashion_stage_events', ['order_id' => $skip['id'], 'kind' => 'override']);
check_eq(count($overrideEvents), 1, 'the override is one stage event');
check_eq((string) $overrideEvents[0]['note'], 'Customer is family', 'the override records the reason');
check_eq((string) $overrideEvents[0]['user_id'], $ownerId, 'the override records the user');
check_eq((string) $overrideEvents[0]['created_at'], '2026-10-16 18:30:00', 'the override records the time');
check_eq(deposit_override($skip['id'], 'Again')['error'], 'deposit_override_once', 'override is once per order');
check_eq(count(db_select('fashion_stage_events', ['order_id' => $skip['id'], 'kind' => 'override'])), 1, 'a second override is not logged');
$_SERVER['REQUEST_URI'] = base_path() . '/orders/' . $skip['id'];
ob_start();
orders_show($skip['id']);
$skipHtml = (string) ob_get_clean();
check(str_contains($skipHtml, 'Customer is family'), 'the order page shows the override reason');
check(str_contains($skipHtml, '16 Oct 2026 18:30'), 'the order page shows the override time');

$covered = deposits_test_order($customer['id'], $garment['id'], '1000');
$coveredPay = payment_record($covered['id'], '300', 'cash');
check_eq(stage_advance($covered['id'])['code'], 'cutting', 'a paid deposit enters Cutting');
$reversal = payment_reverse($covered['id'], $coveredPay['id'], 'Customer asked');
check_eq($reversal['amount_kobo'], -30000, 'the reversal removes 30,000 kobo');
check_eq(stage_latest($covered['id'])['code'], 'cutting', 'a reversal below the deposit does not move the order back');
check_eq(count(db_select('fashion_stage_events', ['order_id' => $covered['id']])), 1, 'a reversal adds no stage event');
$_SERVER['REQUEST_URI'] = base_path() . '/orders/' . $covered['id'];
ob_start();
orders_show($covered['id']);
$coveredHtml = (string) ob_get_clean();
check(str_contains($coveredHtml, t('deposit_shortfall_warning')), 'the order shows a warning when paid drops below the deposit');
check(str_contains($coveredHtml, '₦300.00'), 'the warning order still shows the ₦300.00 deposit');
check(str_contains($coveredHtml, '₦0.00'), 'the warning order shows ₦0.00 paid');

auth_login((string) $other['user_id'], (string) $other['tenant_id']);
$otherCustomer = customer_create([
    'name' => 'Bala Customer',
    'phone' => '08031000005',
    'gender' => 'male',
    'customer_type' => 'regular',
]);
$otherGarment = db_select('fashion_garment_types', ['code' => 'kaftan'])[0];
$otherOrder = deposits_test_order($otherCustomer['id'], $otherGarment['id'], '1000');
$otherBlock = stage_advance($otherOrder['id']);
check_eq($otherBlock['error'], 'deposit_short', 'the other shop blocks on its own percent');
check_eq($otherBlock['due_kobo'], 80000, 'the other shop deposit due is 80,000 kobo');
try {
    stage_advance($short['id']);
    check(false, 'the other shop cannot move this order');
} catch (HttpException $e) {
    check_eq($e->getMessage(), 'Page not found', 'the other shop cannot move this order');
    check_eq($e->status(), 404, 'the other shop move returns 404');
}
$_POST = ['csrf' => csrf_token()];
try {
    router_dispatch('POST', '/orders/' . $short['id'] . '/stage');
    check(false, 'the other shop move endpoint is refused');
} catch (HttpException $e) {
    check_eq($e->getMessage(), 'Page not found', 'the move endpoint refuses another business order id');
    check_eq($e->status(), 404, 'the move endpoint returns 404 for another business order id');
}
auth_login($ownerId, $tenantId);
$still = deposits_test_order($customer['id'], $garment['id'], '1000');
$stillBlock = stage_advance($still['id']);
check_eq($stillBlock['due_kobo'], 30000, 'another business setting does not affect this one');
check_eq($stillBlock['needed_kobo'], 30000, 'this shop still needs its own 30,000 kobo');
$_POST = ['csrf' => csrf_token()];
try {
    router_dispatch('POST', '/orders/' . $otherOrder['id'] . '/stage');
    check(false, 'this shop move endpoint is refused for the other order');
} catch (HttpException $e) {
    check_eq($e->getMessage(), 'Page not found', 'this shop cannot post the other business order id');
    check_eq($e->status(), 404, 'this shop gets 404 for the other business order id');
}
check_eq(stage_latest($still['id']), null, 'the other shop setting did not move this order');
