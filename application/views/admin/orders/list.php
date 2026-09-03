<?php $currency = sk_currency_symbol($settings);
$tab = $tab ?? ($filters['tab'] ?? 'orders');
$qs = function (array $extra = []) use ($filters, $tab) {
    $params = array_filter([
        'tab'            => $extra['tab'] ?? $tab,
        'status'         => array_key_exists('status', $extra) ? $extra['status'] : ($filters['status'] ?? ''),
        'payment_status' => array_key_exists('payment_status', $extra) ? $extra['payment_status'] : ($filters['payment_status'] ?? ''),
        'order_source'   => array_key_exists('order_source', $extra) ? $extra['order_source'] : ($filters['order_source'] ?? ''),
        'search'         => array_key_exists('search', $extra) ? $extra['search'] : ($filters['search'] ?? ''),
        'page'           => $extra['page'] ?? null,
    ], static function ($v) {
        return $v !== null && $v !== '';
    });
    return http_build_query($params);
};
?>

<div class="sk-page-header">
  <h5 class="sk-page-title"><i class="bi bi-cart-check me-2 text-warning"></i>Orders</h5>
</div>

<ul class="nav nav-tabs mb-3">
  <li class="nav-item">
    <a class="nav-link <?= $tab === 'orders' ? 'active' : '' ?>"
       href="<?= site_url('admin/orders?' . $qs(['tab' => 'orders', 'status' => '', 'page' => null])) ?>">
      Orders
      <span class="badge bg-secondary ms-1"><?= (int)($count_orders ?? 0) ?></span>
    </a>
  </li>
  <li class="nav-item">
    <a class="nav-link <?= $tab === 'abandoned' ? 'active' : '' ?>"
       href="<?= site_url('admin/orders?' . $qs(['tab' => 'abandoned', 'status' => '', 'page' => null])) ?>">
      Abandoned
      <span class="badge bg-warning text-dark ms-1"><?= (int)($count_abandoned ?? 0) ?></span>
    </a>
  </li>
</ul>

<!-- Filters -->
<div class="card sk-table-card shadow-sm mb-3">
  <div class="card-body py-2">
    <form method="GET" class="d-flex gap-2 flex-wrap">
      <input type="hidden" name="tab" value="<?= htmlspecialchars($tab) ?>">
      <input type="text" name="search" class="form-control form-control-sm" style="max-width:200px;"
             placeholder="Order number..." value="<?= htmlspecialchars($filters['search'] ?? '') ?>">
      <?php if ($tab === 'orders'): ?>
      <select name="status" class="form-select form-select-sm" style="max-width:160px;">
        <option value="">All Statuses</option>
        <?php
        $status_opts = [
          'pending' => 'Pending',
          'confirmed' => 'Confirmed',
          'processing' => 'Processing',
          'shipped' => 'Shipped',
          'delivered' => 'Delivered',
          'cancelled' => 'Cancelled',
          'returned' => 'Returned',
        ];
        foreach ($status_opts as $s => $label): ?>
          <option value="<?= $s ?>" <?= ($filters['status']??'')===$s?'selected':'' ?>><?= $label ?></option>
        <?php endforeach; ?>
      </select>
      <?php endif; ?>
      <select name="payment_status" class="form-select form-select-sm" style="max-width:160px;">
        <option value="">Payment Status</option>
        <?php foreach (['pending','paid','failed','refunded'] as $s): ?>
          <option value="<?= $s ?>" <?= ($filters['payment_status']??'')===$s?'selected':'' ?>><?= ucfirst($s) ?></option>
        <?php endforeach; ?>
      </select>
      <select name="order_source" class="form-select form-select-sm" style="max-width:140px;">
        <option value="">All Sources</option>
        <option value="web" <?= ($filters['order_source'] ?? '') === 'web' ? 'selected' : '' ?>>Web</option>
        <option value="app" <?= ($filters['order_source'] ?? '') === 'app' ? 'selected' : '' ?>>App</option>
        <option value="unknown" <?= ($filters['order_source'] ?? '') === 'unknown' ? 'selected' : '' ?>>Unknown</option>
      </select>
      <button class="btn btn-sm btn-outline-warning px-3">Filter</button>
      <a href="<?= site_url('admin/orders?tab=' . urlencode($tab)) ?>" class="btn btn-sm btn-outline-secondary">Reset</a>
    </form>
  </div>
