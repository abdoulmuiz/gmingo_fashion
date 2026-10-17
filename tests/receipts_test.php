<?php

declare(strict_types=1);

require_once __DIR__ . '/support.php';
require_once dirname(__DIR__) . '/app/domain/accounts.php';
require_once dirname(__DIR__) . '/app/domain/customers.php';
require_once dirname(__DIR__) . '/app/domain/orders.php';
require_once dirname(__DIR__) . '/app/domain/payments.php';
require_once dirname(__DIR__) . '/app/domain/receipts.php';
require_once dirname(__DIR__) . '/app/domain/paystack.php';
require_once dirname(__DIR__) . '/app/fashion/controllers/orders.php';
require_once dirname(__DIR__) . '/app/fashion/controllers/receipts.php';

tests_reset();
$_COOKIE['lang'] = 'en';

$shop = business_register([
    'business_name' => 'Receipt Shop',
    'owner_name' => 'Receipt Owner',
    'email' => 'receipts@example.com',
    'password' => 'password',
    'phone' => '08032220011',
    'company_type' => 'tailor',
]);
$ownerId = (string) $shop['user_id'];
$tenantId = (string) $shop['tenant_id'];
db_update('fashion_tenants', ['deposit_percent' => 50], ['id' => $tenantId]);
db_insert('fashion_payment_settings', [
    'mode' => 'manual',
    'paystack_public_key' => 'pk_test_SHOULD_NOT_LEAK',
    'paystack_secret_key_encrypted' => 'SECRETKEYVALUE',
    'paystack_key_last4' => '9999',
    'webhook_token' => bin2hex(random_bytes(16)),
    'connected_at' => null,
    'updated_by' => $ownerId,
]);
$customer = customer_create([
    'name' => 'Amina Receipt',
    'phone' => '08031110022',
    'gender' => 'female',
    'customer_type' => 'regular',
]);
$hiddenCustomer = customer_create([
    'name' => 'Ngozi Hidden Customer',
    'phone' => '08039990001',
    'gender' => 'female',
    'customer_type' => 'regular',
]);
$garmentId = (string) db_select('fashion_garment_types', ['code' => 'kaftan'], ['limit' => 1])[0]['id'];
$created = order_create([
    'customer_id' => (string) $customer['id'],
    'due_date' => '2026-10-20',
    'urgent' => '',
    'items' => [[
        'garment_type_id' => $garmentId,
        'quantity' => '1',
        'price' => '100',
        'fabric' => 'HIDDEN-FABRIC',
        'notes' => 'HIDDEN-NOTE',
    ]],
]);
$orderId = (string) $created['id'];
$orderNumber = (string) $created['order_number'];
$first = payment_record($orderId, '30', 'cash');
$second = payment_record($orderId, '20', 'transfer');
check_eq($first['receipt_number'] ?? '', 'RC1001', 'the first receipt number is RC1001');
check_eq($second['receipt_number'] ?? '', 'RC1002', 'the second receipt number is RC1002');
check_eq((int) db_select('fashion_receipts', ['id' => (string) $first['receipt_id']], ['limit' => 1])[0]['amount_kobo'], 3000, 'the receipt amount is 3000 kobo');
check_eq((int) db_select('fashion_receipts', ['id' => (string) $first['receipt_id']], ['limit' => 1])[0]['balance_after_kobo'], 7000, 'the balance after the first payment is 7000 kobo');
check_eq((int) db_select('fashion_receipts', ['id' => (string) $second['receipt_id']], ['limit' => 1])[0]['amount_kobo'], 2000, 'the second receipt amount is 2000 kobo');
check_eq((int) db_select('fashion_receipts', ['id' => (string) $second['receipt_id']], ['limit' => 1])[0]['balance_after_kobo'], 5000, 'the balance after the second payment is 5000 kobo');

