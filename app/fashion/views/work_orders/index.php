<?php
/** @var array $orders */
/** @var array $statuses */
/** @var array $errors */
/** @var array $old */
?>
<section class="card">
    <h1><?= e(t('work_orders')) ?></h1>
    <p class="meta"><?= e(t('work_orders_help')) ?></p>
    <form method="post" action="<?= e(url('/work-orders')) ?>" class="stack">
        <?= csrf_field() ?>
        <label><?= e(t('title')) ?>
            <input name="title" value="<?= e((string) ($old['title'] ?? '')) ?>" required>
        </label>
        <label><?= e(t('quantity')) ?>
            <input name="quantity" type="number" min="1" value="<?= e((string) ($old['quantity'] ?? '1')) ?>" required>
        </label>
        <label><?= e(t('due_date')) ?>
            <input name="due_on" type="date" value="<?= e((string) ($old['due_on'] ?? '')) ?>">
        </label>
        <label><?= e(t('note')) ?>
            <input name="note" value="<?= e((string) ($old['note'] ?? '')) ?>">
        </label>
        <button class="btn" type="submit"><?= e(t('work_order_add')) ?></button>
    </form>
</section>
<section class="card">
    <h2><?= e(t('work_orders_list')) ?></h2>
    <?php if ($orders === []): ?>
        <p><?= e(t('work_orders_empty')) ?></p>
    <?php else: ?>
        <?php foreach ($orders as $order): ?>
            <div class="line">
                <strong><?= e($order['title']) ?></strong>
                <span>×<?= e((string) $order['quantity']) ?></span>
                <span><?= e(t('work_order_status_' . $order['status'])) ?></span>
                <form method="post" action="<?= e(url('/work-orders/' . $order['id'] . '/status')) ?>">
                    <?= csrf_field() ?>
                    <select name="status">
                        <?php foreach ($statuses as $status): ?>
                            <option value="<?= e($status) ?>" <?= $order['status'] === $status ? 'selected' : '' ?>><?= e(t('work_order_status_' . $status)) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button class="btn btn-small" type="submit"><?= e(t('save')) ?></button>
                </form>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</section>
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
