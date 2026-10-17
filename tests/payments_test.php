<?php

declare(strict_types=1);

require_once __DIR__ . '/support.php';
require_once dirname(__DIR__) . '/app/domain/accounts.php';
require_once dirname(__DIR__) . '/app/domain/customers.php';
require_once dirname(__DIR__) . '/app/domain/orders.php';
require_once dirname(__DIR__) . '/app/domain/payments.php';
require_once dirname(__DIR__) . '/app/fashion/controllers/orders.php';
require_once dirname(__DIR__) . '/app/fashion/controllers/payments.php';

tests_reset();

function payments_test_order(string $customerId, string $garmentId, string $price): array
{
    return order_create([
        'customer_id' => $customerId,
        'due_date' => date('Y-m-d', strtotime('+4 days')),
        'urgent' => '',
        'items' => [[
            'garment_type_id' => $garmentId,
            'quantity' => '2',
            'price' => $price,
            'fabric' => 'ankara',
            'notes' => '',
        ]],
    ]);
}

$shop = business_register([
    'business_name' => 'Payment Shop',
    'owner_name' => 'Amina Bello',
    'email' => 'payments@example.com',
    'password' => 'password',
    'phone' => '08031234567',
    'company_type' => 'tailor',
]);
check(isset($shop['user_id']), 'payment tests start from a signed-in shop');
$customer = customer_create([
    'name' => 'Hauwa Sani',
    'phone' => '08031112233',
    'gender' => 'female',
    'customer_type' => 'regular',
]);
$garment = db_select('fashion_garment_types', ['code' => 'kaftan'])[0];
$order = payments_test_order($customer['id'], $garment['id'], '10000');
$found = order_find($order['id']);
check_eq($found['total_kobo'], 2000000, 'the order total is calculated from the items');
check_eq($found['paid_kobo'], 0, 'a new order has no payments');
check_eq($found['balance_kobo'], 2000000, 'the balance starts as the total');

$cash = payment_record($order['id'], '5000', 'cash');
check_eq($cash['amount_kobo'], 500000, 'a naira amount is stored as whole kobo');
$part = order_find($order['id']);
check_eq($part['paid_kobo'], 500000, 'amount paid is the sum of payments');
check_eq($part['balance_kobo'], 1500000, 'the balance is the total minus payments');
check($part['paid'] === false, 'a partly paid order stays unpaid');

$transfer = payment_record($order['id'], '7000.25', 'transfer');
check_eq($transfer['amount_kobo'], 700025, 'kobo keeps the two naira decimal places');
$stored = db_select('fashion_payments', ['id' => $transfer['id']])[0];
check(strpos((string) $stored['amount_kobo'], '.') === false, 'the stored amount has no decimal point');
check_eq((int) $stored['amount_kobo'], 700025, 'the stored amount is whole kobo');
check_eq((string) $stored['user_id'], (string) current_user_id(), 'a payment records who took it');
check_eq((string) $stored['provider'], 'manual', 'a recorded payment is manual');
check_eq((string) $stored['source'], 'staff', 'a recorded payment is entered by staff');
check((string) $stored['created_at'] !== '', 'a payment records when it was taken');
$both = order_find($order['id']);
check_eq($both['paid_kobo'], 1200025, 'payments add together');
check_eq($both['balance_kobo'], 799975, 'the balance follows every payment');

foreach (['10.555', '0', '-5', 'abc', ''] as $bad) {
    $rejected = payment_record($order['id'], $bad, 'cash');
    check(isset($rejected['error']), 'a payment rejects ' . $bad);
}
check_eq(count(db_select('fashion_payments', ['order_id' => $order['id']])), 2, 'a rejected amount is not stored');
check(isset(payment_record($order['id'], '10', 'card')['error']), 'a payment method is cash or transfer');

