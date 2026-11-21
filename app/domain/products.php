<?php

declare(strict_types=1);

require_once __DIR__ . '/modules.php';
require_once __DIR__ . '/stock.php';
require_once __DIR__ . '/subscriptions.php';
require_once __DIR__ . '/staff.php';

function product_list_json(string $raw): array
{
    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        return [];
    }
    $out = [];
    foreach ($decoded as $item) {
        if (!is_string($item)) {
            continue;
        }
        $value = trim($item);
        if ($value === '' || strlen($value) > 40) {
            continue;
        }
        $out[] = $value;
    }
    return array_values(array_unique($out));
}

function product_encode_list(array $items): string
{
    $clean = [];
    foreach ($items as $item) {
        if (!is_string($item)) {
            continue;
        }
        $value = trim($item);
        if ($value === '' || strlen($value) > 40) {
            continue;
        }
        $clean[] = $value;
    }
    return json_encode(array_values(array_unique($clean)), JSON_THROW_ON_ERROR);
}

function product_parse_csv_list(string $raw): array
{
    $parts = preg_split('/\s*,\s*/', trim($raw)) ?: [];
    $out = [];
    foreach ($parts as $part) {
        if ($part === '' || strlen($part) > 40) {
            continue;
        }
        $out[] = $part;
    }
    return array_values(array_unique($out));
}

function product_count(): int
{
    $rows = db_query('SELECT COUNT(*) AS total FROM fashion_products WHERE tenant_id = :tenant_id');
    return (int) ($rows[0]['total'] ?? 0);
}

function product_present(array $row): array
{
    return [
        'id' => (string) $row['id'],
        'name' => (string) $row['name'],
        'sku' => (string) ($row['sku'] ?? ''),
        'description' => (string) ($row['description'] ?? ''),
        'price_kobo' => (int) $row['price_kobo'],
        'sizes' => product_list_json((string) ($row['sizes_json'] ?? '[]')),
        'colours' => product_list_json((string) ($row['colours_json'] ?? '[]')),
        'active' => (int) ($row['active'] ?? 0) === 1,
        'stock_item_id' => $row['stock_item_id'] !== null ? (string) $row['stock_item_id'] : null,
        'created_at' => (string) ($row['created_at'] ?? ''),
        'updated_at' => (string) ($row['updated_at'] ?? ''),
    ];
}

function product_require_access(): void
{
    require_login();
    if (!module_enabled('fashion.sales') && !module_enabled('fashion.inventory')) {
        http_response_code(403);
        throw new HttpException(t('module_disabled'), 403);
    }
    require_permission('catalogue.manage');
}

function product_list(bool $activeOnly = false): array
{
    product_require_access();
    $sql = 'SELECT id, name, sku, description, price_kobo, sizes_json, colours_json, active, stock_item_id, created_at, updated_at
            FROM fashion_products
            WHERE tenant_id = :tenant_id';
    if ($activeOnly) {
        $sql .= ' AND active = 1';
    }
    $sql .= ' ORDER BY name ASC LIMIT 200';
    $rows = db_query($sql);
    $out = [];
    foreach ($rows as $row) {
        if (is_array($row)) {
            $out[] = product_present($row);
        }
    }
    return $out;
}

function product_get(string $productId): ?array
{
    product_require_access();
    if (!is_uuid($productId)) {
        return null;
    }
    $rows = db_select('fashion_products', ['id' => $productId], ['limit' => 1]);
    return $rows !== [] ? product_present($rows[0]) : null;
}

function product_bom_list(string $productId): array
{
    product_require_access();
    if (!is_uuid($productId)) {
        return [];
    }
    $rows = db_query(
        'SELECT b.id, b.product_id, b.stock_item_id, b.quantity_hundredths, i.name AS item_name, i.unit, i.unit_cost_kobo
         FROM fashion_product_bom b
         INNER JOIN fashion_stock_items i ON i.id = b.stock_item_id AND i.tenant_id = :tenant_id
         WHERE b.tenant_id = :tenant_id AND b.product_id = :product_id
         ORDER BY i.name ASC',
        ['product_id' => $productId]
    );
    $out = [];
    foreach ($rows as $row) {
        if (!is_array($row)) {
            continue;
        }
        $qty = (int) $row['quantity_hundredths'];
        $unitCost = $row['unit_cost_kobo'] !== null ? (int) $row['unit_cost_kobo'] : null;
        $lineCost = $unitCost !== null ? (int) floor(($unitCost * $qty) / 100) : null;
        $out[] = [
            'id' => (string) $row['id'],
            'product_id' => (string) $row['product_id'],
            'stock_item_id' => (string) $row['stock_item_id'],
            'item_name' => (string) $row['item_name'],
            'unit' => (string) $row['unit'],
            'quantity_hundredths' => $qty,
            'unit_cost_kobo' => $unitCost,
            'line_cost_kobo' => $lineCost,
        ];
    }
    return $out;
}

function product_estimated_cost_kobo(string $productId): ?int
{
    $lines = product_bom_list($productId);
    if ($lines === []) {
        return null;
    }
    $total = 0;
    $any = false;
    foreach ($lines as $line) {
        if ($line['line_cost_kobo'] === null) {
            continue;
        }
        $any = true;
        $total += (int) $line['line_cost_kobo'];
    }
    return $any ? $total : null;
}

