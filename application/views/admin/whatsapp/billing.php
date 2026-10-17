<?php
$pages = max(1, (int)ceil(($total ?: 0) / max(1, $limit)));
$qs = http_build_query(array_filter([
    'preset'    => $range['preset'] ?? 'month',
    'from'      => ($range['preset'] ?? '') === 'custom' ? ($range['from'] ?? '') : null,
    'to'        => ($range['preset'] ?? '') === 'custom' ? ($range['to'] ?? '') : null,
    'vendor_id' => !empty($is_super) && !empty($vendor_id) ? $vendor_id : null,
], function ($v) { return $v !== null && $v !== ''; }));
$money = function ($amount) use ($currency) {
    return htmlspecialchars($currency) . ' ' . number_format((float)$amount, 4);
};
?>

<div class="sk-page-header d-flex justify-content-between align-items-center flex-wrap gap-2">
  <div>
    <h5 class="sk-page-title mb-0">WhatsApp billing and usage</h5>
    <small class="text-muted">Meta Business Agent charges, conversations, and tool calls. The tables below are the shop’s own message estimate.</small>
  </div>
  <a class="btn btn-sm btn-outline-secondary" href="<?= site_url('admin/whatsapp/billing/export') . ($qs !== '' ? '?' . $qs : '') ?>">Download CSV</a>
</div>

<?php
$meta = is_array($meta_report ?? null) ? $meta_report : [];
$metaTotals = is_array($meta['usage_totals'] ?? null) ? $meta['usage_totals'] : [];
$metaConv = is_array($meta['conversations'] ?? null) ? $meta['conversations'] : [];
$returnQs = $qs !== '' ? ('?' . $qs) : '';
?>
<div class="card shadow-sm mb-3">
  <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
    <span>Meta Business Agent report</span>
    <?php if (!empty($meta['phone_number_id'])): ?>
      <small class="text-muted">Phone <?= htmlspecialchars((string)$meta['phone_number_id']) ?></small>
    <?php endif; ?>
  </div>
  <div class="card-body">
    <?php if (!empty($meta['error'])): ?>
      <div class="alert alert-warning py-2 mb-3"><?= htmlspecialchars((string)$meta['error']) ?></div>
    <?php endif; ?>
    <?php foreach (($meta['notes'] ?? []) as $note): ?>
      <div class="alert alert-light border py-2 mb-2"><?= htmlspecialchars((string)$note) ?></div>
    <?php endforeach; ?>

    <?php if ((int)($vendor_id ?? 0) > 0): ?>
    <form method="post" action="<?= site_url('admin/whatsapp/billing/account') ?>" class="row g-2 align-items-end mb-3">
      <input type="hidden" name="<?= $this->security->get_csrf_token_name() ?>" value="<?= $this->security->get_csrf_hash() ?>">
      <input type="hidden" name="vendor_id" value="<?= (int)$vendor_id ?>">
      <div class="col-md-5">
        <label class="form-label small mb-1">Meta Business Agent account ID</label>
        <input type="text" name="ba_account_id" class="form-control form-control-sm" inputmode="numeric"
               value="<?= htmlspecialchars((string)($meta['ba_account_id'] ?? '')) ?>"
               placeholder="Auto-detect via API, or paste from Billing Hub">
      </div>
      <div class="col-md-2">
        <button class="btn btn-sm btn-warning w-100" name="auto_detect" value="0">Save</button>
      </div>
      <div class="col-md-3">
        <button class="btn btn-sm btn-outline-primary w-100" name="auto_detect" value="1"
                formaction="<?= site_url('admin/whatsapp/billing/sync-account') ?>">Auto-detect &amp; save</button>
      </div>
      <?php if (!empty($meta['ba_account_source'])): ?>
      <div class="col-12"><div class="text-muted small">Source: <?= htmlspecialchars((string)$meta['ba_account_source']) ?></div></div>
      <?php endif; ?>
    </form>
    <?php endif; ?>

    <div class="row g-3 mb-3">
      <?php
      $metaCards = [
          'Billable messages' => (int)($metaTotals['billable_messages'] ?? 0),
          'Billable tokens' => (int)($metaTotals['billable_tokens'] ?? 0),
          'AI conversations' => $metaConv['ai_threads'],
          'Waiting on staff' => $metaConv['ai_handoffs'],
      ];
      foreach ($metaCards as $label => $value): ?>
      <div class="col-md-3">
        <div class="border rounded p-3 h-100">
          <div class="text-muted small"><?= htmlspecialchars($label) ?></div>
          <div class="fs-4 fw-bold"><?= $value === null ? '—' : number_format((int)$value) ?></div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <div class="mb-3">
      <div class="text-muted small">Meta charges</div>
      <div class="fs-5 fw-bold"><?= number_format((float)($metaTotals['cost'] ?? 0), 4) ?></div>
      <div class="text-muted small">Charges are in the Business Agent account currency. Waiting on staff is the current handoff count, not a total for the dates above.</div>
    </div>

    <div class="table-responsive mb-3">
      <table class="table table-sm mb-0">
        <thead><tr><th>Bucket start</th><th>Channel</th><th>Sender</th><th>Messages</th><th>Tokens</th><th>Charge</th></tr></thead>
        <tbody>
        <?php if (empty($meta['usage'])): ?>
          <tr><td colspan="6" class="text-muted">No Meta usage rows for this period.</td></tr>
        <?php else: foreach ($meta['usage'] as $row): ?>
          <tr>
            <td><?= !empty($row['start']) ? htmlspecialchars(date('Y-m-d H:i', (int)$row['start'])) : '—' ?></td>
            <td><?= htmlspecialchars((string)($row['channel'] ?? '—')) ?></td>
            <td><?= htmlspecialchars((string)($row['sender_id'] ?? '—')) ?></td>
            <td><?= (int)($row['billable_messages'] ?? 0) ?></td>
            <td><?= (int)($row['billable_tokens'] ?? 0) ?></td>
            <td><?= number_format((float)($row['cost'] ?? 0), 4) ?></td>
          </tr>
        <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>

    <div class="table-responsive">
      <table class="table table-sm mb-0">
        <thead><tr><th>Tool</th><th>Conversations</th><th>Avg response ms</th><th>Success</th><th>Errors</th><th>Timeouts</th></tr></thead>
        <tbody>
        <?php if (empty($meta['tools'])): ?>
          <tr><td colspan="6" class="text-muted">No tool calls in this period.</td></tr>
        <?php else: foreach ($meta['tools'] as $tool): ?>
          <tr>
            <td><?= htmlspecialchars((string)($tool['tool_name'] ?? '')) ?></td>
            <td><?= (int)($tool['thread_count'] ?? 0) ?></td>
            <td><?= isset($tool['avg_latency_ms']) && $tool['avg_latency_ms'] !== null ? number_format((float)$tool['avg_latency_ms'], 0) : '—' ?></td>
            <td><?= isset($tool['success_rate']) && $tool['success_rate'] !== null ? number_format((float)$tool['success_rate'] * 100, 1) . '%' : '—' ?></td>
            <td><?= isset($tool['error_rate']) && $tool['error_rate'] !== null ? number_format((float)$tool['error_rate'] * 100, 1) . '%' : '—' ?></td>
            <td><?= isset($tool['timeout_rate']) && $tool['timeout_rate'] !== null ? number_format((float)$tool['timeout_rate'] * 100, 1) . '%' : '—' ?></td>
          </tr>
        <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<div class="alert alert-warning">
  The estimate below uses the WhatsApp AI rate in SaaS Billing (<?= $money($unit_amount) ?> per outbound message that did not fail).
  It is the shop’s own count. Meta’s charges are in the report above.
