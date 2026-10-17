<?php

declare(strict_types=1);

require_once __DIR__ . '/modules.php';
require_once __DIR__ . '/deposits.php';
require_once __DIR__ . '/messages.php';
require_once __DIR__ . '/paper.php';

function tracking_grace_days(): int
{
    return 7;
}

function tracking_rate_max(): int
{
    return 8;
}

function tracking_rate_window(): int
{
    return 900;
}

function tracking_token_new(): string
{
    return bin2hex(random_bytes(16));
}

function tracking_token_ok(string $token): bool
{
    return preg_match('/^[a-f0-9]{32}$/', $token) === 1;
}

/**
 * The public tracking page has no login, so the shop is not known yet.
 * This is the only function that reads an order before a tenant is set.
 * It counts the visit by IP, looks the token up, and on a hit binds that
 * one shop for the rest of the request. Callers must release the bind.
 * A missing, regenerated or malformed token all miss this same lookup.
 *
 * @return array{limited: bool, order_id: ?string}
 */
function tracking_bind(string $token): array
{
    $now = app_now();
    $ipKey = hash('sha256', client_ip());
    $limitRows = tracking_platform_query(
        'SELECT hits, window_start FROM fashion_track_limits WHERE ip_key = :ip_key LIMIT 1',
        ['ip_key' => $ipKey]
    );
    $windowOpen = false;
    $hits = 0;
    $windowStart = '';
    if ($limitRows !== []) {
        $windowStart = (string) $limitRows[0]['window_start'];
        $start = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $windowStart, app_timezone());
        if ($start instanceof DateTimeImmutable && ($now->getTimestamp() - $start->getTimestamp()) < tracking_rate_window()) {
            $windowOpen = true;
            $hits = (int) $limitRows[0]['hits'];
        }
    }
    $limited = $windowOpen && $hits >= tracking_rate_max();
    if (!$limited) {
        $nextHits = $windowOpen ? $hits + 1 : 1;
        $stamp = $windowOpen && $windowStart !== '' ? $windowStart : $now->format('Y-m-d H:i:s');
        if ($limitRows === []) {
            tracking_platform_query(
                'INSERT INTO fashion_track_limits (id, tenant_id, ip_key, hits, window_start) VALUES (:id, :tenant_id, :ip_key, :hits, :window_start)',
                [
                    'id' => uuid(),
                    'tenant_id' => '00000000-0000-0000-0000-000000000000',
                    'ip_key' => $ipKey,
                    'hits' => $nextHits,
                    'window_start' => $stamp,
                ]
            );
        } else {
            tracking_platform_query(
                'UPDATE fashion_track_limits SET hits = :hits, window_start = :window_start WHERE ip_key = :ip_key',
                [
                    'hits' => $nextHits,
                    'window_start' => $stamp,
                    'ip_key' => $ipKey,
                ]
            );
        }
    }
    $probe = tracking_token_ok($token) ? $token : '00000000000000000000000000000000';
    $rows = tracking_platform_query(
        'SELECT tenant_id, id FROM fashion_orders WHERE tracking_token = :token LIMIT 1',
        ['token' => $probe]
    );
    if ($limited) {
        return ['limited' => true, 'order_id' => null];
    }
    if (!tracking_token_ok($token) || count($rows) !== 1) {
        return ['limited' => false, 'order_id' => null];
    }
    $tenantId = (string) ($rows[0]['tenant_id'] ?? '');
    $orderId = (string) ($rows[0]['id'] ?? '');
    if (!is_uuid($tenantId) || !is_uuid($orderId)) {
        return ['limited' => false, 'order_id' => null];
    }
    auth_bind_tenant($tenantId);
    return ['limited' => false, 'order_id' => $orderId];
}

function tracking_subscription_expired(): bool
{
    $tenantId = current_tenant_id();
    if ($tenantId === null) {
        return false;
    }
    $rows = db_select('fashion_tenants', ['id' => $tenantId], ['limit' => 1]);
    if ($rows === []) {
        return false;
    }
    $ends = $rows[0]['subscription_ends_on'] ?? null;
    if (!is_string($ends) || $ends === '') {
        return false;
    }
    $end = DateTimeImmutable::createFromFormat('!Y-m-d', substr($ends, 0, 10), app_timezone());
    if ($end === false || $end->format('Y-m-d') !== substr($ends, 0, 10)) {
        return false;
    }
    $last = $end->modify('+' . (string) tracking_grace_days() . ' days');
    $today = DateTimeImmutable::createFromFormat('!Y-m-d', app_today(), app_timezone());
    return $today instanceof DateTimeImmutable && $today > $last;
}

