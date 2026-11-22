<?php
/** @var string $search */
/** @var string $category */
/** @var list<array> $items */
/** @var list<array> $low_items */
/** @var bool $has_previous */
/** @var bool $has_next */
/** @var string $previous_path */
/** @var string $next_path */
$lowCount = count($low_items);
$itemCount = count($items);
ob_start();
?>
<a class="btn" href="<?= e(url('/stock/new')) ?>">
    <?= ui_icon('plus', 'ui-icon') ?>
    <span><?= e(t('new_stock_item')) ?></span>
</a>
<?php
$actions_html = ob_get_clean();
$title = t('stock');
$back_href = null;
$subtitle = null;
require __DIR__ . '/../partials/ui_page_header.php';
?>
<section class="dash-kpis" aria-label="<?= e(t('summary')) ?>">
    <article class="dash-kpi dash-kpi-orange">
        <span class="dash-kpi-top">
            <span class="dash-kpi-icon" aria-hidden="true"><?= ui_icon('clock', 'ui-icon') ?></span>
            <span class="dash-kpi-body">
                <span class="dash-kpi-label"><?= e(t('low_stock_items')) ?></span>
                <strong class="dash-kpi-value"><?= e((string) $lowCount) ?></strong>
            </span>
        </span>
    </article>
    <article class="dash-kpi dash-kpi-blue">
        <span class="dash-kpi-top">
            <span class="dash-kpi-icon" aria-hidden="true"><?= ui_icon('package', 'ui-icon') ?></span>
            <span class="dash-kpi-body">
                <span class="dash-kpi-label"><?= e(t('stock')) ?></span>
                <strong class="dash-kpi-value"><?= e((string) $itemCount) ?></strong>
            </span>
        </span>
    </article>
</section>

<?php if ($low_items !== []): ?>
    <section class="card" style="margin-top:1rem">
        <h2><?= e(t('low_stock_items')) ?></h2>
        <div class="ui-table-wrap">
            <table class="ui-table">
                <thead>
                    <tr>
                        <th><?= e(t('stock_name')) ?></th>
                        <th class="is-num"><?= e(t('stock_quantity')) ?></th>
                        <th class="is-num"><?= e(t('stock_minimum')) ?></th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($low_items as $item): ?>
                        <tr>
                            <td data-label="<?= e(t('stock_name')) ?>">
                                <a href="<?= e(url('/stock/' . $item['id'])) ?>"><strong><?= e($item['name']) ?></strong></a>
                            </td>
                            <td class="is-num" data-label="<?= e(t('stock_quantity')) ?>">
                                <?= e(stock_format((int) $item['quantity_hundredths'])) ?> <?= e(t('stock_unit_' . $item['unit'])) ?>
                            </td>
                            <td class="is-num" data-label="<?= e(t('stock_minimum')) ?>">
                                <?= e(stock_format((int) $item['minimum_hundredths'])) ?>
                            </td>
                            <td data-label="">
                                <a class="btn btn-small btn-quiet" href="<?= e(url('/stock/' . $item['id'])) ?>"><?= e(t('view')) ?></a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
<?php endif; ?>

<section class="card list-page-shell" style="margin-top:1rem">
    <form method="get" action="<?= e(url('/stock')) ?>" class="form-grid ui-filter-inline">
        <label class="span-2"><?= e(t('search')) ?>
            <input name="q" value="<?= e($search) ?>" placeholder="<?= e(t('search')) ?>">
        </label>
        <label><?= e(t('stock_category')) ?>
            <select name="category">
                <option value=""><?= e(t('stock_all_categories')) ?></option>
                <?php foreach (stock_categories() as $choice): ?>
                    <option value="<?= e($choice) ?>" <?= $category === $choice ? 'selected' : '' ?>><?= e(t('stock_cat_' . $choice)) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <div style="display:flex;align-items:flex-end">
            <button class="btn" type="submit"><?= e(t('search')) ?></button>
        </div>
    </form>

    <?php if ($items === []): ?>
        <?php
        $title = t('no_stock');
        $help = null;
        $icon = 'package';
        $cta_href = '/stock/new';
        $cta_label = t('new_stock_item');
        require __DIR__ . '/../partials/ui_empty_state.php';
        ?>
    <?php else: ?>
        <div class="ui-table-wrap">
            <table class="ui-table">
                <thead>
                    <tr>
                        <th><?= e(t('stock_name')) ?></th>
                        <th class="is-num"><?= e(t('stock_quantity')) ?></th>
                        <th><?= e(t('stock_category')) ?></th>
                        <th><?= e(t('stock_colour')) ?></th>
                        <th><?= e(t('stock_last_change')) ?></th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $item): ?>
                        <?php
                        $qty = (int) $item['quantity_hundredths'];
                        $min = $item['minimum_hundredths'] !== null ? (int) $item['minimum_hundredths'] : null;
                        $isLow = $min !== null && $qty <= $min;
                        ?>
                        <tr>
                            <td data-label="<?= e(t('stock_name')) ?>">
                                <a href="<?= e(url('/stock/' . $item['id'])) ?>"><strong><?= e($item['name']) ?></strong></a>
                                <?php if ($isLow): ?>
                                    <span class="orders-badge is-orange"><?= e(t('low_stock_items')) ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="is-num" data-label="<?= e(t('stock_quantity')) ?>">
                                <?= e(stock_format($qty)) ?> <?= e(t('stock_unit_' . $item['unit'])) ?>
                            </td>
                            <td data-label="<?= e(t('stock_category')) ?>">
                                <span class="orders-badge is-blue"><?= e(t('stock_cat_' . $item['category'])) ?></span>
                            </td>
                            <td data-label="<?= e(t('stock_colour')) ?>"><?= $item['colour'] !== null ? e($item['colour']) : '—' ?></td>
                            <td data-label="<?= e(t('stock_last_change')) ?>">
                                <span class="meta"><?= e($item['last_user_name'] ?? t('stock_no_change')) ?></span>
                            </td>
                            <td data-label="">
                                <a class="btn btn-small btn-quiet" href="<?= e(url('/stock/' . $item['id'])) ?>"><?= e(t('view')) ?></a>
                            </td>
                        </tr>
                        <tr class="stock-move-row">
                            <td colspan="6" data-label="">
                                <?php
                                $item_id = $item['id'];
                                $back = '/stock';
                                require __DIR__ . '/move_forms.php';
                                ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php if ($has_previous || $has_next): ?>
            <nav class="pager" aria-label="<?= e(t('stock')) ?>">
                <?php if ($has_previous): ?>
                    <a class="btn btn-small btn-quiet" href="<?= e(url($previous_path)) ?>"><?= e(t('previous')) ?></a>
                <?php endif; ?>
                <?php if ($has_next): ?>
                    <a class="btn btn-small btn-quiet" href="<?= e(url($next_path)) ?>"><?= e(t('next')) ?></a>
                <?php endif; ?>
            </nav>
        <?php endif; ?>
    <?php endif; ?>
</section>