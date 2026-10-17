<?php

declare(strict_types=1);

require_once __DIR__ . '/support.php';
require_once dirname(__DIR__) . '/app/domain/accounts.php';
require_once dirname(__DIR__) . '/app/domain/customers.php';
require_once dirname(__DIR__) . '/app/domain/orders.php';
require_once dirname(__DIR__) . '/app/domain/messages.php';
require_once dirname(__DIR__) . '/app/domain/deposits.php';
require_once dirname(__DIR__) . '/app/domain/fittings.php';
require_once dirname(__DIR__) . '/app/domain/stages.php';
require_once dirname(__DIR__) . '/app/fashion/controllers/orders.php';
require_once dirname(__DIR__) . '/app/fashion/controllers/messages.php';

tests_reset();
$_COOKIE['lang'] = 'en';

$appRoot = dirname(__DIR__) . '/app';
$phpFiles = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($appRoot));
foreach ($phpFiles as $phpFile) {
    if (!$phpFile->isFile() || $phpFile->getExtension() !== 'php') {
        continue;
    }
    $code = (string) file_get_contents($phpFile->getPathname());
    $name = substr($phpFile->getPathname(), strlen($appRoot) + 1);
    check(
        preg_match("/db_update\\(\\s*['\"]fashion_messages['\"]/", $code) !== 1
        && preg_match("/db_delete\\(\\s*['\"]fashion_messages['\"]/", $code) !== 1
        && preg_match('/\bUPDATE\s+fashion_messages\b/i', $code) !== 1
        && preg_match('/\bDELETE\s+FROM\s+fashion_messages\b/i', $code) !== 1,
        $name . ' does not update or delete fashion_messages'
    );
}
foreach ([
    'domain/messages.php',
    'fashion/controllers/messages.php',
    'fashion/views/messages/index.php',
    'fashion/views/messages/show.php',
    'fashion/views/messages/templates.php',
] as $logged) {
    $source = (string) file_get_contents($appRoot . '/' . $logged);
    check(!str_contains($source, 'error_log'), $logged . ' does not log');
}

check_eq(message_whatsapp_digits('08031234567'), '2348031234567', '0803 numbers become 234803 numbers');
check_eq(message_whatsapp_digits('+2348031234567'), '2348031234567', '+234803 numbers become 234803 numbers');
check_eq(message_whatsapp_digits('8031234567'), '2348031234567', '803 numbers become 234803 numbers');
check(message_whatsapp_digits('12345') === null, 'a bad number is rejected');
check_eq(message_whatsapp_href('12345', 'Hello'), null, 'a bad number has no WhatsApp link');
check_eq(
    message_whatsapp_href('08031234567', 'Hello Amina'),
    'https://wa.me/2348031234567?text=Hello%20Amina',
    'the wa.me link carries the converted number and the text'
);
check(in_array('yo', message_languages(), true), 'the template languages include Yoruba');
check(in_array('ig', message_languages(), true), 'the template languages include Igbo');
check(in_array('pcm', message_languages(), true), 'the template languages include Pidgin');
check(message_default_body('order_confirmation', 'yo') === null, 'Yoruba is not machine-translated');
check(message_default_body('order_confirmation', 'ig') === null, 'Igbo is not machine-translated');
check(message_default_body('order_confirmation', 'pcm') === null, 'Pidgin is not machine-translated');

$shop = business_register([
    'business_name' => 'Message Shop',
    'owner_name' => 'Amina Bello',
    'email' => 'messages-a@example.com',
    'password' => 'password',
    'phone' => '08032220001',
    'company_type' => 'tailor',
]);
$ownerId = (string) $shop['user_id'];
$tenantId = (string) $shop['tenant_id'];
$customer = customer_create([
    'name' => 'Message Customer',
    'phone' => '08031234567',
    'gender' => 'female',
    'customer_type' => 'regular',
]);
$customerId = (string) $customer['id'];
$garmentId = (string) db_select('fashion_garment_types', ['code' => 'kaftan'], ['limit' => 1])[0]['id'];

