<?php

declare(strict_types=1);

require_once __DIR__ . '/support.php';
require_once dirname(__DIR__) . '/app/domain/accounts.php';
require_once dirname(__DIR__) . '/app/http/auth_pages.php';

tests_reset();

function sample_shop(string $email = 'owner@example.com'): array
{
    return business_register([
        'business_name' => 'Kano Stitches',
        'owner_name' => 'Amina Bello',
        'email' => $email,
        'password' => 'password',
        'phone' => '08031234567',
    ]);
}

$created = sample_shop();
check(isset($created['user_id'], $created['tenant_id']), 'signup returns the new shop');
check_eq(current_user_id(), $created['user_id'], 'signup signs the owner in');
check_eq(current_tenant_id(), $created['tenant_id'], 'signup binds the business');

$stages = db_select('fashion_stage_definitions', [], ['order' => 'sort_order ASC']);
check_eq(array_column($stages, 'name'), [
    'New', 'Cutting', 'Sewing', 'Embroidery', 'Finishing', 'Fitting', 'Ready', 'Delivered',
], 'default stages are seeded');

$garments = db_select('fashion_garment_types', [], ['order' => 'sort_order ASC']);
check_eq(array_column($garments, 'name'), ['Kaftan', 'Senator', 'Agbada', 'Shirt', 'Trouser', 'Gown'], 'default garments are seeded');
$kaftan = null;
foreach ($garments as $garment) {
    if ($garment['code'] === 'kaftan') {
        $kaftan = $garment;
    } else {
        check((int) $garment['placeholder'] === 1, $garment['name'] . ' is marked as a placeholder');
    }
}
check(is_array($kaftan) && (int) $kaftan['placeholder'] === 0, 'Kaftan is the confirmed list');
$fields = db_select('fashion_measurement_fields', ['garment_type_id' => $kaftan['id']], ['order' => 'sort_order ASC']);
check_eq(array_column($fields, 'name'), [
    'neck', 'shoulder', 'chest', 'stomach', 'sleeve length', 'round sleeve', 'cuff', 'top length',
], 'Kaftan fields match FR-6.2');
foreach ($garments as $garment) {
    if ($garment['code'] === 'kaftan') {
        continue;
    }
    $placeholderFields = db_select('fashion_measurement_fields', ['garment_type_id' => $garment['id']]);
    check($placeholderFields !== [] && (int) $placeholderFields[0]['placeholder'] === 1, $garment['name'] . ' fields are placeholders');
}
check(count(db_select('fashion_order_counters')) === 1, 'order counter is seeded');

$before = (int) db()->query('SELECT COUNT(*) FROM fashion_tenants')->fetchColumn();
$GLOBALS['gmingo_fail_register'] = true;
check_throws(
    fn () => business_register([
        'business_name' => 'Broken Shop',
        'owner_name' => 'Bala',
        'email' => 'broken@example.com',
        'password' => 'password',
        'phone' => '08039876543',
    ]),
    'forced signup failure throws'
);
$GLOBALS['gmingo_fail_register'] = false;
$after = (int) db()->query('SELECT COUNT(*) FROM fashion_tenants')->fetchColumn();
check_eq($after, $before, 'a failed signup does not keep the business');
check(current_user_id() === null, 'a failed signup does not stay signed in');

$duplicate = business_register([
    'business_name' => 'Other Shop',
    'owner_name' => 'Bala',
    'email' => 'owner@example.com',
    'password' => 'password',
    'phone' => '08037654321',
]);
check_eq($duplicate['errors']['email'] ?? '', 'email_taken', 'email cannot be registered twice');

auth_logout();
check(login_user('owner@example.com', 'wrong-password') === 'login_failed', 'wrong password is rejected');
check(current_user_id() === null, 'failed login stays signed out');
check(login_user('owner@example.com', 'password') === null, 'correct password signs in');
check(current_user_id() === $created['user_id'], 'login restores the owner');

auth_boot();
$beforeSession = session_id();
auth_logout();
auth_boot();
$fresh = session_id();
auth_login((string) $created['user_id'], (string) $created['tenant_id']);
check($fresh !== '' && session_id() !== $fresh, 'login regenerates the session id');
check(session_get_cookie_params()['httponly'] === true, 'session cookie is httpOnly');
check(ini_get('session.use_strict_mode') === '1', 'strict session mode is on');
check(ini_get('session.use_only_cookies') === '1', 'session id is only accepted from a cookie');
unset($beforeSession);

auth_clear_attempts('owner@example.com');
auth_logout();
for ($i = 0; $i < 5; $i++) {
    check(login_user('owner@example.com', 'wrong-password') === 'login_failed', 'failure ' . $i . ' is counted');
}
check(login_user('owner@example.com', 'password') === 'rate_limited', 'the sixth try is rate limited');
auth_clear_attempts('owner@example.com');
check(login_user('owner@example.com', 'password') === null, 'login works again after the limit is cleared');

$token = csrf_token();
$_POST = ['csrf' => $token, 'lang' => 'ha', 'back' => '/more'];
try {
    lang_submit();
    check(false, 'language switch redirects');
} catch (RedirectException $e) {
    check_eq($e->path(), '/more', 'language switch returns to More');
}
check_eq($_COOKIE['lang'] ?? '', 'ha', 'language cookie is saved');
$me = db_select('fashion_users', ['id' => current_user_id()]);
check_eq($me[0]['lang'] ?? '', 'ha', 'language is saved on the user');

$_POST = ['csrf' => 'not-the-token', 'lang' => 'en', 'back' => 'https://evil.example'];
check_throws(fn () => lang_submit(), 'a form without the matching token is rejected');

auth_logout();
$_SERVER['REQUEST_URI'] = base_path() . '/login';
ob_start();
router_dispatch('GET', '/login');
$loginHtml = (string) ob_get_clean();
check(str_contains($loginHtml, 'name="csrf"'), 'login form includes a CSRF token');
check(str_contains($loginHtml, t('login')) || str_contains($loginHtml, 'Log in') || str_contains($loginHtml, 'Shiga'), 'login page renders');
check(!str_contains($loginHtml, 'bottom-nav'), 'login page hides the bottom navigation');

$_SERVER['REQUEST_URI'] = base_path() . '/register';
ob_start();
router_dispatch('GET', '/register');
$registerHtml = (string) ob_get_clean();
check(str_contains($registerHtml, 'name="csrf"'), 'register form includes a CSRF token');
check(str_contains($registerHtml, 'name="business_name"'), 'register form asks for the shop');

$_SERVER['REQUEST_URI'] = base_path() . '/register';
ob_start();
router_dispatch('GET', '/register');
$registerHtml = (string) ob_get_clean();
check(str_contains($registerHtml, 'name="csrf"'), 'register form includes a CSRF token');
check(str_contains($registerHtml, 'name="business_name"'), 'register form asks for the shop');

try {
    router_dispatch('GET', '/orders');
    check(false, 'orders require login');
} catch (RedirectException $e) {
    check_eq($e->path(), '/login', 'signed-out orders redirect to login');
}
