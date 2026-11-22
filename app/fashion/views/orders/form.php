<?php
$title = t('new_order');
$back_href = '/orders';
$back_label = t('orders');
$subtitle = null;
$actions_html = '';
require __DIR__ . '/../partials/ui_page_header.php';
?>
<section class="card form-sheet">
    <form method="post" action="<?= e(url('/orders')) ?>">
        <?= csrf_field() ?>
        <div class="form-sections">
            <div class="form-grid">
                <div class="span-2">
                    <label class="ui-label" for="field-customer_id"><?= e(t('customer')) ?> *</label>
                    <select id="field-customer_id" name="customer_id" required>
                        <option value=""><?= e(t('customer')) ?></option>
                        <?php foreach ($customers as $customer): ?>
                            <option value="<?= e($customer['id']) ?>" <?= ($old['customer_id'] ?? '') === $customer['id'] ? 'selected' : '' ?>><?= e($customer['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (!empty($errors['customer_id'])): ?><p class="ui-error"><?= e(t((string) $errors['customer_id'])) ?></p><?php endif; ?>
                </div>
                <div>
                    <label class="ui-label" for="field-due_date"><?= e(t('due_date')) ?> *</label>
                    <input id="field-due_date" type="date" name="due_date" value="<?= e($old['due_date'] ?? '') ?>" required>
                    <?php if (!empty($errors['due_date'])): ?><p class="ui-error"><?= e(t((string) $errors['due_date'])) ?></p><?php endif; ?>
                </div>
                <div>
                    <label class="ui-check" style="margin-top:1.6rem">
                        <input type="checkbox" name="urgent" value="1" <?= !empty($old['urgent']) ? 'checked' : '' ?>>
                        <span><?= e(t('urgent')) ?></span>
                    </label>
                </div>
            </div>

            <div>
                <h2><?= e(t('add_items')) ?></h2>
                <p class="meta"><?= e(t('item_hint')) ?></p>
                <?php if (!empty($errors['items'])): ?><p class="ui-error"><?= e(t((string) $errors['items'])) ?></p><?php endif; ?>
                <?php for ($row = 0; $row < 4; $row++): ?>
                    <?php $item = $old['items'][$row] ?? []; ?>
                    <div class="item-row form-grid" style="margin-top:0.85rem">
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
                            <div class="ui-money">
                                <span class="ui-money-prefix" aria-hidden="true">₦</span>
                                <input name="items[<?= e((string) $row) ?>][price]" value="<?= e((string) ($item['price'] ?? '')) ?>" inputmode="decimal">
                            </div>
                        </label>
                        <label><?= e(t('fabric')) ?>
                            <input name="items[<?= e((string) $row) ?>][fabric]" value="<?= e((string) ($item['fabric'] ?? '')) ?>">
                        </label>
                        <label class="span-2"><?= e(t('notes')) ?>
                            <input name="items[<?= e((string) $row) ?>][notes]" value="<?= e((string) ($item['notes'] ?? '')) ?>">
                        </label>
                    </div>
                <?php endfor; ?>
            </div>
        </div>
        <div class="form-sheet-footer">
            <a class="btn btn-quiet" href="<?= e(url('/orders')) ?>"><?= e(t('cancel')) ?></a>
            <button class="btn" type="submit"><?= e(t('save')) ?></button>
        </div>
    </form>
</section>