$values = [
    'customer_name' => 'Amina Bello',
    'shop_name' => 'Message Shop',
    'order_no' => 'TK1001',
    'due_date' => '2026-10-16',
    'balance_kobo' => 123450000,
    'deposit_due_kobo' => 10000050,
    'fitting_time' => '2026-10-16 15:00',
    'tracking_link' => '/gmingo_fashion/orders/abc',
];
$englishMap = [
    '{customer_name}' => 'Amina Bello',
    '{shop_name}' => 'Message Shop',
    '{order_no}' => 'TK1001',
    '{due_date}' => '16 October 2026',
    '{balance}' => '₦1,234,500.00',
    '{deposit_due}' => '₦100,000.50',
    '{fitting_time}' => '16 October 2026 15:00',
    '{tracking_link}' => '/gmingo_fashion/orders/abc',
];
$hausaMap = $englishMap;
$hausaMap['{due_date}'] = '16 Oktoba 2026';
$hausaMap['{fitting_time}'] = '16 Oktoba 2026 15:00';
$english = message_fill('order_confirmation', 'en', $values);
$hausa = message_fill('order_confirmation', 'ha', $values);
check_eq($english, strtr((string) message_default_body('order_confirmation', 'en'), $englishMap), 'English placeholders are replaced');
check_eq($hausa, strtr((string) message_default_body('order_confirmation', 'ha'), $hausaMap), 'Hausa placeholders are replaced');
check(str_contains($english, '16 October 2026'), 'English dates use English month names');
check(str_contains($hausa, '16 Oktoba 2026'), 'Hausa dates use Hausa month names');
check(str_contains($english, '₦1,234,500.00'), 'balances use Naira with a thousands separator');
check(str_contains($english, '₦100,000.50'), 'deposit amounts use Naira with a thousands separator');
check(!str_contains($english, '{customer_name}'), 'the customer name placeholder is gone');
check(!str_contains($hausa, '{balance}'), 'the balance placeholder is gone in Hausa');
check_eq(message_fill('order_confirmation', 'yo', $values), $english, 'a missing language falls back to English');
check_eq(message_fill('order_confirmation', 'ig', $values), $english, 'a missing Igbo template falls back to English');
check_eq(message_fill('order_confirmation', 'pcm', $values), $english, 'a missing Pidgin template falls back to English');

$defaultEnglish = message_default_body('order_confirmation', 'en');
$saved = message_template_save('order_confirmation', 'en', 'Hi {customer_name}');
check_eq($saved, [], 'the owner can save a shop template');
check_eq(message_default_body('order_confirmation', 'en'), $defaultEnglish, 'the default English template stays');
check_eq(message_fill('order_confirmation', 'en', $values), 'Hi Amina Bello', 'the shop English copy is used');
check_eq(message_template_save('order_confirmation', 'en', '   '), [], 'the owner can clear a shop template');
check_eq(message_fill('order_confirmation', 'en', $values), $english, 'clearing the shop copy uses the English default');
check_eq(message_template_save('order_confirmation', 'yo', 'YO {customer_name}'), [], 'Yoruba can be stored');
check_eq(message_fill('order_confirmation', 'yo', $values), 'YO Amina Bello', 'a saved Yoruba copy is used');
check_eq(message_template_save('order_confirmation', 'yo', ''), [], 'the owner can clear Yoruba');
check_eq(message_fill('order_confirmation', 'yo', $values), $english, 'clearing Yoruba falls back to English');
$long = message_template_save('order_confirmation', 'en', str_repeat('a', 2001));
check_eq($long['error'], 'message_template_long', 'a template over 2,000 characters is refused');
check_eq(message_default_body('order_confirmation', 'en'), $defaultEnglish, 'a refused edit leaves the default in place');

