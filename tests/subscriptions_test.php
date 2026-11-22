<?php

declare(strict_types=1);

require_once __DIR__ . '/support.php';
require_once dirname(__DIR__) . '/app/domain/accounts.php';
require_once dirname(__DIR__) . '/app/domain/subscriptions.php';
require_once dirname(__DIR__) . '/app/domain/tracking.php';
require_once dirname(__DIR__) . '/app/domain/messages.php';

tests_reset();

$GLOBALS['gmingo_now'] = '2026-11-20 10:00:00';

$starter = plan_by_code('starter');
check(is_array($starter), 'starter plan is seeded');
check_eq($starter['code'], 'starter', 'starter plan code');
check(is_int($starter['price_monthly_kobo']) && $starter['price_monthly_kobo'] > 0, 'starter monthly price is whole kobo');
check($starter['trial_available'] === true, 'starter offers a trial');
check(in_array('fashion.orders', $starter['modules'], true), 'starter includes orders module');

$activePlans = plan_list_active();
check(count($activePlans) >= 3, 'active plan catalogue lists seeded plans');

$customCode = 'qa_' . substr(str_replace('-', '', uuid()), 0, 12);
$custom = plan_create([
    'code' => $customCode,
    'name' => 'QA Limited',
    'description' => 'Test plan with tight limits.',
    'price_monthly_kobo' => 100000,
    'price_annual_kobo' => 1000000,
    'trial_available' => true,
    'trial_days' => 7,
    'active' => true,
    'limits' => [
        'max_staff' => 2,
        'max_branches' => 1,
        'max_products' => 10,
        'max_storage_mb' => 64,
        'max_orders_month' => 25,
    ],
    'modules' => ['fashion.orders', 'fashion.finance'],
]);
check(isset($custom['plan']), 'plan_create returns a plan');
check_eq($custom['plan']['code'] ?? null, $customCode, 'created plan is retrievable by code');
$fetched = isset($custom['plan']) ? plan_get($custom['plan']['id']) : null;
check(is_array($fetched) && $fetched['limits']['max_staff'] === 2, 'plan retrieval keeps limits');

$dup = plan_create([
    'code' => $customCode,
    'name' => 'Dup',
    'price_monthly_kobo' => 1,
    'price_annual_kobo' => 1,
    'modules' => ['fashion.orders'],
]);
check(isset($dup['errors']['code']), 'duplicate plan codes are rejected');

$shopA = business_register([
    'business_name' => 'Sub Shop A',
    'owner_name' => 'Owner A',
    'email' => 'sub-a@example.com',
    'password' => 'password',
    'phone' => '08031110001',
    'company_type' => 'tailor',
]);
check(isset($shopA['tenant_id']), 'shop A registers');
$trial = subscription_current();
check(is_array($trial), 'registration starts a trial subscription');
check_eq($trial['status'], 'trial', 'new shop is on trial');
check_eq($trial['plan_id'], $starter['id'], 'trial uses the starter plan');
check_eq($trial['trial_ends_on'], '2026-12-04', 'trial lasts fourteen days from frozen now');
check(subscription_is_entitled($trial) === true, 'trial is entitled');
$tenantA = db_select('fashion_tenants', ['id' => $shopA['tenant_id']], ['limit' => 1])[0];
check_eq((string) $tenantA['subscription_ends_on'], '2026-12-04', 'trial syncs subscription_ends_on');

$events = db_select('fashion_subscription_events', ['subscription_id' => $trial['id']]);
check($events !== [], 'trial writes subscription history');

$paid = subscription_activate($starter['id'], 'monthly');
check_eq($paid['status'], 'active', 'paid activation sets active');
check_eq($paid['billing_interval'], 'monthly', 'activation stores monthly interval');
check_eq($paid['expires_on'], '2026-12-19', 'monthly period ends after one month');
$payments = db_select('fashion_subscription_payments', ['subscription_id' => $paid['id']]);
check(count($payments) === 1, 'activation records a subscription payment');
check_eq((int) $payments[0]['amount_kobo'], (int) $starter['price_monthly_kobo'], 'subscription payment stores whole kobo');
check_eq((string) $payments[0]['provider'], 'manual', 'subscription payment provider is stored');
$orderPayments = db_select('fashion_payments');
check_eq($orderPayments, [], 'subscription payments stay out of fashion_payments');

$renewed = subscription_renew();
check_eq($renewed['status'], 'active', 'renewal keeps active status');
check_eq($renewed['current_period_start'], '2026-12-20', 'renewal starts the day after the prior period');
check(count(db_select('fashion_subscription_payments', ['subscription_id' => $renewed['id']])) === 2, 'renewal adds another subscription payment');

