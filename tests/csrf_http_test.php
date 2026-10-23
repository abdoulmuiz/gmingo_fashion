<?php

declare(strict_types=1);

if (!defined('GMINGO_TESTING')) {
    require __DIR__ . '/bootstrap.php';
}

$configured = defined('GMINGO_DB_NAME') ? (string) GMINGO_DB_NAME : (string) (app_config()['db']['name'] ?? '');
if (!str_ends_with($configured, '_test')) {
    fwrite(STDERR, "Refusing to run CSRF tests: database name must end with _test.\n");
    exit(1);
}

function csrf_http_live_pdo(): PDO
{
    $config = app_config();
    $db = $config['db'] ?? null;
    if (!is_array($db)) {
        throw new RuntimeException('Database settings are missing.');
    }
    $name = (string) ($db['name'] ?? '');
    if (!preg_match('/^[A-Za-z0-9_]+$/', $name)) {
        throw new RuntimeException('Database name is not safe.');
    }
    $dsn = 'mysql:host=' . (string) $db['host'] . ';port=' . (int) $db['port'] . ';dbname=' . $name . ';charset=utf8mb4';
    $password = $db['password'] ?? $db['pass'] ?? '';
    $pdo = new PDO($dsn, (string) $db['user'], (string) $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    $pdo->exec('SET SESSION TRANSACTION READ ONLY');
    return $pdo;
}

function csrf_http_tables(PDO $pdo): array
{
    $tables = [];
    foreach ($pdo->query("SELECT table_name FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name LIKE 'fashion\\_%'")->fetchAll(PDO::FETCH_COLUMN) as $name) {
        if (is_string($name) && preg_match('/^fashion_[a-z0-9_]+$/', $name) === 1) {
            $tables[] = $name;
        }
    }
    sort($tables);
    return $tables;
}

function csrf_http_marker_found(PDO $pdo, string $marker): ?string
{
    if (!preg_match('/^[0-9a-zA-Z ._-]{8,80}$/', $marker)) {
        throw new RuntimeException('Unsafe marker.');
    }
    foreach (csrf_http_tables($pdo) as $table) {
        $columns = $pdo->query('SELECT column_name, data_type FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ' . $pdo->quote($table))->fetchAll(PDO::FETCH_ASSOC);
        $parts = [];
        $params = [];
        foreach ($columns as $index => $column) {
            $name = (string) ($column['column_name'] ?? '');
            $type = strtolower((string) ($column['data_type'] ?? ''));
            if ($name === '' || preg_match('/^[a-z0-9_]+$/', $name) !== 1) {
                continue;
            }
            if (!in_array($type, ['char', 'varchar', 'text', 'mediumtext', 'longtext', 'tinytext'], true)) {
                continue;
            }
            $key = 'm' . (string) $index;
            $parts[] = '`' . $name . '` LIKE :' . $key;
            $params[$key] = '%' . $marker . '%';
        }
        if ($parts === []) {
            continue;
        }
        $found = $pdo->prepare('SELECT 1 FROM `' . $table . '` WHERE ' . implode(' OR ', $parts) . ' LIMIT 1');
        $found->execute($params);
        if ($found->fetch() !== false) {
            return $table;
        }
    }
    return null;
}

function csrf_http_paths(string $id, string $token): array
{
    $paths = [];
    foreach ($GLOBALS['gmingo_routes'] as $route) {
        if ($route[0] !== 'POST') {
            continue;
        }
        $path = str_replace('{token}', $token, (string) $route[1]);
        $path = preg_replace('/\{[a-z_]+\}/', $id, $path);
        if (is_string($path) && $path !== '') {
            $paths[] = $path;
        }
    }
    return $paths;
}

function csrf_http_origin(string $base): string
{
    foreach (['http://127.0.0.1', 'http://localhost'] as $origin) {
        $ch = curl_init($origin . $base . '/login');
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_CONNECTTIMEOUT => 5, CURLOPT_TIMEOUT => 15]);
        $body = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);
        if ($status === 200 && is_string($body) && str_contains($body, 'name="csrf"') && str_contains($body, 'name="email"')) {
            return $origin;
        }
    }
    return '';
}

