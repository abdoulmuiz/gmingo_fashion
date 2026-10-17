<section class="card">
    <h1><?= e(t('new_group')) ?></h1>
    <form method="post" action="<?= e(url('/groups')) ?>">
        <?= csrf_field() ?>
        <label><?= e(t('group_name')) ?>
            <input name="name" value="<?= e((string) ($old['name'] ?? '')) ?>" required>
        </label>
        <label><?= e(t('event_date')) ?>
            <input type="date" name="event_date" value="<?= e((string) ($old['event_date'] ?? '')) ?>">
        </label>
        <label><?= e(t('payer_mode')) ?>
            <select name="pay_mode">
                <option value="each_pays" <?= (string) ($old['pay_mode'] ?? '') === 'each_pays' ? 'selected' : '' ?>><?= e(t('each_pays')) ?></option>
                <option value="one_pays" <?= (string) ($old['pay_mode'] ?? '') === 'one_pays' ? 'selected' : '' ?>><?= e(t('one_pays')) ?></option>
            </select>
        </label>
        <label><?= e(t('paying_customer')) ?>
            <select name="payer_customer_id">
                <option value=""><?= e(t('paying_customer')) ?></option>
                <?php foreach ($customers as $customer): ?>
                    <option value="<?= e($customer['id']) ?>" <?= (string) ($old['payer_customer_id'] ?? '') === $customer['id'] ? 'selected' : '' ?>><?= e($customer['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <?php if ($errors !== []): ?>
            <p class="flash flash-error" role="alert"><?= e(t((string) array_key_first($errors))) ?></p>
        <?php endif; ?>
        <button class="btn" type="submit"><?= e(t('save')) ?></button>
    </form>
</section>
