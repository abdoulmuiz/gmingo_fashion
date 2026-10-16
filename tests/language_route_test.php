<?php

declare(strict_types=1);

if (!defined('GMINGO_TESTING')) {
    require __DIR__ . '/bootstrap.php';
}

$root = dirname(__DIR__);
$folders = [];
foreach (scandir($root) ?: [] as $name) {
    if ($name === '.' || $name === '..') {
        continue;
    }
    if (is_dir($root . '/' . $name)) {
        $folders[] = $name;
    }
}
check(in_array('lang', $folders, true), 'lang is a real top-level folder');
check(in_array('config', $folders, true), 'config is a real top-level folder');
check(in_array('docs', $folders, true), 'docs is a real top-level folder');
check(in_array('tools', $folders, true), 'tools is a real top-level folder');

$source = (string) file_get_contents($root . '/app/routes.php');
preg_match_all('/route\(\s*[\'"]([A-Z]+)[\'"]\s*,\s*[\'"]([^\'"]+)[\'"]/', $source, $declared, PREG_SET_ORDER);
check($declared !== [], 'app/routes.php declares routes');
foreach ($declared as $declaredRoute) {
    $method = $declaredRoute[1];
    $pattern = $declaredRoute[2];
    if ($pattern === '/' || $pattern === '') {
        continue;
    }
    $first = explode('/', ltrim(explode('?', $pattern, 2)[0], '/'))[0] ?? '';
    check(
        $first !== '' && !in_array($first, $folders, true),
        $method . ' ' . $pattern . ' must not start with a real folder'
    );
}
foreach ($GLOBALS['gmingo_routes'] as $liveRoute) {
    $method = (string) $liveRoute[0];
    $pattern = (string) $liveRoute[1];
    if ($pattern === '/' || $pattern === '') {
        continue;
    }
    $first = explode('/', ltrim(explode('?', $pattern, 2)[0], '/'))[0] ?? '';
    check(
        $first !== '' && !in_array($first, $folders, true),
        'live ' . $method . ' ' . $pattern . ' must not start with a real folder'
    );
}
check(route_defined('POST', '/language'), 'language is changed with POST /language');
check(!route_defined('POST', '/lang'), 'POST /lang is not a route');
check(!route_defined('GET', '/lang'), 'GET /lang is not a route');
check(!route_defined('GET', '/language'), 'there is no GET language page');

$htaccess = (string) file_get_contents($root . '/.htaccess');
check(
    str_contains($htaccess, 'RewriteRule ^(config|app|database|tools|tests|storage|lang|docs|\.git)(/|$) - [F,L]'),
    '.htaccess still blocks lang, config, tools, docs and .git'
);
foreach (['lang', 'config', 'tools', 'docs'] as $blockedDir) {
    $deny = (string) file_get_contents($root . '/' . $blockedDir . '/.htaccess');
    check(str_contains($deny, 'Require all denied'), $blockedDir . '/.htaccess still denies access');
}

auth_logout();
$_COOKIE['lang'] = 'en';
$_SERVER['REQUEST_URI'] = base_path() . '/login';
ob_start();
router_dispatch('GET', '/login');
$login = (string) ob_get_clean();
$languageAction = e(url('/language'));
check(str_contains($login, 'method="post" action="' . $languageAction . '"'), 'login language form posts to /language');
check(preg_match('#action="[^"]*/lang"#', $login) !== 1, 'login does not post to /lang');
check(str_contains($login, 'name="csrf"'), 'login language form includes a CSRF token');
check(str_contains($login, 'name="back" value="/login"'), 'login language form returns to this page');
check(preg_match('#<a[^>]+href="[^"]*/language#', $login) !== 1, 'language is not changed by a link');

$_SERVER['REQUEST_URI'] = base_path() . '/more';
ob_start();
render('more', ['title' => t('more')]);
$more = (string) ob_get_clean();
check(str_contains($more, 'method="post" action="' . $languageAction . '"'), 'More language form posts to /language');
check(str_contains($more, 'name="csrf"'), 'More language form includes a CSRF token');
check(str_contains($more, 'name="back" value="/more"'), 'More language form returns to this page');

check_eq(safe_back('/more'), '/more', 'a page inside the app is accepted');
check_eq(safe_back('/login'), '/login', 'login is an in-app return path');
check_eq(safe_back(base_path() . '/orders'), '/orders', 'a return path may start with the base path');
check_eq(safe_back('https://evil.example/phish'), '/', 'an outside address is rejected');
check_eq(safe_back('http://evil.example'), '/', 'an http address is rejected');
check_eq(safe_back('//evil.example'), '/', 'a protocol-relative URL is rejected');
check_eq(safe_back('/\\evil.example'), '/', 'a backslash URL is rejected');
check_eq(safe_back('/%2f%2fevil.example'), '/', 'an encoded protocol-relative URL is rejected');
check_eq(safe_back('/%2e%2e/config/config.php'), '/', 'an encoded climb into config is rejected');
check_eq(safe_back('/lang'), '/', 'a return path whose first segment is lang is rejected');
check_eq(safe_back('/lang/en.php'), '/', 'a return path into lang is rejected');
check_eq(safe_back('/config/config.php'), '/', 'a return path into config is rejected');
check_eq(safe_back('/docs/REQUIREMENTS.md'), '/', 'a return path into docs is rejected');
check_eq(safe_back('/tools/seed.php'), '/', 'a return path into tools is rejected');
check_eq(safe_back('/.git/config'), '/', 'a return path into .git is rejected');
check_eq(safe_back('/orders/../../config/config.php'), '/', 'a return path cannot climb into a real folder');

$token = csrf_token();
$_POST = ['csrf' => $token, 'lang' => 'en', 'back' => 'https://evil.example'];
try {
    lang_submit();
    check(false, 'language switch redirects instead of following an open redirect');
} catch (RedirectException $e) {
    check_eq($e->path(), '/', 'language switch rejects an open redirect');
}
$_POST = ['csrf' => $token, 'lang' => 'ha', 'back' => '/login'];
try {
    lang_submit();
    check(false, 'language switch redirects back to the current page');
} catch (RedirectException $e) {
    check_eq($e->path(), '/login', 'language switch returns to the page the person was on');
}
check_eq($_COOKIE['lang'] ?? '', 'ha', 'language switch saves Hausa');
$_COOKIE['lang'] = 'en';
$_POST = [];
