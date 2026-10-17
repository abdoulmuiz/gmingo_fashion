<?php

declare(strict_types=1);

/**
 * Local site check. Talks to Apache with curl.
 * Creates only shops whose names start with "ZZ Check ".
 * Does not change application code. Problems are reported.
 *
 * Log paths are settings. config.php may set site_check_php_log and
 * site_check_apache_log. Otherwise the XAMPP log paths below are used.
 */

require __DIR__ . '/site_check_cleanup.php';

ini_set('output_buffering', '0');
while (ob_get_level() > 0) {
    ob_end_flush();
}
ob_implicit_flush(true);
register_shutdown_function(static function (): void {
    $error = error_get_last();
    if ($error === null) {
        return;
    }
    if (!in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        return;
    }
    echo 'FATAL ' . $error['message'] . ' in ' . $error['file'] . ':' . (string) $error['line'] . "\n";
});

const SITE_CHECK_PHP_LOG = '/opt/lampp/logs/php_error_log';
const SITE_CHECK_APACHE_LOG = '/opt/lampp/logs/error_log';
const SITE_CHECK_PASSWORD = 'check-pass-19';
const SITE_CHECK_CRAWL_LIMIT = 80;

function site_check_log_paths(array $config): array
{
    $php = trim((string) ($config['site_check_php_log'] ?? SITE_CHECK_PHP_LOG));
    $apache = trim((string) ($config['site_check_apache_log'] ?? SITE_CHECK_APACHE_LOG));
    return ['php' => $php, 'apache' => $apache];
}

function site_check_origin(string $base): string
{
    foreach (['http://127.0.0.1', 'http://localhost'] as $origin) {
        $url = $origin . $base . '/login';
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_HEADER => true,
        ]);
        $raw = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);
        if (!is_string($raw) || $status !== 200) {
            continue;
        }
        $body = site_check_body($raw);
        if (str_contains($body, 'name="csrf"') && str_contains($body, 'name="email"')) {
            return $origin;
        }
    }
    fwrite(STDERR, "No working Apache URL for {$base}\n");
    exit(1);
}

function site_check_body(string $raw): string
{
    $pos = strpos($raw, "\r\n\r\n");
    if ($pos === false) {
        return $raw;
    }
    return substr($raw, $pos + 4);
}

function site_check_headers(string $raw): string
{
    $pos = strpos($raw, "\r\n\r\n");
    if ($pos === false) {
        return '';
    }
    return substr($raw, 0, $pos);
}

function site_check_request(string $method, string $url, string $cookie, array $post = [], bool $follow = false, int $hops = 0): array
{
    $ch = curl_init($url);
    $options = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER => true,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_COOKIEJAR => $cookie,
        CURLOPT_COOKIEFILE => $cookie,
        CURLOPT_CUSTOMREQUEST => $method,
    ];
    if ($method === 'POST') {
        $options[CURLOPT_POSTFIELDS] = http_build_query($post);
    }
    curl_setopt_array($ch, $options);
    $raw = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    if (!is_string($raw)) {
        return ['status' => 0, 'headers' => '', 'body' => '', 'url' => $url, 'error' => $error];
    }
    $result = [
        'status' => $status,
        'headers' => site_check_headers($raw),
        'body' => site_check_body($raw),
        'url' => $url,
        'error' => $error,
    ];
    if ($follow && $status >= 300 && $status < 400) {
        $next = site_check_location($result['headers']);
        if ($next !== null) {
            if (str_starts_with($next, '/')) {
                $parts = parse_url($url);
                $port = isset($parts['port']) ? ':' . (string) $parts['port'] : '';
                $next = (string) ($parts['scheme'] ?? 'http') . '://' . (string) ($parts['host'] ?? '127.0.0.1') . $port . $next;
            }
            if ($hops >= 5) {
                return $result;
            }
            return site_check_request('GET', $next, $cookie, [], true, $hops + 1);
        }
    }
    return $result;
}

function site_check_location(string $headers): ?string
{
    if (preg_match('/^Location:\s*(\S+)/mi', $headers, $match) !== 1) {
        return null;
    }
    return trim($match[1]);
}

function site_check_dom(string $html): DOMDocument
{
    $dom = new DOMDocument();
    $previous = libxml_use_internal_errors(true);
    $dom->loadHTML('<?xml encoding="utf-8">' . $html);
    libxml_clear_errors();
    libxml_use_internal_errors($previous);
    return $dom;
}

function site_check_redact(string $value): string
{
    $redacted = preg_replace('/\d{8,}/', '[digits]', $value);
    return is_string($redacted) ? $redacted : $value;
}

function site_check_add(array &$findings, string $kind, string $url, string $user, string $reason): void
{
    $url = site_check_redact($url);
    $reason = site_check_redact($reason);
    $key = $kind . '|' . $url . '|' . $user . '|' . $reason;
    if (isset($findings[$key])) {
        return;
    }
    $findings[$key] = ['kind' => $kind, 'url' => $url, 'user' => $user, 'reason' => $reason];
    echo $kind . "\t" . $url . "\t" . $user . "\t" . $reason . "\n";
    flush();
}

function site_check_php_error(string $body): ?string
{
    $patterns = [
        'Warning:',
        'Notice:',
        'Deprecated:',
        'Fatal error',
        'Stack trace',
        'Undefined variable',
        'Undefined index',
        'Undefined array',
        'Undefined property',
        'Undefined constant',
    ];
    foreach ($patterns as $pattern) {
        if (str_contains($body, $pattern)) {
            return $pattern;
        }
    }
    return null;
}

