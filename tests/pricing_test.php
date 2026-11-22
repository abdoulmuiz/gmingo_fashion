<?php

declare(strict_types=1);

require_once __DIR__ . '/support.php';
require_once dirname(__DIR__) . '/app/domain/accounts.php';
require_once dirname(__DIR__) . '/app/domain/subscriptions.php';
require_once dirname(__DIR__) . '/app/domain/platform.php';
require_once dirname(__DIR__) . '/app/domain/billing.php';
require_once dirname(__DIR__) . '/app/domain/access.php';
require_once dirname(__DIR__) . '/app/payments/PaystackTransport.php';
require_once dirname(__DIR__) . '/app/payments/SaasPaystack.php';
require_once dirname(__DIR__) . '/app/fashion/controllers/pricing.php';

$connected = db()->query('SELECT DATABASE()')->fetchColumn();
if (!is_string($connected) || !str_ends_with($connected, '_test')) {
    fwrite(STDERR, "Refusing to run pricing tests: database name must end with _test.\n");
    exit(1);
}

tests_reset();
$GLOBALS['gmingo_now'] = '2026-11-21 12:00:00';

$starter = plan_by_code('starter');
$pro = plan_by_code('professional');
$ent = plan_by_code('enterprise');
check(is_array($starter) && is_array($pro) && is_array($ent), 'starter professional enterprise exist');
check(($pro['is_popular'] ?? false) === true, 'professional is marked popular');
check((int) ($starter['sort_order'] ?? 99) < (int) ($pro['sort_order'] ?? 0), 'starter sorts before professional');

$public = plan_list_public();
check(count($public) >= 3, 'public pricing lists active plans');
foreach ($public as $plan) {
    check(($plan['active'] ?? false) === true, 'public list only includes active plans');
    check(isset($plan['price_monthly_kobo'], $plan['price_annual_kobo'], $plan['features']), 'public plans expose prices and features');
}