$growth = plan_by_code('professional');
$changed = subscription_change_plan($growth['id']);
check_eq($changed['plan_id'], $growth['id'], 'plan change updates plan_id');
$intervalChanged = subscription_change_interval('annual');
check_eq($intervalChanged['billing_interval'], 'annual', 'billing interval can change');

$cancelled = subscription_cancel('owner cancelled');
check_eq($cancelled['status'], 'cancelled', 'cancellation sets cancelled');
check($cancelled['cancelled_at'] !== null, 'cancellation stores cancelled_at');
check(subscription_is_entitled($cancelled) === true, 'cancelled stays entitled through the paid period');
$tenantAfterCancel = db_select('fashion_tenants', ['id' => $shopA['tenant_id']], ['limit' => 1])[0];
check_eq((string) $tenantAfterCancel['subscription_ends_on'], $cancelled['expires_on'], 'cancelled keeps ends_on at period end');

$GLOBALS['gmingo_now'] = '2027-01-20 10:00:00';
check(subscription_is_entitled(subscription_current()) === false, 'cancelled loses entitlement after period end');
$expired = subscription_expire();
check_eq($expired['status'], 'expired', 'expire marks the subscription expired');
$tenantExpired = db_select('fashion_tenants', ['id' => $shopA['tenant_id']], ['limit' => 1])[0];
$graceCut = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', '2027-01-20 10:00:00', app_timezone())
    ->modify('-' . (string) (subscription_compat_grace_days() + 1) . ' days')
    ->format('Y-m-d');
check_eq((string) $tenantExpired['subscription_ends_on'], $graceCut, 'expire syncs ends_on beyond tracking grace');

$starterNow = plan_by_code('starter');
$reactivated = subscription_activate(
    $starter['id'],
    'monthly',
    (int) $starterNow['price_monthly_kobo'],
    'manual',
    'sub-pay-reactivate'
);
check_eq($reactivated['status'], 'active', 'expired shops can activate again');
$suspended = subscription_suspend('policy hold');
check_eq($suspended['status'], 'suspended', 'suspend sets suspended');
check(subscription_is_entitled($suspended) === false, 'suspended is not entitled');
check(subscription_allows('access', $suspended) === false, 'suspended fails the access feature check');
$shopASubId = $suspended['id'];

$GLOBALS['gmingo_now'] = '2026-11-20 10:00:00';
auth_logout();
$shopB = business_register([
    'business_name' => 'Sub Shop B',
    'owner_name' => 'Owner B',
    'email' => 'sub-b@example.com',
    'password' => 'password',
    'phone' => '08031110002',
    'company_type' => 'brand',
]);
$trialB = subscription_current();
check(is_array($trialB), 'shop B has its own trial');
check($trialB['id'] !== $shopASubId, 'tenants do not share subscription rows');
check_eq(db_select('fashion_subscriptions', ['id' => $shopASubId]), [], 'shop B cannot read shop A subscription by id');
check_eq(db_select('fashion_subscription_payments', ['subscription_id' => $shopASubId]), [], 'shop B cannot read shop A subscription payments');

$clearTenant = (string) $shopB['tenant_id'];
db()->prepare('DELETE FROM fashion_subscription_events WHERE tenant_id = :tenant_id')->execute(['tenant_id' => $clearTenant]);
db()->prepare('DELETE FROM fashion_subscription_payments WHERE tenant_id = :tenant_id')->execute(['tenant_id' => $clearTenant]);
db()->prepare('DELETE FROM fashion_subscriptions WHERE tenant_id = :tenant_id')->execute(['tenant_id' => $clearTenant]);
$forged = db_insert('fashion_subscriptions', [
    'tenant_id' => $shopA['tenant_id'],
    'plan_id' => $starter['id'],
    'status' => 'active',
    'billing_interval' => 'monthly',
    'started_on' => app_today(),
    'trial_starts_on' => null,
    'trial_ends_on' => null,
    'current_period_start' => app_today(),
    'current_period_end' => '2026-12-19',
    'expires_on' => '2026-12-19',
    'cancelled_at' => null,
    'suspended_at' => null,
    'created_at' => app_now()->format('Y-m-d H:i:s'),
    'updated_at' => app_now()->format('Y-m-d H:i:s'),
]);
$storedTenant = db()->prepare('SELECT tenant_id FROM fashion_subscriptions WHERE id = :id');
$storedTenant->execute(['id' => $forged]);
check_eq($storedTenant->fetchColumn(), $shopB['tenant_id'], 'subscription insert ignores a forged tenant_id');
db()->prepare('DELETE FROM fashion_subscriptions WHERE id = :id')->execute(['id' => $forged]);

