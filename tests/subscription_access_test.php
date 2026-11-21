<?php

declare(strict_types=1);

require_once __DIR__ . '/support.php';
require_once dirname(__DIR__) . '/app/domain/accounts.php';
require_once dirname(__DIR__) . '/app/domain/subscriptions.php';
require_once dirname(__DIR__) . '/app/domain/access.php';
require_once dirname(__DIR__) . '/app/domain/onboarding.php';

$connected = db()->query('SELECT DATABASE()')->fetchColumn();
if (!is_string($connected) || !str_ends_with($connected, '_test')) {
    fwrite(STDERR, "Refusing to run subscription access tests: database name must end with _test.\n");
    exit(1);
}

tests_reset();

$GLOBALS['gmingo_now'] = '2026-11-20 10:00:00';

function sub_access_visit(string $method, string $path, array $post = []): array
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

$shop = business_register([
    'business_name' => 'Access Gate Shop',
    'owner_name' => 'Owner Gate',
    'email' => 'access-gate@example.com',
    'password' => 'password',
    'phone' => '08038880001',
    'company_type' => 'tailor',
]);
check(isset($shop['tenant_id']), 'access gate shop registers');

$homeOk = sub_access_visit('GET', '/');
check($homeOk['kind'] === 'page' && $homeOk['status'] === 200, 'entitled shop can open home');

$accountOk = sub_access_visit('GET', '/account/subscription');
check($accountOk['kind'] === 'page' && $accountOk['status'] === 200, 'entitled shop can open account subscription');
check(str_contains($accountOk['body'], t('subscription_account')), 'account page shows subscription heading');

$sub = subscription_current();
check(is_array($sub), 'shop has a subscription');
subscription_suspend('access gate test');
check(subscription_has_app_access() === false, 'suspended shop loses app access');
check_eq(access_denial('/'), 'subscription', 'home denial is subscription when suspended');
check_eq(access_denial('/more'), '', 'more stays open when suspended');
check_eq(access_denial('/account/subscription'), '', 'account subscription stays open when suspended');

$homeBlocked = sub_access_visit('GET', '/');
check($homeBlocked['kind'] === 'redirect' && $homeBlocked['location'] === '/account/subscription', 'suspended home redirects to account');

$ordersBlocked = sub_access_visit('GET', '/orders');
check($ordersBlocked['kind'] === 'redirect' && $ordersBlocked['location'] === '/account/subscription', 'suspended orders redirects to account');

$moreOpen = sub_access_visit('GET', '/more');
check($moreOpen['kind'] === 'page' && $moreOpen['status'] === 200, 'suspended shop can open more');
check(str_contains($moreOpen['body'], url('/account/subscription')), 'more page links to subscription account');

$accountOpen = sub_access_visit('GET', '/account/subscription');
check($accountOpen['kind'] === 'page' && $accountOpen['status'] === 200, 'suspended shop can open account subscription');
check(str_contains($accountOpen['body'], t('subscription_access_blocked')), 'account page explains blocked access');

$renew = sub_access_visit('POST', '/account/subscription', [
    'csrf' => csrf_token(),
    'action' => 'renew',
]);
check($renew['kind'] === 'redirect' && $renew['location'] === '/', 'owner renew redirects home');
check(subscription_has_app_access() === true, 'manual renew restores app access');

$homeAgain = sub_access_visit('GET', '/');
check($homeAgain['kind'] === 'page' && $homeAgain['status'] === 200, 'renewed shop can open home again');

auth_logout();
$limitedCode = 'qa_access_' . substr(str_replace('-', '', uuid()), 0, 8);
$limitedPlan = plan_create([
    'code' => $limitedCode,
    'name' => 'Access Limited',
    'description' => 'Tight staff limit for access tests.',
    'price_monthly_kobo' => 100000,
    'price_annual_kobo' => 1000000,
    'trial_available' => true,
    'trial_days' => 7,
    'active' => true,
    'limits' => [
        'max_staff' => 1,
        'max_branches' => 1,
        'max_products' => 5,
        'max_storage_mb' => 32,
        'max_orders_month' => 10,
    ],
    'modules' => ['fashion.orders'],
]);
check(isset($limitedPlan['plan']), 'limited plan is created');

