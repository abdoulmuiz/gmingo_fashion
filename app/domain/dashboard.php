<?php

declare(strict_types=1);

require_once __DIR__ . '/orders.php';
require_once __DIR__ . '/payments.php';
require_once __DIR__ . '/stock.php';
require_once __DIR__ . '/subscriptions.php';

function dashboard_hello(int $hour): string
{
    if ($hour < 12) {
        return t('good_morning');
    }
    if ($hour < 17) {
        return t('good_afternoon');
    }
    return t('good_evening');
}

function dashboard_greeting(?int $hour = null): string
{
    require_login();
    $name = dashboard_user_name();
    if ($hour === null) {
        $hour = (int) date('G');
    }
    return t('greeting', ['hello' => dashboard_hello($hour), 'name' => $name]);
}

function dashboard_user_name(): string
{
    require_login();
    $userId = current_user_id();
    if ($userId === null) {
        return '';
    }
    $rows = db_select('fashion_users', ['id' => $userId], ['limit' => 1]);
    if ($rows === []) {
        return '';
    }
    return (string) $rows[0]['name'];
}

function dashboard_user_role_label(): string
{
    require_once __DIR__ . '/staff.php';
    $role = staff_current_role();
    if ($role === 'owner') {
        return t('dash_role_owner');
    }
    if ($role === 'admin') {
        return t('dash_role_admin');
    }
    return t('dash_role_staff');
}

function dashboard_week(string $today): array
{
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $today);
    if ($date === false) {
        $date = new DateTimeImmutable('today');
    }
    $start = $date->modify('-' . ((int) $date->format('N') - 1) . ' days');
    return [$start->format('Y-m-d'), $start->modify('+6 days')->format('Y-m-d')];
}

function dashboard_month_bounds(string $today): array
{
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $today);
    if ($date === false) {
        $date = new DateTimeImmutable('today');
    }
    $start = $date->modify('first day of this month');
    $end = $date->modify('last day of this month');
    return [$start->format('Y-m-d'), $end->format('Y-m-d')];
}

/** @return list<string> */
function dashboard_range_presets(): array
{
    return ['today', 'last_7', 'this_month', 'last_30', 'last_90', 'last_365', 'custom'];
}

/**
 * Resolve and persist the dashboard date range in the session.
 *
 * @return array{preset:string,start:string,end:string,label:string,chart_key:string}
 */
function dashboard_range_resolve(?array $input = null): array
{
    require_login();
    $input = $input ?? $_GET;
    $today = DateTimeImmutable::createFromFormat('!Y-m-d', app_today()) ?: new DateTimeImmutable('today');
    $preset = strtolower(trim((string) ($input['range'] ?? '')));
    if ($preset === '7d') {
        $preset = 'last_7';
    }
    if ($preset === '30d') {
        $preset = 'last_30';
    }
    if ($preset === '3m') {
        $preset = 'last_90';
    }
    if ($preset === '1y') {
        $preset = 'last_365';
    }
    $stored = $_SESSION['gmingo_dashboard_range'] ?? null;
    if ($preset === '' && is_array($stored) && isset($stored['preset'])) {
        $preset = (string) $stored['preset'];
    }
    if (!in_array($preset, dashboard_range_presets(), true)) {
        $preset = 'last_30';
    }
    if ($preset === 'today') {
        $start = $today->format('Y-m-d');
        $end = $start;
    } elseif ($preset === 'last_7') {
        $end = $today->format('Y-m-d');
        $start = $today->modify('-6 days')->format('Y-m-d');
    } elseif ($preset === 'this_month') {
        [$start, $end] = dashboard_month_bounds($today->format('Y-m-d'));
    } elseif ($preset === 'last_90') {
        $end = $today->format('Y-m-d');
        $start = $today->modify('-89 days')->format('Y-m-d');
    } elseif ($preset === 'last_365') {
        $end = $today->format('Y-m-d');
        $start = $today->modify('-364 days')->format('Y-m-d');
    } elseif ($preset === 'custom') {
        $startRaw = trim((string) ($input['from'] ?? ($stored['start'] ?? '')));
        $endRaw = trim((string) ($input['to'] ?? ($stored['end'] ?? '')));
        $startDt = DateTimeImmutable::createFromFormat('!Y-m-d', $startRaw);
        $endDt = DateTimeImmutable::createFromFormat('!Y-m-d', $endRaw);
        if ($startDt === false || $endDt === false) {
            $preset = 'last_30';
            $end = $today->format('Y-m-d');
            $start = $today->modify('-29 days')->format('Y-m-d');
        } else {
            if ($startDt > $endDt) {
                [$startDt, $endDt] = [$endDt, $startDt];
            }
            $maxSpan = $startDt->modify('+366 days');
            if ($endDt > $maxSpan) {
                $endDt = $maxSpan;
            }
            $start = $startDt->format('Y-m-d');
            $end = $endDt->format('Y-m-d');
        }
    } else {
        $preset = 'last_30';
        $end = $today->format('Y-m-d');
        $start = $today->modify('-29 days')->format('Y-m-d');
    }
    $chartKey = match ($preset) {
        'last_7', 'today' => '7d',
        'last_90' => '3m',
        'last_365' => '1y',
        default => '30d',
    };
    $range = [
        'preset' => $preset,
        'start' => $start,
        'end' => $end,
        'label' => dashboard_period_label($start, $end),
        'chart_key' => $chartKey,
    ];
    $_SESSION['gmingo_dashboard_range'] = $range;
    return $range;
}

