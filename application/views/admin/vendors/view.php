<?php
$store = $vendor['store'] ?? [];
$status_badges = [
  'pending' => 'bg-warning text-dark', 'approved' => 'bg-success', 'rejected' => 'bg-danger',
  'suspended' => 'bg-secondary', 'inactive' => 'bg-dark',
];
?>

<div class="sk-page-header d-flex flex-wrap align-items-center justify-content-between gap-2">
  <div>
    <h5 class="sk-page-title mb-1"><?= htmlspecialchars($vendor['business_name']) ?></h5>
    <span class="badge <?= $status_badges[$vendor['status']] ?? 'bg-secondary' ?>"><?= ucfirst($vendor['status']) ?></span>
    <span class="badge bg-light text-dark border ms-1"><?= ucfirst($vendor['verification_status']) ?></span>
  </div>
  <div class="d-flex flex-wrap gap-2">
    <a href="<?= site_url('admin/vendors/edit/'.$vendor['id']) ?>" class="btn btn-outline-primary btn-sm"><i class="bi bi-pencil me-1"></i>Edit</a>
    <a href="<?= site_url('admin/stores/edit/'.$vendor['id']) ?>" class="btn btn-outline-secondary btn-sm"><i class="bi bi-shop me-1"></i>Store</a>
    <?php if ($vendor['status'] === 'pending'): ?>
    <a href="<?= site_url('admin/vendors/approve/'.$vendor['id']) ?>" class="btn btn-success btn-sm">Approve</a>
    <?php endif; ?>
    <?php if ($vendor['status'] === 'approved'): ?>
    <a href="<?= site_url('admin/vendors/suspend/'.$vendor['id']) ?>" class="btn btn-warning btn-sm">Suspend</a>
    <a href="<?= site_url('admin/vendors/login_as/'.$vendor['id']) ?>" class="btn btn-info btn-sm text-white">Login as Vendor</a>
    <?php elseif ($vendor['status'] === 'suspended'): ?>
    <a href="<?= site_url('admin/vendors/activate/'.$vendor['id']) ?>" class="btn btn-success btn-sm">Activate</a>
    <?php endif; ?>
    <button type="button" class="btn btn-outline-dark btn-sm" data-bs-toggle="modal" data-bs-target="#resetPwdModal">Reset Password</button>
    <?php if ($vendor['status'] !== 'rejected'): ?>
    <button type="button" class="btn btn-outline-danger btn-sm" data-bs-toggle="modal" data-bs-target="#rejectModal">Reject</button>
    <?php endif; ?>
    <button type="button" class="btn btn-outline-danger btn-sm"
            onclick="skConfirmDelete('<?= site_url('admin/vendors/delete/'.$vendor['id']) ?>','<?= htmlspecialchars($vendor['business_name']) ?>')">
      Delete
    </button>
  </div>
</div>

