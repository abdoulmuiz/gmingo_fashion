<?php

declare(strict_types=1);

require_once __DIR__ . '/support.php';
require_once dirname(__DIR__) . '/app/domain/accounts.php';
require_once dirname(__DIR__) . '/app/domain/modules.php';
require_once dirname(__DIR__) . '/app/domain/products.php';
require_once dirname(__DIR__) . '/app/domain/sales.php';
require_once dirname(__DIR__) . '/app/domain/stock.php';
require_once dirname(__DIR__) . '/app/domain/purchasing.php';
require_once dirname(__DIR__) . '/app/domain/work_orders.php';
require_once dirname(__DIR__) . '/app/domain/branches.php';
require_once dirname(__DIR__) . '/app/domain/broadcasts.php';
require_once dirname(__DIR__) . '/app/domain/customers.php';
require_once dirname(__DIR__) . '/app/domain/storefront.php';
require_once dirname(__DIR__) . '/app/domain/billing.php';
require_once dirname(__DIR__) . '/app/domain/platform.php';
require_once dirname(__DIR__) . '/app/domain/access.php';

$connected = db()->query('SELECT DATABASE()')->fetchColumn();
if (!is_string($connected) || !str_ends_with($connected, '_test')) {
    fwrite(STDERR, "Refusing to run release remediation tests: database name must end with _test.\n");
    exit(1);
}

tests_reset();

$shopA = business_register([
    'business_name' => 'Remediation A',
    'owner_name' => 'Owner A',
    'email' => 'remediation-a@example.com',
    'password' => 'password',
    'phone' => '08038880001',
    'company_type' => 'brand',
]);
module_save(['fashion.orders', 'fashion.finance', 'fashion.inventory', 'fashion.sales', 'fashion.communication', 'fashion.production', 'fashion.staff']);
check(subscription_has_app_access() === true, 'remediation shop A starts entitled');
$stockA = stock_create([
    'name' => 'Finished A',
    'category' => 'other',
    'unit' => 'pieces',
    'kind' => 'finished',
    'unit_cost_kobo' => '1000',
    'colour' => '',
    'minimum' => '',
]);
stock_post_movement((string) $stockA['id'], 200, 'opening', 'open');
$productA = product_create([
    'name' => 'Product A',
    'sku' => 'RA-1',
    'description' => '',
    'price_kobo' => '500000',
    'sizes' => 'M',
    'colours' => 'Blue',
]);
db_update('fashion_products', ['stock_item_id' => (string) $stockA['id']], ['id' => (string) $productA['product']['id']]);
$saleOk = sale_create([
    'product_id' => (string) $productA['product']['id'],
    'quantity' => 1,
    'channel' => 'retail',
    'size_label' => 'M',
    'colour_label' => 'Blue',
    'note' => '',
]);
check(isset($saleOk['sale_id']), 'normal sale deducts stock');
check_eq((int) stock_find((string) $stockA['id'])['quantity_hundredths'], 100, 'one piece remains after sale');
$saleFail = sale_create([
    'product_id' => (string) $productA['product']['id'],
    'quantity' => 5,
    'channel' => 'retail',
    'size_label' => '',
    'colour_label' => '',
    'note' => '',
]);
check(isset($saleFail['errors']['form']) && $saleFail['errors']['form'] === 'stock_below_zero', 'sale refuses insufficient stock');
check_eq((int) stock_find((string) $stockA['id'])['quantity_hundredths'], 100, 'failed sale leaves stock unchanged');
$returned = sale_return((string) $saleOk['sale_id']);
check(isset($returned['ok']), 'return restores stock');
check_eq((int) stock_find((string) $stockA['id'])['quantity_hundredths'], 200, 'return restores opening quantity');
$adjust = stock_adjust((string) $stockA['id'], ['amount' => '-0.50', 'reason' => 'adjustment', 'note' => 'trim']);
check(isset($adjust['id']), 'authorized adjustment still works');
$overAdjust = stock_adjust((string) $stockA['id'], ['amount' => '-50', 'reason' => 'adjustment', 'note' => 'too much']);
check(($overAdjust['error'] ?? '') === 'stock_below_zero', 'adjustment respects stock floor');

