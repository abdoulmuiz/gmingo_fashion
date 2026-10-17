<?php

declare(strict_types=1);

function paper_font_path(): string
{
    return dirname(__DIR__, 2) . '/public/assets/fonts/NotoSans-Regular.ttf';
}

function paper_can_draw(): bool
{
    if (array_key_exists('gmingo_paper_ready', $GLOBALS)) {
        return (bool) $GLOBALS['gmingo_paper_ready'];
    }
    return extension_loaded('gd')
        && function_exists('imagettftext')
        && function_exists('imagejpeg')
        && function_exists('imagepng')
        && is_file(paper_font_path());
}

function paper_filename(string $number, string $ext): string
{
    $safe = preg_replace('/[^A-Za-z0-9-]/', '', $number);
    if (!is_string($safe) || $safe === '' || ($ext !== 'png' && $ext !== 'pdf')) {
        throw new RuntimeException('Invalid paper file.');
    }
    return $safe . '.' . $ext;
}

/**
 * @param array<string, mixed> $receipt
 * @return array{number: string, lines: list<string>, voided: bool}
 */
function paper_receipt_document(array $receipt): array
{
    $number = (string) ($receipt['receipt_number'] ?? '');
    $lines = [
        (string) ($receipt['shop_name'] ?? ''),
        t('phone') . ': ' . (string) ($receipt['shop_phone'] ?? ''),
        t('receipt'),
    ];
    if (!array_key_exists('show_number', $receipt) || $receipt['show_number']) {
        $lines[] = t('receipt_number') . ': ' . $number;
    }
    $lines[] = t('date') . ': ' . (string) ($receipt['when'] ?? '');
    $lines[] = t('order_number') . ': ' . (string) ($receipt['order_number'] ?? '');
    $lines[] = t('receipt_amount') . ': ' . format_naira((int) ($receipt['amount_kobo'] ?? 0));
    $lines[] = t('method') . ': ' . (string) ($receipt['method_label'] ?? '');
    $lines[] = t('balance_after') . ': ' . format_naira((int) ($receipt['balance_after_kobo'] ?? 0));
    $voided = !empty($receipt['voided']);
    if ($voided) {
        $lines[] = t('void_mark');
    }
    return [
        'number' => $number,
        'lines' => $lines,
        'voided' => $voided,
    ];
}

/**
 * @param array<string, mixed> $invoice
 * @return array{number: string, lines: list<string>, voided: bool}
 */
function paper_invoice_document(array $invoice): array
{
    $lines = [
        (string) ($invoice['shop_name'] ?? ''),
        t('phone') . ': ' . (string) ($invoice['shop_phone'] ?? ''),
        t('invoice'),
        t('order_number') . ': ' . (string) ($invoice['order_number'] ?? ''),
        t('date') . ': ' . (string) ($invoice['when'] ?? ''),
        t('invoice_work'),
    ];
    $rows = is_array($invoice['lines'] ?? null) ? $invoice['lines'] : [];
    foreach ($rows as $row) {
        if (!is_array($row)) {
            continue;
        }
        $lines[] = (string) ($row['description'] ?? '') . '  ' . format_naira((int) ($row['amount_kobo'] ?? 0));
    }
    $lines[] = t('total') . ': ' . format_naira((int) ($invoice['total_kobo'] ?? 0));
    $lines[] = t('deposit_due') . ': ' . format_naira((int) ($invoice['deposit_kobo'] ?? 0));
    $lines[] = t('paid_so_far') . ': ' . format_naira((int) ($invoice['paid_kobo'] ?? 0));
    $lines[] = t('balance') . ': ' . format_naira((int) ($invoice['balance_kobo'] ?? 0));
    return [
        'number' => (string) ($invoice['order_number'] ?? ''),
        'lines' => $lines,
        'voided' => false,
    ];
}

/**
 * @param array{number: string, lines: list<string>, voided: bool} $document
 */
function paper_png(array $document): string
{
    $image = paper_image($document);
    ob_start();
    imagepng($image);
    $bytes = ob_get_clean();
    imagedestroy($image);
    if (!is_string($bytes) || !str_starts_with($bytes, "\x89PNG")) {
        throw new RuntimeException(t('paper_unavailable_body'));
    }
    return $bytes;
}

/**
 * @param array{number: string, lines: list<string>, voided: bool} $document
 */
function paper_pdf(array $document): string
{
    $image = paper_image($document);
    ob_start();
    imagejpeg($image, null, 90);
    $jpeg = ob_get_clean();
    $width = imagesx($image);
    $height = imagesy($image);
    imagedestroy($image);
    if (!is_string($jpeg) || !str_starts_with($jpeg, "\xFF\xD8")) {
        throw new RuntimeException(t('paper_unavailable_body'));
    }
    return paper_pdf_bytes($jpeg, $width, $height, $document['lines']);
}

/**
 * @param array{number: string, lines: list<string>, voided: bool} $document
 */
