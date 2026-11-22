<?php

declare(strict_types=1);

/**
 * Local-only dashboard demo data for Amina Bello's tailor shop
 * (Kano Stitches Tailoring — the seeded Amina Bello / demo@gmingo.test shop).
 *
 * Markers (no new columns):
 * - customers.name ends with " [demo]"
 * - orders.order_number starts with "DEMO-"
 * - products.sku starts with "DEMO-"
 * - sales.sale_number starts with "DEMO-"
 * - stock_items.name contains "[demo]"
 * - payments.reason = "[demo]"
 * - stage_events.note = "[demo]"
 * - stock_movements.note = "[demo]"
 * - expenses.note = "[demo]"
 * - work_orders.note = "[demo]"
 * - subscription_payments.metadata_json contains "demo_seed"
 *
 * Run:  php database/seed_demo_data.php
 * Undo: php database/remove_demo_data.php
 */

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Run from the command line: php database/seed_demo_data.php\n");
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

function demo_assert_local(): void
{
    $config = app_config();
    $env = (string) ($config['environment'] ?? '');
    if ($env !== 'local' && $env !== 'dev') {
        demo_fail('Refusing to seed: environment must be local/dev in config/config.php.');
    }
    $host = strtolower((string) (db_settings()['host'] ?? ''));
    if (!in_array($host, ['127.0.0.1', 'localhost', '::1'], true)) {
        demo_fail('Refusing to seed: database host must be localhost / 127.0.0.1 (got ' . $host . ').');
    }
}

function demo_find_shop(): array
{
    $stmt = db()->prepare(
        'SELECT t.id AS tenant_id, t.name AS shop_name, u.id AS user_id, u.name AS user_name, u.email
         FROM fashion_tenants t
         INNER JOIN fashion_users u ON u.tenant_id = t.id AND u.role = \'owner\'
         WHERE u.email = :email
         LIMIT 1'
    );
    $stmt->execute(['email' => 'demo@gmingo.test']);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row === false) {
        $stmt = db()->prepare(
            'SELECT t.id AS tenant_id, t.name AS shop_name, u.id AS user_id, u.name AS user_name, u.email
             FROM fashion_tenants t
             INNER JOIN fashion_users u ON u.tenant_id = t.id AND u.role = \'owner\'
             WHERE u.name = :name AND t.name LIKE :shop
             LIMIT 1'
        );
        $stmt->execute(['name' => 'Amina Bello', 'shop' => '%Stitches%']);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
    }
    if ($row === false) {
        demo_fail('Could not find Amina Bello / demo@gmingo.test shop. Run `php tools/seed.php` first.');
    }
    return $row;
}

/** @return list<string> */
function demo_customer_names(): array
{
    return [
        'Aisha Abdullahi', 'Fatima Yusuf', 'Hauwa Ibrahim', 'Zainab Mohammed', 'Maryam Sani',
        'Hadiza Bello', 'Rakiya Usman', 'Safiya Musa', 'Aminatu Lawan', 'Khadija Garba',
        'Adeola Ogunleye', 'Funmilayo Adeyemi', 'Bukola Adebayo', 'Yetunde Okonkwo', 'Tolu Akinola',
        'Chinedu Okafor', 'Ngozi Eze', 'Ifeanyi Nwosu', 'Chioma Okeke', 'Emeka Obi',
        'Olumide Balogun', 'Kemi Ajayi', 'Segun Afolabi', 'Bola Olatunji', 'Yemi Fashola',
        'Abubakar Danjuma', 'Suleiman Abubakar', 'Ibrahim Shehu', 'Usman Kabir', 'Bashir Aliyu',
        'Grace Okoro', 'Blessing Uche', 'Patience Ada', 'Favour Chukwu', 'Hope Anene',
        'Tunde Bakare', 'Nneka Ibe', 'Halima Mustapha', 'Sani Abdulkadir', 'Rita Ojo',
    ];
}

