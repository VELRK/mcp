<?php
$c = curl_init('https://talkaipilot.com/shop/product/1');
curl_setopt($c, CURLOPT_RETURNTRANSFER, true);
curl_setopt($c, CURLOPT_FOLLOWLOCATION, true);
$res = curl_exec($c);
$code = curl_getinfo($c, CURLINFO_HTTP_CODE);
echo "HTTP: $code\n";
echo "Length: " . strlen($res) . "\n";
echo substr($res, 0, 500) . "\n";
