<?php $currency = sk_currency_symbol($settings); ?>

<div class="sk-page-header">
  <h5 class="sk-page-title">
    <i class="bi bi-receipt me-2 text-warning"></i>
    Order <span class="text-warning"><?= htmlspecialchars($order['order_number']) ?></span>
  </h5>
  <div class="d-flex gap-2">
    <a href="<?= site_url('admin/orders/invoice/'.$order['id']) ?>" target="_blank" class="btn btn-sm btn-outline-secondary">
      <i class="bi bi-printer me-1"></i> Invoice
    </a>
    <button type="button" class="btn btn-sm btn-outline-primary" id="btnSendInvoice" onclick="sendInvoice(<?= (int)$order['id'] ?>)">
      <i class="bi bi-envelope me-1"></i> Email Invoice
    </button>
    <a href="<?= site_url('admin/orders') ?>" class="btn btn-sm btn-outline-secondary">
      <i class="bi bi-arrow-left me-1"></i> Back
    </a>
  </div>
</div>

<div class="row g-3">
  <!-- Order Items -->
  <div class="col-lg-8">
    <div class="card sk-table-card shadow-sm mb-3">
      <div class="card-header bg-white border-0 py-3 fw-semibold">Order Items</div>
      <div class="card-body p-0">
        <table class="table mb-0">
          <thead><tr><th>Product</th><th>Price</th><th>Qty</th><th>Subtotal</th></tr></thead>
          <tbody>
            <?php foreach ($order['items'] as $item): ?>
            <tr>
              <td>
                <?php if ($item['thumbnail']): ?>
                  <img src="<?= base_url($item['thumbnail']) ?>" width="40" class="rounded me-2">
                <?php endif; ?>
                <?= htmlspecialchars($item['product_name']) ?>
                <?php if ($item['product_sku']): ?>
                  <small class="text-muted d-block">SKU: <?= $item['product_sku'] ?></small>
                <?php endif; ?>
              </td>
              <td><?= $currency . number_format($item['price'],2) ?></td>
              <td><?= $item['quantity'] ?></td>
              <td><?= $currency . number_format($item['subtotal'],2) ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
          <tfoot class="table-light">
            <tr><td colspan="3" class="text-end fw-semibold">Subtotal</td><td><?= $currency . number_format($order['subtotal'],2) ?></td></tr>
            <?php
            $this->load->helper('sk_invoice');
            $disc = sk_order_discount_breakdown($order, $settings ?? []);
            if (($disc['promo'] ?? 0) > 0 || ($disc['affiliate'] ?? 0) > 0):
              $promoAmt = (float)($disc['promo'] ?? 0) + (float)($disc['affiliate'] ?? 0);
              $promoLabel = $disc['promo_code'] ?: ($disc['affiliate_promo'] ?? 'Promo');
            ?>
            <tr><td colspan="3" class="text-end text-success">Discount (<?= htmlspecialchars($promoLabel) ?>)</td><td class="text-success">-<?= $currency . number_format($promoAmt, 2) ?></td></tr>
            <?php endif; ?>
            <?php if (($disc['wallet'] ?? 0) > 0): ?>
            <tr><td colspan="3" class="text-end text-success">Wallet payment discount<?= !empty($disc['wallet_percent']) ? ' (' . rtrim(rtrim(number_format((float)$disc['wallet_percent'], 2), '0'), '.') . '%)' : '' ?></td><td class="text-success">-<?= $currency . number_format($disc['wallet'], 2) ?></td></tr>
            <?php endif; ?>
            <tr><td colspan="3" class="text-end">Shipping</td><td><?= $currency . number_format($order['shipping'],2) ?></td></tr>
            <tr><td colspan="3" class="text-end">Tax</td><td><?= $currency . number_format($order['tax'],2) ?></td></tr>
            <tr><td colspan="3" class="text-end fw-bold fs-6">Total</td><td class="fw-bold fs-6"><?= $currency . number_format($order['total'],2) ?></td></tr>
          </tfoot>
        </table>
      </div>
    </div>

    <!-- Payment Info -->
    <?php if ($order['payment']): $pay = $order['payment']; ?>
    <div class="card sk-table-card shadow-sm">
      <div class="card-header bg-white border-0 py-3 fw-semibold">Payment Details</div>
      <div class="card-body">
        <div class="row g-2">
          <div class="col-6"><small class="text-muted d-block">Razorpay Order ID</small><?= $pay['razorpay_order_id'] ?? '-' ?></div>
          <div class="col-6"><small class="text-muted d-block">Payment ID</small><?= $pay['razorpay_payment_id'] ?? '-' ?></div>
          <div class="col-6"><small class="text-muted d-block">Amount</small><?= $currency . number_format($pay['amount'],2) ?></div>
          <div class="col-6"><small class="text-muted d-block">Status</small>
            <span class="badge badge-<?= $pay['status'] ?>"><?= ucfirst($pay['status']) ?></span>
          </div>
        </div>
      </div>
    </div>
    <?php endif; ?>
  </div>

  <!-- Right column -->
  <div class="col-lg-4">

    <!-- Order Progress Stepper -->
    <?php
    $status_steps = [
      ['key' => 'pending',    'label' => 'Order Placed',     'icon' => 'bi-receipt',       'time' => $order['created_at'] ?? null],
      ['key' => 'confirmed',  'label' => 'Confirmed',        'icon' => 'bi-check2-circle', 'time' => $order['confirmed_at'] ?? null],
      ['key' => 'processing', 'label' => 'Ready to Pick Up', 'icon' => 'bi-box-seam',      'time' => $order['processing_at'] ?? $order['jt_shipment_created_at'] ?? null],
      ['key' => 'shipped',    'label' => 'Shipped',          'icon' => 'bi-truck',         'time' => $order['shipped_at'] ?? null],
      ['key' => 'delivered',  'label' => 'Delivered',        'icon' => 'bi-house-check',   'time' => $order['delivered_at'] ?? null],
    ];
    $step_keys   = array_column($status_steps, 'key');
    $progress_status = ($order['status'] === 'payment_attempt') ? 'pending' : $order['status'];
    $current_idx = array_search($progress_status, $step_keys);
    $is_terminal = in_array($order['status'], ['cancelled', 'returned']);
    $is_payment_attempt = ($order['status'] === 'payment_attempt');
    ?>
    <div class="card sk-table-card shadow-sm mb-3">
      <div class="card-header bg-white border-0 py-3 fw-semibold">
        <i class="bi bi-list-check me-2 text-warning"></i>Order Progress
      </div>
      <div class="card-body py-3">
        <?php if ($is_terminal): ?>
          <div class="d-flex align-items-center gap-2 p-2 rounded"
            style="background:<?= $order['status']==='cancelled'?'#fee2e2':'#ffedd5' ?>;color:<?= $order['status']==='cancelled'?'#991b1b':'#9a3412' ?>;">
            <i class="bi <?= $order['status']==='cancelled'?'bi-x-circle':'bi-arrow-counterclockwise' ?> fs-5"></i>
            <span class="fw-semibold"><?= $order['status']==='cancelled' ? 'Order Cancelled' : 'Return Requested' ?></span>
          </div>
        <?php elseif ($is_payment_attempt): ?>
          <div class="d-flex align-items-center gap-2 p-2 rounded mb-2"
            style="background:#fff7ed;color:#9a3412;">
            <i class="bi bi-cart-x fs-5"></i>
            <span class="fw-semibold">Abandoned — customer started checkout but did not complete payment</span>
          </div>
        <?php else: ?>
          <div class="d-flex align-items-center w-100">
            <?php foreach ($status_steps as $i => $step):
              $done   = ($current_idx !== false) && $i < $current_idx;
              $active = ($current_idx !== false) && $i === $current_idx;
            ?>
              <div class="d-flex align-items-center <?= $i < count($status_steps) - 1 ? 'flex-grow-1' : '' ?>">
                <div class="text-center" style="min-width:54px;">
                  <div class="rounded-circle d-flex align-items-center justify-content-center mx-auto mb-1"
                    style="width:38px;height:38px;
                      background:<?= ($done||$active) ? '#0f172a' : '#f1f5f9' ?>;
                      color:<?= ($done||$active) ? '#fff' : '#94a3b8' ?>;
                      border:<?= $active ? '3px solid #0f172a' : ('2px solid '.($done ? '#0f172a' : '#e2e8f0')) ?>;
                      <?= $active ? 'box-shadow:0 0 0 4px rgba(15,23,42,0.15);' : '' ?>
                      font-size:<?= $active ? '16px' : '13px' ?>;">
                    <i class="bi <?= $done ? 'bi-check-lg' : $step['icon'] ?>"></i>
                  </div>
                  <small style="display:block;font-size:10px;white-space:nowrap;color:<?= ($done||$active) ? '#0f172a' : '#94a3b8' ?>;font-weight:<?= ($done||$active) ? 600 : 400 ?>;">
                    <?= $step['label'] ?>
                  </small>
                  <?php if (!empty($step['time']) && ($done || $active)): ?>
                  <small style="display:block;font-size:9px;color:#64748b;margin-top:2px;"><?= htmlspecialchars(date('d M Y H:i', strtotime($step['time']))) ?></small>
                  <?php endif; ?>
                </div>
                <?php if ($i < count($status_steps) - 1): ?>
                  <div class="flex-grow-1" style="height:2px;margin-bottom:20px;background:<?= $done ? '#0f172a' : '#e2e8f0' ?>;"></div>
                <?php endif; ?>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
    </div>

    <!-- Update Status -->
    <div class="card sk-table-card shadow-sm mb-3">
      <div class="card-header bg-white border-0 py-3 fw-semibold">Update Status</div>
      <div class="card-body">
        <div class="mb-3">
          <label class="form-label">Order Status</label>
          <select id="orderStatus" class="form-select">
            <?php
            $admin_statuses = [
              'payment_attempt' => 'Abandoned',
              'pending' => 'Pending',
              'confirmed' => 'Confirmed',
              'processing' => 'Ready to Pick Up (Processing)',
              'shipped' => 'Shipped',
              'delivered' => 'Delivered',
              'cancelled' => 'Cancelled',
              'returned' => 'Returned',
            ];
            foreach ($admin_statuses as $s => $label): ?>
              <option value="<?= $s ?>" <?= $order['status']===$s?'selected':'' ?>><?= $label ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="mb-3">
          <label class="form-label">Tracking Number</label>
          <input type="text" id="trackingNum" class="form-control" value="<?= htmlspecialchars($order['tracking_number'] ?? '') ?>">
        </div>
        <button onclick="updateStatus(<?= $order['id'] ?>)" class="btn btn-warning w-100 fw-semibold">
          Update Status
        </button>
      </div>
    </div>

    <!-- Customer Info -->
    <div class="card sk-table-card shadow-sm mb-3">
      <div class="card-header bg-white border-0 py-3 fw-semibold">Customer</div>
      <div class="card-body">
        <p class="mb-1 fw-semibold"><?= htmlspecialchars($order['customer_name'] ?? '-') ?></p>
        <p class="mb-1 text-muted small"><?= htmlspecialchars($order['customer_email'] ?? '-') ?></p>
        <?php
          $src = strtolower(trim((string) ($order['order_source'] ?? 'unknown')));
          $srcBadge = $src === 'web' ? 'primary' : ($src === 'app' ? 'success' : 'secondary');
        ?>
        <p class="mb-1 small">
          <span class="text-muted">Source:</span>
          <span class="badge text-bg-<?= $srcBadge ?>"><?= htmlspecialchars(Sk_Order_model::source_label($src)) ?></span>
        </p>
        <?php if (!empty($order['invoice_emailed_at'])): ?>
        <p class="mb-0 small text-success"><i class="bi bi-check-circle me-1"></i>Invoice emailed: <?= date('d M Y, h:i A', strtotime($order['invoice_emailed_at'])) ?></p>
        <?php endif; ?>
      </div>
    </div>

    <!-- Shipping Address -->
    <div class="card sk-table-card shadow-sm">
      <div class="card-header bg-white border-0 py-3 fw-semibold">Shipping Address</div>
      <div class="card-body small">
        <strong><?= htmlspecialchars($order['shipping_name'] ?? '') ?></strong><br>
        <?= htmlspecialchars($order['shipping_phone'] ?? '') ?><br>
        <?= htmlspecialchars($order['shipping_line1'] ?? '') ?><br>
        <?php if ($order['shipping_line2']): ?><?= htmlspecialchars($order['shipping_line2']) ?><br><?php endif; ?>
        <?= htmlspecialchars($order['shipping_city'] ?? '') ?>, <?= htmlspecialchars($order['shipping_state'] ?? '') ?> - <?= htmlspecialchars($order['shipping_pincode'] ?? '') ?><br>
        <?= htmlspecialchars($order['shipping_country'] ?? '') ?>
      </div>
    </div>
  </div>
