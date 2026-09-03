<?php $currency = sk_currency_symbol($settings); ?>

<div class="sk-page-header">
  <h5 class="sk-page-title"><i class="bi bi-bar-chart-line me-2 text-warning"></i>Reports</h5>
  <a href="<?= site_url('shopkart/reports/export?from=' . urlencode($from) . '&to=' . urlencode($to)) ?>" class="btn btn-sm btn-outline-success">
    <i class="bi bi-download me-1"></i> Export CSV
  </a>
</div>

<!-- Date filter -->
<div class="card sk-table-card shadow-sm mb-4">
  <div class="card-body py-2">
    <form method="GET" class="d-flex gap-2 align-items-center flex-wrap">
      <label class="form-label mb-0">From</label>
      <input type="date" name="from" class="form-control form-control-sm" style="max-width:160px;" value="<?= $from ?>">
      <label class="form-label mb-0">To</label>
      <input type="date" name="to" class="form-control form-control-sm" style="max-width:160px;" value="<?= $to ?>">
      <button class="btn btn-sm btn-warning px-3">Apply</button>
    </form>
  </div>
</div>

<!-- Summary cards -->
<div class="row g-3 mb-4">
  <div class="col-md-3">
    <div class="card sk-stat-card shadow-sm text-center py-3">
      <div class="fs-3 fw-bold text-success"><?= $currency . number_format($revenue,0) ?></div>
      <div class="text-muted small">Total Revenue</div>
    </div>
  </div>
  <div class="col-md-3">
    <div class="card sk-stat-card shadow-sm text-center py-3">
      <div class="fs-3 fw-bold text-primary"><?= number_format($order_count) ?></div>
      <div class="text-muted small">Total Orders</div>
    </div>
  </div>
  <div class="col-md-3">
    <div class="card sk-stat-card shadow-sm text-center py-3">
      <div class="fs-3 fw-bold text-warning">
        <?= $order_count > 0 ? $currency . number_format($revenue / $order_count, 0) : $currency.'0' ?>
      </div>
      <div class="text-muted small">Avg. Order Value</div>
    </div>
  </div>
  <div class="col-md-3">
    <div class="card sk-stat-card shadow-sm text-center py-3">
      <?php
        $delivered = 0;
        foreach ($by_status as $bs) { if ($bs['status']==='delivered') $delivered = $bs['count']; }
      ?>
      <div class="fs-3 fw-bold text-info"><?= number_format($delivered) ?></div>
      <div class="text-muted small">Delivered Orders</div>
    </div>
  </div>
</div>

