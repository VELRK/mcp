<?php
$pages = max(1, (int)ceil(($total ?: 0) / max(1, $limit)));
$qs = http_build_query(array_filter([
    'status' => $status ?: null,
    'search' => $search ?: null,
    'from'   => $from ?: null,
    'to'     => $to ?: null,
], fn($v) => $v !== null && $v !== ''));
?>

<div class="sk-page-header d-flex justify-content-between align-items-center flex-wrap gap-2">
  <div>
    <h5 class="sk-page-title mb-0"><i class="bi bi-whatsapp me-2 text-success"></i>WhatsApp Delivery Report</h5>
    <small class="text-muted">Meta template deliveries for order created, updated, cancelled, and delivered</small>
  </div>
</div>

<div class="row g-3 mb-3">
  <div class="col-md-3">
    <div class="card shadow-sm border-0"><div class="card-body py-3">
      <div class="text-muted small">Total attempts</div>
      <div class="fs-4 fw-bold"><?= (int)$summary['total'] ?></div>
    </div></div>
  </div>
  <div class="col-md-2">
    <div class="card shadow-sm border-0"><div class="card-body py-3">
      <div class="text-muted small">Sent</div>
      <div class="fs-4 fw-bold text-primary"><?= (int)($summary['sent'] ?? 0) ?></div>
    </div></div>
  </div>
  <div class="col-md-2">
    <div class="card shadow-sm border-0"><div class="card-body py-3">
      <div class="text-muted small">Delivered</div>
      <div class="fs-4 fw-bold text-success"><?= (int)($summary['delivered'] ?? 0) ?></div>
    </div></div>
  </div>
  <div class="col-md-2">
    <div class="card shadow-sm border-0"><div class="card-body py-3">
      <div class="text-muted small">Read</div>
      <div class="fs-4 fw-bold text-success"><?= (int)($summary['read'] ?? 0) ?></div>
    </div></div>
  </div>
  <div class="col-md-3">
    <div class="card shadow-sm border-0"><div class="card-body py-3">
      <div class="text-muted small">Failed</div>
      <div class="fs-4 fw-bold text-danger"><?= (int)($summary['failed'] ?? 0) ?></div>
    </div></div>
  </div>
</div>

<div class="card sk-table-card shadow-sm mb-3">
  <div class="card-body py-2">
    <form method="get" class="row g-2 align-items-end">
      <div class="col-md-2">
        <label class="form-label small mb-1">Status</label>
        <select name="status" class="form-select form-select-sm">
          <option value="">All</option>
          <option value="sent" <?= $status==='sent'?'selected':'' ?>>Sent</option>
          <option value="delivered" <?= $status==='delivered'?'selected':'' ?>>Delivered</option>
          <option value="read" <?= $status==='read'?'selected':'' ?>>Read</option>
          <option value="failed" <?= $status==='failed'?'selected':'' ?>>Failed</option>
        </select>
      </div>
      <div class="col-md-2">
        <label class="form-label small mb-1">From</label>
        <input type="date" name="from" class="form-control form-control-sm" value="<?= htmlspecialchars($from) ?>">
      </div>
      <div class="col-md-2">
        <label class="form-label small mb-1">To</label>
        <input type="date" name="to" class="form-control form-control-sm" value="<?= htmlspecialchars($to) ?>">
      </div>
      <div class="col-md-4">
        <label class="form-label small mb-1">Search</label>
        <input type="text" name="search" class="form-control form-control-sm" placeholder="Order no / phone / reason"
               value="<?= htmlspecialchars($search) ?>">
      </div>
      <div class="col-md-2 d-flex gap-1">
        <button class="btn btn-sm btn-warning flex-grow-1">Filter</button>
        <a href="<?= site_url('shopkart/whatsapp-report') ?>" class="btn btn-sm btn-outline-secondary">Reset</a>
      </div>
    </form>
  </div>
</div>

