<?php
$account = $account ?? [];
$report = $report ?? ['summary'=>[], 'breakdown'=>[], 'details'=>[]];
$from = $from ?? null;
$to = $to ?? null;
?>

<div class="sk-page-header d-flex align-items-center justify-content-between">
  <div>
    <h5 class="sk-page-title mb-1">WhatsApp Report</h5>
    <div class="small text-muted">Account: <?= htmlspecialchars($account['display_phone'] ?: $account['phone_number_id']) ?></div>
  </div>
  <div>
    <a href="<?= site_url('admin/vendors/view/'.intval($account['vendor_id'])) ?>" class="btn btn-link">Back to vendor</a>
  </div>
</div>

<div class="card mb-3">
  <div class="card-body">
    <form class="row g-2 align-items-end" method="get">
      <div class="col-auto">
        <label class="form-label small">From</label>
        <input type="date" name="from" class="form-control form-control-sm" value="<?= htmlspecialchars($from) ?>">
      </div>
      <div class="col-auto">
        <label class="form-label small">To</label>
        <input type="date" name="to" class="form-control form-control-sm" value="<?= htmlspecialchars($to) ?>">
      </div>
      <div class="col-auto">
        <button class="btn btn-primary btn-sm">Filter</button>
      </div>
      <div class="col-auto">
        <a class="btn btn-outline-secondary btn-sm" href="<?= site_url('admin/vendors/whatsapp_report/'.intval($account['id']).'?from='.urlencode($from).'&to='.urlencode($to).'&export=csv') ?>">Export CSV</a>
      </div>
    </form>
  </div>
</div>

<div class="row">
  <div class="col-lg-4">
    <div class="card mb-3">
      <div class="card-header">Summary</div>
      <div class="card-body small">
        <div>Messages sent: <strong><?= (int)($report['summary']['messages_sent'] ?? 0) ?></strong></div>
        <div>Total spent: <strong><?= number_format((float)($report['summary']['total_spent'] ?? 0),4) .' '.htmlspecialchars($report['summary']['currency'] ?? '') ?></strong></div>
      </div>
    </div>

    <div class="card">
      <div class="card-header">Breakdown</div>
      <div class="card-body small">
        <?php if (!empty($report['breakdown'])): ?>
          <ul class="list-unstyled mb-0">
            <?php foreach ($report['breakdown'] as $b): ?>
              <li><?= htmlspecialchars($b['message_type'] ?: 'unknown') ?> — <?= (int)$b['count'] ?> messages — spent <?= number_format((float)$b['spent'],4) ?></li>
            <?php endforeach; ?>
          </ul>
        <?php else: ?>
          <div class="text-muted">No data</div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <div class="col-lg-8">
    <div class="card">
      <div class="card-header">Recent Messages (details)</div>
      <div class="card-body p-0">
        <?php if (!empty($report['details'])): ?>
        <div class="table-responsive">
          <table class="table table-sm mb-0">
            <thead>
              <tr>
                <th>Sent At</th>
                <th>Recipient</th>
                <th>Type</th>
                <th>Cost</th>
                <th>Status</th>
                <th>Message ID</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($report['details'] as $d): ?>
                <tr>
                  <td><?= htmlspecialchars($d['sent_at']) ?></td>
                  <td><?= htmlspecialchars($d['recipient'] ?: '') ?></td>
                  <td><?= htmlspecialchars($d['message_type'] ?: '') ?></td>
                  <td><?= number_format((float)$d['cost'],4) .' '.htmlspecialchars($d['currency'] ?? '') ?></td>
                  <td><?= htmlspecialchars($d['status'] ?: '') ?></td>
                  <td class="font-monospace small text-break"><?= htmlspecialchars($d['message_id'] ?: '') ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <?php else: ?>
          <div class="p-3 text-muted">No recent messages found.</div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>
