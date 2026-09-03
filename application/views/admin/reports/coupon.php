<?php $currency = sk_currency_symbol($settings); ?>

<div class="sk-page-header">
  <h5 class="sk-page-title"><i class="bi bi-ticket-perforated me-2 text-warning"></i>Coupon Usage Report</h5>
</div>

<!-- Search -->
<form method="GET" class="d-flex gap-2 mb-3">
  <input type="text" name="search" class="form-control form-control-sm" style="max-width:200px;"
         placeholder="Search code…" value="<?= htmlspecialchars($search ?? '') ?>">
  <button class="btn btn-sm btn-outline-warning px-3">Search</button>
  <?php if ($search): ?>
    <a href="<?= site_url('admin/coupon-report') ?>" class="btn btn-sm btn-outline-secondary">Clear</a>
  <?php endif; ?>
</form>

<!-- Summary cards -->
<div class="row g-3 mb-4">
  <div class="col-md-3">
    <div class="card sk-table-card shadow-sm text-center py-3">
      <div class="text-muted small">Total Coupons</div>
      <div class="fw-bold fs-4"><?= count($summaries) ?></div>
    </div>
  </div>
  <div class="col-md-3">
    <div class="card sk-table-card shadow-sm text-center py-3">
      <div class="text-muted small">Total Uses</div>
      <div class="fw-bold fs-4"><?= array_sum(array_column($summaries,'times_used')) ?></div>
    </div>
  </div>
  <div class="col-md-3">
    <div class="card sk-table-card shadow-sm text-center py-3">
      <div class="text-muted small">Total Discount Given</div>
      <div class="fw-bold fs-4 text-success"><?= $currency . number_format($total_discount, 2) ?></div>
    </div>
  </div>
</div>

<!-- Per-coupon summary table -->
<div class="card sk-table-card shadow-sm mb-4">
  <div class="card-header bg-white border-0 py-3 fw-semibold">
    <i class="bi bi-ticket me-1 text-warning"></i> Coupon Summary
  </div>
  <div class="card-body p-0">
    <table class="table table-hover align-middle mb-0">
      <thead class="sk-table-head">
        <tr>
          <th>Code</th><th>Type</th><th>Value</th><th>Times Used</th>
          <th>Usage Limit</th><th>Total Discount</th><th>Last Used</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($summaries)): ?>
        <tr><td colspan="7" class="text-center text-muted py-4">No coupon usage found.</td></tr>
        <?php else: ?>
        <?php foreach ($summaries as $s): ?>
        <tr>
          <td><span class="badge bg-warning text-dark fs-6"><?= htmlspecialchars($s['code']) ?></span></td>
          <td><?= $s['type'] === 'percent' ? 'Percent (%)' : 'Fixed ('.htmlspecialchars($currency).')' ?></td>
          <td><?= $s['type'] === 'percent' ? $s['value'].'%' : $currency.number_format($s['value'],2) ?></td>
          <td><span class="fw-semibold"><?= (int)$s['times_used'] ?></span></td>
          <td><?= $s['usage_limit'] ? $s['usage_limit'] : '∞' ?></td>
          <td class="text-success fw-semibold"><?= $currency . number_format($s['total_discount_given'] ?? 0, 2) ?></td>
          <td class="text-muted small"><?= $s['last_used'] ? date('d M Y, H:i', strtotime($s['last_used'])) : '—' ?></td>
        </tr>
        <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Detailed usage log -->
<div class="card sk-table-card shadow-sm">
  <div class="card-header bg-white border-0 py-3 fw-semibold">
    <i class="bi bi-list-check me-1 text-warning"></i> Usage Log <small class="text-muted fw-normal">(last 100)</small>
  </div>
  <div class="card-body p-0">
    <table class="table table-hover align-middle mb-0">
      <thead class="sk-table-head">
        <tr><th>Date</th><th>Code</th><th>Customer</th><th>Order #</th><th>Discount</th><th>Order Total</th></tr>
      </thead>
      <tbody>
        <?php if (empty($usage_log)): ?>
        <tr><td colspan="6" class="text-center text-muted py-4">No usage records yet.</td></tr>
        <?php else: ?>
        <?php foreach ($usage_log as $u): ?>
        <tr>
          <td class="text-muted small"><?= $u['used_at'] ? date('d M Y, H:i', strtotime($u['used_at'])) : '—' ?></td>
          <td><span class="badge bg-warning text-dark"><?= htmlspecialchars($u['code'] ?? '—') ?></span></td>
          <td>
            <div><?= htmlspecialchars($u['user_name'] ?? '—') ?></div>
            <small class="text-muted"><?= htmlspecialchars($u['user_email'] ?? '') ?></small>
          </td>
          <td>
            <?php if ($u['order_number']): ?>
              <a href="<?= site_url('admin/orders/view/'.($u['order_number'])) ?>" class="text-primary text-decoration-none fw-semibold">
                <?= htmlspecialchars($u['order_number']) ?>
              </a>
            <?php else: ?> — <?php endif; ?>
          </td>
          <td class="text-success fw-semibold">-<?= $currency . number_format($u['discount'] ?? 0, 2) ?></td>
          <td><?= $currency . number_format($u['total'] ?? 0, 2) ?></td>
        </tr>
        <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
