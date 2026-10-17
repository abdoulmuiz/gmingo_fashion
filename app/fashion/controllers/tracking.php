<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/domain/tracking.php';
require_once dirname(__DIR__, 2) . '/domain/receipts.php';

function tracking_show(string $token): void
{
    $lang = tracking_visitor_lang();
    $GLOBALS['gmingo_lang_override'] = $lang;
    try {
        $found = tracking_bind($token);
        if ($found['limited']) {
            tracking_document(429, $lang, t('tracking_limited'), tracking_plain(t('tracking_limited')));
            return;
        }
        if ($found['order_id'] === null) {
            tracking_document(404, $lang, t('not_found'), tracking_plain(t('not_found')));
            return;
        }
        $lang = tracking_shop_lang();
        $GLOBALS['gmingo_lang_override'] = $lang;
        if (tracking_closed()) {
            tracking_document(200, $lang, t('tracking_unavailable'), tracking_plain(t('tracking_unavailable')));
            return;
        }
        $page = tracking_public($found['order_id']);
        $lang = (string) $page['lang'];
        $GLOBALS['gmingo_lang_override'] = $lang;
        tracking_document(
            200,
            $lang,
            (string) $page['shop_name'],
            tracking_inner('show', $page + [
                'language_action' => url('/track/' . $token . '/language'),
                'track_token' => $token,
            ]),
            true
        );
    } catch (HttpException $e) {
        if ($e->status() !== 404) {
            throw $e;
        }
        $lang = tracking_visitor_lang();
        $GLOBALS['gmingo_lang_override'] = $lang;
        tracking_document(404, $lang, t('not_found'), tracking_plain(t('not_found')));
    } finally {
        auth_release_tenant();
        unset($GLOBALS['gmingo_lang_override']);
    }
}

function tracking_language_submit(string $token): void
{
    csrf_check();
    $lang = (string) ($_POST['lang'] ?? 'en');
    if (!in_array($lang, ['en', 'ha'], true)) {
        $lang = 'en';
    }
    tracking_lang_save($lang);
    if (preg_match('/[\r\n]/', $token) === 1 || strlen($token) > 64) {
        tracking_document(404, 'en', t('not_found'), tracking_plain(t('not_found')));
        return;
    }
    redirect('/track/' . $token);
}

function tracking_regenerate_submit(string $id): void
{
    require_module('fashion.communication');
    csrf_check();
    tracking_regenerate($id);
    flash_set('success', t('saved'));
    redirect('/orders/' . $id);
}

function tracking_plain(string $message): string
{
    return '<p>' . e($message) . '</p>';
}

function tracking_inner(string $view, array $data): string
{
    if (!preg_match('#^[a-z0-9_]+$#', $view)) {
        throw new RuntimeException('Invalid view.');
    }
    $file = dirname(__DIR__) . '/views/track/' . $view . '.php';
    extract($data, EXTR_SKIP);
    ob_start();
    require $file;
    return (string) ob_get_clean();
}

function tracking_download(string $token, string $kind, string $paymentId = ''): void
{
    $visitor = tracking_visitor_lang();
    $GLOBALS['gmingo_lang_override'] = $visitor;
    try {
        $found = tracking_bind($token);
        if ($found['limited']) {
            tracking_document(429, $visitor, t('tracking_limited'), tracking_plain(t('tracking_limited')));
            return;
        }
        if ($found['order_id'] === null) {
            tracking_document(404, $visitor, t('not_found'), tracking_plain(t('not_found')));
            return;
        }
        $ext = tracking_download_extension($kind, $paymentId);
        if ($ext === null) {
            tracking_document(404, $visitor, t('not_found'), tracking_plain(t('not_found')));
            return;
        }
        $lang = tracking_shop_lang();
        $GLOBALS['gmingo_lang_override'] = $lang;
        if (tracking_closed()) {
            tracking_document(200, $lang, t('tracking_unavailable'), tracking_plain(t('tracking_unavailable')));
            return;
        }
        $page = tracking_public((string) $found['order_id']);
        $lang = (string) $page['lang'];
        $GLOBALS['gmingo_lang_override'] = $lang;
        $document = null;
        if ($kind === 'receipt') {
            $document = receipt_document_for_payment((string) $found['order_id'], $paymentId);
        } else {
            $invoice = invoice_load((string) $found['order_id']);
            if ($invoice !== null) {
                $document = paper_invoice_document($invoice);
            }
        }
        if ($document === null) {
            $GLOBALS['gmingo_lang_override'] = $visitor;
            tracking_document(404, $visitor, t('not_found'), tracking_plain(t('not_found')));
            return;
        }
        paper_send($document, $ext);
    } catch (HttpException $e) {
        if ($e->status() !== 404) {
            throw $e;
        }
        $GLOBALS['gmingo_lang_override'] = $visitor;
        tracking_document(404, $visitor, t('not_found'), tracking_plain(t('not_found')));
    } finally {
        auth_release_tenant();
        unset($GLOBALS['gmingo_lang_override']);
    }
}

function tracking_download_extension(string $kind, string $paymentId): ?string
{
    $path = request_path();
    if ($kind === 'invoice' && str_ends_with($path, '/invoice.png')) {
        return 'png';
    }
    if ($kind === 'invoice' && str_ends_with($path, '/invoice.pdf')) {
        return 'pdf';
    }
    if ($kind === 'receipt' && $paymentId !== '' && str_ends_with($path, '/receipt/' . $paymentId . '.png')) {
        return 'png';
    }
    if ($kind === 'receipt' && $paymentId !== '' && str_ends_with($path, '/receipt/' . $paymentId . '.pdf')) {
        return 'pdf';
    }
    return null;
}

function tracking_send_file(string $ext, string $filename, string $bytes): void
{
    http_response_code(200);
    header('Content-Type: ' . ($ext === 'png' ? 'image/png' : 'application/pdf'));
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . (string) strlen($bytes));
    header('X-Robots-Tag: noindex, nofollow');
    header('Cache-Control: private, no-store');
    header('Pragma: no-cache');
    echo $bytes;
}

function tracking_document(int $status, string $lang, string $title, string $inner, bool $preview = false): void
{
    if (!in_array($lang, ['en', 'ha'], true)) {
        $lang = 'en';
    }
    http_response_code($status);
    header('Content-Type: text/html; charset=utf-8');
    header('X-Robots-Tag: noindex, nofollow');
    header('Cache-Control: private, no-store');
    header('Pragma: no-cache');
    echo '<!doctype html><html lang="' . e($lang) . '"><head>';
    echo '<meta charset="utf-8">';
    echo '<meta name="viewport" content="width=device-width, initial-scale=1">';
    echo '<meta name="robots" content="noindex, nofollow">';
    echo '<title>' . e($title) . '</title>';
    if ($preview) {
        echo '<meta property="og:title" content="' . e($title) . '">';
        echo '<meta property="og:description" content="' . e($title) . '">';
    }
    echo '<link rel="stylesheet" href="' . e(url('/assets/app.css')) . '">';
    echo '</head><body class="is-guest"><main class="app">';
    echo $inner;
    echo '</main></body></html>';
}
