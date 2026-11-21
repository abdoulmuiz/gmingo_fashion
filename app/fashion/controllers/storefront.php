<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/domain/storefront.php';
require_once dirname(__DIR__, 2) . '/domain/access.php';

function storefront_settings_show(array $errors = [], array $old = []): void
{
    require_access('/settings/storefront');
    $settings = storefront_settings_get();
    render('storefront/settings', [
        'title' => t('storefront_settings'),
        'settings' => $settings,
        'errors' => $errors,
        'old' => $old !== [] ? $old : [
            'slug' => $settings['slug'],
            'enabled' => $settings['enabled'] ? '1' : '',
        ],
        'public_url' => $settings['slug'] !== '' ? url('/shop/' . $settings['slug']) : '',
    ]);
}

function storefront_settings_submit(): void
{
    require_access('/settings/storefront');
    csrf_check();
    $result = storefront_settings_save($_POST);
    if (isset($result['errors'])) {
        storefront_settings_show($result['errors'], $_POST);
        return;
    }
    flash_set('success', t('storefront_saved'));
    redirect('/settings/storefront');
}

function storefront_public_show(string $slug, array $errors = [], array $old = []): void
{
    $shop = storefront_bind($slug);
    if ($shop === null) {
        throw new HttpException(t('not_found'), 404);
    }
    try {
        render('storefront/shop', [
            'title' => $shop['name'],
            'shop' => $shop,
            'products' => storefront_products(),
            'errors' => $errors,
            'old' => $old,
        ]);
    } finally {
        auth_release_tenant();
    }
}

function storefront_public_order_submit(string $slug): void
{
    $shop = storefront_bind($slug);
    if ($shop === null) {
        throw new HttpException(t('not_found'), 404);
    }
    try {
        csrf_check();
        $result = storefront_place_order($_POST);
        if (isset($result['errors'])) {
            render('storefront/shop', [
                'title' => $shop['name'],
                'shop' => $shop,
                'products' => storefront_products(),
                'errors' => $result['errors'],
                'old' => $_POST,
            ]);
            return;
        }
        flash_set('success', t('storefront_order_placed'));
        redirect('/shop/' . $slug);
    } finally {
        auth_release_tenant();
    }
}
