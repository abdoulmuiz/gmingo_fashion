<?php

declare(strict_types=1);

require_once __DIR__ . '/support.php';
require_once dirname(__DIR__) . '/app/domain/accounts.php';
require_once dirname(__DIR__) . '/app/domain/customers.php';
require_once dirname(__DIR__) . '/app/domain/orders.php';
require_once dirname(__DIR__) . '/app/domain/payments.php';
require_once dirname(__DIR__) . '/app/domain/groups.php';
require_once dirname(__DIR__) . '/app/domain/stock.php';

$connected = db()->query('SELECT DATABASE()')->fetchColumn();
if (!is_string($connected) || !str_ends_with($connected, '_test')) {
    fwrite(STDERR, "Refusing to run GET write tests: database name must end with _test.\n");
    exit(1);
}

tests_reset();

function get_writes_counts(string $tenantId): array
{
    if (!is_uuid($tenantId)) {
        throw new RuntimeException('GET write counts need a shop id.');
    }
    $counts = [];
    $names = db()->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
    $hasTenant = db()->prepare(
        'SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table_name AND COLUMN_NAME = :column_name'
    );
    foreach ($names as $name) {
        if (!is_string($name) || preg_match('/^[A-Za-z0-9_]+$/', $name) !== 1) {
            throw new RuntimeException('Unsafe table name.');
        }
        $hasTenant->execute(['table_name' => $name, 'column_name' => 'tenant_id']);
        if ((int) $hasTenant->fetchColumn() === 1) {
            $statement = db()->prepare('SELECT COUNT(*) FROM `' . $name . '` WHERE tenant_id = :tenant_id');
            $statement->execute(['tenant_id' => $tenantId]);
            $counts[$name] = (int) $statement->fetchColumn();
        } else {
            $counts[$name] = (int) db()->query('SELECT COUNT(*) FROM `' . $name . '`')->fetchColumn();
        }
    }
    ksort($counts);
    return $counts;
}

$stamp = bin2hex(random_bytes(4));
$shop = business_register([
    'business_name' => 'Read Only Shop',
    'owner_name' => 'Amina Bello',
    'email' => 'get-writes-' . $stamp . '@example.com',
    'password' => 'password',
    'phone' => '0803' . str_pad((string) (hexdec(substr($stamp, 0, 6)) % 10000000), 7, '0', STR_PAD_LEFT),
    'company_type' => 'tailor',
]);
check(isset($shop['tenant_id'], $shop['user_id']), 'GET visits start from a signed-in owner');
$ownerId = (string) $shop['user_id'];
$tenantId = (string) $shop['tenant_id'];

module_save([
    'fashion.orders',
    'fashion.production',
    'fashion.finance',
    'fashion.measurements',
    'fashion.communication',
    'fashion.inventory',
]);

$customer = customer_create([
    'name' => 'Hauwa Sani',
    'phone' => '08031112255',
    'gender' => 'female',
    'customer_type' => 'regular',
]);
$garment = db_select('fashion_garment_types', ['code' => 'kaftan'])[0];
$order = order_create([
    'customer_id' => $customer['id'],
    'due_date' => date('Y-m-d', strtotime('+3 days')),
    'urgent' => '',
    'items' => [[
        'garment_type_id' => $garment['id'],
        'quantity' => '1',
        'price' => '2000',
        'fabric' => '',
        'notes' => '',
    ]],
]);
check(isset($order['id']), 'GET visits have an order to open');
$orderRow = db_select('fashion_orders', ['id' => $order['id']], ['limit' => 1])[0];
$token = (string) $orderRow['tracking_token'];
$payment = payment_record($order['id'], '500', 'cash');
check(isset($payment['receipt_id']), 'GET visits have a receipt to open');
$group = group_create([
    'name' => 'Wedding party',
    'pay_mode' => 'each_pays',
    'event_date' => '',
    'payer_customer_id' => '',
]);
check(isset($group['id']), 'GET visits have a group to open');
$stock = stock_create([
    'name' => 'White thread',
    'category' => 'thread',
    'unit' => 'cones',
    'colour' => '',
    'minimum' => '',
]);
check(isset($stock['id']), 'GET visits have a stock item to open');
$messages = db_select('fashion_messages', ['order_id' => $order['id']], ['limit' => 1]);
check($messages !== [], 'GET visits have a message to open');