/** Previous period of the same length ending the day before $start. */
function dashboard_previous_range(string $start, string $end): array
{
    $s = DateTimeImmutable::createFromFormat('!Y-m-d', $start) ?: new DateTimeImmutable('today');
    $e = DateTimeImmutable::createFromFormat('!Y-m-d', $end) ?: $s;
    $days = (int) $s->diff($e)->days + 1;
    $prevEnd = $s->modify('-1 day');
    $prevStart = $prevEnd->modify('-' . ($days - 1) . ' days');
    return [$prevStart->format('Y-m-d'), $prevEnd->format('Y-m-d')];
}

function dashboard_pct_change(int $current, int $previous): ?float
{
    if ($previous === 0) {
        return null;
    }
    return (float) number_format((($current - $previous) / $previous) * 100, 1, '.', '');
}

function dashboard_home(?int $hour = null): array
{
    require_login();
    $range = dashboard_range_resolve();
    [$prevStart, $prevEnd] = dashboard_previous_range($range['start'], $range['end']);
    $cards = [];
    $attention = [];
    $ordersOn = module_enabled('fashion.orders');
    $counts = [
        'active' => 0,
        'week' => 0,
        'overdue' => 0,
        'ready' => 0,
        'attention' => [],
        'status' => [],
        'total' => 0,
    ];
    if ($ordersOn) {
        $counts = dashboard_order_counts();
        $cards[] = dashboard_card('card_active', (string) $counts['active']);
        $cards[] = dashboard_card('card_week', (string) $counts['week']);
        $cards[] = dashboard_card('card_overdue', (string) $counts['overdue']);
        $cards[] = dashboard_card('card_ready', (string) $counts['ready']);
        $attention = $counts['attention'];
    }
    if (module_enabled('fashion.finance')) {
        $cards[] = dashboard_card('card_money', money((int) payment_outstanding()['total_kobo']));
    }
    $lowStock = 0;
    $lowStockNew = 0;
    if (module_enabled('fashion.inventory')) {
        $lowStock = stock_low_count();
        $lowStockNew = dashboard_low_stock_new($range['start'], $range['end']);
        $cards[] = dashboard_card('card_low_stock', (string) $lowStock, '', '/stock');
    }

    $revenue = dashboard_revenue_between($range['start'], $range['end']);
    $revenuePrev = dashboard_revenue_between($prevStart, $prevEnd);
    $ordersInRange = $ordersOn ? dashboard_orders_created_count($range['start'], $range['end']) : 0;
    $ordersPrev = $ordersOn ? dashboard_orders_created_count($prevStart, $prevEnd) : 0;
    $customersInRange = $ordersOn ? dashboard_customers_created_count($range['start'], $range['end']) : 0;
    $customersPrev = $ordersOn ? dashboard_customers_created_count($prevStart, $prevEnd) : 0;
    // Active customers = customers with an order in the selected range (fallback to created).
    $activeCustomers = $ordersOn ? dashboard_customers_active_count($range['start'], $range['end']) : 0;
    if ($activeCustomers === 0) {
        $activeCustomers = $customersInRange;
    }
    $activeCustomersPrev = $ordersOn ? dashboard_customers_active_count($prevStart, $prevEnd) : 0;

    $kpis = dashboard_stat_cards([
        'revenue_kobo' => (int) $revenue['period_total_kobo'],
        'revenue_prev_kobo' => (int) $revenuePrev['period_total_kobo'],
        'revenue_points' => $revenue['points'],
        'orders' => $ordersInRange,
        'orders_prev' => $ordersPrev,
        'orders_points' => dashboard_orders_daily_series($range['start'], $range['end']),
        'customers' => $activeCustomers,
        'customers_prev' => $activeCustomersPrev,
        'customers_points' => dashboard_customers_daily_series($range['start'], $range['end']),
        'low_stock' => $lowStock,
        'low_stock_new' => $lowStockNew,
        'orders_on' => $ordersOn,
        'finance_on' => module_enabled('fashion.finance') || module_enabled('fashion.sales'),
        'inventory_on' => module_enabled('fashion.inventory'),
    ]);

    return [
        'greeting' => dashboard_greeting($hour),
        'hello' => dashboard_hello($hour ?? (int) date('G')),
        'user_name' => dashboard_user_name(),
        'user_role' => dashboard_user_role_label(),
        'period_label' => $range['label'],
        'range' => $range,
        'cards' => $cards,
        'kpis' => $kpis,
        'revenue' => array_merge($revenue, [
            'change_pct' => dashboard_pct_change(
                (int) $revenue['period_total_kobo'],
                (int) $revenuePrev['period_total_kobo']
            ),
        ]),
        'categories' => dashboard_sales_categories_between($range['start'], $range['end']),
        'order_status' => $ordersOn
            ? dashboard_order_status_between($range['start'], $range['end'])
            : [],
        'top_products' => dashboard_top_products_between($range['start'], $range['end'], 5),
        'activity' => dashboard_recent_activity_between($range['start'], $range['end'], 6),
        'attention' => $attention,
        'orders_on' => $ordersOn,
        'empty' => $cards === [],
        'upgrade_href' => '/account/subscription',
        'show_upgrade' => subscription_show_upgrade_cta(),
    ];
}

