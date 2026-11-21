<?php
/** @var array $branches */
/** @var array $errors */
/** @var array $old */
?>
<section class="card">
    <h1><?= e(t('branches')) ?></h1>
    <p class="meta"><?= e(t('branches_help')) ?></p>
    <form method="post" action="<?= e(url('/branches')) ?>" class="stack">
        <?= csrf_field() ?>
        <label><?= e(t('name')) ?>
            <input name="name" value="<?= e((string) ($old['name'] ?? '')) ?>" required>
        </label>
        <?php if (isset($errors['form'])): ?><p class="error"><?= e(t($errors['form'])) ?></p><?php endif; ?>
        <label><?= e(t('phone')) ?>
            <input name="phone" value="<?= e((string) ($old['phone'] ?? '')) ?>">
        </label>
        <label><?= e(t('address')) ?>
            <input name="address" value="<?= e((string) ($old['address'] ?? '')) ?>">
        </label>
        <button class="btn" type="submit"><?= e(t('branch_add')) ?></button>
    </form>
</section>
<section class="card">
    <?php if ($branches === []): ?>
        <p><?= e(t('branches_empty')) ?></p>
    <?php else: ?>
        <?php foreach ($branches as $branch): ?>
            <div class="line">
                <strong><?= e($branch['name']) ?></strong>
                <span><?= e($branch['phone']) ?></span>
                <span><?= e($branch['active'] ? t('branch_active') : t('branch_inactive')) ?></span>
                <?php if ($branch['active']): ?>
                    <form method="post" action="<?= e(url('/branches/' . $branch['id'] . '/deactivate')) ?>">
                        <?= csrf_field() ?>
                        <button class="btn btn-small btn-quiet" type="submit"><?= e(t('branch_deactivate')) ?></button>
                    </form>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</section>
