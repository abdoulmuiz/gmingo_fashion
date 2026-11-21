<?php
/** @var string $mode */
/** @var ?array $product */
/** @var array $bom */
/** @var ?int $cost_kobo */
/** @var array $stock_items */
/** @var array $errors */
/** @var array $old */
$isEdit = $mode === 'edit' && is_array($product);
$name = (string) ($old['name'] ?? ($product['name'] ?? ''));
$sku = (string) ($old['sku'] ?? ($product['sku'] ?? ''));
$description = (string) ($old['description'] ?? ($product['description'] ?? ''));
$price = (string) ($old['price_kobo'] ?? ($product['price_kobo'] ?? '0'));
$sizes = (string) ($old['sizes'] ?? implode(', ', $product['sizes'] ?? []));
$colours = (string) ($old['colours'] ?? implode(', ', $product['colours'] ?? []));
$active = array_key_exists('active', $old) ? !empty($old['active']) : ($product['active'] ?? true);
?>
<section class="card">
    <h1><?= e(t($isEdit ? 'product_edit' : 'product_add')) ?></h1>
    <p><a href="<?= e(url('/products')) ?>"><?= e(t('products')) ?></a></p>
    <?php if (!empty($errors['form'])): ?>
        <p class="error"><?= e(t((string) $errors['form'])) ?></p>
    <?php endif; ?>
    <form method="post" action="<?= e(url($isEdit ? '/products/' . $product['id'] : '/products/new')) ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="save">
        <label><?= e(t('name')) ?>
            <input name="name" value="<?= e($name) ?>" required>
        </label>
        <label><?= e(t('product_sku')) ?>
            <input name="sku" value="<?= e($sku) ?>">
        </label>
        <label><?= e(t('product_description')) ?>
            <textarea name="description" rows="2"><?= e($description) ?></textarea>
        </label>
        <label><?= e(t('product_price_kobo')) ?>
            <input name="price_kobo" inputmode="numeric" value="<?= e($price) ?>" required>
        </label>
        <label><?= e(t('product_sizes')) ?>
            <input name="sizes" value="<?= e($sizes) ?>" placeholder="S, M, L">
        </label>
        <label><?= e(t('product_colours')) ?>
            <input name="colours" value="<?= e($colours) ?>" placeholder="Black, Cream">
        </label>
        <?php if ($isEdit): ?>
            <label class="check">
                <input type="checkbox" name="active" value="1" <?= $active ? 'checked' : '' ?>>
                <?= e(t('product_active')) ?>
            </label>
        <?php endif; ?>
        <button class="btn" type="submit"><?= e(t('save')) ?></button>
    </form>
</section>

<?php if ($isEdit): ?>
<section class="card">
    <h2><?= e(t('product_bom')) ?></h2>
    <p class="meta"><?= e(t('product_bom_help')) ?></p>
    <?php if ($cost_kobo !== null): ?>
        <p><strong><?= e(t('product_estimated_cost')) ?>:</strong> <?= e((string) $cost_kobo) ?> kobo</p>
    <?php endif; ?>
    <?php foreach ($bom as $line): ?>
        <p class="meta">
            <?= e((string) $line['item_name']) ?>
            · <?= e(stock_format((int) $line['quantity_hundredths'])) ?> <?= e((string) $line['unit']) ?>
            <?php if ($line['line_cost_kobo'] !== null): ?>
                · <?= e((string) $line['line_cost_kobo']) ?> kobo
            <?php endif; ?>
        </p>
        <form method="post" action="<?= e(url('/products/' . $product['id'])) ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="bom_remove">
            <input type="hidden" name="bom_id" value="<?= e((string) $line['id']) ?>">
            <button class="btn btn-quiet" type="submit"><?= e(t('remove')) ?></button>
        </form>
    <?php endforeach; ?>
    <?php if ($stock_items !== []): ?>
        <form method="post" action="<?= e(url('/products/' . $product['id'])) ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="bom_add">
            <label><?= e(t('stock')) ?>
                <select name="stock_item_id">
                    <?php foreach ($stock_items as $item): ?>
                        <option value="<?= e((string) $item['id']) ?>"><?= e((string) $item['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label><?= e(t('quantity')) ?>
                <input name="quantity" value="1.00">
            </label>
            <button class="btn" type="submit"><?= e(t('product_bom_add')) ?></button>
        </form>
    <?php else: ?>
        <p class="meta"><?= e(t('product_bom_need_stock')) ?></p>
    <?php endif; ?>
</section>
<?php endif; ?>
