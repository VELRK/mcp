<?php
$currency = $currency ?? sk_currency_symbol($settings ?? []);
$is_admin = empty($is_vendor_view);
$name = $is_admin
    ? htmlspecialchars($admin['name'] ?? 'Admin')
    : htmlspecialchars($vendor['owner_name'] ?: ($admin['name'] ?? 'Vendor'));
$s = $is_admin ? [] : ($stats ?? []);
$monthly = (float) ($is_admin ? ($monthly_revenue ?? 0) : ($s['monthly_revenue'] ?? 0));
$totalRev = (float) ($is_admin ? ($total_revenue ?? 0) : ($s['revenue'] ?? 0));
$orderCount = (int) ($is_admin ? ($total_orders ?? 0) : ($s['orders'] ?? 0));
$customerCount = (int) ($is_admin ? ($total_customers ?? 0) : 0);
$pendingCount = (int) ($is_admin ? ($pending_orders ?? 0) : ($s['pending_orders'] ?? 0));
$productCount = (int) ($is_admin ? ($total_products ?? 0) : ($s['products'] ?? 0));
$newCustomers = (int) ($new_customers ?? 0);
$refunds = (float) ($refunds_total ?? 0);
$statusCounts = $status_counts ?? [];
$categorySales = $category_sales ?? [];
$topCustomers = $top_customers ?? [];
$topProducts = $top_products ?? [];
$recentOrders = $recent_orders ?? [];
$chart = $revenue_chart ?? [];
$money = static function ($n) use ($currency) {
    return $currency . number_format((float) $n, 0);
};
$catMax = 0;
foreach ($categorySales as $row) {
    $catMax = max($catMax, (float) ($row['revenue'] ?? 0));
}
$barColors = ['primary', 'warning', 'danger', 'info', 'secondary', 'success'];
$statusMeta = [
    'pending'    => ['Pending', 'badge-label-warning', 'mdi-clock-outline'],
    'confirmed'  => ['Confirmed', 'badge-label-primary', 'mdi-check-circle-outline'],
    'processing' => ['Processing', 'badge-label-info', 'mdi-package-variant'],
    'shipped'    => ['Shipped', 'badge-label-info', 'mdi-truck-outline'],
    'delivered'  => ['Delivered', 'badge-label-success', 'mdi-home-outline'],
    'cancelled'  => ['Cancelled', 'badge-label-danger', 'mdi-close-circle-outline'],
];
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
        'paid' => 'badge-label-success',
        'unpaid' => 'badge-label-danger',
        'failed' => 'badge-label-danger',
        'refunded' => 'badge-label-secondary',
    ];
    return $map[$status] ?? 'badge-label-secondary';
};
$page_title = 'Ecommerce';
$breadcrumb = [
    ['label' => 'Dashboard', 'url' => site_url('shopkart/dashboard')],
    'Ecommerce',
];
$this->load->view('admin/partials/page_title', compact('page_title', 'breadcrumb'));
?>
<style>
.dash-list .rich-list-content { min-width: 0; }
.dash-list .rich-list-title { display: block; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.dash-list .rich-list-append { flex-shrink: 0; white-space: nowrap; }
.dash-cat-name { max-width: 9rem; }
</style>

<div class="row">
  <div class="col-xl-8 col-xxl-9">
    <div class="row">
      <div class="col-xl-5 d-flex">
        <div class="card overflow-hidden position-relative w-100">
          <div class="card-body d-flex flex-column justify-content-between position-relative overflow-hidden" style="min-height:220px;">
            <div class="position-relative" style="z-index:3;max-width:22rem;">
              <h4 class="fw-medium mb-3">Welcome Back, <?= $name ?>!</h4>
              <p class="mb-4 text-muted fs-14 lh-base">
                <?= $is_admin
                    ? "Here's a quick look at your platform today. Stay on top of vendors, sales, and orders."
                    : "Here's a quick look at your store today. Stay on top of sales, orders, and stock." ?>
              </p>
            </div>
            <div class="position-relative" style="z-index:3;">
              <h3 class="fw-normal mb-2"><?= $money($monthly) ?></h3>
              <p class="text-muted fs-14 mb-4">Monthly sales</p>
              <a href="<?= site_url('admin/orders') ?>" class="btn btn-primary">View Reports</a>
            </div>
            <img src="<?= base_url('assets/aquiry/images/dashboard/ecommerce/welcome.png') ?>" alt="" class="position-absolute z-2" style="right:8px;bottom:10px;height:96px;width:auto;max-width:48%;object-fit:contain;">
            <div class="position-absolute h-44 w-44 bg-primary me-16 bottom-0 end-0 rounded-circle blury-effect"></div>
          </div>
        </div>
      </div>
      <div class="col-xl-7 d-flex">
        <div class="card w-100">
          <div class="card-header">
            <h5 class="card-title">Sales Summary</h5>
            <span class="text-muted">Monthly</span>
          </div>
          <div class="card-body pt-0">
            <div id="salesSummaryChart" data-colors='["var(--bs-primary)"]' class="apex-charts" dir="ltr"></div>
          </div>
        </div>
      </div>
    </div>

    <div class="row">
      <?php
      $statCards = [
          ['Total Sales', $money($totalRev), 'All paid orders', 'mdi-shopping-outline', 'info', 75],
          ['Revenue', $money($monthly), 'This month', 'mdi-credit-card-outline', 'success', 80],
          ['Total Orders', number_format($orderCount), $pendingCount . ' pending', 'mdi-cart-outline', 'warning', 60],
          [$is_admin ? 'Customers' : 'Products', number_format($is_admin ? $customerCount : $productCount), $is_admin ? ($newCustomers . ' new this month') : (($s['low_stock'] ?? 0) . ' low stock'), 'mdi-account-group-outline', 'danger', 66],
      ];
      foreach ($statCards as $card): ?>
      <div class="col-md-6 col-xl-3 d-flex">
        <div class="card shadow-sm border-0 w-100">
          <div class="card-body">
            <div class="d-flex align-items-start justify-content-between mb-3">
              <div>
                <h6 class="mb-2"><?= $card[0] ?></h6>
                <p class="text-muted mb-0"><?= htmlspecialchars($card[2]) ?></p>
              </div>
              <div class="avatar size-11 avatar-label-<?= $card[4] ?> avatar-circle">
                <i class="mdi <?= $card[3] ?> fs-20"></i>
              </div>
            </div>
            <h4 class="mb-0 fw-medium"><?= $card[1] ?></h4>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>

    <div class="row">
      <div class="col-md-6 col-xl-4 d-flex" style="min-width:0;">
        <div class="card w-100 overflow-hidden">
          <div class="card-header">
            <h5 class="card-title">Sales by Category</h5>
            <a href="<?= site_url('shopkart/categories') ?>" class="text-muted">See All <i class="mdi mdi-arrow-right"></i></a>
          </div>
          <div class="card-body">
            <div class="table-responsive">
              <table class="table table-borderless align-middle mb-0">
                <tbody>
                  <?php if (empty($categorySales)): ?>
                  <tr><td class="text-muted text-center py-4">No paid sales yet.</td></tr>
                  <?php endif; ?>
                  <?php foreach ($categorySales as $i => $cat):
                    $pct = $catMax > 0 ? (int) round(((float) $cat['revenue'] / $catMax) * 100) : 0;
                    $tone = $barColors[$i % count($barColors)];
                  ?>
                  <tr>
                    <td class="dash-cat-name"><p class="fw-semibold mb-0 text-truncate"><?= htmlspecialchars($cat['name'] ?: 'Uncategorised') ?></p></td>
                    <td class="text-nowrap"><?= $pct ?>%</td>
                    <td>
                      <div class="progress progress-sm ms-auto bg-<?= $tone ?>-subtle" style="min-width:96px;">
                        <div class="progress-bar bg-<?= $tone ?>" style="width: <?= $pct ?>%;"></div>
                      </div>
                    </td>
                  </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>

      <div class="col-md-6 col-xl-4 d-flex" style="min-width:0;">
        <div class="card w-100 overflow-hidden">
          <div class="card-header">
            <h5 class="card-title">Top Products</h5>
            <a href="<?= site_url('shopkart/products') ?>" class="text-muted">See All</a>
          </div>
          <div class="card-body">
            <?php if (empty($topProducts)): ?>
            <p class="text-muted mb-0">No product sales yet.</p>
            <?php endif; ?>
            <div class="rich-list dash-list">
            <?php foreach (array_slice($topProducts, 0, 6) as $tp): ?>
            <div class="rich-list-item px-0 py-2">
              <div class="rich-list-prepend">
                <div class="avatar avatar-sm bg-primary-subtle text-primary rounded d-flex align-items-center justify-content-center">
                  <i class="mdi mdi-package-variant"></i>
                </div>
              </div>
              <div class="rich-list-content">
                <a href="<?= !empty($tp['product_id']) ? site_url('shopkart/products/edit/'.$tp['product_id']) : site_url('shopkart/products') ?>" class="rich-list-title text-body fs-14 fw-semibold">
                  <?= htmlspecialchars($tp['product_name'] ?? 'Product') ?>
                </a>
                <span class="rich-list-subtitle"><?= number_format((float) ($tp['qty_sold'] ?? 0)) ?> units sold</span>
              </div>
              <div class="rich-list-append">
                <span class="fw-semibold"><?= $money($tp['revenue'] ?? 0) ?></span>
              </div>
            </div>
            <?php endforeach; ?>
            </div>
          </div>
        </div>
      </div>

      <div class="col-md-6 col-xl-4 d-flex" style="min-width:0;">
        <div class="card w-100 overflow-hidden">
          <div class="card-header">
            <h5 class="card-title">Top Customers</h5>
            <a href="<?= site_url('admin/customers') ?>" class="text-muted">See All</a>
          </div>
          <div class="card-body">
            <?php if (empty($topCustomers)): ?>
            <p class="text-muted mb-0">No customer sales yet.</p>
            <?php endif; ?>
            <div class="rich-list dash-list">
            <?php foreach ($topCustomers as $cust):
              $initial = strtoupper(substr((string) ($cust['name'] ?: '?'), 0, 1));
            ?>
            <div class="rich-list-item px-0 py-2">
              <div class="rich-list-prepend">
                <div class="avatar avatar-sm avatar-circle bg-light text-primary fw-semibold d-flex align-items-center justify-content-center"><?= $initial ?></div>
              </div>
              <div class="rich-list-content">
                <a href="<?= !empty($cust['id']) ? site_url('admin/customers/view/'.$cust['id']) : '#' ?>" class="rich-list-title text-body fs-14 fw-semibold">
                  <?= htmlspecialchars($cust['name'] ?: 'Guest') ?>
                </a>
                <span class="rich-list-subtitle"><?= number_format((int) ($cust['orders'] ?? 0)) ?> orders</span>
              </div>
              <div class="rich-list-append">
                <span class="fw-semibold"><?= $money($cust['spent'] ?? 0) ?></span>
              </div>
            </div>
            <?php endforeach; ?>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="col-xl-4 col-xxl-3">
    <div class="card">
      <div class="card-body">
        <h5 class="card-title mb-3">Order Status</h5>
        <?php foreach ($statusMeta as $key => $meta): ?>
        <div class="d-flex align-items-center justify-content-between py-2">
          <span class="d-flex align-items-center gap-2">
            <i class="mdi <?= $meta[2] ?> text-muted"></i>
            <?= $meta[0] ?>
          </span>
          <span class="badge <?= $meta[1] ?>"><?= number_format((int) ($statusCounts[$key] ?? 0)) ?></span>
        </div>
        <?php endforeach; ?>

        <?php
          $revSeries = array_map('floatval', ($chart_pack['revenue'] ?? []));
          $curMonthRev = $revSeries ? (float) end($revSeries) : 0;
          $prevMonthRev = count($revSeries) > 1 ? (float) $revSeries[count($revSeries) - 2] : 0;
          $trendPct = $prevMonthRev > 0 ? (int) round((($curMonthRev - $prevMonthRev) / $prevMonthRev) * 100) : 0;
          $trendUp = $trendPct >= 0;
        ?>
        <div class="mt-4 pt-3 border-top">
          <h5 class="card-title mb-3">Order Statistics</h5>
          <div class="d-flex align-items-start justify-content-between mb-3">
            <div>
              <h5 class="fw-medium mb-1"><?= $money($monthly) ?></h5>
              <p class="text-muted mb-0">Monthly Earnings</p>
            </div>
            <span class="text-muted fs-13">
              <i class="mdi <?= $trendUp ? 'mdi-trending-up text-success' : 'mdi-trending-down text-danger' ?> me-1"></i><?= $trendUp ? '+' : '' ?><?= $trendPct ?>%
            </span>
          </div>
          <div id="orderStatistics" data-colors='["var(--bs-primary)"]' class="apex-charts" dir="ltr"></div>
        </div>

        <div class="mt-4 pt-3 border-top">
          <h5 class="card-title mb-3">Quick Links</h5>
          <div class="d-grid gap-2">
            <a href="<?= site_url('shopkart/products/add') ?>" class="btn btn-primary"><i class="mdi mdi-plus-circle-outline me-1"></i>Add Product</a>
            <a href="<?= site_url('shopkart/orders') ?>" class="btn btn-light border"><i class="mdi mdi-receipt-text-outline me-1"></i>View Orders</a>
            <a href="<?= site_url('admin/customers') ?>" class="btn btn-light border"><i class="mdi mdi-account-group-outline me-1"></i>View Customers</a>
            <?php if ($is_admin): ?>
            <a href="<?= site_url('shopkart/vendors') ?>" class="btn btn-light border"><i class="mdi mdi-storefront-outline me-1"></i>Vendors</a>
            <a href="<?= site_url('admin/reports') ?>" class="btn btn-light border"><i class="mdi mdi-chart-line me-1"></i>Reports</a>
            <?php else: ?>
            <a href="<?= site_url('shopkart/stores/edit/'.($vendor['id'] ?? '')) ?>" class="btn btn-light border"><i class="mdi mdi-store-outline me-1"></i>Store Settings</a>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<div class="row">
  <div class="col-lg-6 d-flex">
    <div class="card w-100">
      <div class="card-header border-bottom-0 pb-0">
        <h5 class="card-title">Revenue Statistics</h5>
        <a href="<?= site_url('admin/reports') ?>" class="btn btn-primary btn-sm">Reports</a>
      </div>
      <div class="card-body overflow-hidden">
        <div class="d-flex flex-wrap gap-4 mb-3">
          <div>
            <p class="mb-1 text-muted">Total Revenue</p>
            <h5 class="mb-0 fw-medium"><?= $money($totalRev) ?></h5>
          </div>
          <div>
            <p class="mb-1 text-muted">Total Refunds</p>
            <h5 class="mb-0 fw-medium"><?= $money($refunds) ?></h5>
          </div>
          <div>
            <p class="mb-1 text-muted">Avg. Order</p>
            <h5 class="mb-0 fw-medium"><?= $currency . number_format((float) ($avg_order_value ?? ($orderCount ? $totalRev / max(1, $orderCount) : 0)), 0) ?></h5>
          </div>
        </div>
        <div id="teamProductivityChart" data-colors='["var(--bs-success)", "var(--bs-primary)"]' class="apex-charts" dir="ltr"></div>
      </div>
    </div>
  </div>
  <div class="col-lg-6 d-flex">
    <div class="card w-100">
      <div class="card-header">
        <h5 class="card-title">Top Selling Products</h5>
        <a href="<?= site_url('shopkart/products') ?>" class="text-muted">View all</a>
      </div>
      <div class="card-body">
        <div class="table-responsive">
          <table class="table align-middle mb-0">
            <thead class="table-light">
              <tr>
                <th>Product</th>
                <th>Units Sold</th>
                <th>Revenue</th>
                <th class="text-end">Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach (array_slice($topProducts, 0, 5) as $tp): ?>
              <tr>
                <td class="fw-medium" style="max-width:16rem;"><span class="d-block text-truncate"><?= htmlspecialchars($tp['product_name'] ?? '') ?></span></td>
                <td><?= number_format((float) ($tp['qty_sold'] ?? 0)) ?></td>
                <td><?= $money($tp['revenue'] ?? 0) ?></td>
                <td class="text-end">
                  <?php if (!empty($tp['product_id'])): ?>
                  <a href="<?= site_url('shopkart/products/edit/'.$tp['product_id']) ?>" class="btn btn-sm btn-label-primary btn-icon" aria-label="Edit"><i class="mdi mdi-pencil-outline"></i></a>
                  <?php endif; ?>
                </td>
              </tr>
              <?php endforeach; ?>
              <?php if (empty($topProducts)): ?>
              <tr><td colspan="4" class="text-center text-muted py-4">No products yet.</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>

<?php $pack = $chart_pack ?? ['earnings_percent' => 0, 'week_orders' => []]; ?>
<div class="row">
  <div class="col-md-6 d-flex">
    <div class="card w-100">
      <div class="card-body">
        <div class="d-flex align-items-start justify-content-between mb-1">
          <div>
            <h4 class="fw-medium"><?= $money($monthly) ?></h4>
            <p class="text-muted fs-14 mb-0">Monthly Earnings</p>
          </div>
        </div>
        <div id="monthlyEarningsChart" data-colors='["var(--bs-success)"]' class="apex-charts" dir="ltr"></div>
      </div>
    </div>
  </div>
  <div class="col-md-6 d-flex">
    <div class="card w-100">
      <div class="card-body d-flex flex-column">
        <div class="d-flex align-items-start justify-content-between mb-1">
          <div>
            <h4 class="fw-medium"><?= number_format(array_sum($pack['week_orders'] ?? [])) ?></h4>
            <p class="text-muted fs-14 mb-0">Weekly Orders</p>
          </div>
        </div>
        <div id="weeklyOrdersChart" data-colors='["rgba(var(--bs-danger-rgb), 0.3)"]' class="apex-charts mt-auto mb-2" dir="ltr"></div>
        <span class="text-muted">Last 7 days</span>
      </div>
    </div>
  </div>
</div>

<div class="card">
  <div class="card-header">
    <h5 class="card-title">Recent Orders</h5>
    <a href="<?= site_url('shopkart/orders') ?>" class="btn btn-sm btn-primary">View All</a>
  </div>
  <div class="card-body">
    <div class="table-responsive">
      <table class="table align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th>Order ID</th>
            <th>Customer</th>
            <th>Total</th>
            <th>Status</th>
            <th>Payment</th>
            <th>Date</th>
            <th class="text-end">Action</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($recentOrders as $o):
            $st = (string) ($o['status'] ?? '');
            $pay = (string) ($o['payment_status'] ?? '');
          ?>
          <tr>
            <td class="text-nowrap"><a href="<?= site_url('shopkart/orders/view/'.$o['id']) ?>" class="fw-semibold text-body">#<?= htmlspecialchars($o['order_number']) ?></a></td>
            <td style="max-width:12rem;"><span class="d-block text-truncate"><?= htmlspecialchars($o['customer_name'] ?? '-') ?></span></td>
            <td class="fw-medium"><?= $currency . number_format((float) ($o['vendor_total'] ?? $o['total'] ?? 0), 2) ?></td>
            <td><span class="badge <?= $orderBadge($st) ?>"><?= htmlspecialchars(class_exists('Sk_Order_model') ? Sk_Order_model::status_label($st) : ucfirst($st)) ?></span></td>
            <td><span class="badge <?= $orderBadge($pay) ?>"><?= htmlspecialchars(ucfirst($pay)) ?></span></td>
            <td class="text-muted"><?= !empty($o['created_at']) ? date('d M Y', strtotime($o['created_at'])) : '-' ?></td>
            <td class="text-end">
              <a href="<?= site_url('shopkart/orders/view/'.$o['id']) ?>" class="btn btn-sm btn-label-success btn-icon" aria-label="View"><i class="mdi mdi-eye-outline"></i></a>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php if (empty($recentOrders)): ?>
          <tr><td colspan="7" class="text-center text-muted py-4">No orders yet.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php
$pack = $chart_pack ?? [
    'months' => [], 'revenue' => [], 'refunds' => [],
    'week_labels' => [], 'week_orders' => [], 'earnings_percent' => 0,
];
$monthLabels = $pack['months'] ?? [];
$monthRevenue = array_map('floatval', $pack['revenue'] ?? []);
$monthRefunds = array_map('floatval', $pack['refunds'] ?? []);
$sparkLabels = array_slice($monthLabels, -6);
$sparkRevenue = array_slice($monthRevenue, -6);
?>
<script src="<?= base_url('assets/aquiry/libs/apexcharts/apexcharts.min.js') ?>"></script>
<script>
(function () {
  if (typeof ApexCharts === 'undefined') return;
  function cssColor(name) {
    var v = getComputedStyle(document.documentElement).getPropertyValue(name).trim();
    return v || '#3b5bdb';
  }
  var primary = cssColor('--bs-primary');
  var success = cssColor('--bs-success');
  var dangerRgb = cssColor('--bs-danger-rgb') || '220, 53, 69';
  var money = <?= json_encode($currency) ?>;
  function kLabel(v) {
    var n = (Number(v) || 0) / 1000;
    return money + (Math.abs(n) >= 10 ? n.toFixed(0) : n.toFixed(1)) + 'k';
  }
  function kAxis(v) { return kLabel(v); }
  function axisMax(values, headroom) {
    var max = 0;
    values.forEach(function (n) { max = Math.max(max, Number(n) || 0); });
    if (max <= 0) return 1000;
    var top = headroom ? max * 1.25 : max;
    var step = top < 5000 ? 1000 : (top < 20000 ? 2000 : 5000);
    return Math.ceil(top / step) * step;
  }
  var monthRevenue = <?= json_encode(array_values($monthRevenue)) ?>;
  var monthRefunds = <?= json_encode(array_values($monthRefunds)) ?>;
  var sparkRevenue = <?= json_encode(array_values($sparkRevenue)) ?>;
  var monthMax = axisMax(monthRevenue.concat(monthRefunds), true);
  var salesHas = monthRevenue.some(function (n) { return Number(n) > 0; });
  var sparkHas = sparkRevenue.some(function (n) { return Number(n) > 0; });

  var salesEl = document.querySelector('#salesSummaryChart');
  if (salesEl) {
    new ApexCharts(salesEl, {
      series: [{ name: 'Sales', data: monthRevenue }],
      chart: { height: 250, type: 'bar', parentHeightOffset: 0, toolbar: { show: false }, dropShadow: { enabled: true, top: 10, left: 2, blur: 4, color: '#000', opacity: 0.2 } },
      plotOptions: { bar: { borderRadius: 4, columnWidth: '42%', dataLabels: { position: 'top' } } },
      fill: { opacity: 0.8 },
      dataLabels: { enabled: salesHas, formatter: function (e) { return Number(e) ? kLabel(e) : ''; }, offsetY: -14, style: { fontSize: '10px', colors: ['#304758'] } },
      xaxis: { categories: <?= json_encode(array_values($monthLabels)) ?>, position: 'bottom', axisBorder: { show: false }, axisTicks: { show: false }, labels: { rotate: 0, hideOverlappingLabels: true, style: { fontSize: '11px' } }, tooltip: { enabled: true } },
      grid: { strokeDashArray: 4, padding: { top: 18, left: 4, right: 8, bottom: 0 } },
      yaxis: { min: 0, max: monthMax, axisBorder: { show: false }, axisTicks: { show: false }, labels: { show: true, offsetX: -10, formatter: function (e) { return kAxis(e); } } },
      colors: [primary]
    }).render();
  }

  var orderEl = document.querySelector('#orderStatistics');
  if (orderEl) {
    new ApexCharts(orderEl, {
      chart: { type: 'area', height: 88, toolbar: { show: false }, sparkline: { enabled: true }, parentHeightOffset: 0 },
      series: [{ name: 'Revenue', data: sparkRevenue }],
      stroke: { curve: 'smooth', width: 2 },
      fill: { type: 'gradient', gradient: { shadeIntensity: 1, type: 'vertical', gradientToColors: [primary], opacityFrom: 0.35, opacityTo: 0, stops: [0, 100] } },
      colors: [primary],
      dataLabels: { enabled: false },
      tooltip: { y: { formatter: function (e) { return money + e; } } },
      xaxis: { categories: <?= json_encode(array_values($sparkLabels)) ?>, labels: { show: false }, axisBorder: { show: false }, axisTicks: { show: false } },
      yaxis: {
        show: false,
        min: function (min) { return sparkHas ? Math.max(0, min * 0.82) : 0; },
        max: function (max) { return sparkHas ? max * 1.12 : 1; }
      },
      grid: { show: false, padding: { top: 6, right: 0, bottom: 0, left: 0 } }
    }).render();
  }

  var revEl = document.querySelector('#teamProductivityChart');
  if (revEl) {
    new ApexCharts(revEl, {
      chart: { height: 260, toolbar: { show: false }, zoom: { enabled: false }, dropShadow: { enabled: true, top: 10, left: 2, blur: 4, color: '#000', opacity: 0.2 } },
      series: [
        { name: 'Total Revenue', type: 'line', data: monthRevenue },
        { name: 'Total Refunds', type: 'area', data: monthRefunds }
      ],
      stroke: { curve: 'smooth', width: [2, 2] },
      fill: { type: ['solid', 'gradient'], gradient: { shade: 'light', type: 'vertical', shadeIntensity: 0.5, gradientToColors: [primary], inverseColors: true, opacityFrom: 0.2, opacityTo: 0, stops: [0, 100] } },
      colors: [success, primary],
      xaxis: { categories: <?= json_encode(array_values($monthLabels)) ?> },
      yaxis: { min: 0, max: monthMax, labels: { formatter: function (e) { return kAxis(e); } } },
      markers: { size: 0 },
      tooltip: { shared: true, intersect: false, y: { formatter: function (e) { return kAxis(e); } } },
      grid: { borderColor: '#f1f1f1', row: { opacity: 0 }, strokeDashArray: 4, padding: { top: 12, bottom: 0, left: 8, right: 8 } },
      legend: { show: false }
    }).render();
  }

  var earnEl = document.querySelector('#monthlyEarningsChart');
  if (earnEl) {
    new ApexCharts(earnEl, {
      series: [<?= (int) ($pack['earnings_percent'] ?? 0) ?>],
      chart: { height: 220, type: 'radialBar', offsetY: 0 },
      plotOptions: { radialBar: { startAngle: -135, endAngle: 135, dataLabels: { name: { show: false }, value: { offsetY: 10, fontSize: '22px', formatter: function (e) { return e + '%'; } } } } },
      colors: [success],
      fill: { type: 'gradient', gradient: { shade: 'dark', shadeIntensity: 0.1, inverseColors: false, opacityFrom: 1, opacityTo: 1, stops: [0, 50, 65, 91] } },
      grid: { padding: { top: 0, bottom: 0, left: 0, right: 0 } },
      stroke: { dashArray: 4 },
      labels: ['This month share']
    }).render();
  }

  var weekEl = document.querySelector('#weeklyOrdersChart');
  if (weekEl) {
    new ApexCharts(weekEl, {
      series: [{ name: 'Orders', data: <?= json_encode(array_values($pack['week_orders'] ?? [])) ?> }],
      chart: { type: 'bar', height: 97, sparkline: { enabled: true } },
      plotOptions: { bar: { columnWidth: '35%', borderRadius: 5, distributed: true } },
      colors: ['rgba(' + dangerRgb + ',0.3)'],
      fill: { opacity: 0.3 },
      dataLabels: { enabled: false },
      grid: { show: false },
      xaxis: { categories: <?= json_encode(array_values($pack['week_labels'] ?? [])) ?>, labels: { show: false }, axisBorder: { show: false }, axisTicks: { show: false } },
      yaxis: { show: false },
      tooltip: { enabled: true, y: { formatter: function (e) { return e + ' orders'; } } }
    }).render();
  }
})();
</script>
