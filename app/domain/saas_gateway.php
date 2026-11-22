<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/payments/PaystackClient.php';
require_once __DIR__ . '/platform.php';

function saas_gateway_blank(): array
{
    return [
        'id' => '',
        'enabled' => false,
        'environment' => 'test',
        'public_key' => '',
        'secret_key_encrypted' => '',
        'secret_key_last4' => '',
        'webhook_secret_encrypted' => '',
        'webhook_secret_last4' => '',
        'connected_at' => null,
        'updated_by' => null,
        'created_at' => '',
        'updated_at' => '',
    ];
}

function saas_gateway_row(): array
{
    $rows = saas_platform_query(
        'SELECT id, tenant_id, enabled, environment, public_key, secret_key_encrypted, secret_key_last4,
                webhook_secret_encrypted, webhook_secret_last4, connected_at, updated_by, created_at, updated_at
         FROM fashion_saas_gateway_settings
         WHERE tenant_id = :tenant_id
         LIMIT 1',
        ['tenant_id' => subscription_platform_tenant_id()]
    );
    $row = $rows[0] ?? null;
    if (!is_array($row)) {
        return saas_gateway_blank();
    }
    return [
        'id' => (string) $row['id'],
        'enabled' => (int) ($row['enabled'] ?? 0) === 1,
        'environment' => (string) ($row['environment'] ?? 'test') === 'live' ? 'live' : 'test',
        'public_key' => (string) ($row['public_key'] ?? ''),
        'secret_key_encrypted' => (string) ($row['secret_key_encrypted'] ?? ''),
        'secret_key_last4' => (string) ($row['secret_key_last4'] ?? ''),
        'webhook_secret_encrypted' => (string) ($row['webhook_secret_encrypted'] ?? ''),
        'webhook_secret_last4' => (string) ($row['webhook_secret_last4'] ?? ''),
        'connected_at' => $row['connected_at'] !== null ? (string) $row['connected_at'] : null,
        'updated_by' => $row['updated_by'] !== null ? (string) $row['updated_by'] : null,
        'created_at' => (string) ($row['created_at'] ?? ''),
        'updated_at' => (string) ($row['updated_at'] ?? ''),
    ];
}

/**
 * Public view for platform UI — never includes secret ciphertext.
 */
function saas_gateway_view(): array
{
    $row = saas_gateway_row();
    $configSecret = trim((string) (app_config()['saas_paystack_secret_key'] ?? ''));
    $configPublic = trim((string) (app_config()['saas_paystack_public_key'] ?? ''));
    $hasDbSecret = $row['secret_key_encrypted'] !== '';
    $hasConfigFallback = $configSecret !== '';
    return [
        'enabled' => $row['enabled'],
        'environment' => $row['environment'],
        'public_key' => $row['public_key'] !== '' ? $row['public_key'] : $configPublic,
        'secret_mask' => $row['secret_key_last4'] !== ''
            ? ('••••' . $row['secret_key_last4'])
            : ($hasConfigFallback ? '••••config' : ''),
        'webhook_secret_mask' => $row['webhook_secret_last4'] !== ''
            ? ('••••' . $row['webhook_secret_last4'])
            : '',
        'connected_at' => $row['connected_at'],
        'has_secret' => $hasDbSecret || $hasConfigFallback,
        'using_config_fallback' => !$hasDbSecret && $hasConfigFallback,
        'webhook_url' => saas_gateway_webhook_url(),
        'callback_url' => saas_paystack_callback_url_raw(),
        'checkout_ready' => saas_paystack_enabled(),
    ];
}

function saas_gateway_webhook_url(): string
{
    $origin = saas_paystack_public_origin();
    if ($origin === '') {
        return '';
    }
    return $origin . url('/webhooks/saas-paystack');
}