function dashboard_card(string $key, string $value, string $note = '', string $href = ''): array
{
    return [
        'key' => $key,
        'label' => t($key),
        'value' => $value,
        'note' => $note,
        'href' => $href,
    ];
}

function dashboard_period_label(string $start, string $end): string
{
    $s = DateTimeImmutable::createFromFormat('!Y-m-d', $start);
    $e = DateTimeImmutable::createFromFormat('!Y-m-d', $end);
    if ($s === false || $e === false) {
        return $start . ' – ' . $end;
    }
    return $s->format('M j') . ' – ' . $e->format('M j, Y');
}

function dashboard_stat_cards(array $data): array
{
    $kpis = [];
    if (!empty($data['finance_on'])) {
        $change = dashboard_pct_change((int) $data['revenue_kobo'], (int) $data['revenue_prev_kobo']);
        $rev = (int) $data['revenue_kobo'];
        $kpis[] = [
            'key' => 'dash_kpi_revenue',
            'label' => t('dash_kpi_revenue'),
            'value' => money($rev),
            'value_compact' => money_compact($rev),
            'amount_kobo' => $rev,
            'tone' => 'blue',
            'icon' => 'shopping-cart',
            'href' => module_enabled('fashion.sales') ? '/sales' : '/outstanding',
            'change_pct' => $change,
            'points' => $data['revenue_points'] ?? [],
            'note' => '',
        ];
    }
    if (!empty($data['orders_on'])) {
        $change = dashboard_pct_change((int) $data['orders'], (int) $data['orders_prev']);
        $kpis[] = [
            'key' => 'dash_kpi_orders',
            'label' => t('dash_kpi_orders'),
            'value' => (string) $data['orders'],
            'value_compact' => (string) $data['orders'],
            'amount_kobo' => null,
            'tone' => 'purple',
            'icon' => 'tag',
            'href' => '/orders',
            'change_pct' => $change,
            'points' => $data['orders_points'] ?? [],
            'note' => '',
        ];
        $changeC = dashboard_pct_change((int) $data['customers'], (int) $data['customers_prev']);
        $kpis[] = [
            'key' => 'dash_kpi_customers',
            'label' => t('dash_kpi_customers'),
            'value' => (string) $data['customers'],
            'value_compact' => (string) $data['customers'],
            'amount_kobo' => null,
            'tone' => 'green',
            'icon' => 'users',
            'href' => '/customers',
            'change_pct' => $changeC,
            'points' => $data['customers_points'] ?? [],
            'note' => '',
        ];
    }
    if (!empty($data['inventory_on'])) {
        $new = (int) ($data['low_stock_new'] ?? 0);
        $kpis[] = [
            'key' => 'card_low_stock',
            'label' => t('dash_kpi_low_stock'),
            'value' => (string) $data['low_stock'],
            'value_compact' => (string) $data['low_stock'],
            'amount_kobo' => null,
            'tone' => 'orange',
            'icon' => 'package',
            'href' => '/stock',
            'change_pct' => null,
            'points' => [],
            'note' => $new > 0
                ? t('dash_kpi_low_stock_new', ['count' => (string) $new])
                : t('dash_kpi_stock_ok'),
            'note_danger' => $new > 0,
        ];
    }
    return $kpis;
}

function dashboard_customer_count(): int
{
    if (!module_enabled('fashion.orders')) {
        return 0;
    }
    $rows = db_query('SELECT COUNT(*) AS total FROM fashion_customers WHERE tenant_id = :tenant_id');
    return (int) ($rows[0]['total'] ?? 0);
}

function dashboard_bounds_sql(string $start, string $end): array
{
    return [$start . ' 00:00:00', $end . ' 23:59:59'];
}

function dashboard_day_bucket(string $start, string $end): array
{
    $s = DateTimeImmutable::createFromFormat('!Y-m-d', $start) ?: new DateTimeImmutable('today');
    $e = DateTimeImmutable::createFromFormat('!Y-m-d', $end) ?: $s;
    $byDay = [];
    $cursor = $s;
    while ($cursor <= $e) {
        $byDay[$cursor->format('Y-m-d')] = 0;
        $cursor = $cursor->modify('+1 day');
    }
    return $byDay;
}

