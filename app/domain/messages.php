<?php

declare(strict_types=1);

require_once __DIR__ . '/modules.php';
require_once __DIR__ . '/deposits.php';
require_once __DIR__ . '/orders.php';

function message_template_keys(): array
{
    return [
        'order_confirmation',
        'deposit_reminder',
        'fitting_booking',
        'ready_for_pickup',
        'running_late',
        'receipt_share',
        'invoice_share',
        'invoice_link',
        'pay_balance_link',
    ];
}

function message_languages(): array
{
    return ['en', 'ha', 'yo', 'ig', 'pcm'];
}

function message_language_code(string $lang): string
{
    $lang = strtolower(trim($lang));
    if (!preg_match('/^[a-z]{2,8}$/', $lang)) {
        return 'en';
    }
    return $lang;
}

function message_default_bodies(): array
{
    return [
        'order_confirmation' => [
            'en' => 'Hello {customer_name}, {shop_name} has order {order_no}. Due {due_date}. Balance {balance}. Deposit due {deposit_due}. Fitting {fitting_time}. {tracking_link}',
            'ha' => 'Sannu {customer_name}, {shop_name} yana da oda {order_no}. Ranar kammalawa {due_date}. Ragowar kudi {balance}. Ajiya {deposit_due}. Gwaji {fitting_time}. {tracking_link}',
        ],
        'deposit_reminder' => [
            'en' => 'Hello {customer_name}, the deposit for order {order_no} at {shop_name} is {deposit_due}. Balance {balance}. Due {due_date}. {tracking_link}',
            'ha' => 'Sannu {customer_name}, ajiyar oda {order_no} a {shop_name} ita ce {deposit_due}. Ragowar kudi {balance}. Ranar kammalawa {due_date}. {tracking_link}',
        ],
        'fitting_booking' => [
            'en' => 'Hello {customer_name}, your fitting at {shop_name} for order {order_no} is {fitting_time}. Due {due_date}. {tracking_link}',
            'ha' => 'Sannu {customer_name}, gwajin dinki a {shop_name} na oda {order_no} yana {fitting_time}. Ranar kammalawa {due_date}. {tracking_link}',
        ],
        'ready_for_pickup' => [
            'en' => 'Hello {customer_name}, order {order_no} is ready for pickup at {shop_name}. Receipt balance {balance}. Due {due_date}. {tracking_link}',
            'ha' => 'Sannu {customer_name}, oda {order_no} ta shirya don ɗauka a {shop_name}. Rasit ragowar kudi {balance}. Ranar kammalawa {due_date}. {tracking_link}',
        ],
        'running_late' => [
            'en' => 'Hello {customer_name}, order {order_no} at {shop_name} will be ready on {due_date}. Balance {balance}. {tracking_link}',
            'ha' => 'Sannu {customer_name}, oda {order_no} a {shop_name} za ta kasance a {due_date}. Ragowar kudi {balance}. {tracking_link}',
        ],
        'receipt_share' => [
            'en' => 'Receipt {receipt_no} for order {order_no}. Amount {amount}. {void_note}{tracking_link}',
            'ha' => 'Rasit {receipt_no} na oda {order_no}. Adadi {amount}. {void_note}{tracking_link}',
        ],
        'invoice_share' => [
            'en' => 'Invoice for order {order_no}. Total {total}. Deposit due {deposit_due}. Paid {paid}. Balance {balance}. {tracking_link}',
            'ha' => 'Lissafi na oda {order_no}. Jimla {total}. Ajiya {deposit_due}. An biya {paid}. Ragowar kudi {balance}. {tracking_link}',
        ],
        'invoice_link' => [
            'en' => 'Hello {customer_name}, invoice for order {order_no} at {shop_name}. Balance {balance}. Deposit due {deposit_due}. Due {due_date}. {tracking_link}',
            'ha' => 'Sannu {customer_name}, lissafi don ɗauka na oda {order_no} a {shop_name}. Ragowar kudi {balance}. Ajiya {deposit_due}. Ranar kammalawa {due_date}. {tracking_link}',
        ],
        'pay_balance_link' => [
            'en' => 'Hello {customer_name}, pay the balance for order {order_no} at {shop_name}. Balance {balance}. Due {due_date}. {tracking_link}',
            'ha' => 'Sannu {customer_name}, biya ragowar kudi na oda {order_no} a {shop_name}. Ragowar kudi {balance}. Ranar kammalawa {due_date}. {tracking_link}',
        ],
    ];
}

function message_default_body(string $key, string $lang): ?string
{
    $bodies = message_default_bodies();
    $lang = message_language_code($lang);
    $body = $bodies[$key][$lang] ?? null;
    return is_string($body) && $body !== '' ? $body : null;
}

