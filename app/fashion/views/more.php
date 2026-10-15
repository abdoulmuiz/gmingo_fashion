<section class="card">
    <h1><?= e(t('more')) ?></h1>
    <form method="post" action="<?= e(url('/lang')) ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="back" value="/more">
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