$_SERVER['REQUEST_URI'] = base_path() . '/messages/templates';
ob_start();
messages_templates_form();
$templatesPage = (string) ob_get_clean();
check(str_contains($templatesPage, 'Hello {customer_name}, {shop_name} has order {order_no}.'), 'the editor shows the English default');
check(str_contains($templatesPage, 'Sannu {customer_name}, {shop_name} yana da oda {order_no}.'), 'the editor shows the Hausa default');
check(
    str_contains($templatesPage, 'name="body[order_confirmation][yo]" maxlength="2000" rows="4"></textarea>'),
    'Yoruba starts empty'
);
check(preg_match('/<title>([^<]*)<\/title>/', $templatesPage, $templateTitle) === 1, 'the template page has a title');
check(isset($templateTitle[1]) && !str_contains($templateTitle[1], '08031234567'), 'the template title does not contain the phone number');

db_update('fashion_users', ['role' => 'staff'], ['id' => $ownerId]);
check_throws(
    static fn () => messages_templates_form(),
    HttpException::class,
    'That record is not in this shop.'
);
check_throws(
    static fn () => message_template_save('order_confirmation', 'en', 'Nope {customer_name}'),
    HttpException::class,
    'That record is not in this shop.'
);
db_update('fashion_users', ['role' => 'owner'], ['id' => $ownerId]);

function messages_test_order(string $customerId, string $garmentId): array
{
    return order_create([
        'customer_id' => $customerId,
        'due_date' => '2026-10-16',
        'urgent' => '',
        'items' => [[
            'garment_type_id' => $garmentId,
            'quantity' => '1',
            'price' => '100',
            'fabric' => 'ankara',
            'notes' => '',
        ]],
    ]);
}

$orders = [];
for ($i = 0; $i < 26; $i++) {
    $orders[] = messages_test_order($customerId, $garmentId);
}
$orderId = (string) $orders[0]['id'];
check_eq($orders[0]['order_number'], 'TK1001', 'the first message order is TK1001');
check_eq($orders[25]['order_number'], 'TK1026', 'the 26th message order is TK1026');
check_eq(message_prepared_total('order_confirmation'), 26, 'each order queues one confirmation');
check_eq(message_prepared_total('deposit_reminder'), 0, 'no deposit message when the deposit is off');
check_eq(count(message_to_send_list(1, '')), 25, 'the to send page lists 25 messages');
check_eq(count(message_to_send_list(2, '')), 1, 'the second to send page lists the remaining message');
$filtered = message_to_send_list(1, $orderId);
check_eq(count($filtered), 1, 'the order filter lists one message');
check_eq($filtered[0]['order_id'], $orderId, 'the filtered message belongs to that order');
check_eq(message_prepared_count($orderId, 'order_confirmation'), 1, 'the first order has one confirmation');
ob_start();
orders_show($orderId);
ob_end_clean();
$_GET = [];
$_SERVER['REQUEST_URI'] = base_path() . '/messages';
ob_start();
messages_index();
ob_end_clean();
check_eq(message_prepared_total('order_confirmation'), 26, 'reloading the pages does not queue another confirmation');

$_SERVER['REQUEST_URI'] = base_path() . '/orders/' . $orderId;
ob_start();
orders_show($orderId);
$orderPage = (string) ob_get_clean();
check(str_contains($orderPage, 'Send ready message'), 'the order page can send a ready message');
check(str_contains($orderPage, 'Running late'), 'the order page can record that the order is running late');
check(str_contains($orderPage, 'https://wa.me/2348031234567?text='), 'the order page opens WhatsApp with the converted number');
check(!str_contains($orderPage, '08031234567'), 'the order page does not print the local phone number');
check(preg_match('/<title>([^<]*)<\/title>/', $orderPage, $orderTitle) === 1, 'the order page has a title');
check(
    isset($orderTitle[1]) && !str_contains($orderTitle[1], '08031234567') && !str_contains($orderTitle[1], '2348031234567'),
    'the order title does not contain the phone number'
);