function saas_paystack_public_origin(): string
{
    $origin = '';
    $raw = trim((string) (app_config()['public_base_url'] ?? ''));
    if ($raw !== '') {
        $parts = parse_url($raw);
        if (is_array($parts) && isset($parts['scheme'], $parts['host'])) {
            $origin = $parts['scheme'] . '://' . $parts['host'];
            if (isset($parts['port'])) {
                $origin .= ':' . (int) $parts['port'];
            }
        }
    }
    if ($origin === '') {
        $placeholder = trim((string) (app_config()['payment_placeholder_domain'] ?? ''));
        if ($placeholder !== '') {
            if (preg_match('#^https?://#i', $placeholder) === 1) {
                $parts = parse_url($placeholder);
                if (is_array($parts) && isset($parts['scheme'], $parts['host'])) {
                    $origin = $parts['scheme'] . '://' . $parts['host'];
                    if (isset($parts['port'])) {
                        $origin .= ':' . (int) $parts['port'];
                    }
                }
            } else {
                $origin = 'https://' . $placeholder;
            }
        }
    }
    return $origin;
}

function saas_paystack_callback_url_raw(): string
{
    $origin = saas_paystack_public_origin();
    if ($origin === '') {
        return '';
    }
    return $origin . url('/account/billing/return');
}

function saas_gateway_app_key_ready(): bool
{
    return strlen((string) (app_config()['app_key'] ?? '')) >= 32;
}

/**
 * Resolve the active secret: DB encrypted first, then config fallback.
 */
function saas_gateway_secret_plain(): ?string
{
    if (!saas_gateway_app_key_ready()) {
        return null;
    }
    $row = saas_gateway_row();
    if ($row['secret_key_encrypted'] !== '') {
        try {
            return PaystackClient::reveal($row['secret_key_encrypted']);
        } catch (Throwable $e) {
            return null;
        }
    }
    $configSecret = trim((string) (app_config()['saas_paystack_secret_key'] ?? ''));
    return $configSecret !== '' ? $configSecret : null;
}

function saas_gateway_webhook_secret_plain(): ?string
{
    if (!saas_gateway_app_key_ready()) {
        return null;
    }
    $row = saas_gateway_row();
    if ($row['webhook_secret_encrypted'] !== '') {
        try {
            return PaystackClient::reveal($row['webhook_secret_encrypted']);
        } catch (Throwable $e) {
            return null;
        }
    }
    return saas_gateway_secret_plain();
}

function saas_gateway_public_key(): string
{
    $row = saas_gateway_row();
    if ($row['public_key'] !== '') {
        return $row['public_key'];
    }
    return trim((string) (app_config()['saas_paystack_public_key'] ?? ''));
}

function saas_gateway_checkout_enabled(): bool
{
    $row = saas_gateway_row();
    if ($row['id'] !== '') {
        return $row['enabled'] && saas_gateway_secret_plain() !== null && saas_gateway_app_key_ready();
    }
    // Legacy: config secret alone enables checkout.
    $configSecret = trim((string) (app_config()['saas_paystack_secret_key'] ?? ''));
    return $configSecret !== '' && saas_gateway_app_key_ready();
}

/**
 * @param array{enabled?: bool, environment?: string, public_key?: string, secret_key?: string, webhook_secret?: string} $input
 */