$reference = 'PSREF-SHOULD-NOT-LEAK';
db_insert('fashion_payment_requests', [
    'order_id' => $orderId,
    'amount_kobo' => 1000,
    'reference' => $reference,
    'authorization_url' => 'https://checkout.paystack.com/test',
    'status' => 'pending',
    'needs_review' => 0,
    'used_placeholder' => 0,
    'created_by' => $ownerId,
    'created_at' => date('Y-m-d H:i:s'),
]);
check_eq(paystack_append_payment(['reference' => $reference], 'return'), 'paid', 'a Paystack payment is recorded');
$paystackPayment = db_select('fashion_payments', ['provider' => 'paystack', 'order_id' => $orderId], ['limit' => 1])[0];
$paystackReceipt = db_select('fashion_receipts', ['payment_id' => (string) $paystackPayment['id']], ['limit' => 1])[0];
check_eq((string) $paystackReceipt['receipt_number'], 'RC1003', 'the Paystack receipt number is RC1003');
check_eq((string) $paystackReceipt['method'], 'paystack', 'a Paystack payment is stored as paystack');
check_eq((int) $paystackReceipt['amount_kobo'], 1000, 'the Paystack receipt amount is 1000 kobo');
check_eq((int) $paystackReceipt['balance_after_kobo'], 4000, 'the balance after the Paystack payment is 4000 kobo');

function receipts_test_page(string $path): string
{
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_SERVER['REQUEST_URI'] = base_path() . $path;
    ob_start();
    router_dispatch('GET', $path);
    return (string) ob_get_clean();
}

function receipts_test_download(string $path): array
{
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_SERVER['REQUEST_URI'] = base_path() . $path;
    ob_start();
    try {
        router_dispatch('GET', $path);
        return ['status' => http_response_code(), 'body' => (string) ob_get_clean()];
    } catch (HttpException $e) {
        ob_end_clean();
        return ['status' => $e->status(), 'body' => $e->getMessage()];
    }
}

function receipts_test_pdf(string $bytes, string $label): void
{
    check(str_starts_with($bytes, '%PDF'), $label . ' starts with %PDF');
    check(str_ends_with($bytes, '%%EOF'), $label . ' ends with %%EOF');
    check(str_contains($bytes, '/DCTDecode'), $label . ' embeds a JPEG');
    check(str_contains($bytes, "\xFF\xD8"), $label . ' contains JPEG data');
    $mark = strrpos($bytes, "startxref\n");
    check($mark !== false, $label . ' has startxref');
    if ($mark === false) {
        return;
    }
    $xref = (int) substr($bytes, $mark + strlen("startxref\n"));
    check(substr($bytes, $xref, 4) === 'xref', $label . ' xref offset points at xref');
    $table = substr($bytes, $xref);
    if (!preg_match('/xref\n0 (\d+)\n/', $table, $countMatch)) {
        check(false, $label . ' has an xref count');
        return;
    }
    preg_match_all('/^(\d{10}) \d{5} n $/m', $table, $rows, PREG_SET_ORDER);
    check(count($rows) === (int) $countMatch[1] - 1, $label . ' lists every object');
    $number = 1;
    foreach ($rows as $row) {
        $at = (int) $row[1];
        check(str_starts_with(substr($bytes, $at), (string) $number . ' 0 obj'), $label . ' object ' . (string) $number . ' offset points at the object');
        $number++;
    }
}

