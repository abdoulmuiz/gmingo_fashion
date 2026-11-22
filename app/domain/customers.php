<?php

declare(strict_types=1);

require_once __DIR__ . '/modules.php';
require_once __DIR__ . '/orders.php';

function customer_create(array $input): array
{
    require_module('fashion.orders');
    $name = trim((string) ($input['name'] ?? ''));
    $phone = phone_normalize((string) ($input['phone'] ?? ''));
    $gender = (string) ($input['gender'] ?? '');
    $type = (string) ($input['customer_type'] ?? 'regular');
    $errors = [];
    if ($name === '' || strlen($name) > 190) {
        $errors['name'] = 'required';
    }
    if ($phone === null) {
        $errors['phone'] = 'invalid_phone';
    }
    if (!in_array($gender, ['male', 'female'], true)) {
        $errors['gender'] = 'required';
    }
    if (!in_array($type, ['vip', 'regular'], true)) {
        $errors['customer_type'] = 'required';
    }
    $lang = strtolower(trim((string) ($input['lang'] ?? '')));
    if ($lang !== '' && !in_array($lang, ['en', 'ha'], true)) {
        $errors['lang'] = 'required';
    }
    if ($errors !== []) {
        return ['errors' => $errors];
    }
    $id = db_insert('fashion_customers', [
        'name' => $name,
        'phone' => $phone,
        'gender' => $gender,
        'customer_type' => $type,
        'messages_opt_in' => 0,
        'lang' => $lang === '' ? null : $lang,
        'created_at' => date('Y-m-d H:i:s'),
    ]);
    return ['id' => $id];
}

function customer_search(string $query): array
{
    require_module('fashion.orders');
    $query = trim($query);
    if ($query === '') {
        return db_select('fashion_customers', [], ['order' => 'name ASC', 'limit' => 200]);
    }
    return db_query(
        'SELECT * FROM fashion_customers WHERE tenant_id = :tenant_id AND (name LIKE :q ESCAPE \'\\\\\' OR phone LIKE :phone ESCAPE \'\\\\\') ORDER BY name ASC LIMIT 200',
        ['q' => like_contains($query), 'phone' => like_contains($query)]
    );
}

/** @return list<string> */
function customer_list_tabs(): array
{
    return ['all', 'vip', 'regular', 'balance', 'new_month', 'inactive'];
}

/** @return list<string> */
function customer_list_sorts(): array
{
    return ['name', 'newest', 'orders', 'spent', 'balance'];
}

function customer_list_page_size(): int
{
    return 20;
}

function customer_normalize_tab(string $tab): string
{
    return in_array($tab, customer_list_tabs(), true) ? $tab : 'all';
}

function customer_normalize_sort(string $sort): string
{
    return in_array($sort, customer_list_sorts(), true) ? $sort : 'name';
}

function customer_initials(string $name): string
{
    $name = trim(order_display_customer_name($name));
    if ($name === '') {
        return '?';
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
    return $letters !== '' ? $letters : '?';
}

/**
 * Relative last-order label; full date stays in title tooltip.
 */
function customer_last_order_label(?string $at): string
{
    if ($at === null || trim($at) === '') {
        return t('customers_no_orders_yet');
    }
    $dt = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $at)
        ?: DateTimeImmutable::createFromFormat('!Y-m-d', substr($at, 0, 10));
    if ($dt === false) {
        return $at;
    }
    $now = new DateTimeImmutable('now');
    $secs = $now->getTimestamp() - $dt->getTimestamp();
    if ($secs < 0) {
        return $dt->format('j M Y');
    }
    if ($secs < 60) {
        return t('dash_just_now');
    }
    if ($secs < 3600) {
        return t('dash_mins_ago', ['n' => (string) (int) floor($secs / 60)]);
    }
    if ($secs < 86400) {
        return t('dash_hours_ago', ['n' => (string) (int) floor($secs / 3600)]);
    }
    if ($secs < 86400 * 7) {
        return t('dash_days_ago', ['n' => (string) (int) floor($secs / 86400)]);
    }
    return $dt->format('j M Y');
}

/**
 * Aggregates subquery: one grouped join for orders/spent/balance/last order.
 */
function customer_stats_join_sql(): string
{
    [$totalSql, $paidSql] = order_money_sql();
    // order_money_sql references alias o — reuse inside the grouped subquery.
    return 'LEFT JOIN (
        SELECT o.customer_id AS customer_id,
            COUNT(*) AS order_count,
            COALESCE(SUM(' . $paidSql . '), 0) AS spent_kobo,
            COALESCE(SUM(CASE WHEN ' . $totalSql . ' > ' . $paidSql . ' THEN (' . $totalSql . ' - ' . $paidSql . ') ELSE 0 END), 0) AS balance_kobo,
            MAX(o.created_at) AS last_order_at
        FROM fashion_orders o
        WHERE o.tenant_id = :tenant_id
        GROUP BY o.customer_id
    ) agg ON agg.customer_id = c.id';
}

