<?php

declare(strict_types=1);

require_once __DIR__ . '/support.php';
require_once dirname(__DIR__) . '/app/domain/accounts.php';
require_once dirname(__DIR__) . '/app/domain/customers.php';
require_once dirname(__DIR__) . '/app/domain/orders.php';
require_once dirname(__DIR__) . '/app/domain/stock.php';
require_once dirname(__DIR__) . '/app/domain/dashboard.php';
require_once dirname(__DIR__) . '/app/fashion/controllers/stock.php';
require_once dirname(__DIR__) . '/app/fashion/controllers/home.php';

tests_reset();

function stock_test_sum(string $itemId): int
{
    $rows = db_query(
        'SELECT change_hundredths FROM fashion_stock_movements WHERE tenant_id = :tenant_id AND item_id = :item_id',
        ['item_id' => $itemId]
    );
    $sum = 0;
    foreach ($rows as $row) {
        $sum += (int) $row['change_hundredths'];
    }
    return $sum;
}

function stock_test_modules(bool $inventory): void
{
    $keys = [
        'fashion.orders',
        'fashion.production',
        'fashion.finance',
        'fashion.measurements',
        'fashion.communication',
    ];
    if ($inventory) {
        $keys[] = 'fashion.inventory';
    }
    module_save($keys);
}

$shop = business_register([
    'business_name' => 'Stock Shop',
    'owner_name' => 'Amina Bello',
    'email' => 'stock-a@example.com',
    'password' => 'password',
    'phone' => '08032210001',
    'company_type' => 'tailor',
]);
$ownerId = (string) $shop['user_id'];
$tenantId = (string) $shop['tenant_id'];
stock_test_modules(true);
check_eq(stock_hundredths('1.5'), 150, '1.5 stores as 150 hundredths');
check_eq(stock_hundredths('1.50'), 150, '1.50 stores as 150 hundredths');
check_eq(stock_format(150), '1.50', '150 hundredths shows as 1.50');
check(stock_hundredths('abc', true) === null, 'letters are rejected');
check(stock_hundredths('-1', true) === -100, 'a minus quantity is negative hundredths');

