<?php
$cfg = $cfg ?? [];
$redirect = $redirect_uri ?? sk_wa_meta_redirect_uri();
$webhook = $webhook_uri ?? sk_wa_meta_webhook_uri();
$appId = htmlspecialchars((string)($cfg['app_id'] ?? ''), ENT_QUOTES, 'UTF-8');
$configId = htmlspecialchars((string)($cfg['config_id'] ?? '1661379532355089'), ENT_QUOTES, 'UTF-8');
$version = htmlspecialchars((string)($cfg['api_version'] ?? 'v21.0'), ENT_QUOTES, 'UTF-8');
$oauth = htmlspecialchars((string)($oauth_url ?? '#'), ENT_QUOTES, 'UTF-8');
$cfgAppId = (string)($cfg['app_id'] ?? '1775381900284261');
$cfgConfigId = (string)($cfg['config_id'] ?? '1661379532355089');
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
        <label class="form-label">WhatsApp webhook callback</label>
        <div class="input-group mb-2">
          <input class="form-control font-monospace" id="metaWebhook" readonly value="<?= htmlspecialchars($webhook) ?>">
          <button type="button" class="btn btn-outline-secondary" data-copy="#metaWebhook">Copy</button>
        </div>
        <div class="form-text mb-0">
          Callback fields: <code>messages</code>.
          Verify token: <a href="<?= site_url('admin/settings?tab=wacloud') ?>">Settings → WhatsApp Cloud</a>.
        </div>
      </div>
    </div>

    <div class="card sk-table-card shadow-sm mt-3">
      <div class="card-body">
        <h6 class="mb-2">Meta app + Embedded Signup config</h6>
        <p class="small text-muted">
          Use login configuration <strong>ES Config</strong>
          (<code>config_id=1661379532355089</code>). Phone / WABA / tokens are filled after login — not typed here.
        </p>
        <form method="post" action="<?= site_url('admin/meta/save_app') ?>" class="row g-3">
          <div class="col-md-6">
            <label class="form-label">Facebook App ID</label>
            <input type="text" name="wa_cloud_app_id" class="form-control font-monospace"
                   value="<?= htmlspecialchars($cfgAppId) ?>" required>
          </div>
          <div class="col-md-6">
            <label class="form-label">App secret</label>
            <input type="password" name="wa_cloud_app_secret" class="form-control font-monospace" autocomplete="new-password"
                   value="" placeholder="<?= !empty($cfg['app_secret']) ? '•••• saved (leave blank to keep)' : 'Required — from Meta App → Settings → Basic' ?>">
          </div>
          <div class="col-md-6">
            <label class="form-label">Embedded Signup config ID</label>
            <input type="text" name="wa_cloud_config_id" class="form-control font-monospace"
                   value="<?= htmlspecialchars($cfgConfigId) ?>" required>
          </div>
          <div class="col-md-6">
            <label class="form-label">Graph API version</label>
            <input type="text" name="wa_cloud_api_version" class="form-control"
                   value="<?= htmlspecialchars((string)($cfg['api_version'] ?? 'v21.0')) ?>">
          </div>
          <div class="col-12">
            <button type="submit" class="btn btn-sm btn-primary">Save app credentials</button>
          </div>
        </form>
      </div>
    </div>

    <div class="card sk-table-card shadow-sm mt-3">
      <div class="card-body">
        <h6 class="mb-2">Embedded Signup + token exchange</h6>
        <p class="small text-muted mb-3">
          1) User clicks login → Meta returns a short-lived <code>code</code>.<br>
          2) Our server exchanges that <code>code</code> for an access token (App Secret stays server-side).<br>
          3) We upgrade to a long-lived token, then save Phone Number ID / WABA from the signup session.
        </p>
        <?php if ($appId === '' || empty($cfg['app_secret'])): ?>
          <div class="alert alert-warning mb-0">
            Save <strong>App ID</strong> and <strong>App Secret</strong> above first, then reload this page.
          </div>
        <?php else: ?>
          <div id="fb-root"></div>
          <div class="d-flex flex-wrap gap-2 align-items-center mb-3">
            <button type="button" class="btn btn-primary" id="skMetaEmbedLoginBtn">
              <i class="bi bi-facebook me-1"></i> Login with Facebook (Embedded Signup)
            </button>
            <a class="btn btn-outline-primary" href="<?= $oauth ?>">
              <i class="bi bi-box-arrow-up-right me-1"></i> Continue with Facebook (redirect)
            </a>
          </div>
          <pre class="small bg-light border rounded p-2 mb-2" id="metaSessionLog" style="min-height:4.5rem;white-space:pre-wrap">Session logging response will appear here (WABA ID / Phone Number IDs)…</pre>
          <div id="metaLoginStatus" class="small text-muted"></div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <div class="col-lg-5">
    <div class="card sk-table-card shadow-sm">
      <div class="card-body">
        <h6 class="mb-2">Saved from Meta (dynamic)</h6>
        <dl class="row small mb-0">
          <dt class="col-5">Config ID</dt>
          <dd class="col-7 font-monospace"><?= $configId ?: '—' ?></dd>
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
    <div class="card sk-table-card shadow-sm mt-3">
      <div class="card-body small">
        <h6 class="mb-2">What “Exchange Token” means</h6>
        <p class="mb-2">Meta does <strong>not</strong> give you a permanent token in the browser. After Embedded Signup it only gives a one-time <code>code</code>.</p>
        <p class="mb-2">Our backend calls Graph:</p>
        <code class="d-block mb-2" style="word-break:break-all">GET /oauth/access_token?client_id=…&amp;client_secret=…&amp;redirect_uri=…&amp;code=…</code>
        <p class="mb-0">That is the PHP cURL step in Meta’s docs. We already run it in <code>admin/meta/exchange</code> and <code>admin/meta/callback</code> — you do not paste the code manually.</p>
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
      var log = document.getElementById('metaSessionLog');
      if (log) {
        log.textContent = JSON.stringify(payload, null, 2);
      }
    }
  } catch (e) {}
});

