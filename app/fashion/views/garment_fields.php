<?php
$used = (int) $garment['measurement_count'] > 0;
$removeConfirm = t($used ? 'garment_archive_confirm' : 'garment_delete_confirm');
?>
<section class="card field-editor" data-field-editor>
    <p><a href="<?= e(url('/settings/garments')) ?>"><?= e(t('garment_settings')) ?></a></p>
    <h1><?= e((string) $garment['name']) ?></h1>
    <p class="meta"><?= e(t('drag_hint')) ?></p>
    <?php if ((int) $garment['placeholder'] === 1): ?>
        <p class="meta"><?= e(t('placeholder_note')) ?></p>
    <?php endif; ?>
    <form method="post" action="<?= e(url('/settings/garments')) ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="garment_rename">
        <input type="hidden" name="id" value="<?= e((string) $garment['id']) ?>">
        <input type="hidden" name="garment_id" value="<?= e((string) $garment['id']) ?>">
        <label><?= e(t('garment')) ?>
            <input name="name" value="<?= e((string) $garment['name']) ?>" required>
        </label>
        <button class="btn" type="submit"><?= e(t('save')) ?></button>
    </form>
    <form method="post" action="<?= e(url('/settings/garments')) ?>" data-confirm="<?= e($removeConfirm) ?>" onsubmit="return confirm(this.dataset.confirm);">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="garment_remove">
        <input type="hidden" name="id" value="<?= e((string) $garment['id']) ?>">
        <button class="btn btn-quiet" type="submit"><?= e(t($used ? 'archive_garment' : 'delete_garment')) ?></button>
    </form>
    <div class="field-bin" data-field-bin hidden>
        <span aria-hidden="true">&#128465;</span>
        <?= e(t('remove')) ?>
    </div>
    <div class="field-list" data-field-list>
        <?php foreach ($fields as $field): ?>
            <div class="field-row" data-field-id="<?= e((string) $field['id']) ?>">
                <button class="drag-handle" type="button" data-drag-handle aria-label="<?= e(t('reorder')) ?>">&#8942;</button>
                <form method="post" action="<?= e(url('/settings/garments')) ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="field_save">
                    <input type="hidden" name="id" value="<?= e((string) $field['id']) ?>">
                    <input type="hidden" name="garment_id" value="<?= e((string) $garment['id']) ?>">
                    <label><?= e(t('rename')) ?>
                        <input name="name" value="<?= e((string) $field['name']) ?>" required>
                    </label>
                    <label><?= e(t('unit')) ?>
                        <input name="unit" value="<?= e((string) ($field['unit'] ?? '')) ?>">
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
                <form method="post" action="<?= e(url('/settings/garments')) ?>" data-remove-form>
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="field_remove">
                    <input type="hidden" name="garment_id" value="<?= e((string) $garment['id']) ?>">
                    <input type="hidden" name="id" value="<?= e((string) $field['id']) ?>">
                    <button class="btn btn-quiet icon-btn" type="submit" data-field-remove><?= e(t('remove')) ?></button>
                </form>
                <?php if ((int) $field['active'] !== 1): ?>
                    <form method="post" action="<?= e(url('/settings/garments')) ?>">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="field_activate">
                        <input type="hidden" name="garment_id" value="<?= e((string) $garment['id']) ?>">
                        <input type="hidden" name="id" value="<?= e((string) $field['id']) ?>">
                        <button class="btn btn-quiet" type="submit"><?= e(t('activate')) ?></button>
                    </form>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
    <p class="undo-bar" data-undo hidden><?= e(t('field_removed')) ?> <button type="button" data-undo-button><?= e(t('undo')) ?></button></p>
    <form method="post" action="<?= e(url('/settings/garments')) ?>" data-field-order hidden>
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="field_order">
        <input type="hidden" name="garment_id" value="<?= e((string) $garment['id']) ?>">
    </form>
    <form method="post" action="<?= e(url('/settings/garments')) ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="field_add">
        <input type="hidden" name="garment_id" value="<?= e((string) $garment['id']) ?>">
        <label><?= e(t('add_field')) ?>
            <input name="name" required>
        </label>
        <label><?= e(t('unit')) ?>
            <input name="unit">
        </label>
        <button class="btn" type="submit"><?= e(t('add_field')) ?></button>
    </form>
    <script src="<?= e(url('/assets/fields.js')) ?>"></script>
</section>
