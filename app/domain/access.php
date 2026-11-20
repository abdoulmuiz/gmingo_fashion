<?php

declare(strict_types=1);

require_once __DIR__ . '/modules.php';

function menu_catalog(): array
{
    return [
        ['path' => '/', 'label' => 'home', 'place' => 'nav', 'modules' => [], 'owner' => false],
        ['path' => '/orders', 'label' => 'orders', 'place' => 'nav', 'modules' => ['fashion.orders'], 'owner' => false],
        ['path' => '/board', 'label' => 'board', 'place' => 'nav', 'modules' => ['fashion.production'], 'owner' => false],
        ['path' => '/customers', 'label' => 'customers', 'place' => 'nav', 'modules' => ['fashion.orders'], 'owner' => false],
        ['path' => '/outstanding', 'label' => 'balances', 'place' => 'nav', 'modules' => ['fashion.finance'], 'owner' => false],
        ['path' => '/more', 'label' => 'more', 'place' => 'nav', 'modules' => [], 'owner' => false],
        ['path' => '/settings', 'label' => 'settings', 'place' => 'more', 'modules' => [], 'owner' => true],
        ['path' => '/calendar', 'label' => 'calendar', 'place' => 'more', 'modules' => ['fashion.orders'], 'owner' => false],
        ['path' => '/messages', 'label' => 'messages', 'place' => 'more', 'modules' => ['fashion.communication'], 'owner' => false],
        ['path' => '/groups', 'label' => 'groups', 'place' => 'more', 'modules' => ['fashion.orders'], 'owner' => false],
        ['path' => '/stock', 'label' => 'stock', 'place' => 'more', 'modules' => ['fashion.inventory'], 'owner' => false],
        ['path' => '/settings/payments', 'label' => 'payment_settings', 'place' => 'settings', 'modules' => ['fashion.finance'], 'owner' => true],
        ['path' => '/settings/stages', 'label' => 'stages_settings', 'place' => 'settings', 'modules' => ['fashion.production'], 'owner' => true],
        ['path' => '/settings/garments', 'label' => 'garment_settings', 'place' => 'settings', 'modules' => ['fashion.measurements'], 'owner' => true],
    ];
}

function access_rules(): array
{
    $rules = [];
    foreach (menu_catalog() as $item) {
        $rules[$item['path']] = [
            'modules' => $item['modules'],
            'owner' => $item['owner'],
        ];
    }
    return $rules;
}

function access_is_owner(): bool
{
    $userId = current_user_id();
    if ($userId === null) {
        return false;
    }
    $rows = db_select('fashion_users', ['id' => $userId]);
    return $rows !== [] && (string) $rows[0]['role'] === 'owner';
}

function access_denial(string $route): string
{
    if (current_user_id() === null || current_tenant_id() === null) {
        return 'login';
    }
    $rules = access_rules();
    if (!isset($rules[$route])) {
        return 'owner';
    }
    foreach ($rules[$route]['modules'] as $key) {
        if (!module_enabled($key)) {
            return 'module';
        }
    }
    if ($rules[$route]['owner'] && !access_is_owner()) {
        return 'owner';
    }
    return '';
}

function can_access(string $route): bool
{
    return access_denial($route) === '';
}

function require_access(string $route): void
{
    $denial = access_denial($route);
    if ($denial === '') {
        return;
    }
    if ($denial === 'login') {
        require_login();
    }
    http_response_code(403);
    if ($denial === 'module') {
        throw new HttpException(t('module_disabled'), 403);
    }
    throw new HttpException(t('not_allowed'), 403);
}

function menu_links(string $place): array
{
    $links = [];
    foreach (menu_catalog() as $item) {
        if ($item['place'] !== $place || !can_access($item['path'])) {
            continue;
        }
        $links[] = [$item['path'], $item['label']];
    }
    return $links;
}
