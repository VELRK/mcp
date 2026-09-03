<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Split order.discount into affiliate promo, regular promo, and wallet payment parts.
 */
function sk_order_discount_breakdown(array $order, array $settings = []): array {
    $total = round((float)($order['discount'] ?? 0), 2);

    $wallet = 0.0;
    if (array_key_exists('wallet_discount', $order)) {
        $wallet = round((float)$order['wallet_discount'], 2);
    } elseif (preg_match('/\[Wallet discount:\s*([\d.]+)\]/', (string)($order['notes'] ?? ''), $m)) {
        $wallet = round((float)$m[1], 2);
    }
    $wallet = min(max(0, $wallet), $total);

    $affiliate = 0.0;
    if (!empty($order['affiliate_promo'])) {
        if (array_key_exists('affiliate_discount', $order)) {
            $affiliate = round((float)$order['affiliate_discount'], 2);
        } else {
            $affiliate = round(max(0, $total - $wallet), 2);
        }
    }

    $promo = 0.0;
    $promoCode = '';
    if ($affiliate <= 0 && !empty($order['promo_code'])) {
        $promo = round(max(0, $total - $wallet), 2);
        $promoCode = (string)$order['promo_code'];
    }

    $walletPct = (float)($settings['customer_wallet_discount_percent'] ?? 0);

    return [
        'total'            => $total,
        'affiliate'        => $affiliate,
        'affiliate_promo'  => (string)($order['affiliate_promo'] ?? ''),
        'promo'            => $promo,
        'promo_code'       => $promoCode,
        'wallet'           => $wallet,
        'wallet_percent'   => $walletPct > 0 ? $walletPct : null,
    ];
}

/** Human-readable payment method for invoices and emails. */
function sk_invoice_payment_method_label(array $order): string {
    $method = strtolower(trim($order['payment_method'] ?? 'cod'));
    $wallet = round((float)($order['wallet_amount'] ?? 0), 2);
    $total  = round((float)($order['total'] ?? 0), 2);

    if ($wallet > 0 && $method === 'razorpay' && $wallet < $total) {
        return 'WALLET + RAZORPAY';
    }
    if ($wallet > 0 && ($method === 'wallet' || $wallet >= $total)) {
        return 'WALLET';
    }

    return strtoupper($method ?: 'COD');
}

/**
 * Build structured invoice data from an order row (+ items).
 */
function sk_invoice_build(array $order, array $settings = [], ?array $sellerOverride = null): array {
    $CI =& get_instance();
    $currency = sk_currency_symbol($settings);
    $taxRate  = (float)($settings['tax_rate'] ?? 18);

    $seller = $sellerOverride ?: sk_invoice_resolve_seller($order, $settings);

    $items = [];
    foreach ($order['items'] ?? [] as $row) {
        $hsn = $row['hsn_code'] ?? null;
        if (!$hsn && !empty($row['product_id'])) {
            $p = $CI->db->select('hsn_code, tax_code')->where('id', (int)$row['product_id'])->get('products')->row_array();
            $hsn = $p['hsn_code'] ?? ($p['tax_code'] ?? '—');
        }
        $qty   = (int)($row['quantity'] ?? 1);
        $price = (float)($row['price'] ?? $row['unit_price'] ?? 0);
        $line  = (float)($row['subtotal'] ?? ($price * $qty));
        $items[] = [
            'name'     => $row['product_name'] ?? 'Product',
            'sku'      => $row['product_sku'] ?? ($row['sku'] ?? ''),
            'hsn'      => $hsn ?: '—',
            'qty'      => $qty,
            'price'    => $price,
            'subtotal' => $line,
        ];
    }

    $subtotal  = (float)($order['subtotal'] ?? 0);
    $discount  = (float)($order['discount'] ?? 0);
    $shipping  = (float)($order['shipping'] ?? 0);
    $tax       = (float)($order['tax'] ?? 0);
    $total     = (float)($order['total'] ?? 0);
    $taxable   = max(0, $subtotal - $discount);

    if ($taxable > 0 && $tax > 0) {
        $taxRate = round($tax / $taxable * 100, 2);
    }

    $sellerState = strtoupper(trim($seller['state_code'] ?? $seller['state'] ?? ''));
    $buyerState  = strtoupper(trim($order['shipping_state'] ?? ''));
    $sameState   = $sellerState && $buyerState && (stripos($buyerState, $sellerState) !== false || stripos($sellerState, $buyerState) !== false);

    $gst = ['cgst' => 0, 'sgst' => 0, 'igst' => 0, 'rate' => $taxRate];
    if ($sameState) {
        $gst['cgst'] = round($tax / 2, 2);
        $gst['sgst'] = round($tax / 2, 2);
    } else {
        $gst['igst'] = $tax;
    }

    $promoLabel = '';
    if (!empty($order['affiliate_promo'])) {
        $promoLabel = $order['affiliate_promo'] . ' (Affiliate)';
    } elseif (!empty($order['promo_code'])) {
        $promoLabel = $order['promo_code'];
    }

    $discountBreakdown = sk_order_discount_breakdown($order, $settings);

    $invoiceNo = sk_invoice_number($order, $seller);

    return [
        'invoice_no'   => $invoiceNo,
        'order_number' => $order['order_number'] ?? ('#' . ($order['id'] ?? '')),
        'order_id'     => (int)($order['id'] ?? 0),
        'invoice_date' => date('d M Y', strtotime($order['created_at'] ?? 'now')),
        'order_date'   => date('d M Y, h:i A', strtotime($order['created_at'] ?? 'now')),
        'currency'     => $currency,
        'seller'       => $seller,
        'buyer'        => [
            'name'    => !empty($order['billing_company'])
                ? $order['billing_company']
                : ($order['billing_name'] ?? $order['customer_name'] ?? $order['shipping_name'] ?? ''),
            'person'  => $order['billing_name'] ?? $order['customer_name'] ?? $order['shipping_name'] ?? '',
            'company' => $order['billing_company'] ?? '',
            'email'   => $order['customer_email'] ?? '',
            'phone'   => $order['billing_phone'] ?? $order['shipping_phone'] ?? '',
            'line1'   => $order['billing_line1'] ?? $order['shipping_line1'] ?? '',
            'line2'   => $order['billing_line2'] ?? $order['shipping_line2'] ?? '',
            'city'    => $order['billing_city'] ?? $order['shipping_city'] ?? '',
            'state'   => $order['billing_state'] ?? $order['shipping_state'] ?? '',
            'pincode' => $order['billing_pincode'] ?? $order['shipping_pincode'] ?? '',
            'country' => $order['billing_country'] ?? $order['shipping_country'] ?? 'Malaysia',
        ],
        'items'          => $items,
        'subtotal'       => $subtotal,
        'discount'       => $discount,
        'discount_breakdown' => $discountBreakdown,
        'promo_code'     => $promoLabel,
        'shipping'       => $shipping,
        'tax'            => $tax,
        'taxable_amount' => $taxable,
        'gst'            => $gst,
        'total'          => $total,
        'payment_method' => sk_invoice_payment_method_label($order),
        'wallet_amount'  => (float)($order['wallet_amount'] ?? 0),
        'payment_status' => ucfirst($order['payment_status'] ?? 'pending'),
        'order_status'   => ucfirst($order['status'] ?? 'pending'),
        'notes'          => $order['notes'] ?? '',
    ];
}

