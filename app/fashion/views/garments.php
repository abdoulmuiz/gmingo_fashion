<section class="card">
    <h1><?= e(t('garment_settings')) ?></h1>
    <?php foreach ($garments as $garment): ?>
        <div class="item-row">
            <?php if ((int) $garment['placeholder'] === 1): ?>
                <p class="meta"><?= e(t('placeholder_note')) ?></p>
            <?php endif; ?>
            <form method="post" action="<?= e(url('/settings/garments')) ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="garment_rename">
                <input type="hidden" name="id" value="<?= e((string) $garment['id']) ?>">
                <label><?= e(t('garment')) ?>
                    <input name="name" value="<?= e((string) $garment['name']) ?>">
                </label>
                <button class="btn" type="submit"><?= e(t('save')) ?></button>
            </form>
            <form method="post" action="<?= e(url('/settings/garments')) ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="garment_up">
                <input type="hidden" name="id" value="<?= e((string) $garment['id']) ?>">
                <button class="btn btn-quiet" type="submit"><?= e(t('move_up')) ?></button>
            </form>
            <form method="post" action="<?= e(url('/settings/garments')) ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="garment_down">
                <input type="hidden" name="id" value="<?= e((string) $garment['id']) ?>">
                <button class="btn btn-quiet" type="submit"><?= e(t('move_down')) ?></button>
            </form>
            <form method="post" action="<?= e(url('/settings/garments')) ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="<?= (int) $garment['active'] === 1 ? 'garment_deactivate' : 'garment_activate' ?>">
                <input type="hidden" name="id" value="<?= e((string) $garment['id']) ?>">
                <button class="btn btn-quiet" type="submit"><?= e(t((int) $garment['active'] === 1 ? 'deactivate' : 'activate')) ?></button>
            </form>
            <?php foreach ($garment['fields'] as $field): ?>
                <form method="post" action="<?= e(url('/settings/garments')) ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="field_rename">
                    <input type="hidden" name="id" value="<?= e((string) $field['id']) ?>">
                    <label><?= e(t('add_field')) ?>
                        <input name="name" value="<?= e((string) $field['name']) ?>">
                    </label>
                    <button class="btn" type="submit"><?= e(t('save')) ?></button>
                </form>
                <form method="post" action="<?= e(url('/settings/garments')) ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="field_up">
                    <input type="hidden" name="garment_id" value="<?= e((string) $garment['id']) ?>">
                    <input type="hidden" name="id" value="<?= e((string) $field['id']) ?>">
                    <button class="btn btn-quiet" type="submit"><?= e(t('move_up')) ?></button>
                </form>
                <form method="post" action="<?= e(url('/settings/garments')) ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="field_down">
                    <input type="hidden" name="garment_id" value="<?= e((string) $garment['id']) ?>">
                    <input type="hidden" name="id" value="<?= e((string) $field['id']) ?>">
                    <button class="btn btn-quiet" type="submit"><?= e(t('move_down')) ?></button>
                </form>
                <form method="post" action="<?= e(url('/settings/garments')) ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="<?= (int) $field['active'] === 1 ? 'field_deactivate' : 'field_activate' ?>">
                    <input type="hidden" name="id" value="<?= e((string) $field['id']) ?>">
                    <button class="btn btn-quiet" type="submit"><?= e(t((int) $field['active'] === 1 ? 'deactivate' : 'activate')) ?></button>
                </form>
            <?php endforeach; ?>
            <form method="post" action="<?= e(url('/settings/garments')) ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="field_add">
                <input type="hidden" name="garment_id" value="<?= e((string) $garment['id']) ?>">
                <label><?= e(t('add_field')) ?>
                    <input name="name" value="">
                </label>
                <button class="btn" type="submit"><?= e(t('add_field')) ?></button>
            </form>
        </div>
    <?php endforeach; ?>
    <form method="post" action="<?= e(url('/settings/garments')) ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="garment_add">
        <label><?= e(t('add_garment')) ?>
            <input name="name" value="">
        </label>
        <button class="btn" type="submit"><?= e(t('add_garment')) ?></button>
    </form>
</section>
