<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__) . '/app/domain/orders.php';

$tenant = $argv[1] ?? '';
$user = $argv[2] ?? '';
$customer = $argv[3] ?? '';
$garment = $argv[4] ?? '';
$start = (float) ($argv[5] ?? 0);
if (!is_uuid($tenant) || !is_uuid($user) || !is_uuid($customer) || !is_uuid($garment)) {
    fwrite(STDERR, "missing race arguments\n");
    exit(1);
}
auth_login($user, $tenant);
if ($start > microtime(true)) {
    time_sleep_until($start);
}
$result = order_create([
    'customer_id' => $customer,
    'due_date' => date('Y-m-d', strtotime('+14 days')),
    'urgent' => '',
    'items' => [[
        'garment_type_id' => $garment,
        'quantity' => '1',
        'price' => '1000',
        'fabric' => 'race',
        'notes' => '',
        'product_id' => 'ignored',
    ]],
]);
if (!isset($result['order_number'])) {
    fwrite(STDERR, "order was not created\n");
    exit(1);
}
echo $result['order_number'] . "\n";
