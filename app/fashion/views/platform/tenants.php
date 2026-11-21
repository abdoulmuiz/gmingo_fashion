<?php
/** @var array $tenants */
/** @var string $q */
?>
<section class="card">
    <h1><?= e(t('platform_tenants')) ?></h1>
    <p class="meta"><?= e(t('platform_tenants_help')) ?></p>
    <p><a href="<?= e(url('/platform')) ?>"><?= e(t('platform_home')) ?></a></p>
    <form method="get" action="<?= e(url('/platform/tenants')) ?>">
        <label><?= e(t('search')) ?>
            <input name="q" value="<?= e($q) ?>">
        </label>
        <button class="btn" type="submit"><?= e(t('search')) ?></button>
    </form>
    <?php if ($tenants === []): ?>
        <p><?= e(t('platform_tenants_empty')) ?></p>
    <?php else: ?>
        <?php foreach ($tenants as $tenant): ?>
            <a class="line" href="<?= e(url('/platform/tenants/' . $tenant['id'])) ?>">
                <strong><?= e((string) $tenant['name']) ?></strong>
                <span><?= e((string) $tenant['company_type']) ?></span>
                <span>
                    <?php if (is_array($tenant['subscription'] ?? null)): ?>
                        <?= e(t('subscription_status_' . (string) $tenant['subscription']['status'])) ?>
                    <?php else: ?>
                        <?= e(t('subscription_none')) ?>
                    <?php endif; ?>
                </span>
            </a>
        <?php endforeach; ?>
    <?php endif; ?>
</section>