function dashboard_revenue_between(string $start, string $end): array
{
    [$startSql, $endSql] = dashboard_bounds_sql($start, $end);
    $byDay = dashboard_day_bucket($start, $end);
    $total = 0;
    if (module_enabled('fashion.finance') || module_enabled('fashion.orders')) {
        $rows = db_query(
            'SELECT DATE(created_at) AS day,
                    COALESCE(SUM(CASE WHEN reverses_payment_id IS NULL THEN amount_kobo ELSE -amount_kobo END), 0) AS total
             FROM fashion_payments
             WHERE tenant_id = :tenant_id
               AND created_at >= :start_at
               AND created_at <= :end_at
             GROUP BY DATE(created_at)
             ORDER BY day ASC',
            ['start_at' => $startSql, 'end_at' => $endSql]
        );
        foreach ($rows as $row) {
            $day = (string) ($row['day'] ?? '');
            if (!isset($byDay[$day])) {
                continue;
            }
            $amount = (int) ($row['total'] ?? 0);
            $byDay[$day] = $amount;
            $total += $amount;
        }
    }
    if (module_enabled('fashion.sales')) {
        $sales = db_query(
            'SELECT DATE(created_at) AS day, COALESCE(SUM(total_kobo), 0) AS total
             FROM fashion_sales
             WHERE tenant_id = :tenant_id
               AND status = \'completed\'
               AND created_at >= :start_at
               AND created_at <= :end_at
             GROUP BY DATE(created_at)
             ORDER BY day ASC',
            ['start_at' => $startSql, 'end_at' => $endSql]
        );
        foreach ($sales as $row) {
            $day = (string) ($row['day'] ?? '');
            if (!isset($byDay[$day])) {
                continue;
            }
            $amount = (int) ($row['total'] ?? 0);
            $byDay[$day] += $amount;
            $total += $amount;
        }
    }
    $points = [];
    foreach ($byDay as $day => $amount) {
        $points[] = ['day' => $day, 'amount_kobo' => (int) $amount];
    }
    $amounts = array_map(static fn (array $p): int => (int) $p['amount_kobo'], $points);
    return [
        'start' => $start,
        'end' => $end,
        'days' => count($points),
        'points' => $points,
        'period_total_kobo' => max(0, $total),
        'max_kobo' => max(1, $amounts === [] ? 1 : max($amounts)),
    ];
}

function dashboard_revenue_series(int $days = 30): array
{
    $days = max(7, min(366, $days));
    $today = DateTimeImmutable::createFromFormat('!Y-m-d', app_today()) ?: new DateTimeImmutable('today');
    $start = $today->modify('-' . ($days - 1) . ' days')->format('Y-m-d');
    return dashboard_revenue_between($start, $today->format('Y-m-d'));
}

function dashboard_orders_created_count(string $start, string $end): int
{
    if (!module_enabled('fashion.orders')) {
        return 0;
    }
    [$startSql, $endSql] = dashboard_bounds_sql($start, $end);
    $rows = db_query(
        'SELECT COUNT(*) AS total FROM fashion_orders
         WHERE tenant_id = :tenant_id AND created_at >= :start_at AND created_at <= :end_at',
        ['start_at' => $startSql, 'end_at' => $endSql]
    );
    return (int) ($rows[0]['total'] ?? 0);
}

function dashboard_orders_daily_series(string $start, string $end): array
{
    if (!module_enabled('fashion.orders')) {
        return [];
    }
    [$startSql, $endSql] = dashboard_bounds_sql($start, $end);
    $byDay = dashboard_day_bucket($start, $end);
    $rows = db_query(
        'SELECT DATE(created_at) AS day, COUNT(*) AS total
         FROM fashion_orders
         WHERE tenant_id = :tenant_id AND created_at >= :start_at AND created_at <= :end_at
         GROUP BY DATE(created_at)',
        ['start_at' => $startSql, 'end_at' => $endSql]
    );
    foreach ($rows as $row) {
        $day = (string) ($row['day'] ?? '');
        if (isset($byDay[$day])) {
            $byDay[$day] = (int) ($row['total'] ?? 0);
        }
    }
    $out = [];
    foreach ($byDay as $day => $amount) {
        $out[] = ['day' => $day, 'amount_kobo' => (int) $amount];
    }
    return $out;
}

function dashboard_customers_created_count(string $start, string $end): int
{
    if (!module_enabled('fashion.orders')) {
        return 0;
    }
    [$startSql, $endSql] = dashboard_bounds_sql($start, $end);
    $rows = db_query(
        'SELECT COUNT(*) AS total FROM fashion_customers
         WHERE tenant_id = :tenant_id AND created_at >= :start_at AND created_at <= :end_at',
        ['start_at' => $startSql, 'end_at' => $endSql]
    );
    return (int) ($rows[0]['total'] ?? 0);
}

function dashboard_customers_active_count(string $start, string $end): int
{
    if (!module_enabled('fashion.orders')) {
        return 0;
    }
    [$startSql, $endSql] = dashboard_bounds_sql($start, $end);
    $rows = db_query(
        'SELECT COUNT(DISTINCT o.customer_id) AS total
         FROM fashion_orders o
         WHERE o.tenant_id = :tenant_id
           AND o.created_at >= :start_at
           AND o.created_at <= :end_at',
        ['start_at' => $startSql, 'end_at' => $endSql]
    );
    return (int) ($rows[0]['total'] ?? 0);
}

function dashboard_customers_daily_series(string $start, string $end): array
{
    if (!module_enabled('fashion.orders')) {
        return [];
    }
    [$startSql, $endSql] = dashboard_bounds_sql($start, $end);
    $byDay = dashboard_day_bucket($start, $end);
    $rows = db_query(
        'SELECT DATE(created_at) AS day, COUNT(*) AS total
         FROM fashion_customers
         WHERE tenant_id = :tenant_id AND created_at >= :start_at AND created_at <= :end_at
         GROUP BY DATE(created_at)',
        ['start_at' => $startSql, 'end_at' => $endSql]
    );
    foreach ($rows as $row) {
        $day = (string) ($row['day'] ?? '');
        if (isset($byDay[$day])) {
            $byDay[$day] = (int) ($row['total'] ?? 0);
        }
    }
    $out = [];
    foreach ($byDay as $day => $amount) {
        $out[] = ['day' => $day, 'amount_kobo' => (int) $amount];
    }
    return $out;
}

