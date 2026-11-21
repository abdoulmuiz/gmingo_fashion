<section class="auth-panel card">
    <p class="eyebrow"><?= e(t('app_kicker')) ?></p>
    <h1><?= e(t('login')) ?></h1>
    <p class="auth-lead"><?= e(t('home_title')) ?></p>
    <?php if (!empty($errors['form'])): ?>
        <p class="error"><?= e(t((string) $errors['form'])) ?></p>
    <?php endif; ?>
    <form class="auth-form stack" method="post" action="<?= e(url('/login')) ?>">
        <?= csrf_field() ?>
        <label><?= e(t('email')) ?>
            <input name="email" type="email" value="<?= e($email) ?>" autocomplete="username" required>
        </label>
        <label><?= e(t('password')) ?>
            <input name="password" type="password" autocomplete="current-password" required>
        </label>
        <button class="btn" type="submit"><?= e(t('login')) ?></button>
    </form>
    <p class="auth-link"><a href="<?= e(url('/register')) ?>"><?= e(t('need_account')) ?></a></p>
    <form class="auth-lang" method="post" action="<?= e(url('/language')) ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="back" value="<?= e(request_path()) ?>">
        <label><?= e(t('language')) ?>
            <select name="lang">
                <option value="en" <?= current_lang() === 'en' ? 'selected' : '' ?>><?= e(t('english')) ?></option>
                <option value="ha" <?= current_lang() === 'ha' ? 'selected' : '' ?>><?= e(t('hausa')) ?></option>
            </select>
        </label>
        <button class="btn btn-quiet btn-small" type="submit"><?= e(t('save')) ?></button>
    </form>
</section>
