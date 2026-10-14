<?php
$ch = curl_init('https://talkaipilot.com/shopkart-api/meta-agent/connectors/1389144424278347/search_products?query=show%20prodct%20details');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'X-Api-Key: Ved451959',
    'Content-Type: application/json',
]);
curl_setopt($ch, CURLOPT_POST, true);
$res = curl_exec($ch);
curl_close($ch);
$d = json_decode($res, true);
echo json_encode([
    'success'   => $d['success'] ?? false,
    'count'     => $d['data']['count'] ?? 0,
    'ui'        => $d['data']['ui'] ?? '',
    'image_url' => $d['data']['image_url'] ?? '',
    'caption'   => $d['data']['caption'] ?? '',
    'message'   => $d['data']['message'] ?? '',
    'summary'   => $d['data']['summary'] ?? '',
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";

