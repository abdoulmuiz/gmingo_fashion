<?php
/** @var array $people */
$activeCount = 0;
foreach ($people as $person) {
    if (!empty($person['active'])) {
        $activeCount++;
    }
}
ob_start();
?>
<a class="btn" href="<?= e(url('/staff/new')) ?>">
    <?= ui_icon('plus', 'ui-icon') ?>
    <span><?= e(t('staff_add')) ?></span>
</a>
<?php
$actions_html = ob_get_clean();
$title = t('staff');
$back_href = null;
$subtitle = t('staff_help');
require __DIR__ . '/../partials/ui_page_header.php';
?>
<section class="dash-kpis" aria-label="<?= e(t('summary')) ?>">
    <article class="dash-kpi dash-kpi-blue">
        <span class="dash-kpi-top">
            <span class="dash-kpi-icon" aria-hidden="true"><?= ui_icon('users', 'ui-icon') ?></span>
            <span class="dash-kpi-body">
                <span class="dash-kpi-label"><?= e(t('staff')) ?></span>
                <strong class="dash-kpi-value"><?= e((string) count($people)) ?></strong>
            </span>
        </span>
    </article>
    <article class="dash-kpi dash-kpi-green">
        <span class="dash-kpi-top">
            <span class="dash-kpi-body">
                <span class="dash-kpi-label"><?= e(t('staff_active')) ?></span>
                <strong class="dash-kpi-value"><?= e((string) $activeCount) ?></strong>
            </span>
        </span>
    </article>
</section>

<section class="card list-page-shell" style="margin-top:1rem">
    <?php if ($people === []): ?>
        <?php
        $title = t('staff_empty');
        $help = t('staff_help');
        $icon = 'users';
        $cta_href = '/staff/new';
        $cta_label = t('staff_add');
        require __DIR__ . '/../partials/ui_empty_state.php';
        ?>
    <?php else: ?>
        <div class="ui-table-wrap">
            <table class="ui-table">
                <thead>
                    <tr>
                        <th><?= e(t('name')) ?></th>
                        <th><?= e(t('staff_role')) ?></th>
                        <th><?= e(t('type')) ?></th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($people as $person): ?>
                        <tr>
                            <td data-label="<?= e(t('name')) ?>">
                                <a href="<?= e(url('/staff/' . $person['id'])) ?>"><strong><?= e((string) $person['name']) ?></strong></a>
                            </td>
                            <td data-label="<?= e(t('staff_role')) ?>">
                                <span class="orders-badge is-blue"><?= e(t('role_user_' . (string) $person['role'])) ?></span>
                            </td>
                            <td data-label="<?= e(t('type')) ?>">
                                <span class="orders-badge is-<?= $person['active'] ? 'success' : 'orange' ?>">
                                    <?= e($person['active'] ? t('staff_active') : t('staff_inactive')) ?>
                                </span>
                            </td>
                            <td data-label="">
                                <a class="btn btn-small btn-quiet" href="<?= e(url('/staff/' . $person['id'])) ?>"><?= e(t('view')) ?></a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>