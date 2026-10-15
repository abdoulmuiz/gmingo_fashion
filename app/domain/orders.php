<?php

declare(strict_types=1);

require_once __DIR__ . '/modules.php';
require_once __DIR__ . '/customers.php';

function order_tabs(): array
{
    return ['all', 'active', 'overdue', 'due_today', 'urgent', 'ready', 'delivered', 'unpaid'];
}

function order_create(array $input): array
{
    require_module('fashion.orders');
    $customerId = (string) ($input['customer_id'] ?? '');
    $dueDate = (string) ($input['due_date'] ?? '');
    $urgent = in_array((string) ($input['urgent'] ?? ''), ['1', 'on', 'yes', 'true'], true) ? 1 : 0;
    $errors = [];
    if (!is_uuid($customerId) || db_select('fashion_customers', ['id' => $customerId]) === []) {
        $errors['customer_id'] = 'pick_customer';
    }
    $parsedDate = DateTime::createFromFormat('!Y-m-d', $dueDate);
    if ($parsedDate === false || $parsedDate->format('Y-m-d') !== $dueDate) {
        $errors['due_date'] = 'invalid_date';
    }
    $items = [];
    $rawItems = $input['items'] ?? [];
    if (!is_array($rawItems)) {
        $rawItems = [];
    }
    foreach ($rawItems as $row) {
        if (!is_array($row)) {
            continue;
        }
        $garmentId = trim((string) ($row['garment_type_id'] ?? ''));
        $quantity = trim((string) ($row['quantity'] ?? ''));
        $price = trim((string) ($row['price'] ?? ''));
        $fabric = trim((string) ($row['fabric'] ?? ''));
        $notes = trim((string) ($row['notes'] ?? ''));
        if ($garmentId === '' && $quantity === '' && $price === '' && $fabric === '' && $notes === '') {
            continue;
        }
        if (!is_uuid($garmentId) || db_select('fashion_garment_types', ['id' => $garmentId, 'active' => 1]) === []) {
            $errors['items'] = 'pick_garment';
            continue;
        }
        if (!preg_match('/^[1-9]\d{0,3}$/', $quantity)) {
            $errors['items'] = 'invalid_quantity';
            continue;
        }
        $priceKobo = kobo_from_input($price);
        if ($priceKobo === null) {
            $errors['items'] = 'invalid_money';
            continue;
        }
        if (strlen($fabric) > 190 || strlen($notes) > 500) {
            $errors['items'] = 'required';
            continue;
        }
        $items[] = [
            'garment_type_id' => $garmentId,
            'quantity' => (int) $quantity,
            'price_kobo' => $priceKobo,
            'fabric' => $fabric,
            'notes' => $notes,
        ];
    }
    if ($items === [] && !isset($errors['items'])) {
        $errors['items'] = 'pick_garment';
    }
    if ($errors !== []) {
        return ['errors' => $errors];
    }

    $ownTransaction = !db()->inTransaction();
    if ($ownTransaction) {
        db_begin();
    }
    try {
        $orderNumber = order_allocate_number();
        $orderId = db_insert('fashion_orders', [
            'customer_id' => $customerId,
            'order_number' => $orderNumber,
            'due_date' => $dueDate,
            'urgent' => $urgent,
            'group_id' => null,
            'assigned_user_id' => null,
            'customer_supplied_fabric' => 0,
            'yards_received' => null,
            'yards_used' => null,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
        foreach ($items as $item) {
            db_insert('fashion_order_items', [
                'order_id' => $orderId,
                'garment_type_id' => $item['garment_type_id'],
                'quantity' => $item['quantity'],
                'price_kobo' => $item['price_kobo'],
                'fabric' => $item['fabric'],
                'notes' => $item['notes'],
                'product_id' => null,
            ]);
        }
        if ($ownTransaction) {
            db_commit();
        }
    } catch (Throwable $e) {
        if ($ownTransaction) {
            db_rollback();
        }
        throw $e;
    }
    return ['id' => $orderId, 'order_number' => $orderNumber];
}

function order_allocate_number(): string
{
    $rows = db_query(
        'SELECT id, prefix, last_number FROM fashion_order_counters WHERE tenant_id = :tenant_id FOR UPDATE'
    );
    if ($rows === []) {
        throw new RuntimeException('Missing order counter.');
    }
    $prefix = strtoupper(trim((string) $rows[0]['prefix']));
    if (!preg_match('/^[A-Z0-9]{1,10}$/', $prefix)) {
        throw new RuntimeException('Invalid order prefix.');
    }
    $next = (int) $rows[0]['last_number'] + 1;
    db_execute(
        'UPDATE fashion_order_counters SET last_number = :next WHERE tenant_id = :tenant_id AND id = :id',
        ['next' => $next, 'id' => (string) $rows[0]['id']]
    );
    return $prefix . (string) $next;
}

function list_page_size(): int
{
    return 25;
}

function list_page(mixed $page): int
{
    if (is_int($page)) {
        return $page > 0 ? $page : 1;
    }
    if (is_string($page) && preg_match('/^[1-9][0-9]{0,6}$/', $page) === 1) {
        return (int) $page;
    }
    return 1;
}

function order_list(string $filter, string $search, int $page = 1): array
{
    require_module('fashion.orders');
    if (!in_array($filter, order_tabs(), true)) {
        $filter = 'all';
    }
    return order_rows($filter, trim($search), null, list_page($page));
}

function order_count(string $filter, string $search): int
{
    require_module('fashion.orders');
    if (!in_array($filter, order_tabs(), true)) {
        $filter = 'all';
    }
    [$where, $params] = order_conditions($filter, trim($search));
    $rows = db_query(
        'SELECT COUNT(*) AS total
         FROM fashion_orders o
         INNER JOIN fashion_customers c ON c.id = o.customer_id AND c.tenant_id = :tenant_id
         WHERE o.tenant_id = :tenant_id' . $where,
        $params
    );
    return (int) ($rows[0]['total'] ?? 0);
}

function order_page_path(string $filter, string $search, int $page): string
{
    if (!in_array($filter, order_tabs(), true)) {
        $filter = 'all';
    }
    $path = '/orders?tab=' . rawurlencode($filter);
    $search = trim($search);
    if ($search !== '') {
        $path .= '&q=' . rawurlencode($search);
    }
    if ($page > 1) {
        $path .= '&page=' . (string) $page;
    }
    return $path;
}

function order_find(string $id): ?array
{
    require_module('fashion.orders');
    if (!is_uuid($id)) {
        return null;
    }
    $rows = order_rows('all', '', $id);
    if ($rows === []) {
        return null;
    }
    $order = $rows[0];
    $order['items'] = db_query(
        'SELECT i.id, i.quantity, i.price_kobo, i.fabric, i.notes, i.product_id, g.name AS garment_name
         FROM fashion_order_items i
         INNER JOIN fashion_garment_types g ON g.id = i.garment_type_id AND g.tenant_id = :tenant_id
         WHERE i.tenant_id = :tenant_id AND i.order_id = :order_id
         ORDER BY g.name ASC',
        ['order_id' => $id]
    );
    foreach ($order['items'] as $index => $item) {
        $order['items'][$index]['quantity'] = (int) $item['quantity'];
        $order['items'][$index]['price_kobo'] = (int) $item['price_kobo'];
        $order['items'][$index]['line_kobo'] = (int) $item['quantity'] * (int) $item['price_kobo'];
    }
    if ($order['stage_name'] === null) {
        $first = db_select('fashion_stage_definitions', ['active' => 1], ['order' => 'sort_order ASC', 'limit' => 1]);
        if ($first !== []) {
            $order['stage_name'] = (string) $first[0]['name'];
        }
    }
    return $order;
}

function order_stage_sql(string $column): string
{
    if (!in_array($column, ['code', 'name', 'role'], true)) {
        throw new RuntimeException('Invalid stage column.');
    }
    return '(SELECT sd.' . $column . ' FROM fashion_stage_events se INNER JOIN fashion_stage_definitions sd ON sd.id = se.stage_id AND sd.tenant_id = :tenant_id WHERE se.tenant_id = :tenant_id AND se.order_id = o.id ORDER BY se.seq DESC LIMIT 1)';
}

function order_money_sql(): array
{
    $totalSql = '(SELECT COALESCE(SUM(i.quantity * i.price_kobo), 0) FROM fashion_order_items i WHERE i.order_id = o.id AND i.tenant_id = :tenant_id)';
    $paidSql = '(SELECT COALESCE(SUM(p.amount_kobo), 0) FROM fashion_payments p WHERE p.order_id = o.id AND p.tenant_id = :tenant_id)';
    return [$totalSql, $paidSql];
}

function order_conditions(string $filter, string $search): array
{
    $stageRole = order_stage_sql('role');
    [$totalSql, $paidSql] = order_money_sql();
    $sql = order_filter_sql($filter, $stageRole, $totalSql, $paidSql);
    $params = [];
    if ($filter === 'overdue' || $filter === 'due_today') {
        $params['today'] = app_today();
    }
    if ($search !== '') {
        $like = like_contains($search);
        $sql .= ' AND (o.order_number LIKE :q_number ESCAPE \'\\\\\' OR c.name LIKE :q_name ESCAPE \'\\\\\' OR c.phone LIKE :q_phone ESCAPE \'\\\\\')';
        $params['q_number'] = $like;
        $params['q_name'] = $like;
        $params['q_phone'] = $like;
    }
    return [$sql, $params];
}

function order_rows(string $filter, string $search, ?string $id, int $page = 1): array
{
    $stageCode = order_stage_sql('code');
    $stageName = order_stage_sql('name');
    $stageRole = order_stage_sql('role');
    [$totalSql, $paidSql] = order_money_sql();
    $sql = 'SELECT o.id, o.order_number, o.customer_id, o.due_date, o.urgent, o.created_at,
            c.name AS customer_name, ' . $totalSql . ' AS total_kobo, ' . $paidSql . ' AS paid_kobo, '
            . $stageCode . ' AS stage_code, ' . $stageName . ' AS stage_name, ' . $stageRole . ' AS stage_role
         FROM fashion_orders o
         INNER JOIN fashion_customers c ON c.id = o.customer_id AND c.tenant_id = :tenant_id
         WHERE o.tenant_id = :tenant_id';
    $params = [];
    if ($id !== null) {
        $sql .= ' AND o.id = :order_id';
        $params['order_id'] = $id;
    } else {
        [$where, $params] = order_conditions($filter, $search);
        $sql .= $where;
        $page = list_page($page);
        $sql .= ' ORDER BY o.due_date ASC, o.order_number ASC LIMIT :row_limit OFFSET :row_offset';
        $params['row_limit'] = list_page_size();
        $params['row_offset'] = ($page - 1) * list_page_size();
    }
    }
    $rows = [];
    foreach (db_query($sql, $params) as $row) {
        $rows[] = order_present($row);
    }
    return $rows;
}

function order_filter_sql(string $filter, string $stageRole, string $totalSql, string $paidSql): string
{
    if ($filter === 'active') {
        return ' AND (' . $stageRole . ' IS NULL OR ' . $stageRole . ' NOT IN (\'ready\', \'finished\'))';
    }
    if ($filter === 'overdue') {
        return ' AND o.due_date < :today AND (' . $stageRole . ' IS NULL OR ' . $stageRole . ' <> \'finished\')';
    }
    if ($filter === 'due_today') {
        return ' AND o.due_date = :today';
    }
    if ($filter === 'urgent') {
        return ' AND o.urgent = 1';
    }
    if ($filter === 'ready') {
        return ' AND ' . $stageRole . ' = \'ready\'';
    }
    if ($filter === 'delivered') {
        return ' AND ' . $stageRole . ' = \'finished\'';
    }
    if ($filter === 'unpaid') {
        return ' AND ' . $totalSql . ' > ' . $paidSql;
    }
    return '';
}

function order_present(array $row): array
{
    $total = (int) $row['total_kobo'];
    $paid = (int) $row['paid_kobo'];
    $code = $row['stage_code'];
    $name = $row['stage_name'];
    $role = $row['stage_role'];
    return [
        'id' => (string) $row['id'],
        'order_number' => (string) $row['order_number'],
        'customer_id' => (string) $row['customer_id'],
        'customer_name' => (string) $row['customer_name'],
        'due_date' => (string) $row['due_date'],
        'urgent' => (int) $row['urgent'] === 1,
        'created_at' => (string) $row['created_at'],
        'total_kobo' => $total,
        'paid_kobo' => $paid,
        'balance_kobo' => $total - $paid,
        'paid' => $paid >= $total,
        'stage_code' => is_string($code) && $code !== '' ? $code : null,
        'stage_name' => is_string($name) && $name !== '' ? $name : null,
        'stage_role' => is_string($role) && in_array($role, ['open', 'ready', 'finished'], true) ? $role : null,
    ];
}

function order_garments(): array
{
    require_module('fashion.orders');
    return db_select('fashion_garment_types', ['active' => 1], ['order' => 'sort_order ASC', 'limit' => 100]);
}
