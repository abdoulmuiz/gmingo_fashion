<?php

declare(strict_types=1);

check_eq(e('<script>'), '&lt;script&gt;', 'e() escapes tags');
check_eq(e('"a\'b'), '&quot;a&#039;b', 'e() escapes quotes');
check_eq(t('home'), 'Home', 'English home label');

$_COOKIE['lang'] = 'ha';
// The catalog is cached for the process, so Hausa is checked from the file.
$hausa = require dirname(__DIR__) . '/lang/ha.php';
$english = require dirname(__DIR__) . '/lang/en.php';
check_eq($hausa['home'], 'Gida', 'Hausa home label');
check(isset($english['orders'], $hausa['orders']), 'both languages have orders');
foreach ($english as $key => $value) {
    check(array_key_exists($key, $hausa), 'Hausa has key ' . $key);
}

check_eq(url('/orders'), base_path() . '/orders', 'url() prefixes the base path');
check_eq(url('/'), base_path() . '/', 'home url uses the base path');
check_throws(fn () => url('http://example.com/orders'), 'full domains are rejected');
check_throws(fn () => url("//example.com"), 'protocol-relative links are rejected');

$_SERVER['REQUEST_URI'] = base_path() . '/orders?q=1';
check_eq(request_path(), '/orders', 'request path strips the base path and query');

ob_start();
router_dispatch('GET', '/orders');
$html = (string) ob_get_clean();
check(str_contains($html, t('orders')) || str_contains($html, 'Orders') || str_contains($html, 'Oda'), 'orders route renders');
check(str_contains($html, 'bottom-nav'), 'layout includes bottom navigation');
check(str_contains($html, t('home')) || str_contains($html, 'Home') || str_contains($html, 'Gida'), 'nav has Home');
check(str_contains($html, 'Board') || str_contains($html, 'Allo'), 'nav has Board');
check(str_contains($html, 'Customers') || str_contains($html, 'Abokan ciniki'), 'nav has Customers');
check(str_contains($html, 'More') || str_contains($html, 'Kara'), 'nav has More');

ob_start();
router_dispatch('GET', '/missing');
$missing = (string) ob_get_clean();
check(http_response_code() === 404, 'unknown path is 404');
check(str_contains($missing, 'bottom-nav'), '404 still has navigation');

$ht = (string) file_get_contents(dirname(__DIR__) . '/.htaccess');
foreach (['config', 'app', 'database', 'tools', 'tests', 'storage', 'lang', 'docs', '\\.git', 'public/'] as $needle) {
    check(str_contains($ht, $needle), '.htaccess covers ' . $needle);
}
foreach (['config', 'app', 'database', 'tools', 'tests', 'storage', 'lang', 'docs'] as $dir) {
    $deny = dirname(__DIR__) . '/' . $dir . '/.htaccess';
    check(is_file($deny) && str_contains((string) file_get_contents($deny), 'Require all denied'), $dir . ' is denied');
}

check(function_exists('current_user_id'), 'current_user_id exists');
check(function_exists('current_tenant_id'), 'current_tenant_id exists');
check(function_exists('require_login'), 'require_login exists');
check(current_user_id() === null, 'nobody is signed in yet');

$app = (string) file_get_contents(dirname(__DIR__) . '/app/core/helpers.php');
$routes = (string) file_get_contents(dirname(__DIR__) . '/app/routes.php');
$views = (string) file_get_contents(dirname(__DIR__) . '/app/views/layout.php');
check(!str_contains($app . $routes . $views, '$_SESSION'), 'skeleton files do not read the session');

check_eq(money(150050), '₦1,500.50', 'money formats kobo without floats');
check_eq(money(-100), '-₦1.00', 'negative money stays in whole kobo');
check_eq(kobo_from_input('15000.50'), 1500050, 'naira input becomes kobo');
check_eq(kobo_from_input('10'), 1000, 'whole naira becomes kobo');
check(kobo_from_input('10.555') === null, 'too many decimal places are rejected');
check_eq(phone_normalize('+2348031234567'), '08031234567', 'international Nigerian numbers normalize');
check_eq(phone_intl('08031234567'), '2348031234567', 'WhatsApp uses 234 format');
check(phone_normalize('12345') === null, 'short numbers are rejected');
