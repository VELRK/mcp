<?php
$meta = is_array($meta_report ?? null) ? $meta_report : [];
$totals = is_array($meta['usage_totals'] ?? null) ? $meta['usage_totals'] : [];
$tokens = (int)($totals['billable_tokens'] ?? 0);
$messages = (int)($totals['billable_messages'] ?? 0);
$metaCost = (float)($totals['cost'] ?? 0);
$rate = (float)($token_rate ?? 2);
$estimate = (float)($token_estimate ?? 0);
$typical = $tokens > 0 ? (int)round($tokens / 22500) : 0;
$qs = http_build_query(array_filter([
    'preset'    => $range['preset'] ?? 'month',
    'from'      => ($range['preset'] ?? '') === 'custom' ? ($range['from'] ?? '') : null,
    'to'        => ($range['preset'] ?? '') === 'custom' ? ($range['to'] ?? '') : null,
    'vendor_id' => !empty($is_super) && !empty($vendor_id) ? $vendor_id : null,
], function ($v) { return $v !== null && $v !== ''; }));
?>

<div class="sk-page-header d-flex justify-content-between align-items-center flex-wrap gap-2">
  <div>
    <h5 class="sk-page-title mb-0">Token usage</h5>
    <small class="text-muted">Meta Business Agent replies are charged per token. The rate is $2.00 USD per 1,000,000 tokens.</small>
  </div>
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
          <option value="0">Choose a vendor</option>
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

<?php if (!empty($meta['error'])): ?>
  <div class="alert alert-warning py-2"><?= htmlspecialchars((string)$meta['error']) ?></div>
<?php endif; ?>
<?php foreach (($meta['notes'] ?? []) as $note): ?>
  <div class="alert alert-light border py-2"><?= htmlspecialchars((string)$note) ?></div>
<?php endforeach; ?>

<?php if ((int)($vendor_id ?? 0) > 0): ?>
<form method="post" action="<?= site_url('admin/whatsapp/billing/account') ?>" class="card shadow-sm mb-3">
  <div class="card-body">
    <input type="hidden" name="<?= $this->security->get_csrf_token_name() ?>" value="<?= $this->security->get_csrf_hash() ?>">
    <input type="hidden" name="vendor_id" value="<?= (int)$vendor_id ?>">
    <input type="hidden" name="return" value="tokens">
    <div class="row g-2 align-items-end">
      <div class="col-md-6">
        <label class="form-label small mb-1">Meta Business Agent account ID</label>
        <input type="text" name="ba_account_id" class="form-control form-control-sm" inputmode="numeric"
               value="<?= htmlspecialchars((string)($meta['ba_account_id'] ?? '')) ?>"
               placeholder="Billing Hub → Accounts → Meta Business Agent accounts">
      </div>
      <div class="col-md-2">
        <button class="btn btn-sm btn-warning">Save account</button>
      </div>
    </div>
    <div class="text-muted small mt-2">Token totals come from this Billing Hub account. Without it, Meta does not return billable tokens.</div>
  </div>
</form>
<?php endif; ?>

<div class="row g-3 mb-3">
  <div class="col-md-3">
    <div class="border rounded p-3 h-100 bg-white">
      <div class="text-muted small">Billable tokens</div>
      <div class="fs-4 fw-bold"><?= number_format($tokens) ?></div>
    </div>
  </div>
  <div class="col-md-3">
    <div class="border rounded p-3 h-100 bg-white">
      <div class="text-muted small">Estimated cost</div>
      <div class="fs-4 fw-bold">$<?= number_format($estimate, 4) ?></div>
      <div class="text-muted small">$<?= number_format($rate, 2) ?> per 1,000,000 tokens</div>
    </div>
  </div>
  <div class="col-md-3">
    <div class="border rounded p-3 h-100 bg-white">
      <div class="text-muted small">Meta reported charge</div>
      <div class="fs-4 fw-bold"><?= number_format($metaCost, 4) ?></div>
      <div class="text-muted small">Account currency from Meta</div>
    </div>
  </div>
  <div class="col-md-3">
    <div class="border rounded p-3 h-100 bg-white">
      <div class="text-muted small">Agent messages</div>
      <div class="fs-4 fw-bold"><?= number_format($messages) ?></div>
      <div class="text-muted small"><?= $typical > 0 ? ('About ' . number_format($typical) . ' replies at 22,500 tokens each') : 'A typical reply uses 20,000–25,000 tokens' ?></div>
    </div>
  </div>
</div>

<div class="card shadow-sm mb-3">
  <div class="card-header">Daily tokens</div>
  <div class="table-responsive">
    <table class="table table-sm mb-0">
      <thead>
        <tr>
          <th>Date</th>
          <th>Tokens</th>
          <th>Agent messages</th>
          <th>Estimated USD</th>
          <th>Meta charge</th>
        </tr>
      </thead>
      <tbody>
      <?php if (empty($token_days)): ?>
        <tr><td colspan="5" class="text-muted">No token rows for this period.</td></tr>
      <?php else: foreach ($token_days as $day):
        $dayTokens = (int)$day['tokens'];
        $dayEstimate = $dayTokens / 1000000 * $rate;
      ?>
        <tr>
          <td><?= htmlspecialchars((string)$day['day']) ?></td>
          <td><?= number_format($dayTokens) ?></td>
          <td><?= number_format((int)$day['messages']) ?></td>
          <td>$<?= number_format($dayEstimate, 4) ?></td>
          <td><?= number_format((float)$day['cost'], 4) ?></td>
        </tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>

<div class="alert alert-light border mb-0">
  One agent reply is one Meta Business Agent charge, based on the tokens used to read the customer message and write the reply. A short answer costs less than a long order. A message sent by a person in the inbox is a service message and is charged per message, not per token. A single message is charged once.
</div>
