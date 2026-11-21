<?php

declare(strict_types=1);

require_once __DIR__ . '/modules.php';
require_once __DIR__ . '/products.php';
require_once __DIR__ . '/sales.php';
require_once __DIR__ . '/stock.php';
require_once __DIR__ . '/staff.php';
require_once __DIR__ . '/subscriptions.php';

function storefront_slugify(string $name): string
{
    $slug = strtolower(trim($name));
    $slug = preg_replace('/[^a-z0-9]+/', '-', $slug) ?? '';
    $slug = trim($slug, '-');
    if ($slug === '') {
        $slug = 'shop';
    }
    return substr($slug, 0, 70);
}

function storefront_settings_get(): array
{
    require_permission('settings.manage');
    $tenantId = current_tenant_id();
    if ($tenantId === null) {
        throw new HttpException(t('not_allowed'), 403);
    }
    $rows = db_select('fashion_tenants', ['id' => $tenantId], ['limit' => 1]);
    $row = $rows[0] ?? [];
    return [
        'slug' => (string) ($row['storefront_slug'] ?? ''),
        'enabled' => (int) ($row['storefront_enabled'] ?? 0) === 1,
        'name' => (string) ($row['name'] ?? ''),
    ];
}

function storefront_settings_save(array $input): array
{
    require_permission('settings.manage');
    $tenantId = current_tenant_id();
    if ($tenantId === null) {
        return ['errors' => ['form' => 'not_allowed']];
    }
    $enabled = !empty($input['enabled']);
    $slugRaw = trim((string) ($input['slug'] ?? ''));
    if ($slugRaw === '') {
        $rows = db_select('fashion_tenants', ['id' => $tenantId], ['limit' => 1]);
        $slugRaw = storefront_slugify((string) ($rows[0]['name'] ?? 'shop'));
    }
    $slug = storefront_slugify($slugRaw);
    if (!preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug) || strlen($slug) < 2) {
        return ['errors' => ['slug' => 'required']];
    }
    if ($enabled && (!module_enabled('fashion.sales') || !subscription_allows_module('fashion.sales'))) {
        return ['errors' => ['form' => 'module_disabled']];
    }
    $taken = storefront_platform_query(
        'SELECT id FROM fashion_tenants WHERE storefront_slug = :slug AND id <> :id LIMIT 1',
        ['slug' => $slug, 'id' => $tenantId]
    );
    if ($taken !== []) {
        return ['errors' => ['slug' => 'storefront_slug_taken']];
    }
    db_update('fashion_tenants', [
        'storefront_slug' => $slug,
        'storefront_enabled' => $enabled ? 1 : 0,
    ], ['id' => $tenantId]);
    return ['ok' => true, 'slug' => $slug, 'enabled' => $enabled];
}

function storefront_bind(string $slug): ?array
{
    $slug = storefront_slugify($slug);
    if ($slug === '' || !preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug)) {
        return null;
    }
    $rows = storefront_platform_query(
        'SELECT id, name, storefront_enabled, brand_primary, brand_accent FROM fashion_tenants WHERE storefront_slug = :slug LIMIT 1',
        ['slug' => $slug]
    );
    if ($rows === [] || (int) ($rows[0]['storefront_enabled'] ?? 0) !== 1) {
        return null;
    }
    $tenantId = (string) $rows[0]['id'];
    if (!is_uuid($tenantId)) {
        return null;
    }
    auth_bind_tenant($tenantId);
    if (!module_enabled('fashion.sales') || !subscription_has_app_access()) {
        auth_release_tenant();
        return null;
    }
    return [
        'tenant_id' => $tenantId,
        'name' => (string) $rows[0]['name'],
        'brand_primary' => (string) ($rows[0]['brand_primary'] ?? ''),
        'brand_accent' => (string) ($rows[0]['brand_accent'] ?? ''),
        'slug' => $slug,
    ];
}

function storefront_products(): array
{
    $rows = db_query(
        'SELECT id, name, sku, description, price_kobo, sizes_json, colours_json, active, stock_item_id, created_at, updated_at
         FROM fashion_products
         WHERE tenant_id = :tenant_id AND active = 1
         ORDER BY name ASC
         LIMIT 100'
    );
    $out = [];
    foreach ($rows as $row) {
        if (is_array($row)) {
            $out[] = product_present($row);
        }
    }
    return $out;
}

function storefront_place_order(array $input): array
{
    $productId = (string) ($input['product_id'] ?? '');
    $qty = (int) ($input['quantity'] ?? 0);
    $size = trim((string) ($input['size_label'] ?? ''));
    $colour = trim((string) ($input['colour_label'] ?? ''));
    $buyer = trim((string) ($input['buyer_name'] ?? ''));
    $phoneRaw = trim((string) ($input['buyer_phone'] ?? ''));
    if ($buyer === '' || strlen($buyer) > 120) {
        return ['errors' => ['buyer_name' => 'required']];
    }
    $phone = phone_normalize($phoneRaw);
    if ($phone === null) {
        return ['errors' => ['buyer_phone' => 'invalid_phone']];
    }
    if ($qty < 1 || $qty > 20) {
        return ['errors' => ['quantity' => 'required']];
    }
    if (!is_uuid($productId)) {
        return ['errors' => ['product_id' => 'required']];
    }
    $productRows = db_select('fashion_products', ['id' => $productId], ['limit' => 1]);
    if ($productRows === [] || (int) ($productRows[0]['active'] ?? 0) !== 1) {
        return ['errors' => ['product_id' => 'required']];
    }
    $product = product_present($productRows[0]);
    $owners = db_query(
        'SELECT id FROM fashion_users
         WHERE tenant_id = :tenant_id AND role = \'owner\' AND active = 1
         ORDER BY created_at ASC
         LIMIT 1'
    );
    $ownerId = (string) ($owners[0]['id'] ?? '');
    if (!is_uuid($ownerId)) {
        return ['errors' => ['form' => 'not_allowed']];
    }
    $lineTotal = $product['price_kobo'] * $qty;
    $note = 'Online: ' . $buyer . ' / ' . $phone;
    if (strlen($size) > 40 || strlen($colour) > 80 || strlen($note) > 500) {
        return ['errors' => ['form' => 'required']];
    }
    db_begin();
    try {
        $saleId = db_insert('fashion_sales', [
            'sale_number' => sale_next_number(),
            'customer_id' => null,
            'channel' => 'online',
            'status' => 'completed',
            'total_kobo' => $lineTotal,
            'note' => $note,
            'created_by' => $ownerId,
            'created_at' => app_now()->format('Y-m-d H:i:s'),
        ]);
        db_insert('fashion_sale_lines', [
            'sale_id' => $saleId,
            'product_id' => $productId,
            'quantity' => $qty,
            'unit_price_kobo' => $product['price_kobo'],
            'line_total_kobo' => $lineTotal,
            'size_label' => $size,
            'colour_label' => $colour,
        ]);
        if ($product['stock_item_id'] !== null && module_enabled('fashion.inventory')) {
            stock_post_movement(
                $product['stock_item_id'],
                -($qty * 100),
                'sale',
                'Online sale ' . $saleId,
                null,
                null,
                $ownerId
            );
        }
        db_commit();
    } catch (Throwable $e) {
        db_rollback();
        throw $e;
    }
    return ['sale_id' => $saleId, 'total_kobo' => $lineTotal];
}