auth_logout();
$limitedShop = business_register([
    'business_name' => 'Limited Shop',
    'owner_name' => 'Owner L',
    'email' => 'sub-l@example.com',
    'password' => 'password',
    'phone' => '08031110003',
    'company_type' => 'tailor',
]);
$limitedTenant = (string) $limitedShop['tenant_id'];
db()->prepare('DELETE FROM fashion_subscription_events WHERE tenant_id = :tenant_id')->execute(['tenant_id' => $limitedTenant]);
db()->prepare('DELETE FROM fashion_subscription_payments WHERE tenant_id = :tenant_id')->execute(['tenant_id' => $limitedTenant]);
db()->prepare('DELETE FROM fashion_subscriptions WHERE tenant_id = :tenant_id')->execute(['tenant_id' => $limitedTenant]);
db_update('fashion_tenants', ['subscription_ends_on' => null], ['id' => $limitedTenant]);
$limitedTrial = subscription_start_trial($customCode);
check_eq($limitedTrial['plan_id'], $custom['plan']['id'], 'trial can start on a chosen plan');
check(subscription_allows_module('fashion.orders', $limitedTrial) === true, 'plan modules allow orders');
check(subscription_allows_module('fashion.inventory', $limitedTrial) === false, 'plan modules block inventory');
check(subscription_allows('module:fashion.finance', $limitedTrial) === true, 'feature check supports module: prefix');
check(subscription_limit('max_staff', $limitedTrial) === 2, 'limit lookup returns plan max_staff');
check(subscription_within_limit('max_staff', 2, $limitedTrial) === true, 'usage at the limit is allowed');
check(subscription_within_limit('max_staff', 3, $limitedTrial) === false, 'usage above the limit is blocked');
check(subscription_allows('limit:max_products', $limitedTrial) === true, 'limit feature is allowed when max > 0');

auth_logout();
$compat = business_register([
    'business_name' => 'Compat Shop',
    'owner_name' => 'Owner C',
    'email' => 'sub-c@example.com',
    'password' => 'password',
    'phone' => '08031110004',
    'company_type' => 'tailor',
]);
require_once dirname(__DIR__) . '/app/domain/customers.php';
require_once dirname(__DIR__) . '/app/domain/orders.php';
$customer = customer_create([
    'name' => 'Compat Customer',
    'phone' => '08035550001',
    'gender' => 'female',
    'customer_type' => 'regular',
]);
$order = order_create([
    'customer_id' => $customer['id'],
    'due_date' => '2026-11-25',
    'urgent' => '0',
    'items' => [[
        'garment_type_id' => db_select('fashion_garment_types', [], ['limit' => 1])[0]['id'],
        'quantity' => '1',
        'price' => '5000',
        'fabric' => 'plain',
        'notes' => '',
    ]],
]);
check(isset($order['id']), 'compat order is created');
$token = tracking_issue((string) $order['id']);
check(is_string($token) && tracking_token_ok($token), 'tracking token issues during an entitled trial');
module_save([
    'fashion.orders',
    'fashion.production',
    'fashion.finance',
    'fashion.measurements',
    'fashion.communication',
]);
check(tracking_closed() === false, 'tracking stays open during an entitled trial');
$beyond = app_now()->modify('-' . (string) (tracking_grace_days() + 1) . ' days')->format('Y-m-d');
db_update('fashion_tenants', ['subscription_ends_on' => $beyond], ['id' => $compat['tenant_id']]);
check(tracking_subscription_expired() === true, 'legacy subscription_ends_on still drives tracking expiry');
check(tracking_closed() === true, 'tracking closes when ends_on is past grace');
check_throws(
    fn () => message_prepare_customer((string) $order['id'], 'ready_for_pickup'),
    HttpException::class,
    t('subscription_expired')
);
db_update('fashion_tenants', ['subscription_ends_on' => null], ['id' => $compat['tenant_id']]);
check(tracking_subscription_expired() === false, 'null subscription_ends_on keeps tracking available');

check_throws(
    fn () => subscription_platform_query('SELECT id FROM fashion_plans LIMIT 1'),
    RuntimeException::class,
    'This query is only for subscription plans.'
);

unset($GLOBALS['gmingo_now']);
