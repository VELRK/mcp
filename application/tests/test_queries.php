<?php
$url = 'https://talkaipilot.com/shopkart-api/meta-agent/connectors/1389144424278347/search_products';
$apiKey = 'Ved451959';

$queries = [
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
    $data = json_decode($res, true)['data'] ?? [];
    $count = $data['count'] ?? 0;
    $cards = $data['cards'] ?? [];
    $first = $cards[0]['name'] ?? 'NONE';
    $img = $cards[0]['image_url'] ?? 'NONE';
    printf("Q: %-36s | Count: %2d | First: %-35s | Img: %s\n", $q, $count, substr($first, 0, 35), $img);
    curl_close($ch);
}
