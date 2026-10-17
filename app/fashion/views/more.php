<section class="card">
    <h1><?= e(t('more')) ?></h1>
    <p><a href="<?= e(url('/settings')) ?>"><?= e(t('settings')) ?></a></p>
    <?php if (module_enabled('fashion.orders')): ?>
        <p><a href="<?= e(url('/calendar')) ?>"><?= e(t('calendar')) ?></a></p>
    <?php endif; ?>
    <?php if (module_enabled('fashion.communication')): ?>
        <p><a href="<?= e(url('/messages')) ?>"><?= e(t('messages')) ?></a></p>
    <?php endif; ?>
    <p><a href="<?= e(url('/groups')) ?>"><?= e(t('groups')) ?></a></p>
    <?php if (module_enabled('fashion.inventory')): ?>
        <p><a href="<?= e(url('/stock')) ?>"><?= e(t('stock')) ?></a></p>
    <?php endif; ?>
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
