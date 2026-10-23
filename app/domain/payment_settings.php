<?php

declare(strict_types=1);

require_once __DIR__ . '/modules.php';
require_once dirname(__DIR__) . '/payments/PaystackClient.php';
require_once dirname(__DIR__) . '/payments/ManualProvider.php';
require_once dirname(__DIR__) . '/payments/PaystackProvider.php';

function payment_placeholder_domain(): string
{
    return trim((string) (app_config()['payment_placeholder_domain'] ?? ''));
}

function payment_settings_owner(): void
{
    require_login();
    $rows = db_select('fashion_users', ['id' => (string) current_user_id()]);
    if ($rows === [] || (string) $rows[0]['role'] !== 'owner') {
        http_response_code(403);
        throw new HttpException(t('not_allowed'), 403);
    }
}

function payment_mode(): string
{
    require_module('fashion.finance');
    $rows = db_select('fashion_payment_settings', [], ['limit' => 1]);
    if ($rows === [] || (string) $rows[0]['mode'] !== 'paystack') {
        return 'manual';
    }
    return 'paystack';
}

function payment_links_enabled(): bool
{
    if (!module_enabled('fashion.finance') || !module_enabled('fashion.online_payments')) {
        return false;
    }
    return payment_mode() === 'paystack';
}

function payment_placeholder_host(): string
{
    $domain = payment_placeholder_domain();
    if ($domain === '') {
        return '';
    }
    if (preg_match('#^https?://#i', $domain) === 1) {
        $host = parse_url($domain, PHP_URL_HOST);
        return is_string($host) ? strtolower($host) : '';
    }
    $host = strtolower(explode('/', $domain, 2)[0]);
    $host = explode(':', $host, 2)[0];
    return $host;
}

function payment_public_origin(): string
{
    $domain = payment_placeholder_domain();
    if ($domain === '') {
        return '';
    }
    if (preg_match('#^https?://#i', $domain) !== 1) {
        $domain = 'https://' . $domain;
    }
    $parts = parse_url($domain);
    if (!is_array($parts) || !isset($parts['scheme'], $parts['host'])) {
        return '';
    }
    if (!in_array(strtolower((string) $parts['scheme']), ['https', 'http'], true)) {
        return '';
    }
    $origin = strtolower((string) $parts['scheme']) . '://' . strtolower((string) $parts['host']);
    if (isset($parts['port'])) {
        $origin .= ':' . (int) $parts['port'];
    }
    return $origin;
}

function payment_callback_url(): string
{
    $origin = payment_public_origin();
    if ($origin === '') {
        return '';
    }
    return $origin . url('/pay/return');
}

function payment_email_is_placeholder(string $email): bool
{
    $host = payment_placeholder_host();
    if ($host === '') {
        return false;
    }
    return preg_match('/^pay-[a-f0-9]{16}@' . preg_quote($host, '/') . '$/', strtolower($email)) === 1;
}

function payment_provider(): PaymentProvider
{
    if (payment_links_enabled()) {
        return new PaystackProvider();
    }
    return new ManualProvider();
}

function payment_settings_blank(): array
{
    return [
        'id' => '',
        'mode' => 'manual',
        'paystack_public_key' => '',
        'paystack_secret_key_encrypted' => '',
        'paystack_key_last4' => '',
        'webhook_token' => '',
        'connected_at' => null,
        'updated_by' => '',
    ];
}

function payment_settings_read(): array
{
    require_module('fashion.finance');
    $rows = db_select('fashion_payment_settings', [], ['limit' => 1]);
    if ($rows === []) {
        return payment_settings_blank();
    }
    return $rows[0];
}