$cashPage = receipts_test_page('/receipts/' . $first['receipt_id']);
check(str_contains($cashPage, 'Receipt Shop'), 'the receipt shows the shop name');
check(str_contains($cashPage, '08032220011'), 'the receipt shows the shop phone');
check(str_contains($cashPage, 'Receipt number: RC1001'), 'the receipt shows RC1001');
check(str_contains($cashPage, 'Amina Receipt'), 'the receipt shows the customer');
check(str_contains($cashPage, $orderNumber), 'the receipt shows the order number');
check(str_contains($cashPage, 'Amount: ₦30.00'), 'the receipt shows ₦30.00');
check(str_contains($cashPage, 'Method: Cash'), 'the receipt shows cash');
check(str_contains($cashPage, 'Balance after this payment: ₦70.00'), 'the receipt shows the balance after this payment');
check(str_contains($cashPage, 'Print / Save as PDF'), 'the receipt can be printed');
$paidOn = message_format_date(substr((string) db_select('fashion_payments', ['id' => (string) $first['id']], ['limit' => 1])[0]['created_at'], 0, 10), 'en');
check(str_contains($cashPage, $paidOn), 'the receipt date uses the shop calendar');
foreach (['Ngozi Hidden Customer', '08031110022', '08039990001', 'PSREF-SHOULD-NOT-LEAK', 'pk_test_SHOULD_NOT_LEAK', 'SECRETKEYVALUE', 'HIDDEN-FABRIC', 'HIDDEN-NOTE', (string) $hiddenCustomer['id']] as $hidden) {
    check(!str_contains($cashPage, $hidden), 'the receipt hides ' . $hidden);
}
$transferPage = receipts_test_page('/receipts/' . $second['receipt_id']);
check(str_contains($transferPage, 'Method: Transfer'), 'the receipt shows transfer');
check(str_contains($transferPage, 'Balance after this payment: ₦50.00'), 'the second receipt keeps its own balance');
$paystackPage = receipts_test_page('/receipts/' . $paystackReceipt['id']);
check(str_contains($paystackPage, 'Method: Paystack'), 'the receipt shows Paystack');
check(!str_contains($paystackPage, 'PSREF-SHOULD-NOT-LEAK'), 'the Paystack receipt hides the reference');
check(!str_contains($paystackPage, 'SECRETKEYVALUE'), 'the Paystack receipt hides the secret key');

$invoicePage = receipts_test_page('/orders/' . $orderId . '/invoice');
check(str_contains($invoicePage, 'Kaftan x 1'), 'the invoice describes the work');
check(str_contains($invoicePage, 'Total: ₦100.00'), 'the invoice total is ₦100.00');
check(str_contains($invoicePage, 'Deposit due: ₦50.00'), 'the invoice deposit due is ₦50.00');
check(str_contains($invoicePage, 'Paid so far: ₦60.00'), 'the invoice paid so far is ₦60.00');
check(str_contains($invoicePage, 'Balance: ₦40.00'), 'the invoice balance is ₦40.00');
check(!str_contains($invoicePage, 'RC1001'), 'the invoice is not given a receipt number');

$_SERVER['REQUEST_URI'] = base_path() . '/orders/' . $orderId;
ob_start();
orders_show($orderId);
$orderPage = (string) ob_get_clean();
check(str_contains($orderPage, '>Receipt<'), 'the order page has a Receipt button');
check(str_contains($orderPage, url('/orders/' . $orderId . '/invoice')), 'the order page has an Invoice button');
check(str_contains($orderPage, 'Share on WhatsApp'), 'the order page has Share on WhatsApp');
check(str_contains($orderPage, '>Download PNG<'), 'the order page has Download PNG');
check(str_contains($orderPage, '>Download PDF<'), 'the order page has Download PDF');
check(str_contains($orderPage, '>Print</a>'), 'the order page has Print');
check(str_contains($orderPage, '>Share</button>'), 'the order page has Share');
check(str_contains($orderPage, 'navigator.share'), 'Share uses the browser share call');
check(str_contains($orderPage, 'navigator.canShare'), 'Share checks that the browser can share a file');

