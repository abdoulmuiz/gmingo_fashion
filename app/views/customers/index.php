<section class="card">
    <div class="split">
        <h1><?= e(t('customers')) ?></h1>
        <a class="btn btn-small" href="<?= e(url('/customers/new')) ?>"><?= e(t('new_customer')) ?></a>
    </div>
    <form method="get" action="<?= e(url('/customers')) ?>">
        <?= csrf_field() ?>
        <label><?= e(t('search')) ?>
            <input name="q" value="<?= e($query) ?>">
        </label>
        <button class="btn" type="submit"><?= e(t('search')) ?></button>
    </form>
</section>
<?php if ($customers === []): ?>
    <section class="card"><p><?= e(t('no_customers')) ?></p></section>
<?php endif; ?>
<?php foreach ($customers as $customer): ?>
    <a class="card link-card" href="<?= e(url('/customers/' . $customer['id'])) ?>">
        <strong><?= e($customer['name']) ?></strong>
        <span class="tag"><?= e(t($customer['customer_type'] === 'vip' ? 'vip' : 'regular')) ?></span>
        <p class="meta"><?= e($customer['phone']) ?></p>
    </a>
<?php endforeach; ?>
