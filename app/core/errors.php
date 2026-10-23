<?php

declare(strict_types=1);

function errors_hide_details(): void
{
    ini_set('display_errors', '0');
    ini_set('display_startup_errors', '0');
    ini_set('html_errors', '0');
}

function errors_install(): void
{
    errors_hide_details();
    set_exception_handler('errors_uncaught');
    set_error_handler('errors_php');
    register_shutdown_function('errors_shutdown');
}

function errors_uncaught(Throwable $e): void
{
    try {
        if ($e instanceof RedirectException) {
            return;
        }
        if ($e instanceof HttpException) {
            errors_document($e->status(), $e->getMessage());
            return;
        }
        errors_log_throwable($e);
        errors_document(500, errors_generic_message());
    } catch (Throwable $ignored) {
        errors_document(500, 'Something went wrong. Please try again.');
    }
}

function errors_php(int $severity, string $message, string $file, int $line): bool
{
    if ((error_reporting() & $severity) === 0) {
        return false;
    }
    errors_append('error.log', gmdate('c') . ' php ' . (string) $severity . ' ' . $message . ' in ' . $file . ':' . (string) $line);
    return true;
}

function errors_shutdown(): void
{
    $error = error_get_last();
    if ($error === null) {
        return;
    }
    if (!in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR], true)) {
        return;
    }
    if (!empty($GLOBALS['gmingo_error_page_sent'])) {
        return;
    }
    $message = (string) ($error['message'] ?? '');
    $file = (string) ($error['file'] ?? '');
    $line = (string) ($error['line'] ?? '');
    errors_append('error.log', gmdate('c') . ' fatal ' . $message . ' in ' . $file . ':' . $line);
    if (PHP_SAPI === 'cli' || headers_sent()) {
        return;
    }
    errors_document(500, errors_generic_message());
}

function errors_generic_message(): string
{
    if (!function_exists('t')) {
        return 'Something went wrong. Please try again.';
    }
    $text = t('error_generic');
    if ($text === '' || $text === 'error_generic') {
        return 'Something went wrong. Please try again.';
    }
    return $text;
}

function errors_document(int $status, string $message): void
{
    if ($status < 400 || $status > 599) {
        $status = 500;
    }
    if (!headers_sent()) {
        http_response_code($status);
        header('Content-Type: text/html; charset=utf-8');
    }
    $GLOBALS['gmingo_error_page_sent'] = true;
    $lang = function_exists('current_lang') ? current_lang() : 'en';
    echo '<!doctype html><html lang="' . e($lang) . '"><head><meta charset="utf-8">';
    echo '<meta name="viewport" content="width=device-width, initial-scale=1">';
    echo '<title>' . e($message) . '</title></head><body><p>' . e($message) . '</p></body></html>';
}

function errors_log_throwable(Throwable $e): void
{
    $text = $e::class . ' ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . (string) $e->getLine();
    $text .= "\n" . $e->getTraceAsString();
    errors_append('error.log', gmdate('c') . "\n" . $text);
}

function security_event(string $code): void
{
    if (preg_match('/^[a-z_]+$/', $code) !== 1) {
        return;
    }
    $method = $_SERVER['REQUEST_METHOD'] ?? 'CLI';
    if (!is_string($method)) {
        $method = 'CLI';
    }
    $method = strtoupper($method);
    if (preg_match('/^[A-Z]{3,7}$/', $method) !== 1) {
        $method = 'POST';
    }
    $path = function_exists('request_path') ? request_path() : '/';
    $ip = function_exists('client_ip') ? client_ip() : 'cli';
    $line = gmdate('c') . ' ' . $code . ' ' . $method . ' ' . $path . ' ip=' . $ip;
    errors_append('security.log', $line);
}

function errors_append(string $name, string $line): void
{
    if (preg_match('/^[a-z]+\.log$/', $name) !== 1) {
        return;
    }
    $dir = dirname(__DIR__, 2) . '/storage/logs';
    if (!is_dir($dir) && !mkdir($dir, 0770, true) && !is_dir($dir)) {
        return;
    }
    $posted = $_POST['csrf'] ?? null;
    $cookie = $_COOKIE['csrf_token'] ?? null;
    if (is_string($posted) && strlen($posted) >= 8) {
        $line = str_replace($posted, '[redacted]', $line);
    }
    if (is_string($cookie) && strlen($cookie) >= 8) {
        $line = str_replace($cookie, '[redacted]', $line);
    }
    $line = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $line) ?? '';
    $path = $dir . '/' . $name;
    if (!is_file($path)) {
        touch($path);
        @chmod($path, 0666);
    }
    file_put_contents($path, $line . "\n", FILE_APPEND | LOCK_EX);
}
