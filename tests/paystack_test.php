<?php

declare(strict_types=1);

require_once __DIR__ . '/support.php';
require_once dirname(__DIR__) . '/app/domain/accounts.php';
require_once dirname(__DIR__) . '/app/domain/customers.php';
require_once dirname(__DIR__) . '/app/domain/orders.php';
require_once dirname(__DIR__) . '/app/domain/payments.php';
require_once dirname(__DIR__) . '/app/domain/paystack.php';
require_once dirname(__DIR__) . '/app/payments/PaystackTransport.php';
require_once dirname(__DIR__) . '/app/fashion/controllers/orders.php';
require_once dirname(__DIR__) . '/app/fashion/controllers/paystack.php';
require_once dirname(__DIR__) . '/app/fashion/controllers/payment_settings.php';

$connected = db()->query('SELECT DATABASE()')->fetchColumn();
if (!is_string($connected) || !str_ends_with($connected, '_test')) {
    fwrite(STDERR, "Refusing to run Paystack tests: database name must end with _test.\n");
    exit(1);
}

tests_reset();

if (!class_exists('FakePaystackTransport', false)) {
final class FakePaystackTransport implements PaystackTransport
{
    /** @var list<array{method: string, url: string, headers: list<string>, body: string}> */
    public array $calls = [];

    /** @var array<string, string> */
    public array $amounts = [];

    /** @var array<string, array{status: int, body: string}> */
    public array $verifyOverride = [];

    public function send(string $method, string $url, array $headers, string $body): array
    {
        $this->calls[] = [
            'method' => $method,
            'url' => $url,
            'headers' => $headers,
            'body' => $body,
        ];
        if ($method === 'POST' && str_contains($url, '/transaction/initialize')) {
            $sent = json_decode($body, true);
            $reference = is_array($sent) ? (string) ($sent['reference'] ?? '') : '';
            $this->amounts[$reference] = is_array($sent) ? (string) ($sent['amount'] ?? '0') : '0';
            return [
                'status' => 200,
                'body' => (string) json_encode([
                    'status' => true,
                    'message' => 'Authorization URL created',
                    'data' => [
                        'authorization_url' => 'https://checkout.paystack.com/' . $reference,
                        'access_code' => 'ACC' . substr($reference, 0, 6),
                        'reference' => $reference,
                    ],
                ]),
            ];
        }
        if ($method === 'GET' && str_contains($url, '/transaction/verify/')) {
            $path = parse_url($url, PHP_URL_PATH);
            $reference = rawurldecode(basename(is_string($path) ? $path : ''));
            if (isset($this->verifyOverride[$reference])) {
                return $this->verifyOverride[$reference];
            }
            return [
                'status' => 200,
                'body' => (string) json_encode([
                    'status' => true,
                    'message' => 'Verification successful',
                    'data' => [
                        'status' => 'success',
                        'reference' => $reference,
                        'amount' => (int) ($this->amounts[$reference] ?? 0),
                        'currency' => 'NGN',
                    ],
                ]),
            ];
        }
        return ['status' => 500, 'body' => ''];
    }
}
}

function paystack_test_config(string $domain, string $base): void
{
    $cfg = app_config();
    $cfg['payment_placeholder_domain'] = $domain;
    $cfg['paystack_base_url'] = $base;
    $GLOBALS['gmingo_app_config'] = $cfg;
}

function paystack_test_order(string $customerId): string
{
    $garment = db_select('fashion_garment_types', ['code' => 'kaftan'], ['limit' => 1])[0];
    $order = order_create([
        'customer_id' => $customerId,
        'due_date' => date('Y-m-d', strtotime('+4 days')),
        'urgent' => '',
        'items' => [[
            'garment_type_id' => $garment['id'],
            'quantity' => '1',
            'price' => '2000',
            'fabric' => '',
            'notes' => '',
        ]],
    ]);
    return (string) $order['id'];
}

