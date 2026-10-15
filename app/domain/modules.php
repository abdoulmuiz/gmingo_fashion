<?php

declare(strict_types=1);

function company_types(): array
{
    return ['tailor', 'factory', 'brand'];
}

function module_keys(): array
{
    return [
        'fashion.orders',
        'fashion.production',
        'fashion.finance',
        'fashion.measurements',
        'fashion.communication',
        'fashion.inventory',
        'fashion.sales',
        'fashion.staff',
        'fashion.online_payments',
    ];
}

function module_defaults(string $companyType): array
{
    if ($companyType === 'tailor') {
        return [
            'fashion.orders',
            'fashion.production',
            'fashion.finance',
            'fashion.measurements',
            'fashion.communication',
        ];
    }
    if ($companyType === 'factory') {
        return [
            'fashion.production',
            'fashion.finance',
            'fashion.inventory',
            'fashion.staff',
        ];
    }
    if ($companyType === 'brand') {
        return [
            'fashion.orders',
            'fashion.finance',
            'fashion.communication',
            'fashion.inventory',
            'fashion.sales',
        ];
    }
    return [];
}

function module_label(string $key): string
{
    return t('module_' . str_replace('.', '_', $key));
}

function module_cache_clear(): void
{
    unset($GLOBALS['gmingo_module_cache']);
}

function module_map(): array
{
    $tenant = current_tenant_id();
    if ($tenant === null) {
        return [];
    }
    $cached = $GLOBALS['gmingo_module_cache'] ?? null;
    if (is_array($cached) && ($cached['tenant'] ?? '') === $tenant && isset($cached['map']) && is_array($cached['map'])) {
        return $cached['map'];
    }
    $map = [];
    foreach (db_select('fashion_tenant_modules') as $row) {
        $map[(string) $row['module_key']] = (int) $row['enabled'] === 1;
    }
    $GLOBALS['gmingo_module_cache'] = ['tenant' => $tenant, 'map' => $map];
    return $map;
}

function module_enabled(string $key): bool
{
    if (!in_array($key, module_keys(), true)) {
        return false;
    }
    $map = module_map();
    return !empty($map[$key]);
}

function require_module(string $key): void
{
    require_login();
    if (!module_enabled($key)) {
        http_response_code(403);
        throw new HttpException(t('module_disabled'), 403);
    }
    if ($key === 'fashion.online_payments' && !module_enabled('fashion.finance')) {
        http_response_code(403);
        throw new HttpException(t('module_disabled'), 403);
    }
}

function module_seed(string $companyType, string $userId, string $now): void
{
    $enabled = module_defaults($companyType);
    foreach (module_keys() as $key) {
        db_insert('fashion_tenant_modules', [
            'module_key' => $key,
            'enabled' => in_array($key, $enabled, true) ? 1 : 0,
            'changed_at' => $now,
            'changed_by' => $userId,
        ]);
    }
    module_cache_clear();
}

function module_save(array $wanted): void
{
    $clean = [];
    foreach ($wanted as $key) {
        if (is_string($key) && in_array($key, module_keys(), true)) {
            $clean[$key] = true;
        }
    }
    $userId = current_user_id();
    if ($userId === null) {
        throw new RuntimeException('Missing session.');
    }
    $now = date('Y-m-d H:i:s');
    foreach (module_keys() as $key) {
        $rows = db_select('fashion_tenant_modules', ['module_key' => $key]);
        if ($rows === []) {
            continue;
        }
        $on = isset($clean[$key]);
        if (((int) $rows[0]['enabled'] === 1) === $on) {
            continue;
        }
        db_update('fashion_tenant_modules', [
            'enabled' => $on ? 1 : 0,
            'changed_at' => $now,
            'changed_by' => $userId,
        ], ['id' => $rows[0]['id']]);
        db_insert('fashion_activity_log', [
            'user_id' => $userId,
            'action' => 'module_switch',
            'module_key' => $key,
            'enabled' => $on ? 1 : 0,
            'created_at' => $now,
        ]);
    }
    module_cache_clear();
}
