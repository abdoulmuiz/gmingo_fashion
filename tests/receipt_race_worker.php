<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once dirname(__DIR__) . '/app/domain/payments.php';

$tenant = $argv[1] ?? '';
$user = $argv[2] ?? '';
$order = $argv[3] ?? '';
$start = (float) ($argv[4] ?? 0);
if (!is_uuid($tenant) || !is_uuid($user) || !is_uuid($order)) {
    fwrite(STDERR, "missing race arguments\n");
    exit(1);
}
auth_login($user, $tenant);
if ($start > microtime(true)) {
    time_sleep_until($start);
}
$result = payment_record($order, '10', 'cash');
if (!isset($result['receipt_number'])) {
    fwrite(STDERR, "receipt was not created\n");
    exit(1);
}
echo $result['receipt_number'] . "\n";
