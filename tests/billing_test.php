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

$again = billing_fulfill_reference($reference, 'return');
check_eq($again, 'paid', 'return path is idempotent for a paid checkout');

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

unset($GLOBALS['gmingo_paystack_transport']);
$GLOBALS['gmingo_app_config'] = $originalConfig;
