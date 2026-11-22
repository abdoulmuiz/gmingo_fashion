<?php
/** @var array $gateway */
/** @var array $errors */
?>
<section class="card">
    <h1><?= e(t('platform_gateway')) ?></h1>
    <p class="meta"><?= e(t('platform_gateway_help')) ?></p>
    <?php if (!empty($errors['form'])): ?>
        <p class="error"><?= e(t((string) $errors['form'])) ?></p>
    <?php endif; ?>

    <p>
        <strong><?= e(t('platform_gateway_status')) ?>:</strong>
        <?= e($gateway['checkout_ready'] ? t('platform_gateway_ready') : t('platform_gateway_not_ready')) ?>
    </p>
    <?php if ($gateway['using_config_fallback']): ?>
        <p class="meta"><?= e(t('platform_gateway_config_fallback')) ?></p>
    <?php endif; ?>
    <?php if ($gateway['webhook_url'] !== ''): ?>
        <p><strong><?= e(t('platform_gateway_webhook_url')) ?>:</strong> <code><?= e((string) $gateway['webhook_url']) ?></code></p>
    <?php else: ?>
        <p class="meta"><?= e(t('platform_gateway_set_public_url')) ?></p>
    <?php endif; ?>
    <?php if ($gateway['callback_url'] !== ''): ?>
        <p><strong><?= e(t('platform_gateway_callback_url')) ?>:</strong> <code><?= e((string) $gateway['callback_url']) ?></code></p>
    <?php endif; ?>

    <form method="post" action="<?= e(url('/platform/gateway')) ?>" autocomplete="off">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="save">
        <label class="check">
            <input type="checkbox" name="enabled" value="1" <?= !empty($gateway['enabled']) ? 'checked' : '' ?>>
            <?= e(t('platform_gateway_enable')) ?>
        </label>
        <label><?= e(t('platform_gateway_environment')) ?>
            <select name="environment">
                <option value="test" <?= ($gateway['environment'] ?? '') === 'test' ? 'selected' : '' ?>><?= e(t('platform_gateway_env_test')) ?></option>
                <option value="live" <?= ($gateway['environment'] ?? '') === 'live' ? 'selected' : '' ?>><?= e(t('platform_gateway_env_live')) ?></option>
            </select>
            <?php if (!empty($errors['environment'])): ?><span class="error"><?= e(t((string) $errors['environment'])) ?></span><?php endif; ?>
        </label>
        <label><?= e(t('platform_gateway_public_key')) ?>
            <input name="public_key" value="<?= e((string) ($gateway['public_key'] ?? '')) ?>" autocomplete="off">
            <?php if (!empty($errors['public_key'])): ?><span class="error"><?= e(t((string) $errors['public_key'])) ?></span><?php endif; ?>
        </label>
        <label><?= e(t('platform_gateway_secret_key')) ?>
            <input name="secret_key" type="password" value="" placeholder="<?= e((string) ($gateway['secret_mask'] ?: t('platform_gateway_secret_placeholder'))) ?>" autocomplete="new-password">
            <?php if (($gateway['secret_mask'] ?? '') !== ''): ?>
                <span class="meta"><?= e(t('platform_gateway_saved_mask')) ?>: <?= e((string) $gateway['secret_mask']) ?></span>
            <?php endif; ?>
            <?php if (!empty($errors['secret_key'])): ?><span class="error"><?= e(t((string) $errors['secret_key'])) ?></span><?php endif; ?>
        </label>
        <label><?= e(t('platform_gateway_webhook_secret')) ?>
            <input name="webhook_secret" type="password" value="" placeholder="<?= e((string) ($gateway['webhook_secret_mask'] ?: t('platform_gateway_secret_placeholder'))) ?>" autocomplete="new-password">
            <?php if (($gateway['webhook_secret_mask'] ?? '') !== ''): ?>
                <span class="meta"><?= e(t('platform_gateway_saved_mask')) ?>: <?= e((string) $gateway['webhook_secret_mask']) ?></span>
            <?php endif; ?>
            <span class="meta"><?= e(t('platform_gateway_webhook_secret_help')) ?></span>
            <?php if (!empty($errors['webhook_secret'])): ?><span class="error"><?= e(t((string) $errors['webhook_secret'])) ?></span><?php endif; ?>
        </label>
        <button class="btn" type="submit"><?= e(t('save')) ?></button>
    </form>

    <form method="post" action="<?= e(url('/platform/gateway')) ?>" class="stack" style="margin-top:16px">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="test">
        <button class="btn btn-quiet" type="submit"><?= e(t('platform_gateway_test')) ?></button>
    </form>
</section>