function message_catalog(string $lang): array
{
    static $cache = [];
    $lang = message_language_code($lang);
    if (isset($cache[$lang])) {
        return $cache[$lang];
    }
    $root = dirname(__DIR__, 2) . '/lang';
    $english = require $root . '/en.php';
    $chosen = is_array($english) ? $english : [];
    if ($lang !== 'en') {
        $file = $root . '/' . $lang . '.php';
        if (is_file($file)) {
            $loaded = require $file;
            if (is_array($loaded)) {
                $chosen = array_merge($chosen, $loaded);
            }
        }
    }
    $cache[$lang] = $chosen;
    return $chosen;
}

function message_format_date(string $ymd, string $lang): string
{
    $ymd = substr($ymd, 0, 10);
    $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $ymd, app_timezone());
    if ($parsed === false || $parsed->format('Y-m-d') !== $ymd) {
        return $ymd;
    }
    $catalog = message_catalog($lang);
    $monthKey = 'month_' . (int) $parsed->format('n');
    $month = $catalog[$monthKey] ?? $parsed->format('F');
    return $parsed->format('j') . ' ' . $month . ' ' . $parsed->format('Y');
}

function message_format_datetime(string $local, string $lang): string
{
    $parsed = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $local, app_timezone());
    if ($parsed === false) {
        $parsed = DateTimeImmutable::createFromFormat('Y-m-d H:i', $local, app_timezone());
    }
    if ($parsed === false) {
        return $local;
    }
    return message_format_date($parsed->format('Y-m-d'), $lang) . ' ' . $parsed->format('H:i');
}

function message_shop_body(string $key, string $lang): ?string
{
    if (current_tenant_id() === null) {
        return null;
    }
    $lang = message_language_code($lang);
    $rows = db_query(
        'SELECT body FROM fashion_message_templates
         WHERE tenant_id = :tenant_id AND template_key = :template_key AND language = :language
         LIMIT 1',
        ['template_key' => $key, 'language' => $lang]
    );
    if ($rows === []) {
        return null;
    }
    $body = trim((string) $rows[0]['body']);
    return $body === '' ? null : $body;
}

function message_resolved(string $key, string $lang): array
{
    $lang = message_language_code($lang);
    $shop = message_shop_body($key, $lang);
    if ($shop !== null) {
        return ['language' => $lang, 'body' => $shop];
    }
    $fallback = message_default_body($key, $lang);
    if ($fallback !== null) {
        return ['language' => $lang, 'body' => $fallback];
    }
    if ($lang !== 'en') {
        $shopEnglish = message_shop_body($key, 'en');
        if ($shopEnglish !== null) {
            return ['language' => 'en', 'body' => $shopEnglish];
        }
        $english = message_default_body($key, 'en');
        if ($english !== null) {
            return ['language' => 'en', 'body' => $english];
        }
    }
    throw new RuntimeException('Missing message template.');
}

function message_apply(string $body, string $lang, array $values): string
{
    $due = (string) ($values['due_date'] ?? '');
    $fitting = (string) ($values['fitting_time'] ?? '');
    return strtr($body, [
        '{customer_name}' => (string) ($values['customer_name'] ?? ''),
        '{shop_name}' => (string) ($values['shop_name'] ?? ''),
        '{order_no}' => (string) ($values['order_no'] ?? ''),
        '{due_date}' => $due !== '' ? message_format_date($due, $lang) : '',
        '{balance}' => format_naira((int) ($values['balance_kobo'] ?? 0)),
        '{deposit_due}' => format_naira((int) ($values['deposit_due_kobo'] ?? 0)),
        '{amount}' => format_naira((int) ($values['amount_kobo'] ?? 0)),
        '{total}' => format_naira((int) ($values['total_kobo'] ?? 0)),
        '{paid}' => format_naira((int) ($values['paid_kobo'] ?? 0)),
        '{receipt_no}' => (string) ($values['receipt_no'] ?? ''),
        '{void_note}' => (string) ($values['void_note'] ?? ''),
        '{fitting_time}' => $fitting !== '' ? message_format_datetime($fitting, $lang) : '',
        '{tracking_link}' => (string) ($values['tracking_link'] ?? ''),
    ]);
}

function message_fill(string $key, string $lang, array $values): string
{
    $resolved = message_resolved($key, $lang);
    return message_apply($resolved['body'], $resolved['language'], $values);
}

function message_whatsapp_digits(string $raw): ?string
{
    $local = phone_normalize($raw);
    if ($local === null) {
        return null;
    }
    return phone_intl($local);
}

function message_whatsapp_href(string $rawPhone, string $text): ?string
{
    $digits = message_whatsapp_digits($rawPhone);
    if ($digits === null) {
        return null;
    }
    return 'https://wa.me/' . $digits . '?text=' . rawurlencode($text);
}

