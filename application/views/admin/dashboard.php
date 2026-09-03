<?php $currency = $currency ?? sk_currency_symbol($settings); ?>

<div class="sk-page-header">
  <h5 class="sk-page-title"><i class="bi bi-speedometer2 me-2 text-warning"></i>
    <?= !empty($is_vendor_view) ? 'Vendor Dashboard' : 'Dashboard' ?>
  </h5>
  <span class="text-muted small">
    <?php if (!empty($is_vendor_view) && !empty($vendor)): ?>
      Welcome back, <?= htmlspecialchars($vendor['owner_name'] ?: $admin['name']) ?>!
      — <strong><?= htmlspecialchars($vendor['business_name']) ?></strong>
    <?php else: ?>
      Welcome back, <?= htmlspecialchars($admin['name']) ?>!
    <?php endif; ?>
  </span>
</div>

<?php if (!empty($is_vendor_view)): ?>
<?php $s = $stats; ?>
<div class="row g-3 mb-4">
  <div class="col-6 col-lg-4">
    <div class="card sk-stat-card shadow-sm h-100">
      <div class="card-body d-flex align-items-center gap-3">
        <div class="sk-stat-icon bg-primary bg-opacity-10 text-primary"><i class="bi bi-box-seam"></i></div>
        <div><div class="fs-4 fw-bold"><?= number_format($s['products']) ?></div><div class="text-muted small">My Products</div></div>
      </div>
    </div>
  </div>
  <div class="col-6 col-lg-4">
    <div class="card sk-stat-card shadow-sm h-100">
      <div class="card-body d-flex align-items-center gap-3">
        <div class="sk-stat-icon bg-warning bg-opacity-10 text-warning"><i class="bi bi-cart-check"></i></div>
        <div><div class="fs-4 fw-bold"><?= number_format($s['orders']) ?></div><div class="text-muted small">Orders</div></div>
      </div>
    </div>
  </div>
  <div class="col-6 col-lg-4">
    <div class="card sk-stat-card shadow-sm h-100">
      <div class="card-body d-flex align-items-center gap-3">
        <div class="sk-stat-icon bg-success bg-opacity-10 text-success"><i class="bi bi-cash-stack"></i></div>
        <div><div class="fs-4 fw-bold"><?= $currency . number_format($s['revenue'], 0) ?></div><div class="text-muted small">Revenue</div></div>
      </div>
    </div>
  </div>
</div>
<div class="row g-3 mb-3">
  <div class="col-auto"><span class="badge bg-danger"><?= $s['pending_orders'] ?> pending orders</span></div>
  <div class="col-auto"><span class="badge bg-secondary"><?= $s['low_stock'] ?> low stock</span></div>
  <div class="col-auto"><a href="<?= site_url('admin/stores/edit/'.$vendor['id']) ?>" class="btn btn-sm btn-outline-primary">Store Settings</a></div>
</div>
<?php else: ?>
<div class="row g-3 mb-4">
  <div class="col-6 col-lg-3">
    <div class="card sk-stat-card shadow-sm h-100">
      <div class="card-body d-flex align-items-center gap-3">
        <div class="sk-stat-icon bg-warning bg-opacity-10 text-warning"><i class="bi bi-cart-check"></i></div>
        <div><div class="fs-4 fw-bold"><?= number_format($total_orders) ?></div><div class="text-muted small">Total Orders</div></div>
      </div>
    </div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="card sk-stat-card shadow-sm h-100">
      <div class="card-body d-flex align-items-center gap-3">
        <div class="sk-stat-icon bg-danger bg-opacity-10 text-danger"><i class="bi bi-hourglass-split"></i></div>
        <div><div class="fs-4 fw-bold"><?= number_format($pending_orders) ?></div><div class="text-muted small">Pending Orders</div></div>
      </div>
    </div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="card sk-stat-card shadow-sm h-100">
      <div class="card-body d-flex align-items-center gap-3">
        <div class="sk-stat-icon bg-success bg-opacity-10 text-success"><i class="bi bi-cash-stack"></i></div>
        <div><div class="fs-4 fw-bold"><?= $currency . number_format($total_revenue, 0) ?></div><div class="text-muted small">Total Revenue</div></div>
      </div>
    </div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="card sk-stat-card shadow-sm h-100">
      <div class="card-body d-flex align-items-center gap-3">
        <div class="sk-stat-icon bg-primary bg-opacity-10 text-primary"><i class="bi bi-shop-window"></i></div>
        <div><div class="fs-4 fw-bold"><?= number_format($vendor_counts['approved'] ?? 0) ?></div><div class="text-muted small">Active Vendors</div></div>
      </div>
    </div>
  </div>
