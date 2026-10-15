<?php

declare(strict_types=1);

require_once __DIR__ . '/support.php';
require_once dirname(__DIR__) . '/app/domain/accounts.php';
require_once dirname(__DIR__) . '/app/domain/customers.php';
require_once dirname(__DIR__) . '/app/domain/orders.php';
require_once dirname(__DIR__) . '/app/domain/payments.php';
require_once dirname(__DIR__) . '/app/domain/payment_settings.php';
require_once dirname(__DIR__) . '/app/payments/ManualProvider.php';
require_once dirname(__DIR__) . '/app/payments/PaystackClient.php';

$connected = db()->query('SELECT DATABASE()')->fetchColumn();
if (!is_string($connected) || !str_ends_with($connected, '_test')) {
    fwrite(STDERR, "Refusing to run payment settings tests: database name must end with _test.\n");
    exit(1);
}

tests_reset();

$paymentDomain = (string) (app_config()['payment_placeholder_domain'] ?? '');
$exampleConfig = (string) file_get_contents(dirname(__DIR__) . '/config/config.example.php');
$readme = (string) file_get_contents(dirname(__DIR__) . '/README.md');
check(str_contains($exampleConfig, 'payment_placeholder_domain'), 'the example config names the payment placeholder domain');
check(str_contains($exampleConfig, 'Paystack mode cannot be switched on while this is empty'), 'the example config explains the empty domain');
check(str_contains($readme, 'payment_placeholder_domain'), 'the readme names the payment placeholder domain');

function payment_test_domain(string $domain): void
{
    $cfg = app_config();
    $cfg['payment_placeholder_domain'] = $domain;
    $GLOBALS['gmingo_app_config'] = $cfg;
}

$secret = 'sk_test_DoNotStoreThis1234';
$public = 'pk_test_ShopPublicKey';

$manual = new ManualProvider();
check($manual->createPaymentLink('order', 1000)['ok'] === false, 'manual mode does not create a payment link');
check($manual->verifyPayment('ref')['ok'] === false, 'manual mode does not verify a Paystack payment');
check($manual->parseWebhook('{}', 'sig')['ok'] === false, 'manual mode does not read a Paystack webhook');

$shop = business_register([
    'business_name' => 'Mode Shop',
    'owner_name' => 'Amina Bello',
    'email' => 'paymode@example.com',
    'password' => 'password',
    'phone' => '08031110001',
    'company_type' => 'tailor',
]);
check(isset($shop['tenant_id']), 'payment settings start from a signed-in shop');
check(module_enabled('fashion.online_payments') === false, 'online payments are off until the shop turns them on');
check_eq(payment_mode(), 'manual', 'the default payment mode is manual');
check(payment_links_enabled() === false, 'manual mode does not create payment links');
check(db_select('fashion_payment_settings') === [], 'the default mode does not need a saved row');

$column = db()->prepare(
    'SELECT COLUMN_DEFAULT, IS_NULLABLE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table_name AND COLUMN_NAME = :column_name'
);
$column->execute(['table_name' => 'fashion_payments', 'column_name' => 'provider']);
$providerColumn = $column->fetch();
check(is_array($providerColumn) && trim((string) $providerColumn['COLUMN_DEFAULT'], "'") === 'manual', 'existing payments become manual');
$column->execute(['table_name' => 'fashion_payments', 'column_name' => 'source']);
$sourceColumn = $column->fetch();
check(is_array($sourceColumn) && trim((string) $sourceColumn['COLUMN_DEFAULT'], "'") === 'staff', 'existing payments become staff entries');
$column->execute(['table_name' => 'fashion_payments', 'column_name' => 'provider_reference']);
$referenceColumn = $column->fetch();
check(is_array($referenceColumn) && $referenceColumn['IS_NULLABLE'] === 'YES', 'a manual payment can omit a provider reference');

$_SERVER['REQUEST_URI'] = base_path() . '/settings';
ob_start();
settings_form();
$modulePage = (string) ob_get_clean();
check(str_contains($modulePage, 'value="fashion.online_payments"'), 'online payments appear on the module settings page');
check(!str_contains($modulePage, 'value="fashion.online_payments" checked'), 'online payments start unticked');
check(str_contains($modulePage, url('/settings/payments')), 'finance shows the payment settings page');

try {
    require_module('fashion.online_payments');
    check(false, 'online payments stay closed while the switch is off');
} catch (HttpException $e) {
    check($e->status() === 403, 'online payments stay closed while the switch is off');
}

