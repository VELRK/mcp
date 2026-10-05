<?php $currency = $currency ?? sk_currency_symbol($settings); ?>
<?php 
$is_admin = empty($is_vendor_view); 
$name = $is_admin ? htmlspecialchars($admin['name'] ?? 'Admin') : htmlspecialchars($vendor['owner_name'] ?: ($admin['name'] ?? 'Vendor'));
$s = $is_admin ? [] : $stats;
$monthly = $is_admin ? $monthly_revenue : ($s['monthly_revenue'] ?? 0);
?>

<div class="row g-3 mb-4">
  <!-- Welcome Card -->
  <div class="col-lg-5">
    <div class="card shadow-sm border-0 h-100 overflow-hidden position-relative" style="background: var(--primary-color);">
      <div class="card-body p-4 d-flex flex-column justify-content-center">
        <div style="z-index: 2; position: relative;">
          <h4 class="fw-medium mb-3 text-white">Welcome Back, <?= $name ?>!</h4>
          <p class="mb-4 text-white-50 fs-14 lh-base" style="max-width: 85%;">
            <?= $is_admin ? "Here's a quick look at your platform's performance today. Stay on top of vendors, sales, and orders." : "Here's a quick look at your store's performance today. Stay on top of your sales, orders, and customers." ?>
          </p>
          <a href="<?= site_url($is_admin ? 'admin/orders' : 'admin/orders') ?>" class="btn btn-light btn-sm fw-medium text-primary px-4 py-2" style="color: var(--primary-color) !important;">View Reports</a>
        </div>
        <!-- Decorative blurred circle -->
        <div class="position-absolute bg-white rounded-circle" style="width: 250px; height: 250px; bottom: -80px; right: -50px; opacity: 0.15; filter: blur(40px); z-index: 1;"></div>
      </div>
    </div>
  </div>

  <!-- Sales Summary Chart -->
  <div class="col-lg-7">
    <div class="card shadow-sm border-0 h-100">
      <div class="card-header bg-white border-0 py-4 pb-0 d-flex justify-content-between align-items-center">
        <h5 class="card-title fw-semibold mb-0">Sales Summary</h5>
        <div class="dropdown">
            <span class="badge bg-success bg-opacity-10 text-success fw-normal px-3 py-2 fs-14">
              <?= $currency . number_format($monthly, 0) ?> this month
            </span>
        </div>
      </div>
      <div class="card-body pt-2">
        <div id="revenueChart"></div>
      </div>
    </div>
  </div>
</div>

<?php if (!$is_admin): ?>
<!-- Vendor Stats -->
<div class="row g-3 mb-4">
  <div class="col-6 col-lg-4">
    <div class="card shadow-sm border-0 h-100">
      <div class="card-body p-4">
        <div class="d-flex align-items-start justify-content-between mb-4">
          <div><h6 class="mb-2 text-muted fw-normal fs-15">My Products</h6></div>
          <div class="sk-stat-icon bg-primary bg-opacity-10 text-primary rounded-circle d-flex align-items-center justify-content-center" style="width: 48px; height: 48px;"><i class="bi bi-box-seam fs-4"></i></div>
        </div>
        <div class="d-flex justify-content-between align-items-center">
          <h4 class="mb-0 fw-medium"><?= number_format($s['products'] ?? 0) ?></h4>
        </div>
      </div>
    </div>
  </div>
  <div class="col-6 col-lg-4">
    <div class="card shadow-sm border-0 h-100">
      <div class="card-body p-4">
        <div class="d-flex align-items-start justify-content-between mb-4">
          <div><h6 class="mb-2 text-muted fw-normal fs-15">Orders</h6></div>
          <div class="sk-stat-icon bg-warning bg-opacity-10 text-warning rounded-circle d-flex align-items-center justify-content-center" style="width: 48px; height: 48px;"><i class="bi bi-cart-check fs-4"></i></div>
        </div>
        <div class="d-flex justify-content-between align-items-center">
          <h4 class="mb-0 fw-medium"><?= number_format($s['orders'] ?? 0) ?></h4>
        </div>
      </div>
    </div>
  </div>
  <div class="col-6 col-lg-4">
    <div class="card shadow-sm border-0 h-100">
      <div class="card-body p-4">
        <div class="d-flex align-items-start justify-content-between mb-4">
          <div><h6 class="mb-2 text-muted fw-normal fs-15">Revenue</h6></div>
          <div class="sk-stat-icon bg-success bg-opacity-10 text-success rounded-circle d-flex align-items-center justify-content-center" style="width: 48px; height: 48px;"><i class="bi bi-cash-stack fs-4"></i></div>
        </div>
        <div class="d-flex justify-content-between align-items-center">
          <h4 class="mb-0 fw-medium"><?= $currency . number_format($s['revenue'] ?? 0, 0) ?></h4>
        </div>
      </div>
    </div>
  </div>
