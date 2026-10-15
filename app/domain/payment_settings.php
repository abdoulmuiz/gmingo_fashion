<?php

declare(strict_types=1);

require_once __DIR__ . '/modules.php';
require_once dirname(__DIR__) . '/payments/PaystackClient.php';
require_once dirname(__DIR__) . '/payments/ManualProvider.php';

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

function payment_provider(): PaymentProvider
{
    return new ManualProvider();
}

function payment_settings_ensure(): array
{
    require_module('fashion.finance');
    $rows = db_select('fashion_payment_settings', [], ['limit' => 1]);
    if ($rows !== []) {
        return $rows[0];
    }
    $userId = current_user_id();
    if ($userId === null) {
        throw new RuntimeException('Missing session.');
    }
    $id = db_insert('fashion_payment_settings', [
        'mode' => 'manual',
        'paystack_public_key' => '',
        'paystack_secret_key_encrypted' => '',
        'paystack_key_last4' => '',
        'webhook_token' => bin2hex(random_bytes(16)),
        'connected_at' => null,
        'updated_by' => $userId,
    ]);
    $created = db_select('fashion_payment_settings', ['id' => $id], ['limit' => 1]);
    return $created[0];
}

function payment_settings_view(): array
{
    $row = payment_settings_ensure();
    $last4 = (string) $row['paystack_key_last4'];
    return [
        'mode' => (string) $row['mode'] === 'paystack' ? 'paystack' : 'manual',
        'public_key' => (string) $row['paystack_public_key'],
        'secret_mask' => $last4 === '' ? '' : '••••' . $last4,
        'webhook_url' => url('/paystack/webhook/' . (string) $row['webhook_token']),
        'links_enabled' => payment_links_enabled(),
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
    $publicKey = trim($publicKey);
    $secret = trim($secret);
    $row = payment_settings_ensure();
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
    if (!$modeChanged && $keyUpdates === []) {
        return ['ok' => true];
    }
    $userId = current_user_id();
    if ($userId === null) {
        throw new RuntimeException('Missing session.');
    }
    db_update('fashion_payment_settings', array_merge($keyUpdates, [
        'mode' => $mode,
        'updated_by' => $userId,
    ]), ['id' => $row['id']]);
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
