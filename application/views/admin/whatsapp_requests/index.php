<?php
$accounts = $accounts ?? [];
$requests = $requests ?? [];
$cfg = $cfg ?? [];
$vendorId = (int)($vendor_id ?? 0);
$appId = (string)($cfg['app_id'] ?? '');
$configId = (string)($cfg['config_id'] ?? '1661379532355089');
$hasSecret = !empty($cfg['app_secret']);
$active = array_values(array_filter($accounts, static function ($a) {
    return ($a['status'] ?? '') === 'active';
}));
$inactive = array_values(array_filter($accounts, static function ($a) {
    return ($a['status'] ?? '') !== 'active';
}));
?>
<div class="sk-page-header d-flex flex-wrap align-items-center justify-content-between gap-2">
  <div>
    <h5 class="sk-page-title mb-1"><i class="bi bi-whatsapp text-success me-2"></i>Connect WhatsApp</h5>
    <div class="small text-muted">Add multiple WhatsApp Business numbers. Each Embed Login saves Phone ID + WABA for your store.</div>
  </div>
  <div class="d-flex flex-wrap gap-2">
    <a href="<?= site_url('admin/whatsapp/templates') ?>" class="btn btn-sm btn-outline-success">Templates</a>
    <a href="<?= site_url('admin/whatsapp') ?>" class="btn btn-sm btn-outline-secondary">Inbox</a>
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
    WhatsApp connect is not configured yet (Meta App ID / Secret). Contact the marketplace admin.
  </div>
<?php endif; ?>

<div class="card sk-table-card shadow-sm mb-3">
  <div class="card-body d-flex flex-wrap align-items-center justify-content-between gap-2">
    <div>
      <div class="fw-semibold mb-1">Add another WhatsApp number</div>
      <div class="small text-muted mb-0">
        Opens Facebook Embedded Signup. You can connect several numbers — each gets its own Phone ID / WABA in your store.
      </div>
    </div>
    <button type="button" class="btn btn-primary wa-vendor-add-btn"
            data-account-id="0"
            <?= ($appId === '' || !$hasSecret) ? 'disabled' : '' ?>>
      <i class="bi bi-facebook me-1"></i> Embed Login — Add number
    </button>
  </div>
  <div class="px-3 pb-3 small text-muted" id="waVendorConnectStatus"></div>
</div>

<div class="card sk-table-card shadow-sm mb-3">
  <div class="card-header bg-white border-0 py-3 fw-semibold">
    Your connected numbers
    <span class="badge bg-success"><?= count($active) ?> active</span>
    <?php if (count($inactive)): ?><span class="badge bg-secondary"><?= count($inactive) ?> inactive</span><?php endif; ?>
  </div>
  <div class="card-body p-0">
    <?php if (empty($accounts)): ?>
      <div class="text-muted text-center py-4">No numbers yet. Click <strong>Embed Login — Add number</strong> above.</div>
    <?php else: ?>
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
          <thead>
            <tr>
              <th>Display</th>
              <th>Phone Number ID</th>
              <th>WABA ID</th>
              <th>Status</th>
              <th class="text-end">Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($accounts as $a): ?>
              <?php $isInactive = (($a['status'] ?? '') !== 'active'); ?>
              <tr>
                <td>
                  <strong><?= htmlspecialchars($a['display_phone'] ?: 'WhatsApp') ?></strong>
                  <?php if (!empty($a['is_default']) && !$isInactive): ?>
                    <span class="badge bg-success ms-1">Default</span>
                  <?php endif; ?>
                </td>
                <td class="font-monospace small"><?= htmlspecialchars($a['phone_number_id'] ?? '') ?></td>
                <td class="font-monospace small"><?= htmlspecialchars($a['waba_id'] ?: '—') ?></td>
                <td>
                  <?php if ($isInactive): ?>
                    <span class="badge bg-secondary">Inactive</span>
                  <?php else: ?>
                    <span class="badge bg-success">Active</span>
                  <?php endif; ?>
                </td>
                <td class="text-end text-nowrap">
                  <?php if (!$isInactive): ?>
                    <a href="<?= site_url('admin/whatsapp/templates') ?>" class="btn btn-sm btn-success">Templates</a>
                    <?php if (empty($a['is_default'])): ?>
                      <form method="post" action="<?= site_url('admin/whatsapp_requests/vendor_set_default') ?>" class="d-inline">
                        <input type="hidden" name="wa_account_id" value="<?= (int)$a['id'] ?>">
                        <button type="submit" class="btn btn-sm btn-outline-success">Set default</button>
                      </form>
                    <?php endif; ?>
                    <form method="post" action="<?= site_url('admin/whatsapp_requests/vendor_set_status') ?>" class="d-inline">
                      <input type="hidden" name="wa_account_id" value="<?= (int)$a['id'] ?>">
                      <input type="hidden" name="status" value="inactive">
                      <button type="submit" class="btn btn-sm btn-outline-secondary" onclick="return confirm('Mark this number inactive?');">Inactive</button>
                    </form>
                  <?php else: ?>
                    <button type="button" class="btn btn-sm btn-primary wa-vendor-add-btn"
                            data-account-id="<?= (int)$a['id'] ?>"
                            <?= ($appId === '' || !$hasSecret) ? 'disabled' : '' ?>>
                      Reconnect
                    </button>
                    <form method="post" action="<?= site_url('admin/whatsapp_requests/vendor_set_status') ?>" class="d-inline">
                      <input type="hidden" name="wa_account_id" value="<?= (int)$a['id'] ?>">
                      <input type="hidden" name="status" value="active">
                      <button type="submit" class="btn btn-sm btn-outline-success">Mark active</button>
                    </form>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</div>