/** @return list<array{name:string,sku:string,price:int,garment:string}> */
function demo_product_specs(): array
{
    return [
        ['name' => 'Ankara Gown', 'sku' => 'DEMO-ANKARA-GOWN', 'price' => 2500000, 'garment' => 'gown'],
        ['name' => "Men's Kaftan", 'sku' => 'DEMO-MENS-KAFTAN', 'price' => 4500000, 'garment' => 'kaftan'],
        ['name' => 'Lace Dress', 'sku' => 'DEMO-LACE-DRESS', 'price' => 5500000, 'garment' => 'gown'],
        ['name' => 'Premium Shirt', 'sku' => 'DEMO-PREMIUM-SHIRT', 'price' => 1800000, 'garment' => 'shirt'],
        ['name' => 'Senator Wear', 'sku' => 'DEMO-SENATOR', 'price' => 6000000, 'garment' => 'senator'],
        ['name' => 'Agbada Set', 'sku' => 'DEMO-AGBADA-SET', 'price' => 12000000, 'garment' => 'agbada'],
        ['name' => 'Fila Cap', 'sku' => 'DEMO-FILA-CAP', 'price' => 800000, 'garment' => 'accessories'],
        ['name' => 'Plain Trouser', 'sku' => 'DEMO-TROUSER', 'price' => 1500000, 'garment' => 'trouser'],
        ['name' => 'Embroidered Kaftan', 'sku' => 'DEMO-EMB-KAFTAN', 'price' => 7500000, 'garment' => 'kaftan'],
        ['name' => 'Ready Agbada', 'sku' => 'DEMO-READY-AGBADA', 'price' => 9500000, 'garment' => 'agbada'],
        ['name' => 'Beaded Cap', 'sku' => 'DEMO-BEAD-CAP', 'price' => 1200000, 'garment' => 'accessories'],
        ['name' => 'Office Shirt', 'sku' => 'DEMO-OFFICE-SHIRT', 'price' => 2200000, 'garment' => 'shirt'],
        ['name' => 'Bridal Gown', 'sku' => 'DEMO-BRIDAL', 'price' => 11000000, 'garment' => 'gown'],
        ['name' => 'Kids Kaftan', 'sku' => 'DEMO-KIDS-KAFTAN', 'price' => 1600000, 'garment' => 'kaftan'],
        ['name' => 'Senator Trouser', 'sku' => 'DEMO-SEN-TROUSER', 'price' => 2800000, 'garment' => 'trouser'],
    ];
}

function demo_day_offset_for_index(int $i, int $total): int
{
    // Heavier recent weeks: map index to day offsets 0..119 with bias toward low offsets.
    if ($i < 5) {
        return $i % 2; // last 2 days
    }
    if ($i < 15) {
        return 2 + (($i - 5) % 5); // within last 7
    }
    if ($i < 35) {
        return 7 + (int) floor((($i - 15) / 20) * 22); // within last 30
    }
    $rest = $i - 35;
    $span = max(1, $total - 35);
    return 30 + (int) floor(($rest / $span) * 89);
}

demo_assert_local();
$settings = db_settings();
$shop = demo_find_shop();
$tenantId = (string) $shop['tenant_id'];
$userId = (string) $shop['user_id'];

// Bind identity before any CLI output so session boot stays quiet.
auth_boot();
$_SESSION['user_id'] = $userId;
$_SESSION['tenant_id'] = $tenantId;
auth_bind_tenant($tenantId);

echo 'Connected database: ' . $settings['name'] . ' @ ' . $settings['host'] . ':' . $settings['port'] . "\n";
echo 'Target shop: ' . $shop['shop_name'] . ' (' . $tenantId . ")\n";
echo 'Owner: ' . $shop['user_name'] . ' <' . $shop['email'] . ">\n";
echo "Note: there is no shop named \"Gmingo Fashion\"; using Amina Bello's tailor shop above.\n";

$existing = db_query(
    'SELECT COUNT(*) AS total FROM fashion_orders WHERE tenant_id = :tenant_id AND order_number LIKE :prefix',
    ['prefix' => DEMO_ORDER_PREFIX . '%']
);
if ((int) ($existing[0]['total'] ?? 0) > 0) {
    echo "Demo data already present (DEMO- orders found). Nothing to do.\n";
    echo "Run php database/remove_demo_data.php first if you want a fresh load.\n";
    exit(0);
}

