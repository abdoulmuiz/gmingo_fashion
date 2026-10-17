<section class="card">
    <p><a href="<?= e(url('/orders/' . $order['id'])) ?>"><?= e($order['order_number']) ?></a></p>
    <h1><?= e(t('fabric_history')) ?></h1>
    <?php if ($rows === []): ?>
        <p><?= e(t('fabric_history_empty')) ?></p>
    <?php endif; ?>
    <?php foreach ($rows as $row): ?>
        <div class="line">
            <strong><?= e(t($row['field_name'] === 'used' ? 'yards_used' : 'yards_received')) ?></strong>
            <span><?= e($row['user_name']) ?></span>
            <span><?= e(date('j M Y H:i', strtotime((string) $row['created_at']))) ?></span>
        </div>
        <p>
            <?= e(t('fabric_old')) ?>:
            <?= e($row['old_hundredths'] === null ? t('fabric_not_set') : yards_format((int) $row['old_hundredths'])) ?>
            · <?= e(t('fabric_new')) ?>:
            <?= e(yards_format((int) $row['new_hundredths'])) ?>
        </p>
        <?php if ($row['note'] !== ''): ?>
            <p><?= e($row['note']) ?></p>
        <?php endif; ?>
    <?php endforeach; ?>
</section>
