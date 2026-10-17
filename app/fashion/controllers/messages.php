<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/domain/messages.php';

function messages_index(): void
{
    require_module('fashion.communication');
    $order = (string) ($_GET['order'] ?? '');
    if (!is_uuid($order)) {
        $order = '';
    }
    $toPage = list_page($_GET['to_page'] ?? 1);
    $sentPage = list_page($_GET['sent_page'] ?? 1);
    render('messages/index', [
        'title' => t('messages'),
        'order_filter' => $order,
        'to_send' => message_to_send_list($toPage, $order),
        'sent' => message_sent_list($sentPage, $order),
        'to_count' => message_to_send_count($order),
        'sent_count' => message_sent_count($order),
        'to_page' => $toPage,
        'sent_page' => $sentPage,
        'page_size' => list_page_size(),
        'is_owner' => deposit_is_owner(),
    ]);
}

function messages_show(string $messageId): void
{
    require_module('fashion.communication');
    $message = message_open($messageId);
    render('messages/show', [
        'title' => $message['label'],
        'message' => $message,
    ]);
}

function messages_templates_form(): void
{
    require_module('fashion.communication');
    if (!deposit_is_owner()) {
        http_response_code(403);
        throw new HttpException(t('not_allowed'), 403);
    }
    render('messages/templates', [
        'title' => t('message_templates'),
        'templates' => message_editor(),
    ]);
}

function messages_templates_submit(): void
{
    require_module('fashion.communication');
    csrf_check();
    $posted = $_POST['body'] ?? [];
    if (!is_array($posted)) {
        $posted = [];
    }
    $result = message_templates_save_all($posted);
    if (isset($result['error'])) {
        flash_set('error', t((string) $result['error']));
    } else {
        flash_set('success', t('saved'));
    }
    redirect('/messages/templates');
}

function message_sent_submit(string $messageId): void
{
    require_module('fashion.communication');
    csrf_check();
    $result = message_mark_sent($messageId);
    if (isset($result['error'])) {
        flash_set('error', t((string) $result['error']));
    } else {
        flash_set('success', t('saved'));
    }
    $back = (string) ($_POST['back'] ?? '');
    if (preg_match('#^/orders/[0-9a-f-]{36}$#', $back) !== 1) {
        $back = '/messages';
    }
    redirect($back);
}

function message_link_submit(string $orderId): void
{
    require_module('fashion.communication');
    csrf_check();
    $id = message_send_link($orderId);
    redirect('/messages/' . $id);
}

function message_ready_submit(string $orderId): void
{
    require_module('fashion.communication');
    csrf_check();
    if (message_order_row($orderId) === null) {
        http_response_code(404);
        throw new HttpException(t('not_found'), 404);
    }
    $id = message_queue_ready($orderId);
    flash_set('success', t('saved'));
    redirect('/messages/' . $id);
}

function message_late_submit(string $orderId): void
{
    require_module('fashion.communication');
    csrf_check();
    $result = message_running_late($orderId, (string) ($_POST['due_date'] ?? ''));
    if (isset($result['error'])) {
        flash_set('error', t((string) $result['error']));
    } else {
        flash_set('success', t('saved'));
    }
    redirect('/orders/' . $orderId);
}
