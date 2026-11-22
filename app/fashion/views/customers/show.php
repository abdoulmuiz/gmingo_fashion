<?php
$isVip = ($customer['customer_type'] ?? '') === 'vip';
$displayName = order_display_customer_name((string) $customer['name']);
ob_start();
?>
<div class="customer-detail-hero">
    <div class="customer-detail-identity">
        <?php $name = $displayName; require __DIR__ . '/../partials/ui_avatar.php'; ?>
        <div>
            <div class="detail-meta-row">
                <span class="orders-badge is-<?= $isVip ? 'orange' : 'blue' ?>"><?= e(t($isVip ? 'vip' : 'regular')) ?></span>
            </div>
            <h1><?= e($displayName) ?></h1>
            <p class="meta"><?= e(t('customer_since')) ?> <?= e(date('j M Y', strtotime((string) $customer['created_at']))) ?></p>
            <p class="meta"><?= e((string) $customer['phone']) ?></p>
        </div>
    </div>
    <div class="ui-page-header-actions">
        <a class="btn btn-quiet" href="<?= e(tel_href((string) $customer['phone'])) ?>">
            <?= ui_icon('phone', 'ui-icon') ?>
            <span><?= e(t('call')) ?></span>
        </a>
        <a class="btn btn-whatsapp" href="<?= e(whatsapp_href((string) $customer['phone'])) ?>">
            <?= ui_icon('message-circle', 'ui-icon') ?>
            <span><?= e(t('whatsapp')) ?></span>
        </a>
        <?php if (route_defined('GET', '/orders/new')): ?>
            <a class="btn" href="<?= e(url('/orders/new?customer=' . $customer['id'])) ?>">
                <?= ui_icon('plus', 'ui-icon') ?>
                <span><?= e(t('new_order')) ?></span>
            </a>
        <?php endif; ?>
    </div>
</div>
<?php
$hero = ob_get_clean();
$title = $displayName;
$back_href = '/customers';
$back_label = t('customers');
$subtitle = null;
$actions_html = '';
require __DIR__ . '/../partials/ui_page_header.php';
echo $hero;

$cards = [
    ['label' => t('order_count'), 'value' => (string) $customer['order_count'], 'tone' => 'blue'],
    ['label' => t('total_spent'), 'value' => money((int) $customer['spent_kobo']), 'tone' => 'green'],
    ['label' => t('amount_owed'), 'value' => money((int) $customer['owed_kobo']), 'tone' => 'orange'],
];
?>
<section class="dash-kpis" aria-label="<?= e(t('summary')) ?>">
    <?php foreach ($cards as $card): ?>
        <article class="dash-kpi dash-kpi-<?= e((string) $card['tone']) ?>">
            <span class="dash-kpi-top">
                <span class="dash-kpi-body">
                    <span class="dash-kpi-label"><?= e((string) $card['label']) ?></span>
                    <strong class="dash-kpi-value"><?= e((string) $card['value']) ?></strong>
                </span>
            </span>
        </article>
    <?php endforeach; ?>
</section>

<div class="detail-layout" style="margin-top:1.25rem">
    <div class="detail-main">
        <section class="card">
            <h2><?= e(t('order_history')) ?></h2>
            <?php if ($customer['orders'] === []): ?>
                <p><?= e(t('no_orders')) ?></p>
            <?php else: ?>
                <div class="ui-table-wrap">
                    <table class="ui-table">
                        <thead>
                            <tr>
                                <th><?= e(t('orders')) ?></th>
                                <th><?= e(t('due_date')) ?></th>
                                <th class="is-num"><?= e(t('balance')) ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($customer['orders'] as $order): ?>
                                <?php $orderOpen = route_defined('GET', '/orders/{id}'); ?>
                                <tr>
                                    <td data-label="<?= e(t('orders')) ?>">
                                        <?php if ($orderOpen): ?>
                                            <a href="<?= e(url('/orders/' . $order['id'])) ?>"><strong><?= e($order['order_number']) ?></strong></a>
                                        <?php else: ?>
                                            <strong><?= e($order['order_number']) ?></strong>
                                        <?php endif; ?>
                                    </td>
                                    <td data-label="<?= e(t('due_date')) ?>"><?= e($order['due_date']) ?></td>
                                    <td class="is-num" data-label="<?= e(t('balance')) ?>"><?= e(money((int) $order['balance_kobo'])) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>
    </div>
    <aside class="detail-side">
        <?php /* contact already in hero; keep side open for future notes */ ?>
    </aside>