/**
 * Resolve seller details for invoices.
 * Platform letterhead (Settings / GOLDEN 2 DEAL defaults) is always used so invoices
 * show the registered company name, SSM/tax IDs, address, phone and email.
 */
function sk_invoice_resolve_seller(array $order, array $settings): array {
    return sk_invoice_seller_from_settings($settings);
}

/** Resolve invoice logo: admin site_logo if file exists, else website storefront logo. */
function sk_invoice_logo_paths(?string $preferred = null): array {
    $candidates = [];
    $pref = trim((string)$preferred);
    if ($pref !== '') {
        if (preg_match('#^https?://#i', $pref)) {
            return ['url' => $pref, 'path' => '', 'rel' => ''];
        }
        $candidates[] = ltrim(str_replace('\\', '/', $pref), '/');
    }
    $candidates[] = 'frontend/assets/logo/logo.png';
    $candidates[] = 'frontend/amercereactjs/public/assets/logo/logo.png';
    $candidates[] = 'assets/logo/logo.png';

    $root = rtrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, FCPATH), DIRECTORY_SEPARATOR);
    foreach ($candidates as $rel) {
        $rel = ltrim(str_replace('\\', '/', $rel), '/');
        $abs = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel);
        if (is_file($abs)) {
            return [
                'url'  => base_url($rel),
                'path' => $abs,
                'rel'  => $rel,
            ];
        }
    }

    $fallbackRel = 'frontend/assets/logo/logo.png';
    return [
        'url'  => base_url($fallbackRel),
        'path' => '',
        'rel'  => $fallbackRel,
    ];
}

/** Registered company letterhead used on invoices when Settings fields are empty. */
function sk_invoice_platform_defaults(): array {
    return [
        'name'    => 'GOLDEN 2 DEAL (M) SDN. BHD.',
        'gstin'   => '202101029427',
        'pan'     => '1429727-A',
        'email'   => 'golden2deal@gmail.com',
        'phone'   => '03-6242 2232',
        'address' => "Lot No. 2A/9(B) Anzen Business Park, No 3-9, Jalan 4/37A , Kawasan Industri\nTaman Bukit Maluri, 52100 Kepong Kuala Lumpur.",
    ];
}

function sk_invoice_seller_from_settings(array $settings): array {
    $defaults = sk_invoice_platform_defaults();

    $gstin = trim((string)($settings['gstin'] ?? '')) ?: $defaults['gstin'];
    $pan   = trim((string)($settings['pan_no'] ?? '')) ?: $defaults['pan'];
    $regParts = array_filter([$gstin, $pan]);
    // Format: "202101029427 (1429727-A)" when both Tax ID + registration no. set.
    $registration = '';
    if (count($regParts) === 2) {
        $registration = $regParts[0] . ' (' . $regParts[1] . ')';
    } elseif (count($regParts) === 1) {
        $registration = $regParts[0];
    }

    $name = trim((string)($settings['company_legal_name'] ?? ''));
    if ($name === '') {
        $name = trim((string)($settings['site_name'] ?? ''));
    }
    if ($name === '' || strcasecmp($name, '2DEAL') === 0 || strcasecmp($name, 'Default Store') === 0
        || stripos($name, 'shopkart') !== false) {
        $name = $defaults['name'];
    }

    $email = trim((string)($settings['site_email'] ?? ''));
    $emailLower = strtolower($email);
    if ($email === '' || preg_match('/@(shopkart\.(com|app)|example\.(com|org)|test\.com)$/', $emailLower)) {
        $email = $defaults['email'];
    }

    $phone = trim((string)($settings['site_phone'] ?? ''));
    $phoneDigits = preg_replace('/\D+/', '', $phone);
    // Only replace empty / known India demo number — keep any phone the admin saved.
    if ($phone === '' || $phoneDigits === '9876543210' || $phoneDigits === '919876543210') {
        $phone = $defaults['phone'];
    }

    $address = trim((string)($settings['site_address'] ?? ''));
    $addressLower = strtolower($address);
    if ($address === '' || strpos($addressLower, 'mumbai') !== false
        || strpos($addressLower, '123 main street') !== false) {
        $address = $defaults['address'];
    }

    $logo = sk_invoice_logo_paths($settings['site_logo'] ?? null);

    return [
        'name'            => $name,
        'logo'            => $logo['rel'] !== '' ? $logo['rel'] : ($settings['site_logo'] ?? ''),
        'logo_url'        => $logo['url'],
        'logo_path'       => $logo['path'],
        'gstin'           => $gstin,
        'pan'             => $pan,
        'registration'    => $registration,
        'state_code'      => $settings['state_code'] ?? '',
        'email'           => $email,
        'phone'           => $phone,
        'address'         => $address,
        'invoice_prefix'  => $settings['invoice_prefix'] ?? 'INV',
        'invoice_footer'  => $settings['invoice_footer'] ?? 'Thank you for your business.',
        'source'          => 'platform',
    ];
}