// Conflicting deductions: leave one piece, first sale wins, second must fail.
$conflictQty = (int) stock_find((string) $stockA['id'])['quantity_hundredths'];
$conflictDelta = 100 - $conflictQty;
if ($conflictDelta !== 0) {
    stock_post_movement(
        (string) $stockA['id'],
        $conflictDelta,
        $conflictDelta > 0 ? 'opening' : 'adjustment',
        'set-one-piece'
    );
}
$raceA = sale_create([
    'product_id' => (string) $productA['product']['id'],
    'quantity' => 1,
    'channel' => 'retail',
    'size_label' => 'M',
    'colour_label' => 'Blue',
    'note' => 'race-a',
]);
$raceB = sale_create([
    'product_id' => (string) $productA['product']['id'],
    'quantity' => 1,
    'channel' => 'retail',
    'size_label' => 'M',
    'colour_label' => 'Blue',
    'note' => 'race-b',
]);
check(isset($raceA['sale_id']), 'first conflicting sale succeeds');
check(isset($raceB['errors']['form']) && $raceB['errors']['form'] === 'stock_below_zero', 'second conflicting sale is refused');
check_eq((int) stock_find((string) $stockA['id'])['quantity_hundredths'], 0, 'conflict leaves stock at floor');
stock_post_movement((string) $stockA['id'], 200, 'opening', 'restore-after-conflict');

$sizes = $productA['product']['sizes'] ?? [];
check(is_array($sizes) && in_array('M', $sizes, true), 'product variants (sizes) stored for tenant A');

$supplier = supplier_create(['name' => 'Supplier A', 'phone' => '08038880011', 'email' => '', 'note' => '']);
$po = purchase_order_create([
    'supplier_id' => (string) $supplier['supplier_id'],
    'stock_item_id' => (string) $stockA['id'],
    'quantity' => '1',
    'unit_cost_kobo' => '1200',
    'note' => '',
]);
purchase_order_receive((string) $po['purchase_order_id']);
$expense = expense_create(['category' => 'rent', 'amount_kobo' => '1000', 'spent_on' => app_today(), 'note' => '']);
$wo = work_order_create(['title' => 'Batch A', 'quantity' => 2, 'due_on' => '', 'note' => '']);
$branch = branch_create(['name' => 'Branch A', 'phone' => '', 'address' => '']);
storefront_settings_save(['slug' => 'remediation-a', 'enabled' => '1']);
$sfLow = storefront_place_order([
    'product_id' => (string) $productA['product']['id'],
    'quantity' => 20,
    'buyer_name' => 'Buyer',
    'buyer_phone' => '08038880099',
    'size_label' => 'M',
    'colour_label' => 'Blue',
]);
check(isset($sfLow['errors']['form']) && $sfLow['errors']['form'] === 'stock_below_zero', 'storefront order respects stock floor');
$sfOk = storefront_place_order([
    'product_id' => (string) $productA['product']['id'],
    'quantity' => 1,
    'buyer_name' => 'Buyer',
    'buyer_phone' => '08038880099',
    'size_label' => 'M',
    'colour_label' => 'Blue',
]);
check(isset($sfOk['sale_id']), 'storefront order deducts stock when available');

$customer = customer_create(['name' => 'Opt', 'phone' => '08038880021', 'gender' => 'female', 'customer_type' => 'regular']);
db_update('fashion_customers', ['messages_opt_in' => 1], ['id' => (string) $customer['id']]);
$broadcast = broadcast_create(['title' => 'Hello', 'body' => 'Offer']);

$idsA = [
    'product' => (string) $productA['product']['id'],
    'sale' => (string) $saleOk['sale_id'],
    'stock' => (string) $stockA['id'],
    'supplier' => (string) $supplier['supplier_id'],
    'po' => (string) $po['purchase_order_id'],
    'expense' => (string) $expense['expense_id'],
    'work_order' => (string) $wo['work_order_id'],
    'branch' => (string) $branch['branch_id'],
    'broadcast' => (string) $broadcast['broadcast_id'],
    'tenant' => (string) current_tenant_id(),
];

check(subscription_has_app_access() === true, 'entitled tenant has app access');
subscription_suspend('remediation-test');
check(subscription_has_app_access() === false, 'suspended tenant loses app access');
$starter = plan_by_code('starter');
subscription_activate((string) $starter['id'], 'monthly');
check(subscription_has_app_access() === true, 'reactivated tenant regains access');

auth_logout();
auth_release_tenant();
$shopB = business_register([
    'business_name' => 'Remediation B',
    'owner_name' => 'Owner B',
    'email' => 'remediation-b@example.com',
    'password' => 'password',
    'phone' => '08038880002',
    'company_type' => 'brand',
]);
module_save(['fashion.orders', 'fashion.finance', 'fashion.inventory', 'fashion.sales', 'fashion.communication', 'fashion.production', 'fashion.staff']);

