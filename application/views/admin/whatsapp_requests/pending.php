<?php
$requests = $requests ?? [];
$cfg = $cfg ?? [];
$appId = htmlspecialchars((string)($cfg['app_id'] ?? ''), ENT_QUOTES, 'UTF-8');
$configId = (string)($cfg['config_id'] ?? '');
$oauth = htmlspecialchars((string)($oauth_url ?? '#'), ENT_QUOTES, 'UTF-8');
$selected = (int)($selected_request_id ?? 0);
$selectedVendor = '';
foreach ($requests as $r) {
    if ((int)$r['id'] === $selected) {
        $selectedVendor = $r['vendor_name'] ?? ('Vendor #' . (int)$r['vendor_id']);
        break;
    }
}
?>
<div class="sk-page-header d-flex flex-wrap align-items-center justify-content-between gap-2">
  <div>
    <h5 class="sk-page-title mb-1"><i class="bi bi-whatsapp me-2 text-success"></i>WhatsApp Provision Requests</h5>
    <div class="small text-muted">Pending vendor requests · connect with WhatsApp embedded login</div>
  </div>
  <a href="<?= site_url('admin/meta') ?>" class="btn btn-sm btn-outline-primary">
    <i class="bi bi-facebook me-1"></i> Meta connect page
  </a>
</div>

<?php if ($this->session->flashdata('error')): ?>
  <div class="alert alert-danger"><?= htmlspecialchars($this->session->flashdata('error')) ?></div>
<?php endif; ?>
<?php if ($this->session->flashdata('success')): ?>
  <div class="alert alert-success"><?= htmlspecialchars($this->session->flashdata('success')) ?></div>
<?php endif; ?>

<div class="card sk-table-card shadow-sm mb-3" id="wa-embed-login">
  <div class="card-body">
    <h6 class="mb-2"><i class="bi bi-facebook me-1 text-primary"></i>WhatsApp embedded login</h6>
    <?php if ($appId === ''): ?>
      <div class="alert alert-warning mb-0">
        Save <strong>App ID</strong>, <strong>App Secret</strong>, and preferably <strong>Config ID</strong> in
        <a href="<?= site_url('admin/settings?tab=wacloud') ?>">Settings → WhatsApp Cloud</a>, then reload this page.
      </div>
    <?php else: ?>
      <p class="small text-muted mb-2">
        1) Click <strong>Connect</strong> on a pending request below (selects the vendor).<br>
        2) Use <strong>Continue with Facebook</strong> / WhatsApp Embedded Signup.<br>
        3) We save the phone &amp; WABA to that vendor and mark the request approved.
      </p>
      <div class="alert alert-light border py-2 small mb-3" id="waSelectedBox">
        <?php if ($selected > 0): ?>
          Connecting request <strong>#<?= $selected ?></strong>
          <?php if ($selectedVendor !== ''): ?> for <strong><?= htmlspecialchars($selectedVendor) ?></strong><?php endif; ?>
        <?php else: ?>
          <span class="text-warning">No request selected yet — click Connect on a row first.</span>
        <?php endif; ?>
      </div>
      <div id="fb-root"></div>
      <div class="d-flex flex-wrap gap-2 align-items-center mb-2">
        <button type="button" class="btn btn-primary btn-lg" id="waEmbedLoginBtn">
          <i class="bi bi-facebook me-1"></i> Continue with Facebook / WhatsApp
        </button>
        <a class="btn btn-outline-primary" href="<?= $oauth ?>" id="waOauthFallback">
          <i class="bi bi-box-arrow-up-right me-1"></i> Open Facebook OAuth
        </a>
        <div class="fb-login-button d-none"
             data-width="280"
             data-size="large"
             data-button-type="continue_with"
             data-layout="rounded"
             data-auto-logout-link="false"
             data-use-continue-as="true"
             data-scope="whatsapp_business_management,whatsapp_business_messaging,business_management"
             onlogin="skWaProvisionOnLogin"></div>
      </div>
      <div id="waLoginStatus" class="small text-muted"></div>
    <?php endif; ?>
  </div>
</div>

<div class="card sk-table-card shadow-sm">
  <div class="card-header bg-white border-0 py-3 fw-semibold">Pending requests</div>
  <div class="card-body p-0">
    <?php if (empty($requests)): ?>
      <div class="text-muted text-center py-4">No pending requests.</div>
    <?php else: ?>
      <table class="table table-hover align-middle mb-0">
        <thead>
          <tr><th>ID</th><th>Vendor</th><th>Phone</th><th>Note</th><th>Created</th><th></th></tr>
        </thead>
        <tbody>
          <?php foreach ($requests as $r): ?>
            <tr class="<?= (int)$r['id'] === $selected ? 'table-warning' : '' ?>" id="req-row-<?= (int)$r['id'] ?>">
              <td><?= (int)$r['id'] ?></td>
              <td>
                <a href="<?= site_url('admin/vendors/view/'.(int)$r['vendor_id']) ?>" class="fw-semibold"><?= htmlspecialchars($r['vendor_name'] ?? ('Vendor #'.(int)$r['vendor_id'])) ?></a>
                <?php if (!empty($r['vendor_email'])): ?><div class="small text-muted"><?= htmlspecialchars($r['vendor_email']) ?></div><?php endif; ?>
              </td>
              <td><?= htmlspecialchars($r['display_phone'] ?: $r['phone_number_id'] ?: '—') ?></td>
              <td><?= htmlspecialchars($r['note'] ?? '') ?></td>
              <td class="small text-muted"><?= htmlspecialchars($r['created_at']) ?></td>
              <td class="text-end text-nowrap">
                <a href="<?= site_url('admin/whatsapp_requests/pending?request_id='.(int)$r['id']) ?>#wa-embed-login"
                   class="btn btn-sm btn-success wa-select-connect"
                   data-request-id="<?= (int)$r['id'] ?>"
                   data-vendor-name="<?= htmlspecialchars($r['vendor_name'] ?? '', ENT_QUOTES) ?>">
                  <i class="bi bi-link-45deg me-1"></i> Connect
                </a>
                <form method="post" action="<?= site_url('admin/whatsapp_requests/reject/'.$r['id']) ?>" class="d-inline">
                  <button class="btn btn-sm btn-outline-danger" type="submit" onclick="return confirm('Reject this request?');">Reject</button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>
