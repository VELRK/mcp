<?php
$cfg = $cfg ?? [];
$rows = $rows ?? [];
$platform_ready = !empty($platform_ready);
$webhook = $webhook_uri ?? '';
?>
<div class="sk-page-header d-flex justify-content-between align-items-center flex-wrap gap-2">
  <h5 class="sk-page-title mb-0"><i class="bi bi-robot me-2 text-success"></i>Meta Business Agent</h5>
  <a href="<?= site_url('admin/meta') ?>" class="btn btn-sm btn-outline-secondary">Meta connect</a>
</div>

<?php if ($this->session->flashdata('success')): ?>
  <div class="alert alert-success"><?= htmlspecialchars($this->session->flashdata('success')) ?></div>
<?php endif; ?>
<?php if ($this->session->flashdata('error')): ?>
  <div class="alert alert-danger"><?= htmlspecialchars($this->session->flashdata('error')) ?></div>
<?php endif; ?>

<div class="row g-3 mb-3">
  <div class="col-lg-8">
    <div class="card sk-table-card shadow-sm">
      <div class="card-body">
        <h6 class="mb-2">Platform configuration (.env)</h6>
        <p class="small text-muted mb-3">
          Meta Business Agent is the primary WhatsApp responder. This app stays on
          <code>standby</code> / <code>messaging_handovers</code> and serves commerce connectors.
        </p>
        <div class="row g-2 small">
          <div class="col-md-4"><strong>Enabled:</strong> <?= !empty($cfg['enabled']) ? 'yes' : 'no' ?></div>
          <div class="col-md-4"><strong>API version:</strong> <?= htmlspecialchars((string)($cfg['api_version'] ?? '')) ?></div>
          <div class="col-md-4"><strong>Audience default:</strong> <?= htmlspecialchars((string)($cfg['default_audience'] ?? '')) ?></div>
          <div class="col-md-4"><strong>System token:</strong> <?= !empty($cfg['has_system_token']) ? 'set' : 'missing' ?></div>
          <div class="col-md-4"><strong>Connector key:</strong> <?= !empty($cfg['has_connector_key']) ? 'set' : 'missing' ?></div>
          <div class="col-md-4"><strong>Ready:</strong> <?= $platform_ready ? '<span class="text-success">yes</span>' : '<span class="text-danger">no</span>' ?></div>
          <div class="col-12">
            <strong>Connector base URL:</strong>
            <code><?= htmlspecialchars((string)($cfg['connector_base_url'] ?? '')) ?></code>
          </div>
          <div class="col-12">
            <strong>Webhook:</strong>
            <code><?= htmlspecialchars((string)$webhook) ?></code>
            <span class="text-muted">— subscribe <code>messages</code>, <code>standby</code>, <code>messaging_handovers</code></span>
          </div>
        </div>
        <?php if (!$platform_ready): ?>
          <div class="alert alert-warning mt-3 mb-0 small">
            Copy <code>.env.example</code> to <code>.env</code> and set
            <code>META_BA_ENABLED=1</code>, <code>META_BA_SYSTEM_USER_TOKEN</code>,
            <code>META_BA_CONNECTOR_API_KEY</code>, and a public
            <code>META_BA_CONNECTOR_BASE_URL</code> (HTTPS in production).
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
  <div class="col-lg-4">
    <div class="card sk-table-card shadow-sm h-100">
      <div class="card-body">
        <h6 class="mb-2">Rollout order</h6>
        <ol class="small mb-0 ps-3">
          <li>Connect WhatsApp number</li>
          <li>Check eligibility</li>
          <li>Onboard agent</li>
          <li>Sync commerce connector</li>
          <li>Test (Agent Test API)</li>
          <li>Enable allowlisted</li>
          <li>Enable live (EVERYONE) when billing ready</li>
        </ol>
      </div>
    </div>
  </div>
</div>