$_GET = ['order' => $orderId];
$_SERVER['REQUEST_URI'] = base_path() . '/messages?order=' . $orderId;
ob_start();
messages_index();
$filteredPage = (string) ob_get_clean();
check(str_contains($filteredPage, 'TK1001'), 'the messages page shows the filtered order');
check(!str_contains($filteredPage, 'TK1026'), 'the messages page hides other orders');
check(str_contains($filteredPage, 'To send'), 'the messages page has a To send queue');
check(str_contains($filteredPage, 'Sent'), 'the messages page has a Sent history');
check(!str_contains($filteredPage, '08031234567'), 'the messages page does not print the local phone number');
check(preg_match('/<title>([^<]*)<\/title>/', $filteredPage, $messagesTitle) === 1, 'the messages page has a title');
check(
    isset($messagesTitle[1]) && !str_contains($messagesTitle[1], '2348031234567'),
    'the messages title does not contain the phone number'
);

$preparedRows = db_query(
    'SELECT id FROM fashion_messages
     WHERE tenant_id = :tenant_id AND template_key = :template_key AND status = :status
     ORDER BY created_at ASC, id ASC',
    ['template_key' => 'order_confirmation', 'status' => 'prepared']
);
check_eq(count($preparedRows), 26, '26 confirmation rows were prepared');
$firstMessageId = (string) $preparedRows[0]['id'];
$_SERVER['REQUEST_URI'] = base_path() . '/messages/' . $firstMessageId;
ob_start();
messages_show($firstMessageId);
$showPage = (string) ob_get_clean();
check(str_contains($showPage, 'https://wa.me/2348031234567?text='), 'the message page opens WhatsApp with the text filled in');
check(str_contains($showPage, 'Mark as sent'), 'the message page can mark the text as sent');
check(preg_match('/<title>([^<]*)<\/title>/', $showPage, $showTitle) === 1, 'the message page has a title');
check(
    isset($showTitle[1]) && !str_contains($showTitle[1], '08031234567') && !str_contains($showTitle[1], '2348031234567'),
    'the message title does not contain the phone number'
);

$before = db_query(
    'SELECT id, status, body, user_id, created_at FROM fashion_messages WHERE tenant_id = :tenant_id AND id = :id',
    ['id' => $firstMessageId]
)[0];
$GLOBALS['gmingo_now'] = '2026-10-16 15:04:00';
$marked = message_mark_sent($firstMessageId);
unset($GLOBALS['gmingo_now']);
check(!isset($marked['error']), 'mark as sent adds a row');
$after = db_query(
    'SELECT id, status, body, user_id, created_at FROM fashion_messages WHERE tenant_id = :tenant_id AND id = :id',
    ['id' => $firstMessageId]
)[0];
check_eq($after, $before, 'mark as sent leaves the prepared row unchanged');
$sentRows = db_query(
    'SELECT status, prepared_id, body, created_at, user_id FROM fashion_messages
     WHERE tenant_id = :tenant_id AND prepared_id = :prepared_id',
    ['prepared_id' => $firstMessageId]
);
check_eq(count($sentRows), 1, 'mark as sent adds one status row');
check_eq((string) $sentRows[0]['status'], 'marked_sent', 'the new row is marked sent');
check_eq((string) $sentRows[0]['body'], (string) $before['body'], 'the new row keeps the prepared text');
check_eq((string) $sentRows[0]['created_at'], '2026-10-16 15:04:00', 'mark as sent records the time');
check_eq((string) $sentRows[0]['user_id'], $ownerId, 'mark as sent records who did it');
$repeat = message_mark_sent($firstMessageId);
check_eq($repeat['error'], 'already_marked_sent', 'marking the same message again returns already_marked_sent');
$sentAgain = db_query(
    'SELECT id FROM fashion_messages WHERE tenant_id = :tenant_id AND prepared_id = :prepared_id',
    ['prepared_id' => $firstMessageId]
);
check_eq(count($sentAgain), 1, 'a second mark does not add a row');
check_throws(
    static fn () => db_update('fashion_messages', ['status' => 'marked_sent'], ['id' => $firstMessageId]),
    RuntimeException::class,
    'This table is append-only.'
);
check_throws(
    static fn () => db_delete('fashion_messages', ['id' => $firstMessageId]),
    RuntimeException::class,
    'This table is append-only.'
);
$still = db_query(
    'SELECT status FROM fashion_messages WHERE tenant_id = :tenant_id AND id = :id',
    ['id' => $firstMessageId]
)[0];
check_eq((string) $still['status'], 'prepared', 'the prepared row is still prepared');

