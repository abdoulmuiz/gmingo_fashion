<?php
/** @var array $errors */
/** @var array $old */
/** @var list<array> $customers */
$title = t('new_group');
$back_href = '/groups';
$back_label = t('groups');
$subtitle = null;
$actions_html = '';
require __DIR__ . '/../partials/ui_page_header.php';
?>
<section class="card form-sheet">
    <form method="post" action="<?= e(url('/groups')) ?>">
        <?= csrf_field() ?>
        <div class="form-sections">
            <div class="form-grid">
                <div class="span-2">
                    <label class="ui-label" for="field-name"><?= e(t('group_name')) ?> *</label>
                    <input id="field-name" name="name" value="<?= e((string) ($old['name'] ?? '')) ?>" required>
                </div>
                <div>
                    <label class="ui-label" for="field-event_date"><?= e(t('event_date')) ?></label>
                    <input id="field-event_date" type="date" name="event_date" value="<?= e((string) ($old['event_date'] ?? '')) ?>">
                </div>
                <div>
                    <label class="ui-label" for="field-pay_mode"><?= e(t('payer_mode')) ?></label>
                    <select id="field-pay_mode" name="pay_mode">
                        <option value="each_pays" <?= (string) ($old['pay_mode'] ?? '') === 'each_pays' ? 'selected' : '' ?>><?= e(t('each_pays')) ?></option>
                        <option value="one_pays" <?= (string) ($old['pay_mode'] ?? '') === 'one_pays' ? 'selected' : '' ?>><?= e(t('one_pays')) ?></option>
                    </select>
                </div>
                <div class="span-2">
                    <label class="ui-label" for="field-payer_customer_id"><?= e(t('paying_customer')) ?></label>
                    <select id="field-payer_customer_id" name="payer_customer_id">
                        <option value=""><?= e(t('paying_customer')) ?></option>
                        <?php foreach ($customers as $customer): ?>
                            <option value="<?= e($customer['id']) ?>" <?= (string) ($old['payer_customer_id'] ?? '') === $customer['id'] ? 'selected' : '' ?>><?= e($customer['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </div>
        <?php if ($errors !== []): ?>
            <?php
            $page_toasts = is_array($page_toasts ?? null) ? $page_toasts : [];
            $page_toasts[] = ['type' => 'error', 'message' => t((string) array_key_first($errors))];
            ?>
        <?php endif; ?>
        <div class="form-sheet-footer">
            <a class="btn btn-quiet" href="<?= e(url('/groups')) ?>"><?= e(t('cancel')) ?></a>
            <button class="btn" type="submit"><?= e(t('save')) ?></button>
        </div>
    </form>
</section>