$refused = payment_settings_save('paystack', $public, $secret);
check_eq($refused['error'] ?? '', 'online_payments_off', 'paystack mode is refused when online payments are off');
check_eq(payment_mode(), 'manual', 'a refused Paystack choice stays on manual');

module_save([
    'fashion.orders',
    'fashion.production',
    'fashion.finance',
    'fashion.measurements',
    'fashion.communication',
    'fashion.online_payments',
]);
check(module_enabled('fashion.online_payments') === true, 'the owner can turn online payments on');
require_module('fashion.online_payments');
check(true, 'online payments open when finance is on');

payment_test_domain('');
$blocked = payment_settings_save('paystack', $public, $secret);
check_eq($blocked['error'] ?? '', 'payment_domain_missing', 'paystack mode is refused while the placeholder domain is empty');
check_eq(payment_mode(), 'manual', 'an empty placeholder domain leaves the shop on manual');
payment_test_domain('   ');
check_eq(payment_settings_save('paystack', $public, $secret)['error'] ?? '', 'payment_domain_missing', 'a blank placeholder domain does not switch on Paystack');
$_SERVER['REQUEST_URI'] = base_path() . '/settings/payments';
ob_start();
payment_settings_form();
$domainPage = (string) ob_get_clean();
check(str_contains($domainPage, t('payment_domain_missing')), 'the payment page explains the missing domain');

payment_test_domain('https://pay.example.test');
$saved = payment_settings_save('paystack', $public, $secret);
check(isset($saved['ok']), 'the owner can choose Paystack');
check_eq(payment_mode(), 'paystack', 'the shop is in Paystack mode');
check(payment_links_enabled() === true, 'Paystack mode can create payment links');

$stored = db_select('fashion_payment_settings', [], ['limit' => 1])[0];
$encrypted = (string) $stored['paystack_secret_key_encrypted'];
check($encrypted !== '' && !str_contains($encrypted, $secret), 'the secret key is stored encrypted');
check(!str_contains(json_encode($stored), $secret), 'the raw key never appears in the payment settings row');
check(PaystackClient::open($encrypted)->matches($secret) === true, 'the Paystack client can read its own sealed key');
check(PaystackClient::open($encrypted)->matches('sk_test_WrongKey9999') === false, 'a different key does not match');
check_eq((string) $stored['paystack_key_last4'], '1234', 'only the last four characters are kept beside the secret');

$view = payment_settings_view();
check(!str_contains(json_encode($view), $secret), 'the settings data never includes the secret key');
check_eq($view['secret_mask'], '••••1234', 'the saved secret is shown as a mask');
check(str_starts_with($view['webhook_url'], base_path() . '/webhooks/paystack/'), 'the webhook address uses the base path');
check(!preg_match('#https?://#', $view['webhook_url']), 'the webhook address has no domain');

$_SERVER['REQUEST_URI'] = base_path() . '/settings/payments';
ob_start();
payment_settings_form();
$page = (string) ob_get_clean();
check(!str_contains($page, $secret), 'the settings page never prints the key');
check(!str_contains($page, $encrypted), 'the settings page never prints the stored secret');
check(str_contains($page, '••••1234'), 'the settings page shows the secret mask');
check(str_contains($page, $public), 'the public key can be shown');
check(str_contains($page, 'name="secret_key"'), 'the secret field is on the form');
check(str_contains($page, 'value=""'), 'the secret field is left blank');
check(str_contains($page, t('paystack_guide')), 'the page explains Paystack in plain language');
check(str_contains($page, 'Paystack cannot reach localhost'), 'the page explains how to send a Paystack test event');
check(str_contains($page, $view['webhook_url']), 'the page shows the webhook address');

$_COOKIE['lang'] = 'ha';
check(str_starts_with(t('paystack_guide'), 'Paystack yana tura'), 'the Paystack guide is in Hausa');
$_COOKIE['lang'] = 'en';

$modeLog = db_select('fashion_activity_log', ['action' => 'payment_mode'], ['limit' => 10]);
check(count($modeLog) === 1 && (int) $modeLog[0]['enabled'] === 1, 'switching to Paystack writes the log');
check(!str_contains(json_encode($modeLog), $secret), 'the activity log does not contain the secret key');
$keyLog = db_select('fashion_activity_log', ['action' => 'payment_keys'], ['limit' => 10]);
check(count($keyLog) === 1, 'saving Paystack keys writes the log');