$_POST = ['csrf' => csrf_token()];
$_SERVER['REQUEST_METHOD'] = 'POST';
$_SERVER['REQUEST_URI'] = base_path() . '/receipts/' . $first['receipt_id'] . '/share';
try {
    router_dispatch('POST', '/receipts/' . $first['receipt_id'] . '/share');
    check(false, 'sharing a receipt redirects');
} catch (RedirectException $e) {
    check_eq($e->path(), '/receipts/' . $first['receipt_id'], 'sharing a receipt returns to the receipt');
}
$sharedRows = db_query(
    'SELECT body FROM fashion_messages WHERE tenant_id = :tenant_id AND template_key = :template_key AND order_id = :order_id ORDER BY created_at DESC LIMIT 1',
    ['template_key' => 'receipt_share', 'order_id' => $orderId]
);
$shared = $sharedRows[0];
check(str_contains((string) $shared['body'], '₦30.00'), 'the WhatsApp text includes the receipt amount');
check(str_contains((string) $shared['body'], '/track/'), 'the WhatsApp text includes the tracking link');
check(!str_contains((string) $shared['body'], '.pdf'), 'the WhatsApp text is not an attachment');
check(!str_contains((string) $shared['body'], 'PSREF-SHOULD-NOT-LEAK'), 'the WhatsApp text hides the reference');
$sharedPage = receipts_test_page('/receipts/' . $first['receipt_id']);
check(str_contains($sharedPage, 'https://wa.me/'), 'the receipt page opens the prepared WhatsApp text');

$font = paper_font_path();
$letterWidths = [];
foreach (['ɓ', 'ɗ', 'ƙ', 'ƴ'] as $letter) {
    $box = imagettfbbox(48, 0, $font, $letter);
    check($box !== false, 'the font draws ' . $letter);
    $letterWidths[] = $box === false ? 0 : abs($box[2] - $box[0]);
}
check(count(array_unique($letterWidths)) === 4, 'Hausa letters ɓ ɗ ƙ ƴ have their own shapes');
$paperSource = (string) file_get_contents(dirname(__DIR__) . '/app/domain/paper.php');
check(str_contains($paperSource, "header('Cache-Control: no-store');"), 'paper files send Cache-Control: no-store');
check(!str_contains($paperSource, 'file_put_contents'), 'paper files are not stored');
check_eq(paper_filename('RC1001', 'png'), 'RC1001.png', 'the PNG filename is only the receipt number');
check_eq(paper_filename((string) $orderNumber, 'pdf'), $orderNumber . '.pdf', 'the PDF filename is only the order number');

$loaded = receipt_load((string) $first['receipt_id']);
$normalDoc = paper_receipt_document($loaded);
check(in_array('Amount: ' . format_naira(3000), $normalDoc['lines'], true), 'the drawn amount is ₦30.00 from 3000 kobo');
check(in_array('Balance after this payment: ' . format_naira(7000), $normalDoc['lines'], true), 'the drawn balance is ₦70.00 from 7000 kobo');
$invoiceDoc = paper_invoice_document(invoice_load($orderId));
check(in_array('Total: ' . format_naira(10000), $invoiceDoc['lines'], true), 'the drawn total is ₦100.00 from 10000 kobo');
check(in_array('Deposit due: ' . format_naira(5000), $invoiceDoc['lines'], true), 'the drawn deposit is ₦50.00 from 5000 kobo');
check(in_array('Paid so far: ' . format_naira(6000), $invoiceDoc['lines'], true), 'the drawn paid amount is ₦60.00 from 6000 kobo');
check(in_array('Balance: ' . format_naira(4000), $invoiceDoc['lines'], true), 'the drawn invoice balance is ₦40.00 from 4000 kobo');
foreach (['Ngozi Hidden Customer', '08039990001', 'PSREF-SHOULD-NOT-LEAK', 'pk_test_SHOULD_NOT_LEAK', 'SECRETKEYVALUE', 'HIDDEN-FABRIC', 'HIDDEN-NOTE'] as $hidden) {
    check(!str_contains(implode("\n", $normalDoc['lines']), $hidden), 'the drawn receipt hides ' . $hidden);
    check(!str_contains(implode("\n", $invoiceDoc['lines']), $hidden), 'the drawn invoice hides ' . $hidden);
}

