<?php

declare(strict_types=1);

require_once __DIR__ . '/support.php';
require_once dirname(__DIR__) . '/app/domain/accounts.php';
require_once dirname(__DIR__) . '/app/domain/onboarding.php';
require_once dirname(__DIR__) . '/app/domain/modules.php';
require_once dirname(__DIR__) . '/app/fashion/controllers/auth_pages.php';
require_once dirname(__DIR__) . '/app/fashion/controllers/onboarding.php';

tests_reset();

check(in_array('boutique', company_types(), true), 'boutique is a company type');
check(in_array('ready_to_wear', company_types(), true), 'ready_to_wear is a company type');

$boutique = business_register([
    'business_name' => 'City Boutique',
    'owner_name' => 'Owner B',
    'email' => 'boutique@example.com',
    'password' => 'password',
    'phone' => '08031112201',
    'company_type' => 'boutique',
    'start_onboarding' => true,
]);
check(isset($boutique['tenant_id']), 'boutique registers');
check_eq($boutique['onboarding'] ?? null, true, 'HTTP-style register starts onboarding');
$tenant = db_select('fashion_tenants', ['id' => $boutique['tenant_id']], ['limit' => 1])[0];
check_eq((string) $tenant['onboarding_step'], 'business', 'new shop starts on business step');
check(onboarding_incomplete($tenant) === true, 'onboarding is incomplete');
$enabled = [];
foreach (db_select('fashion_tenant_modules') as $row) {
    if ((int) $row['enabled'] === 1) {
        $enabled[] = (string) $row['module_key'];
    }
}
sort($enabled);
check_eq($enabled, [
    'fashion.communication',
    'fashion.finance',
    'fashion.inventory',
    'fashion.orders',
    'fashion.sales',
], 'boutique gets brand-like modules');

$rtw = business_register([
    'business_name' => 'RTW Shop',
    'owner_name' => 'Owner R',
    'email' => 'rtw@example.com',
    'password' => 'password',
    'phone' => '08031112202',
    'company_type' => 'ready_to_wear',
]);
check(isset($rtw['tenant_id']), 'ready_to_wear registers');
$rtwTenant = db_select('fashion_tenants', ['id' => $rtw['tenant_id']], ['limit' => 1])[0];
check_eq((string) $rtwTenant['onboarding_step'], 'complete', 'domain register without flag stays complete');
check(module_enabled('fashion.sales') === true, 'ready_to_wear enables sales');

auth_logout();
auth_login((string) $boutique['user_id'], (string) $boutique['tenant_id']);
check_eq(post_login_path(), '/onboarding', 'incomplete owner is sent to onboarding');

$saved = onboarding_save_business([
    'business_name' => 'City Boutique Lagos',
    'company_type' => 'boutique',
]);
check(isset($saved['ok']), 'business step saves');
check_eq(onboarding_current_step(), 'contact', 'after business comes contact');

$contact = onboarding_save_contact([
    'phone' => '08031112201',
    'address' => '12 Market Road',
]);
check(isset($contact['ok']), 'contact step saves');
check_eq(onboarding_current_step(), 'branding', 'after contact comes branding');

$brand = onboarding_save_branding([
    'brand_primary' => '#1e3a8a',
    'brand_accent' => '#38b35a',
], null);
check(isset($brand['ok']), 'branding step saves without logo');
check_eq(onboarding_current_step(), 'preferences', 'after branding comes preferences');

$prefs = onboarding_save_preferences([
    'currency' => 'NGN',
    'lang' => 'en',
]);
check(isset($prefs['ok']), 'preferences step saves');
check_eq(onboarding_current_step(), 'staff', 'after preferences comes staff');

$staff = onboarding_save_staff([
    'staff_name' => 'Cashier One',
    'staff_email' => 'boutique-staff@example.com',
    'staff_password' => 'password',
]);
check(isset($staff['ok']), 'staff step creates a user');
$staffRows = db_select('fashion_users', ['email' => 'boutique-staff@example.com'], ['limit' => 1]);
check($staffRows !== [] && (string) $staffRows[0]['role'] === 'staff', 'staff user is stored');
check_eq(onboarding_current_step(), 'inventory', 'inventory follows staff when inventory is on');

