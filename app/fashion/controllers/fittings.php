<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/domain/fittings.php';

function calendar_show(): void
{
    require_module('fashion.orders');
    render('calendar/month', [
        'title' => t('calendar'),
        'calendar' => calendar_page((string) ($_GET['month'] ?? '')),
    ]);
}

function calendar_day_show(string $date): void
{
    require_module('fashion.orders');
    $day = calendar_day($date);
    if ($day === null) {
        http_response_code(404);
        throw new HttpException(t('not_found'), 404);
    }
    render('calendar/day', [
        'title' => $day['title'],
        'day' => $day,
    ]);
}

function fitting_schedule_submit(string $orderId): void
{
    require_module('fashion.orders');
    csrf_check();
    $result = fitting_schedule($orderId, [
        'date' => (string) ($_POST['fitting_date'] ?? ''),
        'time' => (string) ($_POST['fitting_time'] ?? ''),
        'fitting_type' => (string) ($_POST['fitting_type'] ?? ''),
        'note' => (string) ($_POST['note'] ?? ''),
    ]);
    if (isset($result['error'])) {
        flash_set('error', t((string) $result['error']));
    } else {
        flash_set('success', t('saved'));
    }
    redirect('/orders/' . $orderId);
}

function fitting_status_submit(string $orderId, string $fittingId): void
{
    require_module('fashion.orders');
    csrf_check();
    $result = fitting_set_status($orderId, $fittingId, (string) ($_POST['status'] ?? ''));
    if (isset($result['error'])) {
        flash_set('error', t((string) $result['error']));
    } else {
        flash_set('success', t('saved'));
    }
    redirect('/orders/' . $orderId);
}