function site_check_counts(PDO $pdo): array
{
    $counts = [];
    foreach (site_check_fashion_tables($pdo) as $table) {
        $counts[$table] = (int) $pdo->query('SELECT COUNT(*) AS n FROM ' . $table)->fetch()['n'];
    }
    ksort($counts);
    return $counts;
}

function site_check_count_diff(array $before, array $after): array
{
    $changes = [];
    $tables = array_unique(array_merge(array_keys($before), array_keys($after)));
    sort($tables);
    foreach ($tables as $table) {
        $left = $before[$table] ?? 0;
        $right = $after[$table] ?? 0;
        if ($left !== $right) {
            $changes[] = $table . ' ' . (string) $left . ' -> ' . (string) $right;
        }
    }
    return $changes;
}

function site_check_uuid(): string
{
    $bytes = random_bytes(16);
    $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
    $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
    $hex = bin2hex($bytes);
    return substr($hex, 0, 8) . '-' . substr($hex, 8, 4) . '-' . substr($hex, 12, 4) . '-' . substr($hex, 16, 4) . '-' . substr($hex, 20, 12);
}

function site_check_catalogs(): array
{
    $root = dirname(__DIR__) . '/lang';
    $en = require $root . '/en.php';
    $ha = require $root . '/ha.php';
    return ['en' => is_array($en) ? $en : [], 'ha' => is_array($ha) ? $ha : []];
}