$created = stock_create([
    'name' => 'Guinea brocade',
    'category' => 'fabric',
    'unit' => 'yards',
    'colour' => 'gold',
    'minimum' => '0.25',
]);
check(isset($created['id']), 'a stock item is created');
$itemId = (string) $created['id'];
check_eq((int) db_select('fashion_stock_items', ['id' => $itemId])[0]['minimum_hundredths'], 25, 'a minimum of 0.25 is 25 hundredths');
$GLOBALS['gmingo_now'] = '2026-10-16 21:10:00';
$opening = stock_adjust($itemId, [
    'step' => '',
    'amount' => '5',
    'reason' => 'opening',
    'note' => 'First roll',
    'order_id' => '',
]);
unset($GLOBALS['gmingo_now']);
check_eq($opening['change_hundredths'], 500, '5 yards is 500 hundredths');
$firstId = (string) $opening['id'];
check_eq(stock_quantity($itemId), stock_test_sum($itemId), 'current quantity equals the movements sum');
check_eq(stock_quantity($itemId), 500, 'opening stock is 500 hundredths');
$plus = stock_adjust($itemId, ['step' => 'plus', 'amount' => '', 'reason' => '', 'note' => '', 'order_id' => '']);
check_eq($plus['change_hundredths'], 100, 'plus adds one whole unit');
$minus = stock_adjust($itemId, ['step' => 'minus', 'amount' => '', 'reason' => '', 'note' => '', 'order_id' => '']);
check_eq($minus['change_hundredths'], -100, 'minus removes one whole unit');
check_eq(stock_quantity($itemId), 500, 'plus then minus returns to 500 hundredths');
check_eq(stock_quantity($itemId), stock_test_sum($itemId), 'quantity still equals the movements sum');
$movements = db_query(
    'SELECT id, change_hundredths, reason FROM fashion_stock_movements WHERE tenant_id = :tenant_id AND item_id = :item_id ORDER BY seq ASC',
    ['item_id' => $itemId]
);
check_eq(count($movements), 3, 'plus and minus add rows');
check_eq((string) $movements[0]['id'], $firstId, 'the first movement keeps its id');
check_eq((int) $movements[0]['change_hundredths'], 500, 'the first movement stays 500');
check_eq((string) $movements[0]['reason'], 'opening', 'the first movement stays an opening');
check_eq((string) $movements[1]['reason'], 'purchase', 'plus is a purchase row');
check_eq((string) $movements[2]['reason'], 'used', 'minus is a used row');
check_throws(
    static fn () => db_update('fashion_stock_movements', ['change_hundredths' => 1], ['id' => $firstId]),
    RuntimeException::class,
    'This table is append-only.'
);
check_throws(
    static fn () => db_delete('fashion_stock_movements', ['id' => $firstId]),
    RuntimeException::class,
    'This table is append-only.'
);
check_eq((int) db_select('fashion_stock_movements', ['id' => $firstId])[0]['change_hundredths'], 500, 'a refused edit leaves the first row at 500');
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(dirname(__DIR__) . '/app'));
foreach ($iterator as $file) {
    if (!$file->isFile() || $file->getExtension() !== 'php') {
        continue;
    }
    $text = (string) file_get_contents($file->getPathname());
    $base = $file->getBasename();
    check(
        preg_match('/db_(update|delete)\s*\(\s*[\'"]fashion_stock_movements[\'"]/', $text) !== 1,
        $base . ' has no update or delete call for stock movements'
    );
    check(
        preg_match('/\b(?:update|delete(?:\s+from)?)\s+fashion_stock_movements\b/i', $text) !== 1,
        $base . ' has no update or delete SQL for stock movements'
    );
}
check_eq(stock_adjust($itemId, [
    'step' => '',
    'amount' => '-6',
    'reason' => 'correction',
    'note' => '',
    'order_id' => '',
])['error'], 'stock_below_zero', 'below zero is refused');
check_eq(count(db_query(
    'SELECT id FROM fashion_stock_movements WHERE tenant_id = :tenant_id AND item_id = :item_id',
    ['item_id' => $itemId]
)), 3, 'a below-zero change adds no row');
$_POST = [
    'csrf' => csrf_token(),
    'step' => 'minus',
    'back' => '/stock/' . $itemId,
];
stock_adjust($itemId, ['step' => '', 'amount' => '-4.50', 'reason' => 'correction', 'note' => '', 'order_id' => '']);
check_eq(stock_quantity($itemId), 50, 'the item is left with half a yard');
try {
    router_dispatch('POST', '/stock/' . $itemId . '/move');
    check(false, 'a minus that passes zero redirects');
} catch (RedirectException $e) {
    check_eq($e->path(), '/stock/' . $itemId, 'a refused stock change returns to the item');
}
check_eq(flash_get('error'), 'That would take the stock below zero.', 'below zero shows the stock message');
check_eq(stock_quantity($itemId), 50, 'the refused minus leaves half a yard');

