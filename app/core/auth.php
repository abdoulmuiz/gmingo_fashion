<?php

declare(strict_types=1);

/**
 * Session access stays in this file.
 * Other code may call only current_user_id(), current_tenant_id() and require_login()
 * to learn who is signed in. Login and logout write the session; they do not expose it.
 */

function auth_boot(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    $dir = dirname(__DIR__, 2) . '/storage/sessions';
    if (!is_dir($dir)) {
        mkdir($dir, 0770, true);
    }
    session_save_path($dir);
    $secure = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    session_name('gmingo_fashion');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => base_path() . '/',
        'secure' => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.cookie_httponly', '1');
    session_start();
}

function current_user_id(): ?string
{
    auth_boot();
    $id = $_SESSION['user_id'] ?? null;
    return is_string($id) && is_uuid($id) ? $id : null;
}

function current_tenant_id(): ?string
{
    auth_boot();
    $id = $_SESSION['tenant_id'] ?? null;
    return is_string($id) && is_uuid($id) ? $id : null;
}

function require_login(): void
{
    if (current_user_id() === null || current_tenant_id() === null) {
        redirect('/login');
    }
}

function auth_login(string $userId, string $tenantId): void
{
    if (!is_uuid($userId) || !is_uuid($tenantId)) {
        throw new RuntimeException('Invalid session identity.');
    }
    auth_boot();
    session_regenerate_id(true);
    $_SESSION = [
        'user_id' => $userId,
        'tenant_id' => $tenantId,
    ];
}

function auth_logout(): void
{
    auth_boot();
    $_SESSION = [];
    $params = session_get_cookie_params();
    setcookie(session_name(), '', [
        'expires' => time() - 42000,
        'path' => $params['path'],
        'domain' => $params['domain'],
        'secure' => (bool) $params['secure'],
        'httponly' => (bool) $params['httponly'],
        'samesite' => 'Lax',
    ]);
    session_destroy();
}

function auth_find_user_by_email(string $email): ?array
{
    $statement = db()->prepare(
        'SELECT id, tenant_id, name, email, password_hash, lang, active FROM fashion_users WHERE email = :email LIMIT 1'
    );
    $statement->execute(['email' => $email]);
    $row = $statement->fetch();
    return is_array($row) ? $row : null;
}

function auth_rate_path(string $email): string
{
    $dir = dirname(__DIR__, 2) . '/storage/ratelimit';
    if (!is_dir($dir)) {
        mkdir($dir, 0770, true);
    }
    $key = hash('sha256', strtolower($email) . '|' . client_ip());
    return $dir . '/' . $key . '.json';
}

function auth_recent_attempts(string $email): array
{
    $path = auth_rate_path($email);
    if (!is_file($path)) {
        return [];
    }
    $decoded = json_decode((string) file_get_contents($path), true);
    if (!is_array($decoded)) {
        return [];
    }
    $cutoff = time() - 900;
    $recent = [];
    foreach ($decoded as $stamp) {
        if (is_int($stamp) && $stamp >= $cutoff) {
            $recent[] = $stamp;
        }
    }
    return $recent;
}

function auth_too_many_attempts(string $email): bool
{
    return count(auth_recent_attempts($email)) >= 5;
}

function auth_register_failure(string $email): void
{
    $recent = auth_recent_attempts($email);
    $recent[] = time();
    file_put_contents(auth_rate_path($email), json_encode($recent));
}

function auth_clear_attempts(string $email): void
{
    $path = auth_rate_path($email);
    if (is_file($path)) {
        unlink($path);
    }
}
