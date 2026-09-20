<?php
$show_amounts = !empty($show_amounts);
$currency = $currency ?? '₹';
$row = $row ?? [];
?>

<div class="sk-page-header d-flex flex-wrap align-items-center justify-content-between gap-2">
  <div>
    <h5 class="sk-page-title mb-1"><i class="bi bi-cpu me-2 text-warning"></i>AI Request Detail</h5>
    <code><?= htmlspecialchars($row['request_code'] ?? '') ?></code>
  </div>
  <a href="<?= site_url('admin/saas-billing/requests') ?>" class="btn btn-sm btn-outline-secondary">
    <i class="bi bi-arrow-left me-1"></i> Back
  </a>
</div>

<div class="row g-3">
  <div class="col-lg-7">
    <div class="card sk-table-card shadow-sm">
      <div class="card-header bg-white border-0 py-3 fw-semibold">Request</div>
      <div class="card-body p-0">
        <table class="table mb-0">
          <tr><th style="width:40%">Request code</th><td><code><?= htmlspecialchars($row['request_code']) ?></code></td></tr>
          <tr><th>Type</th><td><?= htmlspecialchars($row['request_type']) ?></td></tr>
          <tr><th>Source</th><td><?= htmlspecialchars($row['source']) ?></td></tr>
          <tr><th>Status</th><td><span class="badge bg-secondary"><?= htmlspecialchars($row['status']) ?></span></td></tr>
          <tr><th>Vendor</th><td><?= htmlspecialchars($vendor_name ?? ('#'.$row['vendor_id'])) ?></td></tr>
          <tr><th>Units</th><td><?= number_format((float)$row['units'], 4) ?></td></tr>
          <?php if ($show_amounts): ?>
          <tr><th>Unit amount</th><td><?= htmlspecialchars($currency) . number_format((float)$row['unit_amount'], 4) ?></td></tr>
          <tr><th>Total charge</th><td class="fw-bold"><?= htmlspecialchars($currency) . number_format((float)$row['total_amount'], 4) ?></td></tr>
          <?php endif; ?>
          <tr><th>External ID</th><td><?= htmlspecialchars($row['external_id'] ?? '—') ?></td></tr>
          <tr><th>Created</th><td><?= htmlspecialchars($row['created_at']) ?></td></tr>
          <tr><th>Billed at</th><td><?= htmlspecialchars($row['billed_at'] ?? 'Not yet billed') ?></td></tr>
        </table>
      </div>
    </div>
  </div>
  <div class="col-lg-5">
    <div class="card sk-table-card shadow-sm">
      <div class="card-header bg-white border-0 py-3 fw-semibold">Meta</div>
      <div class="card-body">
        <?php if (empty($meta)): ?>
          <p class="text-muted small mb-0">No meta payload.</p>
        <?php elseif (is_array($meta)): ?>
          <pre class="small bg-light border rounded p-3 mb-0" style="max-height:420px;overflow:auto;"><?= htmlspecialchars(json_encode($meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?></pre>
        <?php else: ?>
          <pre class="small bg-light border rounded p-3 mb-0"><?= htmlspecialchars((string)$meta) ?></pre>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>