function paystack_test_event(string $reference): string
{
    return (string) json_encode([
        'event' => 'charge.success',
        'data' => [
            'reference' => $reference,
            'amount' => 1,
            'currency' => 'USD',
            'status' => 'success',
        ],
    ], JSON_UNESCAPED_SLASHES);
}

function paystack_test_sign(string $secret, string $body): string
{
    return hash_hmac('sha512', $body, $secret);
}

function paystack_test_payments(string $orderId): array
{
    return db_select('fashion_payments', ['order_id' => $orderId, 'provider' => 'paystack'], ['limit' => 20]);
}

$originalConfig = app_config();
$logFile = dirname(__DIR__) . '/storage/logs/paystack.log';
if (is_file($logFile)) {
    file_put_contents($logFile, '');
}
$rateDir = dirname(__DIR__) . '/storage/ratelimit/paystack';
if (is_dir($rateDir)) {
    foreach (glob($rateDir . '/*.json') ?: [] as $rateFile) {
        if (is_file($rateFile)) {
            unlink($rateFile);
        }
    }
}

$secret = 'sk_test_SuperSecretKey99';
$public = 'pk_test_ShopPublicKey';
$fake = new FakePaystackTransport();
paystack_transport_use($fake);
paystack_test_config('', 'https://paystack.test');

$shop = business_register([
    'business_name' => 'Paystack Shop',
    'owner_name' => 'Amina Bello',
    'email' => 'paystack-shop@example.com',
    'password' => 'password',
    'phone' => '08031234567',
    'company_type' => 'tailor',
]);
$ownerId = (string) $shop['user_id'];
$tenantId = (string) $shop['tenant_id'];
$customer = customer_create([
    'name' => 'Amina Mail',
    'phone' => '08031112233',
    'gender' => 'female',
    'customer_type' => 'regular',
]);
$orderId = paystack_test_order((string) $customer['id']);

module_save([
    'fashion.orders',
    'fashion.production',
    'fashion.finance',
    'fashion.measurements',
    'fashion.communication',
    'fashion.online_payments',
]);
$blocked = payment_settings_save('paystack', $public, $secret);
check_eq($blocked['error'] ?? '', 'payment_domain_missing', 'Paystack mode cannot be switched on while payment_placeholder_domain is empty');
check_eq(payment_mode(), 'manual', 'an empty placeholder domain leaves Paystack off');

$_SERVER['REQUEST_URI'] = base_path() . '/orders/' . $orderId;
ob_start();
orders_show($orderId);
$manualPage = (string) ob_get_clean();
check(!str_contains($manualPage, t('request_payment')), 'manual mode shows no Request payment button');
check_eq(payment_request_create($orderId, '', '')['error'] ?? '', 'manual_mode', 'manual mode does not create a payment link');

paystack_test_config('https://pay.example.test', 'https://paystack.test');
$saved = payment_settings_save('paystack', $public, $secret);
check(isset($saved['ok']), 'the shop can turn Paystack on when the placeholder domain is set');
$token = (string) db_select('fashion_payment_settings', [], ['limit' => 1])[0]['webhook_token'];

$balanceBefore = (int) order_find($orderId)['balance_kobo'];
$over = payment_request_create($orderId, '2001', '');
check_eq($over['error'] ?? '', 'amount_above_balance', 'amounts above the balance are refused');
check_eq(count($fake->calls), 0, 'an amount above the balance does not call Paystack');
check_eq(count(db_select('fashion_payment_requests', ['order_id' => $orderId], ['limit' => 5])), 0, 'a refused link is not stored');

