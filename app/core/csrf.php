<?php

declare(strict_types=1);

function csrf_token(): string
{
    $current = $_COOKIE['csrf_token'] ?? '';
    if (is_string($current) && preg_match('/^[a-f0-9]{64}$/', $current)) {
        return $current;
    }
    $token = bin2hex(random_bytes(32));
    setcookie('csrf_token', $token, [
        'expires' => 0,
        'path' => base_path() . '/',
        'httponly' => true,
        'samesite' => 'Lax',
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    ]);
    $_COOKIE['csrf_token'] = $token;
    return $token;
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

function csrf_check(): void
{
    $sent = $_POST['csrf'] ?? null;
    $cookie = $_COOKIE['csrf_token'] ?? null;
    $sentOk = is_string($sent) && preg_match('/^[a-f0-9]{64}$/', $sent) === 1;
    $cookieOk = is_string($cookie) && preg_match('/^[a-f0-9]{64}$/', $cookie) === 1;
    if ($sentOk && $cookieOk && hash_equals($cookie, $sent)) {
        return;
    }
    if (!$sentOk) {
        $reason = 'missing';
    } elseif (!$cookieOk) {
        $reason = 'no_cookie';
    } else {
        $reason = 'mismatch';
    }
    security_event('csrf_failed_' . $reason);
    throw new HttpException(t('csrf_failed'), 403);
}