$stock = onboarding_save_inventory([
    'name' => 'Ankara',
    'category' => 'fabric',
    'unit' => 'yards',
    'colour' => 'blue',
    'opening_qty' => '10',
]);
check(isset($stock['ok']), 'inventory step creates stock');
check(db_select('fashion_stock_items') !== [], 'stock item exists');
check_eq(onboarding_current_step(), 'subscription', 'subscription is last step');

$done = onboarding_confirm_subscription();
check(isset($done['ok']), 'subscription confirm finishes onboarding');
$finished = db_select('fashion_tenants', ['id' => $boutique['tenant_id']], ['limit' => 1])[0];
check_eq((string) $finished['onboarding_step'], 'complete', 'onboarding marked complete');
check($finished['onboarding_completed_at'] !== null, 'completion time is stored');
check_eq(post_login_path(), '/', 'completed owner goes home');

auth_logout();
$_POST = [
    'csrf' => csrf_token(),
    'business_name' => 'Repeat Shop',
    'owner_name' => 'Repeat Owner',
    'email' => 'repeat@example.com',
    'password' => 'password',
    'phone' => '08031112203',
    'company_type' => 'tailor',
];
try {
    register_submit();
    check(false, 'register redirects to onboarding');
} catch (RedirectException $e) {
    check_eq($e->path(), '/onboarding', 'successful register opens onboarding');
}
$firstTenant = (string) current_tenant_id();
$beforeCount = (int) db()->query('SELECT COUNT(*) FROM fashion_tenants')->fetchColumn();
$beforeSubs = (int) db()->query('SELECT COUNT(*) FROM fashion_subscriptions')->fetchColumn();
$_POST = [
    'csrf' => csrf_token(),
    'business_name' => 'Repeat Shop Again',
    'owner_name' => 'Repeat Owner',
    'email' => 'repeat@example.com',
    'password' => 'password',
    'phone' => '08031112203',
    'company_type' => 'tailor',
];
try {
    register_submit();
    check(false, 'repeat register while signed in redirects');
} catch (RedirectException $e) {
    check_eq($e->path(), '/onboarding', 'signed-in repeat register does not create another shop');
}
$afterCount = (int) db()->query('SELECT COUNT(*) FROM fashion_tenants')->fetchColumn();
$afterSubs = (int) db()->query('SELECT COUNT(*) FROM fashion_subscriptions')->fetchColumn();
check_eq($afterCount, $beforeCount, 'repeat register does not add a tenant');
check_eq($afterSubs, $beforeSubs, 'repeat register does not add a subscription');
check_eq((string) current_tenant_id(), $firstTenant, 'session tenant stays the same');

auth_logout();
$_POST = [
    'csrf' => csrf_token(),
    'business_name' => 'Dup Email Shop',
    'owner_name' => 'Other',
    'email' => 'repeat@example.com',
    'password' => 'password',
    'phone' => '08031112204',
    'company_type' => 'factory',
];
ob_start();
register_submit();
$dupHtml = (string) ob_get_clean();
check(str_contains($dupHtml, t('email_taken')) || str_contains($dupHtml, 'email'), 'duplicate email is rejected when logged out');
check(current_user_id() === null, 'failed duplicate register stays logged out');

$skipShop = business_register([
    'business_name' => 'Skip Shop',
    'owner_name' => 'Skip Owner',
    'email' => 'skip@example.com',
    'password' => 'password',
    'phone' => '08031112205',
    'company_type' => 'factory',
    'start_onboarding' => true,
]);
onboarding_skip();
$skipped = db_select('fashion_tenants', ['id' => $skipShop['tenant_id']], ['limit' => 1])[0];
check_eq((string) $skipped['onboarding_step'], 'complete', 'finish later completes onboarding');
