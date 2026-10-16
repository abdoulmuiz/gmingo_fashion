<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/domain/accounts.php';

function safe_back(string $path): string
{
    if ($path === '' || preg_match('/[\x00-\x1F\x7F]/', $path)) {
        return '/';
    }
    $decoded = $path;
    for ($i = 0; $i < 3; $i++) {
        $next = rawurldecode($decoded);
        if ($next === $decoded) {
            break;
        }
        $decoded = $next;
    }
    if (preg_match('/[\x00-\x1F\x7F\\\\]/', $decoded)) {
        return '/';
    }
    if (preg_match('#^[a-z][a-z0-9+.-]*:#i', $decoded) || str_contains($decoded, '://')) {
        return '/';
    }
    if ($decoded === '' || $decoded[0] !== '/' || str_contains($decoded, '//')) {
        return '/';
    }
    $base = base_path();
    if ($decoded === $base || str_starts_with($decoded, $base . '/')) {
        $decoded = substr($decoded, strlen($base));
        if ($decoded === '') {
            $decoded = '/';
        }
    }
    if ($decoded === '' || $decoded[0] !== '/' || str_contains($decoded, '//')) {
        return '/';
    }
    if (str_contains($decoded, '#')) {
        $cut = strstr($decoded, '#', true);
        $decoded = is_string($cut) ? $cut : '';
        if ($decoded === '') {
            return '/';
        }
    }
    $query = '';
    $qpos = strpos($decoded, '?');
    if ($qpos !== false) {
        $query = substr($decoded, $qpos + 1);
        $decoded = substr($decoded, 0, $qpos);
        if (preg_match('/[\x00-\x1F\x7F\\\\]/', $query) || str_contains($query, '://') || str_contains($query, '//')) {
            return '/';
        }
    }
    if ($decoded === '/') {
        return $query === '' ? '/' : '/?' . $query;
    }
    $segments = explode('/', $decoded);
    array_shift($segments);
    if ($segments !== [] && $segments[count($segments) - 1] === '') {
        array_pop($segments);
    }
    if ($segments === []) {
        return '/';
    }
    $blocked = [];
    $root = dirname(__DIR__, 3);
    foreach (scandir($root) ?: [] as $name) {
        if ($name === '.' || $name === '..') {
            continue;
        }
        if (is_dir($root . '/' . $name)) {
            $blocked[] = $name;
        }
    }
    if (in_array($segments[0], $blocked, true)) {
        return '/';
    }
    foreach ($segments as $segment) {
        if ($segment === '' || $segment === '.' || $segment === '..') {
            return '/';
        }
    }
    $safe = '/' . implode('/', $segments);
    if ($query !== '') {
        $safe .= '?' . $query;
    }
    return $safe;
}

function login_form(array $errors = []): void
{
    if (current_user_id() !== null) {
        redirect('/');
    }
    render('login', [
        'title' => t('login'),
        'errors' => $errors,
        'email' => '',
    ]);
}

function login_submit(): void
{
    csrf_check();
    if (current_user_id() !== null) {
        redirect('/');
    }
    $email = (string) ($_POST['email'] ?? '');
    $error = login_user($email, (string) ($_POST['password'] ?? ''));
    if ($error === null) {
        redirect('/');
    }
    render('login', [
        'title' => t('login'),
        'errors' => ['form' => $error],
        'email' => $email,
    ]);
}

function register_form(array $errors = [], array $old = []): void
{
    if (current_user_id() !== null) {
        redirect('/');
    }
    render('register', [
        'title' => t('register'),
        'errors' => $errors,
        'old' => $old,
    ]);
}

function register_submit(): void
{
    csrf_check();
    $input = [
        'business_name' => (string) ($_POST['business_name'] ?? ''),
        'owner_name' => (string) ($_POST['owner_name'] ?? ''),
        'email' => (string) ($_POST['email'] ?? ''),
        'password' => (string) ($_POST['password'] ?? ''),
        'phone' => (string) ($_POST['phone'] ?? ''),
        'company_type' => (string) ($_POST['company_type'] ?? ''),
    ];
    try {
        $result = business_register($input);
    } catch (Throwable $e) {
        $result = ['errors' => ['form' => 'save_failed']];
    }
    if (isset($result['errors'])) {
        unset($input['password']);
        register_form($result['errors'], $input);
        return;
    }
    redirect('/');
}

function logout_submit(): void
{
    csrf_check();
    auth_logout();
    redirect('/login');
}

function lang_submit(): void
{
    csrf_check();
    $lang = (string) ($_POST['lang'] ?? 'en');
    if (!in_array($lang, ['en', 'ha'], true)) {
        $lang = 'en';
    }
    lang_set($lang);
    $userId = current_user_id();
    if ($userId !== null) {
        db_update('fashion_users', ['lang' => $lang], ['id' => $userId]);
    }
    redirect(safe_back((string) ($_POST['back'] ?? '/')));
}