function sk_invoice_seller_from_vendor(array $vendor, array $store, array $settings = []): array {
    $platform = sk_invoice_seller_from_settings($settings);

    $storeName = trim((string)($store['store_name'] ?? ''));
    if ($storeName === '' || strcasecmp($storeName, 'Default Store') === 0) {
        $storeName = '';
    }
    $bizName = trim((string)($vendor['business_name'] ?? ''));
    $sellerName = $storeName !== ''
        ? $storeName
        : ($bizName !== '' ? $bizName : ($platform['name'] ?? '2DEAL'));

    $addrParts = array_filter([
        $store['pickup_line1'] ?? '',
        $store['pickup_line2'] ?? '',
        trim(($store['pickup_city'] ?? '') . ', ' . ($store['pickup_state'] ?? '') . ' - ' . ($store['pickup_pincode'] ?? '')),
        $store['pickup_country'] ?? '',
    ]);
    $address = implode("\n", $addrParts);
    if (trim($address) === '') {
        $address = (string)($platform['address'] ?? '');
    }

    $email = trim((string)($store['contact_email'] ?? $vendor['email'] ?? ''));
    if ($email === '') {
        $email = (string)($platform['email'] ?? '');
    }
    $phone = trim((string)($store['contact_phone'] ?? $vendor['phone'] ?? ''));
    if ($phone === '') {
        $phone = (string)($platform['phone'] ?? '');
    }

    $gst = trim((string)($store['gst_vat'] ?? ''));
    $pan = trim((string)($store['pan_no'] ?? ''));
    $registration = $gst !== '' ? $gst : (string)($platform['registration'] ?? '');

    $logo = sk_invoice_logo_paths(!empty($store['logo']) ? $store['logo'] : ($settings['site_logo'] ?? null));

    return [
        'name'           => $sellerName,
        'logo'           => $logo['rel'] !== '' ? $logo['rel'] : ($store['logo'] ?? $platform['logo'] ?? ''),
        'logo_url'       => $logo['url'] ?: ($platform['logo_url'] ?? ''),
        'logo_path'      => $logo['path'] ?: ($platform['logo_path'] ?? ''),
        'gstin'          => $gst !== '' ? $gst : (string)($platform['gstin'] ?? ''),
        'pan'            => $pan !== '' ? $pan : (string)($platform['pan'] ?? ''),
        'registration'   => $registration,
        'state_code'     => $store['state_code'] ?? ($store['pickup_state'] ?? ($platform['state_code'] ?? '')),
        'state'          => $store['pickup_state'] ?? '',
        'email'          => $email,
        'phone'          => $phone,
        'address'        => $address,
        'invoice_prefix' => trim((string)($store['invoice_prefix'] ?? '')) !== ''
            ? $store['invoice_prefix']
            : ($platform['invoice_prefix'] ?? 'INV'),
        'invoice_footer' => trim((string)($store['invoice_footer'] ?? '')) !== ''
            ? $store['invoice_footer']
            : ($platform['invoice_footer'] ?? 'Thank you for shopping with us.'),
        'source'         => 'vendor',
        'vendor_id'      => (int)$vendor['id'],
    ];
}

function sk_invoice_number(array $order, array $seller): string {
    $prefix = preg_replace('/[^A-Z0-9\-]/i', '', $seller['invoice_prefix'] ?? 'INV') ?: 'INV';
    $id     = max(1, (int)($order['id'] ?? 0));
    // Sequential display: order #1 → INV-100001, #2 → INV-100002, …
    $seq    = 100000 + $id;
    return strtoupper($prefix) . '-' . $seq;
}