</div>

<?php if ($appId !== ''): ?>
<script>
window.skWaProvisionSignup = {};
window.skWaProvisionRequestId = <?= (int)$selected ?>;

window.addEventListener('message', function (event) {
  if (event.origin !== 'https://www.facebook.com' && event.origin !== 'https://web.facebook.com') return;
  try {
    var payload = typeof event.data === 'string' ? JSON.parse(event.data) : event.data;
    if (payload && payload.type === 'WA_EMBEDDED_SIGNUP') {
      window.skWaProvisionSignup = payload.data || {};
    }
  } catch (e) {}
});

function skWaSetSelected(id, name) {
  window.skWaProvisionRequestId = parseInt(id, 10) || 0;
  var box = document.getElementById('waSelectedBox');
  if (box) {
    box.innerHTML = window.skWaProvisionRequestId
      ? ('Connecting request <strong>#' + window.skWaProvisionRequestId + '</strong>' + (name ? ' for <strong>' + name + '</strong>' : ''))
      : '<span class="text-warning">No request selected yet — click Connect on a row first.</span>';
  }
  document.querySelectorAll('tr[id^="req-row-"]').forEach(function (tr) {
    tr.classList.toggle('table-warning', tr.id === 'req-row-' + window.skWaProvisionRequestId);
  });
}

function skWaProvisionPostCode(code) {
  var box = document.getElementById('waLoginStatus');
  if (!window.skWaProvisionRequestId) {
    if (box) box.textContent = 'Select a pending request (Connect) before logging in.';
    return;
  }
  if (box) box.textContent = 'Talking to Meta Graph API…';
  var body = new URLSearchParams();
  body.set('code', code);
  body.set('request_id', String(window.skWaProvisionRequestId));
  body.set('signup', JSON.stringify(window.skWaProvisionSignup || {}));
  fetch(<?= json_encode(site_url('admin/whatsapp_requests/exchange')) ?>, {
    method: 'POST',
    headers: { 'Accept': 'application/json' },
    body: body,
    credentials: 'same-origin'
  }).then(function (r) { return r.json(); }).then(function (res) {
    if (res && res.ok) {
      if (box) box.textContent = res.message || 'Saved.';
      window.location = <?= json_encode(site_url('admin/whatsapp_requests/pending')) ?>;
      return;
    }
    if (box) box.textContent = (res && res.error) ? res.error : 'WhatsApp login failed.';
  }).catch(function () {
    if (box) box.textContent = 'Network error while saving WhatsApp login.';
  });
}

function skWaProvisionOnLogin(response) {
  var auth = response && response.authResponse ? response.authResponse : {};
  if (auth.code) {
    skWaProvisionPostCode(auth.code);
    return;
  }
  var box = document.getElementById('waLoginStatus');
  if (box) box.textContent = 'Facebook login did not return a code. Try again or use Open Facebook OAuth.';
}

function skWaLaunchEmbedLogin() {
  var box = document.getElementById('waLoginStatus');
  if (!window.skWaProvisionRequestId) {
    if (box) box.textContent = 'Select a pending request (Connect) first.';
    return;
  }
  if (!window.FB) {
    if (box) box.textContent = 'Facebook SDK still loading…';
    return;
  }
  var opts = {
    scope: 'whatsapp_business_management,whatsapp_business_messaging,business_management',
    return_scopes: true,
    response_type: 'code',
    override_default_response_type: true
  };
  var configId = <?= json_encode($configId) ?>;
  if (configId) {
    opts.config_id = configId;
    opts.extras = {
      setup: {},
      featureType: 'whatsapp_business_app_onboarding',
      sessionInfoVersion: '3'
    };
  }
  if (box) box.textContent = 'Opening Facebook / WhatsApp login…';
  FB.login(skWaProvisionOnLogin, opts);
}

window.fbAsyncInit = function () {
  if (!window.FB) return;
  FB.init({
    appId: <?= json_encode((string)($cfg['app_id'] ?? '')) ?>,
    cookie: true,
    xfbml: true,
    version: <?= json_encode((string)($cfg['api_version'] ?? 'v21.0')) ?>
  });
};

document.getElementById('waEmbedLoginBtn') && document.getElementById('waEmbedLoginBtn').addEventListener('click', skWaLaunchEmbedLogin);
document.querySelectorAll('.wa-select-connect').forEach(function (a) {
  a.addEventListener('click', function () {
    skWaSetSelected(a.getAttribute('data-request-id'), a.getAttribute('data-vendor-name') || '');
  });
});
</script>
<script async defer crossorigin="anonymous" src="https://connect.facebook.net/en_US/sdk.js"></script>
<?php endif; ?>