function skMetaPostCode(code) {
  var box = document.getElementById('metaLoginStatus');
  if (box) box.textContent = 'Exchanging authorization code for access token (server-side)…';
  var body = new URLSearchParams();
  body.set('code', code);
  body.set('signup', JSON.stringify(window.skMetaSignup || {}));
  fetch(<?= json_encode(site_url('admin/meta/exchange')) ?>, {
    method: 'POST',
    headers: { 'Accept': 'application/json' },
    body: body
  }).then(function (r) { return r.json(); }).then(function (res) {
    if (res && res.ok) {
      if (box) box.textContent = res.message || 'Token saved.';
      window.location = <?= json_encode(site_url('admin/meta')) ?>;
      return;
    }
    if (box) box.textContent = (res && res.error) ? res.error : 'Token exchange failed.';
  }).catch(function () {
    if (box) box.textContent = 'Network error during token exchange.';
  });
}

function skMetaLaunchEmbeddedSignup() {
  if (!window.FB) {
    var box = document.getElementById('metaLoginStatus');
    if (box) box.textContent = 'Facebook SDK still loading… try again in a second.';
    return;
  }
  FB.login(function (response) {
    var auth = response && response.authResponse ? response.authResponse : {};
    if (auth.code) {
      skMetaPostCode(auth.code);
      return;
    }
    var box = document.getElementById('metaLoginStatus');
    if (box) box.textContent = 'Login cancelled or no authorization code returned.';
  }, {
    config_id: <?= json_encode($cfgConfigId) ?>,
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
    appId: <?= json_encode($cfgAppId) ?>,
    cookie: true,
    xfbml: false,
    version: <?= json_encode((string)($cfg['api_version'] ?? 'v21.0')) ?>
  });
  var btn = document.getElementById('skMetaEmbedLoginBtn');
  if (btn) btn.addEventListener('click', skMetaLaunchEmbeddedSignup);
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