function product_create(array $input): array
{
    product_require_access();
    $name = trim((string) ($input['name'] ?? ''));
    $sku = trim((string) ($input['sku'] ?? ''));
    $description = trim((string) ($input['description'] ?? ''));
    $priceRaw = trim((string) ($input['price_kobo'] ?? '0'));
    $sizes = is_array($input['sizes'] ?? null)
        ? $input['sizes']
        : product_parse_csv_list((string) ($input['sizes'] ?? ''));
    $colours = is_array($input['colours'] ?? null)
        ? $input['colours']
        : product_parse_csv_list((string) ($input['colours'] ?? ''));
    $errors = [];
    if ($name === '' || strlen($name) > 190) {
        $errors['name'] = 'required';
    }
    if (strlen($sku) > 80) {
        $errors['sku'] = 'invalid_sku';
    }
    if (strlen($description) > 500) {
        $errors['description'] = 'invalid_description';
    }
    if (!preg_match('/^\d+$/', $priceRaw)) {
        $errors['price_kobo'] = 'invalid_price';
        $price = -1;
    } else {
        $price = (int) $priceRaw;
    }
    if ($errors !== []) {
        return ['errors' => $errors];
    }
    try {
        subscription_assert_within_limit('max_products', product_count() + 1);
    } catch (HttpException $e) {
        return ['errors' => ['form' => 'subscription_limit_reached']];
    }
    $now = app_now()->format('Y-m-d H:i:s');
    $id = db_insert('fashion_products', [
        'name' => $name,
        'sku' => $sku,
        'description' => $description,
        'price_kobo' => $price,
        'sizes_json' => product_encode_list($sizes),
        'colours_json' => product_encode_list($colours),
        'active' => 1,
        'stock_item_id' => null,
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    return ['product' => product_get($id)];
}

function product_update(string $productId, array $input): array
{
    $product = product_get($productId);
    if ($product === null) {
        return ['errors' => ['form' => 'not_found']];
    }
    $name = trim((string) ($input['name'] ?? $product['name']));
    $sku = trim((string) ($input['sku'] ?? $product['sku']));
    $description = trim((string) ($input['description'] ?? $product['description']));
    $priceRaw = trim((string) ($input['price_kobo'] ?? (string) $product['price_kobo']));
    $sizes = is_array($input['sizes'] ?? null)
        ? $input['sizes']
        : product_parse_csv_list((string) ($input['sizes'] ?? implode(', ', $product['sizes'])));
    $colours = is_array($input['colours'] ?? null)
        ? $input['colours']
        : product_parse_csv_list((string) ($input['colours'] ?? implode(', ', $product['colours'])));
    $active = array_key_exists('active', $input) ? !empty($input['active']) : $product['active'];
    $errors = [];
    if ($name === '' || strlen($name) > 190) {
        $errors['name'] = 'required';
    }
    if (strlen($sku) > 80) {
        $errors['sku'] = 'invalid_sku';
    }
    if (strlen($description) > 500) {
        $errors['description'] = 'invalid_description';
    }
    if (!preg_match('/^\d+$/', $priceRaw)) {
        $errors['price_kobo'] = 'invalid_price';
        $price = -1;
    } else {
        $price = (int) $priceRaw;
    }
    if ($errors !== []) {
        return ['errors' => $errors];
    }
    db_update('fashion_products', [
        'name' => $name,
        'sku' => $sku,
        'description' => $description,
        'price_kobo' => $price,
        'sizes_json' => product_encode_list($sizes),
        'colours_json' => product_encode_list($colours),
        'active' => $active ? 1 : 0,
        'updated_at' => app_now()->format('Y-m-d H:i:s'),
    ], ['id' => $productId]);
    return ['product' => product_get($productId)];
}

function product_bom_add(string $productId, array $input): array
{
    $product = product_get($productId);
    if ($product === null) {
        return ['errors' => ['form' => 'not_found']];
    }
    if (!module_enabled('fashion.inventory')) {
        return ['errors' => ['form' => 'module_disabled']];
    }
    $itemId = (string) ($input['stock_item_id'] ?? '');
    $qty = stock_hundredths((string) ($input['quantity'] ?? ''), false);
    if (!is_uuid($itemId) || db_select('fashion_stock_items', ['id' => $itemId], ['limit' => 1]) === []) {
        return ['errors' => ['stock_item_id' => 'required']];
    }
    if ($qty === null || $qty <= 0) {
        return ['errors' => ['quantity' => 'invalid_stock_amount']];
    }
    $existing = db_select('fashion_product_bom', [
        'product_id' => $productId,
        'stock_item_id' => $itemId,
    ], ['limit' => 1]);
    if ($existing !== []) {
        db_update('fashion_product_bom', [
            'quantity_hundredths' => $qty,
        ], ['id' => (string) $existing[0]['id']]);
        return ['ok' => true];
    }
    db_insert('fashion_product_bom', [
        'product_id' => $productId,
        'stock_item_id' => $itemId,
        'quantity_hundredths' => $qty,
        'created_at' => app_now()->format('Y-m-d H:i:s'),
    ]);
    return ['ok' => true];
}

function product_bom_remove(string $productId, string $bomId): array
{
    $product = product_get($productId);
    if ($product === null) {
        return ['errors' => ['form' => 'not_found']];
    }
    if (!is_uuid($bomId)) {
        return ['errors' => ['form' => 'not_found']];
    }
    $rows = db_select('fashion_product_bom', ['id' => $bomId, 'product_id' => $productId], ['limit' => 1]);
    if ($rows === []) {
        return ['errors' => ['form' => 'not_found']];
    }
    db_delete('fashion_product_bom', ['id' => $bomId]);
    return ['ok' => true];
}