$counts = [
    'customers' => 0,
    'garment_types' => 0,
    'products' => 0,
    'stock_items' => 0,
    'stock_movements' => 0,
    'orders' => 0,
    'order_items' => 0,
    'stage_events' => 0,
    'payments' => 0,
    'sales' => 0,
    'sale_lines' => 0,
    'work_orders' => 0,
    'expenses' => 0,
    'subscription_payments' => 0,
];

$today = DateTimeImmutable::createFromFormat('!Y-m-d', app_today()) ?: new DateTimeImmutable('today');

db_begin();
try {
    // Ensure senator is active; ensure Accessories + Cancelled stage exist.
    $senatorRows = db_select('fashion_garment_types', ['code' => 'senator'], ['limit' => 1]);
    if ($senatorRows !== []) {
        db_update('fashion_garment_types', ['active' => 1], ['id' => (string) $senatorRows[0]['id']]);
    }
    $neededGarments = [
        'kaftan' => 'Kaftan',
        'senator' => 'Senator',
        'trouser' => 'Trouser',
        'agbada' => 'Agbada',
        'accessories' => 'Accessories',
        'gown' => 'Gown',
        'shirt' => 'Shirt',
    ];
    $garmentIds = [];
    foreach ($neededGarments as $code => $name) {
        $found = db_select('fashion_garment_types', ['code' => $code], ['limit' => 1]);
        if ($found === []) {
            $garmentIds[$code] = db_insert('fashion_garment_types', [
                'code' => $code,
                'name' => $name,
                'sort_order' => 50,
                'active' => 1,
                'placeholder' => 0,
                'lead_days' => null,
            ]);
            $counts['garment_types']++;
        } else {
            $garmentIds[$code] = (string) $found[0]['id'];
            if ((int) $found[0]['active'] !== 1) {
                db_update('fashion_garment_types', ['active' => 1], ['id' => $garmentIds[$code]]);
            }
        }
    }

    $stageByCode = [];
    foreach (db_select('fashion_stage_definitions', [], ['order' => 'sort_order ASC', 'limit' => 100]) as $stage) {
        $stageByCode[(string) $stage['code']] = $stage;
    }
    if (!isset($stageByCode['cancelled'])) {
        $stageByCode['cancelled'] = [
            'id' => db_insert('fashion_stage_definitions', [
                'code' => 'cancelled',
                'name' => 'Cancelled',
                'sort_order' => 99,
                'active' => 0,
                'role' => 'finished',
            ]),
            'code' => 'cancelled',
            'role' => 'finished',
        ];
    }

    // Customers
    $customerIds = [];
    $phones = [];
    foreach (demo_customer_names() as $i => $name) {
        $phone = '0803' . str_pad((string) (9000000 + $i), 7, '0', STR_PAD_LEFT);
        $phones[] = $phone;
        $createdAt = $today->modify('-' . (10 + ($i * 3) % 110) . ' days')->setTime(9 + ($i % 8), ($i * 7) % 60);
        $customerIds[] = db_insert('fashion_customers', [
            'name' => $name . DEMO_NAME_SUFFIX,
            'phone' => $phone,
            'gender' => $i % 2 === 0 ? 'female' : 'male',
            'customer_type' => $i % 5 === 0 ? 'vip' : 'regular',
            'messages_opt_in' => 0,
            'created_at' => $createdAt->format('Y-m-d H:i:s'),
        ]);
        $counts['customers']++;
    }

    // Products + finished stock (4–5 low)
    $productIds = [];
    $stockIds = [];
    $lowStockIds = [];
    foreach (demo_product_specs() as $i => $spec) {
        $stockId = db_insert('fashion_stock_items', [
            'name' => $spec['name'] . ' ' . DEMO_MARK,
            'category' => $spec['garment'] === 'accessories' ? 'other' : 'fabric',
            'unit' => 'pieces',
            'colour' => null,
            'minimum_hundredths' => 1000, // 10 pieces
            'created_at' => $today->modify('-100 days')->format('Y-m-d H:i:s'),
            'kind' => 'finished',
            'unit_cost_kobo' => (int) floor($spec['price'] * 0.45),
        ]);
        $stockIds[] = $stockId;
        $counts['stock_items']++;

        $isLow = $i < 5;
        $qtyPieces = $isLow ? (2 + ($i % 3)) : (25 + ($i * 3));
        $purchaseAt = $today->modify('-' . (40 + $i) . ' days')->setTime(11, 0);
        db_insert('fashion_stock_movements', [
            'item_id' => $stockId,
            'change_hundredths' => $qtyPieces * 100,
            'reason' => 'purchase',
            'order_id' => null,
            'user_id' => $userId,
            'note' => DEMO_MARK,
            'created_at' => $purchaseAt->format('Y-m-d H:i:s'),
        ]);
        $counts['stock_movements']++;

        if ($isLow) {
            $lowStockIds[] = $stockId;
            // Recent movement so "N new" low-stock alerts fire in last 30 days.
            $saleAt = $today->modify('-' . (1 + ($i % 12)) . ' days')->setTime(15, 20 + $i);
            $drop = max(100, ($qtyPieces - 1) * 100); // leave below minimum
            if ($qtyPieces * 100 - $drop < 1000) {
                // already below after purchase if qty < 10
            }
            // Ensure on-hand ends below minimum: purchase then heavy sale.
            db_insert('fashion_stock_movements', [
                'item_id' => $stockId,
                'change_hundredths' => -max(100, (int) floor(($qtyPieces * 100) * 0.6)),
                'reason' => 'sale',
                'order_id' => null,
                'user_id' => $userId,
                'note' => DEMO_MARK,
                'created_at' => $saleAt->format('Y-m-d H:i:s'),
            ]);
            $counts['stock_movements']++;
        } else {
            // Occasional sale outs
            if ($i % 3 === 0) {
                db_insert('fashion_stock_movements', [
                    'item_id' => $stockId,
                    'change_hundredths' => -200,
                    'reason' => 'sale',
                    'order_id' => null,
                    'user_id' => $userId,
                    'note' => DEMO_MARK,
                    'created_at' => $today->modify('-' . (5 + $i) . ' days')->format('Y-m-d H:i:s'),
                ]);
                $counts['stock_movements']++;
            }
        }

        $now = app_now()->format('Y-m-d H:i:s');
        $productIds[] = db_insert('fashion_products', [
            'name' => $spec['name'],
            'sku' => $spec['sku'],
            'description' => 'Demo product for ' . $spec['garment'] . ' ' . DEMO_MARK,
            'price_kobo' => $spec['price'],
            'sizes_json' => '["S","M","L","XL"]',
            'colours_json' => '["Blue","White","Black"]',
            'active' => 1,
            'stock_item_id' => $stockId,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $counts['products']++;
    }

    $processingCodes = ['cutting', 'sewing', 'embroidery', 'finishing', 'fitting'];
    $orderIds = [];
    $completedOrderIds = [];

    for ($i = 0; $i < 150; $i++) {
        $dayOff = demo_day_offset_for_index($i, 150);
        $created = $today->modify('-' . $dayOff . ' days')->setTime(8 + ($i % 10), ($i * 11) % 60, $i % 60);
        // Spread statuses evenly across the whole set (not only older indices).
        $bucket = ($i * 37) % 100;
        if ($bucket < 20) {
            $status = 'pending';
        } elseif ($bucket < 45) {
            $status = 'processing';
        } elseif ($bucket < 90) {
            $status = 'completed';
        } else {
            $status = 'cancelled';
        }

        $customerId = $customerIds[$i % count($customerIds)];
        $urgent = ($i % 17 === 0) ? 1 : 0;
        $dueOffset = match ($status) {
            'completed' => -(($i % 5) + 1),
            'cancelled' => -(($i % 3) + 1),
            default => ($urgent ? -(($i % 4) + 1) : (($i % 10) + 2)),
        };
        // A few overdue unfinished: pending/processing with past due
        if (($status === 'pending' || $status === 'processing') && ($i % 11 === 0)) {
            $dueOffset = -(($i % 6) + 2);
        }
        $due = $created->modify(($dueOffset >= 0 ? '+' : '') . $dueOffset . ' days')->format('Y-m-d');

        $orderNumber = DEMO_ORDER_PREFIX . str_pad((string) ($i + 1), 4, '0', STR_PAD_LEFT);
        $orderId = db_insert('fashion_orders', [
            'customer_id' => $customerId,
            'order_number' => $orderNumber,
            'due_date' => $due,
            'urgent' => $urgent,
            'group_id' => null,
            'assigned_user_id' => null,
            'customer_supplied_fabric' => 0,
            'yards_received' => null,
            'yards_used' => null,
            'created_at' => $created->format('Y-m-d H:i:s'),
        ]);
        $orderIds[] = $orderId;
        $counts['orders']++;

        $itemCount = 1 + ($i % 4);
        $lineTotal = 0;
        for ($j = 0; $j < $itemCount; $j++) {
            $spec = demo_product_specs()[($i + $j) % count(demo_product_specs())];
            $garmentCode = $spec['garment'];
            if (!isset($garmentIds[$garmentCode])) {
                $garmentCode = 'kaftan';
            }
            $qty = 1 + (($i + $j) % 2);
            $price = $spec['price'];
            db_insert('fashion_order_items', [
                'order_id' => $orderId,
                'garment_type_id' => $garmentIds[$garmentCode],
                'quantity' => $qty,
                'price_kobo' => $price,
                'fabric' => ['ankara', 'lace', 'guinea', 'atiku', 'silk'][($i + $j) % 5],
                'notes' => DEMO_MARK,
            ]);
            $lineTotal += $qty * $price;
            $counts['order_items']++;
        }

        $stageCode = match ($status) {
            'pending' => 'new',
            'processing' => $processingCodes[$i % count($processingCodes)],
            'completed' => ($i % 2 === 0 ? 'delivered' : 'ready'),
            'cancelled' => 'cancelled',
        };
        $stageId = (string) ($stageByCode[$stageCode]['id'] ?? $stageByCode['new']['id']);
        // Seed path: new -> optional intermediates -> final
        $path = ['new'];
        if ($status === 'processing') {
            $path[] = $stageCode;
        } elseif ($status === 'completed') {
            $path = array_merge($path, ['cutting', 'sewing', $stageCode]);
        } elseif ($status === 'cancelled') {
            $path[] = 'cancelled';
        }
        $seqTime = $created;
        foreach (array_values(array_unique($path)) as $pi => $code) {
            if (!isset($stageByCode[$code])) {
                continue;
            }
            $seqTime = $created->modify('+' . ($pi * 2) . ' hours');
            db_insert('fashion_stage_events', [
                'order_id' => $orderId,
                'stage_id' => (string) $stageByCode[$code]['id'],
                'user_id' => $userId,
                'note' => DEMO_MARK,
                'created_at' => $seqTime->format('Y-m-d H:i:s'),
            ]);
            $counts['stage_events']++;
        }

        if ($status === 'completed' || ($status === 'processing' && $i % 3 === 0)) {
            $payFraction = $status === 'completed'
                ? (($i % 4 === 0) ? 0.5 : 1.0) // some outstanding on completed
                : 0.3;
            $payKobo = (int) floor($lineTotal * $payFraction);
            if ($payKobo > 0) {
                $payAt = $seqTime->modify('+1 hour');
                $ref = 'PS-' . $payAt->format('Ymd') . '-' . str_pad((string) (1000 + $i), 4, '0', STR_PAD_LEFT);
                db_insert('fashion_payments', [
                    'order_id' => $orderId,
                    'amount_kobo' => $payKobo,
                    'method' => $i % 2 === 0 ? 'transfer' : 'cash',
                    'reverses_payment_id' => null,
                    'kind' => 'payment',
                    'user_id' => $userId,
                    'created_at' => $payAt->format('Y-m-d H:i:s'),
                    'provider' => 'paystack',
                    'provider_reference' => $ref,
                    'source' => 'staff',
                    'reason' => DEMO_MARK,
                ]);
                $counts['payments']++;
            }
        }
        if ($status === 'completed') {
            $completedOrderIds[] = $orderId;
        }
    }

    // Retail sales so Top Selling Products prefers product names
    $saleSpecs = demo_product_specs();
    for ($s = 0; $s < 40; $s++) {
        $dayOff = $s < 8 ? ($s % 7) : (7 + (($s * 3) % 100));
        $created = $today->modify('-' . $dayOff . ' days')->setTime(12 + ($s % 6), ($s * 5) % 60);
        $saleNumber = DEMO_SALE_PREFIX . str_pad((string) ($s + 1), 4, '0', STR_PAD_LEFT);
        $lines = 1 + ($s % 3);
        $total = 0;
        $lineData = [];
        for ($l = 0; $l < $lines; $l++) {
            $spec = $saleSpecs[($s + $l) % count($saleSpecs)];
            $qty = 1 + ($l % 2);
            $lineTotal = $qty * $spec['price'];
            $total += $lineTotal;
            $lineData[] = [$productIds[($s + $l) % count($productIds)], $qty, $spec['price'], $lineTotal];
        }
        $saleId = db_insert('fashion_sales', [
            'sale_number' => $saleNumber,
            'customer_id' => $customerIds[$s % count($customerIds)],
            'channel' => 'retail',
            'status' => 'completed',
            'total_kobo' => $total,
            'note' => DEMO_MARK,
            'created_by' => $userId,
            'created_at' => $created->format('Y-m-d H:i:s'),
        ]);
        $counts['sales']++;
        foreach ($lineData as $line) {
            db_insert('fashion_sale_lines', [
                'sale_id' => $saleId,
                'product_id' => $line[0],
                'quantity' => $line[1],
                'unit_price_kobo' => $line[2],
                'line_total_kobo' => $line[3],
                'size_label' => 'M',
                'colour_label' => 'Blue',
            ]);
            $counts['sale_lines']++;
        }
    }

    // Work orders
    $woStatuses = [
        ['Cutting batch — kaftans', 'in_progress'],
        ['Sewing senator sets', 'in_progress'],
        ['Finishing agbada', 'planned'],
        ['Cutting trouser lot', 'planned'],
        ['Sewing bridal gown', 'in_progress'],
        ['Finishing caps', 'done'],
        ['Cutting shirt run', 'planned'],
        ['Sewing kids kaftan', 'in_progress'],
        ['Finishing senator', 'done'],
        ['Cutting accessories pack', 'cancelled'],
    ];
    foreach ($woStatuses as $wi => [$title, $status]) {
        db_insert('fashion_work_orders', [
            'product_id' => $productIds[$wi % count($productIds)],
            'title' => $title,
            'quantity' => 5 + $wi,
            'status' => $status,
            'due_on' => $today->modify('+' . (($wi % 5) + 1) . ' days')->format('Y-m-d'),
            'assigned_user_id' => $userId,
            'note' => DEMO_MARK,
            'created_by' => $userId,
            'created_at' => $today->modify('-' . (3 + $wi) . ' days')->format('Y-m-d H:i:s'),
        ]);
        $counts['work_orders']++;
    }

    // Expenses
    $expenses = [
        ['fabric', 8500000, 'Ankara bolt purchase'],
        ['thread', 450000, 'Assorted thread cones'],
        ['transport', 1200000, 'Delivery to customers'],
        ['rent', 15000000, 'Shop rent'],
        ['fabric', 6200000, 'Lace yardage'],
        ['transport', 800000, 'Market run'],
        ['thread', 300000, 'Gold embroidery thread'],
        ['rent', 15000000, 'Shop rent'],
    ];
    foreach ($expenses as $ei => [$cat, $amount, $note]) {
        $spent = $today->modify('-' . (5 + $ei * 12) . ' days')->format('Y-m-d');
        db_insert('fashion_expenses', [
            'order_id' => null,
            'category' => $cat,
            'amount_kobo' => $amount,
            'note' => $note . ' ' . DEMO_MARK,
            'spent_on' => $spent,
            'created_by' => $userId,
            'created_at' => $spent . ' 10:00:00',
        ]);
        $counts['expenses']++;
    }

    // Subscription payments (Starter ₦15,000)
    $subs = db_select('fashion_subscriptions', [], ['limit' => 1]);
    if ($subs !== []) {
        $subId = (string) $subs[0]['id'];
        $refs = [
            ['PS-20251028-7843', 90],
            ['PS-' . $today->modify('-60 days')->format('Ymd') . '-5521', 60],
            ['PS-' . $today->modify('-30 days')->format('Ymd') . '-3310', 30],
        ];
        foreach ($refs as [$ref, $daysAgo]) {
            $paidAt = $today->modify('-' . $daysAgo . ' days')->setTime(14, 22);
            $existsStmt = db()->prepare(
                'SELECT id FROM fashion_subscription_payments WHERE provider = :p AND provider_reference = :r LIMIT 1'
            );
            $existsStmt->execute(['p' => 'paystack', 'r' => $ref]);
            if ($existsStmt->fetch() !== false) {
                continue;
            }
            db_insert('fashion_subscription_payments', [
                'subscription_id' => $subId,
                'amount_kobo' => 1500000,
                'currency' => 'NGN',
                'provider' => 'paystack',
                'provider_reference' => $ref,
                'status' => 'succeeded',
                'paid_at' => $paidAt->format('Y-m-d H:i:s'),
                'metadata_json' => json_encode(['demo_seed' => true, 'plan' => 'Starter'], JSON_UNESCAPED_UNICODE),
                'created_at' => $paidAt->format('Y-m-d H:i:s'),
            ]);
            $counts['subscription_payments']++;
        }
    }

    db_commit();
} catch (Throwable $e) {
    db_rollback();
    demo_fail('Seed failed and was rolled back: ' . $e->getMessage());
}

// Verify dashboard-ish totals for last 30 days
$start30 = $today->modify('-29 days')->format('Y-m-d');
$end30 = $today->format('Y-m-d');
[$startSql, $endSql] = [$start30 . ' 00:00:00', $end30 . ' 23:59:59'];

$rev = db_query(
    'SELECT COALESCE(SUM(CASE WHEN reverses_payment_id IS NULL THEN amount_kobo ELSE -amount_kobo END), 0) AS total
     FROM fashion_payments
     WHERE tenant_id = :tenant_id AND created_at >= :a AND created_at <= :b',
    ['a' => $startSql, 'b' => $endSql]
);
$ord = db_query(
    'SELECT COUNT(*) AS total FROM fashion_orders
     WHERE tenant_id = :tenant_id AND created_at >= :a AND created_at <= :b',
    ['a' => $startSql, 'b' => $endSql]
);
$cust = db_query(
    'SELECT COUNT(DISTINCT customer_id) AS total FROM fashion_orders
     WHERE tenant_id = :tenant_id AND created_at >= :a AND created_at <= :b',
    ['a' => $startSql, 'b' => $endSql]
);
$low = db_query(
    'SELECT COUNT(*) AS total FROM fashion_stock_items i
     WHERE i.tenant_id = :tenant_id
       AND i.name LIKE :mark
       AND i.minimum_hundredths IS NOT NULL
       AND COALESCE((SELECT SUM(m.change_hundredths) FROM fashion_stock_movements m
                     WHERE m.tenant_id = :tenant_id AND m.item_id = i.id), 0) <= i.minimum_hundredths',
    ['mark' => '%' . DEMO_MARK . '%']
);

echo "\nRows added:\n";
echo str_pad('table', 28) . "count\n";
echo str_repeat('-', 36) . "\n";
foreach ($counts as $table => $n) {
    echo str_pad($table, 28) . $n . "\n";
}
echo "\nLast 30 days (this shop):\n";
echo '  revenue_kobo: ' . (int) ($rev[0]['total'] ?? 0) . ' (' . money((int) ($rev[0]['total'] ?? 0)) . ")\n";
echo '  orders: ' . (int) ($ord[0]['total'] ?? 0) . "\n";
echo '  active_customers: ' . (int) ($cust[0]['total'] ?? 0) . "\n";
echo '  low_stock: ' . (int) ($low[0]['total'] ?? 0) . "\n";
echo "\nDone. Sign in as demo@gmingo.test / password to view the dashboard.\n";
echo "Clear later with: php database/remove_demo_data.php\n";