/**
 * @param array{tab?:string,q?:string} $options
 * @return array{0:string,1:array<string,mixed>}
 */
function customer_list_conditions(array $options = []): array
{
    $tab = customer_normalize_tab((string) ($options['tab'] ?? 'all'));
    $q = trim((string) ($options['q'] ?? ''));
    $sql = '';
    $params = [];
    $monthStart = (new DateTimeImmutable('first day of this month'))->format('Y-m-d 00:00:00');
    $inactiveBefore = (new DateTimeImmutable('today'))->modify('-90 days')->format('Y-m-d 00:00:00');
    if ($tab === 'vip') {
        $sql .= ' AND c.customer_type = \'vip\'';
    } elseif ($tab === 'regular') {
        $sql .= ' AND c.customer_type = \'regular\'';
    } elseif ($tab === 'balance') {
        $sql .= ' AND COALESCE(agg.balance_kobo, 0) > 0';
    } elseif ($tab === 'new_month') {
        $sql .= ' AND c.created_at >= :month_start';
        $params['month_start'] = $monthStart;
    } elseif ($tab === 'inactive') {
        $sql .= ' AND (agg.last_order_at IS NULL OR agg.last_order_at < :inactive_before)';
        $params['inactive_before'] = $inactiveBefore;
    }
    if ($q !== '') {
        $like = like_contains($q);
        $sql .= ' AND (c.name LIKE :q_name ESCAPE \'\\\\\' OR c.phone LIKE :q_phone ESCAPE \'\\\\\')';
        $params['q_name'] = $like;
        $params['q_phone'] = $like;
    }
    return [$sql, $params];
}

function customer_list_order_sql(string $sort): string
{
    $sort = customer_normalize_sort($sort);
    if ($sort === 'newest') {
        return ' ORDER BY c.created_at DESC, c.name ASC';
    }
    if ($sort === 'orders') {
        return ' ORDER BY COALESCE(agg.order_count, 0) DESC, c.name ASC';
    }
    if ($sort === 'spent') {
        return ' ORDER BY COALESCE(agg.spent_kobo, 0) DESC, c.name ASC';
    }
    if ($sort === 'balance') {
        return ' ORDER BY COALESCE(agg.balance_kobo, 0) DESC, c.name ASC';
    }
    return ' ORDER BY c.name ASC';
}

/**
 * @param array{tab?:string,q?:string,sort?:string} $options
 */
function customer_list(array $options = [], int $page = 1): array
{
    require_module('fashion.orders');
    $sort = customer_normalize_sort((string) ($options['sort'] ?? 'name'));
    [$where, $params] = customer_list_conditions($options);
    $page = list_page($page);
    $size = customer_list_page_size();
    $sql = 'SELECT c.id, c.name, c.phone, c.customer_type, c.created_at,
            COALESCE(agg.order_count, 0) AS order_count,
            COALESCE(agg.spent_kobo, 0) AS spent_kobo,
            COALESCE(agg.balance_kobo, 0) AS balance_kobo,
            agg.last_order_at AS last_order_at
         FROM fashion_customers c
         ' . customer_stats_join_sql() . '
         WHERE c.tenant_id = :tenant_id' . $where
        . customer_list_order_sql($sort)
        . ' LIMIT :row_limit OFFSET :row_offset';
    $params['row_limit'] = $size;
    $params['row_offset'] = ($page - 1) * $size;
    $rows = [];
    foreach (db_query($sql, $params) as $row) {
        $rows[] = customer_list_present($row);
    }
    return $rows;
}

/**
 * @param array{tab?:string,q?:string} $options
 */
function customer_list_count(array $options = []): int
{
    require_module('fashion.orders');
    [$where, $params] = customer_list_conditions($options);
    $rows = db_query(
        'SELECT COUNT(*) AS total
         FROM fashion_customers c
         ' . customer_stats_join_sql() . '
         WHERE c.tenant_id = :tenant_id' . $where,
        $params
    );
    return (int) ($rows[0]['total'] ?? 0);
}

/**
 * @return array<string,int>
 */
function customer_tab_counts(string $q = ''): array
{
    require_module('fashion.orders');
    $counts = [];
    foreach (customer_list_tabs() as $tab) {
        $counts[$tab] = customer_list_count(['tab' => $tab, 'q' => $q]);
    }
    return $counts;
}

/**
 * Shop-scoped summary cards (ignore list filter).
 *
 * @return array{total:int,new_month:int,vip:int,outstanding_kobo:int}
 */