function message_phone_problem(string $raw): ?string
{
    if (trim($raw) === '') {
        return 'no_phone';
    }
    if (message_whatsapp_digits($raw) === null) {
        return 'phone_unusable';
    }
    return null;
}

function message_customer_problem(string $customerId): ?string
{
    if (!is_uuid($customerId)) {
        return 'no_phone';
    }
    $rows = db_select('fashion_customers', ['id' => $customerId], ['limit' => 1]);
    if ($rows === []) {
        return 'no_phone';
    }
    return message_phone_problem((string) $rows[0]['phone']);
}

function message_customer_lang(string $customerId): string
{
    $saved = '';
    if (is_uuid($customerId)) {
        $rows = db_select('fashion_customers', ['id' => $customerId], ['limit' => 1]);
        if ($rows !== []) {
            $saved = strtolower(trim((string) ($rows[0]['lang'] ?? '')));
        }
    }
    if (in_array($saved, ['en', 'ha'], true)) {
        return $saved;
    }
    $owners = db_select('fashion_users', ['role' => 'owner'], ['limit' => 1]);
    $shop = strtolower(trim((string) ($owners[0]['lang'] ?? 'en')));
    return in_array($shop, ['en', 'ha'], true) ? $shop : 'en';
}

function message_link_is_local(string $link): bool
{
    if ($link === '' || preg_match('#^https?://#i', $link) !== 1) {
        return true;
    }
    $host = parse_url($link, PHP_URL_HOST);
    if (!is_string($host) || $host === '') {
        return true;
    }
    $host = strtolower($host);
    if ($host === 'localhost' || str_ends_with($host, '.localhost')) {
        return true;
    }
    return filter_var($host, FILTER_VALIDATE_IP) !== false;
}

function message_pay_balance_available(): bool
{
    if (!module_enabled('fashion.finance') || !module_enabled('fashion.online_payments')) {
        return false;
    }
    $rows = db_select('fashion_payment_settings', [], ['limit' => 1]);
    return $rows !== [] && (string) $rows[0]['mode'] === 'paystack';
}

function message_link_template(int $balanceKobo, bool $payBalanceAvailable): string
{
    if ($balanceKobo <= 0) {
        return 'ready_for_pickup';
    }
    if ($payBalanceAvailable) {
        return 'pay_balance_link';
    }
    return 'invoice_link';
}

function message_link_label(string $key): string
{
    if ($key === 'ready_for_pickup') {
        return 'send_ready_for_pickup';
    }
    return 'send_invoice';
}

function message_order_row(string $orderId): ?array
{
    if (!is_uuid($orderId)) {
        return null;
    }
    $rows = db_query(
        'SELECT o.id, o.order_number, o.customer_id, o.due_date, c.name AS customer_name
         FROM fashion_orders o
         INNER JOIN fashion_customers c ON c.id = o.customer_id AND c.tenant_id = :tenant_id
         WHERE o.tenant_id = :tenant_id AND o.id = :order_id
         LIMIT 1',
        ['order_id' => $orderId]
    );
    return $rows[0] ?? null;
}

function message_latest_fitting_local(string $orderId): string
{
    $rows = db_query(
        'SELECT starts_at FROM fashion_fittings
         WHERE tenant_id = :tenant_id AND order_id = :order_id AND status <> :cancelled
         ORDER BY starts_at DESC
         LIMIT 1',
        ['order_id' => $orderId, 'cancelled' => 'cancelled']
    );
    if ($rows === []) {
        return '';
    }
    $parsed = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', (string) $rows[0]['starts_at'], new DateTimeZone('UTC'));
    if ($parsed === false) {
        return '';
    }
    return $parsed->setTimezone(app_timezone())->format('Y-m-d H:i:s');
}

function message_values_for_order(string $orderId, ?string $fittingLocal): array
{
    $row = message_order_row($orderId);
    if ($row === null) {
        http_response_code(404);
        throw new HttpException(t('not_found'), 404);
    }
    $money = deposit_money($orderId);
    $due = deposit_due_kobo($money['total_kobo'], deposit_shop()['percent']);
    $paidKobo = (int) $money['paid_kobo'];
    $totalKobo = (int) $money['total_kobo'];
    $tenant = db_select('fashion_tenants', ['id' => (string) current_tenant_id()], ['limit' => 1]);
    $shopName = $tenant === [] ? '' : (string) $tenant[0]['name'];
    if ($fittingLocal === null) {
        $fittingLocal = message_latest_fitting_local($orderId);
    }
    require_once __DIR__ . '/tracking.php';
    $trackingToken = tracking_issue($orderId);
    return [
        'customer_name' => (string) $row['customer_name'],
        'shop_name' => $shopName,
        'order_no' => (string) $row['order_number'],
        'due_date' => substr((string) $row['due_date'], 0, 10),
        'balance_kobo' => $totalKobo - $paidKobo,
        'total_kobo' => $totalKobo,
        'paid_kobo' => $paidKobo,
        'deposit_due_kobo' => $due,
        'fitting_time' => $fittingLocal,
        'tracking_link' => is_string($trackingToken) ? tracking_absolute_link($trackingToken) : '',
        'customer_id' => (string) $row['customer_id'],
    ];
}