function tracking_closed(): bool
{
    return !module_enabled('fashion.communication') || tracking_subscription_expired();
}

function tracking_public_origin(): string
{
    $raw = trim((string) (app_config()['public_base_url'] ?? ''));
    if ($raw === '') {
        return '';
    }
    if (preg_match('#^https?://#i', $raw) !== 1) {
        $raw = 'https://' . $raw;
    }
    $parts = parse_url($raw);
    if (!is_array($parts) || !isset($parts['scheme'], $parts['host'])) {
        return '';
    }
    $scheme = strtolower((string) $parts['scheme']);
    if (!in_array($scheme, ['http', 'https'], true)) {
        return '';
    }
    $origin = $scheme . '://' . strtolower((string) $parts['host']);
    if (isset($parts['port'])) {
        $origin .= ':' . (int) $parts['port'];
    }
    return $origin;
}

function tracking_absolute_link(string $token): string
{
    if (!tracking_token_ok($token)) {
        return '';
    }
    $path = url('/track/' . $token);
    $origin = tracking_public_origin();
    if ($origin === '') {
        return $path;
    }
    return $origin . $path;
}

function tracking_issue(string $orderId): ?string
{
    if (!is_uuid($orderId)) {
        return null;
    }
    $rows = db_select('fashion_orders', ['id' => $orderId], ['limit' => 1]);
    if ($rows === []) {
        return null;
    }
    $existing = (string) ($rows[0]['tracking_token'] ?? '');
    if (tracking_token_ok($existing)) {
        return $existing;
    }
    $token = tracking_token_new();
    db_update('fashion_orders', ['tracking_token' => $token], ['id' => $orderId]);
    return $token;
}

function tracking_regenerate(string $orderId): string
{
    require_module('fashion.communication');
    if (!deposit_is_owner()) {
        http_response_code(403);
        throw new HttpException(t('not_allowed'), 403);
    }
    if (tracking_subscription_expired()) {
        http_response_code(402);
        throw new HttpException(t('subscription_expired'), 402);
    }
    if (!is_uuid($orderId) || db_select('fashion_orders', ['id' => $orderId], ['limit' => 1]) === []) {
        http_response_code(404);
        throw new HttpException(t('not_found'), 404);
    }
    $token = tracking_token_new();
    db_update('fashion_orders', ['tracking_token' => $token], ['id' => $orderId]);
    return $token;
}

function tracking_payment_save(string $channel, string $bank, string $account, string $note): array
{
    require_login();
    if (!deposit_is_owner()) {
        http_response_code(403);
        throw new HttpException(t('not_allowed'), 403);
    }
    if (tracking_subscription_expired()) {
        http_response_code(402);
        throw new HttpException(t('subscription_expired'), 402);
    }
    $bank = trim($bank);
    $account = trim($account);
    $note = trim($note);
    if (!in_array($channel, ['bank', 'opay'], true) || $bank === '' || $account === '') {
        return ['error' => 'required'];
    }
    if (strlen($bank) > 190 || strlen($account) > 190 || strlen($note) > 190) {
        return ['error' => 'required'];
    }
    db_update('fashion_tenants', [
        'payment_channel' => $channel,
        'payment_bank' => $bank,
        'payment_account' => $account,
        'payment_reference_note' => $note,
    ], ['id' => (string) current_tenant_id()]);
    return ['ok' => true];
}

function tracking_visitor_lang(): string
{
    $cookie = $_COOKIE['track_lang'] ?? '';
    if (is_string($cookie) && in_array($cookie, ['en', 'ha'], true)) {
        return $cookie;
    }
    return 'en';
}

function tracking_shop_lang(): string
{
    $cookie = $_COOKIE['track_lang'] ?? '';
    if (is_string($cookie) && in_array($cookie, ['en', 'ha'], true)) {
        return $cookie;
    }
    $owners = db_select('fashion_users', ['role' => 'owner'], ['limit' => 1]);
    $lang = (string) ($owners[0]['lang'] ?? 'en');
    return in_array($lang, ['en', 'ha'], true) ? $lang : 'en';
}

function tracking_lang_save(string $code): void
{
    if (!in_array($code, ['en', 'ha'], true)) {
        $code = 'en';
    }
    setcookie('track_lang', $code, [
        'expires' => time() + 60 * 60 * 24 * 365,
        'path' => base_path() . '/',
        'httponly' => false,
        'samesite' => 'Lax',
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    ]);
    $_COOKIE['track_lang'] = $code;
}

/**
 * @return array<string, mixed>
 */