db_update('fashion_customers', ['email' => 'amina.mail@example.com'], ['id' => (string) $customer['id']]);
$linked = payment_request_create($orderId, '', '');
check(isset($linked['reference']), 'request payment creates a link for the balance');
check_eq((int) ($linked['amount_kobo'] ?? 0), $balanceBefore, 'the default amount is the balance in kobo');
check_eq((int) order_find($orderId)['balance_kobo'], $balanceBefore, 'a pending request does not change the balance');
check_eq(count(paystack_test_payments($orderId)), 0, 'a pending request is not a payment');
$init = json_decode($fake->calls[0]['body'], true);
check(is_array($init), 'initialize sends JSON');
check_eq($init['email'] ?? '', 'amina.mail@example.com', 'a customer with an email uses it');
check_eq($init['amount'] ?? '', (string) $balanceBefore, 'initialize sends the amount in kobo');
check_eq($init['currency'] ?? '', 'NGN', 'initialize sends Naira');
check_eq($init['reference'] ?? '', (string) $linked['reference'], 'the reference sent to Paystack is the stored reference');
check_eq($init['callback_url'] ?? '', 'https://pay.example.test' . url('/pay/return'), 'the callback URL uses the base path');
check(str_starts_with($fake->calls[0]['url'], 'https://paystack.test/transaction/initialize'), 'the Paystack base URL comes from config');
check(preg_match('/^ps[a-f0-9]{32}$/', (string) $linked['reference']) === 1, 'the reference is random');

$_SERVER['REQUEST_URI'] = base_path() . '/orders/' . $orderId;
ob_start();
orders_show($orderId);
$linkPage = (string) ob_get_clean();
check(str_contains($linkPage, (string) $linked['url']), 'the order page shows the payment link');
check(str_contains($linkPage, 'https://wa.me/?text='), 'the order page has a WhatsApp share button');
check(!str_contains($linkPage, t('no_email_receipt')), 'a saved email does not show the placeholder note');

$plain = customer_create([
    'name' => 'Zainab Danladi',
    'phone' => '08037654321',
    'gender' => 'female',
    'customer_type' => 'regular',
]);
$plainOrder = paystack_test_order((string) $plain['id']);
$firstPlaceholder = payment_request_create($plainOrder, '', '');
$secondOrder = paystack_test_order((string) $plain['id']);
$secondPlaceholder = payment_request_create($secondOrder, '', '');
$storedEmail = (string) db_select('fashion_customers', ['id' => (string) $plain['id']], ['limit' => 1])[0]['email'];
check(($firstPlaceholder['placeholder'] ?? false) === true, 'the generated address is marked as a placeholder');
check(($secondPlaceholder['placeholder'] ?? false) === true, 'the reused address is still a placeholder');
$firstBody = json_decode($fake->calls[1]['body'], true);
$secondBody = json_decode($fake->calls[2]['body'], true);
check_eq($firstBody['email'] ?? '', $storedEmail, 'the first link uses the generated placeholder');
check_eq($secondBody['email'] ?? '', $storedEmail, 'a customer without an email gets the same placeholder every time');
check(preg_match('/^pay-[a-f0-9]{16}@pay\.example\.test$/', $storedEmail) === 1, 'the placeholder is pay- plus 16 random characters');
$lowerEmail = strtolower($storedEmail);
check(!str_contains($lowerEmail, 'zainab') && !str_contains($lowerEmail, 'danladi'), 'the placeholder contains no name');
check(!str_contains($storedEmail, '08037654321') && !str_contains($storedEmail, '8037654321'), 'the placeholder contains no phone number');
$_SERVER['REQUEST_URI'] = base_path() . '/orders/' . $plainOrder;
ob_start();
orders_show($plainOrder);
$notePage = (string) ob_get_clean();
check(str_contains($notePage, t('no_email_receipt')), 'a placeholder shows that the customer will not get an email receipt');

$typedCustomer = customer_create([
    'name' => 'Hala Bello',
    'phone' => '08039876543',
    'gender' => 'female',
    'customer_type' => 'regular',
]);
$typedOrder = paystack_test_order((string) $typedCustomer['id']);
$typed = payment_request_create($typedOrder, '500', 'hala@example.com');
check(isset($typed['reference']), 'staff can type an email when creating a link');
check_eq((string) db_select('fashion_customers', ['id' => (string) $typedCustomer['id']], ['limit' => 1])[0]['email'], 'hala@example.com', 'a typed email is saved on the customer');
check(($typed['placeholder'] ?? true) === false, 'a typed email is not a placeholder');

