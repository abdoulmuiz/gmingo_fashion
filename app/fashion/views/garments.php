<?php require __DIR__ . '/partials/settings_nav.php'; ?>
<div class="settings-layout">
<section class="card">
    <h1><?= e(t('garment_settings')) ?></h1>
    <div class="garment-cards">
        <?php foreach ($garments as $garment): ?>
            <?php if ((int) $garment['active'] !== 1) { continue; } ?>
            <div class="garment-card-wrap">
                <a class="garment-card" href="<?= e(url('/settings/garments/' . $garment['id'])) ?>">
                    <strong><?= e((string) $garment['name']) ?></strong>
                    <?php if ((int) $garment['placeholder'] === 1): ?>
                        <span class="meta"><?= e(t('placeholder_note')) ?></span>
                    <?php endif; ?>
                </a>
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
            </div>
        <?php endforeach; ?>
    </div>
    <?php foreach ($garments as $garment): ?>
        <?php if ((int) $garment['active'] === 1) { continue; } ?>
        <p class="meta"><?= e((string) $garment['name']) ?> · <?= e(t('stage_inactive')) ?></p>
        <form method="post" action="<?= e(url('/settings/garments')) ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="garment_activate">
            <input type="hidden" name="id" value="<?= e((string) $garment['id']) ?>">
            <button class="btn btn-quiet" type="submit"><?= e(t('activate')) ?></button>
        </form>
    <?php endforeach; ?>
    <form method="post" action="<?= e(url('/settings/garments')) ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="garment_add">
        <label><?= e(t('add_garment')) ?>
            <input name="name" required>
        </label>
        <button class="btn" type="submit"><?= e(t('add_garment')) ?></button>
    </form>
</section>
</div>
