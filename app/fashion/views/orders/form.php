<section class="card">
    <h1><?= e(t('new_order')) ?></h1>
    <form method="post" action="<?= e(url('/orders')) ?>">
        <?= csrf_field() ?>
        <label><?= e(t('customer')) ?>
            <select name="customer_id" required>
                <option value=""><?= e(t('customer')) ?></option>
                <?php foreach ($customers as $customer): ?>
                    <option value="<?= e($customer['id']) ?>" <?= ($old['customer_id'] ?? '') === $customer['id'] ? 'selected' : '' ?>><?= e($customer['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <?php if (!empty($errors['customer_id'])): ?><p class="error"><?= e(t((string) $errors['customer_id'])) ?></p><?php endif; ?>
        <label><?= e(t('due_date')) ?>
            <input type="date" name="due_date" value="<?= e($old['due_date'] ?? '') ?>" required>
        </label>
        <?php if (!empty($errors['due_date'])): ?><p class="error"><?= e(t((string) $errors['due_date'])) ?></p><?php endif; ?>
        <label>
            <input type="checkbox" name="urgent" value="1" <?= !empty($old['urgent']) ? 'checked' : '' ?>>
            <?= e(t('urgent')) ?>
        </label>
        <h2><?= e(t('add_items')) ?></h2>
        <p class="meta"><?= e(t('item_hint')) ?></p>
        <?php if (!empty($errors['items'])): ?><p class="error"><?= e(t((string) $errors['items'])) ?></p><?php endif; ?>
        <?php for ($row = 0; $row < 4; $row++): ?>
            <?php $item = $old['items'][$row] ?? []; ?>
            <div class="item-row">
                <label><?= e(t('garment')) ?>
                    <select name="items[<?= e((string) $row) ?>][garment_type_id]">
                        <option value=""><?= e(t('garment')) ?></option>
                        <?php foreach ($garments as $garment): ?>
                            <option value="<?= e($garment['id']) ?>" <?= ($item['garment_type_id'] ?? '') === $garment['id'] ? 'selected' : '' ?>><?= e($garment['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label><?= e(t('quantity')) ?>
                    <input name="items[<?= e((string) $row) ?>][quantity]" value="<?= e((string) ($item['quantity'] ?? '')) ?>" inputmode="numeric">
                </label>
                <label><?= e(t('price')) ?>
                    <input name="items[<?= e((string) $row) ?>][price]" value="<?= e((string) ($item['price'] ?? '')) ?>" inputmode="decimal">
                </label>
                <label><?= e(t('fabric')) ?>
                    <input name="items[<?= e((string) $row) ?>][fabric]" value="<?= e((string) ($item['fabric'] ?? '')) ?>">
                </label>
                <label><?= e(t('notes')) ?>
                    <input name="items[<?= e((string) $row) ?>][notes]" value="<?= e((string) ($item['notes'] ?? '')) ?>">
                </label>
            </div>
        <?php endfor; ?>
        <button class="btn" type="submit"><?= e(t('save')) ?></button>
    </form>
</section>
