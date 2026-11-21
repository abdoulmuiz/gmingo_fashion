<?php
/** @var array $plan */
/** @var array $modules */
/** @var array $limit_keys */
/** @var array $errors */
/** @var array $old */
$name = (string) ($old['name'] ?? $plan['name']);
$description = (string) ($old['description'] ?? $plan['description']);
$monthly = (string) ($old['price_monthly_kobo'] ?? $plan['price_monthly_kobo']);
$annual = (string) ($old['price_annual_kobo'] ?? $plan['price_annual_kobo']);
$trialDays = (string) ($old['trial_days'] ?? $plan['trial_days']);
$trialAvailable = array_key_exists('trial_available', $old) ? !empty($old['trial_available']) : $plan['trial_available'];
$active = array_key_exists('active', $old) ? !empty($old['active']) : $plan['active'];
$oldLimits = is_array($old['limits'] ?? null) ? $old['limits'] : $plan['limits'];
$oldModules = is_array($old['modules'] ?? null) ? $old['modules'] : $plan['modules'];
?>
<section class="card">
    <h1><?= e(t('platform_plan_edit')) ?></h1>
    <p class="meta"><?= e((string) $plan['code']) ?></p>
    <p><a href="<?= e(url('/platform/plans')) ?>"><?= e(t('platform_plans')) ?></a></p>
    <?php if (!empty($errors['form'])): ?>
        <p class="error"><?= e(t((string) $errors['form'])) ?></p>
    <?php endif; ?>
    <form method="post" action="<?= e(url('/platform/plans/' . $plan['id'])) ?>">
        <?= csrf_field() ?>
        <label><?= e(t('platform_plan_name')) ?>
            <input name="name" value="<?= e($name) ?>" required>
            <?php if (!empty($errors['name'])): ?><span class="error"><?= e(t((string) $errors['name'])) ?></span><?php endif; ?>
        </label>
        <label><?= e(t('platform_plan_description')) ?>
            <textarea name="description" rows="2"><?= e($description) ?></textarea>
        </label>
        <label><?= e(t('platform_price_monthly')) ?>
            <input name="price_monthly_kobo" inputmode="numeric" value="<?= e($monthly) ?>" required>
        </label>
        <label><?= e(t('platform_price_annual')) ?>
            <input name="price_annual_kobo" inputmode="numeric" value="<?= e($annual) ?>" required>
        </label>
        <label class="check">
            <input type="checkbox" name="trial_available" value="1" <?= $trialAvailable ? 'checked' : '' ?>>
            <?= e(t('platform_trial_available')) ?>
        </label>
        <label><?= e(t('platform_trial_days')) ?>
            <input name="trial_days" inputmode="numeric" value="<?= e($trialDays) ?>">
        </label>
        <label class="check">
            <input type="checkbox" name="active" value="1" <?= $active ? 'checked' : '' ?>>
            <?= e(t('platform_plan_active')) ?>
        </label>
        <h2><?= e(t('platform_plan_limits')) ?></h2>
        <?php foreach ($limit_keys as $key): ?>
            <label><?= e($key) ?>
                <input name="limits[<?= e($key) ?>]" inputmode="numeric" value="<?= e((string) ($oldLimits[$key] ?? '')) ?>">
            </label>
        <?php endforeach; ?>
        <h2><?= e(t('platform_plan_modules')) ?></h2>
        <?php foreach ($modules as $module): ?>
            <label class="check">
                <input type="checkbox" name="modules[]" value="<?= e($module) ?>" <?= in_array($module, $oldModules, true) ? 'checked' : '' ?>>
                <?= e(module_label($module)) ?>
            </label>
        <?php endforeach; ?>
        <button class="btn" type="submit"><?= e(t('save')) ?></button>
    </form>
</section>