function message_template_store(string $key, string $lang, string $body): void
{
    $rows = db_query(
        'SELECT id FROM fashion_message_templates
         WHERE tenant_id = :tenant_id AND template_key = :template_key AND language = :language
         LIMIT 1',
        ['template_key' => $key, 'language' => $lang]
    );
    $userId = current_user_id();
    if ($userId === null) {
        throw new RuntimeException('Missing session.');
    }
    if ($body === '') {
        if ($rows !== []) {
            db_delete('fashion_message_templates', ['id' => (string) $rows[0]['id']]);
        }
        return;
    }
    $now = app_now()->format('Y-m-d H:i:s');
    if ($rows === []) {
        db_insert('fashion_message_templates', [
            'template_key' => $key,
            'language' => $lang,
            'body' => $body,
            'updated_by' => $userId,
            'updated_at' => $now,
        ]);
        return;
    }
    db_update('fashion_message_templates', [
        'body' => $body,
        'updated_by' => $userId,
        'updated_at' => $now,
    ], ['id' => (string) $rows[0]['id']]);
}

function message_template_save(string $key, string $lang, string $body): array
{
    if (!deposit_is_owner()) {
        http_response_code(403);
        throw new HttpException(t('not_allowed'), 403);
    }
    if (!in_array($key, message_template_keys(), true) || !in_array($lang, message_languages(), true)) {
        return ['error' => 'required'];
    }
    $body = trim($body);
    if (strlen($body) > 2000) {
        return ['error' => 'message_template_long'];
    }
    message_template_store($key, $lang, $body);
    return [];
}

function message_templates_save_all(array $posted): array
{
    if (!deposit_is_owner()) {
        http_response_code(403);
        throw new HttpException(t('not_allowed'), 403);
    }
    $clean = [];
    foreach (message_template_keys() as $key) {
        $group = $posted[$key] ?? [];
        if (!is_array($group)) {
            return ['error' => 'required'];
        }
        foreach (message_languages() as $lang) {
            $raw = $group[$lang] ?? '';
            if (!is_string($raw)) {
                return ['error' => 'required'];
            }
            $body = trim($raw);
            if (strlen($body) > 2000) {
                return ['error' => 'message_template_long'];
            }
            $clean[$key][$lang] = $body;
        }
    }
    $own = !db()->inTransaction();
    if ($own) {
        db_begin();
    }
    try {
        foreach ($clean as $key => $langs) {
            foreach ($langs as $lang => $body) {
                message_template_store($key, $lang, $body);
            }
        }
        if ($own) {
            db_commit();
        }
    } catch (Throwable $e) {
        if ($own) {
            db_rollback();
        }
        throw $e;
    }
    return [];
}

function message_editor_body(string $key, string $lang): string
{
    $shop = message_shop_body($key, $lang);
    if ($shop !== null) {
        return $shop;
    }
    if (!in_array($lang, ['en', 'ha'], true)) {
        return '';
    }
    return message_default_body($key, $lang) ?? '';
}

function message_language_label(string $code): string
{
    if ($code === 'en') {
        return t('english');
    }
    if ($code === 'ha') {
        return t('hausa');
    }
    if ($code === 'yo') {
        return t('yoruba');
    }
    if ($code === 'ig') {
        return t('igbo');
    }
    if ($code === 'pcm') {
        return t('pidgin');
    }
    return $code;
}

function message_editor(): array
{
    $rows = [];
    foreach (message_template_keys() as $key) {
        $languages = [];
        foreach (message_languages() as $lang) {
            $languages[] = [
                'code' => $lang,
                'label' => message_language_label($lang),
                'body' => message_editor_body($key, $lang),
            ];
        }
        $rows[] = [
            'key' => $key,
            'label' => t('template_' . $key),
            'languages' => $languages,
        ];
    }
    return $rows;
}

