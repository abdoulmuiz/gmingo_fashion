<?php
$title = t('new_customer');
$back_href = '/customers';
$back_label = t('customers');
$subtitle = null;
$actions_html = '';
require __DIR__ . '/../partials/ui_page_header.php';
?>
<section class="card form-sheet">
    <form method="post" action="<?= e(url('/customers')) ?>">
        <?= csrf_field() ?>
        <div class="form-sections">
            <div class="form-grid">
                <div>
                    <label class="ui-label" for="field-name"><?= e(t('name')) ?> *</label>
                    <input id="field-name" name="name" value="<?= e($old['name'] ?? '') ?>" required>
                    <?php if (!empty($errors['name'])): ?><p class="ui-error"><?= e(t((string) $errors['name'])) ?></p><?php endif; ?>
                </div>
                <div>
                    <label class="ui-label" for="field-phone"><?= e(t('phone')) ?> *</label>
                    <input id="field-phone" name="phone" value="<?= e($old['phone'] ?? '') ?>" required>
                    <?php if (!empty($errors['phone'])): ?><p class="ui-error"><?= e(t((string) $errors['phone'])) ?></p><?php endif; ?>
                </div>
                <div>
                    <label class="ui-label" for="field-gender"><?= e(t('gender')) ?> *</label>
                    <select id="field-gender" name="gender" required>
                        <option value=""><?= e(t('gender')) ?></option>
                        <option value="female" <?= ($old['gender'] ?? '') === 'female' ? 'selected' : '' ?>><?= e(t('female')) ?></option>
                        <option value="male" <?= ($old['gender'] ?? '') === 'male' ? 'selected' : '' ?>><?= e(t('male')) ?></option>
                    </select>
                    <?php if (!empty($errors['gender'])): ?><p class="ui-error"><?= e(t((string) $errors['gender'])) ?></p><?php endif; ?>
                </div>
                <div>
                    <label class="ui-label" for="field-lang"><?= e(t('language')) ?></label>
                    <select id="field-lang" name="lang">
                        <option value="" <?= ($old['lang'] ?? '') === '' ? 'selected' : '' ?>><?= e(t('shop_default_language')) ?></option>
                        <option value="en" <?= ($old['lang'] ?? '') === 'en' ? 'selected' : '' ?>><?= e(t('english')) ?></option>
                        <option value="ha" <?= ($old['lang'] ?? '') === 'ha' ? 'selected' : '' ?>><?= e(t('hausa')) ?></option>
                    </select>
                </div>
                <div>
                    <label class="ui-label" for="field-customer_type"><?= e(t('type')) ?></label>
                    <select id="field-customer_type" name="customer_type">
                        <option value="regular" <?= ($old['customer_type'] ?? '') === 'regular' ? 'selected' : '' ?>><?= e(t('regular')) ?></option>
                        <option value="vip" <?= ($old['customer_type'] ?? '') === 'vip' ? 'selected' : '' ?>><?= e(t('vip')) ?></option>
                    </select>
                </div>
            </div>
        </div>
        <div class="form-sheet-footer">
            <a class="btn btn-quiet" href="<?= e(url('/customers')) ?>"><?= e(t('cancel')) ?></a>
            <button class="btn" type="submit"><?= e(t('save')) ?></button>
        </div>
    </form>
</section>
