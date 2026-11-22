<?php
/** @var array $group */
/** @var list<array> $members */
/** @var list<array> $payments */
/** @var list<array> $customers */
/** @var int $total_kobo */
/** @var int $paid_kobo */
/** @var int $balance_kobo */
/** @var bool $finance */
/** @var bool $is_owner */
/** @var int $page */
/** @var bool $has_previous */
/** @var bool $has_next */
$balanceTone = $balance_kobo > 0 ? 'orange' : 'success';
$title = $group['name'];
$back_href = '/groups';
$back_label = t('groups');
$subtitle = e(t($group['pay_mode']));
if ($group['event_date'] !== null) {
    $subtitle .= ' · ' . e(t('event_date')) . ' ' . e(date('j M Y', strtotime((string) $group['event_date'])));
}
$actions_html = '';
require __DIR__ . '/../partials/ui_page_header.php';
?>
<section class="dash-kpis" aria-label="<?= e(t('summary')) ?>">
    <article class="dash-kpi dash-kpi-blue">
        <span class="dash-kpi-top">
            <span class="dash-kpi-body">
                <span class="dash-kpi-label"><?= e(t('group_total')) ?></span>
                <strong class="dash-kpi-value"><?= e(money((int) $total_kobo)) ?></strong>
            </span>
        </span>
    </article>
    <article class="dash-kpi dash-kpi-green">
        <span class="dash-kpi-top">
            <span class="dash-kpi-body">
                <span class="dash-kpi-label"><?= e(t('group_paid')) ?></span>
                <strong class="dash-kpi-value"><?= e(money((int) $paid_kobo)) ?></strong>
            </span>
        </span>
    </article>
    <article class="dash-kpi dash-kpi-<?= e($balanceTone) ?>">
        <span class="dash-kpi-top">
            <span class="dash-kpi-body">
                <span class="dash-kpi-label"><?= e(t('group_balance')) ?></span>
                <strong class="dash-kpi-value"><?= e(money((int) $balance_kobo)) ?></strong>
            </span>
        </span>
    </article>
</section>

<?php if ($group['pay_mode'] === 'one_pays' && $group['payer_name'] !== ''): ?>
    <p class="meta" style="margin-top:0.75rem"><?= e(t('paying_customer')) ?>: <strong><?= e($group['payer_name']) ?></strong></p>
<?php endif; ?>
<?php if ($group['pay_mode'] === 'each_pays'): ?>
    <p class="meta" style="margin-top:0.75rem"><?= e(t('group_each_pays_note')) ?></p>
<?php endif; ?>

