<?php
/** @var array $expenses */
/** @var array $errors */
/** @var array $old */
$title = t('expenses');
$back_href = null;
$subtitle = t('expenses_help');
$actions_html = '';
require __DIR__ . '/../partials/ui_page_header.php';

$expenseTotal = 0;
foreach ($expenses as $expense) {
    $expenseTotal += (int) $expense['amount_kobo'];
}
?>
<section class="dash-kpis" aria-label="<?= e(t('summary')) ?>">
    <article class="dash-kpi dash-kpi-orange">
        <span class="dash-kpi-top">
            <span class="dash-kpi-icon" aria-hidden="true"><?= ui_icon('wallet', 'ui-icon') ?></span>
            <span class="dash-kpi-body">
                <span class="dash-kpi-label"><?= e(t('expenses_recent')) ?></span>
                <strong class="dash-kpi-value"><?= e((string) count($expenses)) ?></strong>
            </span>
        </span>
    </article>
    <article class="dash-kpi dash-kpi-red">
        <span class="dash-kpi-top">
            <span class="dash-kpi-body">
                <span class="dash-kpi-label"><?= e(t('total')) ?></span>
                <strong class="dash-kpi-value"><?= e(money($expenseTotal)) ?></strong>
            </span>
        </span>
    </article>
</section>

<section class="card form-sheet" style="margin-top:1rem">
    <h2><?= e(t('expense_add')) ?></h2>
    <form method="post" action="<?= e(url('/expenses')) ?>" class="form-grid">
        <?= csrf_field() ?>
        <label><?= e(t('expense_category')) ?>
            <input name="category" value="<?= e((string) ($old['category'] ?? '')) ?>" required>
        </label>
        <?php if (isset($errors['category'])): ?><p class="ui-error"><?= e(t($errors['category'])) ?></p><?php endif; ?>
        <label><?= e(t('amount_kobo')) ?>
            <input name="amount_kobo" value="<?= e((string) ($old['amount_kobo'] ?? '')) ?>" required>
        </label>
        <label><?= e(t('spent_on')) ?>
            <input name="spent_on" type="date" value="<?= e((string) ($old['spent_on'] ?? app_today())) ?>" required>
        </label>
        <label><?= e(t('note')) ?>
            <input name="note" value="<?= e((string) ($old['note'] ?? '')) ?>">
        </label>
        <div class="span-2">
            <button class="btn" type="submit"><?= e(t('expense_add')) ?></button>
        </div>
    </form>
</section>

<section class="card" style="margin-top:1rem">
    <h2><?= e(t('expenses_recent')) ?></h2>
    <?php if ($expenses === []): ?>
        <p><?= e(t('expenses_empty')) ?></p>
    <?php else: ?>
        <div class="ui-table-wrap">
            <table class="ui-table">
                <thead>
                    <tr>
                        <th><?= e(t('expense_category')) ?></th>
                        <th><?= e(t('spent_on')) ?></th>
                        <th class="is-num"><?= e(t('total')) ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($expenses as $expense): ?>
                        <tr>
                            <td data-label="<?= e(t('expense_category')) ?>"><strong><?= e($expense['category']) ?></strong></td>
                            <td data-label="<?= e(t('spent_on')) ?>"><?= e($expense['spent_on']) ?></td>
                            <td class="is-num" data-label="<?= e(t('total')) ?>"><?= e(money((int) $expense['amount_kobo'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