function message_insert_prepared(string $orderId, string $key, string $eventKey, ?string $fittingLocal, ?string $changeNote): string
{
    $values = message_values_for_order($orderId, $fittingLocal);
    $resolved = message_resolved($key, current_lang());
    $userId = current_user_id();
    if ($userId === null) {
        throw new RuntimeException('Missing session.');
    }
    return db_insert('fashion_messages', [
        'order_id' => $orderId,
        'customer_id' => $values['customer_id'],
        'template_key' => $key,
        'language' => $resolved['language'],
        'body' => message_apply($resolved['body'], $resolved['language'], $values),
        'status' => 'prepared',
        'event_key' => $eventKey,
        'prepared_id' => null,
        'change_note' => $changeNote,
        'user_id' => $userId,
        'created_at' => app_now()->format('Y-m-d H:i:s'),
    ]);
}

function message_prepare_once(string $orderId, string $key, string $eventKey, ?string $fittingLocal, ?string $changeNote): string
{
    if (!module_enabled('fashion.communication')) {
        return '';
    }
    if (!in_array($key, message_template_keys(), true)) {
        return '';
    }
    $existing = db_query(
        'SELECT id FROM fashion_messages
         WHERE tenant_id = :tenant_id AND event_key = :event_key AND status = :status
         LIMIT 1',
        ['event_key' => $eventKey, 'status' => 'prepared']
    );
    if ($existing !== []) {
        return (string) $existing[0]['id'];
    }
    return message_insert_prepared($orderId, $key, $eventKey, $fittingLocal, $changeNote);
}

/**
 * Prepare one WhatsApp text. Extra values override the order totals for this text only.
 *
 * @param array<string, mixed> $extra
 */
function message_prepare_event(string $orderId, string $key, string $eventKey, array $extra): string
{
    if (!module_enabled('fashion.communication')) {
        return '';
    }
    if (!in_array($key, message_template_keys(), true)) {
        return '';
    }
    $values = message_values_for_order($orderId, null);
    foreach ($extra as $name => $value) {
        $values[$name] = $value;
    }
    $resolved = message_resolved($key, current_lang());
    $userId = current_user_id();
    if ($userId === null) {
        throw new RuntimeException('Missing session.');
    }
    return db_insert('fashion_messages', [
        'order_id' => $orderId,
        'customer_id' => $values['customer_id'],
        'template_key' => $key,
        'language' => $resolved['language'],
        'body' => message_apply($resolved['body'], $resolved['language'], $values),
        'status' => 'prepared',
        'event_key' => $eventKey,
        'prepared_id' => null,
        'change_note' => null,
        'user_id' => $userId,
        'created_at' => app_now()->format('Y-m-d H:i:s'),
    ]);
}

function message_latest_share(string $orderId, string $templateKey, string $eventPrefix): ?string
{
    if (!module_enabled('fashion.communication') || !is_uuid($orderId)) {
        return null;
    }
    $rows = db_query(
        'SELECT m.body, c.phone AS customer_phone
         FROM fashion_messages m
         INNER JOIN fashion_orders o ON o.id = m.order_id AND o.tenant_id = :tenant_id
         INNER JOIN fashion_customers c ON c.id = o.customer_id AND c.tenant_id = :tenant_id
         WHERE m.tenant_id = :tenant_id AND m.order_id = :order_id
           AND m.template_key = :template_key AND m.event_key LIKE :event_prefix
         ORDER BY m.created_at DESC, m.id DESC
         LIMIT 1',
        [
            'order_id' => $orderId,
            'template_key' => $templateKey,
            'event_prefix' => $eventPrefix,
        ]
    );
    if ($rows === []) {
        return null;
    }
    return message_whatsapp_href((string) $rows[0]['customer_phone'], (string) $rows[0]['body']);
}

function message_queue_order(string $orderId): void
{
    if (!module_enabled('fashion.communication')) {
        return;
    }
    message_prepare_once($orderId, 'order_confirmation', 'order_confirmation:' . $orderId, null, null);
    message_queue_deposit($orderId);
}

function message_queue_deposit(string $orderId): void
{
    if (!module_enabled('fashion.communication')) {
        return;
    }
    if (message_order_row($orderId) === null) {
        return;
    }
    $money = deposit_money($orderId);
    $due = deposit_due_kobo($money['total_kobo'], deposit_shop()['percent']);
    if ($due <= 0 || $money['paid_kobo'] >= $due) {
        return;
    }
    message_prepare_once($orderId, 'deposit_reminder', 'deposit_reminder:' . $orderId, null, null);
}

function message_queue_deposits(): void
{
    if (!module_enabled('fashion.communication')) {
        return;
    }
    if (deposit_shop()['percent'] <= 0) {
        return;
    }
    $orders = db_query('SELECT id FROM fashion_orders WHERE tenant_id = :tenant_id');
    foreach ($orders as $order) {
        message_queue_deposit((string) $order['id']);
    }
}

