<?php

declare(strict_types=1);

require_once __DIR__ . '/support.php';
require_once dirname(__DIR__) . '/app/domain/accounts.php';
require_once dirname(__DIR__) . '/app/domain/customers.php';
require_once dirname(__DIR__) . '/app/domain/orders.php';
require_once dirname(__DIR__) . '/app/domain/payments.php';
require_once dirname(__DIR__) . '/app/domain/messages.php';
require_once dirname(__DIR__) . '/app/domain/tracking.php';
require_once dirname(__DIR__) . '/app/fashion/controllers/orders.php';
require_once dirname(__DIR__) . '/app/fashion/controllers/messages.php';

tests_reset();
$_COOKIE['lang'] = 'en';
unset($GLOBALS['gmingo_now']);
$savedBase = $GLOBALS['gmingo_app_config']['public_base_url'] ?? null;
$GLOBALS['gmingo_app_config']['public_base_url'] = 'https://stitches.example';

check_eq(message_link_template(5000, true), 'pay_balance_link', 'a balance with Paystack uses pay_balance_link');
check_eq(message_link_template(5000, false), 'invoice_link', 'a balance without Paystack uses invoice_link');
check_eq(message_link_template(0, true), 'ready_for_pickup', 'a zero balance uses the ready receipt wording');
check_eq(message_link_template(0, false), 'ready_for_pickup', 'a zero balance stays on the ready receipt wording');

$shop = business_register([
    'business_name' => 'Link Shop',
    'owner_name' => 'Link Owner',
    'email' => 'links@example.com',
    'password' => 'password',
    'phone' => '08030001122',
    'company_type' => 'tailor',
]);
$ownerId = (string) $shop['user_id'];
$tenantId = (string) $shop['tenant_id'];
$phone = '08031119922';
$email = 'secret-mail@example.com';
$customer = customer_create([
    'name' => 'Hauwa Link',
    'phone' => $phone,
    'gender' => 'female',
    'customer_type' => 'regular',
    'lang' => 'ha',
]);
$customerId = (string) $customer['id'];
db_update('fashion_customers', ['email' => $email], ['id' => $customerId]);
$garmentId = (string) db_select('fashion_garment_types', ['code' => 'kaftan'], ['limit' => 1])[0]['id'];
$created = order_create([
    'customer_id' => $customerId,
    'due_date' => '2026-10-20',
    'urgent' => '',
    'items' => [[
        'garment_type_id' => $garmentId,
        'quantity' => '1',
        'price' => '40',
        'fabric' => '',
        'notes' => '',
    ]],
]);
$orderId = (string) $created['id'];
$GLOBALS['gmingo_now'] = '2026-10-17 10:00:00';
$_POST = ['csrf' => csrf_token()];
try {
    message_link_submit($orderId);
    check(false, 'sending the invoice redirects');
} catch (RedirectException $redirect) {
    check(str_starts_with($redirect->path(), '/messages/'), 'sending the invoice opens the prepared message');
    $firstId = substr($redirect->path(), strlen('/messages/'));
}
$first = db_select('fashion_messages', ['id' => $firstId], ['limit' => 1])[0];
check_eq((string) $first['template_key'], 'invoice_link', 'the first send stores invoice_link');
check_eq((string) $first['language'], 'ha', 'the message uses the customer Hausa language');
$body = (string) $first['body'];
$token = (string) db_select('fashion_orders', ['id' => $orderId], ['limit' => 1])[0]['tracking_token'];
check(str_contains($body, 'https://stitches.example' . url('/track/' . $token)), 'the tracking link is absolute and has the token');
check(str_contains($body, 'ɗ'), 'the Hausa invoice text includes a Hausa letter');
check(!str_contains($body, $phone), 'the invoice text leaves out the customer phone');
check(!str_contains($body, $email), 'the invoice text leaves out the customer email');
$href = message_whatsapp_href($phone, $body);
check($href === 'https://wa.me/' . message_whatsapp_digits($phone) . '?text=' . rawurlencode($body), 'the WhatsApp link is the encoded message');
check(is_string($href) && str_contains($href, rawurlencode('ɗ')), 'the WhatsApp link encodes the Hausa letter');
check(is_string($href) && !str_contains($href, 'ɗ'), 'the WhatsApp link does not leave the Hausa letter raw');
$_SERVER['REQUEST_URI'] = base_path() . '/messages/' . $firstId;
ob_start();
messages_show($firstId);
$preparedPage = (string) ob_get_clean();
check(str_contains($preparedPage, (string) $href), 'the message page opens the WhatsApp link');
check(str_contains($preparedPage, 'Mark as sent'), 'the message page keeps Mark as sent');
check(preg_match('/<title>([^<]*)<\/title>/', $preparedPage, $title) === 1 && !str_contains($title[1], $phone), 'the message title leaves out the phone number');

