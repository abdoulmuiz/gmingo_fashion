<?php

declare(strict_types=1);

require_once __DIR__ . '/accounts.php';
require_once __DIR__ . '/customers.php';
require_once __DIR__ . '/orders.php';
require_once __DIR__ . '/payments.php';
require_once __DIR__ . '/measurements.php';
require_once __DIR__ . '/stages.php';

function seed_is_local(array $config): bool
{
    $environment = $config['environment'] ?? null;
    return $environment === 'local' || $environment === 'dev';
}

function seed_password(): string
{
    return 'password';
}

function seed_refusal_message(): string
{
    return "Refusing to load demo data. This seed only runs when environment is local in config/config.php.\n";
}

function seed_demo(): array
{
    $created = seed_company([
        'business_name' => 'Demo Factory',
        'owner_name' => 'Demo Factory Owner',
        'email' => 'factory@gmingo.test',
        'phone' => '08090000011',
        'company_type' => 'factory',
        'staff_email' => 'factory-staff@gmingo.test',
        'staff_name' => 'Demo Factory Staff',
        'fill' => 'factory',
    ]);
    $created = seed_company([
        'business_name' => 'Demo Brand',
        'owner_name' => 'Demo Brand Owner',
        'email' => 'brand@gmingo.test',
        'phone' => '08090000012',
        'company_type' => 'brand',
        'staff_email' => 'brand-staff@gmingo.test',
        'staff_name' => 'Demo Brand Staff',
        'fill' => 'brand',
    ]) || $created;
    $created = seed_company([
        'business_name' => 'Kano Stitches Tailoring',
        'owner_name' => 'Amina Bello',
        'email' => 'demo@gmingo.test',
        'phone' => '08039876010',
        'company_type' => 'tailor',
        'staff_email' => 'tailor-staff@gmingo.test',
        'staff_name' => 'Demo Tailor Staff',
        'fill' => 'tailor',
    ]) || $created;
    return [
        'status' => $created ? 'created' : 'exists',
        'user_id' => (string) current_user_id(),
        'tenant_id' => (string) current_tenant_id(),
    ];
}

function seed_company(array $spec): bool
{
    $email = (string) $spec['email'];
    $login = login_user($email, seed_password());
    if ($login === 'rate_limited') {
        throw new RuntimeException('Too many sign-in tries for the demo shop. Wait five minutes and run this again.');
    }
    $created = false;
    if ($login !== null) {
        $result = business_register([
            'business_name' => (string) $spec['business_name'],
            'owner_name' => (string) $spec['owner_name'],
            'email' => $email,
            'password' => seed_password(),
            'phone' => (string) $spec['phone'],
            'company_type' => (string) $spec['company_type'],
        ]);
        if (isset($result['errors'])) {
            $emailError = (string) ($result['errors']['email'] ?? '');
            if ($emailError === 'email_taken') {
                throw new RuntimeException('The demo email is already used with a different password.');
            }
            throw new RuntimeException('Could not create the demo shop.');
        }
        $created = true;
    }
    seed_staff((string) $spec['staff_email'], (string) $spec['staff_name']);
    if ($spec['fill'] === 'tailor') {
        $created = seed_tailor_sample() || $created;
    } elseif ($spec['fill'] === 'factory') {
        $created = seed_factory_sample() || $created;
    } elseif ($spec['fill'] === 'brand') {
        $created = seed_brand_sample() || $created;
    }
    return $created;
}

function seed_staff(string $email, string $name): void
{
    if (db_select('fashion_users', ['email' => $email], ['limit' => 1]) !== []) {
        return;
    }
    db_insert('fashion_users', [
        'name' => $name,
        'email' => $email,
        'password_hash' => password_hash(seed_password(), PASSWORD_DEFAULT),
        'role' => 'staff',
        'lang' => 'en',
        'active' => 1,
        'created_at' => date('Y-m-d H:i:s'),
    ]);
}

