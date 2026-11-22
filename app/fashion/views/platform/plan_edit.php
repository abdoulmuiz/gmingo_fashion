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
$paystackMonthly = (string) ($old['paystack_plan_code_monthly'] ?? $plan['paystack_plan_code_monthly'] ?? '');
$paystackAnnual = (string) ($old['paystack_plan_code_annual'] ?? $plan['paystack_plan_code_annual'] ?? '');
$currency = (string) ($old['currency'] ?? $plan['currency'] ?? 'NGN');
$isPopular = array_key_exists('is_popular', $old) ? !empty($old['is_popular']) : !empty($plan['is_popular']);
$sortOrder = (string) ($old['sort_order'] ?? $plan['sort_order'] ?? '100');
$icon = (string) ($old['icon'] ?? $plan['icon'] ?? '');
if (is_array($old['features'] ?? null)) {
    $featuresText = implode("\n", $old['features']);
} elseif (is_string($old['features'] ?? null)) {
    $featuresText = (string) $old['features'];
} else {
    $featuresText = implode("\n", $plan['features'] ?? []);
}
?>
<section class="card">
    <h1><?= e(t('platform_plan_edit')) ?></h1>
    <p class="meta"><?= e((string) $plan['code']) ?> · v<?= e((string) ($plan['price_version'] ?? 1)) ?></p>
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
        <label><?= e(t('platform_plan_currency')) ?>
            <input name="currency" value="<?= e($currency) ?>" maxlength="3" required>
            <?php if (!empty($errors['currency'])): ?><span class="error"><?= e(t((string) $errors['currency'])) ?></span><?php endif; ?>
        </label>
        <label class="check">
            <input type="checkbox" name="confirm_price_change" value="1">
            <?= e(t('platform_confirm_price_change')) ?>
        </label>
        <?php if (!empty($errors['confirm_price_change'])): ?><p class="error"><?= e(t((string) $errors['confirm_price_change'])) ?></p><?php endif; ?>
        <p class="meta"><?= e(t('platform_confirm_price_change_help')) ?></p>
        <label><?= e(t('platform_plan_features')) ?>
            <textarea name="features" rows="6" placeholder="<?= e(t('platform_plan_features_help')) ?>"><?= e($featuresText) ?></textarea>
        </label>
        <label><?= e(t('platform_plan_sort_order')) ?>
            <input name="sort_order" inputmode="numeric" value="<?= e($sortOrder) ?>">
            <?php if (!empty($errors['sort_order'])): ?><span class="error"><?= e(t((string) $errors['sort_order'])) ?></span><?php endif; ?>
        </label>
        <label><?= e(t('platform_plan_icon')) ?>
            <input name="icon" value="<?= e($icon) ?>">
            <?php if (!empty($errors['icon'])): ?><span class="error"><?= e(t((string) $errors['icon'])) ?></span><?php endif; ?>
        </label>
        <label class="check">
            <input type="checkbox" name="is_popular" value="1" <?= $isPopular ? 'checked' : '' ?>>
            <?= e(t('platform_plan_most_popular')) ?>
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
        <h2><?= e(t('platform_paystack_plan_codes')) ?></h2>
        <p class="meta"><?= e(t('platform_paystack_plan_codes_help')) ?></p>
        <label><?= e(t('platform_paystack_plan_monthly')) ?>
            <input name="paystack_plan_code_monthly" value="<?= e($paystackMonthly) ?>" autocomplete="off">
            <?php if (!empty($errors['paystack_plan_code_monthly'])): ?><span class="error"><?= e(t((string) $errors['paystack_plan_code_monthly'])) ?></span><?php endif; ?>
        </label>
        <label><?= e(t('platform_paystack_plan_annual')) ?>
            <input name="paystack_plan_code_annual" value="<?= e($paystackAnnual) ?>" autocomplete="off">
            <?php if (!empty($errors['paystack_plan_code_annual'])): ?><span class="error"><?= e(t((string) $errors['paystack_plan_code_annual'])) ?></span><?php endif; ?>
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
