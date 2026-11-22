<?php

declare(strict_types=1);

/**
 * Removes only records created by database/seed_demo_data.php.
 * Run: php database/remove_demo_data.php
 */

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Run from the command line: php database/remove_demo_data.php\n");
    exit(1);
}

require dirname(__DIR__) . '/app/bootstrap.php';

const DEMO_MARK = '[demo]';
const DEMO_NAME_SUFFIX = ' [demo]';
const DEMO_ORDER_PREFIX = 'DEMO-';
const DEMO_SKU_PREFIX = 'DEMO-';
const DEMO_SALE_PREFIX = 'DEMO-';

function demo_fail(string $message): never
{
    fwrite(STDERR, $message . "\n");
    exit(1);
}

$config = app_config();
$env = (string) ($config['environment'] ?? '');
if ($env !== 'local' && $env !== 'dev') {
    demo_fail('Refusing to remove demo data: environment must be local/dev.');
}
$host = strtolower((string) (db_settings()['host'] ?? ''));
if (!in_array($host, ['127.0.0.1', 'localhost', '::1'], true)) {
    demo_fail('Refusing to remove demo data: database host must be localhost / 127.0.0.1.');
}

$settings = db_settings();
$shopStmt = db()->prepare(
    'SELECT t.id AS tenant_id, t.name AS shop_name, u.id AS user_id, u.email
     FROM fashion_tenants t
     INNER JOIN fashion_users u ON u.tenant_id = t.id AND u.role = \'owner\'
     WHERE u.email = :email
     LIMIT 1'
);
$shopStmt->execute(['email' => 'demo@gmingo.test']);
$shop = $shopStmt->fetch(PDO::FETCH_ASSOC);
if ($shop === false) {
    demo_fail('Could not find demo@gmingo.test shop.');
}
$tenantId = (string) $shop['tenant_id'];
$userId = (string) $shop['user_id'];
auth_boot();
$_SESSION['user_id'] = $userId;
$_SESSION['tenant_id'] = $tenantId;
auth_bind_tenant($tenantId);
echo 'Connected database: ' . $settings['name'] . ' @ ' . $settings['host'] . "\n";
echo 'Shop: ' . $shop['shop_name'] . ' (' . $tenantId . ")\n";

$removed = [];

