<?php
/** @var array $expenses */
/** @var array $errors */
/** @var array $old */
?>
<section class="card">
    <h1><?= e(t('expenses')) ?></h1>
    <p class="meta"><?= e(t('expenses_help')) ?></p>
    <form method="post" action="<?= e(url('/expenses')) ?>" class="stack">
        <?= csrf_field() ?>
        <label><?= e(t('expense_category')) ?>
            <input name="category" value="<?= e((string) ($old['category'] ?? '')) ?>" required>
        </label>
        <?php if (isset($errors['category'])): ?><p class="error"><?= e(t($errors['category'])) ?></p><?php endif; ?>
        <label><?= e(t('amount_kobo')) ?>
            <input name="amount_kobo" value="<?= e((string) ($old['amount_kobo'] ?? '')) ?>" required>
        </label>
        <label><?= e(t('spent_on')) ?>
            <input name="spent_on" type="date" value="<?= e((string) ($old['spent_on'] ?? app_today())) ?>" required>
        </label>
        <label><?= e(t('note')) ?>
            <input name="note" value="<?= e((string) ($old['note'] ?? '')) ?>">
        </label>
        <button class="btn" type="submit"><?= e(t('expense_add')) ?></button>
    </form>
</section>
<section class="card">
    <h2><?= e(t('expenses_recent')) ?></h2>
    <?php if ($expenses === []): ?>
        <p><?= e(t('expenses_empty')) ?></p>
    <?php else: ?>
        <?php foreach ($expenses as $expense): ?>
            <div class="line">
                <strong><?= e($expense['category']) ?></strong>
                <span><?= e((string) $expense['amount_kobo']) ?> kobo</span>
                <span><?= e($expense['spent_on']) ?></span>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</section>
