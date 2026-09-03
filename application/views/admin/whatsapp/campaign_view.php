<?php
$c = $campaign;
$tpl = $template ?? null;
$map = sk_wa_cloud_decode_variable_map($tpl['variable_map'] ?? $c['variable_map'] ?? '');
$modules = $modules ?? sk_wa_cloud_customer_modules();
$st = strtolower((string)$c['status']);
$autoSend = (string)$this->input->get('send') === '1' && !empty($ready);
?>
<link rel="stylesheet" href="<?= base_url('assets/admin/css/whatsapp-cloud.css') ?>">
<div class="sk-page-header d-flex flex-wrap align-items-center justify-content-between gap-2">
  <div>
    <h5 class="sk-page-title mb-0"><?= htmlspecialchars($c['name']) ?></h5>
    <div class="small text-muted">Template: <?= htmlspecialchars($c['template_name'] ?? '') ?> · <?= htmlspecialchars($c['created_at']) ?></div>
  </div>
  <div class="d-flex gap-2">
    <a class="btn btn-sm btn-outline-secondary" href="<?= site_url('shopkart/whatsapp/campaigns') ?>">All campaigns</a>
    <?php if ((int)$c['queued'] > 0): ?>
      <button type="button" class="btn btn-sm btn-success" id="waSendBtn" <?= empty($ready) ? 'disabled' : '' ?>>
        Send remaining (<?= (int)$c['queued'] ?>)
      </button>
    <?php endif; ?>
  </div>
</div>

<div class="row g-3 mb-3" id="waCampStats">
  <div class="col-6 col-md"><div class="wa-stat"><div class="wa-stat-n" data-k="total"><?= (int)$c['total'] ?></div><div class="wa-stat-l">Recipients</div></div></div>
  <div class="col-6 col-md"><div class="wa-stat"><div class="wa-stat-n" data-k="queued"><?= (int)$c['queued'] ?></div><div class="wa-stat-l">Queued</div></div></div>
  <div class="col-6 col-md"><div class="wa-stat ok"><div class="wa-stat-n" data-k="sent"><?= (int)$c['sent'] ?></div><div class="wa-stat-l">Sent</div></div></div>
  <div class="col-6 col-md"><div class="wa-stat ok"><div class="wa-stat-n" data-k="delivered"><?= (int)$c['delivered'] ?></div><div class="wa-stat-l">Delivered</div></div></div>
  <div class="col-6 col-md"><div class="wa-stat ok"><div class="wa-stat-n" data-k="read_count"><?= (int)($c['read_count'] ?? 0) ?></div><div class="wa-stat-l">Read</div></div></div>
  <div class="col-6 col-md"><div class="wa-stat bad"><div class="wa-stat-n" data-k="failed"><?= (int)$c['failed'] ?></div><div class="wa-stat-l">Failed</div></div></div>
</div>

<div class="alert alert-light border mb-3">
  <strong>Variable map</strong>
  <?php if (!$map): ?>
    <span class="text-muted"> — no customer fields mapped on this template.</span>
    <?php if (!empty($tpl['id'])): ?>
      <a href="<?= site_url('shopkart/whatsapp/templates/edit/'.$tpl['id']) ?>">Edit mapping</a>
    <?php endif; ?>
  <?php else: ?>
    <div class="mt-2">
      <?php foreach ($map as $n => $field): ?>
        <span class="badge text-bg-light me-1 mb-1">{{<?= htmlspecialchars((string)$n) ?>}} → <?= htmlspecialchars($modules[$field]['label'] ?? $field) ?></span>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<p id="waSendMsg" class="small text-muted" <?= $autoSend ? '' : 'style="display:none"' ?>>Sending in batches… keep this page open.</p>

