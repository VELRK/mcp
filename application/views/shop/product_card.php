<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= htmlspecialchars($name) ?></title>
  <style>
    body { margin: 0; font-family: Arial, sans-serif; background: #f6f7f8; color: #111; }
    .card { max-width: 480px; margin: 0 auto; background: #fff; min-height: 100vh; }
    img { width: 100%; height: auto; display: block; background: #f3f4f6; }
    .body { padding: 20px 18px 32px; }
    h1 { font-size: 22px; line-height: 1.35; margin: 0 0 12px; }
    .price { font-size: 20px; font-weight: 700; color: #128c7e; }
    .mrp { margin-left: 8px; color: #98a2b3; text-decoration: line-through; font-weight: 500; font-size: 16px; }
    p { color: #667085; line-height: 1.5; }
  </style>
</head>
<body>
  <article class="card">
    <?php if ($image !== ''): ?>
      <img src="<?= htmlspecialchars($image) ?>" alt="<?= htmlspecialchars($name) ?>">
    <?php endif; ?>
    <div class="body">
      <h1><?= htmlspecialchars($name) ?></h1>
      <div class="price">₹<?= number_format((float)$price, 0) ?><?php if ($mrp > $price && $mrp > 0): ?><span class="mrp">₹<?= number_format((float)$mrp, 0) ?></span><?php endif; ?></div>
      <p>Open WhatsApp and tap Shop now to buy this from the shop.</p>
    </div>
  </article>
</body>
</html>