function saas_gateway_save(array $input): array
{
    require_platform_admin();
    if (!saas_gateway_app_key_ready()) {
        return ['errors' => ['form' => 'app_key_missing']];
    }
    $row = saas_gateway_row();
    $enabled = !empty($input['enabled']);
    $environment = (string) ($input['environment'] ?? $row['environment']);
    if (!in_array($environment, ['test', 'live'], true)) {
        return ['errors' => ['environment' => 'invalid_environment']];
    }
    $publicKey = trim((string) ($input['public_key'] ?? ''));
    $secret = trim((string) ($input['secret_key'] ?? ''));
    $webhookSecret = trim((string) ($input['webhook_secret'] ?? ''));
    $nextPublic = $publicKey !== '' ? $publicKey : $row['public_key'];
    $hasSecret = $secret !== '' || $row['secret_key_encrypted'] !== ''
        || trim((string) (app_config()['saas_paystack_secret_key'] ?? '')) !== '';
    $errors = [];
    if ($enabled && ($nextPublic === '' || !$hasSecret)) {
        $errors['form'] = 'saas_gateway_keys_required';
    }
    if ($nextPublic !== '' && !preg_match('/^pk_(test|live)_[A-Za-z0-9]+$/', $nextPublic)) {
        $errors['public_key'] = 'invalid_paystack_key';
    }
    if ($secret !== '' && !preg_match('/^sk_(test|live)_[A-Za-z0-9]+$/', $secret)) {
        $errors['secret_key'] = 'invalid_paystack_key';
    }
    if ($webhookSecret !== '' && strlen($webhookSecret) < 8) {
        $errors['webhook_secret'] = 'invalid_webhook_secret';
    }
    if ($environment === 'live' && $nextPublic !== '' && !str_starts_with($nextPublic, 'pk_live_')) {
        $errors['public_key'] = 'live_key_required';
    }
    if ($environment === 'test' && $nextPublic !== '' && !str_starts_with($nextPublic, 'pk_test_')) {
        $errors['public_key'] = 'test_key_required';
    }
    if ($errors !== []) {
        return ['errors' => $errors];
    }
    $now = app_now()->format('Y-m-d H:i:s');
    $userId = (string) current_user_id();
    $fields = [
        'enabled' => $enabled ? 1 : 0,
        'environment' => $environment,
        'public_key' => $nextPublic,
        'updated_by' => $userId,
        'updated_at' => $now,
    ];
    if ($secret !== '') {
        $fields['secret_key_encrypted'] = PaystackClient::seal($secret);
        $fields['secret_key_last4'] = substr($secret, -4);
        $fields['connected_at'] = $row['connected_at'] ?? $now;
    }
    if ($webhookSecret !== '') {
        $fields['webhook_secret_encrypted'] = PaystackClient::seal($webhookSecret);
        $fields['webhook_secret_last4'] = substr($webhookSecret, -4);
    }
    if ($row['id'] === '') {
        $id = uuid();
        saas_platform_execute(
            'INSERT INTO fashion_saas_gateway_settings (
                id, tenant_id, enabled, environment, public_key, secret_key_encrypted, secret_key_last4,
                webhook_secret_encrypted, webhook_secret_last4, connected_at, updated_by, created_at, updated_at
             ) VALUES (
                :id, :tenant_id, :enabled, :environment, :public_key, :secret_key_encrypted, :secret_key_last4,
                :webhook_secret_encrypted, :webhook_secret_last4, :connected_at, :updated_by, :created_at, :updated_at
             )',
            [
                'id' => $id,
                'tenant_id' => subscription_platform_tenant_id(),
                'enabled' => $fields['enabled'],
                'environment' => $fields['environment'],
                'public_key' => $fields['public_key'],
                'secret_key_encrypted' => $fields['secret_key_encrypted'] ?? '',
                'secret_key_last4' => $fields['secret_key_last4'] ?? '',
                'webhook_secret_encrypted' => $fields['webhook_secret_encrypted'] ?? '',
                'webhook_secret_last4' => $fields['webhook_secret_last4'] ?? '',
                'connected_at' => $fields['connected_at'] ?? null,
                'updated_by' => $userId,
                'created_at' => $now,
                'updated_at' => $now,
            ]
        );
    } else {
        $sets = [];
        $params = ['id' => $row['id'], 'tenant_id' => subscription_platform_tenant_id()];
        foreach ($fields as $col => $value) {
            $sets[] = $col . ' = :' . $col;
            $params[$col] = $value;
        }
        saas_platform_execute(
            'UPDATE fashion_saas_gateway_settings SET ' . implode(', ', $sets)
            . ' WHERE id = :id AND tenant_id = :tenant_id',
            $params
        );
    }
    return ['ok' => true, 'view' => saas_gateway_view()];
}

/**
 * Safe connectivity check — calls Paystack /bank (list) with the secret.
 */
function saas_gateway_test_connection(): array
{
    require_platform_admin();
    $secret = saas_gateway_secret_plain();
    if ($secret === null) {
        return ['ok' => false, 'error' => 'saas_paystack_disabled'];
    }
    try {
        $client = PaystackClient::open(PaystackClient::seal($secret));
        $response = $client->call('GET', '/bank?currency=NGN&perPage=1', null);
    } catch (Throwable $e) {
        return ['ok' => false, 'error' => 'saas_gateway_test_failed'];
    }
    $decoded = json_decode($response['body'] ?? '', true);
    $ok = ($response['status'] ?? 0) === 200
        && is_array($decoded)
        && ($decoded['status'] ?? false) === true;
    return $ok
        ? ['ok' => true]
        : ['ok' => false, 'error' => 'saas_gateway_test_failed'];
}