$normalPng = receipts_test_download('/receipts/' . $first['receipt_id'] . '.png');
check_eq($normalPng['status'], 200, 'the staff receipt PNG returns 200');
check_eq($normalPng['body'], paper_png($normalDoc), 'the staff receipt PNG is the shared drawing');
$pngInfo = getimagesizefromstring($normalPng['body']);
check($pngInfo !== false && $pngInfo[0] === 1240 && $pngInfo[1] === 1754, 'the receipt PNG is A4 at 150 dpi');
$normalPdf = receipts_test_download('/receipts/' . $first['receipt_id'] . '.pdf');
check_eq($normalPdf['status'], 200, 'the staff receipt PDF returns 200');
receipts_test_pdf($normalPdf['body'], 'the receipt PDF');
check(str_contains($normalPdf['body'], '₦30.00'), 'the receipt PDF shows ₦30.00');
check(str_contains($normalPdf['body'], '₦70.00'), 'the receipt PDF shows the balance after this payment');
check(!str_contains($normalPdf['body'], 'VOID'), 'the open receipt PDF has no VOID mark');
check(!str_contains($normalPdf['body'], 'PSREF-SHOULD-NOT-LEAK'), 'the receipt PDF hides the reference');
check(!str_contains($normalPdf['body'], 'SECRETKEYVALUE'), 'the receipt PDF hides the secret key');
check(!str_contains($normalPdf['body'], 'Ngozi Hidden Customer'), 'the receipt PDF hides the other customer');
$invoicePdf = receipts_test_download('/orders/' . $orderId . '/invoice.pdf');
receipts_test_pdf($invoicePdf['body'], 'the invoice PDF');
check(str_contains($invoicePdf['body'], '₦100.00'), 'the invoice PDF shows ₦100.00');
check(str_contains($invoicePdf['body'], 'Kaftan x 1'), 'the invoice PDF describes the work');

$GLOBALS['gmingo_paper_ready'] = false;
$missingDraw = receipts_test_download('/receipts/' . $first['receipt_id'] . '.png');
check_eq($missingDraw['status'], 503, 'missing drawing tools return 503');
check(str_contains($missingDraw['body'], 'The drawing tools are missing on this server. Use Print / Save as PDF on the page instead.'), 'missing drawing tools name the print page');
check(!str_starts_with($missingDraw['body'], "\x89PNG"), 'missing drawing tools do not return a broken PNG');
unset($GLOBALS['gmingo_paper_ready']);
$printPage = receipts_test_page('/receipts/' . $first['receipt_id']);
check(str_contains($printPage, 'Print / Save as PDF'), 'the print page still works when drawing is unavailable');

$token = tracking_issue($orderId);
$_SERVER['REMOTE_ADDR'] = '198.51.100.40';
$publicPdf = receipts_test_download('/track/' . $token . '/receipt/' . $first['id'] . '.pdf');
check_eq($publicPdf['status'], 200, 'the public receipt PDF returns 200');
check_eq($publicPdf['body'], $normalPdf['body'], 'the public receipt PDF uses the same drawing');

payment_reverse($orderId, (string) $first['id'], 'wrong amount');
$voided = receipts_test_page('/receipts/' . $first['receipt_id']);
check(str_contains($voided, 'VOID'), 'a reversed payment shows VOID');
check(str_contains($voided, 'Amount: ₦30.00'), 'a voided receipt keeps ₦30.00');
check(str_contains($voided, 'Balance after this payment: ₦70.00'), 'a voided receipt keeps the original balance');
check_eq((int) db_select('fashion_receipts', ['id' => (string) $first['receipt_id']], ['limit' => 1])[0]['amount_kobo'], 3000, 'voiding does not change the stored amount');
$voidDoc = paper_receipt_document(receipt_load((string) $first['receipt_id']));
check(in_array('Amount: ' . format_naira(3000), $voidDoc['lines'], true), 'the voided drawing keeps ₦30.00 from 3000 kobo');
check(in_array('VOID', $voidDoc['lines'], true), 'the voided drawing says VOID');
$voidPng = receipts_test_download('/receipts/' . $first['receipt_id'] . '.png');
check($voidPng['body'] !== $normalPng['body'], 'a voided receipt PNG differs from the normal one');
check(str_starts_with($voidPng['body'], "\x89PNG"), 'the voided receipt PNG is still a PNG');
$voidPdf = receipts_test_download('/receipts/' . $first['receipt_id'] . '.pdf');
check(str_contains($voidPdf['body'], 'VOID'), 'the voided receipt PDF shows VOID');
check(str_contains($voidPdf['body'], '₦30.00'), 'the voided receipt PDF keeps ₦30.00');
check($voidPdf['body'] !== $normalPdf['body'], 'a voided receipt PDF differs from the normal one');
$afterVoid = receipts_test_page('/orders/' . $orderId . '/invoice');
check(str_contains($afterVoid, 'Paid so far: ₦30.00'), 'the invoice paid so far follows the reversal');
check(str_contains($afterVoid, 'Balance: ₦70.00'), 'the invoice balance follows the reversal');

