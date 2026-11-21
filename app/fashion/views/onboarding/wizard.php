<?php
/** @var array $state */
/** @var array $errors */
/** @var array $old */
$step = (string) ($state['step'] ?? 'business');
$tenant = $state['tenant'] ?? [];
$steps = $state['steps'] ?? [];
?>
<section class="card onboarding">
    <h1><?= e(t('onboarding_title')) ?></h1>
    <p class="meta"><?= e(t('onboarding_help')) ?></p>
    <ol class="onboarding-steps">
        <?php foreach ($steps as $name): ?>
            <?php if ($name === 'inventory' && empty($state['inventory_on'])) {
                continue;
            } ?>
            <li class="<?= $name === $step ? 'is-current' : '' ?>"><?= e(t('onboarding_step_' . $name)) ?></li>
        <?php endforeach; ?>
    </ol>

    <?php if (!empty($errors['form'])): ?>
        <p class="error"><?= e(t((string) $errors['form'])) ?></p>
    <?php endif; ?>

    <form method="post" action="<?= e(url('/onboarding')) ?>"<?= $step === 'branding' ? ' enctype="multipart/form-data"' : '' ?>>
        <?= csrf_field() ?>

        <?php if ($step === 'business'): ?>
            <h2><?= e(t('onboarding_step_business')) ?></h2>
            <label><?= e(t('business_name')) ?>
                <input name="business_name" value="<?= e((string) ($old['business_name'] ?? $tenant['name'] ?? '')) ?>" required>
            </label>
            <?php if (!empty($errors['business_name'])): ?><p class="error"><?= e(t((string) $errors['business_name'])) ?></p><?php endif; ?>
            <label><?= e(t('company_type')) ?>
                <select name="company_type" required>
                    <?php foreach ($state['company_types'] as $type): ?>
                        <option value="<?= e($type) ?>" <?= ((string) ($old['company_type'] ?? $tenant['company_type'] ?? '')) === $type ? 'selected' : '' ?>><?= e(t('type_' . $type)) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <?php if (!empty($errors['company_type'])): ?><p class="error"><?= e(t((string) $errors['company_type'])) ?></p><?php endif; ?>

        <?php elseif ($step === 'contact'): ?>
            <h2><?= e(t('onboarding_step_contact')) ?></h2>
            <label><?= e(t('shop_phone')) ?>
                <input name="phone" value="<?= e((string) ($old['phone'] ?? $tenant['phone'] ?? '')) ?>" required>
            </label>
            <?php if (!empty($errors['phone'])): ?><p class="error"><?= e(t((string) $errors['phone'])) ?></p><?php endif; ?>
            <label><?= e(t('business_address')) ?>
                <input name="address" value="<?= e((string) ($old['address'] ?? $tenant['address'] ?? '')) ?>">
            </label>
            <?php if (!empty($errors['address'])): ?><p class="error"><?= e(t((string) $errors['address'])) ?></p><?php endif; ?>

        <?php elseif ($step === 'branding'): ?>
            <h2><?= e(t('onboarding_step_branding')) ?></h2>
            <p class="meta"><?= e(t('onboarding_branding_help')) ?></p>
            <?php if (!empty($state['logo_url'])): ?>
                <p><img class="onboarding-logo" src="<?= e((string) $state['logo_url']) ?>" alt=""></p>
            <?php endif; ?>
            <label><?= e(t('business_logo')) ?>
                <input type="file" name="logo" accept="image/jpeg,image/png,image/webp">
            </label>
            <?php if (!empty($errors['logo'])): ?><p class="error"><?= e(t((string) $errors['logo'])) ?></p><?php endif; ?>
            <label><?= e(t('brand_primary')) ?>
                <input name="brand_primary" placeholder="#1e3a8a" value="<?= e((string) ($old['brand_primary'] ?? $tenant['brand_primary'] ?? '')) ?>">
            </label>
            <label><?= e(t('brand_accent')) ?>
                <input name="brand_accent" placeholder="#38b35a" value="<?= e((string) ($old['brand_accent'] ?? $tenant['brand_accent'] ?? '')) ?>">
            </label>
            <?php if (!empty($errors['brand'])): ?><p class="error"><?= e(t((string) $errors['brand'])) ?></p><?php endif; ?>

        <?php elseif ($step === 'preferences'): ?>
            <h2><?= e(t('onboarding_step_preferences')) ?></h2>
            <label><?= e(t('currency')) ?>
                <select name="currency">
                    <?php foreach ($state['currencies'] as $code): ?>
                        <option value="<?= e($code) ?>" <?= ((string) ($old['currency'] ?? $tenant['currency'] ?? 'NGN')) === $code ? 'selected' : '' ?>><?= e($code) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <?php if (!empty($errors['currency'])): ?><p class="error"><?= e(t((string) $errors['currency'])) ?></p><?php endif; ?>
            <label><?= e(t('language')) ?>
                <select name="lang">
                    <option value="en" <?= ((string) ($old['lang'] ?? current_lang())) === 'en' ? 'selected' : '' ?>><?= e(t('english')) ?></option>
                    <option value="ha" <?= ((string) ($old['lang'] ?? current_lang())) === 'ha' ? 'selected' : '' ?>><?= e(t('hausa')) ?></option>
                </select>
            </label>

        <?php elseif ($step === 'staff'): ?>
            <h2><?= e(t('onboarding_step_staff')) ?></h2>
            <p class="meta"><?= e(t('onboarding_staff_help')) ?></p>
            <label><?= e(t('staff_name')) ?>
                <input name="staff_name" value="<?= e((string) ($old['staff_name'] ?? '')) ?>">
            </label>
            <?php if (!empty($errors['staff_name'])): ?><p class="error"><?= e(t((string) $errors['staff_name'])) ?></p><?php endif; ?>
            <label><?= e(t('staff_email')) ?>
                <input name="staff_email" type="email" value="<?= e((string) ($old['staff_email'] ?? '')) ?>">
            </label>
            <?php if (!empty($errors['staff_email'])): ?><p class="error"><?= e(t((string) $errors['staff_email'])) ?></p><?php endif; ?>
            <label><?= e(t('staff_password')) ?>
                <input name="staff_password" type="password">
            </label>
            <?php if (!empty($errors['staff_password'])): ?><p class="error"><?= e(t((string) $errors['staff_password'])) ?></p><?php endif; ?>
            <button class="btn btn-quiet" type="submit" name="skip" value="1"><?= e(t('onboarding_skip_step')) ?></button>

        <?php elseif ($step === 'inventory'): ?>
            <h2><?= e(t('onboarding_step_inventory')) ?></h2>
            <p class="meta"><?= e(t('onboarding_inventory_help')) ?></p>
            <label><?= e(t('stock_name')) ?>
                <input name="name" value="<?= e((string) ($old['name'] ?? '')) ?>">
            </label>
            <?php if (!empty($errors['name'])): ?><p class="error"><?= e(t((string) $errors['name'])) ?></p><?php endif; ?>
            <label><?= e(t('stock_category')) ?>
                <select name="category">
                    <?php foreach (stock_categories() as $category): ?>
                        <option value="<?= e($category) ?>" <?= ((string) ($old['category'] ?? 'fabric')) === $category ? 'selected' : '' ?>><?= e($category) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label><?= e(t('stock_unit')) ?>
                <select name="unit">
                    <?php foreach (stock_units() as $unit): ?>
                        <option value="<?= e($unit) ?>" <?= ((string) ($old['unit'] ?? 'yards')) === $unit ? 'selected' : '' ?>><?= e($unit) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label><?= e(t('stock_colour')) ?>
                <input name="colour" value="<?= e((string) ($old['colour'] ?? '')) ?>">
            </label>
            <label><?= e(t('stock_opening')) ?>
                <input name="opening_qty" value="<?= e((string) ($old['opening_qty'] ?? '')) ?>">
            </label>
            <button class="btn btn-quiet" type="submit" name="skip" value="1"><?= e(t('onboarding_skip_step')) ?></button>

        <?php elseif ($step === 'subscription'): ?>
            <h2><?= e(t('onboarding_step_subscription')) ?></h2>
            <p><?= e(t('onboarding_subscription_help')) ?></p>
            <?php if (!empty($state['plan'])): ?>
                <p><strong><?= e((string) $state['plan']['name']) ?></strong></p>
                <p class="meta"><?= e(t('onboarding_trial_until', [
                    'date' => (string) (($state['subscription']['trial_ends_on'] ?? $state['subscription']['expires_on'] ?? '')),
                ])) ?></p>
            <?php endif; ?>
        <?php endif; ?>

        <p class="onboarding-actions">
            <button class="btn" type="submit" name="action" value="save"><?= e(t('onboarding_continue')) ?></button>
            <button class="btn btn-quiet" type="submit" name="action" value="skip_all"><?= e(t('onboarding_finish_later')) ?></button>
        </p>
    </form>
</section>