function paper_send(array $document, string $ext): void
{
    if ($ext !== 'png' && $ext !== 'pdf') {
        http_response_code(404);
        throw new HttpException(t('not_found'), 404);
    }
    if (!paper_can_draw()) {
        paper_unavailable_response();
        return;
    }
    try {
        $bytes = $ext === 'png' ? paper_png($document) : paper_pdf($document);
        $filename = paper_filename((string) $document['number'], $ext);
    } catch (RuntimeException) {
        paper_unavailable_response();
        return;
    }
    http_response_code(200);
    header('Content-Type: ' . ($ext === 'png' ? 'image/png' : 'application/pdf'));
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . (string) strlen($bytes));
    header('X-Robots-Tag: noindex, nofollow');
    header('Cache-Control: no-store');
    header('Pragma: no-cache');
    echo $bytes;
}

function paper_unavailable_response(): void
{
    http_response_code(503);
    header('Content-Type: text/html; charset=utf-8');
    header('Cache-Control: no-store');
    echo '<!doctype html><html lang="' . e(current_lang()) . '"><head><meta charset="utf-8">';
    echo '<title>' . e(t('paper_unavailable')) . '</title></head><body><main><p>';
    echo e(t('paper_unavailable_body'));
    echo '</p></main></body></html>';
}

/**
 * @param array{lines: list<string>, voided: bool} $document
 */
function paper_image(array $document): \GdImage
{
    if (!paper_can_draw()) {
        throw new RuntimeException(t('paper_unavailable_body'));
    }
    $font = paper_font_path();
    $width = 1240;
    $height = 1754;
    $image = imagecreatetruecolor($width, $height);
    if ($image === false) {
        throw new RuntimeException(t('paper_unavailable_body'));
    }
    $white = imagecolorallocate($image, 255, 255, 255);
    $ink = imagecolorallocate($image, 28, 28, 28);
    $rule = imagecolorallocate($image, 210, 210, 210);
    if ($white === false || $ink === false || $rule === false) {
        imagedestroy($image);
        throw new RuntimeException(t('paper_unavailable_body'));
    }
    imagefilledrectangle($image, 0, 0, $width, $height, $white);
    $x = 72;
    $y = 110;
    $max = $width - 144;
    $first = true;
    foreach ($document['lines'] as $line) {
        $size = $first ? 32 : 20;
        foreach (paper_wrap($font, $size, (string) $line, $max) as $row) {
            if ($y > $height - 90) {
                break 2;
            }
            $drawn = imagettftext($image, $size, 0, $x, $y, $ink, $font, $row);
            if ($drawn === false) {
                imagedestroy($image);
                throw new RuntimeException(t('paper_unavailable_body'));
            }
            $y += $first ? 48 : 36;
        }
        if ($first) {
            imageline($image, $x, $y - 18, $width - 72, $y - 18, $rule);
            $y += 24;
        }
        $first = false;
    }
    if (!empty($document['voided'])) {
        $red = imagecolorallocate($image, 176, 28, 28);
        if ($red === false || imagettftext($image, 88, 24, 250, 1040, $red, $font, t('void_mark')) === false) {
            imagedestroy($image);
            throw new RuntimeException(t('paper_unavailable_body'));
        }
    }
    return $image;
}

/**
 * @return list<string>
 */
function paper_wrap(string $font, int $size, string $text, int $maxWidth): array
{
    $text = trim(str_replace(["\r", "\n", "\t"], ' ', $text));
    if ($text === '') {
        return [' '];
    }
    $words = preg_split('/ +/', $text);
    if ($words === false || $words === []) {
        return [$text];
    }
    $lines = [];
    $current = '';
    foreach ($words as $word) {
        $candidate = $current === '' ? $word : $current . ' ' . $word;
        $box = imagettfbbox($size, 0, $font, $candidate);
        $wide = $box !== false && abs($box[2] - $box[0]) > $maxWidth;
        if ($current !== '' && $wide) {
            $lines[] = $current;
            $current = $word;
            continue;
        }
        $current = $candidate;
    }
    $lines[] = $current;
    return $lines;
}

/**
 * @param list<string> $lines
 */
function paper_pdf_bytes(string $jpeg, int $width, int $height, array $lines): string
{
    $draw = '';
    foreach ($lines as $line) {
        $draw .= '% ' . str_replace(["\r", "\n"], ' ', $line) . "\n";
    }
    $draw .= "q\n595.28 0 0 841.89 0 0 cm\n/Im1 Do\nQ";
    $objects = [
        "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n",
        "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n",
        "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595.28 841.89] /Contents 4 0 R /Resources << /XObject << /Im1 5 0 R >> >> >>\nendobj\n",
        "4 0 obj\n<< /Length " . (string) strlen($draw) . " >>\nstream\n" . $draw . "\nendstream\nendobj\n",
        "5 0 obj\n<< /Type /XObject /Subtype /Image /Width " . (string) $width
            . " /Height " . (string) $height
            . " /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length "
            . (string) strlen($jpeg) . " >>\nstream\n" . $jpeg . "\nendstream\nendobj\n",
    ];
    $pdf = "%PDF-1.4\n";
    $offsets = [];
    foreach ($objects as $object) {
        $offsets[] = strlen($pdf);
        $pdf .= $object;
    }
    $xref = strlen($pdf);
    $pdf .= "xref\n0 6\n";
    $pdf .= "0000000000 65535 f \n";
    foreach ($offsets as $offset) {
        $pdf .= sprintf("%010d 00000 n \n", $offset);
    }
    $pdf .= "trailer\n<< /Size 6 /Root 1 0 R >>\nstartxref\n" . (string) $xref . "\n%%EOF";
    return $pdf;
}
