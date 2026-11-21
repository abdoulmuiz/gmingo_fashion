<?php
/** @var array $settings */
/** @var array $errors */
/** @var array $old */
/** @var string $public_url */
?>
<section class="card">
    <h1><?= e(t('storefront_settings')) ?></h1>
    <p class="meta"><?= e(t('storefront_help')) ?></p>
    <form method="post" action="<?= e(url('/settings/storefront')) ?>" class="stack">
        <?= csrf_field() ?>
        <label><?= e(t('storefront_slug')) ?>
            <input name="slug" value="<?= e((string) ($old['slug'] ?? '')) ?>">
        </label>
        <?php if (isset($errors['slug'])): ?><p class="error"><?= e(t($errors['slug'])) ?></p><?php endif; ?>
        <?php if (isset($errors['form'])): ?><p class="error"><?= e(t($errors['form'])) ?></p><?php endif; ?>
        <label class="check">
            <input type="checkbox" name="enabled" value="1" <?= !empty($old['enabled']) ? 'checked' : '' ?>>
            <?= e(t('storefront_enabled')) ?>
        </label>
        <button class="btn" type="submit"><?= e(t('save')) ?></button>
    </form>
    <?php if ($public_url !== ''): ?>
        <p class="meta"><?= e(t('storefront_link')) ?>: <a href="<?= e($public_url) ?>"><?= e($public_url) ?></a></p>
    <?php endif; ?>
</section>