</div>
<div class="row g-3 mb-3">
  <div class="col-auto"><span class="badge bg-dark"><?= number_format($vendor_counts['total'] ?? 0) ?> vendors</span></div>
  <div class="col-auto"><span class="badge bg-warning text-dark"><?= number_format($vendor_counts['pending'] ?? 0) ?> pending approval</span></div>
  <div class="col-auto"><span class="badge bg-info text-dark"><?= number_format($total_products) ?> products</span></div>
  <div class="col-auto"><span class="badge bg-secondary"><?= number_format($total_customers) ?> customers</span></div>
</div>
<?php endif; ?>

<div class="row g-3 mb-4">
  <div class="col-lg-8">
    <div class="card sk-table-card shadow-sm h-100">
      <div class="card-header bg-white border-0 py-3 d-flex justify-content-between align-items-center">
        <span class="fw-semibold">Revenue by Day (Last 30 Days)</span>
        <?php if (!empty($is_vendor_view)): ?>
        <span class="badge bg-success"><?= $currency . number_format($s['monthly_revenue'] ?? 0, 0) ?> this month</span>
        <?php elseif (empty($is_vendor_view)): ?>
        <span class="badge bg-success"><?= $currency . number_format($monthly_revenue, 0) ?> this month</span>
        <?php endif; ?>
      </div>
      <div class="card-body"><canvas id="revenueChart" height="110"></canvas></div>
    </div>
  </div>
  <div class="col-lg-4">
    <div class="card sk-table-card shadow-sm h-100">
      <div class="card-header bg-white border-0 py-3"><span class="fw-semibold">Top Products by Revenue</span></div>
      <div class="card-body p-0">
        <ul class="list-group list-group-flush">
          <?php foreach ($top_products as $i => $tp): ?>
          <li class="list-group-item d-flex justify-content-between align-items-center px-3 py-2">
            <span class="d-flex align-items-center gap-2">
              <span class="badge bg-warning text-dark"><?= $i+1 ?></span>
              <span class="small text-truncate" style="max-width:130px;"><?= htmlspecialchars($tp['product_name']) ?></span>
            </span>
            <span class="text-end small">
              <div class="fw-semibold"><?= $currency . number_format((float)($tp['revenue'] ?? 0), 0) ?></div>
              <div class="text-muted"><?= number_format((float)($tp['qty_sold'] ?? 0)) ?> sold</div>
            </span>
          </li>
          <?php endforeach; ?>
          <?php if (empty($top_products)): ?><li class="list-group-item text-muted text-center small py-4">No data yet</li><?php endif; ?>
        </ul>
      </div>
    </div>
  </div>
</div>

<div class="card sk-table-card shadow-sm">
  <div class="card-header bg-white border-0 py-3 d-flex justify-content-between align-items-center">
    <span class="fw-semibold">Recent Orders</span>
    <a href="<?= site_url('admin/orders') ?>" class="btn btn-sm btn-outline-warning">View All</a>
  </div>
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-hover mb-0">
        <thead><tr><th>Order #</th><th>Customer</th><th>Total</th><th>Status</th><th>Payment</th><th>Date</th><th></th></tr></thead>
        <tbody>
          <?php foreach ($recent_orders as $o): ?>
          <tr>
            <td><span class="fw-semibold"><?= htmlspecialchars($o['order_number']) ?></span></td>
            <td><?= htmlspecialchars($o['customer_name'] ?? '-') ?></td>
            <td><?= $currency . number_format($o['vendor_total'] ?? $o['total'], 2) ?></td>
            <td><span class="badge badge-<?= $o['status'] ?>"><?= ucfirst($o['status']) ?></span></td>
            <td><span class="badge badge-<?= $o['payment_status'] ?>"><?= ucfirst($o['payment_status']) ?></span></td>
            <td><?= date('d M y', strtotime($o['created_at'])) ?></td>
            <td><a href="<?= site_url('admin/orders/view/'.$o['id']) ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-eye"></i></a></td>
          </tr>
          <?php endforeach; ?>
          <?php if (empty($recent_orders)): ?><tr><td colspan="7" class="text-center text-muted py-4">No orders yet.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php
$labels   = array_column($revenue_chart, 'date');
$revenues = array_column($revenue_chart, 'revenue');
?>
<script>
new Chart(document.getElementById('revenueChart').getContext('2d'), {
  type: 'line',
  data: { labels: <?= json_encode($labels) ?>, datasets: [{ label: 'Revenue', data: <?= json_encode($revenues) ?>, borderColor: '#f59e0b', backgroundColor: 'rgba(245,158,11,0.1)', tension: 0.4, fill: true, pointRadius: 3 }] },
  options: { responsive: true, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true }, x: { ticks: { maxTicksLimit: 10 } } } }
});
</script>