$level = stock_create([
    'name' => 'Gold thread',
    'category' => 'thread',
    'unit' => 'cones',
    'colour' => '',
    'minimum' => '1',
]);
$above = stock_create([
    'name' => 'Spare zip',
    'category' => 'zip',
    'unit' => 'pieces',
    'colour' => '',
    'minimum' => '1',
]);
$under = stock_create([
    'name' => 'White lining',
    'category' => 'lining',
    'unit' => 'metres',
    'colour' => '',
    'minimum' => '1',
]);
$openEnded = stock_create([
    'name' => 'Buttons',
    'category' => 'button',
    'unit' => 'pieces',
    'colour' => '',
    'minimum' => '',
]);
stock_adjust((string) $level['id'], ['step' => '', 'amount' => '1', 'reason' => 'opening', 'note' => '', 'order_id' => '']);
stock_adjust((string) $above['id'], ['step' => '', 'amount' => '1.01', 'reason' => 'opening', 'note' => '', 'order_id' => '']);
stock_adjust((string) $under['id'], ['step' => '', 'amount' => '0.99', 'reason' => 'opening', 'note' => '', 'order_id' => '']);
check_eq(stock_low_count(), 2, 'stock equal to the minimum counts as low');
check_eq(dashboard_test_stock_value(), '2', 'the dashboard low stock count is 2');
$_SERVER['REQUEST_URI'] = base_path() . '/';
ob_start();
home_show();
$home = (string) ob_get_clean();
check(str_contains($home, t('card_low_stock')), 'the dashboard card appears when inventory is on');
check(str_contains($home, 'href="' . url('/stock') . '"'), 'the low stock card links to stock');
stock_test_modules(false);
check(dashboard_test_stock_value() === null, 'the dashboard card hides when inventory is off');
ob_start();
home_show();
$homeOff = (string) ob_get_clean();
check(!str_contains($homeOff, t('card_low_stock')), 'the home screen hides the low stock card when inventory is off');
stock_test_modules(true);
check_eq(dashboard_test_stock_value(), '2', 'turning inventory on shows the low stock count again');

$_GET = ['q' => 'Guinea', 'category' => 'fabric', 'page' => '1'];
$_SERVER['REQUEST_URI'] = base_path() . '/stock?q=Guinea&category=fabric';
ob_start();
stock_index();
$list = (string) ob_get_clean();
check(str_contains($list, 'Guinea brocade'), 'the stock list shows the matching item');
check(str_contains($list, 'Amina Bello'), 'the stock list shows who made the last change');
check(str_contains($list, 'name="step" value="plus"'), 'the stock list has a plus button');
check(str_contains($list, 'name="step" value="minus"'), 'the stock list has a minus button');
check(str_contains($list, t('low_stock_items')), 'the stock screen has a low stock section');
check(str_contains($list, 'White lining'), 'the low stock section shows an item at or below the minimum');
check(!str_contains($list, 'Spare zip'), 'an item above the minimum stays out of the filtered list');

$_SERVER['REQUEST_URI'] = base_path() . '/stock/' . $itemId;
ob_start();
stock_show($itemId);
$shown = (string) ob_get_clean();
check(str_contains($shown, '0.50'), 'the item page shows the current quantity');
check(str_contains($shown, '16 Oct 2026 21:10'), 'the movement history shows the Lagos time');
check(str_contains($shown, 'First roll'), 'the movement history shows the note');