<details class="mb-3">
  <summary class="small text-muted" style="cursor:pointer">Need admin help? Submit a request instead</summary>
  <div class="card mt-2">
    <div class="card-body">
      <form method="post" action="<?= site_url('admin/whatsapp_requests/submit') ?>">
        <div class="row g-2">
          <div class="col-md-4">
            <label class="form-label small">Display Phone</label>
            <input name="display_phone" class="form-control form-control-sm" placeholder="e.g. +60 …" required>
          </div>
          <div class="col-md-4">
            <label class="form-label small">Note</label>
            <input name="note" class="form-control form-control-sm" placeholder="Ask admin to connect this number">
          </div>
          <div class="col-md-4 d-flex align-items-end">
            <button class="btn btn-outline-primary btn-sm">Submit request</button>
          </div>
        </div>
      </form>
      <?php if (!empty($requests)): ?>
        <hr>
        <div class="small fw-semibold mb-2">Your requests</div>
        <ul class="list-group list-group-flush">
          <?php foreach ($requests as $r): ?>
            <li class="list-group-item px-0 d-flex justify-content-between">
              <span><?= htmlspecialchars($r['display_phone'] ?: $r['phone_number_id'] ?: '—') ?></span>
              <span class="badge bg-light text-dark border"><?= htmlspecialchars($r['status']) ?></span>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </div>
  </div>
</details>

<?php if ($appId !== ''): ?>
<div id="fb-root"></div>
<script>
window.skVWaSignup = {};
window.skVWaAccountId = 0;
window.addEventListener('message', function (event) {
  if (event.origin !== 'https://www.facebook.com' && event.origin !== 'https://web.facebook.com') return;
  try {
    var payload = typeof event.data === 'string' ? JSON.parse(event.data) : event.data;
    if (payload && payload.type === 'WA_EMBEDDED_SIGNUP') {
      window.skVWaSignup = payload.data || {};
    }
  } catch (e) {}
});
function skVWaStatus(msg) {
  var el = document.getElementById('waVendorConnectStatus');
  if (el) el.textContent = msg || '';
}
function skVWaPostCode(code) {
  skVWaStatus('Exchanging token and saving Phone ID / WABA…');
  var body = new URLSearchParams();
  body.set('code', code);
  body.set('account_id', String(window.skVWaAccountId || 0));
  body.set('signup', JSON.stringify(Object.assign({}, window.skVWaSignup || {}, {
    vendor_id: <?= (int)$vendorId ?>
  })));
  fetch(<?= json_encode(site_url('admin/whatsapp_requests/vendor_exchange')) ?>, {
    method: 'POST',
    headers: { 'Accept': 'application/json' },
    body: body,
    credentials: 'same-origin'
  }).then(function (r) { return r.json(); }).then(function (res) {
    if (res && res.ok) {
      skVWaStatus(res.message || 'Saved.');
      window.location.reload();
      return;
    }
    skVWaStatus((res && res.error) ? res.error : 'Connect failed.');
  }).catch(function () {
    skVWaStatus('Network error during token exchange.');
  });
}
function skVWaOnLogin(response) {
  var auth = response && response.authResponse ? response.authResponse : {};
  if (auth.code) {
    skVWaPostCode(auth.code);
    return;
  }
  skVWaStatus('Facebook did not return an authorization code.');
}
function skVWaLaunch(btn) {
  window.skVWaAccountId = parseInt(btn.getAttribute('data-account-id') || '0', 10) || 0;
  if (!window.FB) {
    skVWaStatus('Facebook SDK still loading… try again.');
    return;
  }
  skVWaStatus(window.skVWaAccountId ? 'Reconnecting…' : 'Opening Embed Login to add a number…');
  FB.login(skVWaOnLogin, {
    config_id: <?= json_encode($configId) ?>,
    response_type: 'code',
    override_default_response_type: true,
    extras: { setup: {}, featureType: '', sessionInfoVersion: '3' }
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
  document.querySelectorAll('.wa-vendor-add-btn').forEach(function (btn) {
    btn.addEventListener('click', function () { skVWaLaunch(btn); });
  });
};
</script>
<script async defer crossorigin="anonymous" src="https://connect.facebook.net/en_US/sdk.js"></script>
<?php endif; ?>
