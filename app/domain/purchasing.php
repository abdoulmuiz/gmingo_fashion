<?php

declare(strict_types=1);

require_once __DIR__ . '/modules.php';
require_once __DIR__ . '/stock.php';
require_once __DIR__ . '/staff.php';

function supplier_list(bool $activeOnly = true): array
{
    require_module('fashion.inventory');
    require_permission('purchasing.manage');
    $sql = 'SELECT id, name, phone, email, note, active, created_at
            FROM fashion_suppliers WHERE tenant_id = :tenant_id';
    if ($activeOnly) {
        $sql .= ' AND active = 1';
    }
    $sql .= ' ORDER BY name ASC LIMIT 200';
    $rows = db_query($sql);
    $out = [];
    foreach ($rows as $row) {
        if (is_array($row)) {
            $out[] = [
                'id' => (string) $row['id'],
                'name' => (string) $row['name'],
                'phone' => (string) $row['phone'],
                'email' => (string) $row['email'],
                'note' => (string) $row['note'],
                'active' => (int) $row['active'] === 1,
            ];
        }
    }
    return $out;
}

function supplier_create(array $input): array
{
    require_module('fashion.inventory');
    require_permission('purchasing.manage');
    $name = trim((string) ($input['name'] ?? ''));
    $phoneRaw = trim((string) ($input['phone'] ?? ''));
    $email = strtolower(trim((string) ($input['email'] ?? '')));
    $note = trim((string) ($input['note'] ?? ''));
    $errors = [];
    if ($name === '' || strlen($name) > 190) {
        $errors['name'] = 'required';
    }
    $phone = '';
    if ($phoneRaw !== '') {
        $normalized = phone_normalize($phoneRaw);
        if ($normalized === null) {
            $errors['phone'] = 'invalid_phone';
        } else {
            $phone = $normalized;
        }
    }
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'invalid_email';
    }
    if (strlen($note) > 500) {
        $errors['note'] = 'required';
    }
    if ($errors !== []) {
        return ['errors' => $errors];
    }
    $id = db_insert('fashion_suppliers', [
        'name' => $name,
        'phone' => $phone,
        'email' => $email,
        'note' => $note,
        'active' => 1,
        'created_at' => app_now()->format('Y-m-d H:i:s'),
    ]);
    return ['supplier_id' => $id];
}

function purchase_order_list(int $limit = 50): array
{
    require_module('fashion.inventory');
    require_permission('purchasing.manage');
    $limit = max(1, min(100, $limit));
    $rows = db_query(
        'SELECT po.id, po.supplier_id, po.status, po.ordered_on, po.note, po.received_at, s.name AS supplier_name
         FROM fashion_purchase_orders po
         INNER JOIN fashion_suppliers s ON s.id = po.supplier_id AND s.tenant_id = :tenant_id
         WHERE po.tenant_id = :tenant_id
         ORDER BY po.created_at DESC
         LIMIT ' . (string) $limit
    );
    $out = [];
    foreach ($rows as $row) {
        if (is_array($row)) {
            $out[] = [
                'id' => (string) $row['id'],
                'supplier_id' => (string) $row['supplier_id'],
                'supplier_name' => (string) $row['supplier_name'],
                'status' => (string) $row['status'],
                'ordered_on' => (string) $row['ordered_on'],
                'note' => (string) $row['note'],
                'received_at' => $row['received_at'] !== null ? (string) $row['received_at'] : null,
            ];
        }
    }
    return $out;
}

function purchase_order_create(array $input): array
{
    require_module('fashion.inventory');
    require_permission('purchasing.manage');
    $supplierId = (string) ($input['supplier_id'] ?? '');
    $itemId = (string) ($input['stock_item_id'] ?? '');
    $qty = stock_hundredths((string) ($input['quantity'] ?? ''), false);
    $costRaw = trim((string) ($input['unit_cost_kobo'] ?? '0'));
    $note = trim((string) ($input['note'] ?? ''));
    if (!is_uuid($supplierId) || db_select('fashion_suppliers', ['id' => $supplierId], ['limit' => 1]) === []) {
        return ['errors' => ['supplier_id' => 'required']];
    }
    if (!is_uuid($itemId) || db_select('fashion_stock_items', ['id' => $itemId], ['limit' => 1]) === []) {
        return ['errors' => ['stock_item_id' => 'required']];
    }
    if ($qty === null || $qty <= 0) {
        return ['errors' => ['quantity' => 'invalid_stock_amount']];
    }
    if (!preg_match('/^\d+$/', $costRaw)) {
        return ['errors' => ['unit_cost_kobo' => 'invalid_price']];
    }
    $userId = current_user_id();
    if ($userId === null) {
        return ['errors' => ['form' => 'not_allowed']];
    }
    $poId = db_insert('fashion_purchase_orders', [
        'supplier_id' => $supplierId,
        'status' => 'ordered',
        'ordered_on' => app_today(),
        'note' => $note,
        'created_by' => $userId,
        'created_at' => app_now()->format('Y-m-d H:i:s'),
        'received_at' => null,
    ]);
    db_insert('fashion_purchase_order_lines', [
        'purchase_order_id' => $poId,
        'stock_item_id' => $itemId,
        'quantity_hundredths' => $qty,
        'unit_cost_kobo' => (int) $costRaw,
    ]);
    return ['purchase_order_id' => $poId];
}

