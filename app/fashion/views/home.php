<section class="hero home-hero">
    <p class="eyebrow"><?= e(t('app_kicker')) ?></p>
    <h1><?= e($greeting) ?></h1>
    <p><?= e(t('home_title')) ?></p>
</section>
<?php if ($empty): ?>
    <section class="card">
        <p><?= e(t('switch_on_module')) ?></p>
        <?php if (can_access('/settings')): ?>
            <p><a href="<?= e(url('/settings')) ?>"><?= e(t('settings')) ?></a></p>
        <?php endif; ?>
    </section>
<?php endif; ?>
<?php if ($cards !== []): ?>
    <section class="stats">
        <?php foreach ($cards as $card): ?>
            <div>
                <span><?php if (($card['href'] ?? '') !== ''): ?><a href="<?= e(url((string) $card['href'])) ?>"><?= e($card['label']) ?></a><?php else: ?><?= e($card['label']) ?><?php endif; ?></span>
                <strong><?= e($card['value']) ?></strong>
                <?php if ($card['note'] !== ''): ?>
                    <span><?= e($card['note']) ?></span>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </section>
<?php endif; ?>
<?php if ($orders_on): ?>
    <section class="card">
        <h2><?= e(t('needs_attention')) ?></h2>
        <?php if ($attention === []): ?>
            <p><?= e(t('nothing_urgent')) ?></p>
        <?php endif; ?>
        <?php foreach ($attention as $order): ?>
            <a class="line" href="<?= e(url('/orders/' . $order['id'])) ?>">
                <strong><?= e($order['order_number']) ?></strong>
                <span><?= e($order['customer_name']) ?></span>
                <span><?= e($order['due_date']) ?></span>
            </a>
            <?php if ($order['urgent'] || $order['overdue']): ?>
                <p class="meta">
                    <?php if ($order['overdue']): ?><?= e(t('overdue')) ?><?php endif; ?>
                    <?php if ($order['urgent'] && $order['overdue']): ?> · <?php endif; ?>
                    <?php if ($order['urgent']): ?><?= e(t('urgent')) ?><?php endif; ?>
                </p>
            <?php endif; ?>
        <?php endforeach; ?>
    </section>
<?php endif; ?>
