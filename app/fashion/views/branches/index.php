<?php
/** @var array $branches */
/** @var array $errors */
/** @var array $old */
$activeCount = 0;
foreach ($branches as $branch) {
    if (!empty($branch['active'])) {
        $activeCount++;
    }
}
$title = t('branches');
$back_href = null;
$subtitle = t('branches_help');
$actions_html = '';
require __DIR__ . '/../partials/ui_page_header.php';
?>
<section class="dash-kpis" aria-label="<?= e(t('summary')) ?>">
    <article class="dash-kpi dash-kpi-blue">
        <span class="dash-kpi-top">
            <span class="dash-kpi-icon" aria-hidden="true"><?= ui_icon('building-2', 'ui-icon') ?></span>
            <span class="dash-kpi-body">
                <span class="dash-kpi-label"><?= e(t('branches')) ?></span>
                <strong class="dash-kpi-value"><?= e((string) count($branches)) ?></strong>
            </span>
        </span>
    </article>
    <article class="dash-kpi dash-kpi-green">
        <span class="dash-kpi-top">
            <span class="dash-kpi-body">
                <span class="dash-kpi-label"><?= e(t('branch_active')) ?></span>
                <strong class="dash-kpi-value"><?= e((string) $activeCount) ?></strong>
            </span>
        </span>
    </article>
</section>

<div class="detail-layout" style="margin-top:1rem">
    <div class="detail-main">
        <section class="card list-page-shell">
            <?php if ($branches === []): ?>
                <?php
                $title = t('branches_empty');
                $help = t('branches_help');
                $icon = 'building-2';
                $cta_href = null;
                $cta_label = null;
                require __DIR__ . '/../partials/ui_empty_state.php';
                ?>
            <?php else: ?>
                <div class="ui-table-wrap">
                    <table class="ui-table">
                        <thead>
                            <tr>
                                <th><?= e(t('name')) ?></th>
                                <th><?= e(t('phone')) ?></th>
                                <th><?= e(t('type')) ?></th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($branches as $branch): ?>
                                <tr>
                                    <td data-label="<?= e(t('name')) ?>"><strong><?= e($branch['name']) ?></strong></td>
                                    <td data-label="<?= e(t('phone')) ?>"><?= e($branch['phone']) ?></td>
                                    <td data-label="<?= e(t('type')) ?>">
                                        <span class="orders-badge is-<?= $branch['active'] ? 'success' : 'orange' ?>">
                                            <?= e($branch['active'] ? t('branch_active') : t('branch_inactive')) ?>
                                        </span>
                                    </td>
                                    <td data-label="">
                                        <?php if ($branch['active']): ?>
                                            <form method="post" action="<?= e(url('/branches/' . $branch['id'] . '/deactivate')) ?>">
                                                <?= csrf_field() ?>
                                                <button class="btn btn-small btn-quiet" type="submit"><?= e(t('branch_deactivate')) ?></button>
                                            </form>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>
    </div>

    <aside class="detail-side">
        <section class="card form-sheet">
            <h2><?= e(t('branch_add')) ?></h2>
            <form method="post" action="<?= e(url('/branches')) ?>" class="stack">
                <?= csrf_field() ?>
                <div>
                    <label class="ui-label" for="field-name"><?= e(t('name')) ?> *</label>
                    <input id="field-name" name="name" value="<?= e((string) ($old['name'] ?? '')) ?>" required>
                </div>
                <?php if (isset($errors['form'])): ?><p class="ui-error"><?= e(t($errors['form'])) ?></p><?php endif; ?>
                <div>
                    <label class="ui-label" for="field-phone"><?= e(t('phone')) ?></label>
                    <input id="field-phone" name="phone" value="<?= e((string) ($old['phone'] ?? '')) ?>">
                </div>
                <div>
                    <label class="ui-label" for="field-address"><?= e(t('address')) ?></label>
                    <input id="field-address" name="address" value="<?= e((string) ($old['address'] ?? '')) ?>">
                </div>
                <button class="btn" type="submit"><?= e(t('branch_add')) ?></button>
            </form>
        </section>
    </aside>
</div>