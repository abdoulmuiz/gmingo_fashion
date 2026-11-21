<?php

declare(strict_types=1);

require_once __DIR__ . '/modules.php';
require_once __DIR__ . '/orders.php';

function stock_categories(): array
{
    return ['fabric', 'thread', 'zip', 'button', 'lining', 'interfacing', 'packaging', 'other'];
}

function stock_kinds(): array
{
    return ['raw', 'finished'];
}

function stock_units(): array
{
    return ['cones', 'yards', 'pieces', 'metres', 'other'];
}

function stock_reasons(): array
{
    return ['purchase', 'used', 'adjustment', 'correction', 'opening', 'sale', 'transfer'];
}

function stock_post_movement(string $itemId, int $changeHundredths, string $reason, ?string $note = null, ?string $orderId = null, ?string $branchId = null, ?string $actorUserId = null): string
{
    require_module('fashion.inventory');
    stock_require($itemId);
    if ($changeHundredths === 0 || !in_array($reason, stock_reasons(), true)) {
        throw new RuntimeException('Invalid stock movement.');
    }
    $userId = $actorUserId;
    if ($userId === null || !is_uuid($userId)) {
        $userId = current_user_id();
    }
    if ($userId === null || !is_uuid($userId)) {
        throw new RuntimeException('Missing session.');
    }
    $ownTx = !db()->inTransaction();
    if ($ownTx) {
        db_begin();
    }
    try {
        $locked = db_query(
            'SELECT id FROM fashion_stock_items WHERE tenant_id = :tenant_id AND id = :item_id FOR UPDATE',
            ['item_id' => $itemId]
        );
        if ($locked === []) {
            throw new HttpException(t('not_found'), 404);
        }
        $sumRows = db_query(
            'SELECT COALESCE(SUM(change_hundredths), 0) AS qty
             FROM fashion_stock_movements
             WHERE tenant_id = :tenant_id AND item_id = :item_id',
            ['item_id' => $itemId]
        );
        $current = (int) ($sumRows[0]['qty'] ?? 0);
        if ($current + $changeHundredths < 0) {
            throw new RuntimeException('stock_below_zero');
        }
        $data = [
            'item_id' => $itemId,
            'change_hundredths' => $changeHundredths,
            'reason' => $reason,
            'order_id' => $orderId,
            'user_id' => $userId,
            'note' => $note,
            'created_at' => app_now()->format('Y-m-d H:i:s'),
        ];
        if ($branchId !== null) {
            $data['branch_id'] = $branchId;
        }
        $id = db_insert('fashion_stock_movements', $data);
        if ($ownTx) {
            db_commit();
        }
        return $id;
    } catch (Throwable $e) {
        if ($ownTx && db()->inTransaction()) {
            db_rollback();
        }
        throw $e;
    }
}

function stock_hundredths(string $raw, bool $allowNegative = false): ?int
{
    $raw = trim($raw);
    $negative = false;
    if ($allowNegative && str_starts_with($raw, '-')) {
        $negative = true;
        $raw = substr($raw, 1);
    }
    if (!preg_match('/^(?:0|[1-9]\d{0,5})(?:\.\d{1,2})?$/', $raw)) {
        return null;
    }
    $parts = explode('.', $raw, 2);
    $whole = (int) $parts[0];
    $fraction = str_pad(substr($parts[1] ?? '0', 0, 2), 2, '0');
    $hundredths = ($whole * 100) + (int) $fraction;
    if ($negative && $hundredths === 0) {
        return null;
    }
    return $negative ? -$hundredths : $hundredths;
}

function stock_format(int $hundredths): string
{
    $negative = $hundredths < 0;
    $hundredths = abs($hundredths);
    $whole = intdiv($hundredths, 100);
    $rest = $hundredths % 100;
    return ($negative ? '-' : '') . (string) $whole . '.' . str_pad((string) $rest, 2, '0', STR_PAD_LEFT);
}

function stock_require(string $itemId): array
{
    require_module('fashion.inventory');
    if (!is_uuid($itemId)) {
        http_response_code(404);
        throw new HttpException(t('not_found'), 404);
    }
    $rows = db_select('fashion_stock_items', ['id' => $itemId], ['limit' => 1]);
    if ($rows === []) {
        http_response_code(404);
        throw new HttpException(t('not_found'), 404);
    }
    return $rows[0];
}

function stock_quantity(string $itemId): int
{
    stock_require($itemId);
    $rows = db_query(
        'SELECT COALESCE(SUM(change_hundredths), 0) AS qty
         FROM fashion_stock_movements
         WHERE tenant_id = :tenant_id AND item_id = :item_id',
        ['item_id' => $itemId]
    );
    return (int) ($rows[0]['qty'] ?? 0);
}

function stock_low_count(): int
{
    require_module('fashion.inventory');
    $rows = db_query(
        'SELECT COUNT(*) AS low_count
         FROM fashion_stock_items i
         WHERE i.tenant_id = :tenant_id
           AND i.minimum_hundredths IS NOT NULL
           AND COALESCE((SELECT SUM(m.change_hundredths) FROM fashion_stock_movements m WHERE m.tenant_id = :tenant_id AND m.item_id = i.id), 0) <= i.minimum_hundredths'
    );
    return (int) ($rows[0]['low_count'] ?? 0);
}

