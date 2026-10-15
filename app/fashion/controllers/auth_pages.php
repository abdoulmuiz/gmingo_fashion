<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/domain/accounts.php';

function safe_back(string $path): string
{
    if ($path === '' || $path[0] !== '/' || str_starts_with($path, '//') || preg_match('/[\r\n]/', $path)) {
        return '/';
    }
    return $path;
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
