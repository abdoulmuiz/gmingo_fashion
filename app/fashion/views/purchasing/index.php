<?php
/** @var array $suppliers */
/** @var array $orders */
/** @var array $stock_items */
/** @var array $errors */
/** @var array $old */
$title = t('purchasing');
$back_href = null;
$subtitle = t('purchasing_help');
$actions_html = '';
require __DIR__ . '/../partials/ui_page_header.php';

$openOrders = 0;
foreach ($orders as $order) {
    if (($order['status'] ?? '') !== 'received') {
        $openOrders++;
    }
}
?>
<section class="dash-kpis" aria-label="<?= e(t('summary')) ?>">
    <article class="dash-kpi dash-kpi-blue">
        <span class="dash-kpi-top">
            <span class="dash-kpi-icon" aria-hidden="true"><?= ui_icon('shopping-cart', 'ui-icon') ?></span>
            <span class="dash-kpi-body">
                <span class="dash-kpi-label"><?= e(t('purchase_orders')) ?></span>
                <strong class="dash-kpi-value"><?= e((string) count($orders)) ?></strong>
            </span>
        </span>
    </article>
    <article class="dash-kpi dash-kpi-orange">
        <span class="dash-kpi-top">
            <span class="dash-kpi-body">
                <span class="dash-kpi-label"><?= e(t('supplier_add')) ?></span>
                <strong class="dash-kpi-value"><?= e((string) count($suppliers)) ?></strong>
            </span>
        </span>
    </article>
    <article class="dash-kpi dash-kpi-purple">
        <span class="dash-kpi-top">
            <span class="dash-kpi-body">
                <span class="dash-kpi-label"><?= e(t('purchase_order_receive')) ?></span>
                <strong class="dash-kpi-value"><?= e((string) $openOrders) ?></strong>
            </span>
        </span>
    </article>
</section>

<div class="detail-layout" style="margin-top:1rem">
    <div class="detail-main">
        <section class="card">
            <h2><?= e(t('purchase_order_add')) ?></h2>
            <form method="post" action="<?= e(url('/purchasing/orders')) ?>" class="form-grid">
                <?= csrf_field() ?>
                <label><?= e(t('supplier')) ?>
                    <select name="supplier_id" required>
                        <option value=""><?= e(t('choose')) ?></option>
                        <?php foreach ($suppliers as $supplier): ?>
                            <?php if (!$supplier['active']) { continue; } ?>
                            <option value="<?= e($supplier['id']) ?>"><?= e($supplier['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label><?= e(t('stock')) ?>
                    <select name="stock_item_id" required>
                        <option value=""><?= e(t('choose')) ?></option>
                        <?php foreach ($stock_items as $item): ?>
                            <option value="<?= e($item['id']) ?>"><?= e($item['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label><?= e(t('quantity')) ?>
                    <input name="quantity" value="<?= e((string) ($old['quantity'] ?? '1')) ?>" required>
                </label>
                <label><?= e(t('unit_cost_kobo')) ?>
                    <input name="unit_cost_kobo" value="<?= e((string) ($old['unit_cost_kobo'] ?? '0')) ?>" required>
                </label>
                <label class="span-2"><?= e(t('note')) ?>
                    <input name="note" value="<?= e((string) ($old['note'] ?? '')) ?>">
                </label>
                <div class="span-2">
                    <button class="btn" type="submit"><?= e(t('purchase_order_add')) ?></button>
                </div>
            </form>
        </section>

        <section class="card">
            <h2><?= e(t('purchase_orders')) ?></h2>
            <?php if ($orders === []): ?>
                <p><?= e(t('purchase_orders_empty')) ?></p>
            <?php else: ?>
                <div class="ui-table-wrap">
                    <table class="ui-table">
                        <thead>
                            <tr>
                                <th><?= e(t('supplier')) ?></th>
                                <th><?= e(t('type')) ?></th>
                                <th><?= e(t('due_date')) ?></th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($orders as $order): ?>
                                <tr>
                                    <td data-label="<?= e(t('supplier')) ?>"><strong><?= e($order['supplier_name']) ?></strong></td>
                                    <td data-label="<?= e(t('type')) ?>">
                                        <span class="orders-badge is-<?= $order['status'] === 'received' ? 'success' : 'blue' ?>"><?= e($order['status']) ?></span>
                                    </td>
                                    <td data-label="<?= e(t('due_date')) ?>"><?= e($order['ordered_on']) ?></td>
                                    <td data-label="">
                                        <?php if ($order['status'] !== 'received'): ?>
                                            <form method="post" action="<?= e(url('/purchasing/orders/' . $order['id'] . '/receive')) ?>">
                                                <?= csrf_field() ?>
                                                <button class="btn btn-small" type="submit"><?= e(t('purchase_order_receive')) ?></button>
                                            </form>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>
    </div>
    <aside class="detail-side">
        <section class="card">
            <h2><?= e(t('supplier_add')) ?></h2>
            <form method="post" action="<?= e(url('/purchasing/suppliers')) ?>" class="stack">
                <?= csrf_field() ?>
                <label><?= e(t('name')) ?>
                    <input name="name" value="<?= e((string) ($old['name'] ?? '')) ?>" required>
                </label>
                <label><?= e(t('phone')) ?>
                    <input name="phone" value="<?= e((string) ($old['phone'] ?? '')) ?>">
                </label>
                <label><?= e(t('email')) ?>
                    <input name="email" value="<?= e((string) ($old['email'] ?? '')) ?>">
                </label>
                <label><?= e(t('note')) ?>
                    <input name="note" value="<?= e((string) ($old['note'] ?? '')) ?>">
                </label>
                <button class="btn" type="submit"><?= e(t('supplier_add')) ?></button>
            </form>
            <?php foreach ($suppliers as $supplier): ?>
                <p class="line"><strong><?= e($supplier['name']) ?></strong> <span><?= e($supplier['phone']) ?></span></p>
            <?php endforeach; ?>
        </section>
    </aside>
</div>