<div class="row g-3">
  <div class="col-lg-8">
    <div class="card shadow-sm mb-3">
      <div class="card-header fw-semibold">Vendor Details</div>
      <div class="card-body row g-3">
        <div class="col-md-6"><small class="text-muted d-block">Owner</small><?= htmlspecialchars($vendor['owner_name']) ?></div>
        <div class="col-md-6"><small class="text-muted d-block">Email</small><?= htmlspecialchars($vendor['email']) ?></div>
        <div class="col-md-6"><small class="text-muted d-block">Phone</small><?= htmlspecialchars($vendor['phone'] ?: '—') ?></div>
        <div class="col-md-6"><small class="text-muted d-block">Commission</small><?= number_format((float)$vendor['commission_rate'], 2) ?>%</div>
        <div class="col-md-6"><small class="text-muted d-block">Subscription</small><?= ucfirst($vendor['subscription_plan'] ?? 'basic') ?></div>
        <div class="col-md-6"><small class="text-muted d-block">Rating</small><?= number_format((float)$vendor['rating'], 2) ?> (<?= (int)$vendor['rating_count'] ?>)</div>
        <?php if (!empty($vendor['notes'])): ?>
        <div class="col-12"><small class="text-muted d-block">Notes</small><?= nl2br(htmlspecialchars($vendor['notes'])) ?></div>
        <?php endif; ?>
        <?php if ($vendor['status'] === 'rejected' && !empty($vendor['rejection_reason'])): ?>
        <div class="col-12"><small class="text-muted d-block">Rejection Reason</small><span class="text-danger"><?= htmlspecialchars($vendor['rejection_reason']) ?></span></div>
        <?php endif; ?>
      </div>
    </div>

    <div class="card shadow-sm mb-3">
      <div class="card-header fw-semibold">Store</div>
      <div class="card-body row g-3">
        <div class="col-md-6"><small class="text-muted d-block">Store Name</small><?= htmlspecialchars($store['store_name'] ?? '—') ?></div>
        <div class="col-md-6"><small class="text-muted d-block">Tax ID</small><?= htmlspecialchars($store['gst_vat'] ?? '—') ?></div>
        <div class="col-md-6"><small class="text-muted d-block">Business Reg.</small><?= htmlspecialchars($store['business_reg_no'] ?? '—') ?></div>
        <div class="col-12"><small class="text-muted d-block">Description</small><?= nl2br(htmlspecialchars($store['description'] ?? '—')) ?></div>
        <div class="col-12"><small class="text-muted d-block">Pickup</small>
          <?= htmlspecialchars(trim(($store['pickup_line1'] ?? '').', '.($store['pickup_city'] ?? '').', '.($store['pickup_state'] ?? '').' - '.($store['pickup_pincode'] ?? ''), ', -')) ?: '—' ?>
        </div>
      </div>
    </div>
  </div>

  <div class="col-lg-4">
    <div class="card shadow-sm mb-3">
      <div class="card-header fw-semibold d-flex justify-content-between align-items-center">
        <span><i class="bi bi-whatsapp text-success me-1"></i>WhatsApp Numbers</span>
        <span class="badge bg-secondary"><?= count($wa_accounts ?? []) ?></span>
      </div>
      <div class="card-body">
        <?php
        $wa_accounts = $wa_accounts ?? [];
        $cfg = $cfg ?? [];
        $appId = (string)($cfg['app_id'] ?? '');
        $configId = (string)($cfg['config_id'] ?? '1661379532355089');
        $hasSecret = !empty($cfg['app_secret']);
        ?>
        <?php if ($wa_accounts): ?>
          <?php foreach ($wa_accounts as $acc): ?>
            <?php $isInactive = (($acc['status'] ?? '') === 'inactive'); ?>
            <div class="border rounded p-2 mb-2 <?= !empty($acc['is_default']) && !$isInactive ? 'border-success' : '' ?>">
              <div class="d-flex justify-content-between align-items-center gap-2 mb-1">
                <strong><?= htmlspecialchars($acc['display_phone'] ?: 'WhatsApp Number') ?></strong>
                <div class="d-flex gap-1 align-items-center flex-wrap">
                  <?php if ($isInactive): ?>
                    <span class="badge bg-secondary">Inactive</span>
                  <?php elseif (!empty($acc['is_default'])): ?>
                    <span class="badge bg-success">Active</span>
                  <?php endif; ?>
                </div>
              </div>
              <div class="small text-muted mb-1">Phone ID: <span class="font-monospace text-dark"><?= htmlspecialchars($acc['phone_number_id']) ?></span></div>
              <div class="small text-muted mb-2">WABA: <span class="font-monospace text-dark"><?= htmlspecialchars($acc['waba_id'] ?: '—') ?></span></div>
              <div class="d-flex flex-wrap gap-1">
                <?php if (!$isInactive): ?>
                  <a href="<?= site_url('admin/whatsapp/templates?vendor_id='.(int)$vendor['id']) ?>" class="btn btn-success btn-sm">Templates</a>
                  <a href="<?= site_url('admin/vendors/whatsapp_report/'.$acc['id']) ?>" class="btn btn-outline-secondary btn-sm">Report</a>
                  <?php if (empty($acc['is_default'])): ?>
                    <form method="post" action="<?= site_url('admin/vendors/set_whatsapp_account_default/'.$vendor['id']) ?>" class="d-inline">
                      <input type="hidden" name="wa_account_id" value="<?= (int)$acc['id'] ?>">
                      <button type="submit" class="btn btn-outline-success btn-sm">Set default</button>
                    </form>
                  <?php endif; ?>
                  <form method="post" action="<?= site_url('admin/whatsapp_requests/set_account_status/'.(int)$acc['id']) ?>" class="d-inline">
                    <input type="hidden" name="status" value="inactive">
                    <button type="submit" class="btn btn-outline-secondary btn-sm">Inactive</button>
                  </form>
                <?php else: ?>
                  <button type="button" class="btn btn-primary btn-sm wa-vendor-embed-btn"
                          data-account-id="<?= (int)$acc['id'] ?>"
                          data-vendor-id="<?= (int)$vendor['id'] ?>"
                          <?= ($appId === '' || !$hasSecret) ? 'disabled' : '' ?>>
                    <i class="bi bi-facebook me-1"></i>Reconnect
                  </button>
                  <form method="post" action="<?= site_url('admin/whatsapp_requests/set_account_status/'.(int)$acc['id']) ?>" class="d-inline">
                    <input type="hidden" name="status" value="active">
                    <button type="submit" class="btn btn-outline-success btn-sm">Mark active</button>
                  </form>
                <?php endif; ?>
              </div>
            </div>
          <?php endforeach; ?>
        <?php else: ?>
          <div class="text-muted small mb-2">No WhatsApp number connected yet. Use Embed Login to exchange token and save Phone ID / WABA.</div>
        <?php endif; ?>

        <div class="d-grid gap-2 mb-2">
          <button type="button" class="btn btn-primary btn-sm wa-vendor-embed-btn"
                  data-account-id="0"
                  data-vendor-id="<?= (int)$vendor['id'] ?>"
                  <?= ($appId === '' || !$hasSecret) ? 'disabled' : '' ?>>
            <i class="bi bi-facebook me-1"></i> Embed Login (connect number)
          </button>
          <a href="<?= site_url('admin/whatsapp_requests/pending') ?>" class="btn btn-outline-secondary btn-sm">All WA requests</a>
        </div>
        <div class="small text-muted" id="waVendorLoginStatus"></div>
        <?php if ($appId === '' || !$hasSecret): ?>
          <div class="alert alert-warning small mb-0 mt-2">
            Save App ID + Secret on <a href="<?= site_url('admin/meta') ?>">Meta connect</a> first.
          </div>
        <?php endif; ?>

        <hr class="my-3">
        <div class="small text-muted mb-2">Manual add (optional fallback)</div>
        <form method="post" action="<?= site_url('admin/vendors/add_whatsapp_account/'.$vendor['id']) ?>">
          <div class="mb-2">
            <label class="form-label small">Phone Number ID</label>
            <input type="text" name="wa_phone_number_id" class="form-control form-control-sm font-monospace" placeholder="WhatsApp phone number id">
          </div>
          <div class="mb-2">
            <label class="form-label small">WABA ID</label>
            <input type="text" name="wa_waba_id" class="form-control form-control-sm font-monospace" placeholder="WhatsApp Business Account id">
          </div>
          <div class="mb-2">
            <label class="form-label small">Display Phone</label>
            <input type="text" name="wa_display_phone" class="form-control form-control-sm" placeholder="+60 …">
          </div>
          <div class="mb-2">
            <label class="form-label small">Business ID</label>
            <input type="text" name="wa_business_id" class="form-control form-control-sm font-monospace" placeholder="Facebook business id">
          </div>
          <div class="form-check mb-3">
            <input type="checkbox" class="form-check-input" id="wa_make_default_<?= (int)$vendor['id'] ?>" name="wa_make_default" value="1" checked>
            <label class="form-check-label" for="wa_make_default_<?= (int)$vendor['id'] ?>">Set as active/default</label>
          </div>
          <button type="submit" class="btn btn-outline-primary btn-sm w-100">Add WhatsApp Number</button>
        </form>
      </div>
    </div>

    <div class="card shadow-sm">
      <div class="card-header fw-semibold">Timeline</div>
      <div class="card-body small">
        <div class="mb-2">Created: <?= date('d M Y, H:i', strtotime($vendor['created_at'])) ?></div>
        <?php if ($vendor['approved_at']): ?><div class="mb-2 text-success">Approved: <?= date('d M Y, H:i', strtotime($vendor['approved_at'])) ?></div><?php endif; ?>
        <?php if ($vendor['rejected_at']): ?><div class="text-danger">Rejected: <?= date('d M Y, H:i', strtotime($vendor['rejected_at'])) ?></div><?php endif; ?>
      </div>
    </div>
  </div>