function dashboard_low_stock_new(string $start, string $end): int
{
    if (!module_enabled('fashion.inventory')) {
        return 0;
    }
    [$startSql, $endSql] = dashboard_bounds_sql($start, $end);
    $rows = db_query(
        'SELECT COUNT(*) AS total FROM (
            SELECT i.id
            FROM fashion_stock_items i
            WHERE i.tenant_id = :tenant_id
              AND i.minimum_hundredths IS NOT NULL
              AND COALESCE((SELECT SUM(m.change_hundredths) FROM fashion_stock_movements m
                            WHERE m.tenant_id = :tenant_id AND m.item_id = i.id), 0) <= i.minimum_hundredths
              AND EXISTS (
                  SELECT 1 FROM fashion_stock_movements nm
                  WHERE nm.tenant_id = :tenant_id AND nm.item_id = i.id
                    AND nm.created_at >= :start_at AND nm.created_at <= :end_at
              )
         ) low_new',
        ['start_at' => $startSql, 'end_at' => $endSql]
    );
    return (int) ($rows[0]['total'] ?? 0);
}

function dashboard_sales_categories_between(string $start, string $end): array
{
    if (!module_enabled('fashion.orders')) {
        return ['total_kobo' => 0, 'items' => []];
    }
    [$startSql, $endSql] = dashboard_bounds_sql($start, $end);
    $rows = db_query(
        'SELECT g.name AS label, COALESCE(SUM(i.quantity * i.price_kobo), 0) AS total_kobo
         FROM fashion_order_items i
         INNER JOIN fashion_orders o ON o.id = i.order_id AND o.tenant_id = :tenant_id
         INNER JOIN fashion_garment_types g ON g.id = i.garment_type_id AND g.tenant_id = :tenant_id
         WHERE i.tenant_id = :tenant_id
           AND o.created_at >= :start_at
           AND o.created_at <= :end_at
         GROUP BY g.id, g.name
         ORDER BY total_kobo DESC
         LIMIT 4',
        ['start_at' => $startSql, 'end_at' => $endSql]
    );
    $items = [];
    $sum = 0;
    foreach ($rows as $row) {
        $amount = (int) ($row['total_kobo'] ?? 0);
        if ($amount <= 0) {
            continue;
        }
        $sum += $amount;
        $items[] = ['label' => (string) $row['label'], 'amount_kobo' => $amount];
    }
    foreach ($items as $i => $item) {
        $items[$i]['percent'] = $sum > 0 ? (int) round(($item['amount_kobo'] / $sum) * 100) : 0;
    }
    return ['total_kobo' => $sum, 'items' => $items];
}

function dashboard_sales_categories(): array
{
    $today = app_today();
    $start = (DateTimeImmutable::createFromFormat('!Y-m-d', $today) ?: new DateTimeImmutable('today'))
        ->modify('-29 days')->format('Y-m-d');
    return dashboard_sales_categories_between($start, $today);
}

function dashboard_top_products_between(string $start, string $end, int $limit = 5): array
{
    $limit = max(1, min(10, $limit));
    [$startSql, $endSql] = dashboard_bounds_sql($start, $end);
    if (module_enabled('fashion.sales')) {
        $rows = db_query(
            'SELECT p.name AS label, COALESCE(SUM(l.line_total_kobo), 0) AS total_kobo
             FROM fashion_sale_lines l
             INNER JOIN fashion_sales s ON s.id = l.sale_id AND s.tenant_id = :tenant_id
             INNER JOIN fashion_products p ON p.id = l.product_id AND p.tenant_id = :tenant_id
             WHERE l.tenant_id = :tenant_id AND s.status = \'completed\'
               AND s.created_at >= :start_at AND s.created_at <= :end_at
             GROUP BY p.id, p.name
             ORDER BY total_kobo DESC
             LIMIT ' . $limit,
            ['start_at' => $startSql, 'end_at' => $endSql]
        );
        $out = [];
        foreach ($rows as $row) {
            $out[] = ['label' => (string) $row['label'], 'amount_kobo' => (int) ($row['total_kobo'] ?? 0)];
        }
        if ($out !== []) {
            return $out;
        }
    }
    if (module_enabled('fashion.orders')) {
        $rows = db_query(
            'SELECT g.name AS label, COALESCE(SUM(i.quantity * i.price_kobo), 0) AS total_kobo
             FROM fashion_order_items i
             INNER JOIN fashion_orders o ON o.id = i.order_id AND o.tenant_id = :tenant_id
             INNER JOIN fashion_garment_types g ON g.id = i.garment_type_id AND g.tenant_id = :tenant_id
             WHERE i.tenant_id = :tenant_id
               AND o.created_at >= :start_at AND o.created_at <= :end_at
             GROUP BY g.id, g.name
             ORDER BY total_kobo DESC
             LIMIT ' . $limit,
            ['start_at' => $startSql, 'end_at' => $endSql]
        );
        $out = [];
        foreach ($rows as $row) {
            $out[] = ['label' => (string) $row['label'], 'amount_kobo' => (int) ($row['total_kobo'] ?? 0)];
        }
        return $out;
    }
    return [];
}

