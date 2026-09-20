<?php
$show_amounts = !empty($show_amounts);
$is_master = !empty($is_master);
$currency = $currency ?? '₹';
$filters = $filters ?? [];
$qs = http_build_query(array_filter([
    'vendor_id'    => $filters['vendor_id'] ?? null,
    'request_type' => $filters['request_type'] ?? null,
    'source'       => $filters['source'] ?? null,
    'search'       => $filters['search'] ?? null,
    'date_from'    => $filters['date_from'] ?? null,
    'date_to'      => $filters['date_to'] ?? null,
], static function ($v) { return $v !== null && $v !== '' && $v !== 0; }));
$pages = max(1, (int)ceil(($total ?: 0) / max(1, $limit)));
?>

<div class="sk-page-header d-flex flex-wrap align-items-center justify-content-between gap-2">
  <div>
    <h5 class="sk-page-title mb-1"><i class="bi bi-cpu me-2 text-warning"></i>AI Requests</h5>
    <small class="text-muted">Usage detail by request code · vendors always have access</small>
  </div>
  <a href="<?= site_url('admin/saas-billing') ?>" class="btn btn-sm btn-outline-secondary">
    <i class="bi bi-arrow-left me-1"></i> Billing
  </a>
</div>

<form method="get" class="card sk-table-card shadow-sm mb-3">
  <div class="card-body py-3">
    <div class="row g-2 align-items-end">
      <div class="col-md-2">
        <label class="form-label small mb-1">Search code</label>
        <input type="text" name="search" class="form-control form-control-sm" value="<?= htmlspecialchars($filters['search'] ?? '') ?>" placeholder="AI-…">
      </div>
      <?php if ($is_master): ?>
      <div class="col-md-2">
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
        <label class="form-label small mb-1">Type</label>
        <select name="request_type" class="form-select form-select-sm">
          <option value="">All types</option>
          <?php foreach (['ai_chat','whatsapp_ai','custom'] as $t): ?>
          <option value="<?= $t ?>" <?= ($filters['request_type'] ?? '') === $t ? 'selected' : '' ?>><?= $t ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-1">
        <label class="form-label small mb-1">Source</label>
        <select name="source" class="form-select form-select-sm">
          <option value="">All</option>
          <option value="api" <?= ($filters['source'] ?? '') === 'api' ? 'selected' : '' ?>>api</option>
          <option value="whatsapp" <?= ($filters['source'] ?? '') === 'whatsapp' ? 'selected' : '' ?>>whatsapp</option>
        </select>
      </div>
      <div class="col-md-2">
        <label class="form-label small mb-1">From</label>
        <input type="date" name="date_from" class="form-control form-control-sm" value="<?= htmlspecialchars($filters['date_from'] ?? '') ?>">
      </div>
      <div class="col-md-2">
        <label class="form-label small mb-1">To</label>
        <input type="date" name="date_to" class="form-control form-control-sm" value="<?= htmlspecialchars($filters['date_to'] ?? '') ?>">
      </div>
      <div class="col-md-1">
        <button class="btn btn-sm btn-dark w-100" type="submit">Go</button>
      </div>
    </div>
  </div>
</form>

<div class="card sk-table-card shadow-sm">
  <div class="card-body p-0">
    <table class="table table-hover align-middle mb-0">
      <thead>
        <tr>
          <th>Request code</th>
          <th>Type</th>
          <th>Source</th>
          <?php if ($is_master): ?><th>Vendor</th><?php endif; ?>
          <th>Units</th>
          <?php if ($show_amounts): ?><th class="text-end">Amount</th><?php endif; ?>
          <th>When</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($rows)): ?>
        <tr><td colspan="8" class="text-center text-muted py-4">No AI requests found.</td></tr>
        <?php endif; ?>
        <?php foreach ($rows as $r): ?>
        <tr>
          <td><code class="small"><?= htmlspecialchars($r['request_code']) ?></code></td>
          <td><span class="badge bg-light text-dark"><?= htmlspecialchars($r['request_type']) ?></span></td>
          <td><?= htmlspecialchars($r['source']) ?></td>
          <?php if ($is_master): ?>
          <td><?= htmlspecialchars($vendor_names[(int)$r['vendor_id']] ?? ('#'.$r['vendor_id'])) ?></td>
          <?php endif; ?>
          <td><?= number_format((float)$r['units'], 2) ?></td>
          <?php if ($show_amounts): ?>
          <td class="text-end"><?= htmlspecialchars($currency) . number_format((float)$r['total_amount'], 4) ?></td>
          <?php endif; ?>
          <td class="small text-muted"><?= htmlspecialchars($r['created_at']) ?></td>
          <td class="text-end">
            <a class="btn btn-sm btn-outline-primary" href="<?= site_url('admin/saas-billing/requests/view/' . rawurlencode($r['request_code'])) ?>">
              <i class="bi bi-eye"></i>
            </a>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <div class="card-footer bg-white d-flex justify-content-between align-items-center flex-wrap gap-2">
    <small class="text-muted"><?= (int)$total ?> requests</small>
    <?php if ($pages > 1): ?>
    <ul class="pagination pagination-sm mb-0">
      <?php
        $start = max(1, $page - 3);
        $end = min($pages, $page + 3);
        for ($i = $start; $i <= $end; $i++):
      ?>
      <li class="page-item <?= $i === $page ? 'active' : '' ?>">
        <a class="page-link" href="?page=<?= $i ?>&<?= $qs ?>"><?= $i ?></a>
      </li>
      <?php endfor; ?>
    </ul>
    <?php endif; ?>
  </div>
</div>