</div>

<div id="statusToast" class="position-fixed bottom-0 end-0 p-3" style="z-index:9999">
  <div class="toast align-items-center text-bg-success border-0" role="alert" data-bs-autohide="true" data-bs-delay="2000">
    <div class="d-flex">
      <div class="toast-body fw-semibold"><i class="bi bi-check-circle me-2"></i>Order status updated!</div>
      <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
    </div>
  </div>
</div>

<script>
function sendInvoice(orderId) {
  var btn = document.getElementById('btnSendInvoice');
  if (btn) { btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Sending…'; }
  $.post('<?= site_url('admin/orders/send_invoice') ?>/' + orderId, {}, function(res) {
    if (btn) { btn.disabled = false; btn.innerHTML = '<i class="bi bi-envelope me-1"></i> Email Invoice'; }
    alert(res.message || (res.success ? 'Invoice sent.' : 'Failed to send invoice.'));
    if (res.success) location.reload();
  }, 'json').fail(function() {
    if (btn) { btn.disabled = false; btn.innerHTML = '<i class="bi bi-envelope me-1"></i> Email Invoice'; }
    alert('Network error. Please try again.');
  });
}
function updateStatus(orderId) {
  var btn      = document.querySelector('[onclick="updateStatus(' + orderId + ')"]');
  var status   = document.getElementById('orderStatus').value;
  var tracking = document.getElementById('trackingNum').value;
  if (btn) { btn.disabled = true; btn.textContent = 'Saving…'; }
  $.post('<?= site_url('admin/orders/update_status') ?>/' + orderId, {
    status: status, tracking_number: tracking
  }, function(res) {
    if (res.success) {
      var toastBody = document.querySelector('#statusToast .toast-body');
      if (toastBody) {
        toastBody.innerHTML = '<i class="bi bi-check-circle me-2"></i>' + (res.message || 'Order status updated!');
      }
      var toast = new bootstrap.Toast(document.querySelector('#statusToast .toast'));
      toast.show();
      setTimeout(function() { location.reload(); }, 1800);
    } else {
      if (btn) { btn.disabled = false; btn.textContent = 'Update Status'; }
      alert(res.message || 'Failed to update status.');
    }
  }, 'json').fail(function() {
    if (btn) { btn.disabled = false; btn.textContent = 'Update Status'; }
    alert('Network error. Please try again.');
  });
}
</script>
