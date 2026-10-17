<section class="card">
    <p class="eyebrow"><?= e(t($order['paid'] ? 'paid' : 'unpaid')) ?></p>
    <h1><?= e($order['order_number']) ?></h1>
    <p class="meta">
        <a href="<?= e(url('/customers/' . $order['customer_id'])) ?>"><?= e($order['customer_name']) ?></a>
        · <?= e(t('due_date')) ?> <?= e(date('j M Y', strtotime((string) $order['due_date']))) ?>
        <?php if ($order['urgent']): ?> · <?= e(t('urgent')) ?><?php endif; ?>
    </p>
    <?php if (module_enabled('fashion.production') && $order['stage_name'] !== null): ?>
        <p><?= e(t('stage')) ?>: <?= e($order['stage_name']) ?></p>
    <?php endif; ?>
    <?php if (module_enabled('fashion.production')): ?>
        <?php if ($next_stage !== null): ?>
            <?php if ($deposit_block !== null): ?>
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
                        <button class="btn btn-quiet" type="submit"><?= e(t('deposit_override')) ?></button>
                    </form>
                <?php endif; ?>
            <?php endif; ?>
            <form method="post" action="<?= e(url('/orders/' . $order['id'] . '/stage')) ?>">
                <?= csrf_field() ?>
                <button class="btn" type="submit"><?= e(t('move_next')) ?></button>
            </form>
        <?php else: ?>
            <p><?= e(t('no_next_stage')) ?></p>
        <?php endif; ?>
    <?php endif; ?>
    <div class="stats">
        <div><span><?= e(t('total')) ?></span><strong><?= e(money((int) $order['total_kobo'])) ?></strong></div>
        <?php if (module_enabled('fashion.finance')): ?>
            <div><span><?= e(t('amount_paid')) ?></span><strong><?= e(money((int) $order['paid_kobo'])) ?></strong></div>
            <div><span><?= e(t('balance')) ?></span><strong><?= e(money((int) $order['balance_kobo'])) ?></strong></div>
        <?php endif; ?>
    </div>
    <div class="stats">
        <div><span><?= e(t('deposit_due')) ?></span><strong><?= e(money((int) $deposit['due_kobo'])) ?></strong></div>
        <div><span><?= e(t('deposit_paid')) ?></span><strong><?= e(money((int) $deposit['paid_kobo'])) ?></strong></div>
        <div><span><?= e(t('deposit_remaining')) ?></span><strong><?= e(money((int) $deposit['remaining_kobo'])) ?></strong></div>
    </div>
    <?php if ($deposit['warning']): ?>
        <p><?= e(t('deposit_shortfall_warning')) ?></p>
    <?php endif; ?>
    <?php if ($deposit['override'] !== null): ?>
        <p class="meta"><?= e($deposit['override']['user_name']) ?> · <?= e(date('j M Y H:i', strtotime((string) $deposit['override']['created_at']))) ?></p>
        <p><?= e((string) $deposit['override']['reason']) ?></p>
    <?php endif; ?>
</section>
<?php if ($tracking_href !== null): ?>
<section class="card">
    <h2><?= e(t('copy_tracking_link')) ?></h2>
    <label><?= e(t('tracking_link')) ?>
        <input id="tracking-link" readonly value="<?= e($tracking_href) ?>">
    </label>
    <button class="btn" type="button" id="copy-tracking-link"><?= e(t('copy_tracking_link')) ?></button>
    <?php if (is_array($message_link) && !empty($message_link['local_warning'])): ?>
        <p><?= e(t('link_local_warning')) ?></p>
    <?php endif; ?>
    <script>
    document.getElementById('copy-tracking-link').addEventListener('click', function () {
        var field = document.getElementById('tracking-link');
        field.focus();
        field.select();
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(field.value);
        }
    });
    </script>
    <?php if ($is_owner): ?>
        <form method="post" action="<?= e(url('/orders/' . $order['id'] . '/tracking')) ?>">
            <?= csrf_field() ?>
            <button class="btn btn-quiet" type="submit"><?= e(t('regenerate_tracking_link')) ?></button>
        </form>
    <?php endif; ?>