<!-- Revenue chart + By Status -->
<div class="row g-3 mb-4">
  <div class="col-lg-8">
    <div class="card sk-table-card shadow-sm h-100">
      <div class="card-header bg-white border-0 py-3 fw-semibold">Revenue by Day</div>
      <div class="card-body"><canvas id="reportChart" height="110"></canvas></div>
    </div>
  </div>
  <div class="col-lg-4 d-flex flex-column gap-3">
    <div class="card sk-table-card shadow-sm">
      <div class="card-header bg-white border-0 py-3 fw-semibold">Orders by Status</div>
      <div class="card-body p-0">
        <?php
        $statusColors = [
          'pending'    => 'warning',
          'confirmed'  => 'primary',
          'processing' => 'info',
          'shipped'    => 'secondary',
          'delivered'  => 'success',
          'cancelled'  => 'danger',
          'refunded'   => 'dark',
        ];
        $totalCount = array_sum(array_column($by_status, 'count'));
        ?>
        <?php if (!empty($by_status)): ?>
        <ul class="list-group list-group-flush">
          <?php foreach ($by_status as $bs): ?>
          <?php $pct = $totalCount > 0 ? round(($bs['count'] / $totalCount) * 100) : 0; ?>
          <?php $color = $statusColors[$bs['status']] ?? 'secondary'; ?>
          <li class="list-group-item px-3 py-2">
            <div class="d-flex justify-content-between align-items-center mb-1">
              <span class="badge bg-<?= $color ?>"><?= ucfirst($bs['status']) ?></span>
              <span class="fw-semibold"><?= number_format($bs['count']) ?> <small class="text-muted fw-normal">(<?= $pct ?>%)</small></span>
            </div>
            <div class="progress" style="height:5px;">
              <div class="progress-bar bg-<?= $color ?>" style="width:<?= $pct ?>%"></div>
            </div>
          </li>
          <?php endforeach; ?>
          <li class="list-group-item px-3 py-2 d-flex justify-content-between">
            <span class="text-muted small">Total</span>
            <span class="fw-bold"><?= number_format($totalCount) ?></span>
          </li>
        </ul>
        <?php else: ?>
        <p class="text-muted text-center py-4 mb-0">No orders yet.</p>
        <?php endif; ?>
      </div>
    </div>

    <!-- Payment Status breakdown -->
    <div class="card sk-table-card shadow-sm">
      <div class="card-header bg-white border-0 py-3 fw-semibold">Payment Status</div>
      <div class="card-body p-0">
        <?php
        $payColors = ['paid'=>'success','pending'=>'warning','failed'=>'danger','refunded'=>'info'];
        $payTotal  = array_sum(array_column($by_payment, 'count'));
        ?>
        <?php if (!empty($by_payment)): ?>
        <ul class="list-group list-group-flush">
          <?php foreach ($by_payment as $ps): ?>
          <?php $pct = $payTotal > 0 ? round(($ps['count'] / $payTotal) * 100) : 0; ?>
          <?php $c = $payColors[$ps['payment_status']] ?? 'secondary'; ?>
          <li class="list-group-item px-3 py-2">
            <div class="d-flex justify-content-between align-items-center mb-1">
              <span class="badge bg-<?= $c ?>"><?= ucfirst($ps['payment_status']) ?></span>
              <span class="fw-semibold"><?= number_format($ps['count']) ?> <small class="text-muted fw-normal">(<?= $pct ?>%)</small></span>
            </div>
            <div class="progress" style="height:5px;">
              <div class="progress-bar bg-<?= $c ?>" style="width:<?= $pct ?>%"></div>
            </div>
          </li>
          <?php endforeach; ?>
        </ul>
        <?php else: ?>
        <p class="text-muted text-center py-4 mb-0">No orders yet.</p>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<!-- Top Products -->
<div class="card sk-table-card shadow-sm">
  <div class="card-header bg-white border-0 py-3 fw-semibold">Top Selling Products</div>
  <div class="card-body p-0">
    <table class="table mb-0">
      <thead><tr><th>#</th><th>Product</th><th>Qty Sold</th><th>Revenue</th></tr></thead>
      <tbody>
        <?php foreach ($top_products as $i => $tp): ?>
        <tr>
          <td><span class="badge bg-warning text-dark"><?= $i+1 ?></span></td>
          <td><?= htmlspecialchars($tp['product_name']) ?></td>
          <td><?= number_format($tp['qty_sold']) ?></td>
          <td><?= $currency . number_format($tp['revenue'],2) ?></td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($top_products)): ?>
        <tr><td colspan="4" class="text-center text-muted py-4">No data for this period.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php
$labels = array_column($by_day, 'date');
$revs   = array_column($by_day, 'revenue');
?>
<script>
new Chart(document.getElementById('reportChart').getContext('2d'), {
  type: 'bar',
  data: {
    labels: <?= json_encode($labels) ?>,
    datasets: [{ label: 'Revenue', data: <?= json_encode($revs) ?>, backgroundColor: 'rgba(245,158,11,0.7)' }]
  },
  options: { responsive: true, plugins: { legend: { display: false } },
    scales: { y: { beginAtZero: true, ticks: { callback: v => <?= json_encode(sk_currency_symbol($settings)) ?> + v.toLocaleString() } }, x: { ticks: { maxTicksLimit: 15 } } }
  }
});
</script>
