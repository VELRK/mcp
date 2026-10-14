<?php
$ch = curl_init('https://talkaipilot.com/shopkart-api/meta-agent/connectors/1389144424278347/search_products?query=Show%20product%20details');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'X-Api-Key: Ved451959',
    'Content-Type: application/json',
]);
curl_setopt($ch, CURLOPT_POST, true);
$res = curl_exec($ch);
curl_close($ch);
echo $res;