$GLOBALS['gmingo_now'] = '2026-10-17 10:10:00';
$_SERVER['REQUEST_URI'] = base_path() . '/orders/' . $orderId;
ob_start();
orders_show($orderId);
$againPage = (string) ob_get_clean();
check(str_contains($againPage, 'Prepared 10 minutes ago. Send again?'), 'an earlier send is shown first');
check(str_contains($againPage, $body), 'the earlier message text is shown');
check(str_contains($againPage, 'Send invoice'), 'the order page has Send invoice');
check(str_contains($againPage, 'Mark as sent'), 'the order page keeps Mark as sent');
$_POST = ['csrf' => csrf_token()];
try {
    message_link_submit($orderId);
    check(false, 'sending again redirects');
} catch (RedirectException $redirect) {
    check(str_starts_with($redirect->path(), '/messages/'), 'sending again opens the new message');
}
check_eq(message_prepared_count($orderId, 'invoice_link'), 2, 'sending twice stores two prepared rows');

$plain = customer_create([
    'name' => 'Plain Link',
    'phone' => '08032221100',
    'gender' => 'male',
    'customer_type' => 'regular',
]);
$plainOrder = order_create([
    'customer_id' => (string) $plain['id'],
    'due_date' => '2026-10-21',
    'urgent' => '',
    'items' => [[
        'garment_type_id' => $garmentId,
        'quantity' => '1',
        'price' => '15',
        'fabric' => '',
        'notes' => '',
    ]],
]);
$_POST = ['csrf' => csrf_token()];
try {
    message_link_submit((string) $plainOrder['id']);
    check(false, 'a shop-default send redirects');
} catch (RedirectException $redirect) {
    $plainId = substr($redirect->path(), strlen('/messages/'));
}
$plainBody = (string) db_select('fashion_messages', ['id' => $plainId], ['limit' => 1])[0]['body'];
check(!str_contains($plainBody, 'ɗ'), 'a customer with no saved language uses the shop English default');
db_update('fashion_users', ['lang' => 'ha'], ['id' => $ownerId]);
$_POST = ['csrf' => csrf_token()];
try {
    message_link_submit((string) $plainOrder['id']);
    check(false, 'a Hausa shop default redirects');
} catch (RedirectException $redirect) {
    $shopHaId = substr($redirect->path(), strlen('/messages/'));
}
$shopHa = (string) db_select('fashion_messages', ['id' => $shopHaId], ['limit' => 1])[0]['body'];
check(str_contains($shopHa, 'ɗ'), 'a customer with no saved language uses the shop Hausa default');
db_update('fashion_users', ['lang' => 'en'], ['id' => $ownerId]);

module_save([
    'fashion.orders',
    'fashion.production',
    'fashion.finance',
    'fashion.communication',
    'fashion.measurements',
    'fashion.online_payments',
]);
db_insert('fashion_payment_settings', [
    'mode' => 'paystack',
    'paystack_public_key' => '',
    'paystack_secret_key_encrypted' => '',
    'paystack_key_last4' => '',
    'webhook_token' => bin2hex(random_bytes(16)),
    'connected_at' => null,
    'updated_by' => $ownerId,
]);
$_POST = ['csrf' => csrf_token()];
try {
    message_link_submit($orderId);
    check(false, 'a Paystack balance send redirects');
} catch (RedirectException $redirect) {
    $payId = substr($redirect->path(), strlen('/messages/'));
}
check_eq((string) db_select('fashion_messages', ['id' => $payId], ['limit' => 1])[0]['template_key'], 'pay_balance_link', 'Paystack balance uses pay_balance_link');
payment_record($orderId, '40', 'cash');
$_POST = ['csrf' => csrf_token()];
try {
    message_link_submit($orderId);
    check(false, 'a paid order redirects');
} catch (RedirectException $redirect) {
    $readyId = substr($redirect->path(), strlen('/messages/'));
}
$readyRow = db_select('fashion_messages', ['id' => $readyId], ['limit' => 1])[0];
check_eq((string) $readyRow['template_key'], 'ready_for_pickup', 'a zero balance stores ready_for_pickup');
check(str_contains((string) $readyRow['body'], 'Rasit ragowar kudi'), 'the ready text uses the Hausa receipt wording');
check(str_contains(message_default_bodies()['ready_for_pickup']['en'], 'Receipt balance'), 'the English ready template uses receipt wording');
check(str_contains((string) $readyRow['body'], 'https://stitches.example' . url('/track/' . $token)), 'the ready text includes the absolute tracking link');

