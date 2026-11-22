<?php
$customerName = order_display_customer_name((string) $order['customer_name']);
$dueTone = (string) ($order['due_tone'] ?? 'normal');
$dueLabel = date('j M Y', strtotime((string) $order['due_date']));
$depositMet = (int) $deposit['remaining_kobo'] === 0 && (int) $deposit['due_kobo'] > 0;
$depositFourthLabel = $depositMet ? t('deposit_met') : t('deposit_due');
$depositFourthValue = $depositMet
    ? money((int) $deposit['due_kobo'])
    : money((int) (($deposit['remaining_kobo'] > 0 ? $deposit['remaining_kobo'] : $deposit['due_kobo'])));
$totalKobo = (int) $order['total_kobo'];
$paidKobo = (int) $order['paid_kobo'];
$paidPct = $totalKobo > 0 ? min(100, (int) round(($paidKobo / $totalKobo) * 100)) : 0;
$stage_steps = is_array($stage_steps ?? null) ? $stage_steps : [];

ob_start();
?>
<div class="detail-meta-row">
    <span class="orders-badge is-<?= e((string) $order['status_tone']) ?>"><?= e((string) $order['status_label']) ?></span>
    <span class="orders-badge is-<?= e((string) $order['payment_tone']) ?>"><?= e((string) $order['payment_label']) ?></span>
    <?php if (!empty($order['urgent'])): ?>
        <span class="orders-badge is-orange"><?= e(t('urgent')) ?></span>
    <?php endif; ?>
    <a href="<?= e(url('/customers/' . $order['customer_id'])) ?>"><?= e($customerName) ?></a>
    <span><?= e(t('due_date')) ?> <?= e($dueLabel) ?></span>
    <?php if ($dueTone === 'overdue'): ?>
        <span class="is-overdue"><?= e(t('overdue')) ?></span>
    <?php endif; ?>
</div>
<?php
$subtitleHtml = ob_get_clean();

ob_start();
?>
<?php if ($messages_on): ?>
    <div class="ui-dropdown" data-ui-dropdown>
        <button class="btn btn-quiet" type="button" data-ui-dropdown-toggle>
            <?= ui_icon('mail', 'ui-icon') ?>
            <span><?= e(t('send_message')) ?></span>
            <?= ui_icon('chevron-down', 'ui-icon') ?>
        </button>
        <div class="ui-dropdown-panel">
            <?php if ($message_problem === 'no_phone'): ?>
                <p><?= e(t('no_phone')) ?></p>
            <?php elseif ($message_problem === 'phone_unusable'): ?>
                <p><?= e(t('phone_unusable')) ?></p>
            <?php endif; ?>
            <form method="post" action="<?= e(url('/orders/' . $order['id'] . '/messages/ready')) ?>">
                <?= csrf_field() ?>
                <button type="submit"><?= e(t('send_ready_message')) ?></button>
            </form>
            <?php if (is_array($message_link)): ?>
                <form method="post" action="<?= e($message_link['action']) ?>">
                    <?= csrf_field() ?>
                    <button type="submit"><?= e(t($message_link['label'])) ?></button>
                </form>
            <?php endif; ?>
            <form method="post" action="<?= e(url('/orders/' . $order['id'] . '/messages/late')) ?>">
                <?= csrf_field() ?>
                <label><?= e(t('new_expected_date')) ?>
                    <input name="due_date" type="date" required>
                </label>
                <button type="submit"><?= e(t('running_late')) ?></button>
            </form>
        </div>
    </div>
<?php endif; ?>
<?php if (module_enabled('fashion.finance')): ?>
    <a class="btn" href="#record-payment"><?= e(t('record_payment_action')) ?></a>
<?php endif; ?>
<?php if ($is_owner && $tracking_href !== null): ?>
    <div class="ui-menu" data-ui-menu>
        <button class="btn btn-quiet" type="button" aria-label="<?= e(t('more_actions')) ?>" data-ui-menu-toggle>
            <?= ui_icon('ellipsis-vertical', 'ui-icon') ?>
        </button>
        <div class="ui-menu-panel">
            <form method="post" action="<?= e(url('/orders/' . $order['id'] . '/tracking')) ?>">
                <?= csrf_field() ?>
                <button type="submit"><?= e(t('regenerate_tracking_link')) ?></button>
            </form>
        </div>
    </div>
<?php endif; ?>
<?php
$actionsHtml = ob_get_clean();

