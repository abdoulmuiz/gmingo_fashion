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
    return ($config['environment'] ?? null) === 'local';
}

function seed_refusal_message(): string
{
    return "Refusing to load demo data. This seed only runs when environment is local in config/config.php.\n";
}

function seed_demo(): array
{
    $email = 'demo@gmingo.test';
    $password = 'password';
    $login = login_user($email, $password);
    if ($login === 'rate_limited') {
        throw new RuntimeException('Too many sign-in tries for the demo shop. Wait five minutes and run this again.');
    }
    if ($login === null) {
        $customers = db_select('fashion_customers', [], ['limit' => 20]);
        $orders = db_select('fashion_orders', [], ['limit' => 50]);
        if (count($customers) === 10 && count($orders) === 15) {
            return [
                'status' => 'exists',
                'user_id' => (string) current_user_id(),
                'tenant_id' => (string) current_tenant_id(),
            ];
        }
        if ($customers !== [] || $orders !== []) {
            throw new RuntimeException('The demo shop is only partly set up. Remove that shop from the database and run this again.');
        }
        seed_demo_fill();
        return [
            'status' => 'created',
            'user_id' => (string) current_user_id(),
            'tenant_id' => (string) current_tenant_id(),
        ];
    }

    $result = business_register([
        'business_name' => 'Kano Stitches Tailoring',
        'owner_name' => 'Amina Bello',
        'email' => $email,
        'password' => $password,
        'phone' => '08039876010',
        'company_type' => 'tailor',
    ]);
    if (isset($result['errors'])) {
        $emailError = (string) ($result['errors']['email'] ?? '');
        if ($emailError === 'email_taken') {
            throw new RuntimeException('The demo email is already used with a different password.');
        }
        throw new RuntimeException('Could not create the demo shop.');
    }
    seed_demo_fill();
    return [
        'status' => 'created',
        'user_id' => (string) $result['user_id'],
        'tenant_id' => (string) $result['tenant_id'],
    ];
}

function seed_demo_fill(): void
{
    $garments = [];
    foreach (db_select('fashion_garment_types', ['active' => 1], ['order' => 'sort_order ASC', 'limit' => 20]) as $row) {
        $garments[(string) $row['code']] = (string) $row['id'];
    }
    $kaftan = $garments['kaftan'] ?? '';
    if ($kaftan === '') {
        throw new RuntimeException('Could not find the tailor garment types for the demo shop.');
    }
    $codes = ['kaftan', 'senator', 'agbada', 'shirt', 'trouser', 'gown'];
    $people = [
        ['Amina Yusuf', '08031110001', 'female', 'vip'],
        ['Hauwa Sani', '08031110002', 'female', 'regular'],
        ['Musa Danjuma', '08031110003', 'male', 'regular'],
        ['Fatima Bello', '08031110004', 'female', 'vip'],
        ['Ibrahim Lawal', '08031110005', 'male', 'regular'],
        ['Zainab Musa', '08031110006', 'female', 'regular'],
        ['Sani Garba', '08031110007', 'male', 'vip'],
        ['Maryam Abdullahi', '08031110008', 'female', 'regular'],
        ['Yusuf Danladi', '08031110009', 'male', 'regular'],
        ['Khadija Umar', '08031110010', 'female', 'regular'],
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

    $moves = [0, 1, 2, 3, 4, 5, 6, 7, 0, 1, 2, 3, 4, 5, 6];
    $offsets = [-10, -3, -1, 0, 1, 2, 4, 7, -8, 3, 5, 6, 8, 10, 14];
    $prices = [8000, 12000, 15000, 10000, 20000, 9000, 18000, 25000, 7000, 11000, 16000, 14000, 22000, 6000, 13000];
    $fabrics = ['ankara', 'atiku', 'guinea', 'lace', 'cotton', 'silk'];
    $today = new DateTimeImmutable('today');
    $orderIds = [];
    foreach ($moves as $index => $steps) {
        $due = $today->modify(($offsets[$index] >= 0 ? '+' : '') . $offsets[$index] . ' days')->format('Y-m-d');
        $code = $codes[$index % count($codes)];
        $created = order_create([
            'customer_id' => $customerIds[$index % count($customerIds)],
            'due_date' => $due,
            'urgent' => $offsets[$index] < 0 && $steps < 7 && $index < 2 ? '1' : '',
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
        if ($index % 2 === 0) {
            $amount = $index % 4 === 0 ? $prices[$index] : intdiv($prices[$index], 2);
            $paid = payment_record($orderId, (string) $amount, $index % 4 === 0 ? 'cash' : 'transfer');
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
    for ($i = 0; $i < 8; $i++) {
        $values = $fields;
        $values['neck'] = (string) (15 + $i);
        $saved = measurement_save($customerIds[$i], $kaftan, 'inches', $values);
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
