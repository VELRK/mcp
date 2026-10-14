<?php
$url = 'https://talkaipilot.com/shopkart-api/meta-agent/connectors/1389144424278347/search_products';
$apiKey = 'Ved451959';

$queries = [
    'show prodct details',
    'show product details',
    'Tell me kanjivaram saree details',
    'Give me the product details',
    'Show the products',
    'Tell me about crepe saree',
    'What sarees do you have',
    'Kanjivaram',
    'saree'
];

foreach ($queries as $q) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'X-Api-Key: ' . $apiKey,
        'Content-Type: application/json',
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['query' => $q]));
    $res = curl_exec($ch);
    $json = json_decode($res, true);
    $data = $json['data'] ?? [];
    $count = $data['count'] ?? 0;
    $cards = $data['cards'] ?? [];
    $first = $cards[0]['name'] ?? 'NONE';
    $img = $cards[0]['image_url'] ?? 'NONE';
    $ui = $data['ui'] ?? 'none';
    $summary = $data['summary'] ?? ($json['message'] ?? '');
    printf("Q: %-32s | Count: %2d | UI: %-13s | First: %-30s | Summary: %s\n", $q, $count, $ui, substr($first, 0, 30), substr($summary, 0, 80));
    curl_close($ch);
}