function stock_present(array $row): array
{
    $minimum = $row['minimum_hundredths'];
    $colour = $row['colour'] ?? null;
    $lastUser = $row['last_user_name'] ?? null;
    $kind = (string) ($row['kind'] ?? 'raw');
    if (!in_array($kind, stock_kinds(), true)) {
        $kind = 'raw';
    }
    $unitCost = $row['unit_cost_kobo'] ?? null;
    return [
        'id' => (string) $row['id'],
        'name' => (string) $row['name'],
        'category' => (string) $row['category'],
        'unit' => (string) $row['unit'],
        'kind' => $kind,
        'unit_cost_kobo' => $unitCost === null || $unitCost === '' ? null : (int) $unitCost,
        'colour' => is_string($colour) && $colour !== '' ? $colour : null,
        'minimum_hundredths' => $minimum === null || $minimum === '' ? null : (int) $minimum,
        'quantity_hundredths' => (int) ($row['quantity_hundredths'] ?? 0),
        'last_user_name' => is_string($lastUser) && $lastUser !== '' ? $lastUser : null,
    ];
}

function stock_item_sql(): string
{
    return 'SELECT i.id, i.name, i.category, i.unit, i.colour, i.minimum_hundredths, i.kind, i.unit_cost_kobo,
            COALESCE((SELECT SUM(m.change_hundredths) FROM fashion_stock_movements m WHERE m.tenant_id = :tenant_id AND m.item_id = i.id), 0) AS quantity_hundredths,
            (SELECT u.name FROM fashion_stock_movements lm
                LEFT JOIN fashion_users u ON u.id = lm.user_id AND u.tenant_id = :tenant_id
                WHERE lm.tenant_id = :tenant_id AND lm.item_id = i.id
                ORDER BY lm.seq DESC LIMIT 1) AS last_user_name
         FROM fashion_stock_items i
         WHERE i.tenant_id = :tenant_id';
}

function stock_filters(string $search, string $category): array
{
    $sql = '';
    $params = [];
    if (in_array($category, stock_categories(), true)) {
        $sql .= ' AND i.category = :category';
        $params['category'] = $category;
    }
    if ($search !== '') {
        $like = like_contains($search);
        $sql .= ' AND (i.name LIKE :q_name ESCAPE \'\\\\\' OR i.colour LIKE :q_colour ESCAPE \'\\\\\')';
        $params['q_name'] = $like;
        $params['q_colour'] = $like;
    }
    return [$sql, $params];
}

function stock_list(string $search, string $category, int $page = 1): array
{
    require_module('fashion.inventory');
    $page = list_page($page);
    $search = trim($search);
    [$where, $params] = stock_filters($search, $category);
    $countRows = db_query(
        'SELECT COUNT(*) AS row_count FROM fashion_stock_items i WHERE i.tenant_id = :tenant_id' . $where,
        $params
    );
    $rowCount = (int) ($countRows[0]['row_count'] ?? 0);
    $rows = db_query(
        stock_item_sql() . $where . ' ORDER BY i.name ASC LIMIT :row_limit OFFSET :row_offset',
        $params + [
            'row_limit' => list_page_size(),
            'row_offset' => ($page - 1) * list_page_size(),
        ]
    );
    $items = [];
    foreach ($rows as $row) {
        $items[] = stock_present($row);
    }
    return [
        'items' => $items,
        'page' => $page,
        'row_count' => $rowCount,
        'has_previous' => $page > 1,
        'has_next' => $page * list_page_size() < $rowCount,
    ];
}

function stock_low_items(): array
{
    require_module('fashion.inventory');
    $rows = db_query(
        stock_item_sql() . ' AND i.minimum_hundredths IS NOT NULL
           AND COALESCE((SELECT SUM(m.change_hundredths) FROM fashion_stock_movements m WHERE m.tenant_id = :tenant_id AND m.item_id = i.id), 0) <= i.minimum_hundredths
         ORDER BY i.name ASC'
    );
    $items = [];
    foreach ($rows as $row) {
        $items[] = stock_present($row);
    }
    return $items;
}

function stock_find(string $itemId): ?array
{
    require_module('fashion.inventory');
    if (!is_uuid($itemId)) {
        return null;
    }
    $rows = db_query(
        stock_item_sql() . ' AND i.id = :item_id LIMIT 1',
        ['item_id' => $itemId]
    );
    if ($rows === []) {
        return null;
    }
    return stock_present($rows[0]);
}