function purchase_order_receive(string $poId): array
{
    require_module('fashion.inventory');
    require_permission('purchasing.manage');
    if (!is_uuid($poId)) {
        return ['errors' => ['form' => 'not_found']];
    }
    $rows = db_select('fashion_purchase_orders', ['id' => $poId], ['limit' => 1]);
    if ($rows === [] || (string) $rows[0]['status'] === 'received') {
        return ['errors' => ['form' => 'not_found']];
    }
    $lines = db_select('fashion_purchase_order_lines', ['purchase_order_id' => $poId]);
    db_begin();
    try {
        foreach ($lines as $line) {
            stock_post_movement(
                (string) $line['stock_item_id'],
                (int) $line['quantity_hundredths'],
                'purchase',
                'PO ' . $poId
            );
            if ((int) $line['unit_cost_kobo'] > 0) {
                db_update('fashion_stock_items', [
                    'unit_cost_kobo' => (int) $line['unit_cost_kobo'],
                ], ['id' => (string) $line['stock_item_id']]);
            }
        }
        db_update('fashion_purchase_orders', [
            'status' => 'received',
            'received_at' => app_now()->format('Y-m-d H:i:s'),
        ], ['id' => $poId]);
        db_commit();
    } catch (Throwable $e) {
        db_rollback();
        throw $e;
    }
    return ['ok' => true];
}

function expense_list(int $limit = 50): array
{
    require_module('fashion.finance');
    require_permission('finance.record');
    $limit = max(1, min(100, $limit));
    $rows = db_query(
        'SELECT id, category, amount_kobo, spent_on, note, created_at
         FROM fashion_expenses
         WHERE tenant_id = :tenant_id
         ORDER BY spent_on DESC, created_at DESC
         LIMIT ' . (string) $limit
    );
    $out = [];
    foreach ($rows as $row) {
        if (is_array($row)) {
            $out[] = [
                'id' => (string) $row['id'],
                'category' => (string) $row['category'],
                'amount_kobo' => (int) $row['amount_kobo'],
                'spent_on' => (string) $row['spent_on'],
                'note' => (string) ($row['note'] ?? ''),
            ];
        }
    }
    return $out;
}

function expense_create(array $input): array
{
    require_module('fashion.finance');
    require_permission('finance.record');
    $category = trim((string) ($input['category'] ?? ''));
    $amountRaw = trim((string) ($input['amount_kobo'] ?? ''));
    $spentOn = trim((string) ($input['spent_on'] ?? app_today()));
    $note = trim((string) ($input['note'] ?? ''));
    $errors = [];
    if ($category === '' || strlen($category) > 40) {
        $errors['category'] = 'required';
    }
    if (!preg_match('/^\d+$/', $amountRaw) || (int) $amountRaw <= 0) {
        $errors['amount_kobo'] = 'invalid_price';
    }
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $spentOn, app_timezone());
    if (!$date instanceof DateTimeImmutable || $date->format('Y-m-d') !== $spentOn) {
        $errors['spent_on'] = 'invalid_date';
    }
    if (strlen($note) > 500) {
        $errors['note'] = 'required';
    }
    if ($errors !== []) {
        return ['errors' => $errors];
    }
    $userId = current_user_id();
    if ($userId === null) {
        return ['errors' => ['form' => 'not_allowed']];
    }
    $id = db_insert('fashion_expenses', [
        'order_id' => null,
        'category' => $category,
        'amount_kobo' => (int) $amountRaw,
        'spent_on' => $spentOn,
        'note' => $note,
        'created_by' => $userId,
        'created_at' => app_now()->format('Y-m-d H:i:s'),
    ]);
    return ['expense_id' => $id];
}