$other = business_register([
    'business_name' => 'Other Stock Shop',
    'owner_name' => 'Bala',
    'email' => 'stock-b@example.com',
    'password' => 'password',
    'phone' => '08032210002',
    'company_type' => 'factory',
]);
check(module_enabled('fashion.inventory') === true, 'the factory starts with inventory');
$otherItem = stock_create([
    'name' => 'Factory interfacing',
    'category' => 'interfacing',
    'unit' => 'metres',
    'colour' => '',
    'minimum' => '2',
]);
check(isset($otherItem['id']), 'the other shop creates its own item');
module_save([
    'fashion.orders',
    'fashion.production',
    'fashion.finance',
    'fashion.inventory',
    'fashion.staff',
]);
$otherCustomer = customer_create([
    'name' => 'Factory Buyer',
    'phone' => '08032210003',
    'gender' => 'male',
    'customer_type' => 'regular',
]);
$otherGarmentId = db_insert('fashion_garment_types', [
    'code' => 'kaftan',
    'name' => 'Kaftan',
    'sort_order' => 1,
    'active' => 1,
    'placeholder' => 0,
    'lead_days' => null,
]);
$otherOrder = order_create([
    'customer_id' => (string) $otherCustomer['id'],
    'due_date' => '2026-11-20',
    'urgent' => '',
    'items' => [[
        'garment_type_id' => $otherGarmentId,
        'quantity' => '1',
        'price' => '100',
        'fabric' => 'ankara',
        'notes' => '',
    ]],
]);
check(isset($otherOrder['id']), 'the other shop creates an order');
try {
    router_dispatch('GET', '/stock/' . $itemId);
    check(false, 'the other shop stock page is refused');
} catch (HttpException $e) {
    check_eq($e->getMessage(), 'Page not found', 'the stock address refuses another business item id');
    check_eq($e->status(), 404, 'the stock address returns 404 for another business item id');
}
$_POST = [
    'csrf' => csrf_token(),
    'step' => 'plus',
    'back' => '/stock/' . $itemId,
];
try {
    router_dispatch('POST', '/stock/' . $itemId . '/move');
    check(false, 'the other shop stock change is refused');
} catch (HttpException $e) {
    check_eq($e->getMessage(), 'Page not found', 'a stock change refuses another business item id');
    check_eq($e->status(), 404, 'a stock change returns 404 for another business item id');
}
$otherList = stock_list('', '', 1);
$otherNames = array_column($otherList['items'], 'name');
check(in_array('Factory interfacing', $otherNames, true), 'the other shop list shows its own item');
check(!in_array('Guinea brocade', $otherNames, true), 'items of one business never appear in another list');
auth_login($ownerId, $tenantId);
check_eq(stock_quantity($itemId), 50, 'the other shop did not change this stock');
$ourNames = array_column(stock_list('', '', 1)['items'], 'name');
check(in_array('Guinea brocade', $ourNames, true), 'this shop still lists its own item');
check(!in_array('Factory interfacing', $ourNames, true), 'this shop does not list the other business item');
$beforeRows = count(db_query(
    'SELECT id FROM fashion_stock_movements WHERE tenant_id = :tenant_id AND item_id = :item_id',
    ['item_id' => $itemId]
));
check_eq(stock_adjust($itemId, [
    'step' => '',
    'amount' => '1',
    'reason' => 'used',
    'note' => '',
    'order_id' => (string) $otherOrder['id'],
])['error'], 'stock_order_missing', 'another business order is refused');
check_eq(count(db_query(
    'SELECT id FROM fashion_stock_movements WHERE tenant_id = :tenant_id AND item_id = :item_id',
    ['item_id' => $itemId]
)), $beforeRows, 'a refused order writes no movement');

for ($i = 1; $i <= 26; $i++) {
    $packed = stock_create([
        'name' => 'Pack ' . str_pad((string) $i, 2, '0', STR_PAD_LEFT),
        'category' => 'packaging',
        'unit' => 'pieces',
        'colour' => '',
        'minimum' => '',
    ]);
    check(isset($packed['id']), 'packaging item ' . (string) $i . ' is created');
}
$packPage = stock_list('', 'packaging', 1);
$packNext = stock_list('', 'packaging', 2);
check_eq($packPage['row_count'], 26, 'the stock list counts every packaging item');
check_eq(count($packPage['items']), 25, 'the stock list shows the first page');
check_eq(count($packNext['items']), 1, 'the stock list shows the remaining item on page 2');
for ($i = 1; $i <= 22; $i++) {
    $added = stock_adjust($itemId, [
        'step' => '',
        'amount' => '0.01',
        'reason' => 'adjustment',
        'note' => '',
        'order_id' => '',
    ]);
    check_eq($added['change_hundredths'], 1, 'history row ' . (string) $i . ' adds one hundredth');
}
$historyPage = stock_history($itemId, 1);
$historyNext = stock_history($itemId, 2);
check_eq(count($historyPage['rows']), 25, 'movement history shows the first page');
check_eq(count($historyNext['rows']), 1, 'movement history shows the remaining row on page 2');
check_eq(stock_quantity($itemId), stock_test_sum($itemId), 'the paged history still matches the quantity sum');
check_eq(stock_quantity($itemId), 72, 'twenty-two hundredths bring the quantity to 72');

function dashboard_test_stock_value(): ?string
{
    $dash = dashboard_home(17);
    foreach ($dash['cards'] as $card) {
        if ($card['key'] === 'card_low_stock') {
            return $card['value'];
        }
    }
    return null;
}