function stock_create(array $input): array
{
    require_module('fashion.inventory');
    $name = trim((string) ($input['name'] ?? ''));
    $category = (string) ($input['category'] ?? '');
    $unit = (string) ($input['unit'] ?? '');
    $kind = (string) ($input['kind'] ?? 'raw');
    $colour = trim((string) ($input['colour'] ?? ''));
    $minimumRaw = trim((string) ($input['minimum'] ?? ''));
    $costRaw = trim((string) ($input['unit_cost_kobo'] ?? ''));
    if ($name === '' || strlen($name) > 190) {
        return ['error' => 'required'];
    }
    if (!in_array($category, stock_categories(), true) || !in_array($unit, stock_units(), true)) {
        return ['error' => 'required'];
    }
    if (!in_array($kind, stock_kinds(), true)) {
        return ['error' => 'required'];
    }
    if (strlen($colour) > 80) {
        return ['error' => 'required'];
    }
    $minimum = null;
    if ($minimumRaw !== '') {
        $minimum = stock_hundredths($minimumRaw, false);
        if ($minimum === null) {
            return ['error' => 'invalid_stock_amount'];
        }
    }
    $unitCost = null;
    if ($costRaw !== '') {
        if (!preg_match('/^\d+$/', $costRaw)) {
            return ['error' => 'invalid_price'];
        }
        $unitCost = (int) $costRaw;
    }
    $id = db_insert('fashion_stock_items', [
        'name' => $name,
        'category' => $category,
        'unit' => $unit,
        'kind' => $kind,
        'unit_cost_kobo' => $unitCost,
        'colour' => $colour === '' ? null : $colour,
        'minimum_hundredths' => $minimum,
        'created_at' => app_now()->format('Y-m-d H:i:s'),
    ]);
    return ['id' => $id];
}

function stock_adjust(string $itemId, array $input): array
{
    require_module('fashion.inventory');
    stock_require($itemId);
    $step = (string) ($input['step'] ?? '');
    $reason = (string) ($input['reason'] ?? '');
    $note = trim((string) ($input['note'] ?? ''));
    $orderRaw = trim((string) ($input['order_id'] ?? ''));
    if (strlen($note) > 500) {
        return ['error' => 'stock_note_long'];
    }
    if ($step === 'plus') {
        $change = 100;
        $reason = 'purchase';
    } elseif ($step === 'minus') {
        $change = -100;
        $reason = 'used';
    } else {
        $parsed = stock_hundredths((string) ($input['amount'] ?? ''), true);
        if ($parsed === null || $parsed === 0) {
            return ['error' => 'invalid_stock_amount'];
        }
        if (!in_array($reason, stock_reasons(), true)) {
            return ['error' => 'required'];
        }
        $change = $parsed;
    }
    $orderId = null;
    if ($orderRaw !== '') {
        if (!is_uuid($orderRaw) || db_select('fashion_orders', ['id' => $orderRaw], ['limit' => 1]) === []) {
            return ['error' => 'stock_order_missing'];
        }
        $orderId = $orderRaw;
    }
    try {
        $id = stock_post_movement(
            $itemId,
            $change,
            $reason,
            $note === '' ? null : $note,
            $orderId
        );
    } catch (RuntimeException $e) {
        if ($e->getMessage() === 'stock_below_zero') {
            return ['error' => 'stock_below_zero'];
        }
        throw $e;
    }
    return ['id' => $id, 'change_hundredths' => $change];
}

function stock_history(string $itemId, int $page = 1): array
{
    require_module('fashion.inventory');
    stock_require($itemId);
    $page = list_page($page);
    $countRows = db_query(
        'SELECT COUNT(*) AS row_count FROM fashion_stock_movements WHERE tenant_id = :tenant_id AND item_id = :item_id',
        ['item_id' => $itemId]
    );
    $rowCount = (int) ($countRows[0]['row_count'] ?? 0);
    $rows = db_query(
        'SELECT m.id, m.change_hundredths, m.reason, m.order_id, m.note, m.created_at, m.user_id,
                u.name AS user_name, o.order_number
         FROM fashion_stock_movements m
         LEFT JOIN fashion_users u ON u.id = m.user_id AND u.tenant_id = :tenant_id
         LEFT JOIN fashion_orders o ON o.id = m.order_id AND o.tenant_id = :tenant_id
         WHERE m.tenant_id = :tenant_id AND m.item_id = :item_id
         ORDER BY m.seq ASC
         LIMIT :row_limit OFFSET :row_offset',
        [
            'item_id' => $itemId,
            'row_limit' => list_page_size(),
            'row_offset' => ($page - 1) * list_page_size(),
        ]
    );
    $history = [];
    foreach ($rows as $row) {
        $orderId = $row['order_id'];
        $note = $row['note'] ?? null;
        $history[] = [
            'id' => (string) $row['id'],
            'change_hundredths' => (int) $row['change_hundredths'],
            'reason' => (string) $row['reason'],
            'order_id' => is_string($orderId) && $orderId !== '' ? $orderId : null,
            'order_number' => (string) ($row['order_number'] ?? ''),
            'note' => is_string($note) ? $note : '',
            'created_at' => (string) $row['created_at'],
            'user_id' => (string) $row['user_id'],
            'user_name' => (string) ($row['user_name'] ?? ''),
        ];
    }
    return [
        'rows' => $history,
        'page' => $page,
        'row_count' => $rowCount,
        'has_previous' => $page > 1,
        'has_next' => $page * list_page_size() < $rowCount,
    ];
}