</section>
<?php endif; ?>
<?php if ($messages_on): ?>
<section class="card">
    <h2><?= e(t('messages')) ?></h2>
    <p><?= e(t('whatsapp_prepare_note')) ?></p>
    <?php if ($message_problem === 'no_phone'): ?>
        <p><?= e(t('no_phone')) ?></p>
    <?php elseif ($message_problem === 'phone_unusable'): ?>
        <p><?= e(t('phone_unusable')) ?></p>
    <?php endif; ?>
    <form method="post" action="<?= e(url('/orders/' . $order['id'] . '/messages/ready')) ?>">
        <?= csrf_field() ?>
        <button class="btn" type="submit"><?= e(t('send_ready_message')) ?></button>
    </form>
    <?php if (is_array($message_link)): ?>
        <?php if (!empty($message_link['local_warning'])): ?>
            <p><?= e(t('link_local_warning')) ?></p>
        <?php endif; ?>
        <?php if (is_array($message_link['earlier'])): ?>
            <p><?= e($message_link['earlier']['body']) ?></p>
            <p><?= e($message_link['earlier']['prompt']) ?></p>
        <?php endif; ?>
        <form method="post" action="<?= e($message_link['action']) ?>">
            <?= csrf_field() ?>
            <button class="btn" type="submit"><?= e(t($message_link['label'])) ?></button>
        </form>
    <?php endif; ?>
    <form method="post" action="<?= e(url('/orders/' . $order['id'] . '/messages/late')) ?>">
        <?= csrf_field() ?>
        <label><?= e(t('new_expected_date')) ?>
            <input name="due_date" type="date" required>
        </label>
        <button class="btn" type="submit"><?= e(t('running_late')) ?></button>
    </form>
    <?php if (module_enabled('fashion.finance') && $message_queue === []): ?>
        <p><?= e(t('no_messages_to_send')) ?></p>
    <?php endif; ?>
    <?php if (module_enabled('fashion.finance')): ?>
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
        <label class="check">
            <input type="checkbox" name="supplied" value="1" <?= $order['fabric_supplied'] ? 'checked' : '' ?>>
            <?= e(t('customer_supplied_fabric')) ?>
        </label>
        <button class="btn" type="submit"><?= e(t('save')) ?></button>
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
<section class="card">
    <h2><?= e(t('groups')) ?></h2>
    <?php if ($order_group !== null): ?>
        <p><a href="<?= e(url('/groups/' . $order_group['id'])) ?>"><?= e($order_group['name']) ?></a></p>
        <form method="post" action="<?= e(url('/orders/' . $order['id'] . '/group')) ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="remove">
            <button class="btn btn-quiet" type="submit"><?= e(t('remove_from_group')) ?></button>
        </form>
    <?php endif; ?>
    <?php if ($order_group === null): ?>
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
<section class="card">
    <h2><?= e(t('schedule_fitting')) ?></h2>
    <form method="post" action="<?= e(url('/orders/' . $order['id'] . '/fittings')) ?>">
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
        <button class="btn" type="submit"><?= e(t('schedule_fitting')) ?></button>
    </form>
    <?php if ($fittings === []): ?>
        <p><?= e(t('no_fittings')) ?></p>
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
    <h2><?= e(t('add_items')) ?></h2>
    <?php foreach ($order['items'] as $item): ?>
        <div class="line">
            <strong><?= e($item['garment_name']) ?> × <?= e((string) $item['quantity']) ?></strong>
            <span><?= e(money((int) $item['line_kobo'])) ?></span>
        </div>
        <?php if ($item['fabric'] !== '' || $item['notes'] !== ''): ?>
            <p class="meta"><?= e($item['fabric']) ?><?php if ($item['notes'] !== ''): ?> · <?= e($item['notes']) ?><?php endif; ?></p>
        <?php endif; ?>
    <?php endforeach; ?>
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
    <section class="card">
        <h2><?= e(t('record_payment')) ?></h2>
        <form method="post" action="<?= e(url('/orders/' . $order['id'] . '/payments')) ?>">
            <?= csrf_field() ?>
            <label><?= e(t('amount')) ?>
                <input name="amount" inputmode="decimal" value="">
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
                        <input name="amount" inputmode="decimal" value="<?= e($request_amount) ?>">
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
                <form method="post" action="<?= e(url('/orders/' . $order['id'] . '/payments/' . $payment['id'] . '/reverse')) ?>">
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