</div>

<?php if (module_enabled('fashion.measurements') && is_array($measure)): ?>
    <?php
    $currentByGarment = [];
    foreach ($measure['latest'] as $version) {
        $currentByGarment[$version['garment_id']] = $version;
    }
    $olderByGarment = [];
    foreach ($measure['history'] as $version) {
        $olderByGarment[$version['garment_id']][] = $version;
    }
    $garmentCards = $measure['garments'];
    $knownGarments = [];
    foreach ($garmentCards as $garment) {
        $knownGarments[$garment['id']] = true;
    }
    foreach ($measure['latest'] as $version) {
        if (!isset($knownGarments[$version['garment_id']])) {
            $garmentCards[] = ['id' => $version['garment_id'], 'name' => $version['garment_name'], 'archived' => true];
        }
    }
    $otherPrefill = [];
    foreach ($measure['prefill']['values'] as $key => $value) {
        if (is_string($key) && str_starts_with($key, 'other:')) {
            $otherPrefill[] = ['label' => substr($key, 6), 'value' => (string) $value];
        }
    }
    ?>
    <section class="card" data-measure style="margin-top:1.25rem">
        <h2><?= e(t('measurements')) ?></h2>
        <?php if ($garmentCards === []): ?>
            <p><?= e(t('no_measurements')) ?></p>
        <?php endif; ?>
        <?php foreach ($garmentCards as $garment): ?>
            <?php
            $isSelected = $garment['id'] === $measure['garment_id'];
            $current = $currentByGarment[$garment['id']] ?? null;
            $older = $olderByGarment[$garment['id']] ?? [];
            $archived = !empty($garment['archived']);
            ?>
            <article class="measure-card">
                <h3><?= e($garment['name']) ?></h3>
                <?php if (!empty($garment['placeholder'])): ?>
                    <p class="meta"><?= e(t('placeholder_note')) ?></p>
                <?php endif; ?>
                <?php if ($current === null): ?>
                    <p><?= e(t('no_measurements')) ?></p>
                <?php else: ?>
                    <p class="meta"><?= e(date('j M Y H:i', strtotime((string) $current['created_at']))) ?><?php if ($current['taken_by'] !== ''): ?> · <?= e(t('taken_by')) ?> <?= e($current['taken_by']) ?><?php endif; ?> · <?= e(t($current['unit'] === 'cm' ? 'cm' : 'inches')) ?></p>
                    <?php foreach ($current['lines'] as $line): ?>
                        <div class="line">
                            <strong><?= e($line['name']) ?></strong>
                            <span><?= e($line['value']) ?></span>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
                <?php if ($older !== []): ?>
                    <details class="old-measures">
                        <summary class="btn btn-quiet"><?= e(t('view_old_measurements')) ?></summary>
                        <?php foreach ($older as $version): ?>
                            <div class="old-version">
                                <p class="meta"><?= e(date('j M Y H:i', strtotime((string) $version['created_at']))) ?><?php if ($version['taken_by'] !== ''): ?> · <?= e(t('taken_by')) ?> <?= e($version['taken_by']) ?><?php endif; ?> · <?= e(t($version['unit'] === 'cm' ? 'cm' : 'inches')) ?></p>
                                <?php foreach ($version['lines'] as $line): ?>
                                    <div class="line">
                                        <strong><?= e($line['name']) ?></strong>
                                        <span><?= e($line['value']) ?></span>
                                    </div>
                                <?php endforeach; ?>
                                <?php if (!$archived): ?>
                                    <a class="btn btn-quiet" href="<?= e(url('/customers/' . $customer['id'] . '?garment=' . $garment['id'] . '&from=' . $version['id'])) ?>"><?= e(t('use_as_start')) ?></a>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </details>
                <?php endif; ?>
                <?php
                $cardPrefill = ['unit' => 'inches', 'values' => []];
                if ($current !== null) {
                    $cardPrefill = ['unit' => $current['unit'], 'values' => $current['values']];
                }
                $cardOthers = [];
                $cardOpen = false;
                if ($isSelected && $measure['form_open']) {
                    $cardPrefill = $measure['prefill'];
                    $cardOthers = $otherPrefill;
                    $cardOpen = true;
                }
                $cardFields = $garment['fields'] ?? [];
                ?>
                <?php if (!$archived && $cardFields !== []): ?>
                    <details class="new-measure" <?= $cardOpen ? 'open' : '' ?>>
                        <summary class="btn"><?= e(t('new_measurement')) ?></summary>
                        <form method="post" action="<?= e(url('/customers/' . $customer['id'] . '/measurements')) ?>">
                            <?= csrf_field() ?>
                            <input type="hidden" name="garment_id" value="<?= e($garment['id']) ?>">
                            <p class="meta"><?= e(t('measure_keeps_old')) ?></p>
                            <?php foreach ($cardFields as $field): ?>
                                <label><?= e((string) $field['name'] . ((string) ($field['unit'] ?? '') !== '' ? ' (' . (string) $field['unit'] . ')' : '')) ?>
                                    <input name="values[<?= e((string) $field['field_key']) ?>]" inputmode="decimal" value="<?= e((string) ($cardPrefill['values'][(string) $field['field_key']] ?? '')) ?>">
                                </label>
                            <?php endforeach; ?>
                            <div data-other-list>
                                <?php foreach ($cardOthers as $other): ?>
                                    <div class="other-row">
                                        <label><?= e(t('other_label')) ?>
                                            <input name="others[label][]" value="<?= e($other['label']) ?>">
                                        </label>
                                        <label><?= e(t('other_value')) ?>
                                            <input name="others[value][]" inputmode="decimal" value="<?= e($other['value']) ?>">
                                        </label>
                                        <button class="btn btn-quiet icon-btn" type="button" data-remove-other><?= e(t('remove')) ?></button>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <button class="btn btn-quiet" type="button" data-add-other><?= e(t('add_other')) ?></button>
                            <button class="btn btn-quiet" type="button" data-clear-others data-confirm="<?= e(t('clear_other_confirm')) ?>"><?= e(t('clear_other_fields')) ?></button>
                            <label><?= e(t('unit')) ?>
                                <select name="unit">
                                    <option value="inches" <?= $cardPrefill['unit'] === 'inches' ? 'selected' : '' ?>><?= e(t('inches')) ?></option>
                                    <option value="cm" <?= $cardPrefill['unit'] === 'cm' ? 'selected' : '' ?>><?= e(t('cm')) ?></option>
                                </select>
                            </label>
                            <button class="btn btn-quiet" type="button" data-clear-all data-confirm="<?= e(t('clear_all_confirm')) ?>"><?= e(t('clear_all')) ?></button>
                            <button class="btn" type="submit" name="action" value="new"><?= e(t('new_measurement')) ?></button>
                        </form>
                    </details>
                <?php endif; ?>
            </article>
        <?php endforeach; ?>
        <template id="other-row">
            <div class="other-row">
                <label><?= e(t('other_label')) ?>
                    <input name="others[label][]" value="">
                </label>
                <label><?= e(t('other_value')) ?>
                    <input name="others[value][]" inputmode="decimal" value="">
                </label>
                <button class="btn btn-quiet icon-btn" type="button" data-remove-other><?= e(t('remove')) ?></button>
            </div>
        </template>
        <script src="<?= e(url('/assets/measure.js')) ?>"></script>
    </section>
<?php endif; ?>