$customer = customer_create([
    'name' => 'Hauwa Sani',
    'phone' => '08031112244',
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
$inPaystack = payment_record($order['id'], '500', 'cash');
check(isset($inPaystack['id']), 'manual payments still work in Paystack mode');
$paystackRow = db_select('fashion_payments', ['id' => $inPaystack['id']])[0];
check_eq((string) $paystackRow['provider'], 'manual', 'a staff payment stays manual in Paystack mode');
check_eq((string) $paystackRow['source'], 'staff', 'a staff payment records its source');
$_SERVER['REQUEST_URI'] = base_path() . '/orders/' . $order['id'];
ob_start();
orders_show($order['id']);
$orderPage = (string) ob_get_clean();
check(str_contains($orderPage, 'name="amount"'), 'Paystack mode keeps the record-payment amount field');
check(str_contains($orderPage, 'value="cash"'), 'Paystack mode keeps cash');
check(str_contains($orderPage, 'value="transfer"'), 'Paystack mode keeps transfer');

$back = payment_settings_save('manual', '', '');
check(isset($back['ok']), 'the shop can switch back to manual');
check_eq(payment_mode(), 'manual', 'manual mode is saved');
check(payment_links_enabled() === false, 'manual mode stops payment links');
$kept = db_select('fashion_payment_settings', [], ['limit' => 1])[0];
check((string) $kept['paystack_secret_key_encrypted'] === $encrypted, 'switching back to manual keeps the keys');
$_SERVER['REQUEST_URI'] = base_path() . '/settings/payments';
ob_start();
payment_settings_form();
$manualPage = (string) ob_get_clean();
check(str_contains($manualPage, t('paystack_paused')), 'manual mode says payment links have stopped');
check(!str_contains($manualPage, $secret), 'the manual page still hides the key');
$modes = db_select('fashion_activity_log', ['action' => 'payment_mode'], ['limit' => 10]);
$enabled = array_map(static fn (array $row): int => (int) $row['enabled'], $modes);
sort($enabled);
check_eq($enabled, [0, 1], 'switching modes writes the log');

$stillManual = payment_record($order['id'], '100', 'transfer');
check(isset($stillManual['id']), 'manual payments still work in manual mode');

$other = business_register([
    'business_name' => 'Other Mode Shop',
    'owner_name' => 'Bala',
    'email' => 'paymode-other@example.com',
    'password' => 'password',
    'phone' => '08031110002',
    'company_type' => 'tailor',
]);
check(isset($other['tenant_id']), 'a second shop can sign up');
check_eq(payment_mode(), 'manual', 'the other shop starts on manual');
$otherSave = payment_settings_save('manual', $public, $secret);
check(isset($otherSave['ok']), 'the other shop can save its own manual mode');
check_eq(
    db_update('fashion_payment_settings', ['mode' => 'manual'], ['id' => $stored['id']]),
    0,
    'another business cannot change this payment mode'
);
check(db_select('fashion_payment_settings', ['id' => $stored['id']]) === [], 'another business cannot read these payment settings');

auth_login((string) $shop['user_id'], (string) $shop['tenant_id']);
$afterOther = db_select('fashion_payment_settings', [], ['limit' => 1])[0];
check_eq((string) $afterOther['mode'], 'manual', 'the mode is unchanged by the other shop');
check((string) $afterOther['paystack_secret_key_encrypted'] === $encrypted, 'the key is unchanged by the other shop');

db_update('fashion_users', ['role' => 'staff'], ['id' => (string) current_user_id()]);
try {
    payment_settings_form();
    check(false, 'a user without the owner role cannot open payment settings');
} catch (HttpException $e) {
    check($e->status() === 403, 'a user without the owner role cannot open payment settings');
}
try {
    payment_settings_save('paystack', $public, $secret);
    check(false, 'a user without the owner role cannot change the mode');
} catch (HttpException $e) {
    check($e->status() === 403, 'a user without the owner role cannot change the mode');
}
check_eq(payment_mode(), 'manual', 'a staff change does not switch the mode');

db_update('fashion_users', ['role' => 'owner'], ['id' => (string) current_user_id()]);
module_save([
    'fashion.orders',
    'fashion.production',
    'fashion.measurements',
    'fashion.communication',
    'fashion.online_payments',
]);
try {
    require_module('fashion.online_payments');
    check(false, 'online payments depend on finance');
} catch (HttpException $e) {
    check($e->status() === 403, 'online payments depend on finance');
}
try {
    payment_settings_form();
    check(false, 'payment settings need finance');
} catch (HttpException $e) {
    check($e->status() === 403, 'payment settings need finance');
}
$_SERVER['REQUEST_URI'] = base_path() . '/settings';
ob_start();
settings_form();
$financeOff = (string) ob_get_clean();
check(!str_contains($financeOff, url('/settings/payments')), 'payment settings stay hidden when finance is off');
payment_test_domain($paymentDomain);
