<section class="card">
    <p class="eyebrow"><?= e(t($customer['customer_type'] === 'vip' ? 'vip' : 'regular')) ?></p>
    <h1><?= e($customer['name']) ?></h1>
    <p class="meta"><?= e(t('customer_since')) ?> <?= e(date('j M Y', strtotime((string) $customer['created_at']))) ?></p>
    <div class="stats">
        <div><span><?= e(t('order_count')) ?></span><strong><?= e((string) $customer['order_count']) ?></strong></div>
        <div><span><?= e(t('total_spent')) ?></span><strong><?= e(money((int) $customer['spent_kobo'])) ?></strong></div>
        <div><span><?= e(t('amount_owed')) ?></span><strong><?= e(money((int) $customer['owed_kobo'])) ?></strong></div>
    </div>
    <div class="actions">
        <a class="btn" href="<?= e(tel_href((string) $customer['phone'])) ?>"><?= e(t('call')) ?></a>
        <a class="btn" href="<?= e(whatsapp_href((string) $customer['phone'])) ?>"><?= e(t('whatsapp')) ?></a>
        <?php if (route_defined('GET', '/orders/new')): ?>
            <a class="btn" href="<?= e(url('/orders/new?customer=' . $customer['id'])) ?>"><?= e(t('new_order')) ?></a>
        <?php endif; ?>
    </div>
</section>
<section class="card">
    <h2><?= e(t('order_history')) ?></h2>
    <?php if ($customer['orders'] === []): ?>
        <p><?= e(t('no_orders')) ?></p>
    <?php endif; ?>
    <?php foreach ($customer['orders'] as $order): ?>
        <?php $orderOpen = route_defined('GET', '/orders/{id}'); ?>
        <?php if ($orderOpen): ?><a class="line" href="<?= e(url('/orders/' . $order['id'])) ?>"><?php else: ?><div class="line"><?php endif; ?>
            <strong><?= e($order['order_number']) ?></strong>
            <span><?= e($order['due_date']) ?></span>
            <span><?= e(money((int) $order['balance_kobo'])) ?></span>
        <?php if ($orderOpen): ?></a><?php else: ?></div><?php endif; ?>
    <?php endforeach; ?>
</section>
<?php if (module_enabled('fashion.measurements') && is_array($measure)): ?>
    <section class="card">
        <h2><?= e(t('measurements')) ?></h2>
        <?php if ($measure['garments'] !== []): ?>
            <div class="tabs">
                <?php foreach ($measure['garments'] as $garment): ?>
                    <a class="<?= $garment['id'] === $measure['garment_id'] ? 'active' : '' ?>" href="<?= e(url('/customers/' . $customer['id'] . '?garment=' . $garment['id'])) ?>"><?= e($garment['name']) ?></a>
                <?php endforeach; ?>
            </div>
            <?php if ($measure['placeholder']): ?>
                <p class="meta"><?= e(t('placeholder_note')) ?></p>
            <?php endif; ?>
            <?php if ($measure['fields'] !== []): ?>
                <form method="post" action="<?= e(url('/customers/' . $customer['id'] . '/measurements')) ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="garment_id" value="<?= e($measure['garment_id']) ?>">
                    <?php foreach ($measure['fields'] as $field): ?>
                        <label><?= e((string) $field['name']) ?>
                            <input name="values[<?= e((string) $field['field_key']) ?>]" inputmode="decimal" value="<?= e((string) ($measure['prefill']['values'][(string) $field['field_key']] ?? '')) ?>">
                        </label>
                    <?php endforeach; ?>
                    <label><?= e(t('unit')) ?>
                        <select name="unit">
                            <option value="inches" <?= $measure['prefill']['unit'] === 'inches' ? 'selected' : '' ?>><?= e(t('inches')) ?></option>
                            <option value="cm" <?= $measure['prefill']['unit'] === 'cm' ? 'selected' : '' ?>><?= e(t('cm')) ?></option>
                        </select>
                    </label>
                    <div class="actions">
                        <button class="btn" type="submit" name="action" value="take"><?= e(t('take_measurement')) ?></button>
                        <button class="btn btn-quiet" type="submit" name="action" value="update"><?= e(t('update_measurement')) ?></button>
                    </div>
                </form>
            <?php endif; ?>
        <?php endif; ?>
        <?php if ($measure['latest'] === []): ?>
            <p><?= e(t('no_measurements')) ?></p>
        <?php endif; ?>
        <?php foreach ($measure['latest'] as $version): ?>
            <h2><?= e($version['garment_name']) ?></h2>
            <p class="meta"><?= e(date('j M Y H:i', strtotime((string) $version['created_at']))) ?> · <?= e(t($version['unit'] === 'cm' ? 'cm' : 'inches')) ?></p>
            <?php foreach ($version['lines'] as $line): ?>
                <div class="line">
                    <strong><?= e($line['name']) ?></strong>
                    <span><?= e($line['value']) ?></span>
                </div>
            <?php endforeach; ?>
        <?php endforeach; ?>
        <?php if ($measure['history'] !== []): ?>
            <h2><?= e(t('history')) ?></h2>
            <?php foreach ($measure['history'] as $version): ?>
                <p class="meta"><?= e($version['garment_name']) ?> · <?= e(date('j M Y H:i', strtotime((string) $version['created_at']))) ?> · <?= e(t($version['unit'] === 'cm' ? 'cm' : 'inches')) ?></p>
                <?php foreach ($version['lines'] as $line): ?>
                    <div class="line">
                        <strong><?= e($line['name']) ?></strong>
                        <span><?= e($line['value']) ?></span>
                    </div>
                <?php endforeach; ?>
            <?php endforeach; ?>
        <?php endif; ?>
    </section>
<?php endif; ?>
