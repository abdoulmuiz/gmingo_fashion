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

$title = t($isEdit ? 'product_edit' : 'product_add');
$back_href = '/products';
$back_label = t('products');
$subtitle = null;
$actions_html = '';
require __DIR__ . '/../partials/ui_page_header.php';
?>
<section class="card form-sheet">
    <?php if (!empty($errors['form'])): ?>
        <p class="ui-error" role="alert"><?= e(t((string) $errors['form'])) ?></p>
    <?php endif; ?>
    <form method="post" action="<?= e(url($isEdit ? '/products/' . $product['id'] : '/products/new')) ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="save">
        <div class="form-sections">
            <div class="form-grid">
                <div class="span-2">
                    <label class="ui-label" for="field-name"><?= e(t('name')) ?> *</label>
                    <input id="field-name" name="name" value="<?= e($name) ?>" required>
                </div>
                <div>
                    <label class="ui-label" for="field-sku"><?= e(t('product_sku')) ?></label>
                    <input id="field-sku" name="sku" value="<?= e($sku) ?>">
                </div>
                <div>
                    <label class="ui-label" for="field-price_kobo"><?= e(t('product_price_kobo')) ?> *</label>
                    <input id="field-price_kobo" name="price_kobo" inputmode="numeric" value="<?= e($price) ?>" required>
                </div>
                <div class="span-2">
                    <label class="ui-label" for="field-description"><?= e(t('product_description')) ?></label>
                    <textarea id="field-description" name="description" rows="2"><?= e($description) ?></textarea>
                </div>
                <div>
                    <label class="ui-label" for="field-sizes"><?= e(t('product_sizes')) ?></label>
                    <input id="field-sizes" name="sizes" value="<?= e($sizes) ?>" placeholder="S, M, L">
                </div>
                <div>
                    <label class="ui-label" for="field-colours"><?= e(t('product_colours')) ?></label>
                    <input id="field-colours" name="colours" value="<?= e($colours) ?>" placeholder="Black, Cream">
                </div>
                <?php if ($isEdit): ?>
                    <div class="span-2">
                        <label class="ui-check">
                            <input type="checkbox" name="active" value="1" <?= $active ? 'checked' : '' ?>>
                            <span><?= e(t('product_active')) ?></span>
                        </label>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <div class="form-sheet-footer">
            <a class="btn btn-quiet" href="<?= e(url('/products')) ?>"><?= e(t('cancel')) ?></a>
            <button class="btn" type="submit"><?= e(t('save')) ?></button>
        </div>
    </form>
</section>

<?php if ($isEdit): ?>
    <div class="detail-layout" style="margin-top:1rem">
        <div class="detail-main">
            <section class="card">
                <h2><?= e(t('product_bom')) ?></h2>
                <p class="meta"><?= e(t('product_bom_help')) ?></p>
                <?php if ($cost_kobo !== null): ?>
                    <p class="dash-kpi-label"><?= e(t('product_estimated_cost')) ?>: <strong><?= e(money($cost_kobo)) ?></strong></p>
                <?php endif; ?>
                <?php if ($bom === []): ?>
                    <p class="meta"><?= e(t('product_bom_need_stock')) ?></p>
                <?php else: ?>
                    <div class="ui-table-wrap">
                        <table class="ui-table">
                            <thead>
                                <tr>
                                    <th><?= e(t('stock')) ?></th>
                                    <th class="is-num"><?= e(t('quantity')) ?></th>
                                    <th class="is-num"><?= e(t('total')) ?></th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($bom as $line): ?>
                                    <tr>
                                        <td data-label="<?= e(t('stock')) ?>"><strong><?= e((string) $line['item_name']) ?></strong></td>
                                        <td class="is-num" data-label="<?= e(t('quantity')) ?>">
                                            <?= e(stock_format((int) $line['quantity_hundredths'])) ?> <?= e((string) $line['unit']) ?>
                                        </td>
                                        <td class="is-num" data-label="<?= e(t('total')) ?>">
                                            <?php if ($line['line_cost_kobo'] !== null): ?>
                                                <?= e(money((int) $line['line_cost_kobo'])) ?>
                                            <?php else: ?>
                                                —
                                            <?php endif; ?>
                                        </td>
                                        <td data-label="">
                                            <form method="post" action="<?= e(url('/products/' . $product['id'])) ?>">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="action" value="bom_remove">
                                                <input type="hidden" name="bom_id" value="<?= e((string) $line['id']) ?>">
                                                <button class="btn btn-quiet btn-small" type="submit"><?= e(t('remove')) ?></button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>

                <?php if ($stock_items !== []): ?>
                    <form method="post" action="<?= e(url('/products/' . $product['id'])) ?>" class="form-grid" style="margin-top:1rem">
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
                        <div class="span-2">
                            <button class="btn" type="submit"><?= e(t('product_bom_add')) ?></button>
                        </div>
                    </form>
                <?php else: ?>
                    <p class="meta"><?= e(t('product_bom_need_stock')) ?></p>
                <?php endif; ?>
            </section>
        </div>
    </div>
<?php endif; ?>