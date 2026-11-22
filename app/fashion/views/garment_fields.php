<?php
$used = (int) $garment['measurement_count'] > 0;
$removeConfirm = t($used ? 'garment_archive_confirm' : 'garment_delete_confirm');
require __DIR__ . '/partials/settings_nav.php';
?>
<div class="settings-layout">
<section class="card field-editor settings-panel" data-field-editor data-saved="<?= e(t('saved')) ?>">
    <p class="meta"><a href="<?= e(url('/settings/garments')) ?>"><?= e(t('garment_settings')) ?></a></p>
    <div class="editor-top">
        <h1><?= e((string) $garment['name']) ?></h1>
        <form method="post" action="<?= e(url('/settings/garments')) ?>" data-confirm="<?= e($removeConfirm) ?>" onsubmit="return confirm(this.dataset.confirm);">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="garment_remove">
            <input type="hidden" name="id" value="<?= e((string) $garment['id']) ?>">
            <button class="btn btn-quiet btn-small" type="submit"><?= e(t($used ? 'archive_garment' : 'delete_garment')) ?></button>
        </form>
    </div>
    <form class="name-bar" method="post" action="<?= e(url('/settings/garments')) ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="garment_rename">
        <input type="hidden" name="id" value="<?= e((string) $garment['id']) ?>">
        <input type="hidden" name="garment_id" value="<?= e((string) $garment['id']) ?>">
        <input name="name" value="<?= e((string) $garment['name']) ?>" aria-label="<?= e(t('garment')) ?>" required>
        <button class="btn btn-small" type="submit"><?= e(t('save')) ?></button>
    </form>
    <?php if ((int) $garment['placeholder'] === 1): ?>
        <p class="meta"><?= e(t('placeholder_note')) ?></p>
    <?php endif; ?>
    <p class="meta editor-hint"><?= e(t('drag_hint')) ?></p>
    <div class="field-bin" data-field-bin hidden>
        <span aria-hidden="true">&#128465;</span>
        <?= e(t('remove')) ?>
    </div>
    <div class="field-list" data-field-list>
        <?php foreach ($fields as $field): ?>
            <?php $off = (int) $field['active'] !== 1; ?>
            <div class="field-row<?= $off ? ' is-off' : '' ?>" data-field-id="<?= e((string) $field['id']) ?>">
                <button class="drag-handle" type="button" data-drag-handle aria-label="<?= e(t('reorder')) ?>">&#8942;</button>
                <form class="field-edit" method="post" action="<?= e(url('/settings/garments')) ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="field_save">
                    <input type="hidden" name="id" value="<?= e((string) $field['id']) ?>">
                    <input type="hidden" name="garment_id" value="<?= e((string) $garment['id']) ?>">
                    <input name="name" value="<?= e((string) $field['name']) ?>" aria-label="<?= e(t('rename')) ?>" required>
                    <input class="unit-input" name="unit" value="<?= e((string) ($field['unit'] ?? '')) ?>" aria-label="<?= e(t('unit')) ?>" placeholder="<?= e(t('unit')) ?>">
                    <button class="btn btn-small" type="submit"><?= e(t('save')) ?></button>
                </form>
                <div class="field-moves">
                    <form method="post" action="<?= e(url('/settings/garments')) ?>">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="field_up">
                        <input type="hidden" name="garment_id" value="<?= e((string) $garment['id']) ?>">
                        <input type="hidden" name="id" value="<?= e((string) $field['id']) ?>">
                        <button class="btn btn-quiet btn-small" type="submit"><?= e(t('move_up')) ?></button>
                    </form>
                    <form method="post" action="<?= e(url('/settings/garments')) ?>">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="field_down">
                        <input type="hidden" name="garment_id" value="<?= e((string) $garment['id']) ?>">
                        <input type="hidden" name="id" value="<?= e((string) $field['id']) ?>">
                        <button class="btn btn-quiet btn-small" type="submit"><?= e(t('move_down')) ?></button>
                    </form>
                    <form method="post" action="<?= e(url('/settings/garments')) ?>" data-remove-form>
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="field_remove">
                        <input type="hidden" name="garment_id" value="<?= e((string) $garment['id']) ?>">
                        <input type="hidden" name="id" value="<?= e((string) $field['id']) ?>">
                        <button class="btn btn-quiet btn-small icon-btn" type="submit" data-field-remove aria-label="<?= e(t('remove')) ?>">&#215;</button>
                    </form>
                    <form method="post" action="<?= e(url('/settings/garments')) ?>" data-activate-form<?= $off ? '' : ' hidden' ?>>
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="field_activate">
                        <input type="hidden" name="garment_id" value="<?= e((string) $garment['id']) ?>">
                        <input type="hidden" name="id" value="<?= e((string) $field['id']) ?>">
                        <button class="btn btn-quiet btn-small" type="submit"><?= e(t('activate')) ?></button>
                    </form>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    <p class="undo-bar" data-undo hidden><?= e(t('field_removed')) ?> <button type="button" data-undo-button><?= e(t('undo')) ?></button></p>
    <form method="post" action="<?= e(url('/settings/garments')) ?>" data-field-order hidden>
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="field_order">
        <input type="hidden" name="garment_id" value="<?= e((string) $garment['id']) ?>">
    </form>
    <form class="field-add" method="post" action="<?= e(url('/settings/garments')) ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="field_add">
        <input type="hidden" name="garment_id" value="<?= e((string) $garment['id']) ?>">
        <input name="name" aria-label="<?= e(t('add_field')) ?>" placeholder="<?= e(t('add_field')) ?>" required>
        <input class="unit-input" name="unit" aria-label="<?= e(t('unit')) ?>" placeholder="<?= e(t('unit')) ?>">
        <button class="btn btn-small" type="submit"><?= e(t('add_field')) ?></button>
    </form>
    <template data-field-template>
        <div class="field-row" data-field-id="">
            <button class="drag-handle" type="button" data-drag-handle aria-label="<?= e(t('reorder')) ?>">&#8942;</button>
            <form class="field-edit" method="post" action="<?= e(url('/settings/garments')) ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="field_save">
                <input type="hidden" name="id" value="">
                <input type="hidden" name="garment_id" value="<?= e((string) $garment['id']) ?>">
                <input name="name" value="" aria-label="<?= e(t('rename')) ?>" required>
                <input class="unit-input" name="unit" value="" aria-label="<?= e(t('unit')) ?>" placeholder="<?= e(t('unit')) ?>">
                <button class="btn btn-small" type="submit"><?= e(t('save')) ?></button>
            </form>
            <div class="field-moves">
                <form method="post" action="<?= e(url('/settings/garments')) ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="field_up">
                    <input type="hidden" name="garment_id" value="<?= e((string) $garment['id']) ?>">
                    <input type="hidden" name="id" value="">
                    <button class="btn btn-quiet btn-small" type="submit"><?= e(t('move_up')) ?></button>
                </form>
                <form method="post" action="<?= e(url('/settings/garments')) ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="field_down">
                    <input type="hidden" name="garment_id" value="<?= e((string) $garment['id']) ?>">
                    <input type="hidden" name="id" value="">
                    <button class="btn btn-quiet btn-small" type="submit"><?= e(t('move_down')) ?></button>
                </form>
                <form method="post" action="<?= e(url('/settings/garments')) ?>" data-remove-form>
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="field_remove">
                    <input type="hidden" name="garment_id" value="<?= e((string) $garment['id']) ?>">
                    <input type="hidden" name="id" value="">
                    <button class="btn btn-quiet btn-small icon-btn" type="submit" data-field-remove aria-label="<?= e(t('remove')) ?>">&#215;</button>
                </form>
                <form method="post" action="<?= e(url('/settings/garments')) ?>" data-activate-form hidden>
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="field_activate">
                    <input type="hidden" name="garment_id" value="<?= e((string) $garment['id']) ?>">
                    <input type="hidden" name="id" value="">
                    <button class="btn btn-quiet btn-small" type="submit"><?= e(t('activate')) ?></button>
                </form>
            </div>
        </div>
    </template>
    <script src="<?= e(url('/assets/fields.js')) ?>"></script>
</section>
</div>
