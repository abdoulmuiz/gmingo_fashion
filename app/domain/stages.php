<?php

declare(strict_types=1);

require_once __DIR__ . '/modules.php';
require_once __DIR__ . '/deposits.php';
require_once __DIR__ . '/messages.php';
require_once __DIR__ . '/orders.php';
require_once __DIR__ . '/dashboard.php';

function stage_definitions(): array
{
    require_module('fashion.production');
    return db_select('fashion_stage_definitions', [], ['order' => 'sort_order ASC, name ASC', 'limit' => 100]);
}

function stage_active(): array
{
    $rows = [];
    foreach (stage_definitions() as $stage) {
        if ((int) $stage['active'] === 1) {
            $rows[] = $stage;
        }
    }
    return $rows;
}

function stage_latest(string $orderId): ?array
{
    $rows = db_query(
        'SELECT sd.id, sd.code, sd.name, sd.role, sd.sort_order, sd.active
         FROM fashion_stage_events se
         INNER JOIN fashion_stage_definitions sd ON sd.id = se.stage_id AND sd.tenant_id = :tenant_id
         WHERE se.tenant_id = :tenant_id AND se.order_id = :order_id
         ORDER BY se.seq DESC
         LIMIT 1',
        ['order_id' => $orderId]
    );
    if ($rows === []) {
        return null;
    }
    return $rows[0];
}

function stage_next(string $orderId): ?array
{
    require_module('fashion.production');
    $active = stage_active();
    if ($active === []) {
        return null;
    }
    $current = stage_latest($orderId);
    if ($current === null) {
        return $active[1] ?? null;
    }
    $sort = (int) $current['sort_order'];
    foreach ($active as $stage) {
        if ((int) $stage['sort_order'] > $sort) {
            return $stage;
        }
    }
    return null;
}

function stage_advance(string $orderId): array
{
    require_module('fashion.production');
    if (!is_uuid($orderId) || db_select('fashion_orders', ['id' => $orderId]) === []) {
        http_response_code(404);
        throw new HttpException(t('not_found'), 404);
    }
    $next = stage_next($orderId);
    if ($next === null) {
        return ['error' => 'no_next_stage'];
    }
    $userId = current_user_id();
    if ($userId === null) {
        throw new RuntimeException('Missing session.');
    }
    $depositBlock = deposit_move_block($orderId, (string) $next['id']);
    if ($depositBlock !== null) {
        return $depositBlock;
    }
    db_insert('fashion_stage_events', [
        'order_id' => $orderId,
        'stage_id' => (string) $next['id'],
        'user_id' => $userId,
        'note' => null,
        'kind' => 'move',
        'created_at' => date('Y-m-d H:i:s'),
    ]);
    if ((string) ($next['role'] ?? '') === 'ready') {
        message_queue_ready($orderId);
    }
    return ['stage_id' => (string) $next['id'], 'code' => (string) $next['code']];
}

/** @return list<string> */
function stage_board_filters(): array
{
    return ['all', 'urgent', 'overdue', 'mine', 'week'];
}

function stage_board_normalize_filter(string $filter): string
{
    return in_array($filter, stage_board_filters(), true) ? $filter : 'all';
}

function stage_board_tone(string $code, int $index = 0): string
{
    $code = strtolower($code);
    $map = [
        'new' => 'blue',
        'cutting' => 'purple',
        'sewing' => 'orange',
        'embroidery' => 'teal',
        'finishing' => 'teal',
        'fitting' => 'teal',
        'in_progress' => 'orange',
        'production' => 'orange',
        'ready' => 'green',
        'delivered' => 'green',
        'completed' => 'green',
        'cancelled' => 'red',
    ];
    if (isset($map[$code])) {
        return $map[$code];
    }
    $fallback = ['blue', 'purple', 'orange', 'teal', 'green'];
    return $fallback[$index % count($fallback)];
}

function stage_board_initials(?string $name): ?string
{
    $name = trim((string) $name);
    if ($name === '') {
        return null;
    }
    $parts = preg_split('/\s+/u', $name) ?: [];
    $letters = '';
    foreach ($parts as $part) {
        if ($part === '') {
            continue;
        }
        $letters .= mb_strtoupper(mb_substr($part, 0, 1));
        if (mb_strlen($letters) >= 2) {
            break;
        }
    }
    return $letters !== '' ? $letters : null;
}

function stage_board_due_tone(string $dueDate, bool $finished): string
{
    if ($finished) {
        return 'normal';
    }
    $today = app_today();
    if ($dueDate < $today) {
        return 'overdue';
    }
    $limit = (DateTimeImmutable::createFromFormat('!Y-m-d', $today) ?: new DateTimeImmutable('today'))
        ->modify('+2 days')
        ->format('Y-m-d');
    if ($dueDate <= $limit) {
        return 'soon';
    }
    return 'normal';
}