function customer_summary_stats(): array
{
    require_module('fashion.orders');
    $monthStart = (new DateTimeImmutable('first day of this month'))->format('Y-m-d 00:00:00');
    $rows = db_query(
        'SELECT
            COUNT(*) AS total_count,
            COALESCE(SUM(CASE WHEN c.created_at >= :month_start THEN 1 ELSE 0 END), 0) AS new_month_count,
            COALESCE(SUM(CASE WHEN c.customer_type = \'vip\' THEN 1 ELSE 0 END), 0) AS vip_count,
            COALESCE(SUM(COALESCE(agg.balance_kobo, 0)), 0) AS outstanding_kobo
         FROM fashion_customers c
         ' . customer_stats_join_sql() . '
         WHERE c.tenant_id = :tenant_id',
        ['month_start' => $monthStart]
    );
    $row = $rows[0] ?? [];
    return [
        'total' => (int) ($row['total_count'] ?? 0),
        'new_month' => (int) ($row['new_month_count'] ?? 0),
        'vip' => (int) ($row['vip_count'] ?? 0),
        'outstanding_kobo' => (int) ($row['outstanding_kobo'] ?? 0),
    ];
}

/**
 * @param array{tab?:string,q?:string,sort?:string} $options
 */
function customer_list_path(string $tab, string $q, string $sort, int $page): string
{
    $tab = customer_normalize_tab($tab);
    $sort = customer_normalize_sort($sort);
    $params = [];
    if ($tab !== 'all') {
        $params['tab'] = $tab;
    }
    $q = trim($q);
    if ($q !== '') {
        $params['q'] = $q;
    }
    if ($sort !== 'name') {
        $params['sort'] = $sort;
    }
    if ($page > 1) {
        $params['page'] = (string) $page;
    }
    $path = '/customers';
    if ($params !== []) {
        $path .= '?' . http_build_query($params);
    }
    return $path;
}

function customer_list_present(array $row): array
{
    $name = (string) $row['name'];
    $phone = (string) $row['phone'];
    $lastAt = $row['last_order_at'] ?? null;
    $lastAt = is_string($lastAt) && $lastAt !== '' ? $lastAt : null;
    $wa = null;
    $tel = null;
    if (phone_normalize($phone) !== null) {
        $tel = tel_href($phone);
        $wa = whatsapp_href($phone);
    }
    return [
        'id' => (string) $row['id'],
        'name' => $name,
        'display_name' => order_display_customer_name($name),
        'initials' => customer_initials($name),
        'phone' => $phone,
        'customer_type' => (string) $row['customer_type'] === 'vip' ? 'vip' : 'regular',
        'created_at' => (string) ($row['created_at'] ?? ''),
        'order_count' => (int) ($row['order_count'] ?? 0),
        'spent_kobo' => (int) ($row['spent_kobo'] ?? 0),
        'balance_kobo' => (int) ($row['balance_kobo'] ?? 0),
        'last_order_at' => $lastAt,
        'last_order_label' => customer_last_order_label($lastAt),
        'last_order_title' => $lastAt !== null ? $lastAt : '',
        'tel_href' => $tel,
        'whatsapp_href' => $wa,
    ];
}

function customer_orders(string $customerId): array
{
    require_module('fashion.orders');
    $rows = db_query(
        'SELECT o.id, o.order_number, o.due_date, o.created_at,
            (SELECT COALESCE(SUM(i.quantity * i.price_kobo), 0) FROM fashion_order_items i WHERE i.order_id = o.id AND i.tenant_id = :tenant_id) AS total_kobo,
            (SELECT COALESCE(SUM(p.amount_kobo), 0) FROM fashion_payments p WHERE p.order_id = o.id AND p.tenant_id = :tenant_id) AS paid_kobo
         FROM fashion_orders o
         WHERE o.tenant_id = :tenant_id AND o.customer_id = :customer_id
         ORDER BY o.created_at DESC',
        ['customer_id' => $customerId]
    );
    $orders = [];
    foreach ($rows as $row) {
        $total = (int) $row['total_kobo'];
        $paid = (int) $row['paid_kobo'];
        $balance = $total - $paid;
        $orders[] = [
            'id' => $row['id'],
            'order_number' => $row['order_number'],
            'due_date' => $row['due_date'],
            'total_kobo' => $total,
            'paid_kobo' => $paid,
            'balance_kobo' => $balance,
        ];
    }
    return $orders;
}

function customer_profile(string $id): ?array
{
    require_module('fashion.orders');
    if (!is_uuid($id)) {
        return null;
    }
    $rows = db_select('fashion_customers', ['id' => $id]);
    if ($rows === []) {
        return null;
    }
    $customer = $rows[0];
    $orders = customer_orders($id);
    $spent = 0;
    $owed = 0;
    foreach ($orders as $order) {
        $spent += $order['paid_kobo'];
        if ($order['balance_kobo'] > 0) {
            $owed += $order['balance_kobo'];
        }
    }
    $customer['orders'] = $orders;
    $customer['order_count'] = count($orders);
    $customer['spent_kobo'] = $spent;
    $customer['owed_kobo'] = $owed;
    return $customer;
}