<div class="detail-layout" style="margin-top:1rem">
    <div class="detail-main">
        <section class="card">
            <h2><?= e(t('orders')) ?></h2>
            <?php if ($members === []): ?>
                <p><?= e(t('no_members')) ?></p>
            <?php else: ?>
                <div class="ui-table-wrap">
                    <table class="ui-table">
                        <thead>
                            <tr>
                                <th><?= e(t('orders')) ?></th>
                                <th><?= e(t('customer')) ?></th>
                                <th class="is-num"><?= e(t('total')) ?></th>
                                <th class="is-num"><?= e(t('amount_paid')) ?></th>
                                <th class="is-num"><?= e(t('balance')) ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($members as $member): ?>
                                <tr>
                                    <td data-label="<?= e(t('orders')) ?>">
                                        <a href="<?= e(url('/orders/' . $member['id'])) ?>"><strong><?= e($member['order_number']) ?></strong></a>
                                    </td>
                                    <td data-label="<?= e(t('customer')) ?>"><?= e($member['customer_name']) ?></td>
                                    <td class="is-num" data-label="<?= e(t('total')) ?>"><?= e(money((int) $member['total_kobo'])) ?></td>
                                    <td class="is-num" data-label="<?= e(t('amount_paid')) ?>"><?= e(money((int) $member['paid_kobo'])) ?></td>
                                    <td class="is-num" data-label="<?= e(t('balance')) ?>"><?= e(money((int) $member['balance_kobo'])) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php if ($has_previous || $has_next): ?>
                    <nav class="pager" aria-label="<?= e(t('orders')) ?>">
                        <?php if ($has_previous): ?>
                            <a class="btn btn-small btn-quiet" href="<?= e(url('/groups/' . $group['id'] . '?page=' . (string) ($page - 1))) ?>"><?= e(t('previous')) ?></a>
                        <?php endif; ?>
                        <?php if ($has_next): ?>
                            <a class="btn btn-small btn-quiet" href="<?= e(url('/groups/' . $group['id'] . '?page=' . (string) ($page + 1))) ?>"><?= e(t('next')) ?></a>
                        <?php endif; ?>
                    </nav>
                <?php endif; ?>
            <?php endif; ?>
        </section>

        <?php if ($payments !== []): ?>
            <section class="card">
                <h2><?= e(t('group_payment')) ?></h2>
                <div class="ui-table-wrap">
                    <table class="ui-table">
                        <thead>
                            <tr>
                                <th class="is-num"><?= e(t('amount')) ?></th>
                                <th><?= e(t('method')) ?></th>
                                <th><?= e(t('date')) ?></th>
                                <th><?= e(t('type')) ?></th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($payments as $payment): ?>
                                <tr>
                                    <td class="is-num" data-label="<?= e(t('amount')) ?>"><strong><?= e(money((int) $payment['amount_kobo'])) ?></strong></td>
                                    <td data-label="<?= e(t('method')) ?>"><?= e(t($payment['method'] === 'cash' ? 'cash' : 'transfer')) ?></td>
                                    <td data-label="<?= e(t('date')) ?>"><?= e(date('j M Y H:i', strtotime((string) $payment['created_at']))) ?></td>
                                    <td data-label="<?= e(t('type')) ?>">
                                        <?php if ($payment['reversed']): ?>
                                            <span class="orders-badge is-orange"><?= e(t('reversed')) ?></span>
                                        <?php else: ?>
                                            <span class="orders-badge is-success"><?= e(t('group_payment')) ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td data-label="">
                                        <?php if ($is_owner && !$payment['reversed']): ?>
                                            <form method="post" action="<?= e(url('/groups/' . $group['id'] . '/payments/' . $payment['payment_id'] . '/reverse')) ?>" class="stack">
                                                <?= csrf_field() ?>
                                                <label><?= e(t('reversal_reason')) ?>
                                                    <input name="reason" maxlength="500" required>
                                                </label>
                                                <button class="btn btn-quiet btn-small" type="submit"><?= e(t('reverse')) ?></button>
                                            </form>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        <?php endif; ?>
    </div>

    <aside class="detail-side">
        <?php if ($finance && $group['pay_mode'] === 'one_pays'): ?>
            <section class="card">
                <h2><?= e(t('pay_for_group')) ?></h2>
                <form method="post" action="<?= e(url('/groups/' . $group['id'] . '/pay')) ?>" class="stack">
                    <?= csrf_field() ?>
                    <label><?= e(t('amount')) ?>
                        <input name="amount" inputmode="decimal" value="">
                    </label>
                    <label><?= e(t('method')) ?>
                        <select name="method">
                            <option value="cash"><?= e(t('cash')) ?></option>
                            <option value="transfer"><?= e(t('transfer')) ?></option>
                        </select>
                    </label>
                    <button class="btn" type="submit"><?= e(t('pay_for_group')) ?></button>
                </form>
            </section>
        <?php endif; ?>

        <section class="card">
            <h2><?= e(t('payer_mode')) ?></h2>
            <form method="post" action="<?= e(url('/groups/' . $group['id'])) ?>" class="stack">
                <?= csrf_field() ?>
                <label><?= e(t('group_name')) ?>
                    <input name="name" value="<?= e($group['name']) ?>" required>
                </label>
                <label><?= e(t('event_date')) ?>
                    <input type="date" name="event_date" value="<?= e((string) ($group['event_date'] ?? '')) ?>">
                </label>
                <label><?= e(t('payer_mode')) ?>
                    <select name="pay_mode">
                        <option value="each_pays" <?= $group['pay_mode'] === 'each_pays' ? 'selected' : '' ?>><?= e(t('each_pays')) ?></option>
                        <option value="one_pays" <?= $group['pay_mode'] === 'one_pays' ? 'selected' : '' ?>><?= e(t('one_pays')) ?></option>
                    </select>
                </label>
                <label><?= e(t('paying_customer')) ?>
                    <select name="payer_customer_id">
                        <option value=""><?= e(t('paying_customer')) ?></option>
                        <?php foreach ($customers as $customer): ?>
                            <option value="<?= e($customer['id']) ?>" <?= (string) ($group['payer_customer_id'] ?? '') === $customer['id'] ? 'selected' : '' ?>><?= e($customer['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <button class="btn" type="submit"><?= e(t('save')) ?></button>
            </form>
        </section>
    </aside>
</div>