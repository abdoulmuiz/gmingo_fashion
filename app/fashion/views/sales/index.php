<?php
/** @var array $sales */
/** @var array $products */
/** @var array $channels */
/** @var array $errors */
/** @var array $old */
$title = t('sales');
$back_href = null;
$subtitle = t('sales_help');
$actions_html = '';
require __DIR__ . '/../partials/ui_page_header.php';

$saleCount = count($sales);
$saleTotal = 0;
foreach ($sales as $sale) {
    if (($sale['status'] ?? '') !== 'returned') {
        $saleTotal += (int) $sale['total_kobo'];
    }
}
?>
<section class="dash-kpis" aria-label="<?= e(t('summary')) ?>">
    <article class="dash-kpi dash-kpi-blue">
        <span class="dash-kpi-top">
            <span class="dash-kpi-icon" aria-hidden="true"><?= ui_icon('receipt', 'ui-icon') ?></span>
            <span class="dash-kpi-body">
                <span class="dash-kpi-label"><?= e(t('sales_recent')) ?></span>
                <strong class="dash-kpi-value"><?= e((string) $saleCount) ?></strong>
            </span>
        </span>
    </article>
    <article class="dash-kpi dash-kpi-green">
        <span class="dash-kpi-top">
            <span class="dash-kpi-body">
                <span class="dash-kpi-label"><?= e(t('total')) ?></span>
                <strong class="dash-kpi-value"><?= e(money($saleTotal)) ?></strong>
            </span>
        </span>
    </article>
</section>

<section class="card form-sheet" style="margin-top:1rem;max-width:none">
    <h2><?= e(t('sale_record')) ?></h2>
    <form method="post" action="<?= e(url('/sales')) ?>" class="form-grid">
        <?= csrf_field() ?>
        <label><?= e(t('product')) ?>
            <select name="product_id" required>
                <option value=""><?= e(t('choose')) ?></option>
                <?php foreach ($products as $product): ?>
                    <option value="<?= e($product['id']) ?>" <?= ((string) ($old['product_id'] ?? '') === $product['id']) ? 'selected' : '' ?>>
                        <?= e($product['name']) ?> — <?= e(money((int) $product['price_kobo'])) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>
        <?php if (isset($errors['product_id'])): ?><p class="ui-error"><?= e(t($errors['product_id'])) ?></p><?php endif; ?>
        <label><?= e(t('quantity')) ?>
            <input name="quantity" type="number" min="1" max="1000" value="<?= e((string) ($old['quantity'] ?? '1')) ?>" required>
        </label>
        <label><?= e(t('sale_channel')) ?>
            <select name="channel">
                <?php foreach ($channels as $channel): ?>
                    <option value="<?= e($channel) ?>" <?= ((string) ($old['channel'] ?? 'retail') === $channel) ? 'selected' : '' ?>><?= e(t('sale_channel_' . $channel)) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label><?= e(t('product_sizes')) ?>
            <input name="size_label" value="<?= e((string) ($old['size_label'] ?? '')) ?>">
        </label>
        <label><?= e(t('product_colours')) ?>
            <input name="colour_label" value="<?= e((string) ($old['colour_label'] ?? '')) ?>">
        </label>
        <label><?= e(t('note')) ?>
            <input name="note" value="<?= e((string) ($old['note'] ?? '')) ?>">
        </label>
        <div class="span-2">
            <button class="btn" type="submit"><?= e(t('sale_record')) ?></button>
        </div>
    </form>
</section>

<section class="card" style="margin-top:1rem">
    <h2><?= e(t('sales_recent')) ?></h2>
    <?php if ($sales === []): ?>
        <p><?= e(t('sales_empty')) ?></p>
    <?php else: ?>
        <div class="ui-table-wrap">
            <table class="ui-table">
                <thead>
                    <tr>
                        <th><?= e(t('sales')) ?></th>
                        <th><?= e(t('sale_channel')) ?></th>
                        <th><?= e(t('type')) ?></th>
                        <th class="is-num"><?= e(t('total')) ?></th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($sales as $sale): ?>
                        <tr>
                            <td data-label="<?= e(t('sales')) ?>"><strong><?= e($sale['sale_number']) ?></strong></td>
                            <td data-label="<?= e(t('sale_channel')) ?>"><?= e(t('sale_channel_' . $sale['channel'])) ?></td>
                            <td data-label="<?= e(t('type')) ?>">
                                <span class="orders-badge is-<?= $sale['status'] === 'returned' ? 'orange' : 'success' ?>"><?= e(t('sale_status_' . $sale['status'])) ?></span>
                            </td>
                            <td class="is-num" data-label="<?= e(t('total')) ?>"><?= e(money((int) $sale['total_kobo'])) ?></td>
                            <td data-label="">
                                <?php if ($sale['status'] !== 'returned'): ?>
                                    <form method="post" action="<?= e(url('/sales/' . $sale['id'] . '/return')) ?>">
                                        <?= csrf_field() ?>
                                        <button class="btn btn-small btn-quiet" type="submit"><?= e(t('sale_return')) ?></button>
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
