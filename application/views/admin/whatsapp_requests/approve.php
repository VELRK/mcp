<?php
$req = $request ?? null;
if (!$req) { show_404(); }
?>
<div class="sk-page-header d-flex align-items-center justify-content-between">
  <div>
    <h5 class="sk-page-title mb-1">Approve WhatsApp Request #<?= (int)$req['id'] ?></h5>
    <div class="small text-muted">Vendor ID: <?= (int)$req['vendor_id'] ?></div>
  </div>
  <div>
    <a href="<?= site_url('admin/whatsapp_requests/pending') ?>" class="btn btn-link">Back to requests</a>
  </div>
</div>

<div class="card">
  <div class="card-body">
    <form method="post" action="<?= site_url('admin/whatsapp_requests/approve/'.$req['id']) ?>">
      <div class="row g-2">
        <div class="col-md-4">
          <label class="form-label small">Phone Number ID</label>
          <input name="phone_number_id" class="form-control form-control-sm" value="<?= htmlspecialchars($req['phone_number_id'] ?? '') ?>">
        </div>
        <div class="col-md-4">
          <label class="form-label small">Display Phone</label>
          <input name="display_phone" class="form-control form-control-sm" value="<?= htmlspecialchars($req['display_phone'] ?? '') ?>">
        </div>
        <div class="col-md-4">
          <label class="form-label small">WABA ID</label>
          <input name="waba_id" class="form-control form-control-sm" value="<?= htmlspecialchars($req['waba_id'] ?? '') ?>">
        </div>
        <div class="col-md-6">
          <label class="form-label small">Business ID</label>
          <input name="business_id" class="form-control form-control-sm" value="<?= htmlspecialchars($req['business_id'] ?? '') ?>">
        </div>
        <div class="col-md-6">
          <label class="form-label small">App ID (optional)</label>
          <input name="app_id" class="form-control form-control-sm">
        </div>
        <div class="col-md-6">
          <label class="form-label small">Access Token</label>
          <input name="access_token" class="form-control form-control-sm font-monospace">
        </div>
        <div class="col-md-6">
          <label class="form-label small">Refresh Token</label>
          <input name="refresh_token" class="form-control form-control-sm font-monospace">
        </div>
        <div class="col-12">
          <div class="form-check form-switch">
            <input class="form-check-input" type="checkbox" name="is_default" id="isDefault">
            <label class="form-check-label" for="isDefault">Set as default for vendor</label>
          </div>
        </div>
        <div class="col-12 text-end">
          <button class="btn btn-primary btn-sm">Save and connect</button>
        </div>
      </div>
    </form>
  </div>
</div>
