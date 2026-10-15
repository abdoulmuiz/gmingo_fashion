<section class="card">
    <h1><?= e(t('stages_settings')) ?></h1>
    <?php foreach ($stages as $stage): ?>
        <div class="item-row">
            <form method="post" action="<?= e(url('/settings/stages')) ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="rename">
                <input type="hidden" name="id" value="<?= e((string) $stage['id']) ?>">
                <label><?= e(t('rename')) ?>
                    <input name="name" value="<?= e((string) $stage['name']) ?>">
                </label>
                <button class="btn" type="submit"><?= e(t('save')) ?></button>
            </form>
            <form method="post" action="<?= e(url('/settings/stages')) ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="up">
                <input type="hidden" name="id" value="<?= e((string) $stage['id']) ?>">
                <button class="btn btn-quiet" type="submit"><?= e(t('move_up')) ?></button>
            </form>
            <form method="post" action="<?= e(url('/settings/stages')) ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="down">
                <input type="hidden" name="id" value="<?= e((string) $stage['id']) ?>">
                <button class="btn btn-quiet" type="submit"><?= e(t('move_down')) ?></button>
            </form>
            <form method="post" action="<?= e(url('/settings/stages')) ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="<?= (int) $stage['active'] === 1 ? 'deactivate' : 'activate' ?>">
                <input type="hidden" name="id" value="<?= e((string) $stage['id']) ?>">
                <button class="btn btn-quiet" type="submit"><?= e(t((int) $stage['active'] === 1 ? 'deactivate' : 'activate')) ?></button>
            </form>
        </div>
    <?php endforeach; ?>
    <form method="post" action="<?= e(url('/settings/stages')) ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="add">
        <label><?= e(t('add_stage')) ?>
            <input name="name" value="">
        </label>
        <button class="btn" type="submit"><?= e(t('add_stage')) ?></button>
    </form>
</section>