$added = 0;
foreach ($preparedRows as $preparedRow) {
    $result = message_mark_sent((string) $preparedRow['id']);
    if (!isset($result['error'])) {
        $added++;
    } else {
        check_eq($result['error'], 'already_marked_sent', 'repeat marks are refused');
    }
}
check_eq($added, 25, 'the other confirmations are marked sent once');
check_eq(message_sent_count(''), 26, 'sent history has 26 messages');
check_eq(count(message_sent_list(1, '')), 25, 'the sent page lists 25 messages');
check_eq(count(message_sent_list(2, '')), 1, 'the second sent page lists the remaining message');
$_GET = [];
$_SERVER['REQUEST_URI'] = base_path() . '/messages';
ob_start();
messages_index();
$sentPage = (string) ob_get_clean();
check(str_contains($sentPage, 'sent_page=2'), 'sent history links to the next page');

$GLOBALS['gmingo_now'] = '2026-10-16 12:00:00';
$fit = fitting_schedule($orderId, [
    'date' => '2026-10-20',
    'time' => '15:00',
    'fitting_type' => 'first',
    'note' => '',
]);
unset($GLOBALS['gmingo_now']);
check(!isset($fit['error']), 'the fitting is booked');
check_eq(message_prepared_count($orderId, 'fitting_booking'), 1, 'booking a fitting queues one message');
ob_start();
orders_show($orderId);
ob_end_clean();
check_eq(message_prepared_count($orderId, 'fitting_booking'), 1, 'reloading the order does not queue another fitting message');
$GLOBALS['gmingo_now'] = '2026-10-16 12:00:00';
$fitAgain = fitting_schedule($orderId, [
    'date' => '2026-10-21',
    'time' => '11:00',
    'fitting_type' => 'second',
    'note' => '',
]);
unset($GLOBALS['gmingo_now']);
check(!isset($fitAgain['error']), 'a second fitting is booked');
check_eq(message_prepared_count($orderId, 'fitting_booking'), 2, 'a second fitting queues its own message');
$fitBodies = db_query(
    'SELECT body FROM fashion_messages
     WHERE tenant_id = :tenant_id AND order_id = :order_id AND template_key = :template_key',
    ['order_id' => $orderId, 'template_key' => 'fitting_booking']
);
$fitText = implode("\n", array_map(static fn (array $row): string => (string) $row['body'], $fitBodies));
check(str_contains($fitText, '20 October 2026 15:00'), 'the first fitting message uses 20 October 2026 15:00');
check(str_contains($fitText, '21 October 2026 11:00'), 'the second fitting message uses 21 October 2026 11:00');

