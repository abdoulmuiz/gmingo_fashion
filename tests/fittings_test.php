<?php

declare(strict_types=1);

require_once __DIR__ . '/support.php';
require_once dirname(__DIR__) . '/app/domain/accounts.php';
require_once dirname(__DIR__) . '/app/domain/customers.php';
require_once dirname(__DIR__) . '/app/domain/orders.php';
require_once dirname(__DIR__) . '/app/domain/fittings.php';
require_once dirname(__DIR__) . '/app/fashion/controllers/orders.php';
require_once dirname(__DIR__) . '/app/fashion/controllers/fittings.php';

tests_reset();

function fittings_test_order(string $customerId, string $garmentId, string $dueDate): array
{
    return order_create([
        'customer_id' => $customerId,
        'due_date' => $dueDate,
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

function fittings_test_day(string $html, string $date): string
{
    $marker = 'data-date="' . $date . '"';
    $start = strpos($html, $marker);
    check($start !== false, $date . ' is on the calendar');
    if ($start === false) {
        return '';
    }
    $next = strpos($html, 'data-date="', $start + strlen($marker));
    if ($next === false) {
        $next = strlen($html);
    }
    return substr($html, $start, $next - $start);
}

function fittings_test_calendar(string $month): string
{
    $_GET['month'] = $month;
    $_SERVER['REQUEST_URI'] = base_path() . '/calendar?month=' . $month;
    ob_start();
    calendar_show();
    return (string) ob_get_clean();
}

$shop = business_register([
    'business_name' => 'Fitting Shop',
    'owner_name' => 'Amina Bello',
    'email' => 'fitting-a@example.com',
    'password' => 'password',
    'phone' => '08032220001',
    'company_type' => 'tailor',
]);
$ownerId = (string) $shop['user_id'];
$tenantId = (string) $shop['tenant_id'];
$customer = customer_create([
    'name' => 'Fitting Customer',
    'phone' => '08032220002',
    'gender' => 'female',
    'customer_type' => 'regular',
]);
$garmentId = (string) db_select('fashion_garment_types', ['code' => 'kaftan'], ['limit' => 1])[0]['id'];
$order = fittings_test_order((string) $customer['id'], $garmentId, '2026-10-16');
$orderId = (string) $order['id'];
$orderNumber = (string) $order['order_number'];
check_eq($orderNumber, 'TK1001', 'the first fitting order is TK1001');

$GLOBALS['gmingo_now'] = '2026-10-16 12:00:00';
$before = fitting_schedule($orderId, [
    'date' => '2026-10-16',
    'time' => '23:30',
    'fitting_type' => 'first',
    'note' => 'Before midnight',
]);
$after = fitting_schedule($orderId, [
    'date' => '2026-10-17',
    'time' => '00:30',
    'fitting_type' => 'second',
    'note' => 'After midnight',
]);
check(isset($before['id']), 'a fitting just before Lagos midnight is booked');
check(isset($after['id']), 'a fitting just after Lagos midnight is booked');
$beforeRow = db_select('fashion_fittings', ['id' => (string) $before['id']])[0];
$afterRow = db_select('fashion_fittings', ['id' => (string) $after['id']])[0];
check_eq((string) $beforeRow['starts_at'], '2026-10-16 22:30:00', '23:30 Lagos is 22:30 UTC');
check_eq((string) $afterRow['starts_at'], '2026-10-16 23:30:00', '00:30 Lagos is 23:30 UTC the day before');
check_eq((string) $beforeRow['created_by'], $ownerId, 'the fitting records who booked it');
check_eq((string) $beforeRow['created_at'], '2026-10-16 11:00:00', 'the booking time is stored in UTC');
check_eq(fitting_format((string) $beforeRow['starts_at']), '16 Oct 2026 23:30', '23:30 UTC storage shows as 23:30 Lagos');
check_eq(fitting_format((string) $afterRow['starts_at']), '17 Oct 2026 00:30', 'the after-midnight fitting shows on 17 Oct Lagos');

$october = fittings_test_calendar('2026-10');
$day16 = fittings_test_day($october, '2026-10-16');
$day17 = fittings_test_day($october, '2026-10-17');
check(str_contains($day16, '23:30'), 'the late fitting appears on 16 Oct');
check(str_contains($day16, 'Before midnight'), 'the late fitting note appears on 16 Oct');
check(!str_contains($day16, 'After midnight'), 'the after-midnight fitting is not on 16 Oct');
check(str_contains($day17, '00:30'), 'the after-midnight fitting appears on 17 Oct');
check(str_contains($day17, 'After midnight'), 'the after-midnight note appears on 17 Oct');
check(!str_contains($day17, 'Before midnight'), 'the before-midnight fitting is not on 17 Oct');
check(str_contains($day16, t('fitting_label')), 'the 16 Oct cell labels the fitting');
check(str_contains($day16, t('due_label')), 'the 16 Oct cell labels the due date');
check(t('fitting_label') !== t('due_label'), 'fitting and due labels are different words');
check(str_contains($day16, 'cal-fitting'), 'the fitting uses the fitting colour class');
check(str_contains($day16, 'cal-due'), 'the due date uses the due colour class');
check(str_contains($day16, 'href="' . url('/orders/' . $orderId) . '"'), 'a calendar item opens its order');
check(str_contains($day16, 'cal-today'), '16 Oct has the today marker');
check(str_contains($day16, t('calendar_today')), '16 Oct says Today');
check(!str_contains($day17, 'cal-today'), '17 Oct is not marked today');
$css = (string) file_get_contents(dirname(__DIR__) . '/public/assets/app.css');
check(str_contains($css, '.cal-fitting { background: #dbeafe;'), 'fittings are blue');
check(str_contains($css, '.cal-due { background: #ffedd5;'), 'due dates are orange');
check(!str_contains($october, '<script'), 'the calendar is server-rendered without a script');

$cancel = fitting_schedule($orderId, [
    'date' => '2026-10-18',
    'time' => '10:00',
    'fitting_type' => 'other',
    'note' => 'Cancel me',
]);
check(isset($cancel['id']), 'a fitting to cancel is booked');
$GLOBALS['gmingo_now'] = '2026-10-18 09:00:00';
check_eq(fitting_set_status($orderId, (string) $cancel['id'], 'cancelled')['id'], (string) $cancel['id'], 'cancelling returns the fitting');
$cancelled = db_select('fashion_fittings', ['id' => (string) $cancel['id']])[0];
check_eq((string) $cancelled['status'], 'cancelled', 'the fitting status is cancelled');
check_eq((string) $cancelled['status_by'], $ownerId, 'the cancellation records who');
check_eq((string) $cancelled['status_at'], '2026-10-18 08:00:00', 'the cancellation time is stored in UTC');
check_eq(count(db_select('fashion_fittings', ['id' => (string) $cancel['id']])), 1, 'cancelling does not delete the fitting');
$GLOBALS['gmingo_now'] = '2026-10-16 12:00:00';
$afterCancel = fittings_test_calendar('2026-10');
check(!str_contains($afterCancel, 'Cancel me'), 'a cancelled fitting is not shown as scheduled');
check(str_contains(fittings_test_day($afterCancel, '2026-10-16'), 'Before midnight'), 'the scheduled fitting stays on its day');

$GLOBALS['gmingo_now'] = '2026-10-20 09:15:00';
check_eq(fitting_set_status($orderId, (string) $before['id'], 'done')['id'], (string) $before['id'], 'marking done returns the fitting');
$done = db_select('fashion_fittings', ['id' => (string) $before['id']])[0];
check_eq((string) $done['status'], 'done', 'the fitting status is done');
check_eq((string) $done['status_by'], $ownerId, 'marking done records who');
check_eq((string) $done['status_at'], '2026-10-20 08:15:00', 'marking done records when in UTC');
check_eq((string) $done['created_by'], $ownerId, 'marking done keeps the original booker');
check_eq((string) $done['created_at'], '2026-10-16 11:00:00', 'marking done keeps the original booking time');
check_eq((string) $done['starts_at'], '2026-10-16 22:30:00', 'marking done does not change the appointment');
$_SERVER['REQUEST_URI'] = base_path() . '/orders/' . $orderId;
ob_start();
orders_show($orderId);
$orderPage = (string) ob_get_clean();
check(str_contains($orderPage, t('schedule_fitting')), 'the order page has the schedule form');
check(str_contains($orderPage, 'name="status" value="done"'), 'the order page can mark a fitting done');
check(str_contains($orderPage, 'name="status" value="missed"'), 'the order page can mark a fitting missed');
check(str_contains($orderPage, 'name="status" value="cancelled"'), 'the order page can mark a fitting cancelled');
check(str_contains($orderPage, '16 Oct 2026 23:30'), 'the order page shows the Lagos appointment');
check(str_contains($orderPage, '20 Oct 2026 09:15'), 'the order page shows when the status changed');
check(str_contains($orderPage, 'Amina Bello'), 'the order page shows who changed the status');
check(str_contains($orderPage, t('fitting_status_cancelled')), 'the order page still lists the cancelled fitting');
check(str_contains($orderPage, 'Cancel me'), 'the cancelled fitting stays on the order');

$staffId = db_insert('fashion_users', [
    'name' => 'Fitting Staff',
    'email' => 'fitting-staff@example.com',
    'password_hash' => password_hash('password', PASSWORD_DEFAULT),
    'role' => 'staff',
    'lang' => 'en',
    'active' => 1,
    'created_at' => '2026-10-16 11:00:00',
]);
auth_login($staffId, $tenantId);
$GLOBALS['gmingo_now'] = '2026-10-16 12:00:00';
$pastCount = count(db_select('fashion_fittings', ['order_id' => $orderId], ['limit' => 20]));
check_eq(fitting_schedule($orderId, [
    'date' => '2026-10-16',
    'time' => '11:00',
    'fitting_type' => 'first',
    'note' => 'Staff tried',
])['error'], 'fitting_in_past', 'staff cannot book a fitting in the past');
check_eq(count(db_select('fashion_fittings', ['order_id' => $orderId], ['limit' => 20])), $pastCount, 'a refused past fitting writes no row');
$_POST = [
    'csrf' => csrf_token(),
    'fitting_date' => '2026-10-16',
    'fitting_time' => '11:00',
    'fitting_type' => 'first',
    'note' => 'Staff tried',
];
try {
    router_dispatch('POST', '/orders/' . $orderId . '/fittings');
    check(false, 'a past fitting redirects');
} catch (RedirectException $e) {
    check_eq($e->path(), '/orders/' . $orderId, 'a refused past fitting returns to the order');
}
check_eq(flash_get('error'), 'Choose a time that has not passed.', 'a past fitting shows the past message');
auth_login($ownerId, $tenantId);
check_eq(fitting_schedule($orderId, [
    'date' => '2026-10-16',
    'time' => '11:00',
    'fitting_type' => 'final',
    'note' => '   ',
])['error'], 'fitting_past_note', 'an owner past fitting needs a note');
$_POST['note'] = '   ';
$_POST['csrf'] = csrf_token();
try {
    router_dispatch('POST', '/orders/' . $orderId . '/fittings');
    check(false, 'a blank past note redirects');
} catch (RedirectException $e) {
    check_eq($e->path(), '/orders/' . $orderId, 'a blank past note returns to the order');
}
check_eq(flash_get('error'), 'A past fitting needs a note.', 'a blank past note shows the note message');
$late = fitting_schedule($orderId, [
    'date' => '2026-10-16',
    'time' => '11:00',
    'fitting_type' => 'final',
    'note' => 'Owner note',
]);
check(isset($late['id']), 'an owner can book a past fitting with a note');
check_eq((string) db_select('fashion_fittings', ['id' => (string) $late['id']])[0]['starts_at'], '2026-10-16 10:00:00', '11:00 Lagos is 10:00 UTC');
check_eq((string) db_select('fashion_fittings', ['id' => (string) $late['id']])[0]['note'], 'Owner note', 'the past fitting keeps the note');

for ($i = 1; $i <= 6; $i++) {
    $slot = fitting_schedule($orderId, [
        'date' => '2026-11-02',
        'time' => str_pad((string) (8 + $i), 2, '0', STR_PAD_LEFT) . ':00',
        'fitting_type' => 'other',
        'note' => 'Slot ' . (string) $i,
    ]);
    check(isset($slot['id']), 'slot ' . (string) $i . ' is booked');
}
$november = fittings_test_calendar('2026-11');
$busy = fittings_test_day($november, '2026-11-02');
check(str_contains($busy, 'Slot 1'), 'a busy day shows the first fitting');
check(str_contains($busy, 'Slot 3'), 'a busy day shows the third fitting');
check(!str_contains($busy, 'Slot 6'), 'a busy day hides the sixth fitting');
check(str_contains($busy, t('calendar_more', ['count' => '3'])), 'a busy day shows +3 more');
check(str_contains($busy, 'href="' . url('/calendar/2026-11-02') . '"'), '+3 more opens that day');
$_SERVER['REQUEST_URI'] = base_path() . '/calendar/2026-11-02';
ob_start();
calendar_day_show('2026-11-02');
$dayPage = (string) ob_get_clean();
check(str_contains($dayPage, 'Slot 6'), 'the day page shows the hidden fitting');
check(str_contains($dayPage, 'Slot 1'), 'the day page shows the first fitting');

$decemberOrder = fittings_test_order((string) $customer['id'], $garmentId, '2026-12-31');
$januaryOrder = fittings_test_order((string) $customer['id'], $garmentId, '2027-01-01');
check(isset(fitting_schedule((string) $decemberOrder['id'], [
    'date' => '2026-12-31',
    'time' => '23:30',
    'fitting_type' => 'final',
    'note' => 'Year end before',
])['id']), 'a 31 Dec fitting is booked');
check(isset(fitting_schedule((string) $januaryOrder['id'], [
    'date' => '2027-01-01',
    'time' => '00:30',
    'fitting_type' => 'first',
    'note' => 'Year end after',
])['id']), 'a 1 Jan fitting is booked');
$december = fittings_test_calendar('2026-12');
$january = fittings_test_calendar('2027-01');
check(str_contains($december, 'href="' . url('/calendar?month=2027-01') . '"'), 'December links to January');
check(str_contains($december, 'href="' . url('/calendar?month=2026-11') . '"'), 'December links back to November');
check(str_contains($january, 'href="' . url('/calendar?month=2026-12') . '"'), 'January links back to December');
check(str_contains($january, 'href="' . url('/calendar?month=2027-02') . '"'), 'January links to February');
$dec31 = fittings_test_day($december, '2026-12-31');
$jan1 = fittings_test_day($january, '2027-01-01');
check(str_contains($dec31, 'Year end before'), '31 Dec shows the fitting booked that evening');
check(str_contains($dec31, t('due_label')), '31 Dec shows its due date');
check(!str_contains($december, 'Year end after'), 'the 1 Jan fitting is not on the December calendar');
check(str_contains($jan1, 'Year end after'), '1 Jan shows the fitting booked just after midnight');
check(str_contains($jan1, t('due_label')), '1 Jan shows its due date');
check(!str_contains($january, 'Year end before'), 'the 31 Dec fitting is not on the January calendar');

$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(dirname(__DIR__) . '/app'));
foreach ($iterator as $file) {
    if (!$file->isFile() || $file->getExtension() !== 'php') {
        continue;
    }
    $text = (string) file_get_contents($file->getPathname());
    $base = $file->getBasename();
    check(preg_match('/db_delete\s*\(\s*[\'"]fashion_fittings[\'"]/', $text) !== 1, $base . ' does not delete fittings');
    check(preg_match('/\bdelete(?:\s+from)?\s+fashion_fittings\b/i', $text) !== 1, $base . ' has no delete SQL for fittings');
}

$other = business_register([
    'business_name' => 'Other Fitting Shop',
    'owner_name' => 'Bala',
    'email' => 'fitting-b@example.com',
    'password' => 'password',
    'phone' => '08032220003',
    'company_type' => 'tailor',
]);
$otherCustomer = customer_create([
    'name' => 'Other Fitting Customer',
    'phone' => '08032220004',
    'gender' => 'male',
    'customer_type' => 'regular',
]);
$otherGarmentId = (string) db_select('fashion_garment_types', ['code' => 'kaftan'], ['limit' => 1])[0]['id'];
$otherOrder = fittings_test_order((string) $otherCustomer['id'], $otherGarmentId, '2026-10-21');
check(isset(fitting_schedule((string) $otherOrder['id'], [
    'date' => '2026-10-21',
    'time' => '15:00',
    'fitting_type' => 'first',
    'note' => 'Other shop cloth',
])['id']), 'the other shop books its own fitting');
$_POST = [
    'csrf' => csrf_token(),
    'fitting_date' => '2026-10-21',
    'fitting_time' => '16:00',
    'fitting_type' => 'first',
    'note' => 'Should fail',
];
try {
    router_dispatch('POST', '/orders/' . $orderId . '/fittings');
    check(false, 'the other shop fitting form is refused');
} catch (HttpException $e) {
    check_eq($e->getMessage(), 'Page not found', 'scheduling refuses another business order id');
    check_eq($e->status(), 404, 'scheduling returns 404 for another business order id');
}
$_POST = [
    'csrf' => csrf_token(),
    'status' => 'done',
];
try {
    router_dispatch('POST', '/orders/' . $orderId . '/fittings/' . (string) $before['id'] . '/status');
    check(false, 'the other shop status change is refused');
} catch (HttpException $e) {
    check_eq($e->getMessage(), 'Page not found', 'a status change refuses another business fitting id');
    check_eq($e->status(), 404, 'a status change returns 404 for another business fitting id');
}
$otherOctober = fittings_test_calendar('2026-10');
check(str_contains($otherOctober, 'Other shop cloth'), 'the other shop calendar shows its own fitting');
check(!str_contains($otherOctober, 'Before midnight'), 'the other shop does not see this shop fitting');
check(!str_contains(fittings_test_day($otherOctober, '2026-10-16'), $orderNumber), 'the other shop does not see this shop due date');
auth_login($ownerId, $tenantId);
$ourOctober = fittings_test_calendar('2026-10');
check(str_contains($ourOctober, 'After midnight'), 'this shop still sees its own fitting');
check(!str_contains($ourOctober, 'Other shop cloth'), 'this shop does not see the other shop fitting');
check(!str_contains(fittings_test_day($ourOctober, '2026-10-21'), (string) $otherOrder['order_number']), 'this shop does not see the other shop due date');
unset($GLOBALS['gmingo_now']);
