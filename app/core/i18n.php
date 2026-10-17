<?php

declare(strict_types=1);

function current_lang(): string
{
    $override = $GLOBALS['gmingo_lang_override'] ?? null;
    if (is_string($override) && in_array($override, ['en', 'ha'], true)) {
        return $override;
    }
    $code = $_COOKIE['lang'] ?? 'en';
    if (!is_string($code) || !in_array($code, ['en', 'ha'], true)) {
        return 'en';
    }
    return $code;
}

function i18n_catalog(): array
{
    static $catalogs = [];
    $code = current_lang();
    if (isset($catalogs[$code])) {
        return $catalogs[$code];
    }
    $root = dirname(__DIR__, 2) . '/lang';
    $english = require $root . '/en.php';
    $chosen = $english;
    if ($code !== 'en') {
        $file = $root . '/' . $code . '.php';
        if (is_file($file)) {
            $loaded = require $file;
            if (is_array($loaded)) {
                $chosen = array_merge($english, $loaded);
            }
        }
    }
    $catalogs[$code] = $chosen;
    return $chosen;
}

function t(string $key, array $vars = []): string
{
    $catalog = i18n_catalog();
    $text = $catalog[$key] ?? $key;
    foreach ($vars as $name => $value) {
        $text = str_replace('{' . $name . '}', (string) $value, $text);
    }
    return $text;
}

function lang_set(string $code): void
{
    if (!in_array($code, ['en', 'ha'], true)) {
        $code = 'en';
    }
    setcookie('lang', $code, [
        'expires' => time() + 60 * 60 * 24 * 365,
        'path' => base_path() . '/',
        'httponly' => false,
        'samesite' => 'Lax',
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    ]);
    $_COOKIE['lang'] = $code;
}