$samples = [
    '/track/{token}/invoice.png' => '/track/' . $token . '/invoice.png',
    '/track/{token}/invoice.pdf' => '/track/' . $token . '/invoice.pdf',
    '/track/{token}/receipt/{id}.png' => '/track/' . $token . '/receipt/' . $payment['id'] . '.png',
    '/track/{token}/receipt/{id}.pdf' => '/track/' . $token . '/receipt/' . $payment['id'] . '.pdf',
    '/track/{token}' => '/track/' . $token,
    '/groups/{id}' => '/groups/' . $group['id'],
    '/stock/{id}' => '/stock/' . $stock['id'],
    '/calendar/{date}' => '/calendar/' . app_today(),
    '/orders/{id}' => '/orders/' . $order['id'],
    '/orders/{id}/fabric' => '/orders/' . $order['id'] . '/fabric',
    '/orders/{id}/invoice.png' => '/orders/' . $order['id'] . '/invoice.png',
    '/orders/{id}/invoice.pdf' => '/orders/' . $order['id'] . '/invoice.pdf',
    '/orders/{id}/invoice' => '/orders/' . $order['id'] . '/invoice',
    '/receipts/{id}.png' => '/receipts/' . $payment['receipt_id'] . '.png',
    '/receipts/{id}.pdf' => '/receipts/' . $payment['receipt_id'] . '.pdf',
    '/receipts/{id}' => '/receipts/' . $payment['receipt_id'],
    '/customers/{id}' => '/customers/' . $customer['id'],
    '/settings/garments/{id}' => '/settings/garments/' . $garment['id'],
    '/messages/{id}' => '/messages/' . $messages[0]['id'],
    '/platform/plans/{id}' => '/platform/plans/' . (string) plan_by_code('starter')['id'],
    '/platform/tenants/{id}' => '/platform/tenants/' . $tenantId,
    '/staff/{id}' => '/staff/' . $ownerId,
    '/products/{id}' => '/products/' . uuid(),
    '/shop/{slug}' => '/shop/missing-shop-' . $stamp,
];

auth_login($ownerId, $tenantId);
$before = get_writes_counts($tenantId);
check((int) ($before['fashion_payment_settings'] ?? 0) === 0, 'opening pages starts with no payment settings row');

$visited = 0;
$getRoutes = 0;
foreach ($GLOBALS['gmingo_routes'] as $route) {
    if ((string) $route[0] !== 'GET') {
        continue;
    }
    $getRoutes++;
    $pattern = (string) $route[1];
    if (str_contains($pattern, '{') && !isset($samples[$pattern])) {
        check(false, 'GET ' . $pattern . ' needs a sample path');
        continue;
    }
    $path = $samples[$pattern] ?? $pattern;
    auth_login($ownerId, $tenantId);
    auth_release_tenant();
    $_GET = [];
    $_POST = [];
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_SERVER['REQUEST_URI'] = base_path() . $path;
    $level = ob_get_level();
    ob_start();
    try {
        router_dispatch('GET', $path);
        $visited++;
    } catch (RedirectException) {
        $visited++;
    } catch (HttpException) {
        $visited++;
    } catch (Throwable $e) {
        check(false, 'GET ' . $path . ' threw ' . $e::class . ': ' . $e->getMessage());
    } finally {
        while (ob_get_level() > $level) {
            ob_end_clean();
        }
        auth_release_tenant();
    }
}

check($getRoutes > 0 && $visited === $getRoutes, 'every GET route was visited as the owner');

auth_login($ownerId, $tenantId);
$after = get_writes_counts($tenantId);
$changes = [];
foreach (array_unique(array_merge(array_keys($before), array_keys($after))) as $table) {
    $left = $before[$table] ?? 0;
    $right = $after[$table] ?? 0;
    if ($left !== $right) {
        $changes[] = $table . ' ' . (string) $left . ' -> ' . (string) $right;
    }
}
sort($changes);

// Sessions live in files under storage/sessions. The only table a GET may change is the tracking rate limit.
$allowedChanges = [
    'fashion_track_limits',
];
$unexpected = [];
foreach ($changes as $change) {
    $table = explode(' ', $change, 2)[0];
    if (!in_array($table, $allowedChanges, true)) {
        $unexpected[] = $change;
    }
}
$detail = $unexpected === [] ? 'GET visits do not create rows' : 'GET visits do not create rows (' . implode('; ', $unexpected) . ')';
check($unexpected === [], $detail);
check((int) ($after['fashion_payment_settings'] ?? 0) === 0, 'viewing payment settings does not insert a row');
