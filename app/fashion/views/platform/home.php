<?php
/** @var array $stats */
?>
<section class="card">
    <h1><?= e(t('platform_home')) ?></h1>
    <p class="meta"><?= e(t('platform_home_help')) ?></p>
    <p><strong><?= e(t('platform_stat_tenants')) ?>:</strong> <?= e((string) $stats['tenants']) ?></p>
    <p><strong><?= e(t('platform_stat_plans')) ?>:</strong> <?= e((string) $stats['active_plans']) ?></p>
    <?php if (($stats['subscriptions_by_status'] ?? []) !== []): ?>
        <h2><?= e(t('platform_stat_subscriptions')) ?></h2>
        <?php foreach ($stats['subscriptions_by_status'] as $status => $total): ?>
            <p><?= e(t('subscription_status_' . (string) $status)) ?>: <?= e((string) $total) ?></p>
        <?php endforeach; ?>
    <?php endif; ?>
    <p><a class="btn" href="<?= e(url('/platform/tenants')) ?>"><?= e(t('platform_tenants')) ?></a></p>
    <p><a class="btn btn-quiet" href="<?= e(url('/platform/plans')) ?>"><?= e(t('platform_plans')) ?></a></p>
    <form method="post" action="<?= e(url('/logout')) ?>">
        <?= csrf_field() ?>
        <button class="btn btn-quiet" type="submit"><?= e(t('logout')) ?></button>
    </form>
</section>
