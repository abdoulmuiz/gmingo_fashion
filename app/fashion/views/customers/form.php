<section class="card">
    <h1><?= e(t('new_customer')) ?></h1>
    <form method="post" action="<?= e(url('/customers')) ?>">
        <?= csrf_field() ?>
        <label><?= e(t('name')) ?>
            <input name="name" value="<?= e($old['name'] ?? '') ?>" required>
        </label>
        <?php if (!empty($errors['name'])): ?><p class="error"><?= e(t((string) $errors['name'])) ?></p><?php endif; ?>
        <label><?= e(t('phone')) ?>
            <input name="phone" value="<?= e($old['phone'] ?? '') ?>" required>
        </label>
        <?php if (!empty($errors['phone'])): ?><p class="error"><?= e(t((string) $errors['phone'])) ?></p><?php endif; ?>
        <label><?= e(t('gender')) ?>
            <select name="gender" required>
                <option value=""><?= e(t('gender')) ?></option>
                <option value="female" <?= ($old['gender'] ?? '') === 'female' ? 'selected' : '' ?>><?= e(t('female')) ?></option>
                <option value="male" <?= ($old['gender'] ?? '') === 'male' ? 'selected' : '' ?>><?= e(t('male')) ?></option>
            </select>
        </label>
        <?php if (!empty($errors['gender'])): ?><p class="error"><?= e(t((string) $errors['gender'])) ?></p><?php endif; ?>
        <label><?= e(t('language')) ?>
            <select name="lang">
                <option value="" <?= ($old['lang'] ?? '') === '' ? 'selected' : '' ?>><?= e(t('shop_default_language')) ?></option>
                <option value="en" <?= ($old['lang'] ?? '') === 'en' ? 'selected' : '' ?>><?= e(t('english')) ?></option>
                <option value="ha" <?= ($old['lang'] ?? '') === 'ha' ? 'selected' : '' ?>><?= e(t('hausa')) ?></option>
            </select>
        </label>
        <label><?= e(t('type')) ?>
            <select name="customer_type">
                <option value="regular" <?= ($old['customer_type'] ?? '') === 'regular' ? 'selected' : '' ?>><?= e(t('regular')) ?></option>
                <option value="vip" <?= ($old['customer_type'] ?? '') === 'vip' ? 'selected' : '' ?>><?= e(t('vip')) ?></option>
            </select>
        </label>
        <button class="btn" type="submit"><?= e(t('save')) ?></button>
    </form>
</section>
