<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * PDF writer for tax invoices — layout mirrors sk_invoice_render_html().
 */

function sk_invoice_token_secret(): string {
    $CI =& get_instance();
    $key = (string)config_item('encryption_key');
    if ($key === '') {
        $CI->load->model('Sk_Admin_model');
        $settings = $CI->Sk_Admin_model->get_settings();
        $key = (string)($settings['askeva_api_token'] ?? '') . (string)($settings['site_name'] ?? '2DEAL');
    }
    return hash('sha256', 'invoice|' . $key);
}

function sk_invoice_public_token(int $orderId, string $orderNumber): string {
    return substr(hash_hmac('sha256', $orderId . '|' . $orderNumber, sk_invoice_token_secret()), 0, 32);
}

function sk_invoice_verify_token(int $orderId, string $orderNumber, string $token): bool {
    $expected = sk_invoice_public_token($orderId, $orderNumber);
    return $token !== '' && hash_equals($expected, $token);
}

function sk_invoice_public_url(array $order): string {
    $id = (int)($order['id'] ?? $order['order_id'] ?? 0);
    $num = (string)($order['order_number'] ?? '');
    $token = sk_invoice_public_token($id, $num);
    return site_url('invoice/download/' . $id . '/' . $token);
}

function sk_invoice_pdf_escape(string $s): string {
    return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $s);
}

function sk_invoice_pdf_sanitize(string $s): string {
    $map = [
        '–' => '-', '—' => '-', '−' => '-',
        '‘' => "'", '’' => "'", '“' => '"', '”' => '"',
        '₹' => 'Rs', 'Rs' => 'Rs', 'Rs.' => 'Rs', '€' => 'EUR', '£' => 'GBP',
        '•' => '-', '…' => '...', "\xC2\xA0" => ' ',
    ];
    $s = strtr($s, $map);
    if (function_exists('iconv')) {
        $converted = @iconv('UTF-8', 'Windows-1252//TRANSLIT//IGNORE', $s);
        if ($converted !== false) {
            $s = $converted;
        }
    }
    return trim((string) preg_replace('/[^\x20-\x7E\xA0-\xFF]/', '', $s));
}

function sk_invoice_pdf_text_width(string $text, float $size): float {
    return strlen(sk_invoice_pdf_sanitize($text)) * $size * 0.5;
}

function sk_invoice_pdf_text(float $x, float $y, string $text, float $size = 10, string $font = 'F1'): string {
    $safe = sk_invoice_pdf_escape(sk_invoice_pdf_sanitize($text));
    return sprintf("BT /%s %.1f Tf %.2f %.2f Td (%s) Tj ET\n", $font, $size, $x, $y, $safe);
}

function sk_invoice_pdf_text_right(float $xRight, float $y, string $text, float $size = 10, string $font = 'F1'): string {
    $w = sk_invoice_pdf_text_width($text, $size);
    return sk_invoice_pdf_text($xRight - $w, $y, $text, $size, $font);
}

function sk_invoice_pdf_text_center(float $cx, float $y, string $text, float $size = 10, string $font = 'F1'): string {
    $w = sk_invoice_pdf_text_width($text, $size);
    return sk_invoice_pdf_text($cx - ($w / 2), $y, $text, $size, $font);
}

/**
 * Convert logo file to JPEG bytes for PDF embedding (PNG/WebP/GIF/JPEG supported via GD).
 * @return array{data:string,w:int,h:int}|null
 */
function sk_invoice_pdf_logo_jpeg(?string $absPath): ?array {
    if ($absPath === null || $absPath === '' || !is_file($absPath) || !function_exists('imagecreatefromstring')) {
        return null;
    }
    $raw = @file_get_contents($absPath);
    if ($raw === false || $raw === '') {
        return null;
    }
    $img = @imagecreatefromstring($raw);
    if (!$img) {
        return null;
    }
    $w = imagesx($img);
    $h = imagesy($img);
    if ($w < 1 || $h < 1) {
        imagedestroy($img);
        return null;
    }
    // Flatten transparency onto white for JPEG.
    $canvas = imagecreatetruecolor($w, $h);
    $white = imagecolorallocate($canvas, 255, 255, 255);
    imagefilledrectangle($canvas, 0, 0, $w, $h, $white);
    imagecopy($canvas, $img, 0, 0, 0, 0, $w, $h);
    imagedestroy($img);

    ob_start();
    imagejpeg($canvas, null, 85);
    $jpeg = ob_get_clean();
    imagedestroy($canvas);
    if ($jpeg === false || $jpeg === '') {
        return null;
    }
    return ['data' => $jpeg, 'w' => $w, 'h' => $h];
}

