<?php
/** @var array $suppliers */
/** @var array $orders */
/** @var array $stock_items */
/** @var array $errors */
/** @var array $old */
?>
<section class="card">
    <h1><?= e(t('purchasing')) ?></h1>
    <p class="meta"><?= e(t('purchasing_help')) ?></p>
</section>
<section class="card">
    <h2><?= e(t('supplier_add')) ?></h2>
    <form method="post" action="<?= e(url('/purchasing/suppliers')) ?>" class="stack">
        <?= csrf_field() ?>
        <label><?= e(t('name')) ?>
            <input name="name" value="<?= e((string) ($old['name'] ?? '')) ?>" required>
        </label>
        <label><?= e(t('phone')) ?>
            <input name="phone" value="<?= e((string) ($old['phone'] ?? '')) ?>">
        </label>
        <label><?= e(t('email')) ?>
            <input name="email" value="<?= e((string) ($old['email'] ?? '')) ?>">
        </label>
        <label><?= e(t('note')) ?>
            <input name="note" value="<?= e((string) ($old['note'] ?? '')) ?>">
        </label>
        <button class="btn" type="submit"><?= e(t('supplier_add')) ?></button>
    </form>
    <?php foreach ($suppliers as $supplier): ?>
        <p class="line"><strong><?= e($supplier['name']) ?></strong> <span><?= e($supplier['phone']) ?></span></p>
    <?php endforeach; ?>
</section>
<section class="card">
    <h2><?= e(t('purchase_order_add')) ?></h2>
    <form method="post" action="<?= e(url('/purchasing/orders')) ?>" class="stack">
        <?= csrf_field() ?>
        <label><?= e(t('supplier')) ?>
            <select name="supplier_id" required>
                <option value=""><?= e(t('choose')) ?></option>
                <?php foreach ($suppliers as $supplier): ?>
                    <?php if (!$supplier['active']) { continue; } ?>
                    <option value="<?= e($supplier['id']) ?>"><?= e($supplier['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label><?= e(t('stock')) ?>
            <select name="stock_item_id" required>
                <option value=""><?= e(t('choose')) ?></option>
                <?php foreach ($stock_items as $item): ?>
                    <option value="<?= e($item['id']) ?>"><?= e($item['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label><?= e(t('quantity')) ?>
            <input name="quantity" value="<?= e((string) ($old['quantity'] ?? '1')) ?>" required>
        </label>
        <label><?= e(t('unit_cost_kobo')) ?>
            <input name="unit_cost_kobo" value="<?= e((string) ($old['unit_cost_kobo'] ?? '0')) ?>" required>
        </label>
        <label><?= e(t('note')) ?>
            <input name="note" value="<?= e((string) ($old['note'] ?? '')) ?>">
        </label>
        <button class="btn" type="submit"><?= e(t('purchase_order_add')) ?></button>
    </form>
</section>
<section class="card">
    <h2><?= e(t('purchase_orders')) ?></h2>
    <?php if ($orders === []): ?>
        <p><?= e(t('purchase_orders_empty')) ?></p>
    <?php else: ?>
        <?php foreach ($orders as $order): ?>
            <div class="line">
                <strong><?= e($order['supplier_name']) ?></strong>
                <span><?= e($order['status']) ?></span>
                <span><?= e($order['ordered_on']) ?></span>
                <?php if ($order['status'] !== 'received'): ?>
                    <form method="post" action="<?= e(url('/purchasing/orders/' . $order['id'] . '/receive')) ?>">
                        <?= csrf_field() ?>
                        <button class="btn btn-small" type="submit"><?= e(t('purchase_order_receive')) ?></button>
                    </form>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</section>