function csrf_http_post(string $url, array $cookies, array $fields): array
{
    $ch = curl_init($url);
    $pairs = [];
    foreach ($cookies as $name => $value) {
        $pairs[] = $name . '=' . $value;
    }
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query($fields),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_COOKIE => implode('; ', $pairs),
    ]);
    $raw = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $headerSize = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    curl_close($ch);
    $raw = is_string($raw) ? $raw : '';
    $headers = substr($raw, 0, $headerSize);
    $body = substr($raw, $headerSize);
    $location = '';
    if (preg_match('/^Location:\s*(\S+)/mi', $headers, $match) === 1) {
        $location = $match[1];
    }
    return ['status' => $status, 'body' => $body, 'location' => $location];
}

$csrfSource = (string) file_get_contents(dirname(__DIR__) . '/app/core/csrf.php');
check(!str_contains($csrfSource, 'db_'), 'csrf_check does not call the database helpers');
check(str_contains($csrfSource, "throw new HttpException(t('csrf_failed'), 403)"), 'csrf_check throws 403');

$_COOKIE['lang'] = 'en';
$_COOKIE['csrf_token'] = str_repeat('a', 64);
$_POST = ['csrf' => str_repeat('b', 64)];
$_SERVER['REQUEST_METHOD'] = 'POST';
$_SERVER['REQUEST_URI'] = base_path() . '/login';
try {
    csrf_check();
    check(false, 'a wrong CSRF token is refused');
} catch (HttpException $e) {
    check_eq($e->status(), 403, 'a wrong CSRF token returns 403');
    check_eq($e->getMessage(), 'This page expired. Go back, refresh it and try again.', 'English CSRF page text');
}

$_COOKIE['lang'] = 'ha';
unset($GLOBALS['gmingo_lang_override']);
try {
    csrf_check();
    check(false, 'a wrong CSRF token is refused in Hausa');
} catch (HttpException $e) {
    check_eq($e->status(), 403, 'a Hausa CSRF failure returns 403');
    check_eq($e->getMessage(), 'Wannan shafin ya wuce lokaci. Koma baya, sabunta shi, ka sake gwadawa.', 'Hausa CSRF page text');
}
$_POST = ['lang' => 'en'];
try {
    csrf_check();
    check(false, 'a missing CSRF token is refused');
} catch (HttpException $e) {
    check_eq($e->status(), 403, 'a missing CSRF token returns 403');
}
unset($_COOKIE['csrf_token']);
$_POST = ['csrf' => str_repeat('b', 64)];
try {
    csrf_check();
    check(false, 'a request without the CSRF cookie is refused');
} catch (HttpException $e) {
    check_eq($e->status(), 403, 'an expired CSRF cookie returns 403');
}

$securityLog = dirname(__DIR__) . '/storage/logs/security.log';
$securityText = is_file($securityLog) ? (string) file_get_contents($securityLog) : '';
check(str_contains($securityText, 'csrf_failed_mismatch'), 'a wrong token is logged');
check(str_contains($securityText, 'csrf_failed_missing'), 'a missing token is logged');
check(str_contains($securityText, 'csrf_failed_no_cookie'), 'a missing cookie is logged');
check(!str_contains($securityText, str_repeat('a', 64)), 'the security log omits the cookie token');
check(!str_contains($securityText, str_repeat('b', 64)), 'the security log omits the posted token');

$_COOKIE['lang'] = 'en';
ini_set('display_errors', '1');
errors_hide_details();
check(filter_var((string) ini_get('display_errors'), FILTER_VALIDATE_BOOLEAN) === false, 'display_errors is off');

$errorsSource = (string) file_get_contents(dirname(__DIR__) . '/app/core/errors.php');
check(str_contains($errorsSource, 'errors_document(500, errors_generic_message())'), 'uncaught errors show the generic 500 page');
check(str_contains($errorsSource, "errors_append('error.log'"), 'uncaught errors are written to storage/logs');
check(str_contains($errorsSource, 'getTraceAsString'), 'exception details stay in the log helper');
check(!str_contains($errorsSource, 'echo $e'), 'the error helper does not echo the exception object');