/**
 * @param array{filter?:string,q?:string} $options
 * @return array{orders_off:bool,groups:list<array>,summary:array,filter:string,q:string,total_cards:int}
 */
function stage_board(array $options = []): array
{
    require_module('fashion.production');
    if (!module_enabled('fashion.orders')) {
        return [
            'orders_off' => true,
            'groups' => [],
            'summary' => [
                'production' => 0,
                'overdue' => 0,
                'week' => 0,
                'ready' => 0,
            ],
            'filter' => 'all',
            'q' => '',
            'total_cards' => 0,
        ];
    }
    $filter = stage_board_normalize_filter((string) ($options['filter'] ?? 'all'));
    $q = trim((string) ($options['q'] ?? ''));
    $today = app_today();
    [$weekStart, $weekEnd] = dashboard_week($today);
    $stages = stage_definitions();
    $firstId = null;
    $activeIds = [];
    foreach ($stages as $stage) {
        if ((int) $stage['active'] === 1) {
            $activeIds[] = (string) $stage['id'];
            if ($firstId === null) {
                $firstId = (string) $stage['id'];
            }
        }
    }
    if ($firstId === null && $stages !== []) {
        $firstId = (string) $stages[0]['id'];
    }
    $stageRole = order_stage_sql('role');
    [$totalSql, $paidSql] = order_money_sql();
    $itemCountSql = '(SELECT COALESCE(SUM(i.quantity), 0) FROM fashion_order_items i WHERE i.order_id = o.id AND i.tenant_id = :tenant_id)';
    $itemNamesSql = '(SELECT GROUP_CONCAT(DISTINCT g.name ORDER BY g.name SEPARATOR \', \')
        FROM fashion_order_items i
        INNER JOIN fashion_garment_types g ON g.id = i.garment_type_id AND g.tenant_id = :tenant_id
        WHERE i.order_id = o.id AND i.tenant_id = :tenant_id)';
    $latestStageSql = '(SELECT se.stage_id FROM fashion_stage_events se
             WHERE se.tenant_id = :tenant_id AND se.order_id = o.id
             ORDER BY se.seq DESC LIMIT 1)';
    $unfinished = '(' . $stageRole . ' IS NULL OR ' . $stageRole . ' <> \'finished\')';
    $where = ' WHERE o.tenant_id = :tenant_id';
    $params = [];
    if ($filter === 'urgent') {
        $where .= ' AND o.urgent = 1';
    } elseif ($filter === 'overdue') {
        $where .= ' AND o.due_date < :today AND ' . $unfinished;
        $params['today'] = $today;
    } elseif ($filter === 'mine') {
        $userId = current_user_id();
        if ($userId === null) {
            $where .= ' AND 1 = 0';
        } else {
            $where .= ' AND o.assigned_user_id = :assignee';
            $params['assignee'] = $userId;
        }
    } elseif ($filter === 'week') {
        $where .= ' AND o.due_date >= :week_start AND o.due_date <= :week_end AND ' . $unfinished;
        $params['week_start'] = $weekStart;
        $params['week_end'] = $weekEnd;
    }
    if ($q !== '') {
        $like = like_contains($q);
        $where .= ' AND (o.order_number LIKE :q_number ESCAPE \'\\\\\' OR c.name LIKE :q_name ESCAPE \'\\\\\' OR c.phone LIKE :q_phone ESCAPE \'\\\\\')';
        $params['q_number'] = $like;
        $params['q_name'] = $like;
        $params['q_phone'] = $like;
    }
    $orders = db_query(
        'SELECT o.id, o.order_number, o.due_date, o.urgent, o.assigned_user_id,
            c.name AS customer_name, c.phone AS customer_phone, u.name AS assigned_name,
            ' . $latestStageSql . ' AS stage_id,
            ' . $stageRole . ' AS stage_role,
            ' . $itemCountSql . ' AS item_count,
            ' . $itemNamesSql . ' AS item_names,
            ' . $totalSql . ' AS total_kobo,
            ' . $paidSql . ' AS paid_kobo
         FROM fashion_orders o
         INNER JOIN fashion_customers c ON c.id = o.customer_id AND c.tenant_id = :tenant_id
         LEFT JOIN fashion_users u ON u.id = o.assigned_user_id AND u.tenant_id = :tenant_id'
            . $where . '
         ORDER BY o.urgent DESC, o.due_date ASC, o.order_number ASC
         LIMIT 800',
        $params
    );
    $byStage = [];
    $summary = [
        'production' => 0,
        'overdue' => 0,
        'week' => 0,
        'ready' => 0,
    ];
    // Summary is shop-scoped (ignore list filter) so strip cards stay honest.
    $summaryRows = db_query(
        'SELECT
            COALESCE(SUM(CASE WHEN ' . $unfinished . ' THEN 1 ELSE 0 END), 0) AS production_count,
            COALESCE(SUM(CASE WHEN o.due_date < :sum_today AND ' . $unfinished . ' THEN 1 ELSE 0 END), 0) AS overdue_count,
            COALESCE(SUM(CASE WHEN o.due_date >= :sum_week_start AND o.due_date <= :sum_week_end AND ' . $unfinished . ' THEN 1 ELSE 0 END), 0) AS week_count,
            COALESCE(SUM(CASE WHEN ' . $stageRole . ' = \'ready\' THEN 1 ELSE 0 END), 0) AS ready_count
         FROM fashion_orders o
         WHERE o.tenant_id = :tenant_id',
        [
            'sum_today' => $today,
            'sum_week_start' => $weekStart,
            'sum_week_end' => $weekEnd,
        ]
    );
    $summary = [
        'production' => (int) ($summaryRows[0]['production_count'] ?? 0),
        'overdue' => (int) ($summaryRows[0]['overdue_count'] ?? 0),
        'week' => (int) ($summaryRows[0]['week_count'] ?? 0),
        'ready' => (int) ($summaryRows[0]['ready_count'] ?? 0),
    ];
    foreach ($orders as $order) {
        $stageId = is_string($order['stage_id']) && $order['stage_id'] !== '' ? (string) $order['stage_id'] : $firstId;
        if ($stageId === null) {
            continue;
        }
        $role = is_string($order['stage_role'] ?? null) ? (string) $order['stage_role'] : null;
        $finished = $role === 'finished';
        $customerName = (string) $order['customer_name'];
        $itemCount = (int) ($order['item_count'] ?? 0);
        $itemNames = trim((string) ($order['item_names'] ?? ''));
        $itemsLine = t('board_items_line', ['n' => (string) $itemCount]);
        if ($itemNames !== '') {
            $itemsLine .= ' · ' . $itemNames;
        }
        $payment = order_payment_badge((int) $order['total_kobo'], (int) $order['paid_kobo']);
        $assignedName = is_string($order['assigned_name']) && $order['assigned_name'] !== ''
            ? (string) $order['assigned_name']
            : null;
        $byStage[$stageId][] = [
            'id' => (string) $order['id'],
            'order_number' => (string) $order['order_number'],
            'customer_name' => $customerName,
            'customer_display_name' => order_display_customer_name($customerName),
            'customer_phone' => (string) ($order['customer_phone'] ?? ''),
            'due_date' => (string) $order['due_date'],
            'due_tone' => stage_board_due_tone((string) $order['due_date'], $finished),
            'urgent' => (int) ($order['urgent'] ?? 0) === 1,
            'assigned_name' => $assignedName,
            'assigned_initials' => stage_board_initials($assignedName),
            'item_count' => $itemCount,
            'items_line' => $itemsLine,
            'payment_key' => $payment['key'],
            'payment_tone' => $payment['tone'],
            'can_advance' => !$finished,
        ];
    }
    $groups = [];
    $activeOrdered = [];
    foreach ($stages as $i => $stage) {
        if ((int) $stage['active'] === 1) {
            $activeOrdered[] = (string) $stage['id'];
        }
    }
    $nextByStage = [];
    foreach ($activeOrdered as $i => $id) {
        $nextByStage[$id] = $activeOrdered[$i + 1] ?? null;
    }
    $totalCards = 0;
    foreach ($stages as $i => $stage) {
        $id = (string) $stage['id'];
        $cards = $byStage[$id] ?? [];
        if ((int) $stage['active'] !== 1 && $cards === []) {
            continue;
        }
        // Urgent first, then due date (SQL already ordered; keep stable).
        usort($cards, static function (array $a, array $b): int {
            if ($a['urgent'] !== $b['urgent']) {
                return $a['urgent'] ? -1 : 1;
            }
            if ($a['due_date'] !== $b['due_date']) {
                return $a['due_date'] <=> $b['due_date'];
            }
            return $a['order_number'] <=> $b['order_number'];
        });
        $totalCards += count($cards);
        $code = (string) ($stage['code'] ?? '');
        $groups[] = [
            'id' => $id,
            'code' => $code,
            'name' => (string) $stage['name'],
            'tone' => stage_board_tone($code, $i),
            'role' => (string) ($stage['role'] ?? 'open'),
            'active' => (int) $stage['active'] === 1,
            'next_stage_id' => $nextByStage[$id] ?? null,
            'count' => count($cards),
            'orders' => $cards,
        ];
    }
    return [
        'orders_off' => false,
        'groups' => $groups,
        'summary' => $summary,
        'filter' => $filter,
        'q' => $q,
        'total_cards' => $totalCards,
    ];
}

function stage_board_path(string $filter, string $q): string
{
    $filter = stage_board_normalize_filter($filter);
    $path = '/board';
    $params = [];
    if ($filter !== 'all') {
        $params['filter'] = $filter;
    }
    $q = trim($q);
    if ($q !== '') {
        $params['q'] = $q;
    }
    if ($params !== []) {
        $path .= '?' . http_build_query($params);
    }
    return $path;
}

function stage_rename(string $id, string $name): array
{
    require_module('fashion.production');
    $name = trim($name);
    if (!is_uuid($id) || $name === '' || strlen($name) > 80) {
        return ['error' => 'required'];
    }
    db_update('fashion_stage_definitions', ['name' => $name], ['id' => $id]);
    return [];
}

function stage_reorder(string $id, string $direction): void
{
    require_module('fashion.production');
    if (!is_uuid($id) || !in_array($direction, ['up', 'down'], true)) {
        return;
    }
    $stages = stage_definitions();
    $index = null;
    foreach ($stages as $i => $stage) {
        if ((string) $stage['id'] === $id) {
            $index = $i;
            break;
        }
    }
    if ($index === null) {
        return;
    }
    $neighbor = $direction === 'up' ? $index - 1 : $index + 1;
    if (!isset($stages[$neighbor])) {
        return;
    }
    $here = $stages[$index];
    $there = $stages[$neighbor];
    db_begin();
    try {
        db_update('fashion_stage_definitions', ['sort_order' => (int) $there['sort_order']], ['id' => (string) $here['id']]);
        db_update('fashion_stage_definitions', ['sort_order' => (int) $here['sort_order']], ['id' => (string) $there['id']]);
        db_commit();
    } catch (Throwable $e) {
        db_rollback();
        throw $e;
    }
}

function stage_add(string $name): array
{
    require_module('fashion.production');
    $name = trim($name);
    if ($name === '' || strlen($name) > 80) {
        return ['error' => 'required'];
    }
    $sort = 1;
    foreach (stage_definitions() as $stage) {
        $sort = max($sort, (int) $stage['sort_order'] + 1);
    }
    db_insert('fashion_stage_definitions', [
        'code' => stage_code($name),
        'name' => $name,
        'sort_order' => $sort,
        'active' => 1,
        'role' => 'open',
    ]);
    return [];
}

function stage_role_names(): array
{
    return ['open', 'ready', 'finished'];
}

function stage_role_problem(array $roles): ?string
{
    $ready = 0;
    $finished = 0;
    foreach ($roles as $role) {
        if ($role === 'ready') {
            $ready++;
        } elseif ($role === 'finished') {
            $finished++;
        }
    }
    if ($finished !== 1) {
        return 'stage_need_finished';
    }
    if ($ready > 1) {
        return 'stage_one_ready';
    }
    return null;
}

function stage_save_roles(array $posted): array
{
    require_module('fashion.production');
    $stages = stage_definitions();
    $planned = [];
    foreach ($stages as $stage) {
        $id = (string) $stage['id'];
        $role = (string) $stage['role'];
        if (array_key_exists($id, $posted)) {
            $role = (string) $posted[$id];
        }
        if (!in_array($role, stage_role_names(), true)) {
            return ['error' => 'required'];
        }
        $planned[$id] = $role;
    }
    $problem = stage_role_problem(array_values($planned));
    if ($problem !== null) {
        return ['error' => $problem];
    }
    db_begin();
    try {
        foreach ($planned as $id => $role) {
            db_update('fashion_stage_definitions', ['role' => $role], ['id' => $id]);
        }
        db_commit();
    } catch (Throwable $e) {
        db_rollback();
        throw $e;
    }
    return [];
}

function stage_set_active(string $id, bool $active): void
{
    require_module('fashion.production');
    if (!is_uuid($id)) {
        return;
    }
    db_update('fashion_stage_definitions', ['active' => $active ? 1 : 0], ['id' => $id]);
}

function stage_code(string $name): string
{
    $code = strtolower($name);
    $code = preg_replace('/[^a-z0-9]+/', '_', $code);
    $code = is_string($code) ? trim($code, '_') : '';
    if ($code === '') {
        $code = 'stage';
    }
    $code = substr($code, 0, 30);
    $base = $code;
    $number = 2;
    while (db_select('fashion_stage_definitions', ['code' => $code]) !== []) {
        $code = substr($base, 0, 28) . '_' . (string) $number;
        $number++;
        if ($number > 50) {
            $code = 'stage_' . substr(uuid(), 0, 8);
            break;
        }
    }
    return $code;
}