$_COOKIE['lang'] = 'ha';
$hausa = receipts_test_page('/receipts/' . $second['receipt_id']);
check(str_contains($hausa, 'Lambar rasit: RC1002'), 'the receipt follows the staff Hausa choice');
$_COOKIE['lang'] = 'en';

check_throws(
    fn () => db_update('fashion_receipts', ['amount_kobo' => 1], ['id' => (string) $first['receipt_id']]),
    RuntimeException::class,
    'This table is append-only.'
);

$other = business_register([
    'business_name' => 'Other Receipt Shop',
    'owner_name' => 'Other Owner',
    'email' => 'receipts-other@example.com',
    'password' => 'password',
    'phone' => '08035550022',
    'company_type' => 'tailor',
]);
check_throws(
    fn () => receipt_show((string) $first['receipt_id']),
    HttpException::class,
    'Page not found'
);
check_throws(
    fn () => invoice_show($orderId),
    HttpException::class,
    'Page not found'
);
$refusedPng = receipts_test_download('/receipts/' . $first['receipt_id'] . '.png');
check_eq($refusedPng['status'], 404, 'another shop receipt file returns 404');
check_eq($refusedPng['body'], 'Page not found', 'another shop is told the receipt file is not found');
check(!str_starts_with($refusedPng['body'], "\x89PNG"), 'another shop does not receive this receipt PNG');
$refusedPdf = receipts_test_download('/orders/' . $orderId . '/invoice.pdf');
check_eq($refusedPdf['status'], 404, 'another shop invoice file returns 404');
check_eq($refusedPdf['body'], 'Page not found', 'another shop is told the invoice file is not found');
$otherCustomer = customer_create([
    'name' => 'Other Shop Customer',
    'phone' => '08036660011',
    'gender' => 'male',
    'customer_type' => 'regular',
]);
$otherGarment = (string) db_select('fashion_garment_types', ['code' => 'kaftan'], ['limit' => 1])[0]['id'];
$otherOrder = order_create([
    'customer_id' => (string) $otherCustomer['id'],
    'due_date' => '2026-11-02',
    'urgent' => '',
    'items' => [[
        'garment_type_id' => $otherGarment,
        'quantity' => '1',
        'price' => '15',
        'fabric' => '',
        'notes' => '',
    ]],
]);
$otherPaid = payment_record((string) $otherOrder['id'], '5', 'cash');
check_eq($otherPaid['receipt_number'] ?? '', 'RC1001', 'another business starts its own receipt numbers at RC1001');
$otherToken = tracking_issue((string) $otherOrder['id']);
$_SERVER['REMOTE_ADDR'] = '198.51.100.41';
$foreignReceipt = receipts_test_download('/track/' . $otherToken . '/receipt/' . $first['id'] . '.pdf');
$_SERVER['REMOTE_ADDR'] = '198.51.100.42';
$unknownFile = receipts_test_download('/track/not-a-real-token/receipt/' . $first['id'] . '.pdf');
check_eq($foreignReceipt['status'], 404, 'another shop token does not open this receipt');
check_eq($foreignReceipt['body'], $unknownFile['body'], 'another shop token gets the same not found page');
check(!str_contains($foreignReceipt['body'], 'RC1001'), 'another shop token does not show this receipt number');
check(!str_starts_with($foreignReceipt['body'], '%PDF'), 'another shop token does not return this receipt file');
$_SERVER['REMOTE_ADDR'] = '198.51.100.43';
$foreignInvoice = receipts_test_download('/track/' . $otherToken . '/invoice.pdf');
check_eq($foreignInvoice['status'], 200, 'the other shop token returns its own invoice');
check(str_contains($foreignInvoice['body'], (string) $otherOrder['order_number']), 'the other shop invoice shows its order number');
check(str_contains($foreignInvoice['body'], '08035550022'), 'the other shop invoice shows its phone');
check(!str_contains($foreignInvoice['body'], '08032220011'), 'another shop token does not return this shop phone');
check(!str_contains($foreignInvoice['body'], '₦70.00'), 'another shop token does not return this balance');

