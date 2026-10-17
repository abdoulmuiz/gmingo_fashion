<form method="post" action="<?= e(url('/stock/' . $item_id . '/move')) ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="back" value="<?= e($back) ?>">
    <input type="hidden" name="step" value="plus">
    <button class="btn btn-small" type="submit"><?= e(t('stock_plus')) ?></button>
</form>
<form method="post" action="<?= e(url('/stock/' . $item_id . '/move')) ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="back" value="<?= e($back) ?>">
    <input type="hidden" name="step" value="minus">
    <button class="btn btn-small" type="submit"><?= e(t('stock_minus')) ?></button>
</form>
<form method="post" action="<?= e(url('/stock/' . $item_id . '/move')) ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="back" value="<?= e($back) ?>">
    <label><?= e(t('stock_custom_amount')) ?>
        <input name="amount" inputmode="decimal" value="">
    </label>
    <label><?= e(t('stock_reason')) ?>
        <select name="reason">
            <?php foreach (stock_reasons() as $reason): ?>
                <option value="<?= e($reason) ?>"><?= e(t('stock_reason_' . $reason)) ?></option>
            <?php endforeach; ?>
        </select>
    </label>
    <label><?= e(t('stock_note')) ?>
        <input name="note" maxlength="500" value="">
    </label>
    <label><?= e(t('stock_order')) ?>
        <input name="order_id" value="">
    </label>
    <button class="btn btn-small" type="submit"><?= e(t('save')) ?></button>
</form>