</div>
<div class="row g-3 mb-3">
  <div class="col-auto"><span class="badge bg-danger"><?= $s['pending_orders'] ?? 0 ?> pending orders</span></div>
  <div class="col-auto"><span class="badge bg-secondary"><?= $s['low_stock'] ?? 0 ?> low stock</span></div>
  <div class="col-auto"><a href="<?= site_url('admin/stores/edit/'.($vendor['id']??'')) ?>" class="btn btn-sm btn-outline-primary">Store Settings</a></div>
</div>
<?php else: ?>
<!-- Admin Stats Row 1 -->
<div class="row g-3 mb-4">
  <div class="col-md-6 col-xl-3">
    <div class="card shadow-sm border-0 h-100">
      <div class="card-body p-4">
        <div class="d-flex align-items-start justify-content-between mb-4">
          <div><h6 class="mb-2 text-muted fw-normal fs-15">Total Orders</h6></div>
          <div class="sk-stat-icon bg-warning bg-opacity-10 text-warning rounded-circle d-flex align-items-center justify-content-center" style="width: 48px; height: 48px;"><i class="bi bi-cart-check fs-4"></i></div>
        </div>
        <div class="d-flex justify-content-between align-items-center">
          <h4 class="mb-0 fw-medium"><?= number_format($total_orders) ?></h4>
        </div>
      </div>
    </div>
  </div>
  <div class="col-md-6 col-xl-3">
    <div class="card shadow-sm border-0 h-100">
      <div class="card-body p-4">
        <div class="d-flex align-items-start justify-content-between mb-4">
          <div><h6 class="mb-2 text-muted fw-normal fs-15">Total Revenue</h6></div>
          <div class="sk-stat-icon bg-success bg-opacity-10 text-success rounded-circle d-flex align-items-center justify-content-center" style="width: 48px; height: 48px;"><i class="bi bi-cash-stack fs-4"></i></div>
        </div>
        <div class="d-flex justify-content-between align-items-center">
          <h4 class="mb-0 fw-medium"><?= $currency . number_format($total_revenue, 0) ?></h4>
        </div>
      </div>
    </div>
  </div>
  <div class="col-md-6 col-xl-3">
    <div class="card shadow-sm border-0 h-100">
      <div class="card-body p-4">
        <div class="d-flex align-items-start justify-content-between mb-4">
          <div><h6 class="mb-2 text-muted fw-normal fs-15">Active Vendors</h6></div>
          <div class="sk-stat-icon bg-primary bg-opacity-10 text-primary rounded-circle d-flex align-items-center justify-content-center" style="width: 48px; height: 48px;"><i class="bi bi-shop-window fs-4"></i></div>
        </div>
        <div class="d-flex justify-content-between align-items-center">
          <h4 class="mb-0 fw-medium"><?= number_format($vendor_counts['approved'] ?? 0) ?></h4>
        </div>
      </div>
    </div>
  </div>
  <div class="col-md-6 col-xl-3">
    <div class="card shadow-sm border-0 h-100">
      <div class="card-body p-4">
        <div class="d-flex align-items-start justify-content-between mb-4">
          <div><h6 class="mb-2 text-muted fw-normal fs-15">Total Sales</h6></div>
          <div class="sk-stat-icon bg-success bg-opacity-10 text-success rounded-circle d-flex align-items-center justify-content-center" style="width: 48px; height: 48px;"><i class="bi bi-currency-dollar fs-4"></i></div>
        </div>
        <div class="d-flex justify-content-between align-items-center">
          <h4 class="mb-0 fw-medium"><?= $currency . number_format($total_revenue, 0) ?></h4>
        </div>
      </div>
    </div>
  </div>
