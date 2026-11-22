<?php
/** @var array $orders */
/** @var array $statuses */
/** @var array $errors */
/** @var array $old */
$title = t('work_orders');
$back_href = null;
$subtitle = t('work_orders_help');
$actions_html = '';
require __DIR__ . '/../partials/ui_page_header.php';

$statusCounts = [];
foreach ($orders as $order) {
    $key = (string) ($order['status'] ?? '');
    $statusCounts[$key] = ($statusCounts[$key] ?? 0) + 1;
}
?>
<section class="dash-kpis" aria-label="<?= e(t('summary')) ?>">
    <article class="dash-kpi dash-kpi-blue">
        <span class="dash-kpi-top">
            <span class="dash-kpi-icon" aria-hidden="true"><?= ui_icon('scissors', 'ui-icon') ?></span>
            <span class="dash-kpi-body">
                <span class="dash-kpi-label"><?= e(t('work_orders_list')) ?></span>
                <strong class="dash-kpi-value"><?= e((string) count($orders)) ?></strong>
            </span>
        </span>
    </article>
    <?php foreach (array_slice($statusCounts, 0, 3, true) as $status => $count): ?>
        <article class="dash-kpi dash-kpi-purple">
            <span class="dash-kpi-top">
                <span class="dash-kpi-body">
                    <span class="dash-kpi-label"><?= e(t('work_order_status_' . $status)) ?></span>
                    <strong class="dash-kpi-value"><?= e((string) $count) ?></strong>
                </span>
            </span>
        </article>
    <?php endforeach; ?>
</section>

<div class="detail-layout" style="margin-top:1rem">
    <div class="detail-main">
        <section class="card">
            <h2><?= e(t('work_order_add')) ?></h2>
            <form method="post" action="<?= e(url('/work-orders')) ?>" class="form-grid">
                <?= csrf_field() ?>
                <label class="span-2"><?= e(t('title')) ?>
                    <input name="title" value="<?= e((string) ($old['title'] ?? '')) ?>" required>
                </label>
                <label><?= e(t('quantity')) ?>
                    <input name="quantity" type="number" min="1" value="<?= e((string) ($old['quantity'] ?? '1')) ?>" required>
                </label>
                <label><?= e(t('due_date')) ?>
                    <input name="due_on" type="date" value="<?= e((string) ($old['due_on'] ?? '')) ?>">
                </label>
                <label class="span-2"><?= e(t('note')) ?>
                    <input name="note" value="<?= e((string) ($old['note'] ?? '')) ?>">
                </label>
                <div class="span-2">
                    <button class="btn" type="submit"><?= e(t('work_order_add')) ?></button>
                </div>
            </form>
        </section>

        <section class="card">
            <h2><?= e(t('work_orders_list')) ?></h2>
            <?php if ($orders === []): ?>
                <p><?= e(t('work_orders_empty')) ?></p>
            <?php else: ?>
                <div class="ui-table-wrap">
                    <table class="ui-table">
                        <thead>
                            <tr>
                                <th><?= e(t('title')) ?></th>
                                <th><?= e(t('quantity')) ?></th>
                                <th><?= e(t('type')) ?></th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($orders as $order): ?>
                                <tr>
                                    <td data-label="<?= e(t('title')) ?>"><strong><?= e($order['title']) ?></strong></td>
                                    <td data-label="<?= e(t('quantity')) ?>">×<?= e((string) $order['quantity']) ?></td>
                                    <td data-label="<?= e(t('type')) ?>">
                                        <span class="orders-badge is-blue"><?= e(t('work_order_status_' . $order['status'])) ?></span>
                                    </td>
                                    <td data-label="">
                                        <form method="post" action="<?= e(url('/work-orders/' . $order['id'] . '/status')) ?>" class="detail-meta-row">
                                            <?= csrf_field() ?>
                                            <select name="status">
                                                <?php foreach ($statuses as $status): ?>
                                                    <option value="<?= e($status) ?>" <?= $order['status'] === $status ? 'selected' : '' ?>><?= e(t('work_order_status_' . $status)) ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                            <button class="btn btn-small" type="submit"><?= e(t('save')) ?></button>
                                        </form>
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
        <section class="card">
            <h2><?= e(t('quality_check')) ?></h2>
            <form method="post" action="<?= e(url('/work-orders/quality')) ?>" class="stack">
                <?= csrf_field() ?>
                <label><?= e(t('work_order_id')) ?>
                    <input name="work_order_id" value="<?= e((string) ($old['work_order_id'] ?? '')) ?>">
                </label>
                <label><?= e(t('quality_result')) ?>
                    <select name="result">
                        <option value="pass"><?= e(t('quality_pass')) ?></option>
                        <option value="fail"><?= e(t('quality_fail')) ?></option>
                    </select>
                </label>
                <label><?= e(t('note')) ?>
                    <input name="reason" value="<?= e((string) ($old['reason'] ?? '')) ?>">
                </label>
                <button class="btn" type="submit"><?= e(t('quality_check_save')) ?></button>
            </form>
        </section>
    </aside>
</div>
