<?php

declare(strict_types=1);

require_once __DIR__ . '/modules.php';

function yards_hundredths(string $raw): ?int
{
    $raw = trim($raw);
    if (!preg_match('/^(?:0|[1-9]\d{0,5})(?:\.\d{1,2})?$/', $raw)) {
        return null;
    }
    $parts = explode('.', $raw, 2);
    $whole = (int) $parts[0];
    $fraction = str_pad(substr($parts[1] ?? '0', 0, 2), 2, '0');
    return ($whole * 100) + (int) $fraction;
}

function yards_format(int $hundredths): string
{
    $negative = $hundredths < 0;
    $hundredths = abs($hundredths);
    $whole = intdiv($hundredths, 100);
    $rest = $hundredths % 100;
    return ($negative ? '-' : '') . (string) $whole . '.' . str_pad((string) $rest, 2, '0', STR_PAD_LEFT);
}

function yards_left(int $receivedHundredths, int $usedHundredths): int
{
    return $receivedHundredths - $usedHundredths;
}

function fabric_require_order(string $orderId): array
{
    require_module('fashion.orders');
    if (!is_uuid($orderId)) {
        http_response_code(404);
        throw new HttpException(t('not_found'), 404);
    }
    $rows = db_select('fashion_orders', ['id' => $orderId]);
    if ($rows === []) {
        http_response_code(404);
        throw new HttpException(t('not_found'), 404);
    }
    return $rows[0];
}

function fabric_hundredths_column(mixed $value): ?int
{
    if ($value === null || $value === '') {
        return null;
    }
    return (int) $value;
}

function fabric_set_supplied(string $orderId, bool $on): array
{
    fabric_require_order($orderId);
    db_update('fashion_orders', [
        'customer_supplied_fabric' => $on ? 1 : 0,
    ], ['id' => $orderId]);
    return [];
}

function fabric_save_yards(string $orderId, string $receivedRaw, string $usedRaw, string $note): array
{
    $order = fabric_require_order($orderId);
    if ((int) $order['customer_supplied_fabric'] !== 1) {
        return ['error' => 'fabric_off'];
    }
    $note = trim($note);
    if (strlen($note) > 500) {
        return ['error' => 'fabric_note_long'];
    }
    $currentReceived = fabric_hundredths_column($order['yards_received']);
    $currentUsed = fabric_hundredths_column($order['yards_used']);
    $nextReceived = $currentReceived;
    $nextUsed = $currentUsed;
    if (trim($receivedRaw) !== '') {
        $parsed = yards_hundredths($receivedRaw);
        if ($parsed === null) {
            return ['error' => 'invalid_yards'];
        }
        $nextReceived = $parsed;
    }
    if (trim($usedRaw) !== '') {
        $parsed = yards_hundredths($usedRaw);
        if ($parsed === null) {
            return ['error' => 'invalid_yards'];
        }
        $nextUsed = $parsed;
    }
    $receivedValue = $nextReceived ?? 0;
    $usedValue = $nextUsed ?? 0;
    if ($usedValue > $receivedValue) {
        return ['error' => 'yards_used_above'];
    }
    $changes = [];
    if ($nextReceived !== $currentReceived && $nextReceived !== null) {
        $changes[] = ['field_name' => 'received', 'old' => $currentReceived, 'new' => $nextReceived];
    }
    if ($nextUsed !== $currentUsed && $nextUsed !== null) {
        $changes[] = ['field_name' => 'used', 'old' => $currentUsed, 'new' => $nextUsed];
    }
    if ($changes === []) {
        return [];
    }
    $userId = current_user_id();
    if ($userId === null) {
        throw new RuntimeException('Missing session.');
    }
    $now = app_now()->format('Y-m-d H:i:s');
    $storedNote = $note === '' ? null : $note;
    db_begin();
    try {
        db_update('fashion_orders', [
            'yards_received' => $nextReceived,
            'yards_used' => $nextUsed,
        ], ['id' => $orderId]);
        foreach ($changes as $change) {
            db_insert('fashion_order_fabric_log', [
                'order_id' => $orderId,
                'user_id' => $userId,
                'field_name' => $change['field_name'],
                'old_hundredths' => $change['old'],
                'new_hundredths' => $change['new'],
                'note' => $storedNote,
                'created_at' => $now,
            ]);
        }
        db_commit();
    } catch (Throwable $e) {
        db_rollback();
        throw $e;
    }
    return [];
}

function fabric_has_log(string $orderId): bool
{
    fabric_require_order($orderId);
    return db_select('fashion_order_fabric_log', ['order_id' => $orderId], ['limit' => 1]) !== [];
}

function fabric_log(string $orderId): array
{
    fabric_require_order($orderId);
    $rows = db_query(
        'SELECT l.id, l.field_name, l.old_hundredths, l.new_hundredths, l.note, l.created_at, l.user_id, u.name AS user_name
         FROM fashion_order_fabric_log l
         LEFT JOIN fashion_users u ON u.id = l.user_id AND u.tenant_id = :tenant_id
         WHERE l.tenant_id = :tenant_id AND l.order_id = :order_id
         ORDER BY l.seq ASC
         LIMIT 500',
        ['order_id' => $orderId]
    );
    $log = [];
    foreach ($rows as $row) {
        $old = $row['old_hundredths'];
        $log[] = [
            'id' => (string) $row['id'],
            'field_name' => (string) $row['field_name'],
            'old_hundredths' => $old === null || $old === '' ? null : (int) $old,
            'new_hundredths' => (int) $row['new_hundredths'],
            'note' => (string) ($row['note'] ?? ''),
            'created_at' => (string) $row['created_at'],
            'user_id' => (string) $row['user_id'],
            'user_name' => (string) ($row['user_name'] ?? ''),
        ];
    }
    return $log;
}