$guard = 0;
while ($guard < 8) {
    $latest = stage_latest($orderId);
    if ($latest !== null && (string) $latest['role'] === 'ready') {
        break;
    }
    $moved = stage_advance($orderId);
    check(!isset($moved['error']), 'the order can move toward ready');
    $guard++;
}
$onReady = stage_latest($orderId);
check_eq(is_array($onReady) ? (string) $onReady['role'] : '', 'ready', 'the order is on the ready role');
check_eq(message_prepared_count($orderId, 'ready_for_pickup'), 1, 'reaching the ready role queues one ready message');
$delivered = stage_advance($orderId);
check_eq((string) ($delivered['code'] ?? ''), 'delivered', 'the next move is delivered');
check_eq(message_prepared_count($orderId, 'ready_for_pickup'), 1, 'leaving the ready role does not queue another ready message');
ob_start();
orders_show($orderId);
ob_end_clean();
check_eq(message_prepared_count($orderId, 'ready_for_pickup'), 1, 'reloading the order does not queue another ready message');
$readyId = (string) db_query(
    'SELECT id FROM fashion_messages
     WHERE tenant_id = :tenant_id AND order_id = :order_id AND template_key = :template_key AND status = :status
     LIMIT 1',
    ['order_id' => $orderId, 'template_key' => 'ready_for_pickup', 'status' => 'prepared']
)[0]['id'];
$_POST = ['csrf' => csrf_token()];
try {
    message_ready_submit($orderId);
    check(false, 'send ready message redirects');
} catch (RedirectException $redirect) {
    check_eq($redirect->path(), '/messages/' . $readyId, 'Send ready message opens the queued message');
}
check_eq(message_prepared_count($orderId, 'ready_for_pickup'), 1, 'Send ready message does not queue a second ready message');

$_POST = ['csrf' => csrf_token(), 'due_date' => '2026-02-31'];
try {
    message_late_submit($orderId);
    check(false, 'a bad date redirects');
} catch (RedirectException $redirect) {
    check_eq($redirect->path(), '/orders/' . $orderId, 'a bad date stays on the order');
}
check_eq(flash_get('error'), 'Enter the new expected date.', 'a bad expected date is explained');
check_eq((string) order_find($orderId)['due_date'], '2026-10-16', 'a bad date does not change the order');
check_eq(message_prepared_count($orderId, 'running_late'), 0, 'a bad date does not queue a message');
$_POST = ['csrf' => csrf_token(), 'due_date' => '2026-11-02'];
try {
    message_late_submit($orderId);
    check(false, 'running late redirects');
} catch (RedirectException $redirect) {
    check_eq($redirect->path(), '/orders/' . $orderId, 'running late returns to the order');
}
check_eq((string) order_find($orderId)['due_date'], '2026-11-02', 'running late saves the new expected date');
$late = db_query(
    'SELECT body, change_note FROM fashion_messages
     WHERE tenant_id = :tenant_id AND order_id = :order_id AND template_key = :template_key',
    ['order_id' => $orderId, 'template_key' => 'running_late']
);
check_eq(count($late), 1, 'running late queues one message');
check_eq((string) $late[0]['change_note'], '2026-10-16 -> 2026-11-02', 'running late records the date change');
check(str_contains((string) $late[0]['body'], '2 November 2026'), 'the running late message uses the new date');
ob_start();
orders_show($orderId);
ob_end_clean();
check_eq(message_prepared_count($orderId, 'running_late'), 1, 'reloading the order does not queue another late message');

$cutting = db_select('fashion_stage_definitions', ['name' => 'Cutting'], ['limit' => 1]);
check($cutting !== [], 'the shop has a Cutting stage');
$depositSaved = deposit_settings_save('30', (string) $cutting[0]['id']);
check_eq($depositSaved, [], 'deposit settings save');
check_eq(message_prepared_count($orderId, 'deposit_reminder'), 1, 'a deposit that is due queues one reminder');
check_eq(message_prepared_total('deposit_reminder'), 26, 'each unpaid order queues one deposit reminder');
deposit_settings_save('30', (string) $cutting[0]['id']);
check_eq(message_prepared_total('deposit_reminder'), 26, 'saving the deposit again does not queue a second reminder');
ob_start();
orders_show($orderId);
ob_end_clean();
check_eq(message_prepared_count($orderId, 'deposit_reminder'), 1, 'reloading the order does not queue another deposit reminder');

