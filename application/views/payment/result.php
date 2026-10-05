<?php
$ok = !empty($success);
$pending = !empty($pending);
$title = $ok ? 'Payment Successful' : ($pending ? 'Payment Processing' : 'Payment Failed');
$orderNumber = htmlspecialchars((string)($order_number ?? ''));
$message = htmlspecialchars((string)($message ?? ''));
$ordersUrl = htmlspecialchars((string)($orders_url ?? '/account-orders'));
$homeUrl = htmlspecialchars((string)($home_url ?? '/'));
$heading = $ok
    ? 'Thank you — payment received'
    : ($pending ? 'Payment is still processing' : 'Payment was not completed');
$iconClass = $ok ? 'ok' : ($pending ? 'wait' : 'fail');
$icon = $ok ? '✓' : ($pending ? '…' : '!');
$defaultMsg = $ok
    ? 'Your order is confirmed. You can track it in My Orders.'
    : ($pending
        ? 'Touch ’n Go / FPX sometimes takes a few minutes. Do not pay again. We will confirm the order automatically when Curlec captures the payment.'
        : 'You can try again from My Orders. If money was deducted, it is usually returned in 5–7 working days.');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= $title ?> | Talk AI Pilot</title>
  <style>
    body { margin:0; font-family: Inter, system-ui, sans-serif; background:#f8fafc; color:#0f172a; }
    .bar { background:#3ec1bc; color:#fff; text-align:center; padding:16px; font-weight:700; font-size:18px; }
    .wrap { max-width:480px; margin:40px auto; padding:0 20px; text-align:center; }
    .icon { width:88px; height:88px; border-radius:50%; margin:0 auto 20px; display:flex; align-items:center; justify-content:center; font-size:42px; }
    .ok { background:#d1fae5; color:#047857; }
    .wait { background:#fef3c7; color:#b45309; }
    .fail { background:#fee2e2; color:#b91c1c; }
    h1 { font-size:22px; margin:0 0 10px; }
    p { color:#475569; line-height:1.5; margin:0 0 24px; }
    .btn { display:inline-block; background:#3ec1bc; color:#fff; text-decoration:none; padding:14px 28px; border-radius:999px; font-weight:600; }
    .btn-ghost { background:#fff; color:#0f766e; border:1px solid #99f6e4; margin-left:8px; }
  </style>
</head>
<body>
  <div class="bar"><?= $title ?></div>
  <div class="wrap">
    <div class="icon <?= $iconClass ?>"><?= $icon ?></div>
    <h1><?= $heading ?></h1>
    <p>
      <?= $message ?: $defaultMsg ?>
      <?php if ($orderNumber): ?><br><strong>Order <?= $orderNumber ?></strong><?php endif; ?>
    </p>
    <a class="btn" href="<?= $ordersUrl ?>">My Orders</a>
    <a class="btn btn-ghost" href="<?= $homeUrl ?>">Back to shop</a>
  </div>
  <?php if ($ok): ?>
  <script>
  (function () {
    var lines = <?= json_encode(array_values($cart_clear_lines ?? [])) ?>;
    try { localStorage.setItem('sk_paid_cart_clear', JSON.stringify(lines || [])); } catch (e) {}
    try {
      var raw = localStorage.getItem('2Deal-store');
      if (!raw) return;
      var data = JSON.parse(raw);
      if (!data || !data.state || !Array.isArray(data.state.cartProducts)) return;
      var ids = {};
      (lines || []).forEach(function (l) {
        if (l && l.product_id) ids[String(l.product_id)] = true;
      });
      data.state.cartProducts = Object.keys(ids).length
        ? data.state.cartProducts.filter(function (p) { return !ids[String(p.id)]; })
        : data.state.cartProducts;
      data.state.totalPrice = (data.state.cartProducts || []).reduce(function (sum, p) {
        return sum + (Number(p.price) || 0) * (Number(p.quantity) || 1);
      }, 0);
      localStorage.setItem('2Deal-store', JSON.stringify(data));
    } catch (e) {}
  })();
  </script>
  <?php endif; ?>
</body>
</html>