/** Build one or more discount rows for invoices/emails/admin views. */
function sk_invoice_discount_rows_html(array $invoiceOrOrder, string $currencyHtml, string $colspan = '5', string $style = ''): string {
    $bd = $invoiceOrOrder['discount_breakdown'] ?? null;
    if (!$bd && !empty($invoiceOrOrder['discount'])) {
        $bd = sk_order_discount_breakdown($invoiceOrOrder);
    }
    if (!$bd || ($bd['total'] ?? 0) <= 0) {
        return '';
    }

    $rows = [];
    $cellStyle = $style !== '' ? $style : 'padding:8px;text-align:right;color:#16a34a;';

    if (($bd['affiliate'] ?? 0) > 0) {
        $label = 'Discount (' . htmlspecialchars($bd['affiliate_promo'] ?: 'Affiliate') . ' Affiliate)';
        $rows[] = "<tr><td colspan='{$colspan}' style='{$cellStyle}'>{$label}</td>"
            . "<td style='{$cellStyle}'>-{$currencyHtml}" . number_format($bd['affiliate'], 2) . '</td></tr>';
    } elseif (($bd['promo'] ?? 0) > 0) {
        $label = 'Discount (' . htmlspecialchars($bd['promo_code'] ?: 'Promo') . ')';
        $rows[] = "<tr><td colspan='{$colspan}' style='{$cellStyle}'>{$label}</td>"
            . "<td style='{$cellStyle}'>-{$currencyHtml}" . number_format($bd['promo'], 2) . '</td></tr>';
    }

    if (($bd['wallet'] ?? 0) > 0) {
        $walletLabel = 'Wallet payment discount';
        if (!empty($bd['wallet_percent'])) {
            $walletLabel .= ' (' . rtrim(rtrim(number_format((float)$bd['wallet_percent'], 2), '0'), '.') . '%)';
        }
        $rows[] = "<tr><td colspan='{$colspan}' style='{$cellStyle}'>" . htmlspecialchars($walletLabel) . '</td>'
            . "<td style='{$cellStyle}'>-{$currencyHtml}" . number_format($bd['wallet'], 2) . '</td></tr>';
    }

    if (!$rows) {
        $label = !empty($invoiceOrOrder['promo_code'])
            ? 'Discount (' . htmlspecialchars($invoiceOrOrder['promo_code']) . ')'
            : 'Discount';
        $rows[] = "<tr><td colspan='{$colspan}' style='{$cellStyle}'>{$label}</td>"
            . "<td style='{$cellStyle}'>-{$currencyHtml}" . number_format($bd['total'], 2) . '</td></tr>';
    }

    return implode('', $rows);
}

