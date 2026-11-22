<?php require __DIR__ . '/partials/settings_nav.php'; ?>
<div class="settings-layout">
    <section class="card settings-panel">
        <header class="ui-page-header">
            <div class="ui-page-header-copy">
                <h1><?= e(t('modules')) ?></h1>
                <p><?= e(t('company_type')) ?>: <?= e(t('type_' . $company_type)) ?></p>
            </div>
        </header>
        <p class="meta"><?= e(t('modules_help')) ?></p>
        <form method="post" action="<?= e(url('/settings')) ?>" class="stack">
            <?= csrf_field() ?>
            <?php foreach ($modules as $module): ?>
                <label class="ui-check">
                    <input type="checkbox" name="modules[]" value="<?= e((string) $module['module_key']) ?>" <?= (int) $module['enabled'] === 1 ? 'checked' : '' ?>>
                    <span><?= e(module_label((string) $module['module_key'])) ?></span>
                </label>
            <?php endforeach; ?>
            <button class="btn" type="submit"><?= e(t('save')) ?></button>
        </form>
    </section>

    <section class="card settings-panel">
        <h2><?= e(t('deposit_percent')) ?></h2>
        <p class="meta"><?= e(t('deposit_percent_help')) ?></p>
        <form method="post" action="<?= e(url('/settings')) ?>" class="form-grid">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="deposit">
            <label><?= e(t('deposit_percent')) ?>
                <input name="deposit_percent" inputmode="numeric" value="<?= e((string) $deposit_percent) ?>">
            </label>
            <label><?= e(t('deposit_stage')) ?>
                <select name="deposit_stage_id">
                    <option value=""><?= e(t('deposit_stage_none')) ?></option>
                    <?php foreach ($deposit_stages as $stage): ?>
                        <option value="<?= e((string) $stage['id']) ?>" <?= (string) $stage['id'] === $deposit_stage_id ? 'selected' : '' ?>><?= e((string) $stage['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <div class="span-2">
                <button class="btn" type="submit"><?= e(t('save')) ?></button>
            </div>
        </form>
    </section>

    <section class="card settings-panel">
        <h2><?= e(t('payment_details')) ?></h2>
        <p class="meta"><?= e(t('payment_reference_help')) ?></p>
        <form method="post" action="<?= e(url('/settings')) ?>" class="form-grid">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="payment_details">
            <label><?= e(t('payment_channel')) ?>
                <select name="payment_channel">
                    <option value="bank" <?= $payment_channel === 'bank' ? 'selected' : '' ?>><?= e(t('payment_bank_name')) ?></option>
                    <option value="opay" <?= $payment_channel === 'opay' ? 'selected' : '' ?>><?= e(t('payment_opay')) ?></option>
                </select>
            </label>
            <label><?= e(t('payment_account_name')) ?>
                <input name="payment_bank" maxlength="190" value="<?= e($payment_bank) ?>">
            </label>
            <label><?= e(t('payment_account_number')) ?>
                <input name="payment_account" maxlength="190" value="<?= e($payment_account) ?>">
            </label>
            <label><?= e(t('payment_reference')) ?>
                <input name="payment_reference_note" maxlength="190" value="<?= e($payment_reference_note) ?>">
            </label>
            <div class="span-2">
                <button class="btn" type="submit"><?= e(t('save')) ?></button>
            </div>
        </form>
    </section>
</div>