check(product_get($idsA['product']) === null, 'tenant B cannot load tenant A product');
check(sale_list() === [], 'tenant B does not list tenant A sales');
check(db_select('fashion_sale_lines', ['sale_id' => $idsA['sale']]) === [], 'tenant B cannot read tenant A sale lines');
check(db_select('fashion_sales', ['id' => $idsA['sale']], ['limit' => 1]) === [], 'tenant B cannot read tenant A returns/sales');
check(db_select('fashion_suppliers', ['id' => $idsA['supplier']], ['limit' => 1]) === [], 'tenant B cannot read tenant A supplier');
check(db_select('fashion_purchase_orders', ['id' => $idsA['po']], ['limit' => 1]) === [], 'tenant B cannot read tenant A PO');
check(db_select('fashion_expenses', ['id' => $idsA['expense']], ['limit' => 1]) === [], 'tenant B cannot read tenant A expense');
check(db_select('fashion_work_orders', ['id' => $idsA['work_order']], ['limit' => 1]) === [], 'tenant B cannot read tenant A work order');
check(db_select('fashion_branches', ['id' => $idsA['branch']], ['limit' => 1]) === [], 'tenant B cannot read tenant A branch');
check(db_select('fashion_broadcasts', ['id' => $idsA['broadcast']], ['limit' => 1]) === [], 'tenant B cannot read tenant A broadcast');
check(db_select('fashion_stock_movements', ['item_id' => $idsA['stock']], ['limit' => 1]) === [], 'tenant B cannot read tenant A stock movements');
check(db_select('fashion_product_bom', ['product_id' => $idsA['product']]) === [], 'tenant B cannot read tenant A product BOM/variants lines');
$hijack = product_update($idsA['product'], ['name' => 'Hijack', 'sku' => 'X', 'description' => '', 'price_kobo' => '1', 'sizes' => 'L', 'colours' => 'Red']);
check(isset($hijack['errors']), 'tenant B cannot update tenant A product');
$hijackReturn = sale_return($idsA['sale']);
check(isset($hijackReturn['errors']['form']) && $hijackReturn['errors']['form'] === 'not_found', 'tenant B cannot return tenant A sale');
storefront_settings_save(['slug' => 'remediation-b', 'enabled' => '1']);
auth_logout();
auth_release_tenant();
check(storefront_bind('remediation-a') !== null, 'storefront A remains bindable by slug');
auth_release_tenant();
$boundB = storefront_bind('remediation-b');
check($boundB !== null, 'storefront B binds');
$names = array_column(storefront_products(), 'name');
check(!in_array('Product A', $names, true), 'storefront B does not show tenant A products');
auth_release_tenant();

check(is_platform_admin() === false, 'shop session is not platform admin');
try {
    require_platform_admin();
    check(false, 'shop owner cannot require_platform_admin');
} catch (Throwable $e) {
    check($e instanceof HttpException || $e instanceof RedirectException, 'shop owner blocked from platform admin');
}

$rateSlug = 'remediation-b';
$dir = dirname(__DIR__) . '/storage/ratelimit/storefront';
if (!is_dir($dir)) {
    mkdir($dir, 0770, true);
}
$path = storefront_order_rate_path($rateSlug);
$hits = [];
for ($i = 0; $i < storefront_order_rate_max(); $i++) {
    $hits[] = time();
}
file_put_contents($path, json_encode($hits), LOCK_EX);
check(storefront_order_rate_limited($rateSlug) === true, 'storefront order rate limit engages');
@unlink($path);
check(storefront_order_rate_limited($rateSlug) === false, 'clearing rate file allows orders again');

http_response_code(200);
$_SERVER['REQUEST_URI'] = base_path() . '/shop/no-such-remediation-shop';
try {
    ob_start();
    router_dispatch('GET', '/shop/no-such-remediation-shop');
    $body = (string) ob_get_clean();
    check(http_response_code() === 404, 'public storefront unknown slug sets 404');
    check(str_contains($body, 'Page not found') || str_contains($body, t('not_found')), 'public storefront 404 body is safe');
} catch (HttpException $e) {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    check_eq($e->status(), 404, 'public storefront unknown slug throws 404');
}

// GET billing return must not mutate when owner is signed in with a pending reference.
auth_login((string) $shopB['user_id'], (string) $shopB['tenant_id']);
$payBefore = count(db_select('fashion_subscription_payments', []));
$checkoutBefore = count(db_select('fashion_subscription_checkouts', []));
$_GET = ['reference' => 'sb' . bin2hex(random_bytes(8))];
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = base_path() . '/account/billing/return';
$level = ob_get_level();
ob_start();
try {
    router_dispatch('GET', '/account/billing/return');
} catch (RedirectException $e) {
    // owners without billing entitlement may redirect; still must not write
} catch (HttpException $e) {
    // 403/other is fine as long as no writes
} finally {
    while (ob_get_level() > $level) {
        ob_end_clean();
    }
}
$payAfter = count(db_select('fashion_subscription_payments', []));
$checkoutAfter = count(db_select('fashion_subscription_checkouts', []));
check_eq($payAfter, $payBefore, 'GET billing return does not insert subscription payments');
check_eq($checkoutAfter, $checkoutBefore, 'GET billing return does not insert checkouts');
auth_logout();
auth_release_tenant();
