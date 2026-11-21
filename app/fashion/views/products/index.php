<?php
/** @var array $products */
?>
<section class="card">
    <h1><?= e(t('products')) ?></h1>
    <p class="meta"><?= e(t('products_help')) ?></p>
    <p><a class="btn" href="<?= e(url('/products/new')) ?>"><?= e(t('product_add')) ?></a></p>
    <?php if ($products === []): ?>
        <p><?= e(t('products_empty')) ?></p>
    <?php else: ?>
        <?php foreach ($products as $product): ?>
            <a class="line" href="<?= e(url('/products/' . $product['id'])) ?>">
                <strong><?= e((string) $product['name']) ?></strong>
                <span><?= e((string) $product['sku']) ?></span>
                <span><?= e((string) $product['price_kobo']) ?> kobo</span>
                <span><?= e($product['active'] ? t('product_active') : t('product_inactive')) ?></span>
            </a>
        <?php endforeach; ?>
    <?php endif; ?>
</section>
