<?php
/** @var array $errors */
/** @var bool $must_change */
?>
<section class="card">
    <h1><?= e(t('platform_password_change')) ?></h1>
    <?php if ($must_change): ?>
        <p class="flash flash-error" role="alert"><?= e(t('platform_password_must_change')) ?></p>
    <?php endif; ?>
    <form method="post" action="<?= e(url('/platform/password')) ?>" class="stack">
        <?= csrf_field() ?>
        <label><?= e(t('platform_password_current')) ?>
            <input type="password" name="current_password" required autocomplete="current-password">
        </label>
        <?php if (!empty($errors['current_password'])): ?><p class="error"><?= e(t((string) $errors['current_password'])) ?></p><?php endif; ?>
        <label><?= e(t('platform_password_new')) ?>
            <input type="password" name="new_password" required autocomplete="new-password" minlength="12">
        </label>
        <?php if (!empty($errors['new_password'])): ?><p class="error"><?= e(t((string) $errors['new_password'])) ?></p><?php endif; ?>
        <label><?= e(t('platform_password_confirm')) ?>
            <input type="password" name="confirm_password" required autocomplete="new-password" minlength="12">
        </label>
        <?php if (!empty($errors['confirm_password'])): ?><p class="error"><?= e(t((string) $errors['confirm_password'])) ?></p><?php endif; ?>
        <button class="btn" type="submit"><?= e(t('save')) ?></button>
    </form>
</section>
