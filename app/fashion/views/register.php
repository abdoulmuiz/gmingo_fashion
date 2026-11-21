<section class="card">
    <h1><?= e(t('register')) ?></h1>
    <?php if (!empty($errors['form'])): ?>
        <p class="error"><?= e(t((string) $errors['form'])) ?></p>
    <?php endif; ?>
    <form method="post" action="<?= e(url('/register')) ?>">
        <?= csrf_field() ?>
        <label><?= e(t('company_type')) ?>
            <select name="company_type" required>
                <option value=""><?= e(t('company_type')) ?></option>
                <?php foreach (($company_types ?? company_types()) as $type): ?>
                    <option value="<?= e($type) ?>" <?= ($old['company_type'] ?? '') === $type ? 'selected' : '' ?>><?= e(t('type_' . $type)) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <?php if (!empty($errors['company_type'])): ?><p class="error"><?= e(t((string) $errors['company_type'])) ?></p><?php endif; ?>
        <label><?= e(t('business_name')) ?>
            <input name="business_name" value="<?= e($old['business_name'] ?? '') ?>" required>
        </label>
        <?php if (!empty($errors['business_name'])): ?><p class="error"><?= e(t((string) $errors['business_name'])) ?></p><?php endif; ?>
        <label><?= e(t('owner_name')) ?>
            <input name="owner_name" value="<?= e($old['owner_name'] ?? '') ?>" required>
        </label>
        <?php if (!empty($errors['owner_name'])): ?><p class="error"><?= e(t((string) $errors['owner_name'])) ?></p><?php endif; ?>
        <label><?= e(t('email')) ?>
            <input name="email" type="email" value="<?= e($old['email'] ?? '') ?>" required>
        </label>
        <?php if (!empty($errors['email'])): ?><p class="error"><?= e(t((string) $errors['email'])) ?></p><?php endif; ?>
        <label><?= e(t('password')) ?>
            <input name="password" type="password" required>
        </label>
        <?php if (!empty($errors['password'])): ?><p class="error"><?= e(t((string) $errors['password'])) ?></p><?php endif; ?>
        <label><?= e(t('shop_phone')) ?>
            <input name="phone" value="<?= e($old['phone'] ?? '') ?>" required>
        </label>
        <?php if (!empty($errors['phone'])): ?><p class="error"><?= e(t((string) $errors['phone'])) ?></p><?php endif; ?>
        <button class="btn" type="submit"><?= e(t('register')) ?></button>
    </form>
    <p><a href="<?= e(url('/login')) ?>"><?= e(t('have_account')) ?></a></p>
</section>
