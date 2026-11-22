<?php

declare(strict_types=1);

require_once __DIR__ . '/support.php';
require_once dirname(__DIR__) . '/app/domain/accounts.php';
require_once dirname(__DIR__) . '/app/domain/subscriptions.php';
require_once dirname(__DIR__) . '/app/domain/platform.php';
require_once dirname(__DIR__) . '/app/domain/access.php';
require_once dirname(__DIR__) . '/app/fashion/controllers/onboarding.php';

$connected = db()->query('SELECT DATABASE()')->fetchColumn();
if (!is_string($connected) || !str_ends_with($connected, '_test')) {
    fwrite(STDERR, "Refusing to run platform admin tests: database name must end with _test.\n");
    exit(1);
}

tests_reset();
$GLOBALS['gmingo_now'] = '2026-11-20 10:00:00';

function platform_visit(string $method, string $path, array $post = []): array
{
    http_response_code(200);
    $_GET = [];
    $_POST = $post;
    $_SERVER['REQUEST_METHOD'] = $method;
    $_SERVER['REQUEST_URI'] = url($path);
    $level = ob_get_level();
    ob_start();
    try {
        router_dispatch($method, $path);
        $body = (string) ob_get_clean();
        $status = http_response_code();
        return [
            'kind' => 'page',
            'status' => is_int($status) ? $status : 200,
            'body' => $body,
            'location' => '',
        ];
    } catch (RedirectException $e) {
        while (ob_get_level() > $level) {
            ob_end_clean();
        }
        return ['kind' => 'redirect', 'status' => 302, 'body' => '', 'location' => $e->path()];
    } catch (HttpException $e) {
        while (ob_get_level() > $level) {
            ob_end_clean();
        }
        return ['kind' => 'error', 'status' => $e->status(), 'body' => $e->getMessage(), 'location' => ''];
    } catch (Throwable $e) {
        while (ob_get_level() > $level) {
            ob_end_clean();
        }
        return ['kind' => 'error', 'status' => 500, 'body' => $e->getMessage(), 'location' => ''];
    }
}

check_throws(
    static function (): void {
        $cfg = app_config();
        $cfg['platform_admin_password'] = 'password';
        $GLOBALS['gmingo_app_config'] = $cfg;
        platform_bootstrap();
    },
    RuntimeException::class,
    'Platform admin credentials are not securely configured.'
);
$secureCfg = app_config();
$secureCfg['platform_admin_email'] = 'platform@gmingo.test';
$secureCfg['platform_admin_password'] = 'TestPlatform!Pass99';
$GLOBALS['gmingo_app_config'] = $secureCfg;

check_throws(
    static function (): void {
        $cfg = app_config();
        $cfg['platform_admin_password'] = '';
        $GLOBALS['gmingo_app_config'] = $cfg;
        platform_bootstrap();
    },
    RuntimeException::class,
    'Platform admin credentials are not securely configured.'
);
$GLOBALS['gmingo_app_config'] = $secureCfg;

$boot = platform_bootstrap();
check(isset($boot['user_id']), 'platform bootstrap returns a user');
check_eq($boot['email'], platform_admin_email(), 'platform bootstrap uses configured email');
check(($boot['created'] ?? false) === true || isset($boot['user_id']), 'platform bootstrap creates or reuses admin');

$shop = business_register([
    'business_name' => 'Platform Target Shop',
    'owner_name' => 'Shop Owner',
    'email' => 'platform-target@example.com',
    'password' => 'password',
    'phone' => '08037770001',
    'company_type' => 'tailor',
]);
check(isset($shop['tenant_id']), 'target shop registers');
$shopTenant = (string) $shop['tenant_id'];
$shopDenied = platform_visit('GET', '/platform');
check($shopDenied['kind'] === 'error' && $shopDenied['status'] === 403, 'shop owner cannot open platform home');

auth_logout();
$loginError = login_user(platform_admin_email(), platform_admin_password());
check($loginError === null, 'platform admin can sign in');
check(is_platform_admin() === true, 'signed-in platform user is platform admin');
check_eq(post_login_path(), '/platform', 'platform admin lands on platform home');
check(platform_admin_must_change_password() === true, 'new platform admin must change password');
$forced = platform_visit('GET', '/platform');
check($forced['kind'] === 'redirect' && $forced['location'] === '/platform/password', 'platform home redirects until password changes');
$changed = platform_password_change([
    'current_password' => platform_admin_password(),
    'new_password' => 'TestPlatform!Pass99x',
    'confirm_password' => 'TestPlatform!Pass99x',
]);
check(isset($changed['ok']), 'platform admin can change password');
check(platform_admin_must_change_password() === false, 'password change clears must-change flag');
$secureCfg['platform_admin_password'] = 'TestPlatform!Pass99x';
$GLOBALS['gmingo_app_config'] = $secureCfg;

$home = platform_visit('GET', '/platform');
check($home['kind'] === 'page' && $home['status'] === 200, 'platform admin opens platform home');
check(str_contains($home['body'], t('platform_home')), 'platform home shows heading');