function message_queue_fitting(string $orderId, string $fittingId, string $fittingLocal): void
{
    if (!is_uuid($fittingId)) {
        return;
    }
    message_prepare_once($orderId, 'fitting_booking', 'fitting_booking:' . $fittingId, $fittingLocal, null);
}

function message_earlier_prepared(string $orderId, string $key): ?array
{
    if (!is_uuid($orderId)) {
        return null;
    }
    $rows = db_query(
        'SELECT id, body, created_at FROM fashion_messages
         WHERE tenant_id = :tenant_id AND order_id = :order_id AND template_key = :template_key AND status = :status
         ORDER BY created_at ASC, id ASC
         LIMIT 1',
        ['order_id' => $orderId, 'template_key' => $key, 'status' => 'prepared']
    );
    if ($rows === []) {
        return null;
    }
    $then = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', (string) $rows[0]['created_at'], app_timezone());
    $minutes = 0;
    if ($then instanceof DateTimeImmutable) {
        $minutes = intdiv(max(0, app_now()->getTimestamp() - $then->getTimestamp()), 60);
    }
    return [
        'id' => (string) $rows[0]['id'],
        'body' => (string) $rows[0]['body'],
        'prompt' => t('send_again_prompt', ['minutes' => (string) $minutes]),
    ];
}

function message_link_state(string $orderId): ?array
{
    if (!module_enabled('fashion.communication') || !is_uuid($orderId)) {
        return null;
    }
    $row = message_order_row($orderId);
    if ($row === null) {
        return null;
    }
    require_once __DIR__ . '/tracking.php';
    $money = deposit_money($orderId);
    $balance = (int) $money['total_kobo'] - (int) $money['paid_kobo'];
    $key = message_link_template($balance, message_pay_balance_available());
    $stored = db_select('fashion_orders', ['id' => $orderId], ['limit' => 1]);
    $token = $stored === [] ? '' : (string) ($stored[0]['tracking_token'] ?? '');
    $link = $token !== '' ? tracking_absolute_link($token) : '';
    return [
        'template_key' => $key,
        'label' => message_link_label($key),
        'local_warning' => message_link_is_local($link),
        'earlier' => message_earlier_prepared($orderId, $key),
        'action' => url('/orders/' . $orderId . '/messages/link'),
    ];
}

function message_prepare_customer(string $orderId, string $key): string
{
    require_module('fashion.communication');
    require_once __DIR__ . '/tracking.php';
    if (tracking_subscription_expired()) {
        http_response_code(402);
        throw new HttpException(t('subscription_expired'), 402);
    }
    $row = message_order_row($orderId);
    if ($row === null) {
        http_response_code(404);
        throw new HttpException(t('not_found'), 404);
    }
    if (!in_array($key, ['invoice_link', 'pay_balance_link', 'ready_for_pickup'], true)) {
        http_response_code(404);
        throw new HttpException(t('not_found'), 404);
    }
    $values = message_values_for_order($orderId, null);
    $lang = message_customer_lang((string) $row['customer_id']);
    $resolved = message_resolved($key, $lang);
    $userId = current_user_id();
    if ($userId === null) {
        throw new RuntimeException('Missing session.');
    }
    return db_insert('fashion_messages', [
        'order_id' => $orderId,
        'customer_id' => (string) $row['customer_id'],
        'template_key' => $key,
        'language' => $resolved['language'],
        'body' => message_apply($resolved['body'], $resolved['language'], $values),
        'status' => 'prepared',
        'event_key' => $key . ':' . $orderId . ':' . uuid(),
        'prepared_id' => null,
        'change_note' => null,
        'user_id' => $userId,
        'created_at' => app_now()->format('Y-m-d H:i:s'),
    ]);
}

function message_send_link(string $orderId): string
{
    $row = message_order_row($orderId);
    if ($row === null) {
        http_response_code(404);
        throw new HttpException(t('not_found'), 404);
    }
    $money = deposit_money($orderId);
    $balance = (int) $money['total_kobo'] - (int) $money['paid_kobo'];
    $key = message_link_template($balance, message_pay_balance_available());
    return message_prepare_customer($orderId, $key);
}

function message_queue_ready(string $orderId): string
{
    if (!module_enabled('fashion.communication')) {
        return '';
    }
    if (message_order_row($orderId) === null) {
        return '';
    }
    return message_prepare_once($orderId, 'ready_for_pickup', 'ready_for_pickup:' . $orderId, null, null);
}

