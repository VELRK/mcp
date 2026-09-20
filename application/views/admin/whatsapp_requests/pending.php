<?php
$requests = $requests ?? [];
$pending_count = (int)($pending_count ?? count($requests));
$active_accounts = $active_accounts ?? [];
$inactive_accounts = $inactive_accounts ?? [];
$cfg = $cfg ?? [];
$appId = (string)($cfg['app_id'] ?? '');
$configId = (string)($cfg['config_id'] ?? '1661379532355089');
$hasSecret = !empty($cfg['app_secret']);
$selected = (int)($selected_request_id ?? 0);
?>
<div class="sk-page-header d-flex flex-wrap align-items-center justify-content-between gap-2">
  <div>
    <h5 class="sk-page-title mb-1">
      <i class="bi bi-whatsapp me-2 text-success"></i>WhatsApp Numbers
      <?php if ($pending_count > 0): ?>
        <span class="badge bg-danger align-middle"><?= $pending_count ?> pending</span>
      <?php endif; ?>
    </h5>
    <div class="small text-muted">
      Embed Login → token exchange → store Phone ID / WABA → create templates for that number.
    </div>
  </div>
  <div class="d-flex flex-wrap gap-2">
    <a href="<?= site_url('admin/whatsapp/templates') ?>" class="btn btn-sm btn-outline-success">
      <i class="bi bi-file-earmark-text me-1"></i> Templates
    </a>
    <a href="<?= site_url('admin/meta') ?>" class="btn btn-sm btn-outline-primary">
      <i class="bi bi-facebook me-1"></i> Meta app
    </a>
  </div>
</div>

<?php if ($this->session->flashdata('error')): ?>
  <div class="alert alert-danger"><?= htmlspecialchars($this->session->flashdata('error')) ?></div>
<?php endif; ?>
<?php if ($this->session->flashdata('success')): ?>
  <div class="alert alert-success"><?= htmlspecialchars($this->session->flashdata('success')) ?></div>
<?php endif; ?>

<?php if ($appId === '' || !$hasSecret): ?>
  <div class="alert alert-warning">
    Save <strong>App ID</strong> and <strong>App Secret</strong> on
    <a href="<?= site_url('admin/meta') ?>">Facebook / WhatsApp login</a> before using Embed Login.
  </div>
<?php endif; ?>

<div class="card sk-table-card shadow-sm mb-3">
  <div class="card-header bg-white border-0 py-3 d-flex justify-content-between align-items-center">
    <span class="fw-semibold">Pending requests <span class="badge bg-danger"><?= $pending_count ?></span></span>
    <span class="small text-muted" id="waLoginStatus"></span>
  </div>
  <div class="card-body p-0">
    <?php if (empty($requests)): ?>
      <div class="text-muted text-center py-4">No pending requests.</div>
    <?php else: ?>
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
          <thead>
            <tr>
              <th>ID</th>
              <th>Vendor</th>
              <th>Requested phone</th>
              <th>Note</th>
              <th>Created</th>
              <th class="text-end">Action</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($requests as $r): ?>
              <tr id="req-row-<?= (int)$r['id'] ?>" class="<?= (int)$r['id'] === $selected ? 'table-warning' : '' ?>">
                <td>#<?= (int)$r['id'] ?></td>
                <td>
                  <a href="<?= site_url('admin/vendors/view/'.(int)$r['vendor_id']) ?>" class="fw-semibold">
                    <?= htmlspecialchars($r['vendor_name'] ?? ('Vendor #'.(int)$r['vendor_id'])) ?>
                  </a>
                  <?php if (!empty($r['vendor_email'])): ?>
                    <div class="small text-muted"><?= htmlspecialchars($r['vendor_email']) ?></div>
                  <?php endif; ?>
                </td>
                <td class="font-monospace small"><?= htmlspecialchars($r['display_phone'] ?: $r['phone_number_id'] ?: '—') ?></td>
                <td class="small"><?= htmlspecialchars($r['note'] ?? '') ?></td>
                <td class="small text-muted"><?= htmlspecialchars($r['created_at'] ?? '') ?></td>
                <td class="text-end text-nowrap">
                  <button type="button"
                          class="btn btn-sm btn-primary wa-embed-btn"
                          data-request-id="<?= (int)$r['id'] ?>"
                          data-account-id="0"
                          data-vendor-id="<?= (int)$r['vendor_id'] ?>"
                          data-vendor-name="<?= htmlspecialchars($r['vendor_name'] ?? '', ENT_QUOTES) ?>"
                          <?= ($appId === '' || !$hasSecret) ? 'disabled' : '' ?>>
                    <i class="bi bi-facebook me-1"></i> Embed Login
                  </button>
                  <form method="post" action="<?= site_url('admin/whatsapp_requests/reject/'.$r['id']) ?>" class="d-inline">
                    <button class="btn btn-sm btn-outline-danger" type="submit" onclick="return confirm('Reject this request?');">Reject</button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</div>