$reference = (string) $linked['reference'];
$body = paystack_test_event($reference);
ob_start();
paystack_webhook_receive($token, $body, 'not-the-signature');
$badBody = (string) ob_get_clean();
check_eq(http_response_code(), 400, 'a bad signature is rejected');
check_eq($badBody, '', 'a bad signature response has no detail');
check_eq(count(paystack_test_payments($orderId)), 0, 'a bad signature records nothing');
check_eq((string) db_select('fashion_payment_requests', ['reference' => $reference], ['limit' => 1])[0]['status'], 'pending', 'a bad signature leaves the request pending');

$unknownBody = paystack_test_event('ps' . bin2hex(random_bytes(16)));
$paymentsBeforeUnknown = (int) db()->query('SELECT COUNT(*) FROM fashion_payments')->fetchColumn();
ob_start();
paystack_webhook_receive($token, $unknownBody, paystack_test_sign($secret, $unknownBody));
ob_end_clean();
check_eq(http_response_code(), 200, 'an unknown reference is acknowledged');
check_eq((int) db()->query('SELECT COUNT(*) FROM fashion_payments')->fetchColumn(), $paymentsBeforeUnknown, 'an unknown reference records nothing');

$mismatchRef = (string) $typed['reference'];
$fake->verifyOverride[$mismatchRef] = [
    'status' => 200,
    'body' => (string) json_encode([
        'status' => true,
        'data' => [
            'status' => 'success',
            'reference' => $mismatchRef,
            'amount' => ((int) $typed['amount_kobo']) + 100,
            'currency' => 'NGN',
        ],
    ]),
];
$mismatchEvent = paystack_test_event($mismatchRef);
ob_start();
paystack_webhook_receive($token, $mismatchEvent, paystack_test_sign($secret, $mismatchEvent));
ob_end_clean();
$mismatchRow = db_select('fashion_payment_requests', ['reference' => $mismatchRef], ['limit' => 1])[0];
check_eq(count(paystack_test_payments($typedOrder)), 0, 'an amount mismatch records nothing');
check_eq((int) $mismatchRow['needs_review'], 1, 'an amount mismatch flags the request for review');
check_eq((string) $mismatchRow['status'], 'pending', 'an amount mismatch does not mark the request paid');

unset($fake->verifyOverride[$mismatchRef]);
ob_start();
paystack_webhook_receive($token, $body, paystack_test_sign($secret, $body));
$okBody = (string) ob_get_clean();
check_eq(http_response_code(), 200, 'a valid webhook is acknowledged');
check_eq($okBody, '', 'a valid webhook response has no detail');
$paidRows = paystack_test_payments($orderId);
check_eq(count($paidRows), 1, 'a valid signature and a matching verify record exactly one payment');
check_eq((string) $paidRows[0]['provider'], 'paystack', 'the payment provider is Paystack');
check_eq((string) $paidRows[0]['source'], 'webhook', 'the payment source is the webhook');
check_eq((string) $paidRows[0]['provider_reference'], $reference, 'the payment keeps the Paystack reference');
check_eq((int) $paidRows[0]['amount_kobo'], $balanceBefore, 'the payment amount is the request amount in kobo');
check_eq((int) order_find($orderId)['balance_kobo'], 0, 'the confirmed payment reduces the balance');

$verifyCalls = 0;
foreach ($fake->calls as $call) {
    if ($call['method'] === 'GET' && str_contains($call['url'], '/transaction/verify/' . $reference)) {
        $verifyCalls++;
    }
}
for ($i = 0; $i < 4; $i++) {
    ob_start();
    paystack_webhook_receive($token, $body, paystack_test_sign($secret, $body));
    ob_end_clean();
    check_eq(http_response_code(), 200, 'a repeated webhook is acknowledged');
}
check_eq(count(paystack_test_payments($orderId)), 1, 'the same webhook sent five times records one payment');
$verifyAfter = 0;
foreach ($fake->calls as $call) {
    if ($call['method'] === 'GET' && str_contains($call['url'], '/transaction/verify/' . $reference)) {
        $verifyAfter++;
    }
}
check_eq($verifyAfter, $verifyCalls, 'a repeated webhook does not verify again');