function payment_settings_view(): array
{
    $row = payment_settings_read();
    $last4 = (string) $row['paystack_key_last4'];
    $token = (string) $row['webhook_token'];
    return [
        'mode' => (string) $row['mode'] === 'paystack' ? 'paystack' : 'manual',
        'public_key' => (string) $row['paystack_public_key'],
        'secret_mask' => $last4 === '' ? '' : '••••' . $last4,
        'webhook_url' => $token === '' ? '' : url('/webhooks/paystack/' . $token),
        'links_enabled' => payment_links_enabled(),
        'domain_ready' => payment_placeholder_domain() !== '',
    ];
}

function payment_settings_save(string $mode, string $publicKey, string $secret): array
{
    require_module('fashion.finance');
    payment_settings_owner();
    if (!in_array($mode, ['manual', 'paystack'], true)) {
        return ['error' => 'required'];
    }
    if ($mode === 'paystack' && !module_enabled('fashion.online_payments')) {
        return ['error' => 'online_payments_off'];
    }
    if ($mode === 'paystack' && payment_placeholder_domain() === '') {
        return ['error' => 'payment_domain_missing'];
    }
    $publicKey = trim($publicKey);
    $secret = trim($secret);
    $row = payment_settings_read();
    $persisted = (string) $row['id'] !== '';
    $keyUpdates = [];
    if ($mode === 'paystack') {
        $nextPublic = $publicKey !== '' ? $publicKey : (string) $row['paystack_public_key'];
        $hasSecret = $secret !== '' || (string) $row['paystack_secret_key_encrypted'] !== '';
        if ($nextPublic === '' || !$hasSecret) {
            return ['error' => 'required'];
        }
        if (!preg_match('/^pk_[A-Za-z0-9_]{8,}$/', $nextPublic)) {
            return ['error' => 'invalid_paystack_key'];
        }
        if ($secret !== '' && !preg_match('/^sk_[A-Za-z0-9_]{8,}$/', $secret)) {
            return ['error' => 'invalid_paystack_key'];
        }
        if ($nextPublic !== (string) $row['paystack_public_key']) {
            $keyUpdates['paystack_public_key'] = $nextPublic;
        }
        if ($secret !== '') {
            $keyUpdates['paystack_secret_key_encrypted'] = PaystackClient::seal($secret);
            $keyUpdates['paystack_key_last4'] = substr($secret, -4);
            if ((string) $row['connected_at'] === '') {
                $keyUpdates['connected_at'] = date('Y-m-d H:i:s');
            }
        }
    }
    $modeChanged = (string) $row['mode'] !== $mode;
    if ($persisted && !$modeChanged && $keyUpdates === []) {
        return ['ok' => true];
    }
    $userId = current_user_id();
    if ($userId === null) {
        throw new RuntimeException('Missing session.');
    }
    $fields = array_merge($keyUpdates, [
        'mode' => $mode,
        'updated_by' => $userId,
    ]);
    if ($persisted) {
        db_update('fashion_payment_settings', $fields, ['id' => $row['id']]);
    } else {
        db_insert('fashion_payment_settings', array_merge([
            'paystack_public_key' => (string) $row['paystack_public_key'],
            'paystack_secret_key_encrypted' => (string) $row['paystack_secret_key_encrypted'],
            'paystack_key_last4' => (string) $row['paystack_key_last4'],
            'webhook_token' => bin2hex(random_bytes(16)),
            'connected_at' => $row['connected_at'],
        ], $fields));
    }
    if ($modeChanged) {
        payment_settings_log('payment_mode', $mode === 'paystack' ? 1 : 0);
    }
    if ($keyUpdates !== []) {
        payment_settings_log('payment_keys', 1);
    }
    return ['ok' => true];
}

function payment_settings_log(string $action, int $enabled): void
{
    $userId = current_user_id();
    if ($userId === null) {
        throw new RuntimeException('Missing session.');
    }
    db_insert('fashion_activity_log', [
        'user_id' => $userId,
        'action' => $action,
        'module_key' => 'fashion.online_payments',
        'enabled' => $enabled,
        'created_at' => date('Y-m-d H:i:s'),
    ]);
}
