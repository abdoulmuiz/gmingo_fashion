<?php
/** @var list<array> $groups */
/** @var int $page */
/** @var bool $has_previous */
/** @var bool $has_next */
$groupCount = count($groups);
ob_start();
?>
<a class="btn" href="<?= e(url('/groups/new')) ?>">
    <?= ui_icon('plus', 'ui-icon') ?>
    <span><?= e(t('new_group')) ?></span>
</a>
<?php
$actions_html = ob_get_clean();
$title = t('groups');
$back_href = null;
$subtitle = null;
require __DIR__ . '/../partials/ui_page_header.php';
?>
<section class="dash-kpis" aria-label="<?= e(t('summary')) ?>">
    <article class="dash-kpi dash-kpi-blue">
        <span class="dash-kpi-top">
            <span class="dash-kpi-icon" aria-hidden="true"><?= ui_icon('users', 'ui-icon') ?></span>
            <span class="dash-kpi-body">
                <span class="dash-kpi-label"><?= e(t('groups')) ?></span>
                <strong class="dash-kpi-value"><?= e((string) $groupCount) ?></strong>
            </span>
        </span>
    </article>
</section>

<section class="card list-page-shell" style="margin-top:1rem">
    <?php if ($groups === []): ?>
        <?php
        $title = t('no_groups');
        $help = null;
        $icon = 'users';
        $cta_href = '/groups/new';
        $cta_label = t('new_group');
        require __DIR__ . '/../partials/ui_empty_state.php';
        ?>
    <?php else: ?>
        <div class="ui-table-wrap">
            <table class="ui-table">
                <thead>
                    <tr>
                        <th><?= e(t('group_name')) ?></th>
                        <th><?= e(t('payer_mode')) ?></th>
                        <th><?= e(t('event_date')) ?></th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($groups as $group): ?>
                        <tr>
                            <td data-label="<?= e(t('group_name')) ?>">
                                <a href="<?= e(url('/groups/' . $group['id'])) ?>"><strong><?= e($group['name']) ?></strong></a>
                            </td>
                            <td data-label="<?= e(t('payer_mode')) ?>">
                                <span class="orders-badge is-blue"><?= e(t($group['pay_mode'])) ?></span>
                            </td>
                            <td data-label="<?= e(t('event_date')) ?>">
                                <?php if ($group['event_date'] !== null): ?>
                                    <?= e(date('j M Y', strtotime((string) $group['event_date']))) ?>
                                <?php else: ?>
                                    —
                                <?php endif; ?>
                            </td>
                            <td data-label="">
                                <a class="btn btn-small btn-quiet" href="<?= e(url('/groups/' . $group['id'])) ?>"><?= e(t('view')) ?></a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php if ($has_previous || $has_next): ?>
            <nav class="pager" aria-label="<?= e(t('groups')) ?>">
                <?php if ($has_previous): ?>
                    <a class="btn btn-small btn-quiet" href="<?= e(url('/groups?page=' . (string) ($page - 1))) ?>"><?= e(t('previous')) ?></a>
                <?php endif; ?>
                <?php if ($has_next): ?>
                    <a class="btn btn-small btn-quiet" href="<?= e(url('/groups?page=' . (string) ($page + 1))) ?>"><?= e(t('next')) ?></a>
                <?php endif; ?>
            </nav>
        <?php endif; ?>
    <?php endif; ?>
</section>