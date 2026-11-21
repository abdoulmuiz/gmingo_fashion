<?php
/** @var array $shop */
/** @var array $products */
/** @var array $errors */
/** @var array $old */
?>
<section class="card storefront-hero">
    <h1><?= e((string) $shop['name']) ?></h1>
    <p class="meta"><?= e(t('storefront_public_help')) ?></p>
</section>
<?php if ($products === []): ?>
    <section class="card"><p><?= e(t('products_empty')) ?></p></section>
<?php else: ?>
    <?php foreach ($products as $product): ?>
        <section class="card">
            <h2><?= e($product['name']) ?></h2>
            <p><?= e($product['description']) ?></p>
            <p><strong><?= e((string) $product['price_kobo']) ?> kobo</strong></p>
            <form method="post" action="<?= e(url('/shop/' . $shop['slug'])) ?>" class="stack">
                <?= csrf_field() ?>
                <input type="hidden" name="product_id" value="<?= e($product['id']) ?>">
                <label><?= e(t('quantity')) ?>
                    <input name="quantity" type="number" min="1" max="20" value="1" required>
                </label>
                <label><?= e(t('product_sizes')) ?>
                    <input name="size_label" value="">
                </label>
                <label><?= e(t('product_colours')) ?>
                    <input name="colour_label" value="">
                </label>
                <label><?= e(t('buyer_name')) ?>
                    <input name="buyer_name" value="<?= e((string) ($old['buyer_name'] ?? '')) ?>" required>
                </label>
                <label><?= e(t('buyer_phone')) ?>
                    <input name="buyer_phone" value="<?= e((string) ($old['buyer_phone'] ?? '')) ?>" required>
                </label>
                <?php if (isset($errors['buyer_phone'])): ?><p class="error"><?= e(t($errors['buyer_phone'])) ?></p><?php endif; ?>
                <button class="btn" type="submit"><?= e(t('storefront_order')) ?></button>
            </form>
        </section>
    <?php endforeach; ?>
<?php endif; ?>