$other = business_register([
    'business_name' => 'Other Shop',
    'owner_name' => 'Bala Musa',
    'email' => 'other-shop@example.com',
    'password' => 'password',
    'phone' => '08032345678',
    'company_type' => 'tailor',
]);
module_save([
    'fashion.orders',
    'fashion.production',
    'fashion.finance',
    'fashion.measurements',
    'fashion.communication',
    'fashion.online_payments',
]);
$otherSecret = 'sk_test_OtherShopSecret9';
check(isset(payment_settings_save('paystack', 'pk_test_OtherPublic', $otherSecret)['ok']), 'the other shop can save its own Paystack key');
$otherToken = (string) db_select('fashion_payment_settings', [], ['limit' => 1])[0]['webhook_token'];
$cross = paystack_test_event((string) $secondPlaceholder['reference']);
ob_start();
paystack_webhook_receive($otherToken, $cross, paystack_test_sign($otherSecret, $cross));
ob_end_clean();
auth_login($ownerId, $tenantId);
check_eq((string) db_select('fashion_payment_requests', ['reference' => (string) $secondPlaceholder['reference']], ['limit' => 1])[0]['status'], 'pending', 'a webhook for business A sent to business B records nothing');
check_eq(count(paystack_test_payments($secondOrder)), 0, 'business A gains no payment from business B webhook');
auth_login((string) $other['user_id'], (string) $other['tenant_id']);
check_eq(count(db_select('fashion_payments', ['provider' => 'paystack'], ['limit' => 20])), 0, 'business B records nothing for business A reference');
auth_login($ownerId, $tenantId);

$aloneOrder = paystack_test_order((string) $customer['id']);
$alone = payment_request_create($aloneOrder, '400', '');
$aloneRef = (string) $alone['reference'];
$fake->verifyOverride[$aloneRef] = [
    'status' => 200,
    'body' => (string) json_encode([
        'status' => true,
        'data' => [
            'status' => 'abandoned',
            'reference' => $aloneRef,
            'amount' => (int) $alone['amount_kobo'],
            'currency' => 'NGN',
        ],
    ]),
];
$_GET = ['reference' => $aloneRef, 'status' => 'success'];
$_SERVER['REQUEST_URI'] = base_path() . '/pay/return?reference=' . $aloneRef;
ob_start();
paystack_return_show();
$alonePage = (string) ob_get_clean();
check(str_contains($alonePage, t('payment_confirming')), 'a pending callback says the payment is being confirmed');
check_eq(count(paystack_test_payments($aloneOrder)), 0, 'the browser callback alone cannot create a payment');
check_eq((int) db_select('fashion_payment_requests', ['reference' => $aloneRef], ['limit' => 1])[0]['needs_review'], 0, 'an unconfirmed callback does not flag the request');

unset($fake->verifyOverride[$aloneRef]);
ob_start();
paystack_return_show();
$receivedPage = (string) ob_get_clean();
check(str_contains($receivedPage, t('payment_received')), 'a callback shows payment received only after Paystack verifies it');
$returnRows = paystack_test_payments($aloneOrder);
check_eq(count($returnRows), 1, 'a verified callback records one payment through the shared function');
check_eq((string) $returnRows[0]['source'], 'return', 'the callback records the payment as the return visit');
ob_start();
paystack_webhook_receive($token, paystack_test_event($aloneRef), paystack_test_sign($secret, paystack_test_event($aloneRef)));
ob_end_clean();
check_eq(count(paystack_test_payments($aloneOrder)), 1, 'a webhook after the callback does not record a second payment');

