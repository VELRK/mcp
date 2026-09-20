<?php
$show_amounts = !empty($show_amounts);
$is_master = !empty($is_master);
$currency = $currency ?? '₹';
$filters = $filters ?? [];
$qs = http_build_query(array_filter([
    'vendor_id' => $filters['vendor_id'] ?? null,
    'date_from' => $filters['date_from'] ?? null,
    'date_to'   => $filters['date_to'] ?? null,
], static function ($v) { return $v !== null && $v !== '' && $v !== 0; }));
$pages = max(1, (int)ceil(($total ?: 0) / max(1, $limit)));
?>

<div class="sk-page-header d-flex flex-wrap align-items-center justify-content-between gap-2">
  <div>
    <h5 class="sk-page-title mb-1"><i class="bi bi-calendar3 me-2 text-warning"></i>Daily Bills</h5>
    <small class="text-muted">One bill per vendor per day · updated each morning</small>
  </div>
  <a href="<?= site_url('admin/saas-billing') ?>" class="btn btn-sm btn-outline-secondary">
    <i class="bi bi-arrow-left me-1"></i> Overview
  </a>
</div>

<form method="get" class="card sk-table-card shadow-sm mb-3">
  <div class="card-body py-3">
    <div class="row g-2 align-items-end">
      <?php if ($is_master): ?>
      <div class="col-md-3">
        <label class="form-label small mb-1">Vendor</label>
        <select name="vendor_id" class="form-select form-select-sm">
          <option value="">All</option>
          <?php foreach ($vendors as $v): ?>
          <option value="<?= (int)$v['id'] ?>" <?= (int)($filters['vendor_id'] ?? 0) === (int)$v['id'] ? 'selected' : '' ?>>
            <?= htmlspecialchars($v['business_name'] ?: ('#'.$v['id'])) ?>
          </option>
          <?php endforeach; ?>
        </select>
      </div>
      <?php endif; ?>
      <div class="col-md-2">
        <label class="form-label small mb-1">From</label>
        <input type="date" name="date_from" class="form-control form-control-sm" value="<?= htmlspecialchars($filters['date_from'] ?? '') ?>">
      </div>
      <div class="col-md-2">
        <label class="form-label small mb-1">To</label>
        <input type="date" name="date_to" class="form-control form-control-sm" value="<?= htmlspecialchars($filters['date_to'] ?? '') ?>">
      </div>
      <div class="col-md-2">
        <button class="btn btn-sm btn-dark w-100" type="submit">Filter</button>
      </div>
    </div>
  </div>
</form>

<div class="card sk-table-card shadow-sm">
  <div class="card-body p-0">
    <table class="table table-hover align-middle mb-0">
      <thead>
        <tr>
          <th>Date</th>
          <?php if ($is_master): ?><th>Vendor</th><?php endif; ?>
          <th>Requests</th>
          <?php if ($show_amounts): ?><th class="text-end">Charge</th><?php endif; ?>
          <th>Status</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($rows)): ?>
        <tr><td colspan="6" class="text-center text-muted py-4">No bills found.</td></tr>
        <?php endif; ?>
        <?php foreach ($rows as $r): ?>
        <tr>
          <td class="fw-semibold"><?= htmlspecialchars($r['bill_date']) ?></td>
          <?php if ($is_master): ?>
          <td><?= htmlspecialchars($vendor_names[(int)$r['vendor_id']] ?? ('#'.$r['vendor_id'])) ?></td>
          <?php endif; ?>
          <td><?= (int)$r['request_count'] ?></td>
          <?php if ($show_amounts): ?>
          <td class="text-end"><?= htmlspecialchars($currency) . number_format((float)$r['total_amount'], 2) ?></td>
          <?php endif; ?>
          <td><span class="badge bg-secondary"><?= htmlspecialchars($r['status']) ?></span></td>
          <td class="text-end">
            <a class="btn btn-sm btn-outline-primary"
               href="<?= site_url('admin/saas-billing/requests?' . http_build_query([
                   'date_from' => $r['bill_date'],
                   'date_to' => $r['bill_date'],
                   'vendor_id' => $r['vendor_id'],
               ])) ?>">View usage</a>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php if ($pages > 1): ?>
  <div class="card-footer bg-white d-flex justify-content-between align-items-center">
    <small class="text-muted"><?= (int)$total ?> bills</small>
    <ul class="pagination pagination-sm mb-0">
      <?php for ($i = 1; $i <= min($pages, 20); $i++): ?>
      <li class="page-item <?= $i === $page ? 'active' : '' ?>">
        <a class="page-link" href="?page=<?= $i ?>&<?= $qs ?>"><?= $i ?></a>
      </li>
      <?php endfor; ?>
    </ul>
  </div>
  <?php endif; ?>
</div>