$title = (string) $order['order_number'];
$back_href = '/orders';
$back_label = t('orders');
$subtitle = $subtitleHtml;
$actions_html = $actionsHtml;
require __DIR__ . '/../partials/ui_page_header.php';
?>

<div class="detail-layout">
    <div class="detail-main">
        <?php if (module_enabled('fashion.production') && $stage_steps !== []): ?>
            <section class="card">
                <h2><?= e(t('stage')) ?></h2>
                <?php $steps = $stage_steps; require __DIR__ . '/../partials/ui_stepper.php'; ?>
                <?php if ($next_stage !== null): ?>
                    <div class="stage-move-row">
                        <?php if ($deposit_block !== null): ?>
                            <div class="deposit-gate">
                                <p><?= e(t('deposit_short', [
                                    'due' => money((int) $deposit_block['due_kobo']),
                                    'paid' => money((int) $deposit_block['paid_kobo']),
                                    'needed' => money((int) $deposit_block['needed_kobo']),
                                ])) ?></p>
                                <?php if ($is_owner): ?>
                                    <form method="post" action="<?= e(url('/orders/' . $order['id'] . '/deposit-override')) ?>">
                                        <?= csrf_field() ?>
                                        <label><?= e(t('deposit_override_reason')) ?>
                                            <input name="reason" maxlength="500" required>
                                        </label>
                                        <button class="btn btn-quiet btn-small" type="submit"><?= e(t('deposit_override')) ?></button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                        <form method="post" action="<?= e(url('/orders/' . $order['id'] . '/stage')) ?>">
                            <?= csrf_field() ?>
                            <button class="btn" type="submit"><?= e(t('move_next')) ?> → <?= e((string) $next_stage['name']) ?></button>
                        </form>
                    </div>
                <?php else: ?>
                    <p class="meta"><?= e(t('stage_complete')) ?></p>
                <?php endif; ?>
                <?php if ($deposit['warning']): ?>
                    <p><?= e(t('deposit_shortfall_warning')) ?></p>
                <?php endif; ?>
                <?php if ($deposit['override'] !== null): ?>
                    <p class="meta"><?= e($deposit['override']['user_name']) ?> · <?= e(date('j M Y H:i', strtotime((string) $deposit['override']['created_at']))) ?></p>
                    <p><?= e((string) $deposit['override']['reason']) ?></p>
                <?php endif; ?>
            </section>
        <?php endif; ?>

        <section class="card">
            <h2><?= e(t('summary')) ?></h2>
            <div class="money-card-grid">
                <div>
                    <span><?= e(t('total')) ?></span>
                    <strong><?= e(money($totalKobo)) ?></strong>
                </div>
                <?php if (module_enabled('fashion.finance')): ?>
                    <div>
                        <span><?= e(t('amount_paid')) ?></span>
                        <strong><?= e(money($paidKobo)) ?></strong>
                    </div>
                    <div>
                        <span><?= e(t('balance')) ?></span>
                        <strong><?= e(money((int) $order['balance_kobo'])) ?></strong>
                    </div>
                <?php endif; ?>
                <div>
                    <span><?= e($depositFourthLabel) ?></span>
                    <strong><?= e($depositFourthValue) ?></strong>
                    <?php if ($depositMet): ?>
                        <span class="visually-hidden"><?= e(t('deposit_paid')) ?></span>
                    <?php endif; ?>
                </div>
            </div>
            <?php if (module_enabled('fashion.finance') && $totalKobo > 0): ?>
                <div class="money-progress" aria-hidden="true"><i style="width:<?= e((string) $paidPct) ?>%"></i></div>
            <?php endif; ?>
        </section>

        <section class="card">
            <h2><?= e(t('add_items')) ?></h2>
            <?php if ($order['items'] === []): ?>
                <p><?= e(t('no_orders')) ?></p>
            <?php else: ?>
                <div class="ui-table-wrap">
                    <table class="ui-table">
                        <thead>
                            <tr>
                                <th><?= e(t('garment')) ?></th>
                                <th><?= e(t('quantity')) ?></th>
                                <th class="is-num"><?= e(t('price')) ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($order['items'] as $item): ?>
                                <tr>
                                    <td data-label="<?= e(t('garment')) ?>">
                                        <strong><?= e($item['garment_name']) ?></strong>
                                        <?php if ($item['fabric'] !== '' || $item['notes'] !== ''): ?>
                                            <p class="meta"><?= e($item['fabric']) ?><?php if ($item['notes'] !== ''): ?> · <?= e($item['notes']) ?><?php endif; ?></p>
                                        <?php endif; ?>
                                    </td>
                                    <td data-label="<?= e(t('quantity')) ?>"><?= e((string) $item['quantity']) ?></td>
                                    <td class="is-num" data-label="<?= e(t('price')) ?>"><?= e(money((int) $item['line_kobo'])) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>

        <section class="card">
            <h2><?= e(t('schedule_fitting')) ?></h2>
            <form method="post" action="<?= e(url('/orders/' . $order['id'] . '/fittings')) ?>" class="form-grid">
                <?= csrf_field() ?>
                <label><?= e(t('fitting_date')) ?>
                    <input name="fitting_date" type="date" required>
                </label>
                <label><?= e(t('fitting_time')) ?>
                    <input name="fitting_time" type="time" required>
                </label>
                <label><?= e(t('fitting_type')) ?>
                    <select name="fitting_type">
                        <?php foreach (fitting_types() as $type): ?>
                            <option value="<?= e($type) ?>"><?= e(t('fitting_type_' . $type)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label><?= e(t('fitting_note')) ?>
                    <input name="note" maxlength="500" value="">
                </label>
                <div class="span-2">
                    <button class="btn" type="submit"><?= e(t('schedule_fitting')) ?></button>
                </div>
            </form>
            <?php if ($fittings === []): ?>
                <p class="meta"><?= e(t('no_fittings')) ?></p>
            <?php endif; ?>
            <?php foreach ($fittings as $fitting): ?>
                <div class="line">
                    <strong><?= e($fitting['when']) ?></strong>
                    <span><?= e(t('fitting_type_' . $fitting['fitting_type'])) ?></span>
                    <span><?= e(t('fitting_status_' . $fitting['status'])) ?></span>
                </div>
                <?php if ($fitting['note'] !== ''): ?>
                    <p><?= e($fitting['note']) ?></p>
                <?php endif; ?>
                <p class="meta"><?= e(t('fitting_created_by')) ?> <?= e($fitting['created_name']) ?> · <?= e($fitting['created_when']) ?></p>
                <p class="meta"><?= e(t('fitting_status_by')) ?> <?= e($fitting['status_name']) ?> · <?= e($fitting['status_when']) ?></p>
                <div class="actions">
                    <?php foreach (['done', 'missed', 'cancelled'] as $status): ?>
                        <form method="post" action="<?= e(url('/orders/' . $order['id'] . '/fittings/' . $fitting['id'] . '/status')) ?>">
                            <?= csrf_field() ?>
                            <input type="hidden" name="status" value="<?= e($status) ?>">
                            <button class="btn btn-small" type="submit"><?= e(t('fitting_mark_' . $status)) ?></button>
                        </form>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>
        </section>

        <section class="card">
            <h2><?= e(t('groups')) ?></h2>
            <?php if ($order_group !== null): ?>
                <p><a href="<?= e(url('/groups/' . $order_group['id'])) ?>"><?= e($order_group['name']) ?></a></p>
                <form method="post" action="<?= e(url('/orders/' . $order['id'] . '/group')) ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="remove">
                    <button class="btn btn-quiet" type="submit"><?= e(t('remove_from_group')) ?></button>
                </form>
            <?php else: ?>
                <p><a href="<?= e(url('/groups/new')) ?>"><?= e(t('new_group')) ?></a></p>
                <?php if ($group_choices !== []): ?>
                    <form method="post" action="<?= e(url('/orders/' . $order['id'] . '/group')) ?>">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="add">
                        <label><?= e(t('add_to_group')) ?>
                            <select name="group_id">
                                <?php foreach ($group_choices as $choice): ?>
                                    <option value="<?= e($choice['id']) ?>"><?= e($choice['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <button class="btn" type="submit"><?= e(t('add_to_group')) ?></button>
                    </form>
                <?php endif; ?>
            <?php endif; ?>
        </section>

        <?php if (module_enabled('fashion.finance')): ?>
            <section class="card">
                <h2><?= e(t('invoice')) ?></h2>
                <p><a class="btn" href="<?= e(url('/orders/' . $order['id'] . '/invoice')) ?>"><?= e(t('invoice')) ?></a></p>
                <?php
                $paper_png = url('/orders/' . $order['id'] . '/invoice.png');
                $paper_pdf = url('/orders/' . $order['id'] . '/invoice.pdf');
                $paper_print = url('/orders/' . $order['id'] . '/invoice');
                $paper_name = $order['order_number'] . '.pdf';
                require dirname(__DIR__) . '/paper_files.php';
                ?>
                <?php if ($messages_on): ?>
                    <?php if ($invoice_whatsapp !== null): ?>
                        <p><a class="btn btn-whatsapp" href="<?= e($invoice_whatsapp) ?>"><?= e(t('share_whatsapp')) ?></a></p>
                    <?php else: ?>
                        <form method="post" action="<?= e(url('/orders/' . $order['id'] . '/invoice/share')) ?>">
                            <?= csrf_field() ?>
                            <button class="btn btn-whatsapp" type="submit"><?= e(t('share_whatsapp')) ?></button>
                        </form>
                    <?php endif; ?>
                <?php endif; ?>
            </section>

            <section class="card" id="record-payment">
                <h2><?= e(t('record_payment')) ?></h2>
                <form method="post" action="<?= e(url('/orders/' . $order['id'] . '/payments')) ?>">
                    <?= csrf_field() ?>
                    <label><?= e(t('amount')) ?>
                        <div class="ui-money">
                            <span class="ui-money-prefix" aria-hidden="true">₦</span>
                            <input name="amount" inputmode="decimal" value="">
                        </div>
                    </label>
                    <label><?= e(t('method')) ?>
                        <select name="method">
                            <option value="cash"><?= e(t('cash')) ?></option>
                            <option value="transfer"><?= e(t('transfer')) ?></option>
                        </select>
                    </label>
                    <button class="btn" type="submit"><?= e(t('record_payment')) ?></button>
                </form>
            </section>

            <?php if ($payment_requests !== [] || $can_request_payment): ?>
                <section class="card">
                    <h2><?= e(t('request_payment')) ?></h2>
                    <?php if ($email_note): ?>
                        <p><?= e(t('no_email_receipt')) ?></p>
                    <?php endif; ?>
                    <?php foreach ($payment_requests as $request): ?>
                        <label><?= e(t('payment_link')) ?>
                            <input readonly value="<?= e($request['url']) ?>">
                        </label>
                        <p><a class="btn btn-whatsapp" href="<?= e(whatsapp_share_href(t('payment_link') . ' ' . $request['url'])) ?>"><?= e(t('share_whatsapp')) ?></a></p>
                        <?php if ($request['placeholder']): ?>
                            <p><?= e(t('no_email_receipt')) ?></p>
                        <?php endif; ?>
                    <?php endforeach; ?>
                    <?php if ($can_request_payment): ?>
                        <form method="post" action="<?= e(url('/orders/' . $order['id'] . '/payment-request')) ?>">
                            <?= csrf_field() ?>
                            <label><?= e(t('amount')) ?>
                                <div class="ui-money">
                                    <span class="ui-money-prefix" aria-hidden="true">₦</span>
                                    <input name="amount" inputmode="decimal" value="<?= e($request_amount) ?>">
                                </div>
                            </label>
                            <label><?= e(t('customer_email')) ?>
                                <input name="email" type="email" value="">
                            </label>
                            <button class="btn" type="submit"><?= e(t('request_payment')) ?></button>
                        </form>
                    <?php endif; ?>
                </section>
            <?php endif; ?>

            <section class="card">
                <h2><?= e(t('payment_history')) ?></h2>
                <?php if ($payments === []): ?>
                    <p><?= e(t('no_payments')) ?></p>
                <?php endif; ?>
                <?php foreach ($payments as $payment): ?>
                    <div class="line">
                        <strong><?= e(money((int) $payment['amount_kobo'])) ?></strong>
                        <?php $isPaystack = (string) ($payment['provider'] ?? '') === 'paystack'; ?>
                        <span><?= e(t($isPaystack ? 'mode_paystack' : ($payment['method'] === 'cash' ? 'cash' : 'transfer'))) ?></span>
                        <?php if ($isPaystack && (string) ($payment['provider_reference'] ?? '') !== ''): ?>
                            <span><?= e((string) $payment['provider_reference']) ?></span>
                        <?php endif; ?>
                        <span><?= e(date('j M Y H:i', strtotime((string) $payment['created_at']))) ?></span>
                    </div>
                    <?php if ($payment['kind'] === 'reversal' || $payment['reversed']): ?>
                        <p class="meta"><?= e(t('reversed')) ?></p>
                    <?php endif; ?>
                    <?php if ($payment['kind'] === 'reversal' && (string) ($payment['reason'] ?? '') !== ''): ?>
                        <p><?= e((string) $payment['reason']) ?></p>
                    <?php endif; ?>
                    <?php $receiptButton = $receipt_buttons[$payment['id']] ?? null; ?>
                    <?php if ($payment['kind'] === 'payment' && is_array($receiptButton)): ?>
                        <p><a class="btn btn-small" href="<?= e($receiptButton['href']) ?>"><?= e(t('receipt')) ?></a></p>
                        <?php
                        $paper_png = $receiptButton['png'];
                        $paper_pdf = $receiptButton['pdf'];
                        $paper_print = $receiptButton['href'];
                        $paper_name = $receiptButton['number'] . '.pdf';
                        require dirname(__DIR__) . '/paper_files.php';
                        ?>
                        <?php if ($messages_on): ?>
                            <?php if ($receiptButton['whatsapp'] !== null): ?>
                                <p><a class="btn btn-whatsapp btn-small" href="<?= e($receiptButton['whatsapp']) ?>"><?= e(t('share_whatsapp')) ?></a></p>
                            <?php else: ?>
                                <form method="post" action="<?= e($receiptButton['share_action']) ?>">
                                    <?= csrf_field() ?>
                                    <button class="btn btn-whatsapp btn-small" type="submit"><?= e(t('share_whatsapp')) ?></button>
                                </form>
                            <?php endif; ?>
                        <?php endif; ?>
                    <?php endif; ?>
                    <?php if ($payment['kind'] === 'payment' && !$payment['reversed']): ?>
                        <form method="post" action="<?= e(url('/orders/' . $order['id'] . '/payments/' . $payment['id'] . '/reverse')) ?>" data-confirm="<?= e(t('confirm')) ?>">
                            <?= csrf_field() ?>
                            <label><?= e(t('reversal_reason')) ?>
                                <input name="reason" maxlength="500" required>
                            </label>
                            <button class="btn btn-quiet btn-small" type="submit"><?= e(t('reverse')) ?></button>
                        </form>
                    <?php endif; ?>
                <?php endforeach; ?>
            </section>
            <script>
            document.querySelectorAll('[data-share-file]').forEach(function (button) {
                button.addEventListener('click', function () {
                    var fileUrl = button.getAttribute('data-share-file') || '';
                    var fileName = button.getAttribute('data-share-name') || 'file.pdf';
                    fetch(fileUrl, {credentials: 'same-origin'}).then(function (response) {
                        if (!response.ok) {
                            return;
                        }
                        return response.blob().then(function (blob) {
                            var file = new File([blob], fileName, {type: blob.type || 'application/pdf'});
                            if (navigator.canShare && navigator.canShare({files: [file]})) {
                                navigator.share({files: [file]}).catch(function () {});
                                return;
                            }
                            var link = document.createElement('a');
                            link.href = URL.createObjectURL(blob);
                            link.download = fileName;
                            document.body.appendChild(link);
                            link.click();
                            link.remove();
                        });
                    });
                });
            });
            </script>
        <?php endif; ?>
    </div>

    <aside class="detail-side">
        <?php if ($tracking_href !== null): ?>
            <section class="card">
                <h2><?= e(t('tracking_link')) ?></h2>
                <div class="tracking-copy">
                    <input id="tracking-link" readonly value="<?= e($tracking_href) ?>">
                    <button class="btn btn-quiet" type="button" id="copy-tracking-link" aria-label="<?= e(t('copy_tracking_link')) ?>">
                        <?= ui_icon('copy', 'ui-icon') ?>
                    </button>
                </div>
                <p class="visually-hidden"><?= e(t('copy_tracking_link')) ?></p>
                <?php if (is_array($message_link) && !empty($message_link['local_warning'])): ?>
                    <p><?= e(t('link_local_warning')) ?></p>
                <?php endif; ?>
                <script>
                document.getElementById('copy-tracking-link').addEventListener('click', function () {
                    var field = document.getElementById('tracking-link');
                    field.focus();
                    field.select();
                    var done = function () {
                        if (window.toast) {
                            window.toast.success(<?= json_encode(t('copied'), JSON_UNESCAPED_UNICODE) ?>);
                        }
                    };
                    if (navigator.clipboard && navigator.clipboard.writeText) {
                        navigator.clipboard.writeText(field.value).then(done).catch(done);
                    } else {
                        done();
                    }
                });
                </script>
            </section>
        <?php endif; ?>

        <?php if ($messages_on): ?>
            <section class="card">
                <h2><?= e(t('messages')) ?></h2>
                <p class="meta"><?= e(t('whatsapp_prepare_note')) ?></p>
                <?php if (is_array($message_link) && is_array($message_link['earlier'] ?? null)): ?>
                    <p><?= e($message_link['earlier']['body']) ?></p>
                    <p><?= e($message_link['earlier']['prompt']) ?></p>
                <?php endif; ?>
                <?php if (module_enabled('fashion.finance')): ?>
                    <?php if ($message_queue === []): ?>
                        <p><?= e(t('no_messages_sent')) ?></p>
                        <p class="visually-hidden"><?= e(t('no_messages_to_send')) ?></p>
                    <?php endif; ?>
                    <?php foreach ($message_queue as $message): ?>
                        <p><strong><?= e($message['label']) ?></strong></p>
                        <p><?= e($message['body']) ?></p>
                        <?php if ($message['change_label'] !== null): ?>
                            <p><?= e($message['change_label']) ?></p>
                        <?php endif; ?>
                        <?php if ($message['open_href'] !== null): ?>
                            <p><a class="btn btn-whatsapp" href="<?= e($message['open_href']) ?>"><?= e(t('open_whatsapp')) ?></a></p>
                        <?php endif; ?>
                        <form method="post" action="<?= e(url('/messages/' . $message['id'] . '/sent')) ?>">
                            <?= csrf_field() ?>
                            <input type="hidden" name="back" value="<?= e('/orders/' . $order['id']) ?>">
                            <button class="btn btn-quiet" type="submit"><?= e(t('mark_sent')) ?></button>
                        </form>
                    <?php endforeach; ?>
                <?php endif; ?>
                <p><a href="<?= e(url('/messages?order=' . $order['id'])) ?>"><?= e(t('messages')) ?></a></p>
            </section>
        <?php endif; ?>

        <section class="card">
            <h2><?= e(t('customer_supplied_fabric')) ?></h2>
            <form method="post" action="<?= e(url('/orders/' . $order['id'] . '/fabric')) ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="switch">
                <input type="hidden" name="supplied" value="0">
                <label class="ui-switch">
                    <input type="checkbox" name="supplied" value="1" <?= $order['fabric_supplied'] ? 'checked' : '' ?> onchange="this.form.submit()">
                    <span class="ui-switch-track" aria-hidden="true"></span>
                    <span><?= e(t('customer_supplied_fabric')) ?></span>
                </label>
            </form>
            <?php if ($order['fabric_supplied']): ?>
                <form method="post" action="<?= e(url('/orders/' . $order['id'] . '/fabric')) ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="yards">
                    <label><?= e(t('yards_received')) ?>
                        <input name="received" inputmode="decimal" value="<?= e($order['received_hundredths'] === null ? '' : yards_format((int) $order['received_hundredths'])) ?>">
                    </label>
                    <label><?= e(t('yards_used')) ?>
                        <input name="used" inputmode="decimal" value="<?= e($order['used_hundredths'] === null ? '' : yards_format((int) $order['used_hundredths'])) ?>">
                    </label>
                    <p><?= e(t('yards_left')) ?> <strong><?= e(yards_format((int) $order['yards_left_hundredths'])) ?></strong></p>
                    <label><?= e(t('fabric_note')) ?>
                        <input name="note" maxlength="500" value="">
                    </label>
                    <button class="btn" type="submit"><?= e(t('save')) ?></button>
                </form>
            <?php endif; ?>
            <?php if ($order['fabric_supplied'] || $fabric_has_log): ?>
                <p><a href="<?= e(url('/orders/' . $order['id'] . '/fabric')) ?>"><?= e(t('fabric_history')) ?></a></p>
            <?php endif; ?>
        </section>
    </aside>
</div>

<?php require __DIR__ . '/../partials/ui_confirm.php'; ?>
<script>
(function () {
    function bindToggle(rootSel, toggleSel, openClass) {
        document.querySelectorAll(rootSel).forEach(function (root) {
            var btn = root.querySelector(toggleSel);
            if (!btn) return;
            btn.addEventListener('click', function (ev) {
                ev.stopPropagation();
                var open = root.classList.contains(openClass);
                document.querySelectorAll(rootSel + '.' + openClass).forEach(function (el) { el.classList.remove(openClass); });
                if (!open) root.classList.add(openClass);
            });
        });
        document.addEventListener('click', function () {
            document.querySelectorAll(rootSel + '.' + openClass).forEach(function (el) { el.classList.remove(openClass); });
        });
    }
    bindToggle('[data-ui-menu]', '[data-ui-menu-toggle]', 'is-open');
    bindToggle('[data-ui-dropdown]', '[data-ui-dropdown-toggle]', 'is-open');
})();
</script>
