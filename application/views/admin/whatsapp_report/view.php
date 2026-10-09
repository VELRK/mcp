<?php $log = $log ?? []; ?>

<div class="sk-page-header d-flex justify-content-between align-items-center flex-wrap gap-2">
  <h5 class="sk-page-title mb-0"><i class="bi bi-whatsapp me-2 text-success"></i>WhatsApp Log #<?= (int)$log['id'] ?></h5>
  <div class="d-flex gap-2">
    <?php if (!empty($log['order_id'])): ?>
    <a href="<?= site_url('shopkart/whatsapp-report/resend/'.$log['id'].'?back=view') ?>"
       class="btn btn-sm btn-success"
       onclick="return confirm('Resend WhatsApp for this order status again?');">
      <i class="bi bi-arrow-repeat me-1"></i> Resend
    </a>
    <?php endif; ?>
    <a href="<?= site_url('shopkart/whatsapp-report') ?>" class="btn btn-sm btn-outline-secondary">
      <i class="bi bi-arrow-left me-1"></i> Back to report
    </a>
  </div>
</div>

<?php
  $badge = [
    'sent' => 'bg-primary',
    'delivered' => 'bg-success',
    'read' => 'bg-success',
    'failed' => 'bg-danger',
  ][$log['delivery_status'] ?? ''] ?? 'bg-secondary';
?>

<div class="row g-3">
  <div class="col-lg-6">
    <div class="card shadow-sm">
      <div class="card-header fw-semibold">Delivery summary</div>
      <div class="card-body">
        <table class="table table-sm mb-0">
          <tr><th width="40%">Status</th><td><span class="badge <?= $badge ?>"><?= htmlspecialchars(ucfirst($log['delivery_status'] ?? '')) ?></span></td></tr>
          <tr><th>Time</th><td><?= htmlspecialchars($log['created_at'] ?? '') ?></td></tr>
          <tr><th>Order</th>
            <td>
              <?php if (!empty($log['order_id'])): ?>
                <a href="<?= site_url('shopkart/orders/view/'.$log['order_id']) ?>"><?= htmlspecialchars($log['order_number'] ?: '#'.$log['order_id']) ?></a>
              <?php else: ?>—<?php endif; ?>
            </td>
          </tr>
          <tr><th>Phone</th><td><?= htmlspecialchars($log['phone'] ?: '—') ?> <span class="text-muted small">(<?= htmlspecialchars($log['phone_source'] ?: 'none') ?>)</span></td></tr>
          <tr><th>Status trigger</th><td><?= htmlspecialchars($log['status_trigger'] ?: '—') ?></td></tr>
          <tr><th>Template</th><td><code><?= htmlspecialchars($log['template_name'] ?: '—') ?></code></td></tr>
          <tr><th>Channel</th><td><?= htmlspecialchars($log['channel'] ?: '—') ?></td></tr>
          <tr><th>HTTP</th><td><?= $log['http_code'] !== null && $log['http_code'] !== '' ? (int)$log['http_code'] : '—' ?></td></tr>
          <tr><th>Reason</th><td class="text-break"><?= htmlspecialchars($log['reason'] ?: '—') ?></td></tr>
          <tr><th>API message</th><td class="text-break"><?= htmlspecialchars($log['api_message'] ?: '—') ?></td></tr>
        </table>
      </div>
    </div>
  </div>
  <div class="col-lg-6">
    <div class="card shadow-sm mb-3">
      <div class="card-header fw-semibold">Message body</div>
      <div class="card-body">
        <pre class="mb-0 small" style="white-space:pre-wrap;"><?= htmlspecialchars($log['message_body'] ?: '(none)') ?></pre>
      </div>
    </div>
    <div class="card shadow-sm">
      <div class="card-header fw-semibold">API response</div>
      <div class="card-body">
        <pre class="mb-0 small" style="white-space:pre-wrap;max-height:320px;overflow:auto;"><?php
          $raw = $log['api_response'] ?? '';
          if ($raw) {
              $decoded = json_decode($raw, true);
              echo htmlspecialchars($decoded ? json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) : $raw);
          } else {
              echo '(empty)';
          }
        ?></pre>
      </div>
    </div>
  </div>
</div>
