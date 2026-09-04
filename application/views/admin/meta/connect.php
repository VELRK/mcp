<?php
$cfg = $cfg ?? [];
$redirect = $redirect_uri ?? sk_wa_meta_redirect_uri();
$webhook = $webhook_uri ?? sk_wa_meta_webhook_uri();
$appId = htmlspecialchars((string)($cfg['app_id'] ?? ''), ENT_QUOTES, 'UTF-8');
$configId = htmlspecialchars((string)($cfg['config_id'] ?? ''), ENT_QUOTES, 'UTF-8');
$version = htmlspecialchars((string)($cfg['api_version'] ?? 'v21.0'), ENT_QUOTES, 'UTF-8');
$oauth = htmlspecialchars((string)($oauth_url ?? '#'), ENT_QUOTES, 'UTF-8');
?>
<div class="sk-page-header">
  <h5 class="sk-page-title"><i class="bi bi-facebook me-2 text-primary"></i>Facebook / WhatsApp login</h5>
</div>

<?php if ($this->session->flashdata('success')): ?>
  <div class="alert alert-success"><?= htmlspecialchars($this->session->flashdata('success')) ?></div>
<?php endif; ?>
<?php if ($this->session->flashdata('error')): ?>
  <div class="alert alert-danger"><?= htmlspecialchars($this->session->flashdata('error')) ?></div>
<?php endif; ?>

<div class="row g-3">
  <div class="col-lg-7">
    <div class="card sk-table-card shadow-sm">
      <div class="card-body">
        <h6 class="mb-2">Paste these URLs in Meta Developer</h6>
        <p class="small text-muted mb-3">Facebook App → Facebook Login → Settings, and WhatsApp → Configuration.</p>
        <label class="form-label">Redirect URI</label>
        <div class="input-group mb-3">
          <input class="form-control font-monospace" id="metaRedirect" readonly value="<?= htmlspecialchars($redirect) ?>">
          <button type="button" class="btn btn-outline-secondary" data-copy="#metaRedirect">Copy</button>
        </div>
        <label class="form-label">WhatsApp webhook</label>
        <div class="input-group mb-2">
          <input class="form-control font-monospace" id="metaWebhook" readonly value="<?= htmlspecialchars($webhook) ?>">
          <button type="button" class="btn btn-outline-secondary" data-copy="#metaWebhook">Copy</button>
        </div>
        <div class="form-text mb-0">Callback fields: <code>messages</code>. Verify token is the one saved in WhatsApp Cloud settings.</div>
      </div>
    </div>

    <div class="card sk-table-card shadow-sm mt-3">
      <div class="card-body">
        <h6 class="mb-2">Facebook embed login</h6>
        <?php if ($appId === ''): ?>
          <div class="alert alert-warning mb-0">
            Save <strong>App ID</strong> and <strong>App Secret</strong> in
            <a href="<?= site_url('admin/settings?tab=wacloud') ?>">Settings → WhatsApp Cloud</a>, then open this page again.
          </div>
        <?php else: ?>
          <p class="small text-muted">Login with Facebook. Meta returns a code; we call Graph API, then save phone ID, WABA ID and tokens.</p>
          <div id="fb-root"></div>
          <div class="d-flex flex-wrap gap-2 align-items-center mb-3">
            <div class="fb-login-button"
                 data-width="280"
                 data-size="large"
                 data-button-type="continue_with"
                 data-layout="rounded"
                 data-auto-logout-link="false"
                 data-use-continue-as="true"
                 data-scope="whatsapp_business_management,whatsapp_business_messaging,business_management"
                 onlogin="skMetaOnLogin"></div>
            <a class="btn btn-primary" href="<?= $oauth ?>">
              <i class="bi bi-box-arrow-up-right me-1"></i> Continue with Facebook
            </a>
          </div>
          <div id="metaLoginStatus" class="small text-muted"></div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <div class="col-lg-5">
    <div class="card sk-table-card shadow-sm">
      <div class="card-body">
        <h6 class="mb-2">Saved from Meta</h6>
        <dl class="row small mb-0">
          <dt class="col-5">Phone Number ID</dt>
          <dd class="col-7 font-monospace"><?= htmlspecialchars((string)($cfg['phone_number_id'] ?? '')) ?: '—' ?></dd>
          <dt class="col-5">WABA ID</dt>
          <dd class="col-7 font-monospace"><?= htmlspecialchars((string)($cfg['waba_id'] ?? '')) ?: '—' ?></dd>
          <dt class="col-5">Display phone</dt>
          <dd class="col-7"><?= htmlspecialchars((string)($cfg['display_phone'] ?? '')) ?: '—' ?></dd>
          <dt class="col-5">Token expiry</dt>
          <dd class="col-7"><?= htmlspecialchars((string)($cfg['token_expires'] ?? '')) ?: '—' ?></dd>
          <dt class="col-5">Status</dt>
          <dd class="col-7"><?= !empty($connected) ? '<span class="badge bg-success">Connected</span>' : '<span class="badge bg-secondary">Not connected</span>' ?></dd>
        </dl>
      </div>
    </div>
  </div>
</div>

<script>
window.skMetaSignup = {};
window.addEventListener('message', function (event) {
  if (event.origin !== 'https://www.facebook.com' && event.origin !== 'https://web.facebook.com') return;
  try {
    var payload = typeof event.data === 'string' ? JSON.parse(event.data) : event.data;
    if (payload && payload.type === 'WA_EMBEDDED_SIGNUP') {
      window.skMetaSignup = payload.data || {};
    }
  } catch (e) {}
});

function skMetaPostCode(code) {
  var box = document.getElementById('metaLoginStatus');
  if (box) box.textContent = 'Talking to Meta Graph API…';
  var body = new URLSearchParams();
  body.set('code', code);
  body.set('signup', JSON.stringify(window.skMetaSignup || {}));
  fetch(<?= json_encode(site_url('admin/meta/exchange')) ?>, {
    method: 'POST',
    headers: { 'Accept': 'application/json' },
    body: body
  }).then(function (r) { return r.json(); }).then(function (res) {
    if (res && res.ok) {
      if (box) box.textContent = res.message || 'Saved.';
      window.location = <?= json_encode(site_url('admin/settings?tab=wacloud')) ?>;
      return;
    }
    if (box) box.textContent = (res && res.error) ? res.error : 'Facebook login failed.';
  }).catch(function () {
    if (box) box.textContent = 'Network error while saving Facebook login.';
  });
}

function skMetaOnLogin(response) {
  var auth = response && response.authResponse ? response.authResponse : {};
  if (auth.code) {
    skMetaPostCode(auth.code);
    return;
  }
  if (auth.accessToken) {
    window.location = <?= json_encode(site_url('admin/meta/connect')) ?>;
  }
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
</script>
<?php if ($appId !== ''): ?>
<script async defer crossorigin="anonymous" src="https://connect.facebook.net/en_US/sdk.js"></script>
<?php endif; ?>
<script>
document.querySelectorAll('[data-copy]').forEach(function (btn) {
  btn.addEventListener('click', function () {
    var el = document.querySelector(btn.getAttribute('data-copy'));
    if (!el) return;
    navigator.clipboard.writeText(el.value || '').then(function () {
      btn.textContent = 'Copied';
      setTimeout(function () { btn.textContent = 'Copy'; }, 1200);
    });
  });
});
</script>
