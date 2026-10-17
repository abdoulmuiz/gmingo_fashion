<?php

declare(strict_types=1);

require_once __DIR__ . '/orders.php';
require_once __DIR__ . '/deposits.php';
require_once __DIR__ . '/messages.php';

function fitting_types(): array
{
    return ['first', 'second', 'final', 'other'];
}

function fitting_statuses(): array
{
    return ['scheduled', 'done', 'missed', 'cancelled'];
}

function fitting_utc(DateTimeImmutable $moment): string
{
    return $moment->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
}

function fitting_lagos(string $utc): DateTimeImmutable
{
    $parsed = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $utc, new DateTimeZone('UTC'));
    if (!$parsed instanceof DateTimeImmutable) {
        throw new RuntimeException('Bad fitting time.');
    }
    return $parsed->setTimezone(app_timezone());
}

function fitting_format(string $utc): string
{
    return fitting_lagos($utc)->format('j M Y H:i');
}

function fitting_parse_local(string $date, string $time): ?DateTimeImmutable
{
    $date = trim($date);
    $time = trim($time);
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || !preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $time)) {
        return null;
    }
    $local = DateTimeImmutable::createFromFormat('!Y-m-d H:i', $date . ' ' . $time, app_timezone());
    if (!$local instanceof DateTimeImmutable || $local->format('Y-m-d H:i') !== $date . ' ' . $time) {
        return null;
    }
    return $local;
}

function fitting_order(string $orderId): array
{
    require_module('fashion.orders');
    $order = order_find($orderId);
    if ($order === null) {
        http_response_code(404);
        throw new HttpException(t('not_found'), 404);
    }
    return $order;
}

function fitting_schedule(string $orderId, array $input): array
{
    fitting_order($orderId);
    $local = fitting_parse_local((string) ($input['date'] ?? ''), (string) ($input['time'] ?? ''));
    if ($local === null) {
        return ['error' => 'fitting_time_invalid'];
    }
    $type = (string) ($input['fitting_type'] ?? '');
    if (!in_array($type, fitting_types(), true)) {
        return ['error' => 'required'];
    }
    $note = trim((string) ($input['note'] ?? ''));
    if (strlen($note) > 500) {
        return ['error' => 'fitting_note_long'];
    }
    $userId = current_user_id();
    if ($userId === null) {
        throw new RuntimeException('Missing session.');
    }
    if ($local < app_now()) {
        if (!deposit_is_owner()) {
            return ['error' => 'fitting_in_past'];
        }
        if ($note === '') {
            return ['error' => 'fitting_past_note'];
        }
    }
    $now = fitting_utc(app_now());
    $id = db_insert('fashion_fittings', [
        'order_id' => $orderId,
        'starts_at' => fitting_utc($local),
        'fitting_type' => $type,
        'status' => 'scheduled',
        'note' => $note === '' ? null : $note,
        'created_by' => $userId,
        'created_at' => $now,
        'status_by' => $userId,
        'status_at' => $now,
    ]);
    message_queue_fitting($orderId, $id, $local->format('Y-m-d H:i:s'));
    return ['id' => $id];
}

function fitting_set_status(string $orderId, string $fittingId, string $status): array
{
    fitting_order($orderId);
    if (!in_array($status, ['done', 'missed', 'cancelled'], true)) {
        return ['error' => 'required'];
    }
    if (!is_uuid($fittingId)) {
        http_response_code(404);
        throw new HttpException(t('not_found'), 404);
    }
    $rows = db_select('fashion_fittings', ['id' => $fittingId, 'order_id' => $orderId], ['limit' => 1]);
    if ($rows === []) {
        http_response_code(404);
        throw new HttpException(t('not_found'), 404);
    }
    $userId = current_user_id();
    if ($userId === null) {
        throw new RuntimeException('Missing session.');
    }
    if ((string) $rows[0]['status'] !== $status) {
        db_update('fashion_fittings', [
            'status' => $status,
            'status_by' => $userId,
            'status_at' => fitting_utc(app_now()),
        ], ['id' => $fittingId]);
    }
    return ['id' => $fittingId];
}