function sk_invoice_pdf_line(float $x1, float $y1, float $x2, float $y2, float $width = 0.6): string {
    return sprintf("%.2f w %.2f %.2f m %.2f %.2f l S\n", $width, $x1, $y1, $x2, $y2);
}

function sk_invoice_pdf_rect(float $x, float $y, float $w, float $h, string $rgb = '0.94 0.96 0.99', bool $fill = true): string {
    $out = sprintf("%s rg %.2f %.2f %.2f %.2f re %s\n0 g\n", $rgb, $x, $y, $w, $h, $fill ? 'f' : 'S');
    return $out;
}

/**
 * Build A4 PDF matching the online HTML invoice layout.
 */
function sk_invoice_build_pdf(array $invoice): string {
    $pageW = 595.28;
    $pageH = 841.89;
    $L = 36;
    $R = $pageW - 36;
    $W = $R - $L;

    // Table columns (match HTML: # Item HSN Qty Rate Amount)
    $cNo   = $L + 4;
    $cItem = $L + 28;
    $cHsn  = $L + 268;
    $cQty  = $L + 340;
    $cRate = $L + 400;
    $cAmt  = $R - 4;

    $cur = sk_invoice_pdf_sanitize((string)($invoice['currency'] ?? sk_currency_symbol()));
    $seller = $invoice['seller'] ?? [];
    $buyer  = $invoice['buyer'] ?? [];

    $pages = [];
    $ops = '';
    $y = $pageH - 36;

    $flush = function () use (&$pages, &$ops) {
        $pages[] = $ops;
        $ops = '';
    };

    $need = function (float $h) use (&$y, &$ops, $flush, $pageH, $L) {
        if ($y - $h < 48) {
            $flush();
            $y = $pageH - 40;
            $ops .= sk_invoice_pdf_text($L, $y, 'TAX INVOICE (continued)', 11, 'F2');
            $y -= 20;
        }
    };

    // ── Letterhead header (website logo + company + address) ──
    $companyName = sk_invoice_pdf_sanitize((string)($seller['name'] ?? 'GOLDEN 2 DEAL (M) SDN. BHD.'));
    $registration = sk_invoice_pdf_sanitize((string)($seller['registration'] ?? ''));
    $companyLine = $registration !== '' ? ($companyName . ' ' . $registration) : $companyName;

    $logoJpeg = null;
    $logoPath = (string)($seller['logo_path'] ?? '');
    if ($logoPath === '' && function_exists('sk_invoice_logo_paths')) {
        $lp = sk_invoice_logo_paths($seller['logo'] ?? null);
        $logoPath = (string)($lp['path'] ?? '');
    }
    $logoJpeg = sk_invoice_pdf_logo_jpeg($logoPath !== '' ? $logoPath : null);
    $hasLogo = is_array($logoJpeg);

    if ($hasLogo) {
        // Fit logo into ~120x36 pt box, keep aspect ratio.
        $maxW = 120.0;
        $maxH = 36.0;
        $scale = min($maxW / max(1, $logoJpeg['w']), $maxH / max(1, $logoJpeg['h']));
        $drawW = $logoJpeg['w'] * $scale;
        $drawH = $logoJpeg['h'] * $scale;
        $ops .= sprintf("q %.2f 0 0 %.2f %.2f %.2f cm /ImLogo Do Q\n", $drawW, $drawH, $L, $y - $drawH + 8);
        $y -= ($drawH + 6);
    }

    $ops .= sk_invoice_pdf_text($L, $y, $companyLine, 12, 'F2');
    $ops .= sk_invoice_pdf_text_right($R, $y, 'INVOICE', 14, 'F2');
    $y -= 14;

    $addrLines = preg_split("/\r\n|\n|\r/", (string)($seller['address'] ?? ''));
    $addrLines = array_values(array_filter(array_map('trim', $addrLines ?: [])));
    if ($addrLines) {
        $ops .= sk_invoice_pdf_text($L, $y, sk_invoice_pdf_sanitize($addrLines[0]), 8);
    }
    $ops .= sk_invoice_pdf_text_right($R, $y, 'INVOICE NO:', 8);
    $y -= 11;
    if (!empty($addrLines[1])) {
        $ops .= sk_invoice_pdf_text($L, $y, sk_invoice_pdf_sanitize($addrLines[1]), 8);
    }
    $ops .= sk_invoice_pdf_text_right($R, $y, (string)($invoice['invoice_no'] ?? ''), 11, 'F2');
    $y -= 11;

    $contactBits = array_filter([
        trim((string)($seller['phone'] ?? '')),
        trim((string)($seller['email'] ?? '')),
    ]);
    if ($contactBits) {
        $ops .= sk_invoice_pdf_text($L, $y, sk_invoice_pdf_sanitize(implode('    ', $contactBits)), 8);
    }
    $ops .= sk_invoice_pdf_text_right($R, $y, 'Order: ' . (string)($invoice['order_number'] ?? ''), 9);
    $y -= 11;
    $ops .= sk_invoice_pdf_text_right($R, $y, (string)($invoice['invoice_date'] ?? ''), 9);
    $y -= 8;
    $ops .= sk_invoice_pdf_line($L, $y, $R, $y, 1.2);
    $y -= 18;

    // ── Bill To + Payment cards ──
    $need(70);
    $boxW = ($W - 12) / 2;
    $boxH = 72;
    $ops .= sk_invoice_pdf_rect($L, $y - $boxH + 12, $boxW, $boxH, '0.97 0.98 0.99', true);
    $ops .= sk_invoice_pdf_rect($L + $boxW + 12, $y - $boxH + 12, $boxW, $boxH, '0.97 0.98 0.99', true);

    $ops .= sk_invoice_pdf_text($L + 8, $y, 'BILL TO', 8, 'F2');
    $ops .= sk_invoice_pdf_text($L + $boxW + 20, $y, 'PAYMENT DETAILS', 8, 'F2');
    $y -= 13;

    $buyerName = (string)($buyer['name'] ?? $buyer['person'] ?? 'Customer');
    $ops .= sk_invoice_pdf_text($L + 8, $y, $buyerName, 10, 'F2');
    $ops .= sk_invoice_pdf_text($L + $boxW + 20, $y, 'Method: ' . (string)($invoice['payment_method'] ?? ''), 9);
    $y -= 12;

    $leftY = $y;
    $rightY = $y;
    if (!empty($buyer['phone'])) {
        $ops .= sk_invoice_pdf_text($L + 8, $leftY, 'Phone: ' . (string)$buyer['phone'], 8);
        $leftY -= 11;
    }
    $ops .= sk_invoice_pdf_text($L + $boxW + 20, $rightY, 'Status: ' . (string)($invoice['payment_status'] ?? ''), 9);
    $rightY -= 11;
    $ops .= sk_invoice_pdf_text($L + $boxW + 20, $rightY, 'Order: ' . (string)($invoice['order_status'] ?? ''), 9);
    $rightY -= 11;
    $ops .= sk_invoice_pdf_text($L + $boxW + 20, $rightY, 'Date: ' . (string)($invoice['order_date'] ?? $invoice['invoice_date'] ?? ''), 8);

    $addrParts = array_filter([
        trim((string)($buyer['line1'] ?? '')),
        trim((string)($buyer['line2'] ?? '')),
        trim(implode(', ', array_filter([(string)($buyer['city'] ?? ''), (string)($buyer['state'] ?? '')]))),
        trim(trim((string)($buyer['pincode'] ?? '')) . (!empty($buyer['country']) ? ' ' . $buyer['country'] : '')),
    ]);
    foreach ($addrParts as $part) {
        $ops .= sk_invoice_pdf_text($L + 8, $leftY, $part, 8);
        $leftY -= 11;
    }
    $y = min($leftY, $rightY) - 16;

    // ── Items table header ──
    $need(30);
    $ops .= sk_invoice_pdf_rect($L, $y - 4, $W, 18, '0.06 0.09 0.16', true);
    $ops .= "1 1 1 rg\n";
    $ops .= sk_invoice_pdf_text($cNo, $y, '#', 8, 'F2');
    $ops .= sk_invoice_pdf_text($cItem, $y, 'Item', 8, 'F2');
    $ops .= sk_invoice_pdf_text_center($cHsn + 20, $y, 'HSN', 8, 'F2');
    $ops .= sk_invoice_pdf_text_center($cQty + 10, $y, 'Qty', 8, 'F2');
    $ops .= sk_invoice_pdf_text_right($cRate + 28, $y, 'Rate', 8, 'F2');
    $ops .= sk_invoice_pdf_text_right($cAmt, $y, 'Amount', 8, 'F2');
    $ops .= "0 g\n";
    $y -= 20;

    $items = $invoice['items'] ?? [];
    foreach ($items as $i => $item) {
        $need(28);
        $name = sk_invoice_pdf_sanitize((string)($item['name'] ?? 'Item'));
        $sku  = sk_invoice_pdf_sanitize((string)($item['sku'] ?? ''));
        $hsn  = sk_invoice_pdf_sanitize((string)($item['hsn'] ?? '-'));
        if ($hsn === '' || $hsn === '—' || $hsn === '?') {
            $hsn = '-';
        }
        $qty  = (string)(int)($item['qty'] ?? 1);
        $rate = $cur . number_format((float)($item['price'] ?? 0), 2);
        $amt  = $cur . number_format((float)($item['subtotal'] ?? 0), 2);

        // zebra
        if ($i % 2 === 1) {
            $ops .= sk_invoice_pdf_rect($L, $y - 4, $W, 16, '0.98 0.99 1', true);
        }

        $ops .= sk_invoice_pdf_text($cNo, $y, (string)($i + 1), 8);
        $nameLine = strlen($name) > 42 ? substr($name, 0, 42) . '...' : $name;
        $ops .= sk_invoice_pdf_text($cItem, $y, $nameLine, 8, 'F2');
        $ops .= sk_invoice_pdf_text_center($cHsn + 20, $y, $hsn, 8);
        $ops .= sk_invoice_pdf_text_center($cQty + 10, $y, $qty, 8);
        $ops .= sk_invoice_pdf_text_right($cRate + 28, $y, $rate, 8);
        $ops .= sk_invoice_pdf_text_right($cAmt, $y, $amt, 8, 'F2');
        $y -= 12;
        if ($sku !== '') {
            $ops .= sk_invoice_pdf_text($cItem, $y, 'SKU: ' . $sku, 7);
            $y -= 11;
        } else {
            $y -= 4;
        }
        $ops .= sk_invoice_pdf_line($L, $y + 2, $R, $y + 2, 0.3);
        $y -= 8;
    }

    if (!$items) {
        $ops .= sk_invoice_pdf_text($cItem, $y, 'No items', 9);
        $y -= 16;
    }

    // ── Totals (right side, like HTML tfoot) ──
    $need(100);
    $y -= 4;
    $ops .= sk_invoice_pdf_line($L, $y, $R, $y, 0.8);
    $y -= 16;

    $addTotal = function (string $label, string $value, bool $bold = false) use (&$ops, &$y, $R) {
        $font = $bold ? 'F2' : 'F1';
        $size = $bold ? 11 : 9;
        $ops .= sk_invoice_pdf_text_right($R - 120, $y, $label, $size, $font);
        $ops .= sk_invoice_pdf_text_right($R, $y, $value, $size, $font);
        $y -= 14;
    };

    $addTotal('Subtotal', $cur . number_format((float)($invoice['subtotal'] ?? 0), 2));
    if (!empty($invoice['discount'])) {
        $addTotal('Discount', '-' . $cur . number_format((float)$invoice['discount'], 2));
    }
    $taxable = (float)($invoice['taxable_amount'] ?? max(0, (float)($invoice['subtotal'] ?? 0) - (float)($invoice['discount'] ?? 0)));
    $addTotal('Taxable Value', $cur . number_format($taxable, 2));

    $tax = (float)($invoice['tax'] ?? 0);
    $g = $invoice['gst'] ?? [];
    $taxAmt = (float)(($g['cgst'] ?? 0) + ($g['sgst'] ?? 0) + ($g['igst'] ?? 0));
    if ($taxAmt <= 0) {
        $taxAmt = $tax;
    }
    if ($taxAmt > 0) {
        $rate = !empty($g['rate']) ? ' @ ' . rtrim(rtrim(number_format((float)$g['rate'], 2), '0'), '.') . '%' : '';
        $addTotal('Tax' . $rate, $cur . number_format($taxAmt, 2));
    }

    $shipVal = ((float)($invoice['shipping'] ?? 0) == 0)
        ? 'Free'
        : ($cur . number_format((float)$invoice['shipping'], 2));
    $addTotal('Shipping', $shipVal);

    $y -= 2;
    $ops .= sk_invoice_pdf_rect($L + $W - 220, $y - 4, 220, 20, '0.97 0.98 0.99', true);
    $addTotal('Grand Total', $cur . number_format((float)($invoice['total'] ?? 0), 2), true);

    $y -= 10;
    $need(40);
    $ops .= sk_invoice_pdf_line($L, $y, $R, $y, 0.5);
    $y -= 14;
    $footer = sk_invoice_pdf_sanitize((string)($seller['invoice_footer'] ?? 'Thank you for your business.'));
    $ops .= sk_invoice_pdf_text_center(($L + $R) / 2, $y, $footer, 8);
    $y -= 12;
    $ops .= sk_invoice_pdf_text_center(($L + $R) / 2, $y, 'This is a computer-generated tax invoice.', 7);

    $flush();

    // ── Assemble PDF ──
    $pageCount = count($pages);
    $objects = [];
    $objects[1] = "1 0 obj<< /Type /Catalog /Pages 2 0 R >>endobj\n";
    $objects[6] = "6 0 obj<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>endobj\n";
    $objects[7] = "7 0 obj<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>endobj\n";

    $logoObjNum = null;
    if (!empty($hasLogo) && is_array($logoJpeg)) {
        $logoObjNum = 5;
        $jpeg = $logoJpeg['data'];
        $jw = (int)$logoJpeg['w'];
        $jh = (int)$logoJpeg['h'];
        $jlen = strlen($jpeg);
        $objects[5] = "5 0 obj<< /Type /XObject /Subtype /Image /Width {$jw} /Height {$jh} /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length {$jlen} >>stream\n"
            . $jpeg
            . "\nendstream endobj\n";
    }

    $kids = [];
    $objNum = 8;
    $pageObjs = [];
    $contentObjs = [];
    foreach ($pages as $i => $_) {
        $pageObjs[$i] = $objNum++;
        $contentObjs[$i] = $objNum++;
        $kids[] = $pageObjs[$i] . ' 0 R';
    }
    $objects[2] = '2 0 obj<< /Type /Pages /Kids [' . implode(' ', $kids) . '] /Count ' . $pageCount . " >>endobj\n";

    foreach ($pages as $i => $content) {
        $len = strlen($content);
        $p = $pageObjs[$i];
        $c = $contentObjs[$i];
        $xobjects = ($logoObjNum !== null) ? " /XObject << /ImLogo {$logoObjNum} 0 R >>" : '';
        $objects[$p] = "{$p} 0 obj<< /Type /Page /Parent 2 0 R /MediaBox [0 0 {$pageW} {$pageH}] /Contents {$c} 0 R /Resources << /Font << /F1 6 0 R /F2 7 0 R >>{$xobjects} >> >>endobj\n";
        $objects[$c] = "{$c} 0 obj<< /Length {$len} >>stream\n{$content}\nendstream endobj\n";
    }

    ksort($objects);
    $pdf = "%PDF-1.4\n";
    $offsets = [0];
    $maxObj = max(array_keys($objects));
    for ($i = 1; $i <= $maxObj; $i++) {
        if (!isset($objects[$i])) {
            $objects[$i] = "{$i} 0 obj<< >>endobj\n";
        }
        $offsets[$i] = strlen($pdf);
        $pdf .= $objects[$i];
    }
    $xref = strlen($pdf);
    $count = $maxObj + 1;
    $pdf .= "xref\n0 {$count}\n0000000000 65535 f \n";
    for ($i = 1; $i <= $maxObj; $i++) {
        $pdf .= sprintf("%010d 00000 n \n", $offsets[$i]);
    }
    $pdf .= "trailer<< /Size {$count} /Root 1 0 R >>\nstartxref\n{$xref}\n%%EOF";
    return $pdf;
}
