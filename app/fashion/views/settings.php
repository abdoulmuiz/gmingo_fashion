<section class="card">
    <h1><?= e(t('modules')) ?></h1>
    <p class="meta"><?= e(t('company_type')) ?>: <?= e(t('type_' . $company_type)) ?></p>
    <p><?= e(t('modules_help')) ?></p>
    <form method="post" action="<?= e(url('/settings')) ?>">
        <?= csrf_field() ?>
        <?php foreach ($modules as $module): ?>
            <label class="check">
                <input type="checkbox" name="modules[]" value="<?= e((string) $module['module_key']) ?>" <?= (int) $module['enabled'] === 1 ? 'checked' : '' ?>>
                <?= e(module_label((string) $module['module_key'])) ?>
            </label>
        <?php endforeach; ?>
        <button class="btn" type="submit"><?= e(t('save')) ?></button>
    </form>
    <?php if (module_enabled('fashion.production')): ?>
        <p><a href="<?= e(url('/settings/stages')) ?>"><?= e(t('stages_settings')) ?></a></p>
    <?php endif; ?>
</section>
