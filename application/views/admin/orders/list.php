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

<?php
$page_title = 'Order List';
$breadcrumb = [
    ['label' => 'Ecommerce', 'url' => site_url('shopkart/orders')],
    'Order List',
];
$this->load->view('admin/partials/page_title', compact('page_title', 'breadcrumb'));
$orderBadge = static function (string $status): string {
    $map = [
        'pending' => 'badge-label-warning',
        'payment_attempt' => 'badge-label-warning',
        'confirmed' => 'badge-label-primary',
        'processing' => 'badge-label-info',
        'shipped' => 'badge-label-info',
        'delivered' => 'badge-label-success',
        'cancelled' => 'badge-label-danger',
        'returned' => 'badge-label-secondary',
    ];
    return $map[$status] ?? 'badge-label-secondary';
};
?>

<div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-4">
  <div class="nav nav-pills nav-group-tabs">
    <a class="nav-item nav-link <?= $tab === 'orders' && ($filters['status'] ?? '') === '' ? 'active' : '' ?>"
       href="<?= site_url('shopkart/orders?' . $qs(['tab' => 'orders', 'status' => '', 'page' => null])) ?>">All Orders</a>
    <a class="nav-item nav-link <?= ($filters['status'] ?? '') === 'pending' ? 'active' : '' ?>"
       href="<?= site_url('shopkart/orders?' . $qs(['tab' => 'orders', 'status' => 'pending', 'page' => null])) ?>">Pending</a>
    <a class="nav-item nav-link <?= ($filters['status'] ?? '') === 'shipped' ? 'active' : '' ?>"
       href="<?= site_url('shopkart/orders?' . $qs(['tab' => 'orders', 'status' => 'shipped', 'page' => null])) ?>">Shipped</a>
    <a class="nav-item nav-link <?= ($filters['status'] ?? '') === 'delivered' ? 'active' : '' ?>"
       href="<?= site_url('shopkart/orders?' . $qs(['tab' => 'orders', 'status' => 'delivered', 'page' => null])) ?>">Delivered</a>
    <a class="nav-item nav-link <?= ($filters['status'] ?? '') === 'cancelled' ? 'active' : '' ?>"
       href="<?= site_url('shopkart/orders?' . $qs(['tab' => 'orders', 'status' => 'cancelled', 'page' => null])) ?>">Cancelled</a>
    <a class="nav-item nav-link <?= $tab === 'abandoned' ? 'active' : '' ?>"
       href="<?= site_url('shopkart/orders?' . $qs(['tab' => 'abandoned', 'status' => '', 'page' => null])) ?>">
      Abandoned <span class="badge bg-warning text-dark ms-1"><?= (int)($count_abandoned ?? 0) ?></span>
    </a>
  </div>
</div>

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