db_update('fashion_customers', ['phone' => ''], ['id' => $customerId]);
$_POST = ['csrf' => csrf_token()];
try {
    message_link_submit($orderId);
    check(false, 'a missing phone still prepares a message');
} catch (RedirectException $redirect) {
    $copyId = substr($redirect->path(), strlen('/messages/'));
}
$_SERVER['REQUEST_URI'] = base_path() . '/messages/' . $copyId;
ob_start();
messages_show($copyId);
$copyPage = (string) ob_get_clean();
check(str_contains($copyPage, 'No phone number'), 'a missing phone shows a notice');
check(str_contains($copyPage, 'Copy message'), 'a missing phone offers Copy message');
check(!str_contains($copyPage, 'https://wa.me/'), 'a missing phone does not open WhatsApp');
check(preg_match('/<title>([^<]*)<\/title>/', $copyPage, $copyTitle) === 1 && !str_contains($copyTitle[1], $phone), 'the copy page title leaves out the phone number');

$GLOBALS['gmingo_app_config']['public_base_url'] = 'http://127.0.0.1';
$_SERVER['REQUEST_URI'] = base_path() . '/orders/' . $orderId;
ob_start();
orders_show($orderId);
$ipPage = (string) ob_get_clean();
check(str_contains($ipPage, 'This link will only work on this computer.'), 'an IP address shows the localhost warning');
$GLOBALS['gmingo_app_config']['public_base_url'] = 'http://localhost';
ob_start();
orders_show($orderId);
$localPage = (string) ob_get_clean();
check(str_contains($localPage, 'This link will only work on this computer.'), 'localhost shows the warning');
$GLOBALS['gmingo_app_config']['public_base_url'] = '';
ob_start();
orders_show($orderId);
$emptyPage = (string) ob_get_clean();
check(str_contains($emptyPage, 'This link will only work on this computer.'), 'an empty public address shows the warning');
$GLOBALS['gmingo_app_config']['public_base_url'] = 'https://stitches.example';
ob_start();
orders_show($orderId);
$publicPage = (string) ob_get_clean();
check(!str_contains($publicPage, 'This link will only work on this computer.'), 'a public address hides the warning');

$today = DateTimeImmutable::createFromFormat('!Y-m-d', app_today(), app_timezone());
$beyond = $today->modify('-' . (string) (tracking_grace_days() + 1) . ' days')->format('Y-m-d');
db_update('fashion_tenants', ['subscription_ends_on' => $beyond], ['id' => $tenantId]);
$_POST = ['csrf' => csrf_token()];
try {
    message_link_submit($orderId);
    check(false, 'an ended subscription blocks the send');
} catch (HttpException $e) {
    check_eq($e->status(), 402, 'an ended subscription returns 402');
    check_eq($e->getMessage(), 'The subscription has ended.', 'an ended subscription says the subscription has ended');
}
db_update('fashion_tenants', ['subscription_ends_on' => null], ['id' => $tenantId]);

module_save(['fashion.orders', 'fashion.finance']);
$_POST = ['csrf' => csrf_token()];
try {
    message_link_submit($orderId);
    check(false, 'a switched-off module stays closed');
} catch (HttpException $e) {
    check_eq($e->status(), 403, 'the module off gives 403');
    check_eq($e->getMessage(), 'This part of the app is switched off.', 'the module off explains that it is switched off');
}

business_register([
    'business_name' => 'Other Link Shop',
    'owner_name' => 'Other Link',
    'email' => 'links-other@example.com',
    'password' => 'password',
    'phone' => '08034445566',
    'company_type' => 'tailor',
]);
$_POST = ['csrf' => csrf_token()];
check_throws(
    static fn () => message_link_submit($orderId),
    HttpException::class,
    'Page not found'
);

if ($savedBase === null) {
    unset($GLOBALS['gmingo_app_config']['public_base_url']);
} else {
    $GLOBALS['gmingo_app_config']['public_base_url'] = $savedBase;
}
unset($GLOBALS['gmingo_now']);
$_COOKIE['lang'] = 'en';