function fitting_present(array $row): array
{
    $note = $row['note'] ?? null;
    $local = fitting_lagos((string) $row['starts_at']);
    return [
        'id' => (string) $row['id'],
        'order_id' => (string) $row['order_id'],
        'order_number' => (string) ($row['order_number'] ?? ''),
        'fitting_type' => (string) $row['fitting_type'],
        'status' => (string) $row['status'],
        'note' => is_string($note) ? $note : '',
        'starts_at' => (string) $row['starts_at'],
        'when' => $local->format('j M Y H:i'),
        'clock' => $local->format('H:i'),
        'day' => $local->format('Y-m-d'),
        'created_by' => (string) $row['created_by'],
        'created_name' => (string) ($row['created_name'] ?? ''),
        'created_when' => fitting_format((string) $row['created_at']),
        'status_by' => (string) $row['status_by'],
        'status_name' => (string) ($row['status_name'] ?? ''),
        'status_when' => fitting_format((string) $row['status_at']),
    ];
}

function fitting_list(string $orderId): array
{
    fitting_order($orderId);
    $rows = db_query(
        'SELECT f.id, f.order_id, f.starts_at, f.fitting_type, f.status, f.note,
                f.created_by, f.created_at, f.status_by, f.status_at, o.order_number,
                cu.name AS created_name, su.name AS status_name
         FROM fashion_fittings f
         INNER JOIN fashion_orders o ON o.id = f.order_id AND o.tenant_id = :tenant_id
         LEFT JOIN fashion_users cu ON cu.id = f.created_by AND cu.tenant_id = :tenant_id
         LEFT JOIN fashion_users su ON su.id = f.status_by AND su.tenant_id = :tenant_id
         WHERE f.tenant_id = :tenant_id AND f.order_id = :order_id
         ORDER BY f.starts_at ASC, f.id ASC',
        ['order_id' => $orderId]
    );
    $fittings = [];
    foreach ($rows as $row) {
        $fittings[] = fitting_present($row);
    }
    return $fittings;
}

function fitting_month(string $raw): DateTimeImmutable
{
    $raw = trim($raw);
    $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $raw . '-01', app_timezone());
    if ($parsed instanceof DateTimeImmutable && $parsed->format('Y-m') === $raw) {
        return $parsed->setTime(0, 0);
    }
    $now = app_now();
    return $now->setTime(0, 0)->setDate((int) $now->format('Y'), (int) $now->format('n'), 1);
}

function fitting_date(string $raw): ?DateTimeImmutable
{
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw)) {
        return null;
    }
    $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $raw, app_timezone());
    if (!$parsed instanceof DateTimeImmutable || $parsed->format('Y-m-d') !== $raw) {
        return null;
    }
    return $parsed->setTime(0, 0);
}

function fitting_calendar_item(array $row): array
{
    $local = fitting_lagos((string) $row['starts_at']);
    $status = (string) $row['status'];
    $class = 'cal-fitting';
    $label = 'fitting_label';
    if ($status === 'done') {
        $class = 'cal-done';
        $label = 'fitting_status_done';
    } elseif ($status === 'missed') {
        $class = 'cal-missed';
        $label = 'fitting_status_missed';
    }
    $note = $row['note'] ?? null;
    return [
        'kind' => 'fitting',
        'class' => $class,
        'label_key' => $label,
        'text' => $local->format('H:i') . ' · ' . (string) $row['order_number'],
        'note' => is_string($note) ? $note : '',
        'href' => '/orders/' . (string) $row['order_id'],
    ];
}

function fitting_due_item(array $row): array
{
    return [
        'kind' => 'due',
        'class' => 'cal-due',
        'label_key' => 'due_label',
        'text' => (string) $row['order_number'],
        'note' => '',
        'href' => '/orders/' . (string) $row['id'],
    ];
}

