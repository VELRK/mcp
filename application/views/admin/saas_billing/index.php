<?php
$currency = $currency ?? '₹';
$show_amounts = !empty($show_amounts);
$is_master = !empty($is_master);
?>

<div class="sk-page-header d-flex flex-wrap align-items-center justify-content-between gap-2">
  <div>
    <h5 class="sk-page-title mb-1"><i class="bi bi-receipt me-2 text-warning"></i>SaaS Billing</h5>
    <small class="text-muted">Daily AI usage charges · current month rollup</small>
  </div>
  <div class="d-flex flex-wrap gap-2">
    <a href="<?= site_url('admin/saas-billing/requests') ?>" class="btn btn-sm btn-outline-secondary">
      <i class="bi bi-list-ul me-1"></i> AI Requests
    </a>
    <a href="<?= site_url('admin/saas-billing/daily') ?>" class="btn btn-sm btn-outline-secondary">
      <i class="bi bi-calendar3 me-1"></i> Daily Bills
    </a>
    <?php if ($is_master): ?>
    <a href="<?= site_url('admin/saas-billing/amounts') ?>" class="btn btn-sm btn-outline-secondary">
      <i class="bi bi-tag me-1"></i> Client Amounts
    </a>
    <a href="<?= site_url('admin/saas-billing/run_job') ?>" class="btn btn-sm btn-warning"
       onclick="return confirm('Run morning bill job for yesterday (WhatsApp sync + rollup)?');">
      <i class="bi bi-lightning me-1"></i> Run job
    </a>
    <?php endif; ?>
  </div>
</div>

<form method="get" class="card sk-table-card shadow-sm mb-3">
  <div class="card-body py-3">
    <div class="row g-2 align-items-end">
      <div class="col-md-3">
        <label class="form-label small mb-1">Month</label>
        <input type="month" name="month" class="form-control form-control-sm" value="<?= htmlspecialchars($month) ?>">
      </div>
      <?php if ($is_master): ?>
      <div class="col-md-4">
        <label class="form-label small mb-1">Vendor</label>
        <select name="vendor_id" class="form-select form-select-sm">
          <option value="">All vendors</option>
          <?php foreach ($vendors as $v): ?>
          <option value="<?= (int)$v['id'] ?>" <?= (int)($vendor_id ?? 0) === (int)$v['id'] ? 'selected' : '' ?>>
            <?= htmlspecialchars($v['business_name'] ?: ('#'.$v['id'])) ?>
          </option>
          <?php endforeach; ?>
        </select>
      </div>
      <?php endif; ?>
      <div class="col-md-2">
        <button type="submit" class="btn btn-sm btn-dark w-100">Apply</button>
      </div>
    </div>
  </div>
</form>

<div class="row g-3 mb-3">
  <div class="col-md-3">
    <div class="card shadow-sm border-0 h-100"><div class="card-body py-3">
      <div class="text-muted small">Current month charge</div>
      <div class="fs-4 fw-bold">
        <?php if ($show_amounts): ?>
          <?= htmlspecialchars($currency) . number_format((float)$month_total['total_amount'], 2) ?>
        <?php else: ?>
          —
        <?php endif; ?>
      </div>
      <small class="text-muted"><?= (int)$month_total['request_count'] ?> requests · <?= htmlspecialchars($month) ?></small>
    </div></div>
  </div>
  <div class="col-md-3">
    <div class="card shadow-sm border-0 h-100"><div class="card-body py-3">
      <div class="text-muted small">Yesterday bill</div>
      <div class="fs-4 fw-bold">
        <?php if ($show_amounts): ?>
          <?= htmlspecialchars($currency) . number_format((float)$yesterday['total_amount'], 2) ?>
        <?php else: ?>
          —
        <?php endif; ?>
      </div>
      <small class="text-muted"><?= (int)$yesterday['request_count'] ?> requests · <?= htmlspecialchars($yesterday['date']) ?></small>
    </div></div>
  </div>
  <div class="col-md-3">
    <div class="card shadow-sm border-0 h-100"><div class="card-body py-3">
      <div class="text-muted small">Today usage</div>
      <div class="fs-4 fw-bold"><?= (int)$today_count ?></div>
      <small class="text-muted">AI requests logged today</small>
    </div></div>
  </div>
  <div class="col-md-3">
    <div class="card shadow-sm border-0 h-100"><div class="card-body py-3">
      <div class="text-muted small">Month request count</div>
      <div class="fs-4 fw-bold"><?= (int)$month_total['request_count'] ?></div>
      <small class="text-muted">Including live today</small>
    </div></div>
  </div>
</div>

<div class="card sk-table-card shadow-sm">
  <div class="card-header bg-white border-0 py-3 d-flex justify-content-between align-items-center">
    <span class="fw-semibold">Daily bills this month</span>
    <a href="<?= site_url('admin/saas-billing/daily') ?>" class="small">View all</a>
  </div>
  <div class="card-body p-0">
    <table class="table table-hover align-middle mb-0">
      <thead>
        <tr>
          <th>Date</th>
          <?php if ($is_master): ?><th>Vendor</th><?php endif; ?>
          <th>Requests</th>
          <?php if ($show_amounts): ?><th class="text-end">Charge</th><?php endif; ?>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($daily_rows)): ?>
        <tr><td colspan="<?= $is_master ? ($show_amounts ? 5 : 4) : ($show_amounts ? 4 : 3) ?>" class="text-center text-muted py-4">No daily bills yet for this month.</td></tr>
        <?php endif; ?>
        <?php
          $vmap = [];
          foreach ($vendors as $v) { $vmap[(int)$v['id']] = $v['business_name'] ?: ('#'.$v['id']); }
          foreach ($daily_rows as $r):
        ?>
        <tr>
          <td><?= htmlspecialchars($r['bill_date']) ?></td>
          <?php if ($is_master): ?>
          <td><?= htmlspecialchars($vmap[(int)$r['vendor_id']] ?? ('#'.$r['vendor_id'])) ?></td>
          <?php endif; ?>
          <td><?= (int)$r['request_count'] ?></td>
          <?php if ($show_amounts): ?>
          <td class="text-end fw-semibold"><?= htmlspecialchars($currency) . number_format((float)$r['total_amount'], 2) ?></td>
          <?php endif; ?>
          <td class="text-end">
            <a class="btn btn-sm btn-outline-primary"
               href="<?= site_url('admin/saas-billing/requests?' . http_build_query([
                   'date_from' => $r['bill_date'],
                   'date_to'   => $r['bill_date'],
                   'vendor_id' => $r['vendor_id'],
               ])) ?>">Usage</a>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
