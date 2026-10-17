<section class="card">
    <h1><?= e(t('new_stock_item')) ?></h1>
    <form method="post" action="<?= e(url('/stock')) ?>">
        <?= csrf_field() ?>
        <label><?= e(t('stock_name')) ?>
            <input name="name" value="<?= e((string) ($old['name'] ?? '')) ?>" required>
        </label>
        <label><?= e(t('stock_category')) ?>
            <select name="category">
                <?php foreach (stock_categories() as $choice): ?>
                    <option value="<?= e($choice) ?>" <?= (string) ($old['category'] ?? '') === $choice ? 'selected' : '' ?>><?= e(t('stock_cat_' . $choice)) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label><?= e(t('stock_unit')) ?>
            <select name="unit">
                <?php foreach (stock_units() as $choice): ?>
                    <option value="<?= e($choice) ?>" <?= (string) ($old['unit'] ?? '') === $choice ? 'selected' : '' ?>><?= e(t('stock_unit_' . $choice)) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label><?= e(t('stock_colour')) ?>
            <input name="colour" value="<?= e((string) ($old['colour'] ?? '')) ?>">
        </label>
        <label><?= e(t('stock_minimum')) ?>
            <input name="minimum" inputmode="decimal" value="<?= e((string) ($old['minimum'] ?? '')) ?>">
        </label>
        <?php if ($errors !== []): ?>
            <p class="flash flash-error" role="alert"><?= e(t((string) array_key_first($errors))) ?></p>
        <?php endif; ?>
        <button class="btn" type="submit"><?= e(t('save')) ?></button>
    </form>
</section>
