<?php

declare(strict_types=1);

require_once __DIR__ . '/support.php';
require_once dirname(__DIR__) . '/app/domain/accounts.php';
require_once dirname(__DIR__) . '/app/domain/subscriptions.php';
require_once dirname(__DIR__) . '/app/domain/billing.php';
require_once dirname(__DIR__) . '/app/domain/access.php';
require_once dirname(__DIR__) . '/app/payments/PaystackTransport.php';
require_once dirname(__DIR__) . '/app/payments/SaasPaystack.php';

$connected = db()->query('SELECT DATABASE()')->fetchColumn();
if (!is_string($connected) || !str_ends_with($connected, '_test')) {
    fwrite(STDERR, "Refusing to run billing tests: database name must end with _test.\n");
    exit(1);
}

tests_reset();
$GLOBALS['gmingo_now'] = '2026-11-20 10:00:00';

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

function billing_test_config(array $overrides): void
{
    $cfg = app_config();
    foreach ($overrides as $key => $value) {
        $cfg[$key] = $value;
    }
    $GLOBALS['gmingo_app_config'] = $cfg;
}

$originalConfig = app_config();
$secret = 'sk_test_SaasBillingSecretKey99';
$fake = new FakePaystackTransport();
paystack_transport_use($fake);
billing_test_config([
    'app_key' => str_repeat('a', 32),
    'saas_paystack_secret_key' => $secret,
    'saas_paystack_public_key' => 'pk_test_saas',
    'paystack_base_url' => 'https://paystack.test',
    'public_base_url' => 'https://billing.test',
]);

check(saas_paystack_enabled() === true, 'saas paystack enables with secret and app_key');
check_eq(
    saas_paystack_callback_url(),
    'https://billing.test' . url('/account/billing/return'),
    'saas callback uses public_base_url'
);

$shop = business_register([
    'business_name' => 'Billing Shop',
    'owner_name' => 'Owner Bill',
    'email' => 'billing-shop@example.com',
    'password' => 'password',
    'phone' => '08036660001',
    'company_type' => 'tailor',
]);
check(isset($shop['tenant_id']), 'billing shop registers');
subscription_suspend('force pay');
check(subscription_has_app_access() === false, 'suspended shop needs billing');

$starter = plan_by_code('starter');
$started = billing_checkout_start((string) $starter['id'], 'monthly');
check(isset($started['url']), 'checkout start returns paystack url');
check(isset($started['checkout']['provider_reference']), 'checkout stores reference');
$reference = (string) $started['checkout']['provider_reference'];
check(str_starts_with($reference, 'sb'), 'saas references use sb prefix');
check(str_starts_with((string) $started['url'], 'https://checkout.paystack.com/'), 'checkout redirects to paystack');

$checkouts = db_select('fashion_subscription_checkouts', ['provider_reference' => $reference], ['limit' => 1]);
check($checkouts !== [] && (string) $checkouts[0]['status'] === 'pending', 'checkout row is pending');

$payload = (string) json_encode([
    'event' => 'charge.success',
    'data' => [
        'reference' => $reference,
        'amount' => (int) $starter['price_monthly_kobo'],
        'currency' => 'NGN',
        'status' => 'success',
    ],
], JSON_UNESCAPED_SLASHES);
$signature = hash_hmac('sha512', $payload, $secret);

auth_logout();
$_SERVER['HTTP_X_PAYSTACK_SIGNATURE'] = $signature;
$_SERVER['REQUEST_METHOD'] = 'POST';
$_SERVER['REQUEST_URI'] = url('/webhooks/saas-paystack');
$level = ob_get_level();
ob_start();
try {
    // Simulate webhook body via fulfill directly (router cannot inject php://input easily).
    billing_webhook_receive($payload, $signature);
    $webhookStatus = http_response_code();
} finally {
    while (ob_get_level() > $level) {
        ob_end_clean();
    }
}
check($webhookStatus === 200, 'saas webhook accepts a signed charge.success');

check(login_user('billing-shop@example.com', 'password') === null, 'owner signs in after webhook');
$sub = subscription_current();
check_eq($sub['status'] ?? '', 'active', 'webhook activates the subscription');
check(subscription_has_app_access() === true, 'paid shop regains access');
$payments = db_select('fashion_subscription_payments', ['provider_reference' => $reference], ['limit' => 1]);
check($payments !== [] && (string) $payments[0]['status'] === 'succeeded', 'saas payment is recorded');
$paidCheckout = db_select('fashion_subscription_checkouts', ['provider_reference' => $reference], ['limit' => 1]);
check((string) ($paidCheckout[0]['status'] ?? '') === 'paid', 'checkout marked paid');