<div class="card sk-table-card shadow-sm">
  <div class="card-header bg-white d-flex flex-wrap gap-2 align-items-center">
    <strong>Tracking</strong>
    <div class="ms-auto d-flex gap-1">
      <?php foreach (['' => 'All', 'queued' => 'Queued', 'sent' => 'Sent', 'delivered' => 'Delivered', 'read' => 'Read', 'failed' => 'Failed'] as $k => $lab): ?>
        <a class="btn btn-sm <?= ($filter_status ?? '') === $k ? 'btn-success' : 'btn-outline-secondary' ?>"
           href="<?= site_url('shopkart/whatsapp/campaigns/view/'.$c['id']) ?><?= $k !== '' ? ('?status='.$k) : '' ?>"><?= $lab ?></a>
      <?php endforeach; ?>
    </div>
  </div>
  <div class="card-body p-0">
    <table class="table table-hover mb-0" id="waRecipients">
      <thead>
        <tr>
          <th>Customer</th>
          <th>Phone</th>
          <th>Status</th>
          <th>Variables</th>
          <th>Error</th>
          <th>Sent</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach (($recipients ?? []) as $r): ?>
        <tr data-id="<?= (int)$r['id'] ?>">
          <td><?= htmlspecialchars($r['name'] ?: '—') ?></td>
          <td><?= htmlspecialchars($r['phone']) ?></td>
          <td><span class="badge wa-st-<?= htmlspecialchars($r['status']) ?>"><?= htmlspecialchars($r['status']) ?></span></td>
          <td class="small text-muted">
            <?php
              $vars = json_decode((string)($r['variables_json'] ?? ''), true);
              if (is_array($vars) && $vars) {
                  $bits = [];
                  foreach ($vars as $k => $v) {
                      $bits[] = '{{' . $k . '}}=' . $v;
                  }
                  echo htmlspecialchars(implode(' · ', $bits));
              } else {
                  echo '—';
              }
            ?>
          </td>
          <td class="small text-danger"><?= htmlspecialchars($r['error_text'] ?? '') ?></td>
          <td class="small"><?= htmlspecialchars($r['sent_at'] ?? '') ?></td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($recipients)): ?>
        <tr><td colspan="6" class="text-muted p-4">No recipients in this filter.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
<script>
(function () {
  var id = <?= (int)$c['id'] ?>;
  var sendUrl = <?= json_encode(site_url('admin/whatsapp/campaigns/send/'.$c['id'])) ?>;
  var statsUrl = <?= json_encode(site_url('admin/whatsapp/campaigns/stats/'.$c['id'])) ?>;
  var btn = document.getElementById('waSendBtn');
  var msg = document.getElementById('waSendMsg');
  var sending = false;

  function applyCampaign(c) {
    if (!c) return;
    document.querySelectorAll('#waCampStats [data-k]').forEach(function (el) {
      var k = el.getAttribute('data-k');
      if (c[k] != null) el.textContent = c[k];
    });
    if (btn) {
      var q = parseInt(c.queued, 10) || 0;
      btn.textContent = q ? ('Send remaining (' + q + ')') : 'Sent';
      if (!q) btn.disabled = true;
    }
  }

  function sendBatch() {
    if (sending) return;
    sending = true;
    if (msg) { msg.style.display = ''; msg.textContent = 'Sending batch…'; }
    if (btn) btn.disabled = true;
    fetch(sendUrl, { method: 'POST', headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
      .then(function (r) { return r.json(); })
      .then(function (data) {
        sending = false;
        if (!data.success) {
          if (msg) msg.textContent = data.message || 'Send failed.';
          if (btn) btn.disabled = false;
          return;
        }
        applyCampaign(data.campaign);
        if (data.done) {
          if (msg) msg.textContent = 'Finished. Tracking updates as WhatsApp reports delivered / read.';
          setTimeout(function () { location.href = location.pathname; }, 1200);
          return;
        }
        if (msg) msg.textContent = 'Sent this batch. ' + data.remaining + ' remaining…';
        setTimeout(sendBatch, 400);
      })
      .catch(function () {
        sending = false;
        if (msg) msg.textContent = 'Network error. Click Send remaining to retry.';
        if (btn) btn.disabled = false;
      });
  }

  if (btn) btn.addEventListener('click', sendBatch);
  <?php if ($autoSend): ?>
  sendBatch();
  <?php elseif (in_array($st, ['sending', 'sent'], true)): ?>
  setInterval(function () {
    fetch(statsUrl, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
      .then(function (r) { return r.json(); })
      .then(function (data) { if (data.success) applyCampaign(data.campaign); });
  }, 8000);
  <?php endif; ?>
})();
</script>
