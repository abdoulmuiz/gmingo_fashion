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