function tracking_public(string $orderId): array
{
    $orders = db_select('fashion_orders', ['id' => $orderId], ['limit' => 1]);
    if ($orders === []) {
        http_response_code(404);
        throw new HttpException(t('not_found'), 404);
    }
    $order = $orders[0];
    $tenants = db_select('fashion_tenants', ['id' => (string) current_tenant_id()], ['limit' => 1]);
    $shop = $tenants[0] ?? [];
    $lang = tracking_shop_lang();
    $totals = db_query(
        'SELECT COALESCE(SUM(quantity * price_kobo), 0) AS total_kobo FROM fashion_order_items WHERE tenant_id = :tenant_id AND order_id = :order_id',
        ['order_id' => $orderId]
    );
    $paidRows = db_query(
        'SELECT COALESCE(SUM(amount_kobo), 0) AS paid_kobo FROM fashion_payments WHERE tenant_id = :tenant_id AND order_id = :order_id',
        ['order_id' => $orderId]
    );
    $totalKobo = (int) ($totals[0]['total_kobo'] ?? 0);
    $paidKobo = (int) ($paidRows[0]['paid_kobo'] ?? 0);
    $balance = $totalKobo - $paidKobo;
    $depositKobo = deposit_due_kobo($totalKobo, (int) deposit_shop()['percent']);
    $stages = db_select('fashion_stage_definitions', ['active' => 1], ['order' => 'sort_order ASC', 'limit' => 50]);
    $events = db_query(
        'SELECT stage_id FROM fashion_stage_events WHERE tenant_id = :tenant_id AND order_id = :order_id ORDER BY seq DESC LIMIT 1',
        ['order_id' => $orderId]
    );
    $currentId = (string) ($events[0]['stage_id'] ?? '');
    if ($currentId === '' && $stages !== []) {
        $currentId = (string) $stages[0]['id'];
    }
    $steps = [];
    $status = '';
    foreach ($stages as $stage) {
        $current = (string) $stage['id'] === $currentId;
        $steps[] = [
            'name' => (string) $stage['name'],
            'current' => $current,
        ];
        if ($current) {
            $status = (string) $stage['name'];
        }
    }
    $phone = (string) ($shop['phone'] ?? '');
    $digits = message_whatsapp_digits($phone);
    $orderNumber = (string) $order['order_number'];
    $chat = null;
    if ($digits !== null) {
        $chat = 'https://wa.me/' . $digits . '?text=' . rawurlencode('Order ' . $orderNumber);
    }
    $channel = (string) ($shop['payment_channel'] ?? '');
    if (!in_array($channel, ['bank', 'opay'], true)) {
        $channel = '';
    }
    return [
        'lang' => $lang,
        'shop_name' => (string) ($shop['name'] ?? ''),
        'shop_phone' => $phone,
        'order_number' => $orderNumber,
        'status' => $status,
        'expected_date' => message_format_date((string) $order['due_date'], $lang),
        'steps' => $steps,
        'balance' => format_naira($balance),
        'invoice' => [
            'description' => tracking_work_description($orderId),
            'total' => format_naira($totalKobo),
            'deposit' => format_naira($depositKobo),
            'paid' => format_naira($paidKobo),
            'balance' => format_naira($balance),
            'paid_full' => $balance === 0,
            'payments' => tracking_payment_rows($orderId, $lang),
        ],
        'payment_channel' => $channel,
        'payment_bank' => (string) ($shop['payment_bank'] ?? ''),
        'payment_account' => (string) ($shop['payment_account'] ?? ''),
        'payment_note' => (string) ($shop['payment_reference_note'] ?? ''),
        'chat_href' => $chat,
    ];
}

function tracking_work_description(string $orderId): string
{
    $items = db_query(
        'SELECT g.name AS name, i.quantity AS quantity
         FROM fashion_order_items i
         INNER JOIN fashion_garment_types g ON g.id = i.garment_type_id AND g.tenant_id = :tenant_id
         WHERE i.tenant_id = :tenant_id AND i.order_id = :order_id
         ORDER BY g.name ASC',
        ['order_id' => $orderId]
    );
    $parts = [];
    foreach ($items as $item) {
        $name = trim((string) ($item['name'] ?? ''));
        if ($name === '') {
            continue;
        }
        $parts[] = $name . ' x ' . (string) (int) ($item['quantity'] ?? 0);
    }
    return implode(', ', $parts);
}

function tracking_method_label(string $method): string
{
    if ($method === 'cash') {
        return t('cash');
    }
    if ($method === 'transfer') {
        return t('transfer');
    }
    return '';
}

/**
 * @return list<array{id: string, date: string, amount: string, method: string}>
 */
