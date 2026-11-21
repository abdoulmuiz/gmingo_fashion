<?php
/** @var array $tenant */
/** @var array $events */
/** @var array $plans */
/** @var array $errors */
$sub = is_array($tenant['subscription'] ?? null) ? $tenant['subscription'] : null;
$plan = is_array($tenant['plan'] ?? null) ? $tenant['plan'] : null;
?>
<section class="card">
    <h1><?= e((string) $tenant['name']) ?></h1>
    <p><a href="<?= e(url('/platform/tenants')) ?>"><?= e(t('platform_tenants')) ?></a></p>
    <?php if (!empty($errors['form'])): ?>
        <p class="error"><?= e(t((string) $errors['form'])) ?></p>
    <?php endif; ?>
    <p><strong><?= e(t('company_type')) ?>:</strong> <?= e((string) $tenant['company_type']) ?></p>
    <p><strong><?= e(t('phone')) ?>:</strong> <?= e((string) $tenant['phone']) ?></p>
    <p>
        <strong><?= e(t('subscription_status')) ?>:</strong>
        <?= e($sub !== null ? t('subscription_status_' . (string) $sub['status']) : t('subscription_none')) ?>
    </p>
    <?php if ($plan !== null): ?>
        <p><strong><?= e(t('subscription_plan')) ?>:</strong> <?= e((string) ($plan['name'] ?? $plan['code'] ?? '')) ?></p>
    <?php endif; ?>
    <p>
        <strong><?= e(t('subscription_access_until')) ?>:</strong>
        <?= e((string) ($tenant['subscription_ends_on'] ?? t('subscription_none'))) ?>
    </p>
</section>

<section class="card">
    <h2><?= e(t('platform_tenant_actions')) ?></h2>
    <?php if ($sub !== null && (string) $sub['status'] !== 'suspended'): ?>
        <form method="post" action="<?= e(url('/platform/tenants/' . $tenant['id'])) ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="suspend">
            <label><?= e(t('platform_action_note')) ?>
                <input name="note" maxlength="500">
            </label>
            <button class="btn" type="submit"><?= e(t('platform_suspend')) ?></button>
        </form>
    <?php endif; ?>

    <form method="post" action="<?= e(url('/platform/tenants/' . $tenant['id'])) ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="reactivate">
        <label><?= e(t('subscription_plan')) ?>
            <select name="plan_id">
                <?php foreach ($plans as $option): ?>
                    <option value="<?= e((string) $option['id']) ?>" <?= ($plan['id'] ?? '') === $option['id'] ? 'selected' : '' ?>>
                        <?= e((string) $option['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>
        <label><?= e(t('platform_billing_interval')) ?>
            <select name="billing_interval">
                <option value="monthly"><?= e(t('platform_interval_monthly')) ?></option>
                <option value="annual"><?= e(t('platform_interval_annual')) ?></option>
            </select>
        </label>
        <button class="btn" type="submit"><?= e(t('platform_reactivate')) ?></button>
    </form>

    <?php if ($sub !== null && in_array((string) $sub['status'], ['trial', 'active', 'suspended'], true)): ?>
        <form method="post" action="<?= e(url('/platform/tenants/' . $tenant['id'])) ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="change_plan">
            <label><?= e(t('platform_change_plan')) ?>
                <select name="plan_id">
                    <?php foreach ($plans as $option): ?>
                        <option value="<?= e((string) $option['id']) ?>" <?= ($plan['id'] ?? '') === $option['id'] ? 'selected' : '' ?>>
                            <?= e((string) $option['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
            <button class="btn btn-quiet" type="submit"><?= e(t('save')) ?></button>
        </form>
    <?php endif; ?>
</section>

<section class="card">
    <h2><?= e(t('platform_tenant_events')) ?></h2>
    <?php if ($events === []): ?>
        <p><?= e(t('platform_events_empty')) ?></p>
    <?php else: ?>
        <?php foreach ($events as $event): ?>
            <p class="meta">
                <?= e((string) $event['created_at']) ?>
                · <?= e((string) $event['event_type']) ?>
                <?php if (($event['from_status'] ?? '') !== '' || ($event['to_status'] ?? '') !== ''): ?>
                    (<?= e((string) ($event['from_status'] ?? '')) ?> → <?= e((string) ($event['to_status'] ?? '')) ?>)
                <?php endif; ?>
                <?php if (($event['note'] ?? '') !== ''): ?>
                    — <?= e((string) $event['note']) ?>
                <?php endif; ?>
            </p>
        <?php endforeach; ?>
    <?php endif; ?>
</section>