$shopHome = platform_visit('GET', '/');
check($shopHome['kind'] === 'redirect' && $shopHome['location'] === '/platform', 'platform admin is kept out of shop home');

$tenants = platform_list_tenants();
$ids = array_map(static fn (array $row): string => (string) $row['id'], $tenants);
check(in_array($shopTenant, $ids, true), 'platform list includes the target shop');
check(!in_array(subscription_platform_tenant_id(), $ids, true), 'platform list excludes the platform tenant');

$detail = platform_visit('GET', '/platform/tenants/' . $shopTenant);
check($detail['kind'] === 'page' && $detail['status'] === 200, 'platform admin opens shop detail');
check(str_contains($detail['body'], 'Platform Target Shop'), 'shop detail shows business name');

$suspend = platform_visit('POST', '/platform/tenants/' . $shopTenant, [
    'csrf' => csrf_token(),
    'action' => 'suspend',
    'note' => 'policy review',
]);
check($suspend['kind'] === 'redirect', 'suspend redirects back to shop detail');
$afterSuspend = platform_tenant_get($shopTenant);
check_eq($afterSuspend['subscription']['status'] ?? '', 'suspended', 'platform suspend sets suspended');

auth_logout();
check(login_user('platform-target@example.com', 'password') === null, 'shop owner signs back in after suspend');
$blocked = platform_visit('GET', '/orders');
check($blocked['kind'] === 'redirect' && $blocked['location'] === '/account/subscription', 'suspended shop is blocked from orders');

auth_logout();
check(login_user(platform_admin_email(), platform_admin_password()) === null, 'platform admin signs in again');
$growth = plan_by_code('professional');
check(is_array($growth), 'professional plan exists');
$reactivate = platform_visit('POST', '/platform/tenants/' . $shopTenant, [
    'csrf' => csrf_token(),
    'action' => 'reactivate',
    'plan_id' => (string) $growth['id'],
    'billing_interval' => 'monthly',
]);
check($reactivate['kind'] === 'redirect', 'reactivate redirects');
$afterReactivate = platform_tenant_get($shopTenant);
check_eq($afterReactivate['subscription']['status'] ?? '', 'active', 'reactivate sets active');
check_eq($afterReactivate['subscription']['plan_id'] ?? '', (string) $growth['id'], 'reactivate can set professional plan');

$change = platform_change_tenant_plan($shopTenant, (string) plan_by_code('starter')['id']);
check_eq($change['plan_id'], (string) plan_by_code('starter')['id'], 'platform can change shop plan');

$planCode = 'qa_plat_' . substr(str_replace('-', '', uuid()), 0, 8);
$create = platform_visit('POST', '/platform/plans', [
    'csrf' => csrf_token(),
    'code' => $planCode,
    'name' => 'Platform QA Plan',
    'description' => 'Created by platform admin tests.',
    'price_monthly_kobo' => '250000',
    'price_annual_kobo' => '2500000',
    'trial_available' => '1',
    'trial_days' => '7',
    'active' => '1',
    'limits' => [
        'max_staff' => '4',
        'max_branches' => '1',
        'max_products' => '40',
        'max_storage_mb' => '128',
        'max_orders_month' => '100',
    ],
    'modules' => ['fashion.orders', 'fashion.finance'],
]);
check($create['kind'] === 'redirect' && $create['location'] === '/platform/plans', 'plan create redirects to plans');
$created = plan_by_code($planCode);
check(is_array($created), 'created plan is readable');
check_eq($created['name'], 'Platform QA Plan', 'created plan keeps name');
check($created['limits']['max_staff'] === 4, 'created plan keeps limits');

$update = plan_update((string) $created['id'], [
    'name' => 'Platform QA Plan Updated',
    'description' => 'Updated',
    'price_monthly_kobo' => 260000,
    'price_annual_kobo' => 2600000,
    'confirm_price_change' => true,
    'trial_available' => true,
    'trial_days' => 10,
    'active' => true,
    'limits' => [
        'max_staff' => 5,
        'max_branches' => 1,
        'max_products' => 50,
        'max_storage_mb' => 256,
        'max_orders_month' => 120,
    ],
    'modules' => ['fashion.orders', 'fashion.finance', 'fashion.communication'],
]);
check(isset($update['plan']), 'plan update succeeds');
check_eq($update['plan']['name'], 'Platform QA Plan Updated', 'plan update changes name');
check($update['plan']['limits']['max_staff'] === 5, 'plan update changes limits');

check_throws(
    static function (): void {
        admin_platform_query('SELECT id FROM fashion_tenants LIMIT 1');
    },
    RuntimeException::class,
    'This query is only for platform administration.'
);

auth_logout();
check(login_user('platform-target@example.com', 'password') === null, 'reactivated shop owner signs in');
$ordersOk = platform_visit('GET', '/orders');
check($ordersOk['kind'] === 'page' && $ordersOk['status'] === 200, 'reactivated shop can open orders again');