function site_check_resolve(string $href, string $pageUrl, string $origin, string $base): ?string
{
    $href = trim(html_entity_decode($href, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    if ($href === '' || str_starts_with($href, '#') || str_starts_with($href, 'javascript:') || str_starts_with($href, 'mailto:') || str_starts_with($href, 'tel:')) {
        return null;
    }
    if (str_starts_with($href, '//')) {
        return null;
    }
    if (preg_match('#^https?://#i', $href) === 1) {
        $parts = parse_url($href);
        if (!is_array($parts) || !isset($parts['host'])) {
            return null;
        }
        $originParts = parse_url($origin);
        $host = strtolower((string) $parts['host']);
        $originHost = strtolower((string) ($originParts['host'] ?? ''));
        if ($host !== $originHost) {
            return null;
        }
        $path = (string) ($parts['path'] ?? '/');
        $query = isset($parts['query']) ? '?' . $parts['query'] : '';
        $href = $path . $query;
    }
    if (!str_starts_with($href, '/')) {
        $basePath = (string) (parse_url($pageUrl, PHP_URL_PATH) ?? '/');
        $dir = str_ends_with($basePath, '/') ? $basePath : preg_replace('#/[^/]*$#', '/', $basePath);
        $href = (is_string($dir) ? $dir : '/') . $href;
    }
    $hash = strpos($href, '#');
    if ($hash !== false) {
        $href = substr($href, 0, $hash);
    }
    if ($href === '' || !str_starts_with($href, $base . '/') && $href !== $base && $href !== $base . '/') {
        return null;
    }
    $path = $href === $base ? '/' : substr($href, strlen($base));
    if ($path === '') {
        $path = '/';
    }
    if ($path === '/logout' || str_starts_with($path, '/logout?') || str_starts_with($path, '/logout/')) {
        return null;
    }
    return $origin . $base . ($path === '/' ? '/' : $path);
}

function site_check_bad_href(string $href): ?string
{
    $href = trim(html_entity_decode($href, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    if ($href === '' || str_starts_with($href, '#') || str_starts_with($href, 'javascript:') || str_starts_with($href, 'mailto:') || str_starts_with($href, 'tel:')) {
        return null;
    }
    if (str_starts_with($href, '//') || preg_match('#^https?://#i', $href) === 1) {
        return 'full domain';
    }
    if (preg_match('/:[0-9]{2,5}(?![0-9])/', $href) === 1) {
        return 'port';
    }
    return null;
}

function site_check_forms(DOMDocument $dom, string $pageUrl): array
{
    $forms = [];
    $xpath = new DOMXPath($dom);
    foreach ($xpath->query('//form') as $form) {
        if (!$form instanceof DOMElement) {
            continue;
        }
        $method = strtolower(trim($form->getAttribute('method')));
        if ($method === '') {
            $method = 'get';
        }
        $action = trim($form->getAttribute('action'));
        if ($action === '') {
            $action = $pageUrl;
        }
        $fields = [];
        $hasCsrf = false;
        foreach ($xpath->query('.//input|.//textarea|.//select', $form) as $field) {
            if (!$field instanceof DOMElement) {
                continue;
            }
            $name = $field->getAttribute('name');
            if ($name === '') {
                continue;
            }
            if ($name === 'csrf') {
                $hasCsrf = true;
            }
            $value = $field->tagName === 'textarea' ? $field->textContent : $field->getAttribute('value');
            if ($field->tagName === 'select') {
                $value = '';
                foreach ($xpath->query('.//option', $field) as $option) {
                    if ($option instanceof DOMElement && $option->hasAttribute('selected')) {
                        $value = $option->getAttribute('value');
                        break;
                    }
                }
            }
            $fields[] = [
                'name' => $name,
                'type' => strtolower($field->getAttribute('type')),
                'value' => $value,
            ];
        }
        $names = array_map(static fn (array $field): string => $field['name'], $fields);
        sort($names);
        $forms[] = [
            'method' => $method,
            'action' => $action,
            'page' => $pageUrl,
            'fields' => $fields,
            'signature' => $method . ' ' . $action . ' ' . implode(',', $names),
            'csrf' => $hasCsrf,
        ];
    }
    return $forms;
}

function site_check_text_nodes(DOMDocument $dom): array
{
    $xpath = new DOMXPath($dom);
    foreach ($xpath->query('//script|//style') as $node) {
        $node->parentNode?->removeChild($node);
    }
    $texts = [];
    foreach ($xpath->query('//text()') as $node) {
        $value = trim((string) $node->textContent);
        if ($value !== '') {
            $texts[] = $value;
        }
    }
    return $texts;
}

function site_check_inspect_html(array &$findings, string $body, string $url, string $user, string $lang, array $catalogs): void
{
    $phpError = site_check_php_error($body);
    if ($phpError !== null) {
        site_check_add($findings, 'FAIL', $url, $user, 'PHP error text ' . $phpError);
    }
    if (!str_contains(strtolower(substr($body, 0, 200)), '<!doctype html') && !str_contains(strtolower(substr($body, 0, 300)), '<html')) {
        return;
    }
    $dom = site_check_dom($body);
    $xpath = new DOMXPath($dom);
    foreach ($xpath->query('//a[@href]|//form[@action]') as $node) {
        if (!$node instanceof DOMElement) {
            continue;
        }
        $attr = $node->hasAttribute('href') ? 'href' : 'action';
        $raw = $node->getAttribute($attr);
        $problem = site_check_bad_href($raw);
        if ($problem !== null) {
            site_check_add($findings, 'FAIL', $url, $user, $problem . ' in ' . $attr . ' ' . $raw);
        }
    }
    foreach (site_check_forms($dom, $url) as $form) {
        if ($form['method'] === 'post' && !$form['csrf']) {
            site_check_add($findings, 'FAIL', $form['action'], $user, 'POST form has no CSRF field');
        }
        if ($form['method'] === 'get' && !$form['csrf']) {
            site_check_add($findings, 'WARN', $form['action'], $user, 'GET form has no CSRF field');
        }
    }
    $catalog = $lang === 'ha' ? $catalogs['ha'] : $catalogs['en'];
    $english = $catalogs['en'];
    foreach (site_check_text_nodes($dom) as $text) {
        if (isset($english[$text]) && (string) $english[$text] !== $text) {
            site_check_add($findings, 'FAIL', $url, $user, 'raw translation key ' . $text);
        }
        if ($lang === 'ha' && isset($english[$text]) && (string) $english[$text] === $text && (!isset($catalog[$text]) || (string) $catalog[$text] === $text)) {
            continue;
        }
    }
    if ($lang !== 'ha') {
        return;
    }
    $visible = implode("\n", site_check_text_nodes($dom));
    foreach ($english as $key => $value) {
        if (!is_string($value) || strlen($value) < 18) {
            continue;
        }
        $hausa = (string) ($catalogs['ha'][$key] ?? '');
        if ($hausa === '' || $hausa === $value) {
            continue;
        }
        if (str_contains($visible, $value)) {
            site_check_add($findings, 'WARN', $url, $user, 'English text in Hausa mode: ' . $value);
        }
    }
}

function site_check_next_url(array &$queue): string
{
    foreach ($queue as $index => $url) {
        if (!str_contains($url, 'month=')) {
            array_splice($queue, $index, 1);
            return $url;
        }
    }
    return (string) array_shift($queue);
}

function site_check_crawl(array &$findings, string $origin, string $base, string $cookie, string $user, string $lang, array $catalogs, PDO $pdo): array
{
    $home = $origin . $base . '/';
    $before = site_check_counts($pdo);
    $queue = [$home];
    $seen = [];
    $forms = [];
    $pages = 0;
    while ($queue !== [] && $pages < SITE_CHECK_CRAWL_LIMIT) {
        $url = site_check_next_url($queue);
        if (isset($seen[$url])) {
            continue;
        }
        $seen[$url] = true;
        $pages++;
        $response = site_check_request('GET', $url, $cookie, [], true);
        $status = (int) $response['status'];
        echo 'PAGE ' . (string) $status . ' ' . site_check_redact($url) . ' ' . $user . "\n";
        if ($status === 0) {
            site_check_add($findings, 'FAIL', $url, $user, 'request failed');
            continue;
        }
        if ($status >= 500) {
            site_check_add($findings, 'FAIL', $url, $user, 'status ' . (string) $status);
        } elseif ($status === 403 || $status === 404) {
            site_check_add($findings, 'FAIL', $url, $user, 'visible link returned ' . (string) $status);
        } elseif ($response['body'] !== '' && str_contains($response['body'], 'name="password"') && str_contains($response['body'], 'name="email"') && !str_contains($url, '/login')) {
            site_check_add($findings, 'FAIL', $url, $user, 'visible link opened the login page');
        }
        $phpError = site_check_php_error((string) $response['body']);
        if ($phpError !== null) {
            site_check_add($findings, 'FAIL', $url, $user, 'PHP error text ' . $phpError);
        }
        $type = '';
        if (preg_match('/^Content-Type:\s*([^\r\n]+)/mi', (string) $response['headers'], $match) === 1) {
            $type = strtolower($match[1]);
        }
        $isHtml = str_contains($type, 'html') || str_contains(strtolower(substr((string) $response['body'], 0, 40)), '<!doctype html');
        if (!$isHtml) {
            continue;
        }
        site_check_inspect_html($findings, (string) $response['body'], $url, $user, $lang, $catalogs);
        $dom = site_check_dom((string) $response['body']);
        foreach (site_check_forms($dom, $url) as $form) {
            if ($form['method'] === 'post' && !isset($forms[$form['signature']])) {
                $forms[$form['signature']] = $form;
            }
            if ($form['method'] === 'get') {
                $target = site_check_resolve($form['action'], $url, $origin, $base);
                if ($target !== null && !isset($seen[$target])) {
                    $queue[] = $target;
                }
            }
        }
        $xpath = new DOMXPath($dom);
        foreach ($xpath->query('//a[@href]') as $link) {
            if (!$link instanceof DOMElement) {
                continue;
            }
            $target = site_check_resolve($link->getAttribute('href'), $url, $origin, $base);
            if ($target !== null && !isset($seen[$target])) {
                $queue[] = $target;
            }
        }
    }
    if ($queue !== []) {
        site_check_add($findings, 'WARN', $home, $user, 'crawl stopped after ' . (string) SITE_CHECK_CRAWL_LIMIT . ' pages');
    }
    $after = site_check_counts($pdo);
    $diff = site_check_count_diff($before, $after);
    if ($diff !== []) {
        site_check_add($findings, 'FAIL', $home, $user, 'crawl changed row counts: ' . implode('; ', $diff));
    }
    return $forms;
}

function site_check_login(string $origin, string $base, string $cookie, string $email): array
{
    $page = site_check_request('GET', $origin . $base . '/login', $cookie);
    $dom = site_check_dom((string) $page['body']);
    $token = '';
    $xpath = new DOMXPath($dom);
    $node = $xpath->query('//form[contains(@action, "/login")]//input[@name="csrf"]')->item(0);
    if ($node instanceof DOMElement) {
        $token = $node->getAttribute('value');
    }
    $response = site_check_request('POST', $origin . $base . '/login', $cookie, [
        'csrf' => $token,
        'email' => $email,
        'password' => SITE_CHECK_PASSWORD,
    ]);
    $status = (int) $response['status'];
    $location = site_check_location((string) $response['headers']);
    if ($status >= 300 && $status < 400 && is_string($location)) {
        $next = str_starts_with($location, 'http') ? $location : $origin . $location;
        return site_check_request('GET', $next, $cookie);
    }
    return $response;
}

function site_check_field_post(array $form, string $mode, string $otherId): array
{
    $post = [];
    $long = str_repeat('A', 5000);
    $script = '<script>site-check-xss</script>';
    foreach ($form['fields'] as $field) {
        $name = (string) $field['name'];
        $type = (string) $field['type'];
        $value = (string) $field['value'];
        if ($type === 'submit' || $type === 'button' || $type === 'image' || $type === 'file') {
            continue;
        }
        if ($name === 'csrf') {
            $post[$name] = $mode === 'csrf' ? str_repeat('0', 64) : $value;
            continue;
        }
        if ($type === 'checkbox' || $type === 'radio') {
            if ($mode === 'empty') {
                continue;
            }
            $post[$name] = $value === '' ? '1' : $value;
            continue;
        }
        if ($mode === 'empty') {
            $post[$name] = '';
        } elseif ($mode === 'long') {
            $post[$name] = $long;
        } elseif ($mode === 'script') {
            $post[$name] = $script;
        } elseif ($mode === 'other') {
            $post[$name] = preg_replace('/[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}/i', $otherId, $value) ?? $value;
            if ($post[$name] === $value && preg_match('/id$/', $name) === 1) {
                $post[$name] = $otherId;
            }
        } else {
            $post[$name] = $value;
        }
    }
    return $post;
}

function site_check_form_cases(array &$findings, string $origin, string $base, string $cookie, string $user, array $forms, string $otherId, PDO $pdo): void
{
    foreach ($forms as $saved) {
        $pageUrl = (string) $saved['page'];
        $page = site_check_request('GET', $pageUrl, $cookie);
        if ((int) $page['status'] === 403 || (int) $page['status'] === 404) {
            continue;
        }
        $fresh = site_check_match_form((string) $page['body'], $saved, $pageUrl);
        if ($fresh === null) {
            site_check_add($findings, 'WARN', $pageUrl, $user, 'could not find the form again');
            continue;
        }
        $target = site_check_absolute_action((string) $fresh['action'], $origin, $base);
        foreach (['empty' => 'empty', 'long' => '5000 characters', 'script' => 'script text', 'csrf' => 'wrong CSRF', 'other' => 'another shop id'] as $mode => $label) {
            $before = site_check_counts($pdo);
            $post = site_check_field_post($fresh, $mode, $otherId);
            $postTarget = $target;
            if ($mode === 'other') {
                $postTarget = preg_replace('/[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}/i', $otherId, $target) ?? $target;
            }
            $response = site_check_request('POST', $postTarget, $cookie, $post, true);
            site_check_judge_form($findings, $response, $before, $pdo, $postTarget, $user, $label);
            if (str_contains((string) $response['body'], 'name="password"') && str_contains((string) $response['body'], 'name="email"')) {
                $email = site_check_email_from_user($user);
                if ($email !== '') {
                    site_check_login($origin, $base, $cookie, $email);
                    $page = site_check_request('GET', $pageUrl, $cookie);
                    $again = site_check_match_form((string) $page['body'], $saved, $pageUrl);
                    if ($again !== null) {
                        $fresh = $again;
                        $target = site_check_absolute_action((string) $fresh['action'], $origin, $base);
                    }
                }
            }
        }
    }
}

function site_check_email_from_user(string $user): string
{
    if (preg_match('/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/i', $user, $match) === 1) {
        return $match[0];
    }
    return '';
}

function site_check_absolute_action(string $action, string $origin, string $base): string
{
    if (preg_match('#^https?://#i', $action) === 1) {
        return $action;
    }
    if (str_starts_with($action, '/')) {
        return $origin . $action;
    }
    return $origin . $base . '/' . ltrim($action, '/');
}

function site_check_match_form(string $html, array $saved, string $pageUrl): ?array
{
    if ($html === '') {
        return null;
    }
    $dom = site_check_dom($html);
    foreach (site_check_forms($dom, $pageUrl) as $form) {
        if ($form['signature'] === $saved['signature'] || ($form['method'] === 'post' && $form['action'] === $saved['action'] && $form['signature'] === $saved['signature'])) {
            return $form;
        }
        $savedNames = array_map(static fn (array $field): string => $field['name'], $saved['fields']);
        $names = array_map(static fn (array $field): string => $field['name'], $form['fields']);
        sort($savedNames);
        sort($names);
        if ($form['method'] === 'post' && $form['action'] === $saved['action'] && $names === $savedNames) {
            return $form;
        }
    }
    return null;
}

function site_check_judge_form(array &$findings, array $response, array $before, PDO $pdo, string $url, string $user, string $label): void
{
    $status = (int) $response['status'];
    $body = (string) $response['body'];
    if ($status === 0 || $status >= 500) {
        site_check_add($findings, 'FAIL', $url, $user, $label . ' returned status ' . (string) $status);
    }
    $phpError = site_check_php_error($body);
    if ($phpError !== null) {
        site_check_add($findings, 'FAIL', $url, $user, $label . ' showed PHP error text ' . $phpError);
    }
    if (str_contains($body, '<script>site-check-xss')) {
        site_check_add($findings, 'FAIL', $url, $user, $label . ' came back unescaped');
    } elseif (str_contains($body, 'site-check-xss') && !str_contains($body, '&lt;script&gt;')) {
        site_check_add($findings, 'FAIL', $url, $user, $label . ' came back without escaping');
    }
    $diff = site_check_count_diff($before, site_check_counts($pdo));
    $refused = in_array($status, [400, 402, 403, 404, 419, 422], true);
    if ($diff !== [] && !$refused) {
        site_check_add($findings, 'FAIL', $url, $user, $label . ' wrote rows: ' . implode('; ', $diff));
    }
    if ($diff !== [] && $refused) {
        site_check_add($findings, 'FAIL', $url, $user, $label . ' was refused but wrote rows: ' . implode('; ', $diff));
    }
}

function site_check_module_paths(): array
{
    return [
        'fashion.orders' => ['/orders', '/customers', '/groups', '/calendar'],
        'fashion.production' => ['/board', '/settings/stages'],
        'fashion.finance' => ['/outstanding', '/settings/payments'],
        'fashion.measurements' => ['/settings/garments'],
        'fashion.communication' => ['/messages'],
        'fashion.inventory' => ['/stock', '/stock/new'],
        'fashion.sales' => [],
        'fashion.staff' => [],
        'fashion.online_payments' => [],
    ];
}

function site_check_links(string $html, string $origin, string $base): array
{
    $dom = site_check_dom($html);
    $xpath = new DOMXPath($dom);
    $paths = [];
    foreach ($xpath->query('//a[@href]') as $link) {
        if (!$link instanceof DOMElement) {
            continue;
        }
        $absolute = site_check_resolve($link->getAttribute('href'), $origin . $base . '/', $origin, $base);
        if ($absolute === null) {
            continue;
        }
        $path = (string) parse_url($absolute, PHP_URL_PATH);
        $basePath = $base === '' ? '' : $base;
        if (str_starts_with($path, $basePath)) {
            $path = substr($path, strlen($basePath));
            if ($path === '') {
                $path = '/';
            }
        }
        $paths[] = $path;
    }
    return $paths;
}

function site_check_path_hits(array $paths, string $needle): bool
{
    foreach ($paths as $path) {
        if ($path === $needle || str_starts_with($path, $needle . '/')) {
            return true;
        }
    }
    return false;
}

function site_check_modules(array &$findings, PDO $pdo, string $origin, string $base, array $shop): void
{
    $email = (string) $shop['owner_email'];
    $user = (string) $shop['name'] . ' owner';
    $cookie = (string) $shop['owner_cookie'];
    if (is_file($cookie)) {
        unlink($cookie);
    }
    site_check_login($origin, $base, $cookie, $email);
    $original = [];
    $rows = $pdo->prepare('SELECT module_key, enabled FROM fashion_tenant_modules WHERE tenant_id = :id');
    $rows->execute(['id' => $shop['id']]);
    foreach ($rows->fetchAll() as $row) {
        if ((int) $row['enabled'] === 1) {
            $original[] = (string) $row['module_key'];
        }
    }
    foreach (site_check_module_paths() as $key => $paths) {
        $wanted = array_values(array_filter($original, static fn (string $item): bool => $item !== $key));
        site_check_post_modules($origin, $base, $cookie, $wanted);
        if ($paths === []) {
            site_check_add($findings, 'WARN', '/settings', $user, $key . ' has no GET page to check');
        }
        foreach ($paths as $path) {
            $response = site_check_request('GET', $origin . $base . $path, $cookie);
            if ((int) $response['status'] !== 403) {
                site_check_add($findings, 'FAIL', $origin . $base . $path, $user, $key . ' off returned ' . (string) $response['status'] . ' instead of 403');
            }
        }
        $linked = [];
        foreach (['/', '/more', '/settings'] as $path) {
            $page = site_check_request('GET', $origin . $base . $path, $cookie);
            if ((int) $page['status'] === 200) {
                $linked = array_merge($linked, site_check_links((string) $page['body'], $origin, $base));
            }
        }
        foreach ($paths as $path) {
            if (site_check_path_hits($linked, $path)) {
                site_check_add($findings, 'FAIL', $origin . $base . $path, $user, $key . ' is off but a menu link is still shown');
            }
        }
        site_check_post_modules($origin, $base, $cookie, $original);
    }
}

function site_check_post_modules(string $origin, string $base, string $cookie, array $keys): void
{
    $page = site_check_request('GET', $origin . $base . '/settings', $cookie);
    $dom = site_check_dom((string) $page['body']);
    $xpath = new DOMXPath($dom);
    $token = '';
    $node = $xpath->query('//form[@action][.//input[@name="modules[]"]]//input[@name="csrf"]')->item(0);
    if ($node instanceof DOMElement) {
        $token = $node->getAttribute('value');
    }
    $post = ['csrf' => $token];
    foreach (array_values($keys) as $index => $key) {
        $post['modules'][$index] = $key;
    }
    if ($keys === []) {
        $post['modules'] = '';
    }
    site_check_request('POST', $origin . $base . '/settings', $cookie, $post, true);
}

function site_check_subscription(array &$findings, PDO $pdo, string $origin, string $base, array $shop, array $forms): void
{
    $email = (string) $shop['owner_email'];
    $user = (string) $shop['name'] . ' owner';
    $cookie = (string) $shop['owner_cookie'];
    $today = new DateTimeImmutable('now', new DateTimeZone('Africa/Lagos'));
    $states = [
        'active' => null,
        'grace' => $today->modify('-3 days')->format('Y-m-d'),
        'expired' => $today->modify('-8 days')->format('Y-m-d'),
    ];
    $update = $pdo->prepare('UPDATE fashion_tenants SET subscription_ends_on = :ends WHERE id = :id AND name LIKE :prefix');
    foreach ($states as $label => $ends) {
        $update->execute(['ends' => $ends, 'id' => $shop['id'], 'prefix' => site_check_prefix() . '%']);
        if (is_file($cookie)) {
            unlink($cookie);
        }
        $home = site_check_login($origin, $base, $cookie, $email);
        if ((int) $home['status'] !== 200) {
            site_check_add($findings, 'FAIL', $origin . $base . '/', $user, $label . ' subscription home returned ' . (string) $home['status']);
        }
        if ($label !== 'expired') {
            continue;
        }
        foreach ($forms as $saved) {
            $action = (string) $saved['action'];
            if (str_contains($action, '/logout') || str_contains($action, '/language')) {
                continue;
            }
            $pageUrl = (string) $saved['page'];
            $page = site_check_request('GET', $pageUrl, $cookie);
            if ((int) $page['status'] !== 200) {
                continue;
            }
            $fresh = site_check_match_form((string) $page['body'], $saved, $pageUrl);
            if ($fresh === null) {
                continue;
            }
            $before = site_check_counts($pdo);
            $post = site_check_field_post($fresh, 'keep', '');
            $target = site_check_absolute_action((string) $fresh['action'], $origin, $base);
            $response = site_check_request('POST', $target, $cookie, $post, true);
            if ((int) $response['status'] !== 402) {
                site_check_add($findings, 'FAIL', $target, $user, 'expired write returned ' . (string) $response['status'] . ' instead of 402');
            }
            $diff = site_check_count_diff($before, site_check_counts($pdo));
            if ($diff !== []) {
                site_check_add($findings, 'FAIL', $target, $user, 'expired write changed rows: ' . implode('; ', $diff));
            }
        }
    }
    $update->execute(['ends' => null, 'id' => $shop['id'], 'prefix' => site_check_prefix() . '%']);
}

function site_check_log_offset(string $path): int
{
    if (!is_file($path)) {
        return -1;
    }
    $size = filesize($path);
    return $size === false ? -1 : $size;
}

function site_check_log_new(string $path, int $offset): string
{
    if ($offset < 0 || !is_file($path)) {
        return '';
    }
    $handle = fopen($path, 'rb');
    if ($handle === false) {
        return '';
    }
    fseek($handle, $offset);
    $text = stream_get_contents($handle);
    fclose($handle);
    return is_string($text) ? $text : '';
}

function site_check_register_shop(string $origin, string $base, string $cookie, array $input): array
{
    $page = site_check_request('GET', $origin . $base . '/register', $cookie);
    $dom = site_check_dom((string) $page['body']);
    $xpath = new DOMXPath($dom);
    $node = $xpath->query('//form[contains(@action, "/register")]//input[@name="csrf"]')->item(0);
    $token = $node instanceof DOMElement ? $node->getAttribute('value') : '';
    $response = site_check_request('POST', $origin . $base . '/register', $cookie, [
        'csrf' => $token,
        'company_type' => $input['company_type'],
        'business_name' => $input['business_name'],
        'owner_name' => $input['owner_name'],
        'email' => $input['email'],
        'password' => SITE_CHECK_PASSWORD,
        'phone' => $input['phone'],
    ]);
    $status = (int) $response['status'];
    $location = site_check_location((string) $response['headers']);
    if ($status >= 300 && $status < 400 && is_string($location)) {
        $next = str_starts_with($location, 'http') ? $location : $origin . $location;
        return site_check_request('GET', $next, $cookie);
    }
    return $response;
}

function site_check_sample(string $origin, string $base, string $cookie, string $customerPhone): ?string
{
    $newCustomer = site_check_request('GET', $origin . $base . '/customers/new', $cookie);
    $dom = site_check_dom((string) $newCustomer['body']);
    $xpath = new DOMXPath($dom);
    $csrf = $xpath->query('//form[contains(@action, "/customers")]//input[@name="csrf"]')->item(0);
    if (!$csrf instanceof DOMElement) {
        return null;
    }
    $created = site_check_request('POST', $origin . $base . '/customers', $cookie, [
        'csrf' => $csrf->getAttribute('value'),
        'name' => 'ZZ Check Customer',
        'phone' => $customerPhone,
        'gender' => 'female',
        'lang' => '',
        'customer_type' => 'regular',
    ], true);
    if ((int) $created['status'] >= 400) {
        return null;
    }
    $orderPage = site_check_request('GET', $origin . $base . '/orders/new', $cookie);
    $orderDom = site_check_dom((string) $orderPage['body']);
    $orderPath = new DOMXPath($orderDom);
    $orderCsrf = $orderPath->query('//form[contains(@action, "/orders")]//input[@name="csrf"]')->item(0);
    $customer = $orderPath->query('//select[@name="customer_id"]/option[@value!=""]')->item(0);
    $garment = $orderPath->query('//select[contains(@name, "garment_type_id")]/option[@value!=""]')->item(0);
    if (!$orderCsrf instanceof DOMElement || !$customer instanceof DOMElement || !$garment instanceof DOMElement) {
        return null;
    }
    $saved = site_check_request('POST', $origin . $base . '/orders', $cookie, [
        'csrf' => $orderCsrf->getAttribute('value'),
        'customer_id' => $customer->getAttribute('value'),
        'due_date' => '2026-12-20',
        'items' => [[
            'garment_type_id' => $garment->getAttribute('value'),
            'quantity' => '1',
            'price' => '20',
            'fabric' => '',
            'notes' => '',
        ]],
    ], true);
    if ((int) $saved['status'] >= 400) {
        return null;
    }
    $final = (string) $saved['url'];
    if (preg_match('/orders\/([0-9a-f-]{36})/i', $final, $match) === 1) {
        return $match[1];
    }
    $location = site_check_location((string) $saved['headers']);
    if (is_string($location) && preg_match('/orders\/([0-9a-f-]{36})/i', $location, $match) === 1) {
        return $match[1];
    }
    return '';
}

function site_check_write_report(array $findings, string $path): void
{
    $rows = array_values($findings);
    usort($rows, static function (array $a, array $b): int {
        return [$a['kind'], $a['user'], $a['url'], $a['reason']] <=> [$b['kind'], $b['user'], $b['url'], $b['reason']];
    });
    $fails = 0;
    $warns = 0;
    $lines = ["kind\turl\tuser\treason"];
    foreach ($rows as $row) {
        if ($row['kind'] === 'FAIL') {
            $fails++;
        } else {
            $warns++;
        }
        $lines[] = $row['kind'] . "\t" . $row['url'] . "\t" . $row['user'] . "\t" . $row['reason'];
    }
    $lines[] = 'Failures: ' . (string) $fails . '. Warnings: ' . (string) $warns . '.';
    file_put_contents($path, implode("\n", $lines) . "\n");
    echo "\n" . implode("\n", $lines) . "\n";
}

$started = microtime(true);
$config = site_check_config();
$logs = site_check_log_paths($config);
$base = rtrim((string) ($config['base_path'] ?? ''), '/');
if ($base === '' || $base[0] !== '/') {
    fwrite(STDERR, "base_path is missing\n");
    exit(1);
}
foreach ($logs as $label => $path) {
    if ($path === '' || !is_file($path)) {
        fwrite(STDERR, "Log setting {$label} is missing: {$path}\n");
        exit(1);
    }
}
$logMarks = [];
foreach ($logs as $label => $path) {
    $logMarks[$label] = site_check_log_offset($path);
}
$pdo = site_check_pdo($config);
$removed = site_check_remove($pdo);
foreach ($removed as $name) {
    echo "Removed leftover {$name}\n";
}
$origin = site_check_origin($base);
echo "Using {$origin}{$base}\n";
echo 'PHP log ' . $logs['php'] . "\n";
echo 'Apache log ' . $logs['apache'] . "\n";

$work = dirname(__DIR__) . '/storage/logs/site-check';
if (!is_dir($work) && !mkdir($work, 0770, true) && !is_dir($work)) {
    fwrite(STDERR, "Could not create {$work}\n");
    exit(1);
}

$shops = [
    ['type' => 'tailor', 'name' => 'ZZ Check Tailor', 'phone' => '08039001001', 'customer_phone' => '08031110011'],
    ['type' => 'factory', 'name' => 'ZZ Check Factory', 'phone' => '08039001002', 'customer_phone' => ''],
    ['type' => 'brand', 'name' => 'ZZ Check Brand', 'phone' => '08039001003', 'customer_phone' => '08031110013'],
];
$catalogs = site_check_catalogs();
$findings = [];
$setupCookie = $work . '/setup.cookies';
foreach ($shops as &$shop) {
    if (is_file($setupCookie)) {
        unlink($setupCookie);
    }
    touch($setupCookie);
    $shop['owner_email'] = 'zz-check-' . $shop['type'] . '-owner@gmingo.test';
    $shop['staff_email'] = 'zz-check-' . $shop['type'] . '-staff@gmingo.test';
    $shop['owner_cookie'] = $work . '/' . $shop['type'] . '-owner.cookies';
    $registered = site_check_register_shop($origin, $base, $setupCookie, [
        'company_type' => $shop['type'],
        'business_name' => $shop['name'],
        'owner_name' => $shop['name'] . ' Owner',
        'email' => $shop['owner_email'],
        'phone' => $shop['phone'],
    ]);
    if ((int) $registered['status'] !== 200 || str_contains((string) $registered['body'], 'name="business_name"')) {
        site_check_add($findings, 'FAIL', $origin . $base . '/register', $shop['name'], 'could not create the shop');
        continue;
    }
    $owner = $pdo->prepare('SELECT id, tenant_id FROM fashion_users WHERE email = :email');
    $owner->execute(['email' => $shop['owner_email']]);
    $ownerRow = $owner->fetch();
    if (!is_array($ownerRow)) {
        site_check_add($findings, 'FAIL', $origin . $base . '/register', $shop['name'], 'owner row was not saved');
        continue;
    }
    $shop['id'] = (string) $ownerRow['tenant_id'];
    $staffId = site_check_uuid();
    $insert = $pdo->prepare(
        'INSERT INTO fashion_users (id, tenant_id, name, email, password_hash, role, lang, active, created_at)
         VALUES (:id, :tenant_id, :name, :email, :password_hash, :role, :lang, 1, :created_at)'
    );
    $insert->execute([
        'id' => $staffId,
        'tenant_id' => $shop['id'],
        'name' => $shop['name'] . ' Staff',
        'email' => $shop['staff_email'],
        'password_hash' => password_hash(SITE_CHECK_PASSWORD, PASSWORD_DEFAULT),
        'role' => 'staff',
        'lang' => 'en',
        'created_at' => (new DateTimeImmutable('now', new DateTimeZone('Africa/Lagos')))->format('Y-m-d H:i:s'),
    ]);
    $shop['order_id'] = null;
    if ($shop['customer_phone'] !== '') {
        $shop['order_id'] = site_check_sample($origin, $base, $setupCookie, $shop['customer_phone']);
        if ($shop['order_id'] === null) {
            site_check_add($findings, 'WARN', $origin . $base . '/orders/new', $shop['name'] . ' owner', 'sample order was not created');
        }
    }
    echo "Created {$shop['name']}\n";
}
unset($shop);

$personas = [];
$allForms = [];
foreach ($shops as $shop) {
    if (!isset($shop['id'])) {
        continue;
    }
    foreach (['owner' => $shop['owner_email'], 'staff' => $shop['staff_email']] as $role => $email) {
        foreach (['en' => 'English', 'ha' => 'Hausa'] as $code => $language) {
            $user = $shop['name'] . ' ' . $role . ' ' . $language . ' ' . $email;
            $cookie = $work . '/' . $shop['type'] . '-' . $role . '-' . $code . '.cookies';
            if (is_file($cookie)) {
                unlink($cookie);
            }
            touch($cookie);
            $langUpdate = $pdo->prepare('UPDATE fashion_users SET lang = :lang WHERE email = :email AND tenant_id = :tenant_id');
            $langUpdate->execute(['lang' => $code, 'email' => $email, 'tenant_id' => $shop['id']]);
            $home = site_check_login($origin, $base, $cookie, $email);
            if ((int) $home['status'] !== 200 || str_contains((string) $home['body'], 'name="password"')) {
                site_check_add($findings, 'FAIL', $origin . $base . '/login', $user, 'could not log in');
                continue;
            }
            echo "Crawl {$user}\n";
            $forms = site_check_crawl($findings, $origin, $base, $cookie, $user, $code, $catalogs, $pdo);
            $personas[] = ['user' => $user, 'cookie' => $cookie, 'forms' => $forms, 'shop' => $shop['id']];
            if ($role === 'owner' && $code === 'en') {
                $allForms[$shop['id']] = $forms;
            }
        }
    }
}

foreach ($personas as $persona) {
    $otherId = '';
    foreach ($shops as $shop) {
        if (isset($shop['id']) && $shop['id'] !== $persona['shop']) {
            $otherId = (string) $shop['id'];
            break;
        }
    }
    echo "Forms {$persona['user']}\n";
    site_check_form_cases($findings, $origin, $base, $persona['cookie'], $persona['user'], $persona['forms'], $otherId, $pdo);
}

foreach ($shops as $shop) {
    if (!isset($shop['id'])) {
        continue;
    }
    echo "Modules {$shop['name']}\n";
    site_check_modules($findings, $pdo, $origin, $base, $shop);
    echo "Subscription {$shop['name']}\n";
    site_check_subscription($findings, $pdo, $origin, $base, $shop, $allForms[$shop['id']] ?? []);
}

foreach ($logs as $label => $path) {
    $fresh = site_check_log_new($path, $logMarks[$label]);
    if (trim($fresh) === '') {
        continue;
    }
    $snippet = trim(preg_replace('/\s+/', ' ', substr($fresh, 0, 500)) ?? '');
    site_check_add($findings, 'FAIL', $path, 'logs', 'new ' . $label . ' log line: ' . $snippet);
}

$report = __DIR__ . '/site_check_report.txt';
site_check_write_report($findings, $report);
$fails = 0;
foreach ($findings as $row) {
    if ($row['kind'] === 'FAIL') {
        $fails++;
    }
}
echo 'Report ' . $report . "\n";
echo 'Elapsed ' . (string) (int) (microtime(true) - $started) . "s\n";
exit($fails > 0 ? 1 : 0);