db_begin();
try {
    $orderIds = array_column(
        db_query(
            'SELECT id FROM fashion_orders WHERE tenant_id = :tenant_id AND order_number LIKE :p',
            ['p' => DEMO_ORDER_PREFIX . '%']
        ),
        'id'
    );
    $saleIds = array_column(
        db_query(
            'SELECT id FROM fashion_sales WHERE tenant_id = :tenant_id AND sale_number LIKE :p',
            ['p' => DEMO_SALE_PREFIX . '%']
        ),
        'id'
    );
    $productIds = array_column(
        db_query(
            'SELECT id FROM fashion_products WHERE tenant_id = :tenant_id AND sku LIKE :p',
            ['p' => DEMO_SKU_PREFIX . '%']
        ),
        'id'
    );
    $stockIds = array_column(
        db_query(
            'SELECT id FROM fashion_stock_items WHERE tenant_id = :tenant_id AND name LIKE :m',
            ['m' => '%' . DEMO_MARK . '%']
        ),
        'id'
    );
    $customerIds = array_column(
        db_query(
            'SELECT id FROM fashion_customers WHERE tenant_id = :tenant_id AND name LIKE :m',
            ['m' => '%' . DEMO_NAME_SUFFIX]
        ),
        'id'
    );

    $del = static function (string $sql, array $params, string $label) use (&$removed): void {
        $stmt = db()->prepare($sql);
        db_bind($stmt, $params);
        $stmt->execute();
        $removed[$label] = ($removed[$label] ?? 0) + $stmt->rowCount();
    };

    // Sale lines
    if ($saleIds !== []) {
        $in = implode(',', array_fill(0, count($saleIds), '?'));
        $stmt = db()->prepare('DELETE FROM fashion_sale_lines WHERE tenant_id = ? AND sale_id IN (' . $in . ')');
        $stmt->execute(array_merge([$tenantId], $saleIds));
        $removed['sale_lines'] = $stmt->rowCount();
        $stmt = db()->prepare('DELETE FROM fashion_sales WHERE tenant_id = ? AND id IN (' . $in . ')');
        $stmt->execute(array_merge([$tenantId], $saleIds));
        $removed['sales'] = $stmt->rowCount();
    }

    if ($orderIds !== []) {
        $in = implode(',', array_fill(0, count($orderIds), '?'));
        $stmt = db()->prepare('DELETE FROM fashion_payments WHERE tenant_id = ? AND (reason = ? OR order_id IN (' . $in . '))');
        $stmt->execute(array_merge([$tenantId, DEMO_MARK], $orderIds));
        $removed['payments'] = $stmt->rowCount();

        $stmt = db()->prepare('DELETE FROM fashion_stage_events WHERE tenant_id = ? AND (note = ? OR order_id IN (' . $in . '))');
        $stmt->execute(array_merge([$tenantId, DEMO_MARK], $orderIds));
        $removed['stage_events'] = $stmt->rowCount();

        $stmt = db()->prepare('DELETE FROM fashion_order_items WHERE tenant_id = ? AND order_id IN (' . $in . ')');
        $stmt->execute(array_merge([$tenantId], $orderIds));
        $removed['order_items'] = $stmt->rowCount();

        $stmt = db()->prepare('DELETE FROM fashion_orders WHERE tenant_id = ? AND id IN (' . $in . ')');
        $stmt->execute(array_merge([$tenantId], $orderIds));
        $removed['orders'] = $stmt->rowCount();
    } else {
        $del(
            'DELETE FROM fashion_payments WHERE tenant_id = :tenant_id AND reason = :m',
            ['tenant_id' => $tenantId, 'm' => DEMO_MARK],
            'payments'
        );
    }

    $del(
        'DELETE FROM fashion_work_orders WHERE tenant_id = :tenant_id AND note = :m',
        ['tenant_id' => $tenantId, 'm' => DEMO_MARK],
        'work_orders'
    );
    $del(
        'DELETE FROM fashion_expenses WHERE tenant_id = :tenant_id AND note LIKE :m',
        ['tenant_id' => $tenantId, 'm' => '%' . DEMO_MARK . '%'],
        'expenses'
    );

    if ($stockIds !== []) {
        $in = implode(',', array_fill(0, count($stockIds), '?'));
        $stmt = db()->prepare('DELETE FROM fashion_stock_movements WHERE tenant_id = ? AND (note = ? OR item_id IN (' . $in . '))');
        $stmt->execute(array_merge([$tenantId, DEMO_MARK], $stockIds));
        $removed['stock_movements'] = $stmt->rowCount();
    } else {
        $del(
            'DELETE FROM fashion_stock_movements WHERE tenant_id = :tenant_id AND note = :m',
            ['tenant_id' => $tenantId, 'm' => DEMO_MARK],
            'stock_movements'
        );
    }

    if ($productIds !== []) {
        $in = implode(',', array_fill(0, count($productIds), '?'));
        $stmt = db()->prepare('DELETE FROM fashion_product_bom WHERE tenant_id = ? AND product_id IN (' . $in . ')');
        $stmt->execute(array_merge([$tenantId], $productIds));
        $removed['product_bom'] = $stmt->rowCount();
        $stmt = db()->prepare('DELETE FROM fashion_products WHERE tenant_id = ? AND id IN (' . $in . ')');
        $stmt->execute(array_merge([$tenantId], $productIds));
        $removed['products'] = $stmt->rowCount();
    }

    if ($stockIds !== []) {
        $in = implode(',', array_fill(0, count($stockIds), '?'));
        $stmt = db()->prepare('DELETE FROM fashion_stock_items WHERE tenant_id = ? AND id IN (' . $in . ')');
        $stmt->execute(array_merge([$tenantId], $stockIds));
        $removed['stock_items'] = $stmt->rowCount();
    }

    if ($customerIds !== []) {
        $in = implode(',', array_fill(0, count($customerIds), '?'));
        // Only delete demo customers that have no remaining non-demo orders
        $stmt = db()->prepare(
            'DELETE FROM fashion_customers WHERE tenant_id = ? AND id IN (' . $in . ')
             AND NOT EXISTS (SELECT 1 FROM fashion_orders o WHERE o.tenant_id = ? AND o.customer_id = fashion_customers.id)'
        );
        $stmt->execute(array_merge([$tenantId], $customerIds, [$tenantId]));
        $removed['customers'] = $stmt->rowCount();
    }

    $del(
        'DELETE FROM fashion_subscription_payments
         WHERE tenant_id = :tenant_id AND metadata_json LIKE :m',
        ['tenant_id' => $tenantId, 'm' => '%demo_seed%'],
        'subscription_payments'
    );

    // Demo-only garment / cancelled stage (only if unused)
    $acc = db_select('fashion_garment_types', ['code' => 'accessories'], ['limit' => 1]);
    if ($acc !== []) {
        $used = db_query(
            'SELECT COUNT(*) AS total FROM fashion_order_items WHERE tenant_id = :tenant_id AND garment_type_id = :id',
            ['id' => (string) $acc[0]['id']]
        );
        if ((int) ($used[0]['total'] ?? 0) === 0) {
            db_delete('fashion_garment_types', ['id' => (string) $acc[0]['id']]);
            $removed['garment_types'] = 1;
        }
    }
    $cancelled = db_select('fashion_stage_definitions', ['code' => 'cancelled'], ['limit' => 1]);
    if ($cancelled !== []) {
        $used = db_query(
            'SELECT COUNT(*) AS total FROM fashion_stage_events WHERE tenant_id = :tenant_id AND stage_id = :id',
            ['id' => (string) $cancelled[0]['id']]
        );
        if ((int) ($used[0]['total'] ?? 0) === 0) {
            db_delete('fashion_stage_definitions', ['id' => (string) $cancelled[0]['id']]);
            $removed['stage_definitions'] = ($removed['stage_definitions'] ?? 0) + 1;
        }
    }

    db_commit();
} catch (Throwable $e) {
    db_rollback();
    demo_fail('Remove failed and was rolled back: ' . $e->getMessage());
}

echo "\nRemoved:\n";
foreach ($removed as $label => $n) {
    echo '  ' . $label . ': ' . $n . "\n";
}
echo "Done.\n";
