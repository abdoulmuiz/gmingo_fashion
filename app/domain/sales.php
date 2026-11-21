<?php

declare(strict_types=1);

require_once __DIR__ . '/modules.php';
require_once __DIR__ . '/products.php';
require_once __DIR__ . '/stock.php';
require_once __DIR__ . '/staff.php';

function sale_channels(): array
{
    return ['retail', 'wholesale', 'online'];
}

function sale_next_number(): string
{
    $rows = db_select('fashion_sale_counters', [], ['limit' => 1]);
    if ($rows === []) {
        db_insert('fashion_sale_counters', [
            'id' => uuid(),
            'last_number' => 1001,
        ]);
        return 'SL-1001';
    }
    $next = (int) $rows[0]['last_number'] + 1;
    db_update('fashion_sale_counters', ['last_number' => $next], ['id' => (string) $rows[0]['id']]);
    return 'SL-' . (string) $next;
}

function sale_product_options(): array
{
    require_module('fashion.sales');
    require_permission('sales.record');
    $rows = db_query(
        'SELECT id, name, sku, price_kobo, sizes_json, colours_json, stock_item_id
         FROM fashion_products
         WHERE tenant_id = :tenant_id AND active = 1
         ORDER BY name ASC
         LIMIT 200'
    );
    $out = [];
    foreach ($rows as $row) {
        if (is_array($row)) {
            $out[] = product_present($row);
        }
    }
    return $out;
}

function sale_list(int $limit = 50): array
{
    require_module('fashion.sales');
    require_permission('sales.record');
    $limit = max(1, min(100, $limit));
    $rows = db_query(
        'SELECT id, sale_number, customer_id, channel, status, total_kobo, note, created_by, created_at
         FROM fashion_sales
         WHERE tenant_id = :tenant_id
         ORDER BY created_at DESC
         LIMIT ' . (string) $limit
    );
    $out = [];
    foreach ($rows as $row) {
        if (is_array($row)) {
            $out[] = [
                'id' => (string) $row['id'],
                'sale_number' => (string) $row['sale_number'],
                'channel' => (string) $row['channel'],
                'status' => (string) $row['status'],
                'total_kobo' => (int) $row['total_kobo'],
                'created_at' => (string) $row['created_at'],
            ];
        }
    }
    return $out;
}

function sale_create(array $input): array
{
    require_module('fashion.sales');
    require_permission('sales.record');
    $channel = (string) ($input['channel'] ?? 'retail');
    $note = trim((string) ($input['note'] ?? ''));
    $productId = (string) ($input['product_id'] ?? '');
    $qty = (int) ($input['quantity'] ?? 0);
    $size = trim((string) ($input['size_label'] ?? ''));
    $colour = trim((string) ($input['colour_label'] ?? ''));
    if (!in_array($channel, sale_channels(), true)) {
        return ['errors' => ['channel' => 'required']];
    }
    if (strlen($note) > 500 || strlen($size) > 40 || strlen($colour) > 80) {
        return ['errors' => ['form' => 'required']];
    }
    if ($qty < 1 || $qty > 1000) {
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
    $userId = current_user_id();
    if ($userId === null) {
        return ['errors' => ['form' => 'not_allowed']];
    }
    $lineTotal = $product['price_kobo'] * $qty;
    db_begin();
    try {
        $saleId = db_insert('fashion_sales', [
            'sale_number' => sale_next_number(),
            'customer_id' => null,
            'channel' => $channel,
            'status' => 'completed',
            'total_kobo' => $lineTotal,
            'note' => $note,
            'created_by' => $userId,
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
                'Sale ' . $saleId
            );
        } elseif (module_enabled('fashion.inventory')) {
            // Prefer finished-goods item with same name if linked later; optional no-op.
        }
        db_commit();
    } catch (Throwable $e) {
        db_rollback();
        throw $e;
    }
    return ['sale_id' => $saleId, 'total_kobo' => $lineTotal];
}

function sale_return(string $saleId): array
{
    require_module('fashion.sales');
    require_permission('sales.record');
    if (!is_uuid($saleId)) {
        return ['errors' => ['form' => 'not_found']];
    }
    $sales = db_select('fashion_sales', ['id' => $saleId], ['limit' => 1]);
    if ($sales === [] || (string) $sales[0]['status'] === 'returned') {
        return ['errors' => ['form' => 'not_found']];
    }
    $lines = db_select('fashion_sale_lines', ['sale_id' => $saleId]);
    db_begin();
    try {
        db_update('fashion_sales', ['status' => 'returned'], ['id' => $saleId]);
        foreach ($lines as $line) {
            $productRows = db_select('fashion_products', ['id' => (string) $line['product_id']], ['limit' => 1]);
            $stockItemId = $productRows[0]['stock_item_id'] ?? null;
            if (is_string($stockItemId) && $stockItemId !== '' && module_enabled('fashion.inventory')) {
                stock_post_movement(
                    $stockItemId,
                    ((int) $line['quantity']) * 100,
                    'sale',
                    'Return ' . $saleId
                );
            }
        }
        db_commit();
    } catch (Throwable $e) {
        db_rollback();
        throw $e;
    }
    return ['ok' => true];
}
