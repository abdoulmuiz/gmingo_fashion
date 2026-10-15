<section class="card">
    <h1><?= e(t('login')) ?></h1>
    <?php if (!empty($errors['form'])): ?>
        <p class="error"><?= e(t((string) $errors['form'])) ?></p>
    <?php endif; ?>
    <form method="post" action="<?= e(url('/login')) ?>">
        <?= csrf_field() ?>
        <label><?= e(t('email')) ?>
            <input name="email" type="email" value="<?= e($email) ?>" required>
        </label>
        <label><?= e(t('password')) ?>
            <input name="password" type="password" required>
        </label>
        <button class="btn" type="submit"><?= e(t('login')) ?></button>
    </form>
    <p><a href="<?= e(url('/register')) ?>"><?= e(t('need_account')) ?></a></p>
    <form method="post" action="<?= e(url('/lang')) ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="back" value="/login">
        <label><?= e(t('language')) ?>
            <select name="lang">
                <option value="en" <?= current_lang() === 'en' ? 'selected' : '' ?>><?= e(t('english')) ?></option>
                <option value="ha" <?= current_lang() === 'ha' ? 'selected' : '' ?>><?= e(t('hausa')) ?></option>
            </select>
        </label>
        <button class="btn btn-quiet" type="submit"><?= e(t('save')) ?></button>
    </form>
</section>