</div>

<?php if ($appId !== ''): ?>
<div id="fb-root"></div>
<script>
window.skVendorWaSignup = {};
window.skVendorWaCtx = { vendorId: <?= (int)$vendor['id'] ?>, accountId: 0 };
window.addEventListener('message', function (event) {
  if (event.origin !== 'https://www.facebook.com' && event.origin !== 'https://web.facebook.com') return;
  try {
    var payload = typeof event.data === 'string' ? JSON.parse(event.data) : event.data;
    if (payload && payload.type === 'WA_EMBEDDED_SIGNUP') {
      window.skVendorWaSignup = payload.data || {};
    }
  } catch (e) {}
});
function skVendorWaStatus(msg) {
  var el = document.getElementById('waVendorLoginStatus');
  if (el) el.textContent = msg || '';
}
function skVendorWaPostCode(code) {
  skVendorWaStatus('Exchanging token… saving Phone ID / WABA…');
  var body = new URLSearchParams();
  body.set('code', code);
  body.set('request_id', '0');
  body.set('account_id', String(window.skVendorWaCtx.accountId || 0));
  body.set('vendor_id', String(window.skVendorWaCtx.vendorId || 0));
  body.set('signup', JSON.stringify(Object.assign({}, window.skVendorWaSignup || {}, {
    vendor_id: window.skVendorWaCtx.vendorId
  })));
  fetch(<?= json_encode(site_url('admin/whatsapp_requests/exchange')) ?>, {
    method: 'POST',
    headers: { 'Accept': 'application/json' },
    body: body,
    credentials: 'same-origin'
  }).then(function (r) { return r.json(); }).then(function (res) {
    if (res && res.ok) {
      skVendorWaStatus(res.message || 'Saved.');
      window.location.reload();
      return;
    }
    skVendorWaStatus((res && res.error) ? res.error : 'Embed login failed.');
  }).catch(function () {
    skVendorWaStatus('Network error during token exchange.');
  });
}
function skVendorWaOnLogin(response) {
  var auth = response && response.authResponse ? response.authResponse : {};
  if (auth.code) {
    skVendorWaPostCode(auth.code);
    return;
  }
  skVendorWaStatus('No authorization code returned.');
}
function skVendorWaLaunch(btn) {
  window.skVendorWaCtx.accountId = parseInt(btn.getAttribute('data-account-id') || '0', 10) || 0;
  window.skVendorWaCtx.vendorId = parseInt(btn.getAttribute('data-vendor-id') || '0', 10) || <?= (int)$vendor['id'] ?>;
  if (!window.FB) {
    skVendorWaStatus('Facebook SDK loading… try again.');
    return;
  }
  skVendorWaStatus('Opening Facebook Embed Login…');
  FB.login(skVendorWaOnLogin, {
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
  document.querySelectorAll('.wa-vendor-embed-btn').forEach(function (btn) {
    btn.addEventListener('click', function () { skVendorWaLaunch(btn); });
  });
};
</script>
<script async defer crossorigin="anonymous" src="https://connect.facebook.net/en_US/sdk.js"></script>
<?php endif; ?>

<div class="modal fade" id="resetPwdModal" tabindex="-1">
  <div class="modal-dialog">
    <form method="post" action="<?= site_url('admin/vendors/reset_password/'.$vendor['id']) ?>" class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Reset Vendor Password</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <label class="form-label">New password</label>
        <input type="text" name="new_password" class="form-control" value="password" required>
        <small class="text-muted">Default: <code>password</code> — share this with the vendor.</small>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" class="btn btn-primary">Reset Password</button>
      </div>
    </form>
  </div>
</div>

<div class="modal fade" id="rejectModal" tabindex="-1">
  <div class="modal-dialog">
    <form method="post" action="<?= site_url('admin/vendors/reject/'.$vendor['id']) ?>" class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Reject Vendor</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <label class="form-label">Reason</label>
        <textarea name="reason" class="form-control" rows="3" required placeholder="Reason for rejection..."></textarea>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" class="btn btn-danger">Reject Vendor</button>
      </div>
    </form>
  </div>
</div>

<a href="<?= site_url('admin/vendors') ?>" class="btn btn-link ps-0 mt-2"><i class="bi bi-arrow-left me-1"></i>Back to vendors</a>