$peek = billing_return_peek($reference);
check_eq($peek, 'paid', 'billing return peek reports paid without mutating');
$paymentsBefore = count(db_select('fashion_subscription_payments', ['provider_reference' => $reference]));
$again = billing_verify_reference($reference);
check_eq($again, 'paid', 'verify path is idempotent for a paid checkout');
$paymentsAfter = count(db_select('fashion_subscription_payments', ['provider_reference' => $reference]));
check_eq($paymentsAfter, $paymentsBefore, 'verify does not duplicate subscription payments');

$pendingCheckout = billing_checkout_start((string) $starter['id'], 'monthly');
$pendingRef = (string) $pendingCheckout['checkout']['provider_reference'];
$beforePendingPayments = count(db_select('fashion_subscription_payments', []));
$getPeek = billing_return_peek($pendingRef);
check_eq($getPeek, 'waiting', 'unpaid checkout peeks as waiting');
$stillPending = db_select('fashion_subscription_checkouts', ['provider_reference' => $pendingRef], ['limit' => 1]);
check_eq((string) ($stillPending[0]['status'] ?? ''), 'pending', 'GET peek does not mark checkout paid');
$afterPeekPayments = count(db_select('fashion_subscription_payments', []));
check_eq($afterPeekPayments, $beforePendingPayments, 'GET peek does not add subscription payments');

$renewStart = billing_checkout_start((string) $starter['id'], 'monthly');
check(isset($renewStart['checkout']), 'active shop can start a renewal checkout');
check_eq($renewStart['checkout']['intent'] ?? '', 'renew', 'same plan/interval checkout is renew');
$renewRef = (string) $renewStart['checkout']['provider_reference'];
$renewPayload = (string) json_encode([
    'event' => 'charge.success',
    'data' => ['reference' => $renewRef, 'status' => 'success'],
], JSON_UNESCAPED_SLASHES);
billing_webhook_receive($renewPayload, hash_hmac('sha512', $renewPayload, $secret));
$afterRenew = subscription_current();
check_eq($afterRenew['status'] ?? '', 'active', 'renewal keeps active status');
check((string) ($afterRenew['current_period_end'] ?? '') > (string) ($sub['current_period_end'] ?? ''), 'renewal extends the period');

$bad = saas_paystack_parse_webhook($payload, 'deadbeef');
check(($bad['ok'] ?? true) === false, 'bad webhook signature is rejected');

$psBody = (string) json_encode([
    'event' => 'charge.success',
    'data' => ['reference' => 'ps' . bin2hex(random_bytes(8))],
], JSON_UNESCAPED_SLASHES);
$orderStyle = saas_paystack_parse_webhook($psBody, hash_hmac('sha512', $psBody, $secret));
check(($orderStyle['ok'] ?? true) === false && ($orderStyle['error'] ?? '') === 'ignored', 'order-style references are ignored by saas webhook');

check_throws(
    static function (): void {
        billing_platform_query('SELECT id FROM fashion_subscription_checkouts LIMIT 1');
    },
    RuntimeException::class,
    'This query is only for subscription billing.'
);

billing_test_config([
    'app_key' => str_repeat('a', 32),
    'saas_paystack_secret_key' => '',
    'saas_paystack_public_key' => '',
    'paystack_base_url' => 'https://paystack.test',
    'public_base_url' => 'https://billing.test',
]);
check(saas_paystack_enabled() === false, 'empty saas secret disables online billing');
$manual = billing_checkout_start((string) $starter['id'], 'monthly');
check(isset($manual['errors']['form']), 'checkout refuses when saas paystack is off');

// Restore SaaS keys for remaining cases.
billing_test_config([
    'app_key' => str_repeat('a', 32),
    'saas_paystack_secret_key' => $secret,
    'saas_paystack_public_key' => 'pk_test_saas',
    'paystack_base_url' => 'https://paystack.test',
    'public_base_url' => 'https://billing.test',
]);
check(login_user('billing-shop@example.com', 'password') === null, 'owner signs in for mismatch cases');

