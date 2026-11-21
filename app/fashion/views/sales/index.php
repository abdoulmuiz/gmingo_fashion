<?php
/** @var array $sales */
/** @var array $products */
/** @var array $channels */
/** @var array $errors */
/** @var array $old */
?>
<section class="card">
    <h1><?= e(t('sales')) ?></h1>
    <p class="meta"><?= e(t('sales_help')) ?></p>
    <form method="post" action="<?= e(url('/sales')) ?>" class="stack">
        <?= csrf_field() ?>
        <label><?= e(t('product')) ?>
            <select name="product_id" required>
                <option value=""><?= e(t('choose')) ?></option>
                <?php foreach ($products as $product): ?>
                    <option value="<?= e($product['id']) ?>" <?= ((string) ($old['product_id'] ?? '') === $product['id']) ? 'selected' : '' ?>>
                        <?= e($product['name']) ?> — <?= e((string) $product['price_kobo']) ?> kobo
                    </option>
                <?php endforeach; ?>
            </select>
        </label>
        <?php if (isset($errors['product_id'])): ?><p class="error"><?= e(t($errors['product_id'])) ?></p><?php endif; ?>
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
        <button class="btn" type="submit"><?= e(t('sale_record')) ?></button>
    </form>
</section>
<section class="card">
    <h2><?= e(t('sales_recent')) ?></h2>
    <?php if ($sales === []): ?>
        <p><?= e(t('sales_empty')) ?></p>
    <?php else: ?>
        <?php foreach ($sales as $sale): ?>
            <div class="line">
                <strong><?= e($sale['sale_number']) ?></strong>
                <span><?= e(t('sale_channel_' . $sale['channel'])) ?></span>
                <span><?= e((string) $sale['total_kobo']) ?> kobo</span>
                <span><?= e(t('sale_status_' . $sale['status'])) ?></span>
                <?php if ($sale['status'] !== 'returned'): ?>
                    <form method="post" action="<?= e(url('/sales/' . $sale['id'] . '/return')) ?>">
                        <?= csrf_field() ?>
                        <button class="btn btn-small btn-quiet" type="submit"><?= e(t('sale_return')) ?></button>
                    </form>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</section>
