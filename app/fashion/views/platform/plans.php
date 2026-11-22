<?php
/** @var array $plans */
/** @var array $modules */
/** @var array $limit_keys */
/** @var array $errors */
/** @var array $old */
?>
<section class="card">
    <h1><?= e(t('platform_plans')) ?></h1>
    <p class="meta"><?= e(t('platform_plans_help')) ?></p>
    <p><a href="<?= e(url('/platform')) ?>"><?= e(t('platform_home')) ?></a></p>

    <?php if ($plans === []): ?>
        <p><?= e(t('platform_plans_empty')) ?></p>
    <?php else: ?>
        <?php foreach ($plans as $plan): ?>
            <a class="line" href="<?= e(url('/platform/plans/' . $plan['id'])) ?>">
                <strong><?= e((string) $plan['name']) ?></strong>
                <span><?= e((string) $plan['code']) ?></span>
                <span><?= e(money((int) $plan['price_monthly_kobo'])) ?> / <?= e(t('platform_interval_monthly')) ?></span>
                <span><?= e($plan['active'] ? t('platform_plan_active') : t('platform_plan_inactive')) ?></span>
                <?php if (!empty($plan['is_popular'])): ?><span class="tag"><?= e(t('pricing_most_popular')) ?></span><?php endif; ?>
            </a>
        <?php endforeach; ?>
    <?php endif; ?>
</section>

<section class="card">
    <h2><?= e(t('platform_plan_create')) ?></h2>
    <?php if (!empty($errors['form'])): ?>
        <p class="error"><?= e(t((string) $errors['form'])) ?></p>
    <?php endif; ?>
    <form method="post" action="<?= e(url('/platform/plans')) ?>">
        <?= csrf_field() ?>
        <label><?= e(t('platform_plan_code')) ?>
            <input name="code" value="<?= e((string) ($old['code'] ?? '')) ?>" required>
            <?php if (!empty($errors['code'])): ?><span class="error"><?= e(t((string) $errors['code'])) ?></span><?php endif; ?>
        </label>
        <label><?= e(t('platform_plan_name')) ?>
            <input name="name" value="<?= e((string) ($old['name'] ?? '')) ?>" required>
            <?php if (!empty($errors['name'])): ?><span class="error"><?= e(t((string) $errors['name'])) ?></span><?php endif; ?>
        </label>
        <label><?= e(t('platform_plan_description')) ?>
            <textarea name="description" rows="2"><?= e((string) ($old['description'] ?? '')) ?></textarea>
        </label>
        <label><?= e(t('platform_price_monthly')) ?>
            <input name="price_monthly_kobo" inputmode="numeric" value="<?= e((string) ($old['price_monthly_kobo'] ?? '')) ?>" required>
        </label>
        <label><?= e(t('platform_price_annual')) ?>
            <input name="price_annual_kobo" inputmode="numeric" value="<?= e((string) ($old['price_annual_kobo'] ?? '')) ?>" required>
        </label>
        <label><?= e(t('platform_plan_currency')) ?>
            <input name="currency" value="<?= e((string) ($old['currency'] ?? 'NGN')) ?>" maxlength="3">
        </label>
        <label><?= e(t('platform_plan_features')) ?>
            <textarea name="features" rows="5" placeholder="<?= e(t('platform_plan_features_help')) ?>"><?= e((string) ($old['features'] ?? '')) ?></textarea>
        </label>
        <label><?= e(t('platform_plan_sort_order')) ?>
            <input name="sort_order" inputmode="numeric" value="<?= e((string) ($old['sort_order'] ?? '100')) ?>">
        </label>
        <label><?= e(t('platform_plan_icon')) ?>
            <input name="icon" value="<?= e((string) ($old['icon'] ?? '')) ?>">
        </label>
        <label class="check">
            <input type="checkbox" name="is_popular" value="1" <?= !empty($old['is_popular']) ? 'checked' : '' ?>>
            <?= e(t('platform_plan_most_popular')) ?>
        </label>
        <label class="check">
            <input type="checkbox" name="trial_available" value="1" <?= !empty($old['trial_available']) ? 'checked' : '' ?>>
            <?= e(t('platform_trial_available')) ?>
        </label>
        <label><?= e(t('platform_trial_days')) ?>
            <input name="trial_days" inputmode="numeric" value="<?= e((string) ($old['trial_days'] ?? '14')) ?>">
        </label>
        <label class="check">
            <input type="checkbox" name="active" value="1" <?= !isset($old['active']) || !empty($old['active']) ? 'checked' : '' ?>>
            <?= e(t('platform_plan_active')) ?>
        </label>
        <h3><?= e(t('platform_plan_limits')) ?></h3>
        <?php foreach ($limit_keys as $key): ?>
            <label><?= e($key) ?>
                <input name="limits[<?= e($key) ?>]" inputmode="numeric" value="<?= e((string) (($old['limits'][$key] ?? '') )) ?>">
            </label>
        <?php endforeach; ?>
        <h3><?= e(t('platform_plan_modules')) ?></h3>
        <?php
        $oldModules = is_array($old['modules'] ?? null) ? $old['modules'] : [];
        foreach ($modules as $module):
        ?>
            <label class="check">
                <input type="checkbox" name="modules[]" value="<?= e($module) ?>" <?= in_array($module, $oldModules, true) ? 'checked' : '' ?>>
                <?= e(module_label($module)) ?>
            </label>
        <?php endforeach; ?>
        <button class="btn" type="submit"><?= e(t('save')) ?></button>
    </form>
</section>