</div>
<!-- Admin Stats Row 2 -->
<div class="row g-3 mb-4">
  <div class="col-md-6 col-xl-4">
    <div class="card shadow-sm border-0 h-100">
      <div class="card-body p-4">
        <div class="d-flex align-items-start justify-content-between mb-4">
          <div><h6 class="mb-2 text-muted fw-normal fs-15">Pending Orders</h6></div>
          <div class="sk-stat-icon bg-danger bg-opacity-10 text-danger rounded-circle d-flex align-items-center justify-content-center" style="width: 48px; height: 48px;"><i class="bi bi-hourglass-split fs-4"></i></div>
        </div>
        <div class="d-flex justify-content-between align-items-center">
          <h4 class="mb-0 fw-medium"><?= number_format($pending_orders) ?></h4>
        </div>
      </div>
    </div>
  </div>
  <div class="col-md-6 col-xl-4">
    <div class="card shadow-sm border-0 h-100">
      <div class="card-body p-4">
        <div class="d-flex align-items-start justify-content-between mb-4">
          <div><h6 class="mb-2 text-muted fw-normal fs-15">Active Sessions</h6></div>
          <div class="sk-stat-icon bg-info bg-opacity-10 text-info rounded-circle d-flex align-items-center justify-content-center" style="width: 48px; height: 48px;"><i class="bi bi-people fs-4"></i></div>
        </div>
        <div class="d-flex justify-content-between align-items-center">
          <h4 class="mb-0 fw-medium"><?= $active_sessions ?></h4>
        </div>
      </div>
    </div>
  </div>
  <div class="col-md-6 col-xl-4">
    <div class="card shadow-sm border-0 h-100">
      <div class="card-body p-4">
        <div class="d-flex align-items-start justify-content-between mb-4">
          <div><h6 class="mb-2 text-muted fw-normal fs-15">Avg. Order Value</h6></div>
          <div class="sk-stat-icon bg-secondary bg-opacity-10 text-secondary rounded-circle d-flex align-items-center justify-content-center" style="width: 48px; height: 48px;"><i class="bi bi-currency-dollar fs-4"></i></div>
        </div>
        <div class="d-flex justify-content-between align-items-center">
          <h4 class="mb-0 fw-medium"><?= $currency . number_format($avg_order_value, 2) ?></h4>
        </div>
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
  <div class="col-lg-4">
    <div class="card shadow-sm border-0 h-100">
      <div class="card-header bg-white border-0 py-4 pb-0"><h5 class="card-title fw-semibold mb-0">Top Products</h5></div>
      <div class="card-body p-0 mt-3">
        <ul class="list-group list-group-flush border-top">
          <?php foreach ($top_products as $i => $tp): ?>
          <li class="list-group-item d-flex justify-content-between align-items-center px-4 py-3 border-0">
            <span class="d-flex align-items-center gap-3">
              <div class="avatar-sm rounded bg-warning bg-opacity-10 text-warning d-flex align-items-center justify-content-center" style="width:32px;height:32px;font-weight:600;"><?= $i+1 ?></div>
              <div>
                <h6 class="mb-0 text-truncate" style="max-width:140px; font-size:14px;"><?= htmlspecialchars($tp['product_name']) ?></h6>
                <small class="text-muted"><?= number_format((float)($tp['qty_sold'] ?? 0)) ?> sold</small>
              </div>
            </span>
            <span class="text-end fw-medium" style="font-size:15px;">
              <?= $currency . number_format((float)($tp['revenue'] ?? 0), 0) ?>
            </span>
          </li>
          <?php endforeach; ?>
          <?php if (empty($top_products)): ?><li class="list-group-item text-muted text-center small py-4 border-0">No data yet</li><?php endif; ?>
        </ul>
      </div>
    </div>
  </div>
  <div class="col-lg-4">
    <div class="card shadow-sm border-0 h-100">
      <div class="card-header bg-white border-0 py-4 pb-0 d-flex justify-content-between align-items-center">
        <h5 class="card-title fw-semibold mb-0">Orders Overview</h5>
      </div>
      <div class="card-body pt-4">
        <div id="ordersChart"></div>
      </div>
    </div>
  </div>
  <div class="col-lg-4">
    <div class="card shadow-sm border-0 h-100">
      <div class="card-header bg-white border-0 py-4 pb-0 d-flex justify-content-between align-items-center">
        <h5 class="card-title fw-semibold mb-0">Top Categories</h5>
      </div>
      <div class="card-body pt-4">
        <div id="categoriesChart"></div>
      </div>
    </div>
  </div>
</div>