/** Render printable / emailable invoice HTML. */
function sk_invoice_render_html(array $invoice, bool $forEmail = false): string {
    $s = $invoice['seller'];
    $b = $invoice['buyer'];
    $cur = htmlspecialchars($invoice['currency']);
    $logoHtml = '';
    $logoUrl = !empty($s['logo_url']) ? $s['logo_url'] : '';
    if ($logoUrl === '' && !empty($s['logo'])) {
        $logoUrl = strpos($s['logo'], 'http') === 0 ? $s['logo'] : base_url($s['logo']);
    }
    if ($logoUrl === '') {
        $logoUrl = base_url('frontend/assets/logo/logo.png');
    }
    $logoHtml = "<img src='" . htmlspecialchars($logoUrl) . "' alt='Logo' style='max-height:64px;max-width:200px;object-fit:contain;margin-bottom:10px;display:block;'>";

    $itemsRows = '';
    foreach ($invoice['items'] as $i => $item) {
        $itemsRows .= '<tr>'
            . '<td style="padding:10px 8px;border-bottom:1px solid #e2e8f0;">' . ($i + 1) . '</td>'
            . '<td style="padding:10px 8px;border-bottom:1px solid #e2e8f0;"><strong>' . htmlspecialchars($item['name']) . '</strong>'
            . ($item['sku'] ? '<br><small style="color:#64748b;">SKU: ' . htmlspecialchars($item['sku']) . '</small>' : '') . '</td>'
            . '<td style="padding:10px 8px;border-bottom:1px solid #e2e8f0;text-align:center;">' . htmlspecialchars($item['hsn']) . '</td>'
            . '<td style="padding:10px 8px;border-bottom:1px solid #e2e8f0;text-align:center;">' . (int)$item['qty'] . '</td>'
            . '<td style="padding:10px 8px;border-bottom:1px solid #e2e8f0;text-align:right;">' . $cur . number_format($item['price'], 2) . '</td>'
            . '<td style="padding:10px 8px;border-bottom:1px solid #e2e8f0;text-align:right;font-weight:600;">' . $cur . number_format($item['subtotal'], 2) . '</td>'
            . '</tr>';
    }

    $discountRow = sk_invoice_discount_rows_html($invoice, $cur);

    $gstRows = '';
    $g = $invoice['gst'];
    $taxAmt = (float)($g['cgst'] + $g['sgst'] + $g['igst']);
    if ($taxAmt <= 0 && !empty($invoice['tax'])) {
        $taxAmt = (float)$invoice['tax'];
    }
    if ($taxAmt > 0) {
        $rateLabel = !empty($g['rate']) ? ' @ ' . rtrim(rtrim(number_format((float)$g['rate'], 2), '0'), '.') . '%' : '';
        $gstRows .= "<tr><td colspan='5' style='padding:6px 8px;text-align:right;color:#64748b;'>Tax{$rateLabel}</td>"
            . "<td style='padding:6px 8px;text-align:right;'>{$cur}" . number_format($taxAmt, 2) . '</td></tr>';
    }

    $shipLabel = $invoice['shipping'] == 0 ? '<span style="color:#16a34a;">Free</span>' : $cur . number_format($invoice['shipping'], 2);

    $sellerMeta = array_filter([
        !empty($s['phone']) ? htmlspecialchars($s['phone']) : '',
        !empty($s['email']) ? htmlspecialchars($s['email']) : '',
    ]);
    $sellerMetaHtml = implode(' &nbsp;&nbsp; ', $sellerMeta);
    $companyLine = htmlspecialchars($s['name']);
    if (!empty($s['registration'])) {
        $companyLine .= ' ' . htmlspecialchars($s['registration']);
    }

    $buyerNameLines = [];
    if (!empty($b['company'])) {
        $buyerNameLines[] = htmlspecialchars($b['company']);
        if (!empty($b['person']) && $b['person'] !== $b['company']) {
            $buyerNameLines[] = 'Attn: ' . htmlspecialchars($b['person']);
        }
    } else {
        $buyerNameLines[] = htmlspecialchars($b['name'] ?: ($b['person'] ?? ''));
    }
    $buyerAddr = array_filter(array_merge($buyerNameLines, [
        htmlspecialchars($b['phone'] ?? ''),
        htmlspecialchars($b['line1'] ?? ''),
        htmlspecialchars($b['line2'] ?? ''),
        htmlspecialchars(trim(($b['city'] ?? '') . ', ' . ($b['state'] ?? '') . ' - ' . ($b['pincode'] ?? ''))),
        htmlspecialchars($b['country'] ?? ''),
    ]));
    $buyerAddrHtml = implode('<br>', $buyerAddr);

    $walletPayHtml = '';
    $walletPaid = (float)($invoice['wallet_amount'] ?? 0);
    if ($walletPaid > 0) {
        $walletPayHtml = "<div><strong>Wallet:</strong> {$cur}" . number_format($walletPaid, 2) . '</div>';
    }
    $onlinePaid = max(0, (float)$invoice['total'] - $walletPaid);
    if ($onlinePaid > 0.009 && $walletPaid > 0) {
        $methodLabel = strtolower((string)($invoice['payment_method'] ?? '')) === 'cod' ? 'COD' : 'Online';
        $walletPayHtml .= "<div><strong>{$methodLabel} due:</strong> {$cur}" . number_format($onlinePaid, 2) . '</div>';
    }

    $printBtns = $forEmail ? '' : "
    <div class='no-print' style='text-align:center;padding:12px;background:#f8fafc;border-bottom:1px solid #e2e8f0;'>
      <button onclick='window.print()' style='background:#f59e0b;color:#fff;border:none;padding:8px 20px;border-radius:6px;cursor:pointer;font-weight:600;'>Print / Save PDF</button>
      <button onclick='window.close()' style='background:#64748b;color:#fff;border:none;padding:8px 20px;border-radius:6px;cursor:pointer;margin-left:8px;'>Close</button>
    </div>";

    $CI =& get_instance();
    $CI->load->helper('sk_invoice_pdf');
    $invoiceOrder = [
        'id'           => (int)$invoice['order_id'],
        'order_number' => (string)$invoice['order_number'],
    ];
    $invoiceUrl = sk_invoice_public_url($invoiceOrder);
    $invoiceViewUrl = site_url('invoice/view/' . (int)$invoice['order_id'] . '/' . sk_invoice_public_token((int)$invoice['order_id'], (string)$invoice['order_number']));

    return "<!DOCTYPE html><html lang='en'><head><meta charset='UTF-8'><meta name='viewport' content='width=device-width,initial-scale=1'>
<title>Tax Invoice – {$invoice['invoice_no']}</title>
<style>
  body{margin:0;padding:0;font-family:Arial,Helvetica,sans-serif;font-size:13px;color:#1e293b;background:#f1f5f9;}
  .wrap{max-width:820px;margin:20px auto;background:#fff;box-shadow:0 4px 24px rgba(0,0,0,.08);border-radius:8px;overflow:hidden;}
  @media print{.no-print{display:none!important}body{background:#fff}.wrap{box-shadow:none;margin:0;max-width:100%}}
</style></head><body>
{$printBtns}
<div class='wrap'>
  <div style='background:#fff;color:#111;padding:24px 32px 18px;border-bottom:2px solid #111;display:flex;justify-content:space-between;align-items:flex-start;gap:20px;flex-wrap:wrap;'>
    <div style='flex:1;min-width:260px;'>
      {$logoHtml}
      <div style='font-size:16px;font-weight:700;letter-spacing:.2px;text-transform:uppercase;'>{$companyLine}</div>
      " . (!empty($s['address']) ? "<div style='margin-top:6px;font-size:12px;color:#333;line-height:1.55;white-space:pre-line;'>" . htmlspecialchars($s['address']) . '</div>' : '') . "
      " . ($sellerMetaHtml ? "<div style='margin-top:6px;font-size:12px;color:#333;'>{$sellerMetaHtml}</div>" : '') . "
    </div>
    <div style='text-align:right;min-width:180px;'>
      <div style='font-size:22px;font-weight:700;letter-spacing:1px;'>INVOICE</div>
      <div style='margin-top:10px;font-size:12px;color:#555;'>INVOICE NO:</div>
      <div style='font-size:15px;font-weight:700;'>" . htmlspecialchars($invoice['invoice_no']) . "</div>
      <div style='margin-top:8px;font-size:12px;color:#555;'>Order: " . htmlspecialchars($invoice['order_number']) . "</div>
      <div style='font-size:12px;color:#333;'>" . htmlspecialchars($invoice['invoice_date']) . "</div>
    </div>
  </div>

  <div style='padding:28px 32px;'>
    <div style='display:flex;gap:24px;flex-wrap:wrap;margin-bottom:24px;'>
      <div style='flex:1;min-width:240px;padding:16px;background:#f8fafc;border-radius:8px;border:1px solid #e2e8f0;'>
        <div style='font-size:11px;text-transform:uppercase;letter-spacing:.5px;color:#64748b;margin-bottom:8px;font-weight:700;'>Bill To</div>
        <div style='line-height:1.7;font-size:13px;'>{$buyerAddrHtml}</div>
      </div>
      <div style='flex:1;min-width:200px;padding:16px;background:#f8fafc;border-radius:8px;border:1px solid #e2e8f0;'>
        <div style='font-size:11px;text-transform:uppercase;letter-spacing:.5px;color:#64748b;margin-bottom:8px;font-weight:700;'>Payment Details</div>
        <div style='line-height:1.8;font-size:13px;'>
          <div><strong>Method:</strong> " . htmlspecialchars($invoice['payment_method']) . "</div>
          {$walletPayHtml}
          <div><strong>Status:</strong> " . htmlspecialchars($invoice['payment_status']) . "</div>
          <div><strong>Order Status:</strong> " . htmlspecialchars($invoice['order_status']) . "</div>
          <div><strong>Date:</strong> " . htmlspecialchars($invoice['order_date']) . "</div>
        </div>
      </div>
    </div>

    <table width='100%' style='border-collapse:collapse;margin-bottom:8px;'>
      <thead>
        <tr style='background:#0f172a;color:#fff;'>
          <th style='padding:10px 8px;text-align:left;font-size:12px;'>#</th>
          <th style='padding:10px 8px;text-align:left;font-size:12px;'>Item</th>
          <th style='padding:10px 8px;text-align:center;font-size:12px;'>HSN</th>
          <th style='padding:10px 8px;text-align:center;font-size:12px;'>Qty</th>
          <th style='padding:10px 8px;text-align:right;font-size:12px;'>Rate</th>
          <th style='padding:10px 8px;text-align:right;font-size:12px;'>Amount</th>
        </tr>
      </thead>
      <tbody>{$itemsRows}</tbody>
      <tfoot>
        <tr><td colspan='5' style='padding:8px;text-align:right;color:#64748b;'>Subtotal</td>
            <td style='padding:8px;text-align:right;'>{$cur}" . number_format($invoice['subtotal'], 2) . "</td></tr>
        {$discountRow}
        <tr><td colspan='5' style='padding:8px;text-align:right;color:#64748b;'>Taxable Value</td>
            <td style='padding:8px;text-align:right;'>{$cur}" . number_format($invoice['taxable_amount'], 2) . "</td></tr>
        {$gstRows}
        <tr><td colspan='5' style='padding:8px;text-align:right;color:#64748b;'>Shipping</td>
            <td style='padding:8px;text-align:right;'>{$shipLabel}</td></tr>
        <tr style='background:#f8fafc;'>
          <td colspan='5' style='padding:14px 8px;text-align:right;font-size:16px;font-weight:700;border-top:2px solid #0f172a;'>Grand Total</td>
          <td style='padding:14px 8px;text-align:right;font-size:16px;font-weight:700;border-top:2px solid #0f172a;'>{$cur}" . number_format($invoice['total'], 2) . "</td></tr>
      </tfoot>
    </table>

    " . ($invoice['notes'] ? "<div style='margin-top:16px;padding:12px;background:#fffbeb;border-radius:6px;font-size:12px;color:#92400e;'><strong>Note:</strong> " . htmlspecialchars($invoice['notes']) . '</div>' : '') . "

    <div style='margin-top:28px;padding-top:16px;border-top:1px dashed #cbd5e1;text-align:center;font-size:12px;color:#64748b;line-height:1.6;'>
      " . htmlspecialchars($s['invoice_footer'] ?? '') . "
      " . ($forEmail
        ? "<p style='margin-top:16px;'>"
            . "<a href='{$invoiceUrl}' style='display:inline-block;background:#f59e0b;color:#fff;text-decoration:none;padding:12px 22px;border-radius:8px;font-weight:700;'>Download Invoice PDF</a>"
            . "</p>"
            . "<p style='margin-top:10px;font-size:12px;'><a href='{$invoiceViewUrl}' style='color:#3b82f6;'>View invoice online</a></p>"
        : '') . "
      <p style='margin:8px 0 0;font-size:11px;color:#94a3b8;'>This is a computer-generated tax invoice.</p>
    </div>
  </div>
</div>
</body></html>";
}

/** Friendly post-order email body (no full bill — PDF attached + download button). */
function sk_invoice_email_body(array $invoice, array $order, array $settings = []): string {
    $CI =& get_instance();
    $CI->load->helper('sk_invoice_pdf');

    $site = htmlspecialchars($settings['site_name'] ?? '2DEAL');
    $name = htmlspecialchars($order['customer_name'] ?? ($order['shipping_name'] ?? 'Customer'));
    $orderNo = htmlspecialchars($invoice['order_number'] ?? ($order['order_number'] ?? ''));
    $invoiceNo = htmlspecialchars($invoice['invoice_no'] ?? '');
    $currency = htmlspecialchars($invoice['currency'] ?? sk_currency_symbol($settings));
    $total = $currency . number_format((float)($invoice['total'] ?? $order['total'] ?? 0), 2);
    $date = htmlspecialchars($invoice['invoice_date'] ?? date('d M Y'));
    $payment = htmlspecialchars($invoice['payment_method'] ?? sk_invoice_payment_method_label($order));
    $phone = htmlspecialchars($settings['site_phone'] ?? '');
    $email = htmlspecialchars($settings['site_email'] ?? '');

    $pdfUrl = sk_invoice_public_url([
        'id'           => (int)($invoice['order_id'] ?? $order['id'] ?? 0),
        'order_number' => (string)($invoice['order_number'] ?? $order['order_number'] ?? ''),
    ]);

    $base = rtrim(base_url(), '/');
    $policies = [
        ['Track your order', $base . '/track-order', 'Enter your order number or AWB anytime.'],
        ['Return & Refund', $base . '/return-refund', 'Eligible returns within the stated window with original packaging.'],
        ['Privacy Policy', $base . '/privacy-policy', 'How we collect and protect your personal data.'],
        ['Terms & Conditions', $base . '/terms-and-conditions', 'Purchase terms, shipping and usage guidelines.'],
        ['Orders FAQ', $base . '/orders-faq', 'Answers about payment, delivery and account help.'],
    ];
    $policyRows = '';
    foreach ($policies as $p) {
        $policyRows .= "<tr>
          <td style='padding:12px 0;border-bottom:1px solid #f1f5f9;vertical-align:top;'>
            <a href='" . htmlspecialchars($p[1]) . "' style='color:#0f172a;font-weight:700;text-decoration:none;font-size:14px;'>" . htmlspecialchars($p[0]) . "</a>
            <div style='color:#64748b;font-size:12px;margin-top:4px;line-height:1.5;'>" . htmlspecialchars($p[2]) . "</div>
          </td>
        </tr>";
    }

    $contactBits = array_filter([
        $phone ? "Phone: {$phone}" : '',
        $email ? "Email: <a href='mailto:{$email}' style='color:#3b82f6;text-decoration:none;'>{$email}</a>" : '',
    ]);
    $contactHtml = $contactBits ? implode(' &nbsp;·&nbsp; ', $contactBits) : '';

    return "<!DOCTYPE html>
<html>
<head><meta charset='utf-8'><meta name='viewport' content='width=device-width,initial-scale=1'></head>
<body style='margin:0;padding:0;background:#f8fafc;font-family:Arial,Helvetica,sans-serif;'>
<div style='max-width:600px;margin:28px auto;background:#fff;border-radius:14px;overflow:hidden;box-shadow:0 8px 28px rgba(15,23,42,.08);'>
  <div style='background:#0f172a;padding:34px 36px;text-align:center;'>
    <div style='color:#f8fafc;font-size:22px;font-weight:700;letter-spacing:.4px;'>{$site}</div>
    <div style='color:#94a3b8;margin-top:8px;font-size:14px;'>Thank you for your order</div>
  </div>

  <div style='padding:36px;'>
    <p style='margin:0 0 12px;color:#334155;font-size:16px;'>Hi <strong>{$name}</strong>,</p>
    <p style='margin:0 0 18px;color:#475569;font-size:15px;line-height:1.65;'>
      We’re excited to confirm your order. Your items are being prepared with care, and we’ll notify you when they ship.
    </p>

    <div style='background:#f8fafc;border:1px solid #e2e8f0;border-radius:12px;padding:18px 20px;margin:22px 0;'>
      <table width='100%' style='border-collapse:collapse;'>
        <tr>
          <td style='padding:6px 0;color:#64748b;font-size:12px;text-transform:uppercase;letter-spacing:.4px;'>Order</td>
          <td style='padding:6px 0;text-align:right;font-weight:700;color:#0f172a;font-size:16px;'>#{$orderNo}</td>
        </tr>
        <tr>
          <td style='padding:6px 0;color:#64748b;font-size:13px;'>Date</td>
          <td style='padding:6px 0;text-align:right;color:#334155;'>{$date}</td>
        </tr>
        <tr>
          <td style='padding:6px 0;color:#64748b;font-size:13px;'>Payment</td>
          <td style='padding:6px 0;text-align:right;color:#334155;'>{$payment}</td>
        </tr>
        <tr>
          <td style='padding:10px 0 0;color:#0f172a;font-size:15px;font-weight:700;border-top:1px solid #e2e8f0;'>Total paid</td>
          <td style='padding:10px 0 0;text-align:right;color:#0f172a;font-size:18px;font-weight:700;border-top:1px solid #e2e8f0;'>{$total}</td>
        </tr>
      </table>
    </div>

    <div style='text-align:center;margin:28px 0 10px;'>
      <a href='" . htmlspecialchars($pdfUrl) . "' style='display:inline-block;background:#f59e0b;color:#fff;text-decoration:none;padding:14px 28px;border-radius:10px;font-weight:700;font-size:15px;'>
        Download Invoice PDF
      </a>
      <p style='margin:12px 0 0;color:#64748b;font-size:12px;line-height:1.5;'>
        Your tax invoice <strong>{$invoiceNo}</strong> is also attached to this email.<br>
        Keep it for your records — the full bill is in the PDF only.
      </p>
    </div>

    <div style='margin-top:30px;padding:18px 20px;background:#fffbeb;border:1px solid #fde68a;border-radius:12px;'>
      <div style='font-weight:700;color:#92400e;margin-bottom:8px;font-size:14px;'>What happens next</div>
      <ul style='margin:0;padding-left:18px;color:#78350f;font-size:13px;line-height:1.7;'>
        <li>We confirm stock and pack your order carefully.</li>
        <li>You’ll get a shipping update with tracking once handed to the courier.</li>
        <li>Use Track Order anytime with your order number or AWB.</li>
      </ul>
    </div>

    <div style='margin-top:28px;'>
      <h3 style='margin:0 0 6px;color:#0f172a;font-size:16px;'>Policies &amp; help</h3>
      <p style='margin:0 0 12px;color:#64748b;font-size:13px;'>Please review these before your delivery arrives:</p>
      <table width='100%' style='border-collapse:collapse;'>{$policyRows}</table>
    </div>

    <p style='margin:28px 0 0;color:#475569;font-size:14px;line-height:1.65;'>
      Need help? Reply to this email" . ($contactHtml ? " or reach us at {$contactHtml}" : '') . ".
      We’re happy to assist.
    </p>
    <p style='margin:18px 0 0;color:#0f172a;font-size:15px;font-weight:600;'>Thank you for shopping with {$site}.</p>
  </div>

  <div style='background:#f8fafc;padding:20px 36px;text-align:center;border-top:1px solid #e2e8f0;'>
    <p style='margin:0;color:#94a3b8;font-size:12px;'>{$site} &copy; " . date('Y') . " · This email confirms your purchase; the PDF is your official invoice.</p>
  </div>
</div>
</body>
</html>";
}

/** Send tax invoice email to customer for an order (friendly body + PDF attachment + download button). */
function sk_mail_order_invoice(array $order, array $settings = []): bool {
    if (empty($settings)) {
        $CI =& get_instance();
        $CI->load->model('Sk_Admin_model');
        $settings = $CI->Sk_Admin_model->get_settings();
    }

    $to_email = $order['customer_email'] ?? '';
    $to_name  = $order['customer_name'] ?? ($order['shipping_name'] ?? 'Customer');
    if (empty($to_email)) return false;

    $CI =& get_instance();
    $CI->load->helper(['sk_mailer', 'sk_invoice_pdf']);

    $invoice = sk_invoice_build($order, $settings);
    $subject = 'Order confirmed #' . ($invoice['order_number'] ?? '') . ' — your invoice is ready';
    $body    = sk_invoice_email_body($invoice, $order, $settings);
    $pdf     = sk_invoice_build_pdf($invoice);
    $pdfName = 'invoice-' . preg_replace('/[^a-zA-Z0-9_-]/', '', $invoice['order_number'] ?? 'order') . '.pdf';

    $tmp = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $pdfName;
    @file_put_contents($tmp, $pdf);
    $attachments = is_file($tmp) ? [$tmp] : [];

    $sent = sk_send_mail($to_email, $to_name, $subject, $body, $attachments);
    if (is_file($tmp)) {
        @unlink($tmp);
    }

    if ($sent && !empty($order['id'])) {
        $CI->db->where('id', (int)$order['id'])->update('orders', [
            'invoice_emailed_at' => date('Y-m-d H:i:s'),
        ]);
    }

    // Admin copy: full order digest (no customer-facing invoice tone); PDF attached when available.
    $currency = sk_currency_symbol($settings);
    $itemsSummary = '';
    foreach (($order['items'] ?? []) as $item) {
        $itemsSummary .= htmlspecialchars(($item['product_name'] ?? 'Item') . ' × ' . ($item['quantity'] ?? 1)) . '<br>';
    }
    $adminPdf = [];
    $adminTmp = '';
    if (!empty($pdf)) {
        $adminTmp = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'admin-' . $pdfName;
        if (@file_put_contents($adminTmp, $pdf) !== false) {
            $adminPdf = [$adminTmp];
        }
    }
    sk_mail_notify_admin(
        'Invoice emailed – order #' . ($invoice['order_number'] ?? $order['order_number'] ?? ''),
        sk_mail_admin_rows([
            'Event'          => 'Tax invoice email sent to customer',
            'Order'          => '#' . ($invoice['order_number'] ?? $order['order_number'] ?? ''),
            'Customer'       => $to_name,
            'Customer email' => $to_email,
            'Phone'          => $order['shipping_phone'] ?? ($order['customer_phone'] ?? ''),
            'Payment'        => strtoupper($order['payment_method'] ?? ''),
            'Total'          => $currency . number_format((float)($order['total'] ?? 0), 2),
            'Items'          => $itemsSummary,
            'Ship to'        => implode(', ', array_filter([
                $order['shipping_line1'] ?? '',
                $order['shipping_city'] ?? '',
                $order['shipping_state'] ?? '',
                $order['shipping_pincode'] ?? '',
            ])),
        ]),
        $settings,
        $adminPdf
    );
    if ($adminTmp !== '' && is_file($adminTmp)) {
        @unlink($adminTmp);
    }

    return $sent;
}

/** Ensure vendor_stores has invoice columns. */
function sk_invoice_ensure_vendor_schema(): void {
    $CI =& get_instance();
    if (!$CI->db->table_exists('vendor_stores')) return;

    $cols = [
        'invoice_prefix' => "VARCHAR(20) DEFAULT 'INV'",
        'invoice_footer' => 'TEXT DEFAULT NULL',
        'pan_no'         => 'VARCHAR(20) DEFAULT NULL',
        'state_code'     => 'VARCHAR(10) DEFAULT NULL',
    ];
    foreach ($cols as $col => $def) {
        if (!$CI->db->field_exists($col, 'vendor_stores')) {
            $CI->db->query("ALTER TABLE `vendor_stores` ADD COLUMN `{$col}` {$def}");
        }
    }

    // Contact phone was VARCHAR(20) — too short for formatted numbers like "03-6242 2232".
    if ($CI->db->field_exists('contact_phone', 'vendor_stores')) {
        $CI->db->query('ALTER TABLE `vendor_stores` MODIFY COLUMN `contact_phone` VARCHAR(50) DEFAULT NULL');
    }
    if ($CI->db->field_exists('contact_email', 'vendor_stores')) {
        $CI->db->query('ALTER TABLE `vendor_stores` MODIFY COLUMN `contact_email` VARCHAR(190) DEFAULT NULL');
    }

    if (!$CI->db->field_exists('invoice_emailed_at', 'orders')) {
        $CI->db->query('ALTER TABLE `orders` ADD COLUMN `invoice_emailed_at` DATETIME DEFAULT NULL AFTER `updated_at`');
    }
}