$blank = payment_reverse($order['id'], $transfer['id'], '   ');
check_eq($blank['error'] ?? '', 'reversal_reason_required', 'a reversal needs a reason');
$tooLong = payment_reverse($order['id'], $transfer['id'], str_repeat('a', 501));
check_eq($tooLong['error'] ?? '', 'reversal_reason_required', 'a reversal reason stays within 500 characters');
check_eq(count(db_select('fashion_payments', ['order_id' => $order['id']])), 2, 'a reversal without a reason is not stored');
$reversed = payment_reverse($order['id'], $transfer['id'], 'Customer paid twice');
check_eq($reversed['amount_kobo'], -700025, 'a reversal stores the opposite whole kobo amount');
$original = db_select('fashion_payments', ['id' => $transfer['id']])[0];
check_eq((int) $original['amount_kobo'], 700025, 'the original payment stays unchanged');
check(($original['reason'] ?? null) === null, 'the original payment does not gain a reason');
$reversal = db_select('fashion_payments', ['id' => $reversed['id']])[0];
check_eq((string) $reversal['kind'], 'reversal', 'a mistake is a new reversing payment');
check_eq((string) $reversal['reverses_payment_id'], $transfer['id'], 'the reversing payment points at the original');
check_eq((string) $reversal['reason'], 'Customer paid twice', 'the reversal row stores the reason');
$after = order_find($order['id']);
check_eq($after['paid_kobo'], 500000, 'a reversal reduces the amount paid');
check_eq($after['balance_kobo'], 1500000, 'a reversal restores the balance');
$again = payment_reverse($order['id'], $transfer['id'], 'Customer paid twice');
check_eq($again['error'] ?? '', 'already_reversed', 'a payment is reversed once');
check_eq(count(db_select('fashion_payments', ['order_id' => $order['id']])), 3, 'a second reversal is not stored');
check_throws(
    static fn () => db_update('fashion_payments', ['amount_kobo' => 1], ['id' => $cash['id']]),
    RuntimeException::class,
    'This table is append-only.'
);
check_throws(
    static fn () => db_delete('fashion_payments', ['id' => $cash['id']]),
    RuntimeException::class,
    'This table is append-only.'
);
$paymentSource = (string) file_get_contents(dirname(__DIR__) . '/app/domain/payments.php')
    . (string) file_get_contents(dirname(__DIR__) . '/app/fashion/controllers/payments.php');
check(!str_contains($paymentSource, 'db_update'), 'the payment code has no update path');
check(!str_contains($paymentSource, 'db_delete'), 'the payment code has no delete path');

$paidOff = payments_test_order($customer['id'], $garment['id'], '1000');
payment_record($paidOff['id'], '2000', 'cash');
$owing = payment_outstanding();
$owingNumbers = array_column($owing['rows'], 'order_number');
check(in_array($order['order_number'], $owingNumbers, true), 'outstanding lists an order with a balance');
check(!in_array($paidOff['order_number'], $owingNumbers, true), 'outstanding leaves out an order paid in full');
check_eq($owing['total_kobo'], 1500000, 'outstanding adds the balances');

$other = business_register([
    'business_name' => 'Other Payment Shop',
    'owner_name' => 'Bala',
    'email' => 'payments-other@example.com',
    'password' => 'password',
    'phone' => '08037654321',
    'company_type' => 'tailor',
]);
check(isset($other['tenant_id']), 'a second shop can sign up');
try {
    payment_record($order['id'], '100', 'cash');
    check(false, 'another shop cannot record a payment');
} catch (HttpException $e) {
    check($e->status() === 404, 'another shop cannot record a payment');
}
try {
    payment_reverse($order['id'], $cash['id'], 'Not this shop');
    check(false, 'another shop cannot reverse a payment');
} catch (HttpException $e) {
    check($e->status() === 404, 'another shop cannot reverse a payment');
}
check(payment_outstanding()['rows'] === [], 'another shop does not see this balance');