<div class="card shadow-sm border-0 mb-4">
  <div class="card-header bg-white border-0 py-4 d-flex justify-content-between align-items-center">
    <h5 class="card-title fw-semibold mb-0">Recent Orders</h5>
    <a href="<?= site_url('admin/orders') ?>" class="btn btn-sm btn-outline-primary px-3 rounded-pill">View All</a>
  </div>
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead class="table-light text-muted fs-14">
            <tr>
                <th class="ps-4 fw-medium border-0">Order ID</th>
                <th class="fw-medium border-0">Customer</th>
                <th class="fw-medium border-0">Total</th>
                <th class="fw-medium border-0">Status</th>
                <th class="fw-medium border-0">Payment</th>
                <th class="fw-medium border-0">Date</th>
                <th class="pe-4 text-end border-0">Action</th>
            </tr>
        </thead>
        <tbody class="border-top-0">
          <?php foreach ($recent_orders as $o): ?>
          <tr>
            <td class="ps-4"><span class="fw-semibold text-primary">#<?= htmlspecialchars($o['order_number']) ?></span></td>
            <td>
                <div class="d-flex align-items-center gap-2">
                    <div class="bg-light rounded-circle d-flex align-items-center justify-content-center text-secondary" style="width:32px;height:32px;"><i class="bi bi-person"></i></div>
                    <span class="fs-14"><?= htmlspecialchars($o['customer_name'] ?? '-') ?></span>
                </div>
            </td>
            <td class="fw-medium"><?= $currency . number_format($o['vendor_total'] ?? $o['total'], 2) ?></td>
            <td>
                <?php 
                    $scolor = 'secondary';
                    if($o['status'] == 'completed') $scolor = 'success';
                    if($o['status'] == 'pending') $scolor = 'warning';
                    if($o['status'] == 'cancelled') $scolor = 'danger';
                ?>
                <span class="badge bg-<?= $scolor ?> bg-opacity-10 text-<?= $scolor ?> px-2 py-1 rounded-pill fw-normal"><?= ucfirst($o['status']) ?></span>
            </td>
            <td>
                <?php 
                    $pcolor = 'secondary';
                    if($o['payment_status'] == 'paid') $pcolor = 'success';
                    if($o['payment_status'] == 'unpaid') $pcolor = 'danger';
                ?>
                <span class="badge bg-<?= $pcolor ?> bg-opacity-10 text-<?= $pcolor ?> px-2 py-1 rounded-pill fw-normal"><?= ucfirst($o['payment_status']) ?></span>
            </td>
            <td class="text-muted fs-14"><?= date('d M Y', strtotime($o['created_at'])) ?></td>
            <td class="pe-4 text-end">
                <a href="<?= site_url('admin/orders/view/'.$o['id']) ?>" class="btn btn-sm btn-light rounded-circle" style="width:32px;height:32px;padding:0;line-height:32px;"><i class="bi bi-chevron-right text-muted"></i></a>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php if (empty($recent_orders)): ?><tr><td colspan="7" class="text-center text-muted py-5">No orders yet.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php
$labels   = array_column($revenue_chart, 'date');
$revenues = array_column($revenue_chart, 'revenue');
?>
<script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
<script>
// Revenue Chart
var optionsRev = {
  series: [{ name: 'Revenue', data: <?= json_encode($revenues) ?> }],
  chart: { type: 'area', height: 260, toolbar: { show: false }, zoom: { enabled: false }, parentHeightOffset: 0 },
  colors: ['#128C7E'],
  fill: { type: 'gradient', gradient: { shadeIntensity: 1, opacityFrom: 0.4, opacityTo: 0.05, stops: [0, 100] } },
  dataLabels: { enabled: false },
  stroke: { curve: 'smooth', width: 2 },
  xaxis: { categories: <?= json_encode($labels) ?>, axisBorder: { show: false }, axisTicks: { show: false }, labels: { style: { colors: '#a1aab2' } } },
  yaxis: { labels: { style: { colors: '#a1aab2' } } },
  grid: { borderColor: '#f1f1f1', strokeDashArray: 3, xaxis: { lines: { show: true } }, yaxis: { lines: { show: true } } }
};
new ApexCharts(document.querySelector("#revenueChart"), optionsRev).render();

// Orders Chart
var optionsOrd = {
  series: [{ name: 'Orders', data: <?= json_encode($order_counts ?? []) ?> }],
  chart: { type: 'area', height: 250, toolbar: { show: false }, zoom: { enabled: false }, parentHeightOffset: 0 },
  colors: ['#25D366'],
  fill: { type: 'gradient', gradient: { shadeIntensity: 1, opacityFrom: 0.4, opacityTo: 0.05, stops: [0, 100] } },
  dataLabels: { enabled: false },
  stroke: { curve: 'smooth', width: 2 },
  xaxis: { categories: <?= json_encode($order_labels ?? $labels) ?>, axisBorder: { show: false }, axisTicks: { show: false }, labels: { style: { colors: '#a1aab2' } } },
  yaxis: { labels: { style: { colors: '#a1aab2' } } },
  grid: { borderColor: '#f1f1f1', strokeDashArray: 3 }
};
new ApexCharts(document.querySelector("#ordersChart"), optionsOrd).render();

// Categories Doughnut
var optionsCat = {
  series: <?= json_encode(empty($category_values) ? [0] : $category_values) ?>,
  labels: <?= json_encode(empty($category_labels) ? ['No Data'] : $category_labels) ?>,
  chart: { type: 'donut', height: 250 },
  colors: ['#075E54', '#25D366', '#128c7e', '#0a7a5a', '#34b7f1'],
  plotOptions: { pie: { donut: { size: '75%' } } },
  dataLabels: { enabled: false },
  stroke: { width: 0 },
  legend: { position: 'bottom', markers: { radius: 12 } }
};
new ApexCharts(document.querySelector("#categoriesChart"), optionsCat).render();
</script>
