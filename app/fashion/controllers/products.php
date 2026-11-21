<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/domain/products.php';
require_once dirname(__DIR__, 2) . '/domain/stock.php';
require_once dirname(__DIR__, 2) . '/domain/access.php';

function products_index(): void
{
    require_access('/products');
    render('products/index', [
        'title' => t('products'),
        'products' => product_list(false),
    ]);
}

function products_create_show(array $errors = [], array $old = []): void
{
    require_access('/products');
    render('products/form', [
        'title' => t('product_add'),
        'mode' => 'create',
        'product' => null,
        'bom' => [],
        'cost_kobo' => null,
        'stock_items' => module_enabled('fashion.inventory') ? stock_list('', '', 1)['items'] : [],
        'errors' => $errors,
        'old' => $old,
    ]);
}

function products_create_submit(): void
{
    require_access('/products');
    csrf_check();
    $result = product_create($_POST);
    if (isset($result['errors'])) {
        products_create_show($result['errors'], $_POST);
        return;
    }
    flash_set('success', t('product_created'));
    redirect('/products/' . $result['product']['id']);
}

function products_edit_show(string $productId, array $errors = [], array $old = []): void
{
    require_access('/products');
    $product = product_get($productId);
    if ($product === null) {
        throw new HttpException(t('not_found'), 404);
    }
    render('products/form', [
        'title' => t('product_edit'),
        'mode' => 'edit',
        'product' => $product,
        'bom' => product_bom_list($productId),
        'cost_kobo' => product_estimated_cost_kobo($productId),
        'stock_items' => module_enabled('fashion.inventory') ? stock_list('', '', 1)['items'] : [],
        'errors' => $errors,
        'old' => $old,
    ]);
}

function products_edit_submit(string $productId): void
{
    require_access('/products');
    csrf_check();
    $action = (string) ($_POST['action'] ?? 'save');
    if ($action === 'bom_add') {
        $result = product_bom_add($productId, $_POST);
        if (isset($result['errors'])) {
            products_edit_show($productId, $result['errors'], $_POST);
            return;
        }
        flash_set('success', t('product_bom_saved'));
        redirect('/products/' . $productId);
    }
    if ($action === 'bom_remove') {
        $result = product_bom_remove($productId, (string) ($_POST['bom_id'] ?? ''));
        if (isset($result['errors'])) {
            products_edit_show($productId, $result['errors']);
            return;
        }
        flash_set('success', t('product_bom_removed'));
        redirect('/products/' . $productId);
    }
    $result = product_update($productId, [
        'name' => (string) ($_POST['name'] ?? ''),
        'sku' => (string) ($_POST['sku'] ?? ''),
        'description' => (string) ($_POST['description'] ?? ''),
        'price_kobo' => (string) ($_POST['price_kobo'] ?? '0'),
        'sizes' => (string) ($_POST['sizes'] ?? ''),
        'colours' => (string) ($_POST['colours'] ?? ''),
        'active' => !empty($_POST['active']),
    ]);
    if (isset($result['errors'])) {
        products_edit_show($productId, $result['errors'], $_POST);
        return;
    }
    flash_set('success', t('product_updated'));
    redirect('/products/' . $productId);
}