function seed_tailor_sample(): bool
{
    $customers = db_select('fashion_customers', [], ['limit' => 50]);
    $orders = db_select('fashion_orders', [], ['limit' => 50]);
    if ($customers !== [] || $orders !== []) {
        return false;
    }
    seed_demo_fill();
    return true;
}

function seed_factory_sample(): bool
{
    foreach (stage_definitions() as $stage) {
        if ((string) $stage['name'] === 'Sample line') {
            return false;
        }
    }
    $added = stage_add('Sample line');
    if (isset($added['error'])) {
        throw new RuntimeException('Could not save a demo production stage.');
    }
    return true;
}

function seed_brand_sample(): bool
{
    $customers = db_select('fashion_customers', [], ['limit' => 50]);
    $orders = db_select('fashion_orders', [], ['limit' => 50]);
    if ($customers !== [] || $orders !== []) {
        return false;
    }
    $top = seed_brand_garment('sample_top', 'Sample top', 1);
    $trouser = seed_brand_garment('sample_trouser', 'Sample trouser', 2);
    $people = [
        ['Demo Buyer 1', '08020000001', 'female', 'regular'],
        ['Demo Buyer 2', '08020000002', 'male', 'vip'],
        ['Demo Buyer 3', '08020000003', 'female', 'regular'],
    ];
    $customerIds = [];
    foreach ($people as $person) {
        $created = customer_create([
            'name' => $person[0],
            'phone' => $person[1],
            'gender' => $person[2],
            'customer_type' => $person[3],
        ]);
        if (isset($created['errors'])) {
            throw new RuntimeException('Could not save a demo customer.');
        }
        $customerIds[] = (string) $created['id'];
    }
    $today = new DateTimeImmutable('today');
    $plan = [
        [$customerIds[0], $top, '-3 days', '9000', '4000'],
        [$customerIds[1], $trouser, '+2 days', '12000', '12000'],
        [$customerIds[2], $top, '+6 days', '7000', ''],
        [$customerIds[0], $trouser, '+10 days', '15000', '5000'],
    ];
    foreach ($plan as $row) {
        $created = order_create([
            'customer_id' => $row[0],
            'due_date' => $today->modify($row[2])->format('Y-m-d'),
            'urgent' => '',
            'items' => [[
                'garment_type_id' => $row[1],
                'quantity' => '1',
                'price' => $row[3],
                'fabric' => 'sample cloth',
                'notes' => '',
            ]],
        ]);
        if (isset($created['errors'])) {
            throw new RuntimeException('Could not save a demo order.');
        }
        if ($row[4] !== '') {
            $paid = payment_record((string) $created['id'], $row[4], 'transfer');
            if (isset($paid['error'])) {
                throw new RuntimeException('Could not record a demo payment.');
            }
        }
    }
    return true;
}

function seed_brand_garment(string $code, string $name, int $sort): string
{
    $rows = db_select('fashion_garment_types', ['code' => $code], ['limit' => 1]);
    if ($rows !== []) {
        return (string) $rows[0]['id'];
    }
    return db_insert('fashion_garment_types', [
        'code' => $code,
        'name' => $name,
        'sort_order' => $sort,
        'active' => 1,
        'placeholder' => 0,
        'lead_days' => null,
    ]);
}