<div class="card sk-table-card shadow-sm mb-3">
  <div class="card-header bg-white border-0 py-3 fw-semibold">
    Active numbers <span class="badge bg-success"><?= count($active_accounts) ?></span>
  </div>
  <div class="card-body p-0">
    <?php if (empty($active_accounts)): ?>
      <div class="text-muted text-center py-4">No connected WhatsApp numbers yet.</div>
    <?php else: ?>
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
          <thead>
            <tr>
              <th>Vendor</th>
              <th>Display</th>
              <th>Phone Number ID</th>
              <th>WABA ID</th>
              <th>Updated</th>
              <th class="text-end">Action</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($active_accounts as $a): ?>
              <tr>
                <td>
                  <a href="<?= site_url('admin/vendors/view/'.(int)$a['vendor_id']) ?>" class="fw-semibold">
                    <?= htmlspecialchars($a['vendor_name'] ?? ('Vendor #'.(int)$a['vendor_id'])) ?>
                  </a>
                </td>
                <td><?= htmlspecialchars($a['display_phone'] ?: '—') ?></td>
                <td class="font-monospace small"><?= htmlspecialchars($a['phone_number_id'] ?? '') ?></td>
                <td class="font-monospace small"><?= htmlspecialchars($a['waba_id'] ?: '—') ?></td>
                <td class="small text-muted"><?= htmlspecialchars($a['updated_at'] ?? '') ?></td>
                <td class="text-end text-nowrap">
                  <a class="btn btn-sm btn-success"
                     href="<?= site_url('admin/whatsapp/templates?vendor_id='.(int)$a['vendor_id']) ?>">
                    <i class="bi bi-file-earmark-plus me-1"></i> Templates
                  </a>
                  <form method="post" action="<?= site_url('admin/whatsapp_requests/set_account_status/'.(int)$a['id']) ?>" class="d-inline">
                    <input type="hidden" name="status" value="inactive">
                    <button type="submit" class="btn btn-sm btn-outline-secondary" onclick="return confirm('Mark this number inactive?');">Inactive</button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</div>

<div class="card sk-table-card shadow-sm mb-3">
  <div class="card-header bg-white border-0 py-3 fw-semibold">
    Inactive numbers <span class="badge bg-secondary"><?= count($inactive_accounts) ?></span>
  </div>
  <div class="card-body p-0">
    <?php if (empty($inactive_accounts)): ?>
      <div class="text-muted text-center py-4">No inactive numbers.</div>
    <?php else: ?>
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
          <thead>
            <tr>
              <th>Vendor</th>
              <th>Display</th>
              <th>Phone Number ID</th>
              <th>WABA ID</th>
              <th class="text-end">Action</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($inactive_accounts as $a): ?>
              <tr>
                <td><?= htmlspecialchars($a['vendor_name'] ?? ('Vendor #'.(int)$a['vendor_id'])) ?></td>
                <td><?= htmlspecialchars($a['display_phone'] ?: '—') ?></td>
                <td class="font-monospace small"><?= htmlspecialchars($a['phone_number_id'] ?? '') ?></td>
                <td class="font-monospace small"><?= htmlspecialchars($a['waba_id'] ?: '—') ?></td>
                <td class="text-end text-nowrap">
                  <button type="button"
                          class="btn btn-sm btn-primary wa-embed-btn"
                          data-request-id="0"
                          data-account-id="<?= (int)$a['id'] ?>"
                          data-vendor-id="<?= (int)$a['vendor_id'] ?>"
                          data-vendor-name="<?= htmlspecialchars($a['vendor_name'] ?? '', ENT_QUOTES) ?>"
                          <?= ($appId === '' || !$hasSecret) ? 'disabled' : '' ?>>
                    <i class="bi bi-facebook me-1"></i> Reconnect Embed Login
                  </button>
                  <form method="post" action="<?= site_url('admin/whatsapp_requests/set_account_status/'.(int)$a['id']) ?>" class="d-inline">
                    <input type="hidden" name="status" value="active">
                    <button type="submit" class="btn btn-sm btn-outline-success">Mark active</button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</div>

