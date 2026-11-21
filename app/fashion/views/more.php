<section class="card">
    <h1><?= e(t('more')) ?></h1>
    <?php if (current_user_id() !== null && !subscription_has_app_access()): ?>
        <p class="flash flash-error" role="alert"><?= e(t('subscription_access_blocked')) ?></p>
        <p><a class="btn" href="<?= e(url('/account/subscription')) ?>"><?= e(t('subscription_account')) ?></a></p>
    <?php endif; ?>
    <?php foreach (menu_links('more') as [$path, $label]): ?>
        <p><a href="<?= e(url($path)) ?>"><?= e(t($label)) ?></a></p>
    <?php endforeach; ?>
    <form method="post" action="<?= e(url('/language')) ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="back" value="<?= e(request_path()) ?>">
        <label><?= e(t('language')) ?>
            <select name="lang">
                <option value="en" <?= current_lang() === 'en' ? 'selected' : '' ?>><?= e(t('english')) ?></option>
                <option value="ha" <?= current_lang() === 'ha' ? 'selected' : '' ?>><?= e(t('hausa')) ?></option>
            </select>
        </label>
        <button class="btn" type="submit"><?= e(t('save')) ?></button>
    </form>
    <form method="post" action="<?= e(url('/logout')) ?>">
        <?= csrf_field() ?>
        <button class="btn btn-quiet" type="submit"><?= e(t('logout')) ?></button>
    </form>
</section>
