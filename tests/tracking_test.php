<?php

declare(strict_types=1);

require_once __DIR__ . '/support.php';
require_once dirname(__DIR__) . '/app/domain/accounts.php';
require_once dirname(__DIR__) . '/app/domain/customers.php';
require_once dirname(__DIR__) . '/app/domain/orders.php';
require_once dirname(__DIR__) . '/app/domain/payments.php';
require_once dirname(__DIR__) . '/app/domain/measurements.php';
require_once dirname(__DIR__) . '/app/domain/tracking.php';
require_once dirname(__DIR__) . '/app/fashion/controllers/orders.php';
require_once dirname(__DIR__) . '/app/fashion/controllers/tracking.php';

tests_reset();
$_COOKIE['lang'] = 'en';
unset($_COOKIE['track_lang'], $GLOBALS['gmingo_lang_override']);

$trackingSource = (string) file_get_contents(dirname(__DIR__) . '/app/domain/tracking.php');
check(str_contains($trackingSource, 'random_bytes(16)'), 'the token comes from a secure random source');
check(!str_contains($trackingSource, 'fashion_customers'), 'the tracking file does not read customers');
check(!str_contains($trackingSource, 'fashion_measurements'), 'the tracking file does not read measurements');
check_throws(
    fn () => tracking_platform_query(
        'SELECT tenant_id, id FROM fashion_orders WHERE tracking_token = :token LIMIT 1',
        ['token' => 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa']
    ),
    RuntimeException::class,
    'This query is only for tracking.'
);

$shop = business_register([
    'business_name' => 'Track Shop',
    'owner_name' => 'Track Owner Secret',
    'email' => 'track-secret-owner@example.com',
    'password' => 'password',
    'phone' => '08031110022',
    'company_type' => 'tailor',
]);
$ownerId = (string) $shop['user_id'];
$tenantId = (string) $shop['tenant_id'];
$customer = customer_create([
    'name' => 'Zainab Secret Customer',
    'phone' => '08035550991',
    'gender' => 'female',
    'customer_type' => 'regular',
]);
$customerId = (string) $customer['id'];
$garmentId = (string) db_select('fashion_garment_types', ['code' => 'kaftan'], ['limit' => 1])[0]['id'];
$created = order_create([
    'customer_id' => $customerId,
    'due_date' => '2026-10-20',
    'urgent' => '',
    'items' => [[
        'garment_type_id' => $garmentId,
        'quantity' => '1',
        'price' => '7777.77',
        'fabric' => 'SECRET-FABRIC-GOLD',
        'notes' => 'SECRET-NOTE-ZIP',
    ]],
]);
$orderId = (string) $created['id'];
$orderNumber = (string) $created['order_number'];
$otherOrder = order_create([
    'customer_id' => $customerId,
    'due_date' => '2026-11-01',
    'urgent' => '',
    'items' => [[
        'garment_type_id' => $garmentId,
        'quantity' => '1',
        'price' => '50',
        'fabric' => '',
        'notes' => '',
    ]],
]);
$paid = payment_record($orderId, '1000', 'cash');
check(is_uuid((string) ($paid['id'] ?? '')), 'a payment of 1000 naira is recorded');
check(isset(measurement_save($customerId, $garmentId, 'inches', ['neck' => '91.91'])['id']), 'a measurement is stored for the customer');
$cutting = db_select('fashion_stage_definitions', ['code' => 'cutting'], ['limit' => 1])[0];
db_insert('fashion_stage_events', [
    'order_id' => $orderId,
    'stage_id' => (string) $cutting['id'],
    'user_id' => $ownerId,
    'note' => 'INTERNAL-STAGE-NOTE',
    'kind' => 'move',
    'created_at' => date('Y-m-d H:i:s'),
]);
db_insert('fashion_payment_settings', [
    'mode' => 'manual',
    'paystack_public_key' => 'pk_test_SHOULD_NOT_LEAK',
    'paystack_secret_key_encrypted' => 'SECRETKEYVALUE',
    'paystack_key_last4' => '9999',
    'webhook_token' => bin2hex(random_bytes(16)),
    'connected_at' => null,
    'updated_by' => $ownerId,
]);
check_eq(tracking_payment_save('bank', 'GTBank Track', '0123456789', 'Use the order number')['ok'] ?? false, true, 'the shop saves bank details');
$token = tracking_issue($orderId);
check(is_string($token) && tracking_token_ok($token), 'the tracking token is 32 hex characters');
check(!str_contains((string) $token, $orderNumber), 'the token is not the order number');
check(!str_contains((string) $token, $orderId), 'the token is not the order id');

$_SERVER['REQUEST_URI'] = base_path() . '/orders/' . $orderId;
ob_start();
orders_show($orderId);
$orderPage = (string) ob_get_clean();
check(str_contains($orderPage, t('copy_tracking_link')), 'the order page has Copy tracking link');
check(str_contains($orderPage, url('/track/' . $token)), 'the order page shows the public tracking path');

function tracking_test_fetch(string $token): array
{
    return tracking_test_download('/track/' . $token);
}

function tracking_test_download(string $path): array
{
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_SERVER['REQUEST_URI'] = base_path() . $path;
    $started = hrtime(true);
    ob_start();
    router_dispatch('GET', $path);
    return [
        'status' => http_response_code(),
        'body' => (string) ob_get_clean(),
        'ns' => hrtime(true) - $started,
        'headers' => implode("\n", headers_list()),
    ];
}

$_SERVER['REMOTE_ADDR'] = '198.51.100.21';
$page = tracking_test_fetch((string) $token);
check_eq($page['status'], 200, 'a real tracking token returns 200');
check(str_contains($page['body'], '<meta name="robots" content="noindex, nofollow">'), 'the tracking page has a noindex meta tag');
$trackingController = (string) file_get_contents(dirname(__DIR__) . '/app/fashion/controllers/tracking.php');
check(str_contains($trackingController, "header('X-Robots-Tag: noindex, nofollow');"), 'the tracking page sends a robots header');
check(str_contains($trackingController, "header('Cache-Control: private, no-store');"), 'shared caches are told not to store the tracking page');
check(str_contains($page['body'], '<title>Track Shop</title>'), 'the page title is the shop name');
check(str_contains($page['body'], '<meta property="og:title" content="Track Shop">'), 'og:title is the shop name');
check(str_contains($page['body'], '<meta property="og:description" content="Track Shop">'), 'og:description is the shop name');
$headEnd = strpos($page['body'], '</head>');
$head = $headEnd === false ? '' : substr($page['body'], 0, $headEnd);
check(!str_contains($head, 'Zainab'), 'the page title and og tags contain no customer name');
check(!str_contains($head, '₦'), 'the page title and og tags contain no amount');
check(!str_contains($head, '7777'), 'the page title and og tags contain no amount digits');
check(!str_contains($head, $orderNumber), 'the page title and og tags leave out the order number');
$titleEnd = strpos($page['body'], '</title>');
check($titleEnd !== false && !str_contains(substr($page['body'], 0, $titleEnd), '08031110022'), 'the shop phone stays out of the page title');
$html = $page['body'];
check(str_contains($html, 'Track Shop'), 'the page shows the shop name');
check(str_contains($html, '08031110022'), 'the page shows the shop phone');
check(str_contains($html, $orderNumber), 'the page shows the order number');
check(str_contains($html, 'Status: Cutting'), 'the page shows the stage name');
check(str_contains($html, '20 October 2026'), 'the page shows the expected date');
check(str_contains($html, 'New'), 'the timeline includes the shop stage New');
check(str_contains($html, 'Cutting (Current)'), 'the timeline marks the current stage');
check(str_contains($html, 'Balance due: ₦6,777.77'), 'the page shows the balance due');
$statusAt = strpos($html, 'Status: Cutting');
$invoiceAt = strpos($html, '<h2>Invoice</h2>');
$dueAt = strpos($html, 'Due date:');
check($statusAt !== false && $invoiceAt !== false && $dueAt !== false && $invoiceAt > $statusAt && $dueAt > $invoiceAt, 'the invoice sits below the status');
check(str_contains($html, 'Description: Kaftan x 1'), 'the invoice describes the work');
check(str_contains($html, 'Total: ₦7,777.77'), 'the invoice total is ₦7,777.77');
check(str_contains($html, 'Deposit: ₦0.00'), 'the invoice deposit is ₦0.00');
check(str_contains($html, 'Paid so far: ₦1,000.00'), 'the invoice paid so far is ₦1,000.00');
check(str_contains($html, 'Balance: ₦6,777.77'), 'the invoice balance is ₦6,777.77');
$paidOn = message_format_date(substr((string) db_select('fashion_payments', ['id' => (string) $paid['id']], ['limit' => 1])[0]['created_at'], 0, 10), 'en');
check(str_contains($html, $paidOn), 'the invoice lists the payment date');
check(str_contains($html, 'Cash'), 'the invoice lists the payment method');
check(str_contains($html, url('/track/' . $token . '/invoice.png')), 'the page links to the invoice PNG');
check(str_contains($html, url('/track/' . $token . '/invoice.pdf')), 'the page links to the invoice PDF');
check(str_contains($html, url('/track/' . $token . '/receipt/' . $paid['id'] . '.png')), 'the page links to the receipt PNG');
check(str_contains($html, url('/track/' . $token . '/receipt/' . $paid['id'] . '.pdf')), 'the page links to the receipt PDF');
check(str_contains($html, 'Download invoice (PNG)'), 'the page offers Download invoice (PNG)');
check(str_contains($html, 'Download invoice (PDF)'), 'the page offers Download invoice (PDF)');
check(str_contains($html, 'Download receipt'), 'the page offers Download receipt');
check(str_contains($html, 'GTBank Track'), 'the page shows the bank name');
check(str_contains($html, '0123456789'), 'the page shows the account number');
check(str_contains($html, 'Use the order number'), 'the page shows the reference note');
check(str_contains($html, 'https://wa.me/2348031110022?text=Order%20' . $orderNumber), 'Chat with shop uses the shop WhatsApp number');
check(str_contains($html, '<p>A Pay balance button will be added here.</p>'), 'a Pay balance place is reserved');
check(!str_contains($html, 'paystack.com'), 'the Pay balance button is not built');
check(!str_contains(strtolower($html), '<script'), 'the public page has no scripts');
foreach ([
    '08035550991',
    '2348035550991',
    'track-secret-owner@example.com',
    'Zainab Secret Customer',
    'Track Owner Secret',
    '91.91',
    'SECRET-NOTE-ZIP',
    'SECRET-FABRIC-GOLD',
    'INTERNAL-STAGE-NOTE',
    'pk_test_SHOULD_NOT_LEAK',
    'SECRETKEYVALUE',
    (string) $otherOrder['order_number'],
    $orderId,
    $customerId,
    $tenantId,
    $ownerId,
    (string) $cutting['id'],
] as $hidden) {
    check(!str_contains($html, $hidden), 'the public page hides ' . $hidden);
}

db_update('fashion_users', ['lang' => 'ha'], ['id' => $ownerId]);
unset($_COOKIE['track_lang']);
$hausaDefault = tracking_test_fetch((string) $token);
check(str_contains($hausaDefault['body'], 'Yi magana da shago'), 'the page uses the shop Hausa language');
check(str_contains($hausaDefault['body'], '20 Oktoba 2026'), 'the expected date uses the Hausa month name');
$_POST = ['csrf' => csrf_token(), 'lang' => 'en'];
$_SERVER['REQUEST_METHOD'] = 'POST';
$_SERVER['REQUEST_URI'] = base_path() . '/track/' . $token . '/language';
try {
    router_dispatch('POST', '/track/' . $token . '/language');
    check(false, 'the English switch redirects');
} catch (RedirectException $e) {
    check_eq($e->path(), '/track/' . $token, 'the English switch returns to the tracking page');
}
$english = tracking_test_fetch((string) $token);
check(str_contains($english['body'], 'Chat with shop'), 'switching to English shows English text');
$_POST = ['csrf' => csrf_token(), 'lang' => 'ha'];
try {
    router_dispatch('POST', '/track/' . $token . '/language');
    check(false, 'the Hausa switch redirects');
} catch (RedirectException $e) {
    check_eq($e->path(), '/track/' . $token, 'the Hausa switch returns to the tracking page');
}
$hausa = tracking_test_fetch((string) $token);
check(str_contains($hausa['body'], 'Yi magana da shago'), 'the page shows Hausa text when switched');
check(str_contains($hausa['body'], '20 Oktoba 2026'), 'the switched page shows the Hausa date');
unset($_COOKIE['track_lang']);
db_update('fashion_users', ['lang' => 'en'], ['id' => $ownerId]);

$settled = order_create([
    'customer_id' => $customerId,
    'due_date' => '2026-10-25',
    'urgent' => '',
    'items' => [[
        'garment_type_id' => $garmentId,
        'quantity' => '1',
        'price' => '40',
        'fabric' => '',
        'notes' => '',
    ]],
]);
check(isset(payment_record((string) $settled['id'], '40', 'transfer')['id']), 'the settled order is paid in full');
$settledToken = tracking_issue((string) $settled['id']);
$_SERVER['REMOTE_ADDR'] = '198.51.100.28';
$full = tracking_test_fetch((string) $settledToken);
check(str_contains($full['body'], 'Paid in full'), 'a zero balance says Paid in full');
check(!str_contains($full['body'], 'Payment details'), 'a zero balance hides the payment details');
check(!str_contains($full['body'], 'GTBank Track'), 'a zero balance hides the bank name');
check(!str_contains($full['body'], '0123456789'), 'a zero balance hides the account number');
check(!str_contains($full['body'], 'The shop has not added payment details yet.'), 'a zero balance hides the empty payment notice');
check(str_contains($full['body'], 'Transfer'), 'a paid order still lists the payment method');
check(str_contains($full['body'], '₦40.00'), 'a paid order still lists the payment amount');

$oldToken = (string) $token;
$fresh = tracking_regenerate($orderId);
check(tracking_token_ok($fresh) && $fresh !== $oldToken, 'regenerate replaces the token');
$_SERVER['REMOTE_ADDR'] = '198.51.100.22';
$unknown = tracking_test_fetch(bin2hex(random_bytes(16)));
$regenerated = tracking_test_fetch($oldToken);
$malformed = tracking_test_fetch('not-a-real-token');
check_eq($unknown['status'], 404, 'an unknown token returns 404');
check_eq($regenerated['status'], $unknown['status'], 'a regenerated token returns the same status as an unknown token');
check_eq($malformed['status'], $unknown['status'], 'a malformed token returns the same status as an unknown token');
check_eq($unknown['body'], '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex, nofollow"><title>Page not found</title><link rel="stylesheet" href="' . url('/assets/app.css') . '"></head><body class="is-guest"><main class="app"><p>Page not found</p></main></body></html>', 'an unknown token shows Page not found');
check_eq($regenerated['body'], $unknown['body'], 'a regenerated token shows the same page as an unknown token');
check_eq($malformed['body'], $unknown['body'], 'a malformed token shows the same page as an unknown token');
$fast = min($unknown['ns'], $regenerated['ns'], $malformed['ns']);
$slow = max($unknown['ns'], $regenerated['ns'], $malformed['ns']);
check($slow - $fast < 1000000000, 'unknown, regenerated and malformed tokens take a similar time');
$again = tracking_test_fetch($fresh);
check_eq($again['status'], 200, 'the new token opens the order');
check(str_contains($again['body'], $orderNumber), 'the new token shows the same order');

db_update('fashion_users', ['role' => 'staff'], ['id' => $ownerId]);
check_throws(
    fn () => tracking_regenerate($orderId),
    HttpException::class,
    'That record is not in this shop.'
);
db_update('fashion_users', ['role' => 'owner'], ['id' => $ownerId]);

module_save(['fashion.orders', 'fashion.production', 'fashion.finance', 'fashion.measurements']);
$_SERVER['REMOTE_ADDR'] = '198.51.100.23';
$closed = tracking_test_fetch($fresh);
check_eq($closed['status'], 200, 'a switched-off module still answers the link');
check(str_contains($closed['body'], 'This page is not available.'), 'module off gives the unavailable page');
check(!str_contains($closed['body'], $orderNumber), 'module off does not show the order');
$closedFile = tracking_test_download('/track/' . $fresh . '/invoice.png');
check(str_contains($closedFile['body'], 'This page is not available.'), 'module off gives the unavailable invoice');
check(!str_starts_with($closedFile['body'], "\x89PNG"), 'module off does not return the invoice file');
module_save(['fashion.orders', 'fashion.production', 'fashion.finance', 'fashion.measurements', 'fashion.communication']);

$today = DateTimeImmutable::createFromFormat('!Y-m-d', app_today(), app_timezone());
$beyond = $today->modify('-' . (string) (tracking_grace_days() + 1) . ' days')->format('Y-m-d');
$within = $today->modify('-3 days')->format('Y-m-d');
db_update('fashion_tenants', ['subscription_ends_on' => $beyond], ['id' => $tenantId]);
$_SERVER['REMOTE_ADDR'] = '198.51.100.24';
$expired = tracking_test_fetch($fresh);
check(str_contains($expired['body'], 'This page is not available.'), 'an ended subscription gives the unavailable page');
check(!str_contains($expired['body'], $orderNumber), 'an ended subscription does not show the order');
$expiredFile = tracking_test_download('/track/' . $fresh . '/invoice.pdf');
check(str_contains($expiredFile['body'], 'This page is not available.'), 'an ended subscription gives the unavailable invoice');
check(!str_starts_with($expiredFile['body'], '%PDF'), 'an ended subscription does not return the invoice file');
try {
    tracking_regenerate($orderId);
    check(false, 'an ended subscription blocks regenerate');
} catch (HttpException $e) {
    check_eq($e->status(), 402, 'an ended subscription returns 402');
    check_eq($e->getMessage(), 'The subscription has ended.', 'an ended subscription says the subscription has ended');
}
db_update('fashion_tenants', ['subscription_ends_on' => $within], ['id' => $tenantId]);
$grace = tracking_test_fetch($fresh);
check(str_contains($grace['body'], $orderNumber), 'the page stays open during the 7 day grace period');
db_update('fashion_tenants', ['subscription_ends_on' => null], ['id' => $tenantId]);

$other = business_register([
    'business_name' => 'Other Track Shop',
    'owner_name' => 'Other Owner',
    'email' => 'track-other@example.com',
    'password' => 'password',
    'phone' => '08039991111',
    'company_type' => 'tailor',
]);
$otherCustomer = customer_create([
    'name' => 'Other Customer',
    'phone' => '08036667788',
    'gender' => 'male',
    'customer_type' => 'regular',
]);
$otherGarment = (string) db_select('fashion_garment_types', ['code' => 'kaftan'], ['limit' => 1])[0]['id'];
$foreign = order_create([
    'customer_id' => (string) $otherCustomer['id'],
    'due_date' => '2026-12-01',
    'urgent' => '',
    'items' => [[
        'garment_type_id' => $otherGarment,
        'quantity' => '1',
        'price' => '80',
        'fabric' => '',
        'notes' => '',
    ]],
]);
db_update('fashion_orders', ['order_number' => 'ZZ-OTHER-77'], ['id' => (string) $foreign['id']]);
$_SERVER['REMOTE_ADDR'] = '198.51.100.25';
$isolated = tracking_test_fetch($fresh);
check(str_contains($isolated['body'], 'Track Shop'), 'shop A token shows shop A');
check(!str_contains($isolated['body'], 'Other Track Shop'), 'shop A token does not show shop B');
check(!str_contains($isolated['body'], 'ZZ-OTHER-77'), 'shop A token does not show shop B order');
check(!str_contains($isolated['body'], '08036667788'), 'shop A token does not show shop B phone');
check_eq(current_tenant_id(), (string) $other['tenant_id'], 'the public page releases the shop bind');
$_POST = ['csrf' => csrf_token()];
$_SERVER['REQUEST_METHOD'] = 'POST';
$_SERVER['REQUEST_URI'] = base_path() . '/orders/' . $orderId . '/tracking';
try {
    router_dispatch('POST', '/orders/' . $orderId . '/tracking');
    check(false, 'another shop cannot regenerate this tracking link');
} catch (HttpException $e) {
    check_eq($e->status(), 404, 'another shop gets 404 for this order id');
    check_eq($e->getMessage(), 'Page not found', 'another shop is told the page is not found');
}

$foreignToken = tracking_issue((string) $foreign['id']);
$foreignPaid = payment_record((string) $foreign['id'], '10', 'cash');
check(is_uuid((string) ($foreignPaid['id'] ?? '')), 'the other shop records its own payment');
$_SERVER['REMOTE_ADDR'] = '198.51.100.29';
$invoicePng = tracking_test_download('/track/' . $fresh . '/invoice.png');
$invoicePdf = tracking_test_download('/track/' . $fresh . '/invoice.pdf');
check_eq($invoicePng['status'], 200, 'the invoice PNG returns 200');
check(str_starts_with($invoicePng['body'], "\x89PNG"), 'the invoice PNG is a PNG file');
check_eq($invoicePdf['status'], 200, 'the invoice PDF returns 200');
check(str_starts_with($invoicePdf['body'], '%PDF'), 'the invoice PDF is a PDF file');
check(str_contains($invoicePdf['body'], '₦7,777.77'), 'the invoice PDF shows the total');
check(str_contains($invoicePdf['body'], '₦1,000.00'), 'the invoice PDF shows the amount paid');
check(str_contains($invoicePdf['body'], '₦6,777.77'), 'the invoice PDF shows the balance');
check(str_contains($invoicePdf['body'], 'Kaftan x 1'), 'the invoice PDF describes the work');
check(!str_contains($invoicePdf['body'], 'Zainab'), 'the invoice PDF leaves out the customer name');
check(!str_contains($invoicePdf['body'], '08035550991'), 'the invoice PDF leaves out the customer phone');
check(!str_contains($invoicePdf['body'], 'SECRET-FABRIC-GOLD'), 'the invoice PDF leaves out the fabric');
$receiptPng = tracking_test_download('/track/' . $fresh . '/receipt/' . $paid['id'] . '.png');
$receiptPdf = tracking_test_download('/track/' . $fresh . '/receipt/' . $paid['id'] . '.pdf');
check(str_starts_with($receiptPng['body'], "\x89PNG"), 'the receipt PNG is a PNG file');
check(str_contains($receiptPdf['body'], '₦1,000.00'), 'the receipt PDF shows the payment amount');
check(str_contains($receiptPdf['body'], 'Cash'), 'the receipt PDF shows the payment method');
check(!str_contains($receiptPdf['body'], 'Track Owner Secret'), 'the receipt PDF leaves out who recorded the payment');
$badPng = tracking_test_download('/track/not-a-real-token/invoice.png');
$badPdf = tracking_test_download('/track/' . bin2hex(random_bytes(16)) . '/invoice.pdf');
check_eq($badPng['status'], 404, 'a bad token on the invoice PNG returns 404');
check_eq($badPng['body'], $unknown['body'], 'a bad token on the invoice PNG gives the same response as the page');
check_eq($badPdf['status'], 404, 'a bad token on the invoice PDF returns 404');
check_eq($badPdf['body'], $unknown['body'], 'a bad token on the invoice PDF gives the same response as the page');
$foreignPdf = tracking_test_download('/track/' . $foreignToken . '/invoice.pdf');
check_eq($foreignPdf['status'], 200, 'the other shop token returns its own invoice');
check(str_contains($foreignPdf['body'], 'ZZ-OTHER-77'), 'the other shop invoice shows its order');
check(!str_contains($foreignPdf['body'], '6,777.77'), 'another shop token does not return this invoice');
check(!str_contains($foreignPdf['body'], $orderNumber), 'another shop token does not return this order number');
$refused = tracking_test_download('/track/' . $fresh . '/receipt/' . $foreignPaid['id'] . '.png');
check_eq($refused['status'], 404, 'a receipt for another shop payment returns 404');
check_eq($refused['body'], $unknown['body'], 'a receipt for another shop payment gives the same not found page');
check(!str_starts_with($refused['body'], "\x89PNG"), 'a receipt for another shop payment is not a file');

$_SERVER['REMOTE_ADDR'] = '198.51.100.26';
$sample = null;
for ($i = 0; $i < tracking_rate_max(); $i++) {
    $sample = tracking_test_fetch(bin2hex(random_bytes(16)));
}
check_eq($sample['status'], 404, 'attempts inside the limit still return not found');
$blocked = tracking_test_fetch($fresh);
check_eq($blocked['status'], 429, 'the rate limit blocks after the set number of attempts');
check(str_contains($blocked['body'], 'Too many attempts. Wait and try again.'), 'the rate limit explains the wait');
check(!str_contains($blocked['body'], $orderNumber), 'a blocked request does not show the order');
$_SERVER['REMOTE_ADDR'] = '198.51.100.27';
$openAgain = tracking_test_fetch($fresh);
check_eq($openAgain['status'], 200, 'a different address is not blocked');

unset($_COOKIE['track_lang'], $GLOBALS['gmingo_lang_override']);