$badAmountStart = billing_checkout_start((string) $starter['id'], 'monthly');
$badAmountRef = (string) $badAmountStart['checkout']['provider_reference'];
$fake->verifyOverride[$badAmountRef] = [
    'status' => 200,
    'body' => (string) json_encode([
        'status' => true,
        'data' => [
            'status' => 'success',
            'reference' => $badAmountRef,
            'amount' => ((int) $starter['price_monthly_kobo']) + 100,
            'currency' => 'NGN',
        ],
    ]),
];
check_eq(billing_fulfill_reference($badAmountRef, 'verify'), 'mismatch', 'wrong amount does not activate');
$still = db_select('fashion_subscription_checkouts', ['provider_reference' => $badAmountRef], ['limit' => 1]);
check_eq((string) ($still[0]['status'] ?? ''), 'pending', 'mismatched amount leaves checkout pending');

$badCurrencyStart = billing_checkout_start((string) $starter['id'], 'monthly');
$badCurrencyRef = (string) $badCurrencyStart['checkout']['provider_reference'];
$fake->verifyOverride[$badCurrencyRef] = [
    'status' => 200,
    'body' => (string) json_encode([
        'status' => true,
        'data' => [
            'status' => 'success',
            'reference' => $badCurrencyRef,
            'amount' => (int) $starter['price_monthly_kobo'],
            'currency' => 'USD',
        ],
    ]),
];
check_eq(billing_fulfill_reference($badCurrencyRef, 'verify'), 'mismatch', 'wrong currency does not activate');

$failedStart = billing_checkout_start((string) $starter['id'], 'monthly');
$failedRef = (string) $failedStart['checkout']['provider_reference'];
$fake->verifyOverride[$failedRef] = [
    'status' => 200,
    'body' => (string) json_encode([
        'status' => true,
        'data' => [
            'status' => 'failed',
            'reference' => $failedRef,
            'amount' => (int) $starter['price_monthly_kobo'],
            'currency' => 'NGN',
        ],
    ]),
];
check_eq(billing_fulfill_reference($failedRef, 'verify'), 'waiting', 'failed verify stays waiting');

check_eq(billing_fulfill_reference('sb' . bin2hex(random_bytes(16)), 'verify'), 'missing', 'unknown reference is missing');

$dupPayload = (string) json_encode([
    'event' => 'charge.success',
    'data' => [
        'id' => 900001,
        'reference' => $reference,
        'amount' => (int) $starter['price_monthly_kobo'],
        'currency' => 'NGN',
        'status' => 'success',
    ],
], JSON_UNESCAPED_SLASHES);
$dupSig = hash_hmac('sha512', $dupPayload, $secret);
billing_webhook_receive($dupPayload, $dupSig);
$beforeDup = count(db_select('fashion_subscription_payments', ['provider_reference' => $reference]));
billing_webhook_receive($dupPayload, $dupSig);
$afterDup = count(db_select('fashion_subscription_payments', ['provider_reference' => $reference]));
check_eq($afterDup, $beforeDup, 'duplicate webhook event is idempotent');

$other = business_register([
    'business_name' => 'Other Billing Shop',
    'owner_name' => 'Other Owner',
    'email' => 'billing-other@example.com',
    'password' => 'password',
    'phone' => '08036660002',
    'company_type' => 'tailor',
]);
check(isset($other['tenant_id']), 'second shop registers for isolation');
$otherPayments = subscription_payments_for_tenant();
foreach ($otherPayments as $row) {
    check((string) $row['tenant_id'] === (string) $other['tenant_id'], 'payment history stays on current tenant');
}
check(login_user('billing-shop@example.com', 'password') === null, 'original owner signs back in');
$ownPayments = subscription_payments_for_tenant();
foreach ($ownPayments as $row) {
    check((string) $row['tenant_id'] === (string) current_tenant_id(), 'owner payment history is tenant scoped');
}

// Recurring failed invoice
$subRow = subscription_current();
db_update('fashion_subscriptions', [
    'paystack_subscription_code' => 'SUB_test_billing_1',
    'paystack_customer_code' => 'CUS_test_billing_1',
], ['id' => $subRow['id']]);
$failInvoice = (string) json_encode([
    'event' => 'invoice.payment_failed',
    'data' => [
        'id' => 900002,
        'subscription_code' => 'SUB_test_billing_1',
        'amount' => (int) $starter['price_monthly_kobo'],
        'currency' => 'NGN',
        'reference' => 'invfail_' . bin2hex(random_bytes(6)),
    ],
], JSON_UNESCAPED_SLASHES);
billing_webhook_receive($failInvoice, hash_hmac('sha512', $failInvoice, $secret));
$failedPay = db_select('fashion_subscription_payments', ['status' => 'failed'], ['limit' => 1]);
check($failedPay !== [], 'failed recurring invoice records a failed payment');

