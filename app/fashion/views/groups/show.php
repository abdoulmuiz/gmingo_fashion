<section class="card">
    <h1><?= e($group['name']) ?></h1>
    <p class="meta"><?= e(t($group['pay_mode'])) ?></p>
    <?php if ($group['event_date'] !== null): ?>
        <p><?= e(t('event_date')) ?> <?= e(date('j M Y', strtotime((string) $group['event_date']))) ?></p>
    <?php endif; ?>
    <?php if ($group['pay_mode'] === 'one_pays' && $group['payer_name'] !== ''): ?>
        <p><?= e(t('paying_customer')) ?>: <?= e($group['payer_name']) ?></p>
    <?php endif; ?>
    <div class="stats">
        <div><span><?= e(t('group_total')) ?></span><strong><?= e(money((int) $total_kobo)) ?></strong></div>
        <div><span><?= e(t('group_paid')) ?></span><strong><?= e(money((int) $paid_kobo)) ?></strong></div>
        <div><span><?= e(t('group_balance')) ?></span><strong><?= e(money((int) $balance_kobo)) ?></strong></div>
    </div>
    <?php if ($group['pay_mode'] === 'each_pays'): ?>
        <p><?= e(t('group_each_pays_note')) ?></p>
    <?php endif; ?>
    <?php if ($finance && $group['pay_mode'] === 'one_pays'): ?>
        <form method="post" action="<?= e(url('/groups/' . $group['id'] . '/pay')) ?>">
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
    <?php endif; ?>
</section>
<section class="card">
    <h2><?= e(t('orders')) ?></h2>
    <?php if ($members === []): ?>
        <p><?= e(t('no_members')) ?></p>
    <?php endif; ?>
    <?php foreach ($members as $member): ?>
        <a class="line" href="<?= e(url('/orders/' . $member['id'])) ?>">
            <strong><?= e($member['order_number']) ?></strong>
            <span><?= e($member['customer_name']) ?></span>
        </a>
        <p class="meta">
            <?= e(t('total')) ?> <?= e(money((int) $member['total_kobo'])) ?>
            · <?= e(t('amount_paid')) ?> <?= e(money((int) $member['paid_kobo'])) ?>
            · <?= e(t('balance')) ?> <?= e(money((int) $member['balance_kobo'])) ?>
        </p>
    <?php endforeach; ?>
    <?php if ($has_previous || $has_next): ?>
        <nav class="pager" aria-label="<?= e(t('orders')) ?>">
            <?php if ($has_previous): ?>
                <a href="<?= e(url('/groups/' . $group['id'] . '?page=' . (string) ($page - 1))) ?>"><?= e(t('previous')) ?></a>
            <?php endif; ?>
            <?php if ($has_next): ?>
                <a href="<?= e(url('/groups/' . $group['id'] . '?page=' . (string) ($page + 1))) ?>"><?= e(t('next')) ?></a>
            <?php endif; ?>
        </nav>
    <?php endif; ?>
</section>
<?php if ($payments !== []): ?>
    <section class="card">
        <h2><?= e(t('group_payment')) ?></h2>
        <?php foreach ($payments as $payment): ?>
            <div class="line">
                <strong><?= e(money((int) $payment['amount_kobo'])) ?></strong>
                <span><?= e(t($payment['method'] === 'cash' ? 'cash' : 'transfer')) ?></span>
                <span><?= e(date('j M Y H:i', strtotime((string) $payment['created_at']))) ?></span>
            </div>
            <?php if ($payment['reversed']): ?>
                <p class="meta"><?= e(t('reversed')) ?></p>
            <?php endif; ?>
            <?php if ($is_owner && !$payment['reversed']): ?>
                <form method="post" action="<?= e(url('/groups/' . $group['id'] . '/payments/' . $payment['payment_id'] . '/reverse')) ?>">
                    <?= csrf_field() ?>
                    <label><?= e(t('reversal_reason')) ?>
                        <input name="reason" maxlength="500" required>
                    </label>
                    <button class="btn btn-quiet btn-small" type="submit"><?= e(t('reverse')) ?></button>
                </form>
            <?php endif; ?>
        <?php endforeach; ?>
    </section>
<?php endif; ?>
<section class="card">
    <h2><?= e(t('payer_mode')) ?></h2>
    <form method="post" action="<?= e(url('/groups/' . $group['id'])) ?>">
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