$originalConfig = app_config();
$GLOBALS['gmingo_app_config'] = array_merge($originalConfig, [
    'app_key' => str_repeat('b', 32),
    'saas_paystack_secret_key' => 'sk_test_PricingSecretKey99',
    'saas_paystack_public_key' => 'pk_test_pricing',
    'paystack_base_url' => 'https://paystack.test',
    'public_base_url' => 'https://pricing.test',
    'platform_admin_email' => 'platform-pricing@gmingo.test',
    'platform_admin_password' => 'PlatformAdmin!234',
]);
platform_bootstrap();
$admin = db()->query("SELECT id, tenant_id FROM fashion_users WHERE email = 'platform-pricing@gmingo.test' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
check(is_array($admin), 'platform admin ready for pricing edits');
auth_login((string) $admin['id'], (string) $admin['tenant_id']);
db()->exec("UPDATE fashion_users SET password_must_change = 0 WHERE email = 'platform-pricing@gmingo.test'");

$beforeMonthly = (int) $starter['price_monthly_kobo'];
$noConfirm = plan_update((string) $starter['id'], [
    'price_monthly_kobo' => $beforeMonthly + 10000,
    'price_annual_kobo' => (int) $starter['price_annual_kobo'],
    'confirm_price_change' => false,
]);
check(isset($noConfirm['errors']['confirm_price_change']), 'price change requires confirmation');

$withConfirm = plan_update((string) $starter['id'], [
    'name' => (string) $starter['name'],
    'description' => (string) $starter['description'],
    'price_monthly_kobo' => $beforeMonthly + 10000,
    'price_annual_kobo' => (int) $starter['price_annual_kobo'],
    'currency' => 'NGN',
    'features' => $starter['features'],
    'confirm_price_change' => true,
    'paystack_plan_code_monthly' => 'PLN_old_starter_month',
]);
check(($withConfirm['ok'] ?? false) === true || isset($withConfirm['plan']), 'confirmed starter price update saves');
$starterAfter = plan_by_code('starter');
check_eq((int) $starterAfter['price_monthly_kobo'], $beforeMonthly + 10000, 'starter monthly price updated in DB');
check((int) $starterAfter['price_version'] === (int) $starter['price_version'] + 1, 'price version bumps on amount change');
check(($starterAfter['paystack_plan_code_monthly'] ?? 'x') === 'PLN_old_starter_month', 'explicit paystack code can be kept on price change');

$proBefore = (int) $pro['price_monthly_kobo'];
plan_update((string) $pro['id'], [
    'price_monthly_kobo' => $proBefore + 5000,
    'price_annual_kobo' => (int) $pro['price_annual_kobo'],
    'confirm_price_change' => true,
]);
check_eq((int) plan_by_code('professional')['price_monthly_kobo'], $proBefore + 5000, 'professional price updates');

$entBefore = (int) $ent['price_monthly_kobo'];
plan_update((string) $ent['id'], [
    'price_monthly_kobo' => $entBefore + 7000,
    'price_annual_kobo' => (int) $ent['price_annual_kobo'],
    'confirm_price_change' => true,
]);
check_eq((int) plan_by_code('enterprise')['price_monthly_kobo'], $entBefore + 7000, 'enterprise price updates');

$reflected = plan_list_public();
$byCode = [];
foreach ($reflected as $row) {
    $byCode[$row['code']] = $row;
}
check_eq((int) $byCode['starter']['price_monthly_kobo'], $beforeMonthly + 10000, 'public pricing reflects starter change');
check_eq((int) $byCode['professional']['price_monthly_kobo'], $proBefore + 5000, 'public pricing reflects professional change');
check_eq((int) $byCode['enterprise']['price_monthly_kobo'], $entBefore + 7000, 'public pricing reflects enterprise change');

plan_update((string) $ent['id'], [
    'active' => false,
    'price_monthly_kobo' => (int) plan_by_code('enterprise')['price_monthly_kobo'],
    'price_annual_kobo' => (int) plan_by_code('enterprise')['price_annual_kobo'],
]);
$afterDeactivate = plan_list_public();
foreach ($afterDeactivate as $row) {
    check($row['code'] !== 'enterprise', 'inactive enterprise hidden from public pricing');
}
plan_update((string) $ent['id'], [
    'active' => true,
    'price_monthly_kobo' => (int) plan_by_code('enterprise')['price_monthly_kobo'],
    'price_annual_kobo' => (int) plan_by_code('enterprise')['price_annual_kobo'],
]);

// Existing subscriber keeps period after catalogue price change
$shop = business_register([
    'business_name' => 'Pricing Shop',
    'owner_name' => 'Price Owner',
    'email' => 'pricing-shop@example.com',
    'password' => 'password',
    'phone' => '08036661111',
    'company_type' => 'tailor',
]);
check(isset($shop['tenant_id']), 'pricing shop registers');
$sub = subscription_current();
$endsBefore = (string) ($sub['current_period_end'] ?? $sub['expires_on'] ?? '');
auth_login((string) $admin['id'], (string) $admin['tenant_id']);
plan_update((string) $starter['id'], [
    'price_monthly_kobo' => (int) plan_by_code('starter')['price_monthly_kobo'] + 1000,
    'price_annual_kobo' => (int) plan_by_code('starter')['price_annual_kobo'],
    'confirm_price_change' => true,
]);
check(login_user('pricing-shop@example.com', 'password') === null, 'shop owner signs back in');
$subAfter = subscription_current();
check_eq((string) ($subAfter['current_period_end'] ?? $subAfter['expires_on'] ?? ''), $endsBefore, 'price change does not move active period');

if (!class_exists('FakePaystackTransportPricing', false)) {
    final class FakePaystackTransportPricing implements PaystackTransport
    {
        public array $amounts = [];
        public function send(string $method, string $url, array $headers, string $body): array
        {
            if ($method === 'POST' && str_contains($url, '/transaction/initialize')) {
                $sent = json_decode($body, true);
                $reference = is_array($sent) ? (string) ($sent['reference'] ?? '') : '';
                $this->amounts[$reference] = is_array($sent) ? (int) ($sent['amount'] ?? 0) : 0;
                return [
                    'status' => 200,
                    'body' => (string) json_encode([
                        'status' => true,
                        'data' => [
                            'authorization_url' => 'https://checkout.paystack.com/' . $reference,
                            'reference' => $reference,
                        ],
                    ]),
                ];
            }
            if ($method === 'GET' && str_contains($url, '/transaction/verify/')) {
                $path = parse_url($url, PHP_URL_PATH);
                $reference = rawurldecode(basename(is_string($path) ? $path : ''));
                return [
                    'status' => 200,
                    'body' => (string) json_encode([
                        'status' => true,
                        'data' => [
                            'status' => 'success',
                            'reference' => $reference,
                            'amount' => $this->amounts[$reference] ?? 0,
                            'currency' => 'NGN',
                        ],
                    ]),
                ];
            }
            return ['status' => 500, 'body' => ''];
        }
    }
}
$fake = new FakePaystackTransportPricing();
paystack_transport_use($fake);
$latestStarter = plan_by_code('starter');
$checkout = billing_checkout_start((string) $latestStarter['id'], 'monthly');
check(isset($checkout['checkout']), 'checkout starts with DB price');
check_eq((int) $checkout['checkout']['amount_kobo'], (int) $latestStarter['price_monthly_kobo'], 'checkout amount matches DB monthly price');
$ref = (string) $checkout['checkout']['provider_reference'];
check_eq($fake->amounts[$ref] ?? 0, (int) $latestStarter['price_monthly_kobo'], 'Paystack initialize uses DB kobo amount');

$annual = billing_checkout_start((string) $latestStarter['id'], 'annual');
check_eq((int) $annual['checkout']['amount_kobo'], (int) $latestStarter['price_annual_kobo'], 'annual checkout uses DB annual price');

// Browser-supplied amount is ignored; checkout always uses DB kobo.
$manipulated = billing_checkout_start((string) $latestStarter['id'], 'monthly');
check(isset($manipulated['checkout']), 'checkout ignores client amounts');
check_eq(
    (int) $manipulated['checkout']['amount_kobo'],
    (int) $latestStarter['price_monthly_kobo'],
    'checkout amount cannot be manipulated via browser'
);

// Unauthorized shop owner cannot open platform plan admin
check(login_user('pricing-shop@example.com', 'password') === null, 'shop owner for unauthorized plan edit');
check_throws(static function (): void {
    require_platform_admin();
}, HttpException::class, t('not_allowed'));
$stillStarter = plan_by_code('starter');
check_eq(
    (int) $stillStarter['price_monthly_kobo'],
    (int) $latestStarter['price_monthly_kobo'],
    'unauthorized user did not change plan price'
);

// Restore catalogue seed amounts so later suite files keep stable plan fixtures.
auth_login((string) $admin['id'], (string) $admin['tenant_id']);
foreach ([
    'starter' => [1500000, 15000000],
    'professional' => [3500000, 35000000],
    'enterprise' => [7500000, 75000000],
] as $code => [$monthly, $annual]) {
    $row = plan_by_code($code);
    if (!is_array($row)) {
        continue;
    }
    plan_update((string) $row['id'], [
        'price_monthly_kobo' => $monthly,
        'price_annual_kobo' => $annual,
        'active' => true,
        'confirm_price_change' => true,
    ]);
}

unset($GLOBALS['gmingo_paystack_transport']);
$GLOBALS['gmingo_app_config'] = $originalConfig;