<div class="row">
  <?php if (empty($orders)): ?>
  <div class="col-12"><div class="card"><div class="card-body text-center text-muted py-5"><?= $tab === 'abandoned' ? 'No abandoned orders.' : 'No orders found.' ?></div></div></div>
  <?php endif; ?>
  <?php foreach ($orders as $o):
    $first = $this->db->select('product_name')->where('order_id', $o['id'])->order_by('id', 'ASC')->limit(1)->get('order_items')->row_array();
    $itemName = $first['product_name'] ?? 'Order';
    $itemCnt = (int) $this->db->where('order_id', $o['id'])->count_all_results('order_items');
    $addr = trim(implode(', ', array_filter([
        $o['shipping_line1'] ?? '',
        $o['shipping_city'] ?? '',
        $o['shipping_state'] ?? '',
        $o['shipping_pincode'] ?? '',
    ])));
    $st = (string) ($o['status'] ?? '');
    $initial = strtoupper(substr((string) ($o['customer_name'] ?: '?'), 0, 1));
    $day = !empty($o['created_at']) ? date('d', strtotime($o['created_at'])) : '--';
    $mon = !empty($o['created_at']) ? date('M', strtotime($o['created_at'])) : '';
  ?>
  <div class="col-xxl-3 col-xl-4 col-md-6">
    <div class="card card-h-100 border">
      <div class="card-header flex-wrap gap-2">
        <div>
          <a href="<?= site_url('shopkart/orders/view/'.$o['id']) ?>" class="fw-semibold text-body fs-16 mb-0 d-block"><?= htmlspecialchars($itemName) ?></a>
          <small class="text-success d-block"><?= $currency . number_format((float) $o['total'], 2) ?></small>
          <?php if ($itemCnt > 1): ?><small class="text-muted"><?= $itemCnt ?> items</small><?php endif; ?>
        </div>
        <div class="text-end">
          <span class="fw-semibold fs-16">Order Id</span>
          <a href="<?= site_url('shopkart/orders/view/'.$o['id']) ?>" class="d-block text-muted"><?= htmlspecialchars($o['order_number']) ?></a>
        </div>
      </div>
      <div class="card-body d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div style="max-width:70%;">
          <h6 class="mb-1">Delivery Address</h6>
          <p class="mb-0 text-muted" style="display:-webkit-box;-webkit-line-clamp:3;-webkit-box-orient:vertical;overflow:hidden;">
            <?= $addr !== '' ? htmlspecialchars($addr) : 'No address saved' ?>
          </p>
          <?php if (!empty($o['promo_code'])): ?>
            <small class="text-success d-block mt-1"><?= htmlspecialchars($o['promo_code']) ?> · -<?= $currency . number_format((float) $o['discount'], 2) ?></small>
          <?php endif; ?>
        </div>
        <div class="p-2 text-center bg-info-subtle rounded flex-shrink-0" style="min-width:56px;">
          <h6 class="mb-0 text-info"><?= $day ?></h6>
          <h6 class="mb-0 text-info"><?= $mon ?></h6>
        </div>
      </div>
      <div class="card-footer d-flex justify-content-between align-items-center">
        <span class="d-flex align-items-center gap-2">
          <span class="avatar avatar-sm avatar-circle bg-light text-primary fw-semibold d-flex align-items-center justify-content-center"><?= $initial ?></span>
          <span>
            <p class="fw-semibold mb-0"><?= htmlspecialchars($o['customer_name'] ?? 'Guest') ?></p>
            <small class="text-muted"><?= htmlspecialchars(ucfirst((string) ($o['payment_status'] ?? ''))) ?> · <?= htmlspecialchars(Sk_Order_model::source_label($o['order_source'] ?? 'unknown')) ?></small>
          </span>
        </span>
        <span class="badge <?= $orderBadge($st) ?>"><?= htmlspecialchars(Sk_Order_model::status_label($st)) ?></span>
      </div>
      <div class="px-3 pb-3 d-flex gap-2">
        <a href="<?= site_url('shopkart/orders/view/'.$o['id']) ?>" class="btn btn-sm btn-label-primary">View</a>
        <a href="<?= site_url('shopkart/orders/invoice/'.$o['id']) ?>" target="_blank" class="btn btn-sm btn-light">Invoice</a>
      </div>
    </div>
  </div>
  <?php endforeach; ?>
</div>
<?php $pages = (int) ceil(($total ?: 0) / max(1, (int) $limit)); if ($pages > 1): ?>
<div class="d-flex justify-content-between align-items-center mt-2 mb-4">
  <small class="text-muted"><?= (int) $total ?> orders</small>
  <nav><ul class="pagination pagination-sm mb-0">
    <?php for ($i = 1; $i <= $pages; $i++): ?>
      <li class="page-item <?= $i === (int) $page ? 'active' : '' ?>">
        <a class="page-link" href="?<?= $qs(['page' => $i]) ?>"><?= $i ?></a>
      </li>
    <?php endfor; ?>
  </ul></nav>
</div>
<?php endif; ?>