auth_logout();
ob_start();
paystack_webhook_receive('not-a-real-token', '{}', 'abc');
$missingBody = (string) ob_get_clean();
check_eq(http_response_code(), 404, 'an unknown token is 404');
check_eq($missingBody, '', 'an unknown token response has no detail');
check(current_user_id() === null && current_tenant_id() === null, 'a webhook does not sign anybody in');
auth_login($ownerId, $tenantId);

$_SERVER['REQUEST_URI'] = base_path() . '/orders/' . $orderId;
ob_start();
orders_show($orderId);
$historyPage = (string) ob_get_clean();
check(str_contains($historyPage, t('mode_paystack')), 'the order page labels the online payment Paystack');
check(str_contains($historyPage, $reference), 'the order page shows the Paystack reference');
check(str_contains($historyPage, '/reverse'), 'a Paystack payment can still be reversed');
check(!str_contains($historyPage, 'delete'), 'staff cannot delete a Paystack payment');
$reversed = payment_reverse($orderId, (string) $paidRows[0]['id'], 'Customer paid twice');
check(isset($reversed['id']), 'a reversal is a new row');
$stillThere = db_select('fashion_payments', ['id' => (string) $paidRows[0]['id']], ['limit' => 1]);
check(count($stillThere) === 1, 'the original Paystack payment stays in the history');
$reversal = db_select('fashion_payments', ['id' => (string) $reversed['id']], ['limit' => 1])[0];
check_eq((string) $reversal['kind'], 'reversal', 'the reversal is its own payment row');
check_eq((string) $reversal['provider'], 'paystack', 'the reversal keeps the Paystack provider');
check_eq((int) $reversal['amount_kobo'], -$balanceBefore, 'the reversal puts the amount back');
check_eq((string) $reversal['reason'], 'Customer paid twice', 'a Paystack reversal keeps the reason');

$transport = (string) file_get_contents(dirname(__DIR__) . '/app/payments/PaystackTransport.php');
$provider = (string) file_get_contents(dirname(__DIR__) . '/app/payments/PaystackProvider.php');
check(str_contains($transport, 'CURLOPT_TIMEOUT'), 'Paystack requests have a timeout');
check(str_contains($transport, 'CURLOPT_CONNECTTIMEOUT'), 'Paystack requests have a connect timeout');
check(str_contains($transport, 'CURLOPT_SSL_VERIFYPEER => true'), 'Paystack requests verify TLS');
check(str_contains($transport, 'CURLOPT_SSL_VERIFYHOST => 2'), 'Paystack requests check the TLS host');
check(!str_contains($provider, 'curl_init'), 'the provider does not call curl itself');
check(route_defined('POST', '/webhooks/paystack/{token}'), 'the webhook route is registered');
check(route_defined('GET', '/pay/return'), 'the callback route is registered');
check_throws(
    fn () => paystack_platform_query('SELECT id FROM fashion_payment_settings', []),
    RuntimeException::class,
    'This query is only for Paystack.'
);

for ($i = 0; $i < 31; $i++) {
    ob_start();
    paystack_webhook_receive('flood-token', '{}', 'abc');
    ob_end_clean();
}
check_eq(http_response_code(), 429, 'the webhook is rate limited');

$pages = $manualPage . $linkPage . $notePage . $alonePage . $receivedPage . $historyPage . $badBody . $okBody . $missingBody;
$activity = (string) json_encode(db_select('fashion_activity_log', [], ['limit' => 100]));
$logText = is_file($logFile) ? (string) file_get_contents($logFile) : '';
check(str_contains($logText, 'bad_signature'), 'a bad signature is logged');
check(str_contains($logText, 'mismatch'), 'an amount mismatch is logged');
check(str_contains($logText, 'unknown_reference'), 'an unknown reference is logged');
check(str_contains($logText, 'unknown_token'), 'an unknown token is logged');
check(!str_contains($pages . $activity . $logText, $secret), 'the secret key never appears in logs or responses');
check(!str_contains($pages . $activity . $logText, $otherSecret), 'the other shop secret never appears in logs or responses');

$GLOBALS['gmingo_app_config'] = $originalConfig;
unset($GLOBALS['gmingo_paystack_transport']);