function message_running_late(string $orderId, string $newDate): array
{
    require_module('fashion.communication');
    $row = message_order_row($orderId);
    if ($row === null) {
        http_response_code(404);
        throw new HttpException(t('not_found'), 404);
    }
    $newDate = trim($newDate);
    $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $newDate, app_timezone());
    if ($parsed === false || $parsed->format('Y-m-d') !== $newDate) {
        return ['error' => 'late_date_invalid'];
    }
    $old = substr((string) $row['due_date'], 0, 10);
    $own = !db()->inTransaction();
    if ($own) {
        db_begin();
    }
    try {
        db_update('fashion_orders', ['due_date' => $newDate], ['id' => $orderId]);
        $stamp = app_now()->format('Y-m-d H:i:s');
        $id = message_insert_prepared(
            $orderId,
            'running_late',
            'running_late:' . $orderId . ':' . $newDate . ':' . $stamp,
            null,
            $old . ' -> ' . $newDate
        );
        if ($own) {
            db_commit();
        }
    } catch (Throwable $e) {
        if ($own) {
            db_rollback();
        }
        throw $e;
    }
    return ['id' => $id];
}

function message_mark_sent(string $messageId): array
{
    require_module('fashion.communication');
    if (!is_uuid($messageId)) {
        http_response_code(404);
        throw new HttpException(t('not_found'), 404);
    }
    $rows = db_query(
        'SELECT id, order_id, customer_id, template_key, language, body, status, user_id, created_at
         FROM fashion_messages
         WHERE tenant_id = :tenant_id AND id = :id
         LIMIT 1',
        ['id' => $messageId]
    );
    if ($rows === [] || (string) $rows[0]['status'] !== 'prepared') {
        http_response_code(404);
        throw new HttpException(t('not_found'), 404);
    }
    $sent = db_query(
        'SELECT id FROM fashion_messages
         WHERE tenant_id = :tenant_id AND prepared_id = :prepared_id AND status = :status
         LIMIT 1',
        ['prepared_id' => $messageId, 'status' => 'marked_sent']
    );
    if ($sent !== []) {
        return ['error' => 'already_marked_sent'];
    }
    $userId = current_user_id();
    if ($userId === null) {
        throw new RuntimeException('Missing session.');
    }
    $id = db_insert('fashion_messages', [
        'order_id' => (string) $rows[0]['order_id'],
        'customer_id' => (string) $rows[0]['customer_id'],
        'template_key' => (string) $rows[0]['template_key'],
        'language' => (string) $rows[0]['language'],
        'body' => (string) $rows[0]['body'],
        'status' => 'marked_sent',
        'event_key' => 'sent:' . $messageId,
        'prepared_id' => $messageId,
        'change_note' => null,
        'user_id' => $userId,
        'created_at' => app_now()->format('Y-m-d H:i:s'),
    ]);
    return ['id' => $id];
}

function message_prepared_count(string $orderId, string $key): int
{
    $rows = db_query(
        'SELECT COUNT(*) AS total FROM fashion_messages
         WHERE tenant_id = :tenant_id AND order_id = :order_id AND template_key = :template_key AND status = :status',
        ['order_id' => $orderId, 'template_key' => $key, 'status' => 'prepared']
    );
    return (int) ($rows[0]['total'] ?? 0);
}

function message_prepared_total(string $key): int
{
    $rows = db_query(
        'SELECT COUNT(*) AS total FROM fashion_messages
         WHERE tenant_id = :tenant_id AND template_key = :template_key AND status = :status',
        ['template_key' => $key, 'status' => 'prepared']
    );
    return (int) ($rows[0]['total'] ?? 0);
}

function message_change_label(?string $note): ?string
{
    if ($note === null || preg_match('/^(\d{4}-\d{2}-\d{2}) -> (\d{4}-\d{2}-\d{2})$/', $note, $matches) !== 1) {
        return null;
    }
    return t('due_changed', ['from' => $matches[1], 'to' => $matches[2]]);
}

function message_present(array $row): array
{
    $phone = (string) ($row['customer_phone'] ?? '');
    $problem = message_phone_problem($phone);
    $note = $row['change_note'] ?? null;
    $note = is_string($note) && $note !== '' ? $note : null;
    $userName = $row['user_name'] ?? '';
    return [
        'id' => (string) $row['id'],
        'order_id' => (string) $row['order_id'],
        'order_number' => (string) $row['order_number'],
        'customer_name' => (string) $row['customer_name'],
        'template_key' => (string) $row['template_key'],
        'label' => t('template_' . (string) $row['template_key']),
        'language' => (string) $row['language'],
        'body' => (string) $row['body'],
        'status' => (string) $row['status'],
        'change_note' => $note,
        'change_label' => message_change_label($note),
        'created_at' => message_format_datetime((string) $row['created_at'], current_lang()),
        'user_name' => is_string($userName) ? $userName : '',
        'open_href' => $problem === null ? message_whatsapp_href($phone, (string) $row['body']) : null,
        'problem' => $problem,
    ];
}