$index = (string) file_get_contents(dirname(__DIR__) . '/public/index.php');
check(str_contains($index, "ini_set('display_errors', '0')"), 'the front controller turns display_errors off');
check(str_contains($index, 'errors_install()'), 'the front controller installs one error handler');
check(!str_contains($index, 'getTraceAsString'), 'the front controller does not print a stack trace');

@chmod(dirname(__DIR__) . '/storage/logs', 0777);

$base = base_path();
$origin = csrf_http_origin($base);
check($origin !== '', 'Apache answers on the real HTTP address');
if ($origin === '') {
    return;
}

$markerId = uuid();
$token = 'csrfprobe' . bin2hex(random_bytes(12));
$paths = csrf_http_paths($markerId, $token);
check(count($paths) >= 29, 'every POST route is included');
$live = csrf_http_live_pdo();
check(csrf_http_marker_found($live, $markerId) === null, 'the probe id is unused before the posts');
$cookieToken = bin2hex(random_bytes(32));
$wrongToken = bin2hex(random_bytes(32));
$english = 'This page expired. Go back, refresh it and try again.';
$hausa = 'Wannan shafin ya wuce lokaci. Koma baya, sabunta shi, ka sake gwadawa.';
$logBefore = is_file($securityLog) ? (string) file_get_contents($securityLog) : '';

foreach ($paths as $path) {
    $url = $origin . $base . $path;
    $webhook = str_starts_with($path, '/webhooks/');
    $cases = [
        'wrong token' => [['csrf_token' => $cookieToken, 'lang' => 'en', 'gmingo_fashion' => 'expired-session-id'], ['csrf' => $wrongToken, 'lang' => 'en', 'name' => 'CSRF Probe ' . $markerId, 'message' => 'CSRF Probe ' . $markerId], $english],
        'missing token' => [['csrf_token' => $cookieToken, 'lang' => 'en', 'gmingo_fashion' => 'expired-session-id'], ['lang' => 'en', 'name' => 'CSRF Probe ' . $markerId, 'message' => 'CSRF Probe ' . $markerId], $english],
        'expired session' => [['gmingo_fashion' => 'expired-session-id'], ['lang' => 'en', 'name' => 'CSRF Probe ' . $markerId, 'message' => 'CSRF Probe ' . $markerId], $english],
        'wrong token in Hausa' => [['csrf_token' => $cookieToken, 'lang' => 'ha'], ['csrf' => $wrongToken, 'lang' => 'ha', 'name' => 'CSRF Probe ' . $markerId, 'message' => 'CSRF Probe ' . $markerId], $hausa],
    ];
    foreach ($cases as $label => [$cookies, $fields, $text]) {
        $response = csrf_http_post($url, $cookies, $fields);
        $status = $response['status'];
        $loginRedirect = $status >= 300 && $status < 400 && str_contains($response['location'], '/login');
        if ($webhook) {
            check($status !== 500 && $status !== 0, $path . ' ' . $label . ' does not return 500');
        } else {
            check($status === 403 || $loginRedirect, $path . ' ' . $label . ' returns 403 or a login redirect (got ' . (string) $status . ')');
        }
        $body = $response['body'];
        check(!str_contains($body, 'Stack trace'), $path . ' ' . $label . ' shows no stack trace');
        check(!str_contains($body, $wrongToken), $path . ' ' . $label . ' does not echo the token');
        if ($status === 403) {
            check(str_contains($body, $text), $path . ' ' . $label . ' shows the expired-page text');
        }
    }
}

check(csrf_http_marker_found($live, $markerId) === null, 'failed CSRF posts leave no probe id in the database');
check(csrf_http_marker_found($live, 'CSRF Probe ' . $markerId) === null, 'failed CSRF posts leave no probe text in the database');
$freshLog = substr(is_file($securityLog) ? (string) file_get_contents($securityLog) : '', strlen($logBefore));
check(str_contains($freshLog, 'csrf_failed_'), 'HTTP CSRF failures are logged');
check(!str_contains($freshLog, $wrongToken), 'the HTTP security log omits the posted token');
check(!str_contains($freshLog, $cookieToken), 'the HTTP security log omits the cookie token');
