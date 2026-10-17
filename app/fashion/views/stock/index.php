<section class="card">
    <div class="split">
        <h1><?= e(t('stock')) ?></h1>
        <a class="btn btn-small" href="<?= e(url('/stock/new')) ?>"><?= e(t('new_stock_item')) ?></a>
    </div>
    <form method="get" action="<?= e(url('/stock')) ?>">
        <label><?= e(t('search')) ?>
            <input name="q" value="<?= e($search) ?>">
        </label>
        <label><?= e(t('stock_category')) ?>
            <select name="category">
                <option value=""><?= e(t('stock_all_categories')) ?></option>
                <?php foreach (stock_categories() as $choice): ?>
                    <option value="<?= e($choice) ?>" <?= $category === $choice ? 'selected' : '' ?>><?= e(t('stock_cat_' . $choice)) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <button class="btn" type="submit"><?= e(t('search')) ?></button>
    </form>
</section>
<section class="card">
    <h2><?= e(t('low_stock_items')) ?></h2>
    <?php if ($low_items === []): ?>
        <p><?= e(t('stock_none_low')) ?></p>
    <?php endif; ?>
    <?php foreach ($low_items as $item): ?>
        <a class="line" href="<?= e(url('/stock/' . $item['id'])) ?>">
            <strong><?= e($item['name']) ?></strong>
            <span><?= e(stock_format((int) $item['quantity_hundredths'])) ?> <?= e(t('stock_unit_' . $item['unit'])) ?></span>
        </a>
        <p class="meta"><?= e(t('stock_minimum')) ?> <?= e(stock_format((int) $item['minimum_hundredths'])) ?></p>
    <?php endforeach; ?>
</section>
<?php if ($items === []): ?>
    <section class="card"><p><?= e(t('no_stock')) ?></p></section>
<?php endif; ?>
<?php foreach ($items as $item): ?>
    <section class="card">
        <a href="<?= e(url('/stock/' . $item['id'])) ?>"><strong><?= e($item['name']) ?></strong></a>
        <span><?= e(stock_format((int) $item['quantity_hundredths'])) ?> <?= e(t('stock_unit_' . $item['unit'])) ?></span>
        <span class="tag"><?= e(t('stock_cat_' . $item['category'])) ?></span>
        <?php if ($item['colour'] !== null): ?>
            <span><?= e($item['colour']) ?></span>
        <?php endif; ?>
        <p class="meta">
            <?= e(t('stock_last_change')) ?>
            <?= e($item['last_user_name'] ?? t('stock_no_change')) ?>
        </p>
        <?php
        $item_id = $item['id'];
        $back = '/stock';
        require __DIR__ . '/move_forms.php';
        ?>
    </section>
<?php endforeach; ?>
<?php if ($has_previous || $has_next): ?>
    <nav class="pager" aria-label="<?= e(t('stock')) ?>">
        <?php if ($has_previous): ?>
            <a href="<?= e(url($previous_path)) ?>"><?= e(t('previous')) ?></a>
        <?php endif; ?>
        <?php if ($has_next): ?>
            <a href="<?= e(url($next_path)) ?>"><?= e(t('next')) ?></a>
        <?php endif; ?>
    </nav>
<?php endif; ?>