auth_login((string) $shop['user_id'], (string) $shop['tenant_id']);
check_eq(count(db_select('fashion_payments', ['order_id' => $order['id']])), 3, 'the other shop did not add a payment');
$_POST = [
    'csrf' => csrf_token(),
    'amount' => '10',
    'amount_kobo' => '1',
    'method' => 'transfer',
];
try {
    router_dispatch('POST', '/orders/' . $order['id'] . '/payments');
    check(false, 'recording a payment redirects');
} catch (RedirectException $e) {
    check_eq($e->path(), '/orders/' . $order['id'], 'recording a payment returns to the order');
}
$posted = db_select('fashion_payments', ['order_id' => $order['id']], ['order' => 'seq DESC', 'limit' => 1])[0];
check_eq((int) $posted['amount_kobo'], 1000, 'the form amount is naira converted to kobo');
$_SERVER['REQUEST_URI'] = base_path() . '/orders/' . $order['id'];
ob_start();
orders_show($order['id']);
$open = (string) ob_get_clean();
check(str_contains($open, t('amount_paid')), 'the order page shows the amount paid');
check(str_contains($open, t('balance')), 'the order page shows the balance');
check(str_contains($open, t('record_payment')), 'the order page has the payment form');
check(str_contains($open, money(1000)), 'the payment history shows the recorded amount');
check(str_contains($open, t('reverse')), 'a payment can be reversed from the order page');
check(str_contains($open, 'name="reason"'), 'the reverse form asks for a reason');
check(str_contains($open, 'Customer paid twice'), 'the payment history shows the reversal reason');
$_POST = [
    'csrf' => csrf_token(),
    'reason' => '   ',
];
try {
    router_dispatch('POST', '/orders/' . $order['id'] . '/payments/' . $cash['id'] . '/reverse');
    check(false, 'a reversal without a reason redirects');
} catch (RedirectException $e) {
    check_eq($e->path(), '/orders/' . $order['id'], 'a reversal without a reason returns to the order');
}
check_eq(count(db_select('fashion_payments', ['reverses_payment_id' => $cash['id']])), 0, 'a blank reason stores no reversal');

$_SERVER['REQUEST_URI'] = base_path() . '/outstanding';
ob_start();
outstanding_show();
$page = (string) ob_get_clean();
check(str_contains($page, $order['order_number']), 'the outstanding page shows the order number');
check(str_contains($page, 'Hauwa Sani'), 'the outstanding page shows the customer');
check(str_contains($page, money(1499000)), 'the outstanding page shows the balance');

module_save([
    'fashion.orders',
    'fashion.production',
    'fashion.measurements',
    'fashion.communication',
]);
check(module_enabled('fashion.finance') === false, 'finance is switched off');
$_SERVER['REQUEST_URI'] = base_path() . '/';
ob_start();
router_dispatch('GET', '/');
$closedHome = (string) ob_get_clean();
check(!str_contains($closedHome, 'href="' . url('/outstanding') . '"'), 'Balances link is hidden when finance is off');
try {
    ob_start();
    router_dispatch('GET', '/outstanding');
    ob_end_clean();
    check(false, 'outstanding stays closed');
} catch (HttpException $e) {
    ob_end_clean();
    check($e->status() === 403, 'outstanding returns 403');
}
try {
    payment_record($order['id'], '10', 'cash');
    check(false, 'recording a payment stays closed');
} catch (HttpException $e) {
    check($e->status() === 403, 'recording a payment returns 403 when finance is off');
}
ob_start();
orders_show($order['id']);
$plain = (string) ob_get_clean();
check(!str_contains($plain, t('record_payment')), 'the payment form leaves the order page');
check(!str_contains($plain, t('amount_paid')), 'the amount paid leaves the order page');
check(!str_contains($plain, t('balance')), 'the balance leaves the order page');
check(!str_contains($plain, t('payment_history')), 'the payment history leaves the order page');
check(str_contains($plain, money(2000000)), 'the order total stays on the page');
