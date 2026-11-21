<?php

declare(strict_types=1);

require_once __DIR__ . '/support.php';
require_once dirname(__DIR__) . '/app/domain/accounts.php';
require_once dirname(__DIR__) . '/app/fashion/controllers/auth_pages.php';

tests_reset();

function sample_shop(string $email = 'owner@example.com'): array
{
    return business_register([
        'business_name' => 'Kano Stitches',
        'owner_name' => 'Amina Bello',
        'email' => $email,
        'password' => 'password',
        'phone' => '08031234567',
        'company_type' => 'tailor',
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
$defaults = (string) file_get_contents(dirname(__DIR__) . '/app/domain/defaults.php');
check(str_contains($defaults, 'confirm with Kano tailors'), 'placeholder garments say confirm with Kano tailors');
$ownerRow = db_select('fashion_users', ['id' => current_user_id()]);
check(is_array($ownerRow[0] ?? null) && $ownerRow[0]['password_hash'] !== 'password', 'password is stored as a hash');
check(password_verify('password', (string) $ownerRow[0]['password_hash']), 'password_hash verifies the owner password');

$authSource = (string) file_get_contents(dirname(__DIR__) . '/app/core/auth.php');
preg_match_all('/^function\s+(\w+)/m', $authSource, $authFunctions);
check_eq($authFunctions[1], [
    'auth_boot',
    'current_user_id',
    'current_tenant_id',
    'auth_bind_tenant',
    'auth_release_tenant',
    'require_login',
    'auth_login',
    'auth_logout',
], 'auth.php keeps session access next to the identity functions');
check(!str_contains($authSource, 'platform_query_unsafe'), 'auth.php does not look up users');

$before = (int) db()->query('SELECT COUNT(*) FROM fashion_tenants')->fetchColumn();
$GLOBALS['gmingo_fail_register'] = true;
check_throws(
    fn () => business_register([
        'business_name' => 'Broken Shop',
        'owner_name' => 'Bala',
        'email' => 'broken@example.com',
        'password' => 'password',
        'phone' => '08039876543',
        'company_type' => 'tailor',
    ]),
    RuntimeException::class,
    'Simulated signup failure.'
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
    'company_type' => 'tailor',
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
file_put_contents(auth_rate_path('owner@example.com'), json_encode(array_fill(0, 5, time() - 301)));
check(login_user('owner@example.com', 'password') === null, 'failures older than 5 minutes do not block login');

auth_clear_attempts('owner@example.com');
auth_logout();
for ($i = 0; $i < 5; $i++) {
    check(login_user('owner@example.com', 'wrong-password') === 'login_failed', 'failure ' . $i . ' is counted');
}
check(login_user('owner@example.com', 'password') === 'rate_limited', 'the sixth try is rate limited');
$_POST = [
    'csrf' => csrf_token(),
    'email' => 'owner@example.com',
    'password' => 'password',
];
ob_start();
login_submit();
$limited = (string) ob_get_clean();
check(str_contains($limited, t('rate_limited')), 'rate limit shows a friendly error');
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

$_COOKIE['lang'] = 'en';
$_POST = ['csrf' => 'not-the-token', 'lang' => 'en', 'back' => 'https://evil.example'];
check_throws(
    fn () => lang_submit(),
    HttpException::class,
    'This page expired. Go back, refresh it and try again.'
);

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

$openWhenSignedOut = [
    '/login',
    '/register',
    '/pay/return',
    '/track/{token}',
    '/track/{token}/invoice.png',
    '/track/{token}/invoice.pdf',
    '/track/{token}/receipt/{id}.png',
    '/track/{token}/receipt/{id}.pdf',
    '/shop/{slug}',
];
foreach ($GLOBALS['gmingo_routes'] as [$method, $pattern, $handler]) {
    if ($method !== 'GET' || in_array($pattern, $openWhenSignedOut, true)) {
        continue;
    }
    $path = preg_replace('/\{[a-z_]+\}/', uuid(), $pattern);
    try {
        ob_start();
        router_dispatch('GET', (string) $path);
        ob_end_clean();
        check(false, 'signed-out ' . $pattern . ' stays closed');
    } catch (RedirectException $e) {
        ob_end_clean();
        check_eq($e->path(), '/login', 'signed-out ' . $pattern . ' redirects to login');
    }
}
try {
    ob_start();
    router_dispatch('GET', '/missing');
    ob_end_clean();
    check(false, 'signed-out unknown page stays closed');
} catch (RedirectException $e) {
    ob_end_clean();
    check_eq($e->path(), '/login', 'signed-out unknown page redirects to login');
}
$_SERVER['REQUEST_URI'] = base_path() . '/track/not-a-token';
ob_start();
router_dispatch('GET', '/track/not-a-token');
$publicTrack = (string) ob_get_clean();
check(http_response_code() === 404 && str_contains($publicTrack, 'Page not found'), 'a signed-out tracking link shows Page not found');

$_SERVER['REQUEST_URI'] = base_path() . '/shop/missing-public-shop';
http_response_code(200);
try {
    ob_start();
    router_dispatch('GET', '/shop/missing-public-shop');
    $publicShop = (string) ob_get_clean();
    check(http_response_code() === 404 && str_contains($publicShop, 'Page not found'), 'signed-out unknown storefront shows Page not found');
} catch (HttpException $e) {
    ob_end_clean();
    check_eq($e->status(), 404, 'signed-out unknown storefront returns 404');
    check(str_contains($e->getMessage(), t('not_found')) || $e->getMessage() !== '', 'signed-out unknown storefront 404 has a message');
}
