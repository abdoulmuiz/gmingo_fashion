<?php
/** @var array $errors */
/** @var array $old */
$title = t('new_stock_item');
$back_href = '/stock';
$back_label = t('stock');
$subtitle = null;
$actions_html = '';
require __DIR__ . '/../partials/ui_page_header.php';
?>
<section class="card form-sheet">
    <form method="post" action="<?= e(url('/stock')) ?>">
        <?= csrf_field() ?>
        <div class="form-sections">
            <div class="form-grid">
                <div class="span-2">
                    <label class="ui-label" for="field-name"><?= e(t('stock_name')) ?> *</label>
                    <input id="field-name" name="name" value="<?= e((string) ($old['name'] ?? '')) ?>" required>
                </div>
                <div>
                    <label class="ui-label" for="field-category"><?= e(t('stock_category')) ?></label>
                    <select id="field-category" name="category">
                        <?php foreach (stock_categories() as $choice): ?>
                            <option value="<?= e($choice) ?>" <?= (string) ($old['category'] ?? '') === $choice ? 'selected' : '' ?>><?= e(t('stock_cat_' . $choice)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="ui-label" for="field-unit"><?= e(t('stock_unit')) ?></label>
                    <select id="field-unit" name="unit">
                        <?php foreach (stock_units() as $choice): ?>
                            <option value="<?= e($choice) ?>" <?= (string) ($old['unit'] ?? '') === $choice ? 'selected' : '' ?>><?= e(t('stock_unit_' . $choice)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="ui-label" for="field-colour"><?= e(t('stock_colour')) ?></label>
                    <input id="field-colour" name="colour" value="<?= e((string) ($old['colour'] ?? '')) ?>">
                </div>
                <div>
                    <label class="ui-label" for="field-minimum"><?= e(t('stock_minimum')) ?></label>
                    <input id="field-minimum" name="minimum" inputmode="decimal" value="<?= e((string) ($old['minimum'] ?? '')) ?>">
                </div>
            </div>
        </div>
        <?php if ($errors !== []): ?>
            <?php
            $page_toasts = is_array($page_toasts ?? null) ? $page_toasts : [];
            $page_toasts[] = ['type' => 'error', 'message' => t((string) array_key_first($errors))];
            ?>
        <?php endif; ?>
        <div class="form-sheet-footer">
            <a class="btn btn-quiet" href="<?= e(url('/stock')) ?>"><?= e(t('cancel')) ?></a>
            <button class="btn" type="submit"><?= e(t('save')) ?></button>
        </div>
    </form>
</section>