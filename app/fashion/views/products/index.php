<?php
/** @var array $products */
$activeCount = 0;
foreach ($products as $product) {
    if (!empty($product['active'])) {
        $activeCount++;
    }
}
ob_start();
?>
<a class="btn" href="<?= e(url('/products/new')) ?>">
    <?= ui_icon('plus', 'ui-icon') ?>
    <span><?= e(t('product_add')) ?></span>
</a>
<?php
$actions_html = ob_get_clean();
$title = t('products');
$back_href = null;
$subtitle = t('products_help');
require __DIR__ . '/../partials/ui_page_header.php';
?>
<section class="dash-kpis" aria-label="<?= e(t('summary')) ?>">
    <article class="dash-kpi dash-kpi-blue">
        <span class="dash-kpi-top">
            <span class="dash-kpi-icon" aria-hidden="true"><?= ui_icon('shirt', 'ui-icon') ?></span>
            <span class="dash-kpi-body">
                <span class="dash-kpi-label"><?= e(t('products')) ?></span>
                <strong class="dash-kpi-value"><?= e((string) count($products)) ?></strong>
            </span>
        </span>
    </article>
    <article class="dash-kpi dash-kpi-green">
        <span class="dash-kpi-top">
            <span class="dash-kpi-body">
                <span class="dash-kpi-label"><?= e(t('product_active')) ?></span>
                <strong class="dash-kpi-value"><?= e((string) $activeCount) ?></strong>
            </span>
        </span>
    </article>
</section>

<section class="card list-page-shell" style="margin-top:1rem">
    <?php if ($products === []): ?>
        <?php
        $title = t('products_empty');
        $help = t('products_help');
        $icon = 'shirt';
        $cta_href = '/products/new';
        $cta_label = t('product_add');
        require __DIR__ . '/../partials/ui_empty_state.php';
        ?>
    <?php else: ?>
        <div class="ui-table-wrap">
            <table class="ui-table">
                <thead>
                    <tr>
                        <th><?= e(t('name')) ?></th>
                        <th><?= e(t('product_sku')) ?></th>
                        <th class="is-num"><?= e(t('total')) ?></th>
                        <th><?= e(t('type')) ?></th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($products as $product): ?>
                        <tr>
                            <td data-label="<?= e(t('name')) ?>">
                                <a href="<?= e(url('/products/' . $product['id'])) ?>"><strong><?= e((string) $product['name']) ?></strong></a>
                            </td>
                            <td data-label="<?= e(t('product_sku')) ?>"><?= e((string) $product['sku']) ?></td>
                            <td class="is-num" data-label="<?= e(t('total')) ?>"><?= e(money((int) $product['price_kobo'])) ?></td>
                            <td data-label="<?= e(t('type')) ?>">
                                <span class="orders-badge is-<?= $product['active'] ? 'success' : 'orange' ?>">
                                    <?= e($product['active'] ? t('product_active') : t('product_inactive')) ?>
                                </span>
                            </td>
                            <td data-label="">
                                <a class="btn btn-small btn-quiet" href="<?= e(url('/products/' . $product['id'])) ?>"><?= e(t('view')) ?></a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>