function seed_demo_fill(): void
{
    $garments = [];
    foreach (db_select('fashion_garment_types', ['active' => 1], ['order' => 'sort_order ASC', 'limit' => 20]) as $row) {
        $garments[(string) $row['code']] = (string) $row['id'];
    }
    $kaftan = $garments['kaftan'] ?? '';
    $senator = $garments['senator'] ?? '';
    if ($kaftan === '' || $senator === '') {
        throw new RuntimeException('Could not find the tailor garment types for the demo shop.');
    }
    $codes = ['kaftan', 'senator', 'agbada', 'shirt', 'trouser', 'gown', 'kaftan', 'senator'];
    $people = [
        ['Demo Customer 1', '08010000001', 'female', 'vip'],
        ['Demo Customer 2', '08010000002', 'male', 'regular'],
        ['Demo Customer 3', '08010000003', 'female', 'regular'],
        ['Demo Customer 4', '08010000004', 'male', 'vip'],
        ['Demo Customer 5', '08010000005', 'female', 'regular'],
    ];
    $customerIds = [];
    foreach ($people as $person) {
        $created = customer_create([
            'name' => $person[0],
            'phone' => $person[1],
            'gender' => $person[2],
            'customer_type' => $person[3],
        ]);
        if (isset($created['errors'])) {
            throw new RuntimeException('Could not save a demo customer.');
        }
        $customerIds[] = (string) $created['id'];
    }

    $moves = [0, 1, 2, 3, 4, 5, 6, 7];
    $offsets = [-4, 0, 1, 2, 4, 6, 9, 14];
    $prices = [8000, 12000, 10000, 16000, 9000, 14000, 11000, 20000];
    $fabrics = ['ankara', 'atiku', 'guinea', 'lace', 'cotton', 'silk'];
    $today = new DateTimeImmutable('today');
    $orderIds = [];
    foreach ($moves as $index => $steps) {
        $due = $today->modify(($offsets[$index] >= 0 ? '+' : '') . $offsets[$index] . ' days')->format('Y-m-d');
        $code = $codes[$index % count($codes)];
        $created = order_create([
            'customer_id' => $customerIds[$index % count($customerIds)],
            'due_date' => $due,
            'urgent' => $offsets[$index] < 0 ? '1' : '',
            'items' => [[
                'garment_type_id' => $garments[$code],
                'quantity' => '1',
                'price' => (string) $prices[$index],
                'fabric' => $fabrics[$index % count($fabrics)],
                'notes' => '',
            ]],
        ]);
        if (isset($created['errors'])) {
            throw new RuntimeException('Could not save a demo order.');
        }
        $orderId = (string) $created['id'];
        for ($step = 0; $step < $steps; $step++) {
            $moved = stage_advance($orderId);
            if (isset($moved['error'])) {
                throw new RuntimeException('Could not move a demo order to the next stage.');
            }
        }
        if ($index < 4) {
            $amount = $index === 3 ? $prices[$index] : intdiv($prices[$index], 2);
            $paid = payment_record($orderId, (string) $amount, $index === 3 ? 'cash' : 'transfer');
            if (isset($paid['error'])) {
                throw new RuntimeException('Could not record a demo payment.');
            }
        }
        $orderIds[] = $orderId;
    }

    $fields = [
        'neck' => '16',
        'shoulder' => '18',
        'chest' => '40',
        'stomach' => '38',
        'sleeve_length' => '24',
        'round_sleeve' => '16',
        'cuff' => '9',
        'top_length' => '50',
    ];
    $senatorFields = [
        'neck' => '16',
        'chest' => '40',
        'shoulder' => '18',
        'sleeve_length' => '25',
        'length' => '42',
    ];
    foreach ([$customerIds[0], $customerIds[1]] as $customerId) {
        $saved = measurement_save($customerId, $kaftan, 'inches', $fields);
        if (isset($saved['error'])) {
            throw new RuntimeException('Could not save a demo measurement.');
        }
        $saved = measurement_save($customerId, $senator, 'inches', $senatorFields);
        if (isset($saved['error'])) {
            throw new RuntimeException('Could not save a demo measurement.');
        }
    }
    $updated = $fields;
    $updated['neck'] = '16.5';
    $again = measurement_save($customerIds[0], $kaftan, 'cm', $updated);
    if (isset($again['error'])) {
        throw new RuntimeException('Could not save a later demo measurement.');
    }
    if ($orderIds === []) {
        throw new RuntimeException('Could not save a demo order.');
    }
}