function message_list_parts(string $mode, string $orderId): array
{
    $params = [];
    if ($mode === 'queue') {
        $params['status'] = 'prepared';
        $where = 'WHERE m.tenant_id = :tenant_id AND m.status = :status AND NOT EXISTS (
            SELECT 1 FROM fashion_messages sent_row
            WHERE sent_row.tenant_id = :tenant_id AND sent_row.prepared_id = m.id AND sent_row.status = :sent_status
        )';
        $params['sent_status'] = 'marked_sent';
    } else {
        $params['status'] = 'marked_sent';
        $where = 'WHERE m.tenant_id = :tenant_id AND m.status = :status';
    }
    if ($orderId !== '') {
        $where .= ' AND m.order_id = :order_id';
        $params['order_id'] = $orderId;
    }
    return [$where, $params];
}

function message_list_count(string $mode, string $orderId): int
{
    require_module('fashion.communication');
    [$where, $params] = message_list_parts($mode, $orderId);
    $rows = db_query('SELECT COUNT(*) AS total FROM fashion_messages m ' . $where, $params);
    return (int) ($rows[0]['total'] ?? 0);
}

function message_list_rows(string $mode, int $page, string $orderId): array
{
    require_module('fashion.communication');
    [$where, $params] = message_list_parts($mode, $orderId);
    $page = list_page($page);
    $params['row_limit'] = list_page_size();
    $params['row_offset'] = ($page - 1) * list_page_size();
    $sql = 'SELECT m.id, m.order_id, m.template_key, m.language, m.body, m.status, m.change_note, m.created_at,
            o.order_number, c.name AS customer_name, c.phone AS customer_phone, u.name AS user_name
         FROM fashion_messages m
         INNER JOIN fashion_orders o ON o.id = m.order_id AND o.tenant_id = :tenant_id
         INNER JOIN fashion_customers c ON c.id = m.customer_id AND c.tenant_id = :tenant_id
         LEFT JOIN fashion_users u ON u.id = m.user_id AND u.tenant_id = :tenant_id
         ' . $where . '
         ORDER BY m.created_at DESC, m.id DESC
         LIMIT :row_limit OFFSET :row_offset';
    $rows = [];
    foreach (db_query($sql, $params) as $row) {
        $rows[] = message_present($row);
    }
    return $rows;
}

function message_to_send_list(int $page, string $orderId): array
{
    return message_list_rows('queue', $page, $orderId);
}

function message_sent_list(int $page, string $orderId): array
{
    return message_list_rows('sent', $page, $orderId);
}

function message_to_send_count(string $orderId): int
{
    return message_list_count('queue', $orderId);
}

function message_sent_count(string $orderId): int
{
    return message_list_count('sent', $orderId);
}

function message_load(string $messageId): ?array
{
    if (!is_uuid($messageId)) {
        return null;
    }
    $rows = db_query(
        'SELECT m.id, m.order_id, m.template_key, m.language, m.body, m.status, m.change_note, m.created_at,
            o.order_number, c.name AS customer_name, c.phone AS customer_phone, u.name AS user_name
         FROM fashion_messages m
         INNER JOIN fashion_orders o ON o.id = m.order_id AND o.tenant_id = :tenant_id
         INNER JOIN fashion_customers c ON c.id = m.customer_id AND c.tenant_id = :tenant_id
         LEFT JOIN fashion_users u ON u.id = m.user_id AND u.tenant_id = :tenant_id
         WHERE m.tenant_id = :tenant_id AND m.id = :id
         LIMIT 1',
        ['id' => $messageId]
    );
    if ($rows === []) {
        return null;
    }
    $present = message_present($rows[0]);
    $sent = db_query(
        'SELECT id FROM fashion_messages
         WHERE tenant_id = :tenant_id AND prepared_id = :prepared_id AND status = :status
         LIMIT 1',
        ['prepared_id' => $messageId, 'status' => 'marked_sent']
    );
    $present['already_sent'] = (string) $rows[0]['status'] === 'marked_sent' || $sent !== [];
    return $present;
}

function message_open(string $messageId): array
{
    require_module('fashion.communication');
    $row = message_load($messageId);
    if ($row === null) {
        http_response_code(404);
        throw new HttpException(t('not_found'), 404);
    }
    return $row;
}

function message_page_path(string $orderId, int $toPage, int $sentPage): string
{
    $params = [];
    if ($orderId !== '') {
        $params['order'] = $orderId;
    }
    if ($toPage > 1) {
        $params['to_page'] = (string) $toPage;
    }
    if ($sentPage > 1) {
        $params['sent_page'] = (string) $sentPage;
    }
    if ($params === []) {
        return '/messages';
    }
    return '/messages?' . http_build_query($params);
}