$today = DateTimeImmutable::createFromFormat('!Y-m-d', app_today(), app_timezone());
$beyond = $today->modify('-' . (string) (tracking_grace_days() + 1) . ' days')->format('Y-m-d');
db_update('fashion_tenants', ['subscription_ends_on' => $beyond], ['id' => (string) $other['tenant_id']]);
try {
    payment_record((string) $otherOrder['id'], '1', 'cash');
    check(false, 'an ended subscription blocks a new receipt');
} catch (HttpException $e) {
    check_eq($e->status(), 402, 'an ended subscription returns 402');
    check_eq($e->getMessage(), 'The subscription has ended.', 'an ended subscription says the subscription has ended');
}
db_update('fashion_tenants', ['subscription_ends_on' => null], ['id' => (string) $other['tenant_id']]);

$raceShop = business_register([
    'business_name' => 'Receipt Race Shop',
    'owner_name' => 'Race Owner',
    'email' => 'receipt-race@example.com',
    'password' => 'password',
    'phone' => '08037770011',
    'company_type' => 'tailor',
]);
$raceCustomer = customer_create([
    'name' => 'Race Customer',
    'phone' => '08038880022',
    'gender' => 'female',
    'customer_type' => 'regular',
]);
$raceGarment = (string) db_select('fashion_garment_types', ['code' => 'kaftan'], ['limit' => 1])[0]['id'];
$raceOrder = order_create([
    'customer_id' => (string) $raceCustomer['id'],
    'due_date' => '2026-11-03',
    'urgent' => '',
    'items' => [[
        'garment_type_id' => $raceGarment,
        'quantity' => '1',
        'price' => '1000',
        'fabric' => '',
        'notes' => '',
    ]],
]);
$start = microtime(true) + 2.5;
$workers = 8;
$processes = [];
for ($i = 0; $i < $workers; $i++) {
    $pipes = [];
    $process = proc_open(
        [
            PHP_BINARY,
            __DIR__ . '/receipt_race_worker.php',
            (string) $raceShop['tenant_id'],
            (string) $raceShop['user_id'],
            (string) $raceOrder['id'],
            sprintf('%.6F', $start),
        ],
        [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ],
        $pipes
    );
    check(is_resource($process), 'a receipt race worker starts');
    fclose($pipes[0]);
    $processes[] = ['process' => $process, 'out' => $pipes[1], 'err' => $pipes[2]];
}
$issued = [];
foreach ($processes as $worker) {
    $issued[] = trim((string) stream_get_contents($worker['out']));
    $error = trim((string) stream_get_contents($worker['err']));
    $code = proc_close($worker['process']);
    check($code === 0, 'a receipt race worker finishes' . ($error === '' ? '' : ' (' . $error . ')'));
}
$expected = [];
for ($n = 1001; $n <= 1008; $n++) {
    $expected[] = 'RC' . (string) $n;
}
sort($issued);
sort($expected);
check_eq($issued, $expected, 'concurrent receipts receive eight different numbers');
check_eq((int) db_select('fashion_receipt_counters')[0]['last_number'], 1008, 'the receipt counter lands on the last issued number');
