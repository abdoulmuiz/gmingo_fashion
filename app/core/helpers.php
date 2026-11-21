<?php

declare(strict_types=1);

function app_config(): array
{
    if (isset($GLOBALS['gmingo_app_config']) && is_array($GLOBALS['gmingo_app_config'])) {
        return $GLOBALS['gmingo_app_config'];
    }
    $file = dirname(__DIR__, 2) . '/config/config.php';
    if (!is_file($file)) {
        $GLOBALS['gmingo_app_config'] = [
            'base_path' => '/gmingo_fashion',
            'db' => [],
        ];
        return $GLOBALS['gmingo_app_config'];
    }
    $loaded = require $file;
    $GLOBALS['gmingo_app_config'] = is_array($loaded) ? $loaded : [];
    return $GLOBALS['gmingo_app_config'];
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
    throw new RedirectException($path);
}

function redirect_external(string $absoluteUrl): void
{
    if (preg_match('#^https://[A-Za-z0-9._~:/?#\[\]@!$&\'()*+,;=%-]+$#', $absoluteUrl) !== 1) {
        throw new RuntimeException('Invalid external redirect.');
    }
    header('Location: ' . $absoluteUrl);
    throw new RedirectException($absoluteUrl);
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

function posted_id_list(string $raw): array
{
    $ids = [];
    foreach (explode(',', $raw) as $part) {
        $part = trim($part);
        if (is_uuid($part) && !in_array($part, $ids, true)) {
            $ids[] = $part;
        }
    }
    return $ids;
}

function is_uuid(string $value): bool
{
    return (bool) preg_match(
        '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i',
        $value
    );
}

function format_naira(int $kobo): string
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

function money(int $kobo): string
{
    return format_naira($kobo);
}

function flash_set(string $key, string $message): void
{
    if (!preg_match('/^[a-z0-9_]{1,32}$/', $key)) {
        throw new RuntimeException('Invalid flash key.');
    }
    $all = flash_read();
    $all[$key] = $message;
    flash_write($all);
}

function flash_get(string $key): ?string
{
    if (!preg_match('/^[a-z0-9_]{1,32}$/', $key)) {
        return null;
    }
    $all = flash_read();
    if (!array_key_exists($key, $all)) {
        return null;
    }
    $message = $all[$key];
    unset($all[$key]);
    flash_write($all);
    return $message;
}

function flash_read(): array
{
    $raw = $_COOKIE['flash'] ?? '';
    if (!is_string($raw) || $raw === '') {
        return [];
    }
    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        return [];
    }
    $clean = [];
    foreach ($decoded as $name => $value) {
        if (!is_string($name) || !is_string($value) || !preg_match('/^[a-z0-9_]{1,32}$/', $name)) {
            continue;
        }
        $clean[$name] = $value;
    }
    return $clean;
}

function flash_write(array $messages): void
{
    $payload = json_encode($messages, JSON_UNESCAPED_UNICODE);
    if (!is_string($payload)) {
        $payload = '{}';
    }
    setcookie('flash', $payload, [
        'expires' => 0,
        'path' => base_path() . '/',
        'httponly' => true,
        'samesite' => 'Lax',
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    ]);
    $_COOKIE['flash'] = $payload;
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

function tel_href(string $local): string
{
    return 'tel:+' . phone_intl($local);
}

function whatsapp_href(string $local): string
{
    return 'https://wa.me/' . phone_intl($local);
}

function whatsapp_share_href(string $text): string
{
    return 'https://wa.me/?text=' . rawurlencode($text);
}

function like_contains(string $value): string
{
    $value = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    return '%' . $value . '%';
}

function render(string $view, array $data = []): void
{
    if (!preg_match('#^[a-z0-9_/]+$#', $view)) {
        throw new RuntimeException('Invalid view.');
    }
    $file = dirname(__DIR__) . '/fashion/views/' . $view . '.php';
    if (!is_file($file)) {
        throw new RuntimeException('Missing view.');
    }
    $data['view_file'] = $file;
    if (!isset($data['title'])) {
        $data['title'] = t('app_name');
    }
    extract($data, EXTR_SKIP);
    header('Content-Type: text/html; charset=utf-8');
    require dirname(__DIR__) . '/fashion/views/layout.php';
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

class RedirectException extends RuntimeException
{
    public function __construct(private string $path)
    {
        parent::__construct('Redirect');
    }

    public function path(): string
    {
        return $this->path;
    }
}

class HttpException extends RuntimeException
{
    public function __construct(string $message, private int $status = 500)
    {
        parent::__construct($message);
    }

    public function status(): int
    {
        return $this->status;
    }
}
