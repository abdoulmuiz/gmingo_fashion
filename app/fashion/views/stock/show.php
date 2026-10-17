<section class="card">
    <p><a href="<?= e(url('/stock')) ?>"><?= e(t('stock')) ?></a></p>
    <h1><?= e($item['name']) ?></h1>
    <p class="meta">
        <?= e(t('stock_cat_' . $item['category'])) ?>
        · <?= e(t('stock_unit_' . $item['unit'])) ?>
        <?php if ($item['colour'] !== null): ?> · <?= e($item['colour']) ?><?php endif; ?>
    </p>
    <div class="stats">
        <div><span><?= e(t('stock_quantity')) ?></span><strong><?= e(stock_format((int) $item['quantity_hundredths'])) ?></strong></div>
        <div>
            <span><?= e(t('stock_minimum')) ?></span>
            <strong><?= e($item['minimum_hundredths'] === null ? t('stock_minimum_none') : stock_format((int) $item['minimum_hundredths'])) ?></strong>
        </div>
    </div>
    <?php
    $item_id = $item['id'];
    $back = '/stock/' . $item['id'];
    require __DIR__ . '/move_forms.php';
    ?>
</section>
<section class="card">
    <h2><?= e(t('stock_history')) ?></h2>
    <?php if ($rows === []): ?>
        <p><?= e(t('no_movements')) ?></p>
    <?php endif; ?>
    <?php foreach ($rows as $row): ?>
        <div class="line">
            <strong><?= e(stock_format((int) $row['change_hundredths'])) ?></strong>
            <span><?= e(t('stock_reason_' . $row['reason'])) ?></span>
            <span><?= e($row['user_name']) ?></span>
            <span><?= e(date('j M Y H:i', strtotime((string) $row['created_at']))) ?></span>
        </div>
        <?php if ($row['order_id'] !== null && $row['order_number'] !== ''): ?>
            <p class="meta"><a href="<?= e(url('/orders/' . $row['order_id'])) ?>"><?= e($row['order_number']) ?></a></p>
        <?php endif; ?>
        <?php if ($row['note'] !== ''): ?>
            <p><?= e($row['note']) ?></p>
        <?php endif; ?>
    <?php endforeach; ?>
    <?php if ($has_previous || $has_next): ?>
        <nav class="pager" aria-label="<?= e(t('stock_history')) ?>">
            <?php if ($has_previous): ?>
                <a href="<?= e(url('/stock/' . $item['id'] . '?page=' . (string) ($page - 1))) ?>"><?= e(t('previous')) ?></a>
            <?php endif; ?>
            <?php if ($has_next): ?>
                <a href="<?= e(url('/stock/' . $item['id'] . '?page=' . (string) ($page + 1))) ?>"><?= e(t('next')) ?></a>
            <?php endif; ?>
        </nav>
    <?php endif; ?>
</section>