function fitting_items_for_range(DateTimeImmutable $start, DateTimeImmutable $end): array
{
    $fromUtc = fitting_utc($start);
    $toUtc = fitting_utc($end->modify('+1 day'));
    $fittings = db_query(
        'SELECT f.id, f.order_id, f.starts_at, f.fitting_type, f.status, f.note, o.order_number
         FROM fashion_fittings f
         INNER JOIN fashion_orders o ON o.id = f.order_id AND o.tenant_id = :tenant_id
         WHERE f.tenant_id = :tenant_id AND f.status <> \'cancelled\'
           AND f.starts_at >= :from_utc AND f.starts_at < :to_utc
         ORDER BY f.starts_at ASC, o.order_number ASC',
        ['from_utc' => $fromUtc, 'to_utc' => $toUtc]
    );
    $dues = db_query(
        'SELECT id, order_number, due_date
         FROM fashion_orders
         WHERE tenant_id = :tenant_id AND due_date >= :from_date AND due_date <= :to_date
         ORDER BY due_date ASC, order_number ASC',
        ['from_date' => $start->format('Y-m-d'), 'to_date' => $end->format('Y-m-d')]
    );
    $byDay = [];
    foreach ($fittings as $row) {
        $day = fitting_lagos((string) $row['starts_at'])->format('Y-m-d');
        $byDay[$day][] = fitting_calendar_item($row);
    }
    foreach ($dues as $row) {
        $byDay[(string) $row['due_date']][] = fitting_due_item($row);
    }
    return $byDay;
}

function fitting_day_cell(DateTimeImmutable $day, array $items, bool $inMonth): array
{
    $visible = array_slice($items, 0, 3);
    $hidden = count($items) - count($visible);
    $date = $day->format('Y-m-d');
    return [
        'date' => $date,
        'number' => $day->format('j'),
        'in_month' => $inMonth,
        'today' => $date === app_today(),
        'items' => $inMonth ? $visible : [],
        'hidden' => $inMonth ? $hidden : 0,
        'more_href' => '/calendar/' . $date,
    ];
}

function calendar_page(string $raw): array
{
    require_module('fashion.orders');
    $month = fitting_month($raw);
    $days = (int) $month->format('t');
    $last = $month->setDate((int) $month->format('Y'), (int) $month->format('n'), $days);
    $byDay = fitting_items_for_range($month, $last);
    $gridStart = $month->modify('-' . ((int) $month->format('N') - 1) . ' days');
    $gridEnd = $last->modify('+' . (7 - (int) $last->format('N')) . ' days');
    $weeks = [];
    $cursor = $gridStart;
    while ($cursor <= $gridEnd) {
        $week = [];
        for ($i = 0; $i < 7; $i++) {
            $key = $cursor->format('Y-m-d');
            $week[] = fitting_day_cell($cursor, $byDay[$key] ?? [], $cursor->format('Y-m') === $month->format('Y-m'));
            $cursor = $cursor->modify('+1 day');
        }
        $weeks[] = $week;
    }
    return [
        'month' => $month->format('Y-m'),
        'title' => t('month_' . (string) (int) $month->format('n')) . ' ' . $month->format('Y'),
        'previous' => '/calendar?month=' . $month->modify('-1 month')->format('Y-m'),
        'next' => '/calendar?month=' . $month->modify('+1 month')->format('Y-m'),
        'weeks' => $weeks,
    ];
}

function calendar_day(string $raw): ?array
{
    require_module('fashion.orders');
    $day = fitting_date($raw);
    if ($day === null) {
        return null;
    }
    $byDay = fitting_items_for_range($day, $day);
    return [
        'date' => $day->format('Y-m-d'),
        'title' => $day->format('j') . ' ' . t('month_' . (string) (int) $day->format('n')) . ' ' . $day->format('Y'),
        'today' => $day->format('Y-m-d') === app_today(),
        'items' => $byDay[$day->format('Y-m-d')] ?? [],
        'back' => '/calendar?month=' . $day->format('Y-m'),
    ];
}