$disable = (string) json_encode([
    'event' => 'subscription.disable',
    'data' => [
        'id' => 900003,
        'subscription_code' => 'SUB_test_billing_1',
        'customer' => ['customer_code' => 'CUS_test_billing_1'],
    ],
], JSON_UNESCAPED_SLASHES);
billing_webhook_receive($disable, hash_hmac('sha512', $disable, $secret));
$afterDisable = subscription_current();
check_eq($afterDisable['status'] ?? '', 'cancelled', 'subscription.disable cancels the shop subscription');

// Manual renew fallback when gateway off
billing_test_config([
    'app_key' => str_repeat('a', 32),
    'saas_paystack_secret_key' => '',
    'saas_paystack_public_key' => '',
    'paystack_base_url' => 'https://paystack.test',
    'public_base_url' => 'https://billing.test',
]);
check(saas_paystack_enabled() === false, 'gateway off for manual renew');
check(login_user('billing-shop@example.com', 'password') === null, 'owner for manual renew');
subscription_activate((string) $starter['id'], 'monthly');
$manualSub = subscription_current();
check_eq($manualSub['status'] ?? '', 'active', 'manual activate works when paystack is off');
$manualPayments = db_select('fashion_subscription_payments', ['provider' => 'manual'], ['limit' => 1]);
check($manualPayments !== [], 'manual renew records a manual payment');

// Gateway settings: test/live separation + masked secrets
require_once dirname(__DIR__) . '/app/domain/saas_gateway.php';
require_once dirname(__DIR__) . '/app/domain/platform.php';
billing_test_config([
    'app_key' => str_repeat('a', 32),
    'saas_paystack_secret_key' => '',
    'saas_paystack_public_key' => '',
    'paystack_base_url' => 'https://paystack.test',
    'public_base_url' => 'https://billing.test',
    'platform_admin_email' => 'platform-billing@gmingo.test',
    'platform_admin_password' => 'PlatformAdmin!234',
]);
platform_bootstrap();
$adminUser = db()->query(
    "SELECT id, tenant_id FROM fashion_users WHERE email = 'platform-billing@gmingo.test' LIMIT 1"
)->fetch(PDO::FETCH_ASSOC);
check(is_array($adminUser), 'platform admin user exists for gateway tests');
auth_login((string) $adminUser['id'], (string) $adminUser['tenant_id']);
db()->exec("UPDATE fashion_users SET password_must_change = 0 WHERE email = 'platform-billing@gmingo.test'");
check(is_platform_admin() === true, 'gateway tests run as platform admin');

$liveMismatch = saas_gateway_save([
    'enabled' => true,
    'environment' => 'live',
    'public_key' => 'pk_test_wrongenv',
    'secret_key' => 'sk_test_wrongenv99',
]);
check(isset($liveMismatch['errors']['public_key']), 'live environment rejects pk_test key');

$saved = saas_gateway_save([
    'enabled' => true,
    'environment' => 'test',
    'public_key' => 'pk_test_gatewayui99',
    'secret_key' => 'sk_test_gatewayui99xx',
    'webhook_secret' => 'whsec_test_gateway_secret',
]);
check(($saved['ok'] ?? false) === true, 'gateway settings save in test mode');
$view = saas_gateway_view();
check(($view['secret_mask'] ?? '') !== '' && !str_contains((string) $view['secret_mask'], 'sk_test_gatewayui99xx'), 'secret is masked in gateway view');
check(($view['public_key'] ?? '') === 'pk_test_gatewayui99', 'public key visible after save');
check(str_contains((string) ($view['webhook_url'] ?? ''), '/webhooks/saas-paystack'), 'gateway view shows webhook url');
check(saas_paystack_enabled() === true, 'db gateway enables checkout without config secret');

$disabled = saas_gateway_save([
    'enabled' => false,
    'environment' => 'test',
    'public_key' => 'pk_test_gatewayui99',
]);
check(($disabled['ok'] ?? false) === true, 'gateway can be disabled');
check(saas_paystack_enabled() === false, 'disabled gateway turns checkout off');

$platformPayments = billing_platform_payments(['provider' => 'paystack']);
check(is_array($platformPayments), 'platform payment list returns rows');

unset($GLOBALS['gmingo_paystack_transport']);
$GLOBALS['gmingo_app_config'] = $originalConfig;