</div>

<?php if ($tab === 'abandoned'): ?>
<div class="alert alert-warning py-2 small mb-3">
  Abandoned checkouts (customer started online payment but did not complete). These do not appear under Orders.
</div>
<?php endif; ?>

<div class="card sk-table-card shadow-sm">
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead>
          <tr><th>Order #</th><th>Customer</th><th>Items</th><th>Coupon</th><th>Total</th><th>Source</th><th>Status</th><th>Payment</th><th>Date</th><th></th></tr>
        </thead>
        <tbody>
          <?php foreach ($orders as $o): ?>
          <tr>
            <td><span class="fw-semibold"><?= htmlspecialchars($o['order_number']) ?></span></td>
            <td>
              <div><?= htmlspecialchars($o['customer_name'] ?? '-') ?></div>
              <small class="text-muted"><?= htmlspecialchars($o['customer_email'] ?? '') ?></small>
            </td>
            <td>
              <?php
                $item_cnt = $this->db->where('order_id',$o['id'])->count_all_results('order_items');
                echo $item_cnt . ' item' . ($item_cnt!=1?'s':'');
              ?>
            </td>
            <td>
              <?php if (!empty($o['promo_code'])): ?>
                <span class="badge bg-success-subtle text-success border border-success-subtle">
                  <?= htmlspecialchars($o['promo_code']) ?>
                </span>
                <small class="text-muted d-block">-<?= $currency . number_format($o['discount'],2) ?></small>
              <?php else: ?>
                <span class="text-muted small">—</span>
              <?php endif; ?>
            </td>
            <td class="fw-semibold"><?= $currency . number_format($o['total'],2) ?></td>
            <td>
              <?php
                $src = strtolower(trim((string) ($o['order_source'] ?? 'unknown')));
                $srcBadge = $src === 'web' ? 'primary' : ($src === 'app' ? 'success' : 'secondary');
              ?>
              <span class="badge text-bg-<?= $srcBadge ?>"><?= htmlspecialchars(Sk_Order_model::source_label($src)) ?></span>
            </td>
            <td>
              <?php
                $st = $o['status'] ?? '';
                $badgeClass = $st === 'payment_attempt' ? 'pending' : $st;
              ?>
              <span class="badge badge-<?= htmlspecialchars($badgeClass) ?>"><?= htmlspecialchars(Sk_Order_model::status_label($st)) ?></span>
            </td>
            <td><span class="badge badge-<?= $o['payment_status'] ?>"><?= ucfirst($o['payment_status']) ?></span></td>
            <td><?= sk_format_datetime($o['created_at'], 'd M y, h:i A') ?></td>
            <td class="d-flex gap-1">
              <a href="<?= site_url('admin/orders/view/'.$o['id']) ?>" class="btn btn-sm btn-outline-primary">
                <i class="bi bi-eye"></i>
              </a>
              <a href="<?= site_url('admin/orders/invoice/'.$o['id']) ?>" target="_blank" class="btn btn-sm btn-outline-secondary">
                <i class="bi bi-printer"></i>
              </a>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php if (empty($orders)): ?>
          <tr><td colspan="10" class="text-center py-5 text-muted"><?= $tab === 'abandoned' ? 'No abandoned orders.' : 'No orders found.' ?></td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
  <?php $pages = ceil($total / $limit); if ($pages > 1): ?>
  <div class="card-footer bg-white d-flex justify-content-between align-items-center">
    <small class="text-muted"><?= $total ?> total</small>
    <nav><ul class="pagination pagination-sm mb-0">
      <?php for ($i=1; $i<=$pages; $i++): ?>
        <li class="page-item <?= $i===$page?'active':'' ?>">
          <a class="page-link" href="?<?= $qs(['page' => $i]) ?>"><?= $i ?></a>
        </li>
      <?php endfor; ?>
    </ul></nav>
  </div>
  <?php endif; ?>
</div>