<pre class="small bg-light border rounded p-2 mb-0" id="waSessionLog" style="min-height:3rem;white-space:pre-wrap">Session / exchange log…</pre>

<?php if ($appId !== ''): ?>
<div id="fb-root"></div>
<script>
window.skWaCtx = { requestId: <?= (int)$selected ?>, accountId: 0, vendorId: 0 };
window.skWaSignup = {};

window.addEventListener('message', function (event) {
  if (event.origin !== 'https://www.facebook.com' && event.origin !== 'https://web.facebook.com') return;
  try {
    var payload = typeof event.data === 'string' ? JSON.parse(event.data) : event.data;
    if (payload && payload.type === 'WA_EMBEDDED_SIGNUP') {
      window.skWaSignup = payload.data || {};
      var log = document.getElementById('waSessionLog');
      if (log) log.textContent = JSON.stringify(payload, null, 2);
    }
  } catch (e) {}
});

function skWaStatus(msg) {
  var el = document.getElementById('waLoginStatus');
  if (el) el.textContent = msg || '';
}

function skWaPostCode(code) {
  if (!window.skWaCtx.requestId && !window.skWaCtx.accountId) {
    skWaStatus('Click Embed Login on a pending or inactive row first.');
    return;
  }
  skWaStatus('Exchanging code → saving Phone ID / WABA…');
  var body = new URLSearchParams();
  body.set('code', code);
  body.set('request_id', String(window.skWaCtx.requestId || 0));
  body.set('account_id', String(window.skWaCtx.accountId || 0));
  body.set('signup', JSON.stringify(window.skWaSignup || {}));
  fetch(<?= json_encode(site_url('admin/whatsapp_requests/exchange')) ?>, {
    method: 'POST',
    headers: { 'Accept': 'application/json' },
    body: body,
    credentials: 'same-origin'
  }).then(function (r) { return r.json(); }).then(function (res) {
    if (res && res.ok) {
      skWaStatus(res.message || 'Saved.');
      var url = (res.saved && res.saved.templates_url)
        ? res.saved.templates_url
        : <?= json_encode(site_url('admin/whatsapp_requests/pending')) ?>;
      window.location = url;
      return;
    }
    skWaStatus((res && res.error) ? res.error : 'Embed login failed.');
  }).catch(function () {
    skWaStatus('Network error during token exchange.');
  });
}

function skWaOnLogin(response) {
  var auth = response && response.authResponse ? response.authResponse : {};
  if (auth.code) {
    skWaPostCode(auth.code);
    return;
  }
  skWaStatus('Facebook did not return an authorization code.');
}

function skWaLaunchEmbed(btn) {
  window.skWaCtx = {
    requestId: parseInt(btn.getAttribute('data-request-id') || '0', 10) || 0,
    accountId: parseInt(btn.getAttribute('data-account-id') || '0', 10) || 0,
    vendorId: parseInt(btn.getAttribute('data-vendor-id') || '0', 10) || 0
  };
  var name = btn.getAttribute('data-vendor-name') || '';
  skWaStatus('Opening Embed Login for ' + (name || ('vendor #' + window.skWaCtx.vendorId)) + '…');

  if (!window.FB) {
    skWaStatus('Facebook SDK still loading… try again.');
    return;
  }
  FB.login(skWaOnLogin, {
    config_id: <?= json_encode($configId) ?>,
    response_type: 'code',
    override_default_response_type: true,
    extras: {
      setup: {},
      featureType: '',
      sessionInfoVersion: '3'
    }
  });
}

window.fbAsyncInit = function () {
  if (!window.FB) return;
  FB.init({
    appId: <?= json_encode($appId) ?>,
    cookie: true,
    xfbml: false,
    version: <?= json_encode((string)($cfg['api_version'] ?? 'v21.0')) ?>
  });
  document.querySelectorAll('.wa-embed-btn').forEach(function (btn) {
    btn.addEventListener('click', function () { skWaLaunchEmbed(btn); });
  });
};
</script>
<script async defer crossorigin="anonymous" src="https://connect.facebook.net/en_US/sdk.js"></script>
<?php endif; ?>