$_COOKIE['lang'] = 'ha';
$hausaOrder = messages_test_order($customerId, $garmentId);
$_COOKIE['lang'] = 'en';
$hausaOrderId = (string) $hausaOrder['id'];
$hausaBody = db_query(
    'SELECT body FROM fashion_messages
     WHERE tenant_id = :tenant_id AND order_id = :order_id AND template_key = :template_key
     LIMIT 1',
    ['order_id' => $hausaOrderId, 'template_key' => 'order_confirmation']
)[0];
check(str_contains((string) $hausaBody['body'], '16 Oktoba 2026'), 'a queued Hausa message uses Hausa month names');
check_eq(message_prepared_count($hausaOrderId, 'order_confirmation'), 1, 'the Hausa order queues one confirmation');
check_eq(message_prepared_count($hausaOrderId, 'deposit_reminder'), 1, 'the Hausa order queues one deposit reminder');
message_queue_order($hausaOrderId);
check_eq(message_prepared_count($hausaOrderId, 'order_confirmation'), 1, 'queuing the order again does not duplicate the confirmation');
check_eq(message_prepared_count($hausaOrderId, 'deposit_reminder'), 1, 'queuing the order again does not duplicate the deposit reminder');

db_update('fashion_customers', ['phone' => ''], ['id' => $customerId]);
$_SERVER['REQUEST_URI'] = base_path() . '/orders/' . $orderId;
ob_start();
orders_show($orderId);
$noPhone = (string) ob_get_clean();
check(str_contains($noPhone, 'No phone number'), 'a customer with no phone number shows No phone number');
check(!str_contains($noPhone, 'https://wa.me/234'), 'a missing number is not opened in WhatsApp');
db_update('fashion_customers', ['phone' => '12345'], ['id' => $customerId]);
ob_start();
orders_show($orderId);
$badPhone = (string) ob_get_clean();
check(str_contains($badPhone, 'That phone number cannot be used on WhatsApp.'), 'a number that cannot be fixed is explained');
check(!str_contains($badPhone, 'https://wa.me/234'), 'a bad number is not opened in WhatsApp');
check(preg_match('/<title>([^<]*)<\/title>/', $badPhone, $badTitle) === 1, 'the bad-number page has a title');
check(isset($badTitle[1]) && !str_contains($badTitle[1], '12345'), 'the bad-number title does not contain the phone number');

module_save(['fashion.orders', 'fashion.production', 'fashion.finance', 'fashion.measurements']);
$quiet = messages_test_order($customerId, $garmentId);
check_eq(message_prepared_count((string) $quiet['id'], 'order_confirmation'), 0, 'a shop without communication does not queue a message');

business_register([
    'business_name' => 'Other Message Shop',
    'owner_name' => 'Bola Ade',
    'email' => 'messages-b@example.com',
    'password' => 'password',
    'phone' => '08032220009',
    'company_type' => 'tailor',
]);
check_eq(message_to_send_count(''), 0, 'another shop does not see this queue');
check_throws(
    static fn () => message_open($firstMessageId),
    HttpException::class,
    'Page not found'
);
$_POST = ['csrf' => csrf_token(), 'back' => '/messages'];
check_throws(
    static fn () => message_sent_submit($firstMessageId),
    HttpException::class,
    'Page not found'
);
$_POST = ['csrf' => csrf_token(), 'due_date' => '2026-12-01'];
check_throws(
    static fn () => message_late_submit($orderId),
    HttpException::class,
    'Page not found'
);
$_POST = ['csrf' => csrf_token()];
check_throws(
    static fn () => message_ready_submit($orderId),
    HttpException::class,
    'Page not found'
);
auth_login($ownerId, $tenantId);
check_eq((string) order_find($orderId)['due_date'], '2026-11-02', 'another shop cannot change the due date');

$_POST = [];
$_GET = [];
$_COOKIE['lang'] = 'en';
unset($GLOBALS['gmingo_now']);