</div>

<div class="card shadow-sm mb-3">
  <div class="card-body py-2">
    <form method="get" class="row g-2 align-items-end">
      <div class="col-md-3">
        <label class="form-label small mb-1">Period</label>
        <select name="preset" class="form-select form-select-sm">
          <?php foreach (['today' => 'Today', 'yesterday' => 'Yesterday', '7d' => 'Last 7 days', '30d' => 'Last 30 days', 'month' => 'This month', 'prev_month' => 'Previous month', 'custom' => 'Custom'] as $key => $label): ?>
          <option value="<?= $key ?>" <?= ($range['preset'] ?? '') === $key ? 'selected' : '' ?>><?= $label ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-2">
        <label class="form-label small mb-1">From</label>
        <input type="date" name="from" class="form-control form-control-sm" value="<?= htmlspecialchars($range['from'] ?? '') ?>">
      </div>
      <div class="col-md-2">
        <label class="form-label small mb-1">To</label>
        <input type="date" name="to" class="form-control form-control-sm" value="<?= htmlspecialchars($range['to'] ?? '') ?>">
      </div>
      <?php if (!empty($is_super)): ?>
      <div class="col-md-3">
        <label class="form-label small mb-1">Vendor</label>
        <select name="vendor_id" class="form-select form-select-sm">
          <option value="0">All vendors</option>
          <?php foreach ($vendors as $vendor): ?>
          <option value="<?= (int)$vendor['id'] ?>" <?= (int)$vendor_id === (int)$vendor['id'] ? 'selected' : '' ?>><?= htmlspecialchars($vendor['business_name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <?php endif; ?>
      <div class="col-md-2">
        <button class="btn btn-sm btn-warning w-100">Apply</button>
      </div>
    </form>
  </div>
</div>

<div class="row g-3 mb-3">
  <?php
  $cards = [
      'Messages stored' => (int)$summary['submitted'],
      'Outbound' => (int)$summary['outbound'],
      'Delivered' => (int)$summary['delivered'],
      'Read' => (int)$summary['read'],
      'Failed' => (int)$summary['failed'],
  ];
  foreach ($cards as $label => $value): ?>
  <div class="col-md-2">
    <div class="card shadow-sm border-0"><div class="card-body py-3">
      <div class="text-muted small"><?= htmlspecialchars($label) ?></div>
      <div class="fs-4 fw-bold"><?= (int)$value ?></div>
    </div></div>
  </div>
  <?php endforeach; ?>
  <div class="col-md-2">
    <div class="card shadow-sm border-0"><div class="card-body py-3">
      <div class="text-muted small">Estimated service cost</div>
      <div class="fs-5 fw-bold"><?= $money($summary['estimated']) ?></div>
    </div></div>
  </div>
</div>

<div class="row g-3 mb-3">
  <div class="col-lg-6">
    <div class="card shadow-sm h-100">
      <div class="card-header">Monthly usage</div>
      <div class="card-body p-0">
        <table class="table table-sm mb-0">
          <thead><tr><th>Month</th><th>Outbound</th><th>Failed</th><th>Estimate</th><th>Meta invoice</th><th>Ad spend</th></tr></thead>
          <tbody>
          <?php if (!$monthly): ?>
            <tr><td colspan="6" class="text-muted p-3">No outbound messages in this period.</td></tr>
          <?php else: foreach ($monthly as $row): ?>
            <tr>
              <td><?= htmlspecialchars($row['month']) ?></td>
              <td><?= (int)$row['outbound'] ?></td>
              <td><?= (int)$row['failed'] ?></td>
              <td><?= $money($row['estimated']) ?></td>
              <td>Unavailable</td>
              <td>Not connected</td>
            </tr>
          <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
  <div class="col-lg-6">
    <div class="card shadow-sm h-100">
      <div class="card-header">Templates</div>
      <div class="card-body p-0">
        <table class="table table-sm mb-0">
          <thead><tr><th>Template</th><th>Submitted</th><th>Failed</th><th>Estimate</th></tr></thead>
          <tbody>
          <?php if (!$templates): ?>
            <tr><td colspan="4" class="text-muted p-3">No template messages in this period.</td></tr>
          <?php else: foreach ($templates as $row): ?>
            <tr>
              <td><?= htmlspecialchars($row['name']) ?></td>
              <td><?= (int)$row['submitted'] ?></td>
              <td><?= (int)$row['failed'] ?></td>
              <td><?= $money($row['estimated']) ?></td>
            </tr>
          <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<div class="card shadow-sm mb-3">
  <div class="card-header">WhatsApp campaigns</div>
  <div class="card-body p-0">
    <table class="table table-sm mb-0">
      <thead><tr><th>Campaign</th><th>Template</th><th>Sent</th><th>Delivered</th><th>Read</th><th>Failed</th><th>Message estimate</th><th>Ad spend</th><th>Orders</th></tr></thead>
      <tbody>
      <?php if (!$campaigns): ?>
        <tr><td colspan="9" class="text-muted p-3">No campaigns in this period.</td></tr>
      <?php else: foreach ($campaigns as $row): ?>
        <tr>
          <td><?= htmlspecialchars($row['name']) ?></td>
          <td><?= htmlspecialchars((string)($row['template_name'] ?? '')) ?></td>
          <td><?= (int)$row['sent'] ?></td>
          <td><?= (int)$row['delivered'] ?></td>
          <td><?= (int)$row['read_count'] ?></td>
          <td><?= (int)$row['failed'] ?></td>
          <td><?= $money($row['estimated']) ?></td>
          <td>Not connected</td>
          <td>No attribution</td>
        </tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>

<div class="card shadow-sm">
  <div class="card-header">Messages</div>
  <div class="card-body p-0">
    <table class="table table-sm mb-0">
      <thead><tr><th>Time</th><th>Vendor</th><th>Phone</th><th>Direction</th><th>Template</th><th>Status</th><th>Billing</th></tr></thead>
      <tbody>
      <?php if (!$messages): ?>
        <tr><td colspan="7" class="text-muted p-3">No messages in this period.</td></tr>
      <?php else: foreach ($messages as $row): ?>
        <tr>
          <td><?= htmlspecialchars((string)$row['created_at']) ?></td>
          <td><?= (int)$row['vendor_id'] ?></td>
          <td><?= htmlspecialchars((string)$row['phone_masked']) ?></td>
          <td><?= htmlspecialchars((string)$row['direction']) ?></td>
          <td><?= htmlspecialchars((string)($row['template_name'] ?? '')) ?></td>
          <td><?= htmlspecialchars((string)$row['status']) ?></td>
          <td><?= $row['billable_label'] === 'estimated' ? 'Estimate' : 'Not billed here' ?></td>
        </tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
  <?php if ($pages > 1): ?>
  <div class="card-footer d-flex gap-2">
    <?php for ($i = 1; $i <= $pages && $i <= 20; $i++): ?>
      <a class="btn btn-sm <?= $i === (int)$page ? 'btn-warning' : 'btn-outline-secondary' ?>" href="<?= site_url('admin/whatsapp/billing') . '?' . $qs . '&page=' . $i ?>"><?= $i ?></a>
    <?php endfor; ?>
  </div>
  <?php endif; ?>
</div>
