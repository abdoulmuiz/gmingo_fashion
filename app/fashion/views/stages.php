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
    <h2><?= e(t('stage_roles')) ?></h2>
    <p class="meta"><?= e(t('stage_roles_help')) ?></p>
    <form method="post" action="<?= e(url('/settings/stages')) ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="roles">
        <?php foreach ($stages as $stage): ?>
            <label><?= e((string) $stage['name']) ?><?php if ((int) $stage['active'] !== 1): ?> (<?= e(t('stage_inactive')) ?>)<?php endif; ?>
                <select name="roles[<?= e((string) $stage['id']) ?>]" aria-label="<?= e(t('stage_role')) ?>">
                    <?php foreach (['open', 'ready', 'finished'] as $role): ?>
                        <option value="<?= e($role) ?>" <?= (string) $stage['role'] === $role ? 'selected' : '' ?>><?= e(t('role_' . $role)) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
        <?php endforeach; ?>
        <button class="btn" type="submit"><?= e(t('save')) ?></button>
    </form>
</section>
