<section class="card">
    <div class="split">
        <h1><?= e(t('orders')) ?></h1>
        <span class="actions">
            <a class="btn btn-small btn-quiet" href="<?= e(url('/calendar')) ?>"><?= e(t('calendar')) ?></a>
            <a class="btn btn-small" href="<?= e(url('/orders/new')) ?>"><?= e(t('new_order')) ?></a>
        </span>
    </div>
    <nav class="tabs" aria-label="<?= e(t('orders')) ?>">
        <?php foreach (order_tabs() as $tab): ?>
            <?php
            $label = $tab === 'unpaid' ? 'tab_unpaid' : $tab;
            $href = '/orders?tab=' . $tab;
            if ($query !== '') {
                $href .= '&q=' . rawurlencode($query);
            }
            ?>
            <a class="<?= $filter === $tab ? 'active' : '' ?>" href="<?= e(url($href)) ?>"><?= e(t($label)) ?></a>
        <?php endforeach; ?>
    </nav>
    <form method="get" action="<?= e(url('/orders')) ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="tab" value="<?= e($filter) ?>">
        <label><?= e(t('search')) ?>
            <input name="q" value="<?= e($query) ?>">
        </label>
        <button class="btn" type="submit"><?= e(t('search')) ?></button>
    </form>
</section>
<?php if ($orders === []): ?>
    <section class="card"><p><?= e(t('no_orders')) ?></p></section>
<?php endif; ?>
<?php foreach ($orders as $order): ?>
    <a class="card link-card" href="<?= e(url('/orders/' . $order['id'])) ?>">
        <strong><?= e($order['order_number']) ?></strong>
        <span class="tag"><?= e(t($order['paid'] ? 'paid' : 'unpaid')) ?></span>
        <?php if ($order['urgent']): ?><span class="tag"><?= e(t('urgent')) ?></span><?php endif; ?>
        <?php if ($order['fabric_badge']): ?><span class="tag"><?= e(t('fabric_left', ['yards' => yards_format((int) $order['yards_left_hundredths'])])) ?></span><?php endif; ?>
        <p class="meta"><?= e($order['customer_name']) ?> · <?= e($order['due_date']) ?></p>
    </a>
<?php endforeach; ?>
<?php if ($has_previous || $has_next): ?>
    <nav class="pager" aria-label="<?= e(t('orders')) ?>">
        <?php if ($has_previous): ?>
            <a href="<?= e(url($previous_path)) ?>"><?= e(t('previous')) ?></a>
        <?php endif; ?>
        <?php if ($has_next): ?>
            <a href="<?= e(url($next_path)) ?>"><?= e(t('next')) ?></a>
        <?php endif; ?>
    </nav>
<?php endif; ?>