<div class="card sk-table-card shadow-sm">
  <div class="card-body">
    <h6 class="mb-3">WhatsApp numbers</h6>
    <?php if (!$rows): ?>
      <p class="text-muted mb-0">No active WhatsApp numbers yet. Connect one under Meta / My numbers.</p>
    <?php else: ?>
      <div class="table-responsive">
        <table class="table table-sm align-middle">
          <thead>
            <tr>
              <th>Number</th>
              <th>Vendor</th>
              <th>Agent</th>
              <th>Status</th>
              <th style="min-width:280px">Actions</th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($rows as $row):
              $acct = $row['account'] ?? [];
              $agent = $row['agent'] ?? null;
              $vendor = $row['vendor'] ?? null;
              $phoneId = (string)($acct['phone_number_id'] ?? '');
              $display = trim((string)($acct['display_phone'] ?? '')) ?: $phoneId;
              $shop = trim((string)($vendor['business_name'] ?? $vendor['owner_name'] ?? '')) ?: ('Vendor #' . (int)($acct['vendor_id'] ?? 0));
          ?>
            <tr data-phone="<?= htmlspecialchars($phoneId) ?>">
              <td>
                <div class="fw-semibold"><?= htmlspecialchars($display) ?></div>
                <div class="small text-muted font-monospace"><?= htmlspecialchars($phoneId) ?></div>
              </td>
              <td class="small"><?= htmlspecialchars($shop) ?></td>
              <td class="small">
                <?php if ($agent && !empty($agent['agent_id'])): ?>
                  <code><?= htmlspecialchars((string)$agent['agent_id']) ?></code>
                <?php else: ?>
                  <span class="text-muted">—</span>
                <?php endif; ?>
                <?php if ($agent && !empty($agent['connector_id'])): ?>
                  <div class="text-muted">connector set</div>
                <?php endif; ?>
              </td>
              <td class="small">
                <?php
                  $bits = [];
                  if (!empty($agent['eligible'])) $bits[] = 'eligible';
                  if (!empty($agent['onboarded'])) $bits[] = 'onboarded';
                  if (!empty($agent['agent_enabled'])) $bits[] = 'enabled';
                  if (!empty($agent['ai_audience'])) $bits[] = (string)$agent['ai_audience'];
                  if (!empty($agent['sync_status'])) $bits[] = (string)$agent['sync_status'];
                  echo $bits ? htmlspecialchars(implode(' · ', $bits)) : '<span class="text-muted">pending</span>';
                  if (!empty($agent['last_error'])):
                ?>
                  <div class="text-danger"><?= htmlspecialchars((string)$agent['last_error']) ?></div>
                <?php endif; ?>
              </td>
              <td>
                <div class="d-flex flex-wrap gap-1">
                  <button type="button" class="btn btn-sm btn-outline-primary mba-op" data-op="eligibility">Eligibility</button>
                  <button type="button" class="btn btn-sm btn-outline-primary mba-op" data-op="onboard">Onboard</button>
                  <button type="button" class="btn btn-sm btn-outline-success mba-op" data-op="sync">Sync tools</button>
                  <button type="button" class="btn btn-sm btn-outline-secondary mba-op" data-op="test">Test</button>
                  <button type="button" class="btn btn-sm btn-success mba-op" data-op="enable_allowlist">Enable allowlist</button>
                  <button type="button" class="btn btn-sm btn-warning mba-op" data-op="enable_live">Enable live</button>
                  <button type="button" class="btn btn-sm btn-outline-danger mba-op" data-op="disable">Disable</button>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</div>

<div id="mbaResult" class="alert alert-secondary mt-3 d-none small" style="white-space:pre-wrap"></div>

<script>
(function () {
  var result = document.getElementById('mbaResult');
  var csrfName = <?= json_encode($this->security->get_csrf_token_name()) ?>;
  var csrfHash = <?= json_encode($this->security->get_csrf_hash()) ?>;
  function csrfFromCookie() {
    var m = document.cookie.match(/(?:^|; )csrf_cookie=([^;]*)/);
    return m ? decodeURIComponent(m[1]) : csrfHash;
  }
  function show(ok, msg, data) {
    result.classList.remove('d-none', 'alert-success', 'alert-danger', 'alert-secondary');
    result.classList.add(ok ? 'alert-success' : 'alert-danger');
    result.textContent = msg + (data ? '\n' + JSON.stringify(data, null, 2) : '');
  }
  document.querySelectorAll('.mba-op').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var tr = btn.closest('tr');
      var phone = tr ? tr.getAttribute('data-phone') : '';
      var op = btn.getAttribute('data-op');
      if (!phone || !op) return;
      btn.disabled = true;
      var body = new FormData();
      body.append(csrfName, csrfFromCookie());
      body.append('phone_number_id', phone);
      body.append('op', op);
      if (op === 'test') {
        var msg = window.prompt('Test message', 'Hi, what products do you have?');
        if (msg === null) { btn.disabled = false; return; }
        body.append('message', msg);
      }
      fetch('<?= site_url('admin/meta/agent/action') ?>', {
        method: 'POST',
        body: body,
        credentials: 'same-origin',
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
      }).then(function (r) {
        return r.text().then(function (t) {
          var j = null;
          try { j = JSON.parse(t); } catch (e) {}
          return { ok: r.ok, j: j, raw: t };
        });
      }).then(function (x) {
        if (!x.j) {
          show(false, x.ok ? 'Unexpected response' : 'Request blocked (refresh the page and try again)');
          return;
        }
        show(!!x.j.success, x.j.message || 'Done', x.j.data);
        if (x.j.success) setTimeout(function () { location.reload(); }, 900);
      })
        .catch(function (e) { show(false, e.message || 'Request failed'); })
        .finally(function () { btn.disabled = false; });
    });
  });
})();
</script>