function dashboard_top_products(int $limit = 5): array
{
    $today = app_today();
    $start = (DateTimeImmutable::createFromFormat('!Y-m-d', $today) ?: new DateTimeImmutable('today'))
        ->modify('-29 days')->format('Y-m-d');
    return dashboard_top_products_between($start, $today, $limit);
}

function dashboard_order_status_between(string $start, string $end): array
{
    require_module('fashion.orders');
    [$startSql, $endSql] = dashboard_bounds_sql($start, $end);
    // Status buckets use stage codes (and finished/ready roles). Roles are only open/ready/finished.
    $stageCode = order_stage_sql('code');
    $stageRole = order_stage_sql('role');
    $rows = db_query(
        'SELECT
            COALESCE(SUM(CASE WHEN ' . $stageCode . ' IS NULL OR ' . $stageCode . ' = \'new\' THEN 1 ELSE 0 END), 0) AS pending_count,
            COALESCE(SUM(CASE WHEN ' . $stageCode . ' IN (\'cutting\', \'sewing\', \'embroidery\', \'finishing\', \'fitting\', \'in_progress\', \'production\') THEN 1 ELSE 0 END), 0) AS processing_count,
            COALESCE(SUM(CASE WHEN ' . $stageRole . ' IN (\'ready\', \'finished\') AND ' . $stageCode . ' <> \'cancelled\' THEN 1 ELSE 0 END), 0) AS completed_count,
            COALESCE(SUM(CASE WHEN ' . $stageCode . ' = \'cancelled\' THEN 1 ELSE 0 END), 0) AS cancelled_count,
            COUNT(*) AS total_count
         FROM fashion_orders o
         WHERE o.tenant_id = :tenant_id
           AND o.created_at >= :start_at
           AND o.created_at <= :end_at',
        ['start_at' => $startSql, 'end_at' => $endSql]
    );
    $counts = $rows[0] ?? [];
    $pending = (int) ($counts['pending_count'] ?? 0);
    $processing = (int) ($counts['processing_count'] ?? 0);
    $completed = (int) ($counts['completed_count'] ?? 0);
    $cancelled = (int) ($counts['cancelled_count'] ?? 0);
    $total = (int) ($counts['total_count'] ?? 0);
    $other = max(0, $total - $pending - $processing - $completed - $cancelled);
    return [
        ['key' => 'pending', 'label' => t('dash_status_pending'), 'count' => $pending, 'tone' => 'orange'],
        ['key' => 'processing', 'label' => t('dash_status_processing'), 'count' => $processing, 'tone' => 'blue'],
        ['key' => 'completed', 'label' => t('dash_status_completed'), 'count' => $completed, 'tone' => 'green'],
        ['key' => 'cancelled', 'label' => t('dash_status_cancelled'), 'count' => $cancelled + $other, 'tone' => 'red'],
    ];
}

function dashboard_recent_activity_between(string $start, string $end, int $limit = 6): array
{
    $limit = max(1, min(20, $limit));
    [$startSql, $endSql] = dashboard_bounds_sql($start, $end);
    $items = [];
    if (module_enabled('fashion.orders')) {
        $rows = db_query(
            'SELECT o.id, o.order_number, o.created_at, c.name AS customer_name
             FROM fashion_orders o
             INNER JOIN fashion_customers c ON c.id = o.customer_id AND c.tenant_id = :tenant_id
             WHERE o.tenant_id = :tenant_id
               AND o.created_at >= :start_at AND o.created_at <= :end_at
             ORDER BY o.created_at DESC
             LIMIT ' . $limit,
            ['start_at' => $startSql, 'end_at' => $endSql]
        );
        foreach ($rows as $row) {
            $items[] = [
                'tone' => 'blue',
                'icon' => 'package',
                'title' => t('dash_activity_order', ['number' => (string) $row['order_number']]),
                'meta' => (string) $row['customer_name'],
                'href' => '/orders/' . $row['id'],
                'when' => dashboard_activity_when((string) $row['created_at']),
            ];
        }
    }
    return array_slice($items, 0, $limit);
}

function dashboard_activity_when(string $at): string
{
    $dt = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $at)
        ?: DateTimeImmutable::createFromFormat('!Y-m-d', substr($at, 0, 10));
    if ($dt === false) {
        return $at;
    }
    $now = new DateTimeImmutable('now');
    $secs = max(0, $now->getTimestamp() - $dt->getTimestamp());
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
    return $dt->format('M j');
}

/** @param list<array> $attention */
function dashboard_recent_activity(array $attention, int $limit = 6): array
{
    $today = app_today();
    $start = (DateTimeImmutable::createFromFormat('!Y-m-d', $today) ?: new DateTimeImmutable('today'))
        ->modify('-29 days')->format('Y-m-d');
    $items = dashboard_recent_activity_between($start, $today, $limit);
    if ($items !== []) {
        return $items;
    }
    foreach ($attention as $order) {
        $items[] = [
            'tone' => !empty($order['overdue']) ? 'danger' : 'orange',
            'icon' => 'package',
            'title' => t('dash_activity_order', ['number' => (string) $order['order_number']]),
            'meta' => (string) $order['customer_name'],
            'href' => '/orders/' . $order['id'],
            'when' => (string) $order['due_date'],
        ];
        if (count($items) >= $limit) {
            break;
        }
    }
    return $items;
}

function dashboard_order_counts(): array
{
    require_module('fashion.orders');
    $today = app_today();
    [$weekStart, $weekEnd] = dashboard_week($today);
    $stageRole = order_stage_sql('role');
    $open = '(' . $stageRole . ' IS NULL OR ' . $stageRole . ' NOT IN (\'ready\', \'finished\'))';
    $unfinished = '(' . $stageRole . ' IS NULL OR ' . $stageRole . ' <> \'finished\')';
    $rows = db_query(
        'SELECT
            COALESCE(SUM(CASE WHEN ' . $open . ' THEN 1 ELSE 0 END), 0) AS active_count,
            COALESCE(SUM(CASE WHEN o.due_date >= :week_start AND o.due_date <= :week_end AND ' . $unfinished . ' THEN 1 ELSE 0 END), 0) AS week_count,
            COALESCE(SUM(CASE WHEN o.due_date < :today AND ' . $unfinished . ' THEN 1 ELSE 0 END), 0) AS overdue_count,
            COALESCE(SUM(CASE WHEN ' . $stageRole . ' = \'ready\' THEN 1 ELSE 0 END), 0) AS ready_count,
            COUNT(*) AS total_count,
            COALESCE(SUM(CASE WHEN ' . order_stage_sql('code') . ' IS NULL OR ' . order_stage_sql('code') . ' = \'new\' THEN 1 ELSE 0 END), 0) AS pending_count,
            COALESCE(SUM(CASE WHEN ' . order_stage_sql('code') . ' IN (\'cutting\', \'sewing\', \'embroidery\', \'finishing\', \'fitting\', \'in_progress\', \'production\') THEN 1 ELSE 0 END), 0) AS processing_count,
            COALESCE(SUM(CASE WHEN ' . $stageRole . ' IN (\'ready\', \'finished\') AND ' . order_stage_sql('code') . ' <> \'cancelled\' THEN 1 ELSE 0 END), 0) AS completed_count
         FROM fashion_orders o
         WHERE o.tenant_id = :tenant_id',
        ['week_start' => $weekStart, 'week_end' => $weekEnd, 'today' => $today]
    );
    $counts = $rows[0] ?? [];
    $attentionRows = db_query(
        'SELECT o.id, o.order_number, o.due_date, o.urgent, c.name AS customer_name, '
            . $stageRole . ' AS stage_role
         FROM fashion_orders o
         INNER JOIN fashion_customers c ON c.id = o.customer_id AND c.tenant_id = :tenant_id
         WHERE o.tenant_id = :tenant_id
           AND ((o.due_date < :today AND ' . $unfinished . ') OR o.urgent = 1)
         ORDER BY o.due_date ASC, o.order_number ASC',
        ['today' => $today]
    );
    $attention = [];
    foreach ($attentionRows as $row) {
        $role = $row['stage_role'];
        $finished = is_string($role) && $role === 'finished';
        $attention[] = [
            'id' => (string) $row['id'],
            'order_number' => (string) $row['order_number'],
            'customer_name' => (string) $row['customer_name'],
            'due_date' => (string) $row['due_date'],
            'urgent' => (int) $row['urgent'] === 1,
            'overdue' => (string) $row['due_date'] < $today && !$finished,
        ];
    }
    $pending = (int) ($counts['pending_count'] ?? 0);
    $processing = (int) ($counts['processing_count'] ?? 0);
    $completed = (int) ($counts['completed_count'] ?? 0);
    $total = (int) ($counts['total_count'] ?? 0);
    $other = max(0, $total - $pending - $processing - $completed);
    return [
        'active' => (int) ($counts['active_count'] ?? 0),
        'week' => (int) ($counts['week_count'] ?? 0),
        'overdue' => (int) ($counts['overdue_count'] ?? 0),
        'ready' => (int) ($counts['ready_count'] ?? 0),
        'total' => $total,
        'attention' => $attention,
        'status' => [
            ['key' => 'pending', 'label' => t('dash_status_pending'), 'count' => $pending, 'tone' => 'orange'],
            ['key' => 'processing', 'label' => t('dash_status_processing'), 'count' => $processing, 'tone' => 'blue'],
            ['key' => 'completed', 'label' => t('dash_status_completed'), 'count' => $completed, 'tone' => 'green'],
            ['key' => 'other', 'label' => t('dash_status_other'), 'count' => $other, 'tone' => 'muted'],
        ],
    ];
}

/**
 * Nice round Y-axis max (kobo) at or above the data max.
 */
function dashboard_chart_nice_max(int $maxKobo): int
{
    $naira = max(0, (int) ceil(max(0, $maxKobo) / 100));
    if ($naira <= 0) {
        return 400; // ₦0–₦4 axis when there is no revenue yet
    }
    // Choose a tick step so 4 steps reach at/above the data max (0 + 4*step).
    $rawStep = $naira / 4;
    $exp = (int) floor(log10(max(1.0, $rawStep)));
    $mag = (float) (10 ** max(0, $exp));
    $norm = $rawStep / $mag;
    $niceNorm = 10.0;
    foreach ([1.0, 1.25, 1.5, 2.0, 2.5, 3.0, 4.0, 5.0, 6.0, 7.5, 8.0, 10.0] as $c) {
        if ($norm <= $c) {
            $niceNorm = $c;
            break;
        }
    }
    $step = (int) round($niceNorm * $mag);
    if ($step < 1) {
        $step = 1;
    }
    $niceNaira = $step * 4;
    while ($niceNaira < $naira) {
        $step += max(1, (int) round($mag));
        $niceNaira = $step * 4;
    }
    return max(100, $niceNaira * 100);
}

/**
 * Evenly spaced ascending Y ticks from 0 (bottom) to nice max (top), in kobo.
 *
 * @return list<int>
 */
function dashboard_chart_y_ticks(int $maxKobo, int $steps = 4): array
{
    $steps = max(2, min(6, $steps));
    $niceMax = dashboard_chart_nice_max($maxKobo);
    $ticks = [];
    $seen = [];
    for ($i = 0; $i <= $steps; $i++) {
        $tick = (int) round(($niceMax / $steps) * $i);
        if (isset($seen[$tick])) {
            continue;
        }
        $seen[$tick] = true;
        $ticks[] = $tick;
    }
    if ($ticks === [] || $ticks[0] !== 0) {
        array_unshift($ticks, 0);
    }
    if (($ticks[count($ticks) - 1] ?? 0) !== $niceMax) {
        $ticks[] = $niceMax;
    }
    return $ticks;
}

/**
 * Build chart geometry for revenue points (plot area inside padding).
 * Y grows upward: 0 at bottom, $maxKobo at top.
 *
 * @return array{polyline:string,area:string,dots:list<array{x:float,y:float,day:string,amount:int}>,max:int}
 */
function dashboard_chart_geometry(array $points, int $maxKobo, int $width = 640, int $height = 240, int $padL = 52, int $padR = 16, int $padT = 16, int $padB = 36): array
{
    $n = count($points);
    $maxKobo = max(1, $maxKobo);
    $plotW = max(1, $width - $padL - $padR);
    $plotH = max(1, $height - $padT - $padB);
    if ($n === 0) {
        return ['polyline' => '', 'area' => '', 'dots' => [], 'max' => $maxKobo];
    }
    $coords = [];
    $dots = [];
    foreach ($points as $i => $point) {
        $x = $padL + ($n === 1 ? $plotW / 2 : ($i / ($n - 1)) * $plotW);
        $ratio = min(1.0, max(0.0, (int) $point['amount_kobo'] / $maxKobo));
        $y = $padT + $plotH - ($ratio * $plotH);
        $coords[] = round($x, 1) . ',' . round($y, 1);
        $dots[] = [
            'x' => round($x, 1),
            'y' => round($y, 1),
            'day' => (string) $point['day'],
            'amount' => (int) $point['amount_kobo'],
        ];
    }
    $poly = implode(' ', $coords);
    $area = $padL . ',' . ($padT + $plotH) . ' ' . $poly . ' ' . ($padL + $plotW) . ',' . ($padT + $plotH);
    return ['polyline' => $poly, 'area' => $area, 'dots' => $dots, 'max' => $maxKobo];
}

/** Build SVG polyline for revenue points. */
function dashboard_chart_polyline(array $points, int $maxKobo, int $width = 560, int $height = 180): string
{
    return dashboard_chart_geometry($points, $maxKobo, $width, $height, 0, 0, 6, 6)['polyline'];
}

function dashboard_sparkline(array $points, string $color, int $width = 220, int $height = 36): string
{
    if ($points === []) {
        return '';
    }
    $amounts = array_map(static fn (array $p): int => (int) ($p['amount_kobo'] ?? 0), $points);
    $max = max(1, max($amounts));
    $geo = dashboard_chart_geometry($points, $max, $width, $height, 2, 2, 2, 2);
    if ($geo['polyline'] === '') {
        return '';
    }
    $id = 'sp' . substr(sha1($color . $geo['polyline']), 0, 8);
    return '<svg class="dash-spark" viewBox="0 0 ' . $width . ' ' . $height . '" preserveAspectRatio="none" width="100%" height="' . $height . '" aria-hidden="true">'
        . '<defs><linearGradient id="' . e($id) . '" x1="0" y1="0" x2="0" y2="1">'
        . '<stop offset="0%" stop-color="' . e($color) . '" stop-opacity="0.35"/>'
        . '<stop offset="100%" stop-color="' . e($color) . '" stop-opacity="0.02"/>'
        . '</linearGradient></defs>'
        . '<polygon fill="url(#' . e($id) . ')" points="' . e($geo['area']) . '"></polygon>'
        . '<polyline fill="none" stroke="' . e($color) . '" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" points="' . e($geo['polyline']) . '"></polyline>'
        . '</svg>';
}

function dashboard_donut_gradient(array $items): string
{
    if ($items === []) {
        return 'conic-gradient(var(--line) 0 100%)';
    }
    $tones = ['#1A56F0', '#8B3FE0', '#12A150', '#F59E0B', '#F21A3C'];
    $parts = [];
    $cursor = 0;
    foreach ($items as $i => $item) {
        $pct = (int) ($item['percent'] ?? 0);
        $color = $tones[$i % count($tones)];
        $next = min(100, $cursor + max(0, $pct));
        $parts[] = $color . ' ' . $cursor . '% ' . $next . '%';
        $cursor = $next;
    }
    if ($cursor < 100) {
        $parts[] = 'var(--line) ' . $cursor . '% 100%';
    }
    return 'conic-gradient(' . implode(', ', $parts) . ')';
}