function tracking_payment_rows(string $orderId, string $lang): array
{
    $rows = db_query(
        'SELECT id, amount_kobo, method, created_at
         FROM fashion_payments
         WHERE tenant_id = :tenant_id AND order_id = :order_id
         ORDER BY seq ASC
         LIMIT 100',
        ['order_id' => $orderId]
    );
    $payments = [];
    foreach ($rows as $row) {
        $id = (string) ($row['id'] ?? '');
        if (!is_uuid($id)) {
            continue;
        }
        $payments[] = [
            'id' => $id,
            'date' => message_format_date(substr((string) ($row['created_at'] ?? ''), 0, 10), $lang),
            'amount' => format_naira((int) ($row['amount_kobo'] ?? 0)),
            'method' => tracking_method_label((string) ($row['method'] ?? '')),
        ];
    }
    return $payments;
}

/**
 * @return array{id: string, date: string, amount: string, method: string}|null
 */
function tracking_receipt_for(string $orderId, string $paymentId, string $lang): ?array
{
    if (!is_uuid($orderId) || !is_uuid($paymentId)) {
        return null;
    }
    $rows = db_query(
        'SELECT id, amount_kobo, method, created_at
         FROM fashion_payments
         WHERE tenant_id = :tenant_id AND order_id = :order_id AND id = :id
         LIMIT 1',
        ['order_id' => $orderId, 'id' => $paymentId]
    );
    if ($rows === []) {
        return null;
    }
    $row = $rows[0];
    return [
        'id' => (string) $row['id'],
        'date' => message_format_date(substr((string) ($row['created_at'] ?? ''), 0, 10), $lang),
        'amount' => format_naira((int) ($row['amount_kobo'] ?? 0)),
        'method' => tracking_method_label((string) ($row['method'] ?? '')),
    ];
}

/**
 * @param array<string, mixed> $page
 * @param array{date: string, amount: string, method: string}|null $receipt
 * @return list<string>
 */
function tracking_invoice_lines(array $page, ?array $receipt): array
{
    $invoice = is_array($page['invoice'] ?? null) ? $page['invoice'] : [];
    $lines = [(string) ($page['shop_name'] ?? '')];
    if ($receipt === null) {
        $lines[] = t('invoice');
        $lines[] = t('order_number') . ': ' . (string) ($page['order_number'] ?? '');
        $lines[] = t('invoice_work') . ': ' . (string) ($invoice['description'] ?? '');
        $lines[] = t('total') . ': ' . (string) ($invoice['total'] ?? '');
        $lines[] = t('invoice_deposit') . ': ' . (string) ($invoice['deposit'] ?? '');
        $lines[] = t('paid_so_far') . ': ' . (string) ($invoice['paid'] ?? '');
        $lines[] = t('balance') . ': ' . (string) ($invoice['balance'] ?? '');
        if (!empty($invoice['paid_full'])) {
            $lines[] = t('paid_in_full');
        }
        $payments = is_array($invoice['payments'] ?? null) ? $invoice['payments'] : [];
        if ($payments !== []) {
            $lines[] = t('payments_made');
            foreach ($payments as $payment) {
                if (!is_array($payment)) {
                    continue;
                }
                $lines[] = (string) ($payment['date'] ?? '') . '  ' . (string) ($payment['amount'] ?? '') . '  ' . (string) ($payment['method'] ?? '');
            }
        }
        return $lines;
    }
    $lines[] = t('download_receipt');
    $lines[] = t('order_number') . ': ' . (string) ($page['order_number'] ?? '');
    $lines[] = (string) ($receipt['date'] ?? '');
    $lines[] = (string) ($receipt['amount'] ?? '');
    $lines[] = (string) ($receipt['method'] ?? '');
    return $lines;
}

function tracking_file_text(string $line): string
{
    $line = str_replace('₦', 'NGN ', $line);
    $clean = preg_replace('/[^\x20-\x7E]/', '', $line);
    return is_string($clean) ? $clean : '';
}

function tracking_png(array $lines): string
{
    return paper_png(tracking_paper_document($lines));
}

function tracking_pdf(array $lines): string
{
    return paper_pdf(tracking_paper_document($lines));
}

/**
 * @param list<string> $lines
 * @return array{number: string, lines: list<string>, voided: bool}
 */
function tracking_paper_document(array $lines): array
{
    $drawn = [];
    foreach ($lines as $line) {
        $drawn[] = (string) $line;
    }
    return [
        'number' => 'page',
        'lines' => $drawn,
        'voided' => in_array(t('void_mark'), $drawn, true),
    ];
}