$limitedShop = business_register([
    'business_name' => 'Staff Limit Shop',
    'owner_name' => 'Owner Limit',
    'email' => 'staff-limit@example.com',
    'password' => 'password',
    'phone' => '08038880002',
    'company_type' => 'tailor',
    'start_onboarding' => true,
]);
$limitedTenant = (string) $limitedShop['tenant_id'];
db()->prepare('DELETE FROM fashion_subscription_events WHERE tenant_id = :tenant_id')->execute(['tenant_id' => $limitedTenant]);
db()->prepare('DELETE FROM fashion_subscription_payments WHERE tenant_id = :tenant_id')->execute(['tenant_id' => $limitedTenant]);
db()->prepare('DELETE FROM fashion_subscriptions WHERE tenant_id = :tenant_id')->execute(['tenant_id' => $limitedTenant]);
subscription_start_trial($limitedCode);
check_eq(subscription_staff_count(), 1, 'owner alone counts as one staff');
check(subscription_within_limit('max_staff', 2) === false, 'second staff exceeds max_staff of 1');

$staffResult = onboarding_save_staff([
    'staff_name' => 'Extra Staff',
    'staff_email' => 'extra-staff@example.com',
    'staff_password' => 'password1',
]);
check(isset($staffResult['errors']['staff_email']), 'onboarding staff save blocks over-limit hire');
check_eq($staffResult['errors']['staff_email'] ?? '', 'subscription_limit_reached', 'staff limit error key is returned');

$featurePlanCode = 'qa_feat_' . substr(str_replace('-', '', uuid()), 0, 8);
$featurePlan = plan_create([
    'code' => $featurePlanCode,
    'name' => 'Orders Only',
    'price_monthly_kobo' => 50000,
    'price_annual_kobo' => 500000,
    'trial_available' => true,
    'trial_days' => 7,
    'active' => true,
    'limits' => [
        'max_staff' => 5,
        'max_branches' => 1,
        'max_products' => 20,
        'max_storage_mb' => 64,
        'max_orders_month' => 50,
    ],
    'modules' => ['fashion.orders'],
]);
check(isset($featurePlan['plan']), 'feature-limited plan is created');

auth_logout();
$featureShop = business_register([
    'business_name' => 'Feature Gate Shop',
    'owner_name' => 'Owner Feature',
    'email' => 'feature-gate@example.com',
    'password' => 'password',
    'phone' => '08038880003',
    'company_type' => 'tailor',
]);
$featureTenant = (string) $featureShop['tenant_id'];
db()->prepare('DELETE FROM fashion_subscription_events WHERE tenant_id = :tenant_id')->execute(['tenant_id' => $featureTenant]);
db()->prepare('DELETE FROM fashion_subscription_payments WHERE tenant_id = :tenant_id')->execute(['tenant_id' => $featureTenant]);
db()->prepare('DELETE FROM fashion_subscriptions WHERE tenant_id = :tenant_id')->execute(['tenant_id' => $featureTenant]);
subscription_start_trial($featurePlanCode);
module_save(['fashion.orders', 'fashion.inventory']);
check(module_enabled('fashion.inventory') === true, 'inventory module can be enabled locally');
check(subscription_allows_module('fashion.inventory') === false, 'plan still blocks inventory');
check_eq(access_denial('/stock'), 'subscription_feature', 'stock denial is subscription_feature');

$stockBlocked = sub_access_visit('GET', '/stock');
check($stockBlocked['kind'] === 'error' && $stockBlocked['status'] === 403, 'plan-blocked stock returns 403');

auth_logout();
$GLOBALS['gmingo_now'] = '2026-11-20 10:00:00';
$expiredShop = business_register([
    'business_name' => 'Expired Gate Shop',
    'owner_name' => 'Owner Expired',
    'email' => 'expired-gate@example.com',
    'password' => 'password',
    'phone' => '08038880004',
    'company_type' => 'tailor',
]);
$expiredSub = subscription_current();
check(is_array($expiredSub), 'expired gate shop has subscription');
$GLOBALS['gmingo_now'] = '2027-01-20 10:00:00';
check(subscription_has_app_access() === false, 'past trial end soft-expires and blocks access');
$softExpired = subscription_current();
check_eq($softExpired['status'] ?? '', 'expired', 'refresh marks past trial as expired');
$expiredHome = sub_access_visit('GET', '/');
check($expiredHome['kind'] === 'redirect' && $expiredHome['location'] === '/account/subscription', 'expired home redirects to account');
$expiredAccount = sub_access_visit('GET', '/account/subscription');
check($expiredAccount['kind'] === 'page' && $expiredAccount['status'] === 200, 'expired shop can still open account');
