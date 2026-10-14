<?php
$c = curl_init('http://127.0.0.1/mcp/shop/product/13');
curl_setopt($c, CURLOPT_RETURNTRANSFER, true);
curl_setopt($c, CURLOPT_FOLLOWLOCATION, true);
$res = curl_exec($c);
$code = curl_getinfo($c, CURLINFO_HTTP_CODE);
echo "Localhost HTTP: $code\n";
echo "Length: " . strlen($res) . "\n";
echo substr($res, 0, 500) . "\n";
