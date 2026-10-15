<?php

declare(strict_types=1);

function app_config(): array
{
    static $cfg = null;
    if ($cfg !== null) {
        return $cfg;
    }
    $file = dirname(__DIR__, 2) . '/config/config.php';
    if (!is_file($file)) {
        $cfg = [
            'base_path' => '/gmingo_fashion',
            'db' => [],
        ];
        return $cfg;
    }
    $loaded = require $file;
    $cfg = is_array($loaded) ? $loaded : [];
    return $cfg;
}

function base_path(): string
{
    $base = (string) (app_config()['base_path'] ?? '/gmingo_fashion');
    if ($base === '' || $base[0] !== '/') {
        $base = '/gmingo_fashion';
    }
    $base = rtrim($base, '/');
    return $base === '' ? '/gmingo_fashion' : $base;
}

function url(string $path): string
{
    if (preg_match('#^https?://#i', $path) || str_starts_with($path, '//')) {
        throw new RuntimeException('Links must use the base path, not a full domain.');
    }
    if (preg_match('/[\r\n]/', $path)) {
        throw new RuntimeException('Invalid link.');
    }
    $path = '/' . ltrim($path, '/');
    if ($path === '/') {
        return base_path() . '/';
    }
    return base_path() . $path;
}

function redirect(string $path): void
{
    header('Location: ' . url($path));
    exit;
}

function request_path(): string
{
    $uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
    if (!is_string($uri) || $uri === '') {
        $uri = '/';
    }
    $base = base_path();
    if (str_starts_with($uri, $base)) {
        $uri = substr($uri, strlen($base));
        if ($uri === '') {
            $uri = '/';
        }
    }
    if (str_starts_with($uri, '/public')) {
        $uri = substr($uri, strlen('/public'));
        if ($uri === '') {
            $uri = '/';
        }
    }
    $uri = '/' . trim($uri, '/');
    return $uri === '/' ? '/' : $uri;
}

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function uuid(): string
{
    $bytes = random_bytes(16);
    $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
    $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
    $hex = bin2hex($bytes);
    return sprintf(
        '%s-%s-%s-%s-%s',
        substr($hex, 0, 8),
        substr($hex, 8, 4),
        substr($hex, 12, 4),
        substr($hex, 16, 4),
        substr($hex, 20, 12)
    );
}

function is_uuid(string $value): bool
{
    return (bool) preg_match(
        '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i',
        $value
    );
}

function money(int $kobo): string
{
    $negative = $kobo < 0;
    $kobo = abs($kobo);
    $naira = intdiv($kobo, 100);
    $rest = $kobo % 100;
    $digits = (string) $naira;
    $grouped = '';
    while (strlen($digits) > 3) {
        $grouped = ',' . substr($digits, -3) . $grouped;
        $digits = substr($digits, 0, -3);
    }
    $grouped = $digits . $grouped;
    return ($negative ? '-' : '') . '₦' . $grouped . '.' . str_pad((string) $rest, 2, '0', STR_PAD_LEFT);
}

function kobo_from_input(string $raw): ?int
{
    $raw = trim($raw);
    if (!preg_match('/^\d+(\.\d{1,2})?$/', $raw)) {
        return null;
    }
    $parts = explode('.', $raw, 2);
    $naira = $parts[0];
    $fraction = str_pad(substr($parts[1] ?? '0', 0, 2), 2, '0');
    if (strlen($naira) > 15) {
        return null;
    }
    return ((int) $naira * 100) + (int) $fraction;
}

function phone_normalize(string $raw): ?string
{
    $digits = preg_replace('/\D+/', '', $raw);
    if (!is_string($digits)) {
        return null;
    }
    if (str_starts_with($digits, '234') && strlen($digits) === 13) {
        $digits = '0' . substr($digits, 3);
    }
    if (strlen($digits) === 10 && preg_match('/^[789]/', $digits)) {
        $digits = '0' . $digits;
    }
    if (!preg_match('/^0[789][01]\d{8}$/', $digits)) {
        return null;
    }
    return $digits;
}

function phone_intl(string $local): string
{
    return '234' . substr($local, 1);
}

function render(string $view, array $data = []): void
{
    if (!preg_match('#^[a-z0-9_/]+$#', $view)) {
        throw new RuntimeException('Invalid view.');
    }
    $file = dirname(__DIR__) . '/views/' . $view . '.php';
    if (!is_file($file)) {
        throw new RuntimeException('Missing view.');
    }
    $data['view_file'] = $file;
    if (!isset($data['title'])) {
        $data['title'] = t('app_name');
    }
    extract($data, EXTR_SKIP);
    header('Content-Type: text/html; charset=utf-8');
    require dirname(__DIR__) . '/views/layout.php';
}

function nav_active(string $path): string
{
    $current = request_path();
    if ($path === '/') {
        return $current === '/' ? 'active' : '';
    }
    return str_starts_with($current, $path) ? 'active' : '';
}

function client_ip(): string
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'cli';
    return is_string($ip) && $ip !== '' ? $ip : 'cli';
}
