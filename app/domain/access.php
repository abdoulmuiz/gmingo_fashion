<?php

declare(strict_types=1);

require_once __DIR__ . '/modules.php';
require_once __DIR__ . '/subscriptions.php';
require_once __DIR__ . '/staff.php';

function menu_catalog(): array
{
    return [
        ['path' => '/', 'label' => 'home', 'place' => 'nav', 'modules' => [], 'owner' => false, 'permission' => '', 'subscription' => true],
        ['path' => '/orders', 'label' => 'orders', 'place' => 'nav', 'modules' => ['fashion.orders'], 'owner' => false, 'permission' => 'orders.manage', 'subscription' => true],
        ['path' => '/board', 'label' => 'board', 'place' => 'nav', 'modules' => ['fashion.production'], 'owner' => false, 'permission' => 'production.board', 'subscription' => true],
        ['path' => '/customers', 'label' => 'customers', 'place' => 'nav', 'modules' => ['fashion.orders'], 'owner' => false, 'permission' => 'customers.manage', 'subscription' => true],
        ['path' => '/outstanding', 'label' => 'balances', 'place' => 'nav', 'modules' => ['fashion.finance'], 'owner' => false, 'permission' => 'finance.view', 'subscription' => true],
        ['path' => '/more', 'label' => 'more', 'place' => 'nav', 'modules' => [], 'owner' => false, 'permission' => '', 'subscription' => false],
        ['path' => '/account/subscription', 'label' => 'subscription_account', 'place' => 'more', 'modules' => [], 'owner' => false, 'permission' => '', 'subscription' => false],
        ['path' => '/staff', 'label' => 'staff', 'place' => 'more', 'modules' => ['fashion.staff'], 'owner' => false, 'permission' => 'staff.manage', 'subscription' => true],
        ['path' => '/settings', 'label' => 'settings', 'place' => 'more', 'modules' => [], 'owner' => false, 'permission' => 'settings.manage', 'subscription' => true],
        ['path' => '/calendar', 'label' => 'calendar', 'place' => 'more', 'modules' => ['fashion.orders'], 'owner' => false, 'permission' => 'orders.manage', 'subscription' => true],
        ['path' => '/messages', 'label' => 'messages', 'place' => 'more', 'modules' => ['fashion.communication'], 'owner' => false, 'permission' => 'messages.send', 'subscription' => true],
        ['path' => '/groups', 'label' => 'groups', 'place' => 'more', 'modules' => ['fashion.orders'], 'owner' => false, 'permission' => 'orders.manage', 'subscription' => true],
        ['path' => '/stock', 'label' => 'stock', 'place' => 'more', 'modules' => ['fashion.inventory'], 'owner' => false, 'permission' => 'inventory.manage', 'subscription' => true],
        ['path' => '/products', 'label' => 'products', 'place' => 'more', 'modules' => [], 'owner' => false, 'permission' => 'catalogue.manage', 'subscription' => true],
        ['path' => '/sales', 'label' => 'sales', 'place' => 'more', 'modules' => ['fashion.sales'], 'owner' => false, 'permission' => 'sales.record', 'subscription' => true],
        ['path' => '/purchasing', 'label' => 'purchasing', 'place' => 'more', 'modules' => ['fashion.inventory'], 'owner' => false, 'permission' => 'purchasing.manage', 'subscription' => true],
        ['path' => '/expenses', 'label' => 'expenses', 'place' => 'more', 'modules' => ['fashion.finance'], 'owner' => false, 'permission' => 'finance.record', 'subscription' => true],
        ['path' => '/work-orders', 'label' => 'work_orders', 'place' => 'more', 'modules' => ['fashion.production'], 'owner' => false, 'permission' => 'production.board', 'subscription' => true],
        ['path' => '/branches', 'label' => 'branches', 'place' => 'more', 'modules' => [], 'owner' => false, 'permission' => 'branches.manage', 'subscription' => true],
        ['path' => '/reports', 'label' => 'reports', 'place' => 'more', 'modules' => [], 'owner' => false, 'permission' => 'reports.view', 'subscription' => true],
        ['path' => '/broadcasts', 'label' => 'broadcasts', 'place' => 'more', 'modules' => ['fashion.communication'], 'owner' => false, 'permission' => 'messages.send', 'subscription' => true],
        ['path' => '/settings/payments', 'label' => 'payment_settings', 'place' => 'settings', 'modules' => ['fashion.finance'], 'owner' => false, 'permission' => 'settings.manage', 'subscription' => true],
        ['path' => '/settings/stages', 'label' => 'stages_settings', 'place' => 'settings', 'modules' => ['fashion.production'], 'owner' => false, 'permission' => 'settings.manage', 'subscription' => true],
        ['path' => '/settings/garments', 'label' => 'garment_settings', 'place' => 'settings', 'modules' => ['fashion.measurements'], 'owner' => false, 'permission' => 'settings.manage', 'subscription' => true],
        ['path' => '/settings/storefront', 'label' => 'storefront_settings', 'place' => 'settings', 'modules' => ['fashion.sales'], 'owner' => false, 'permission' => 'settings.manage', 'subscription' => true],
    ];
}

function access_rules(): array
{
    $rules = [];
    foreach (menu_catalog() as $item) {
        $rules[$item['path']] = [
            'modules' => $item['modules'],
            'owner' => $item['owner'],
            'permission' => (string) ($item['permission'] ?? ''),
            'subscription' => $item['subscription'] ?? true,
        ];
    }
    return $rules;
}

function access_is_owner(): bool
{
    return staff_current_role() === 'owner';
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
    $needsSubscription = !empty($rules[$route]['subscription']);
    if ($needsSubscription && !subscription_path_open($route) && !subscription_has_app_access()) {
        return 'subscription';
    }
    foreach ($rules[$route]['modules'] as $key) {
        if (!module_enabled($key)) {
            return 'module';
        }
        if ($needsSubscription && !subscription_allows_module($key)) {
            return 'subscription_feature';
        }
    }
    if ($route === '/products'
        && !module_enabled('fashion.sales')
        && !module_enabled('fashion.inventory')) {
        return 'module';
    }
    $permission = (string) ($rules[$route]['permission'] ?? '');
    if ($permission !== '' && !user_can($permission)) {
        return 'permission';
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
    require_once __DIR__ . '/platform.php';
    if (current_user_id() !== null && is_platform_admin()) {
        redirect('/platform');
    }
    $denial = access_denial($route);
    if ($denial === '') {
        return;
    }
    if ($denial === 'login') {
        require_login();
    }
    if ($denial === 'subscription') {
        redirect('/account/subscription');
    }
    http_response_code(403);
    if ($denial === 'module') {
        throw new HttpException(t('module_disabled'), 403);
    }
    if ($denial === 'subscription_feature') {
        throw new HttpException(t('subscription_feature_blocked'), 403);
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