<div class="card sk-table-card shadow-sm">
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead>
          <tr>
            <th>Time</th>
            <th>Order</th>
            <th>Phone</th>
            <th>Trigger</th>
            <th>Template</th>
            <th>Status</th>
            <th>Reason / detail</th>
            <th class="text-end">Actions</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($logs as $log): ?>
          <?php
            $badge = [
              'sent' => 'bg-primary',
              'delivered' => 'bg-success',
              'read' => 'bg-success',
              'failed' => 'bg-danger',
            ][$log['delivery_status']] ?? 'bg-secondary';
            $canResend = !empty($log['order_id']);
          ?>
          <tr>
            <td class="small text-nowrap"><?= sk_format_datetime($log['created_at'], 'd M Y, h:i A') ?></td>
            <td>
              <?php if (!empty($log['order_id'])): ?>
                <a href="<?= site_url('shopkart/orders/view/'.$log['order_id']) ?>" class="fw-semibold text-decoration-none">
                  <?= htmlspecialchars($log['order_number'] ?: ('#'.$log['order_id'])) ?>
                </a>
              <?php else: ?>
                <?= htmlspecialchars($log['order_number'] ?: '—') ?>
              <?php endif; ?>
            </td>
            <td class="small">
              <?= htmlspecialchars($log['phone'] ?: '—') ?>
              <?php if (!empty($log['phone_source']) && $log['phone_source'] !== 'none'): ?>
                <div class="text-muted" style="font-size:11px;"><?= htmlspecialchars($log['phone_source']) ?></div>
              <?php endif; ?>
            </td>
            <td><span class="badge bg-light text-dark border"><?= htmlspecialchars($log['status_trigger'] ?: '—') ?></span></td>
            <td class="small"><code><?= htmlspecialchars($log['template_name'] ?: ($log['channel'] ?: '—')) ?></code></td>
            <td><span class="badge <?= $badge ?>"><?= htmlspecialchars(ucfirst($log['delivery_status'])) ?></span></td>
            <td class="small" style="max-width:320px;">
              <div class="text-truncate" title="<?= htmlspecialchars($log['reason'] ?: $log['api_message'] ?: '') ?>">
                <?= htmlspecialchars($log['reason'] ?: $log['api_message'] ?: '—') ?>
              </div>
              <?php if (!empty($log['http_code'])): ?>
                <div class="text-muted" style="font-size:11px;">HTTP <?= (int)$log['http_code'] ?></div>
              <?php endif; ?>
            </td>
            <td class="text-end text-nowrap">
              <?php if ($canResend): ?>
              <a href="<?= site_url('shopkart/whatsapp-report/resend/'.$log['id']) ?>"
                 class="btn btn-sm btn-outline-success"
                 title="Resend WhatsApp for this order status"
                 onclick="return confirm('Resend WhatsApp for <?= htmlspecialchars(addslashes($log['order_number'] ?: ('#'.$log['order_id']))) ?> (<?= htmlspecialchars(addslashes($log['status_trigger'] ?: 'status')) ?>)?');">
                <i class="bi bi-arrow-repeat"></i> Resend
              </a>
              <?php endif; ?>
              <a href="<?= site_url('shopkart/whatsapp-report/view/'.$log['id']) ?>" class="btn btn-sm btn-outline-primary" title="Details">
                <i class="bi bi-eye"></i>
              </a>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (empty($logs)): ?>
          <tr><td colspan="8" class="text-center text-muted py-5">No Meta template deliveries yet. Order status messages are sent with the shop's WhatsApp template.</td></tr>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
  <?php if ($pages > 1): ?>
  <div class="card-footer bg-white d-flex justify-content-between align-items-center">
    <small class="text-muted"><?= (int)$total ?> records</small>
    <nav><ul class="pagination pagination-sm mb-0">
      <?php for ($i = 1; $i <= $pages; $i++): ?>
        <li class="page-item <?= $i === $page ? 'active' : '' ?>">
          <a class="page-link" href="?page=<?= $i ?>&<?= $qs ?>"><?= $i ?></a>
        </li>
      <?php endfor; ?>
    </ul></nav>
  </div>
  <?php endif; ?>
</div>
