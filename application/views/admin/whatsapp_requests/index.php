<?php
$accounts = $accounts ?? [];
$requests = $requests ?? [];
$cfg = $cfg ?? [];
$vendorId = (int)($vendor_id ?? 0);
$active = array_values(array_filter($accounts, static function ($a) {
    return ($a['status'] ?? '') === 'active';
}));
$inactive = array_values(array_filter($accounts, static function ($a) {
    return ($a['status'] ?? '') !== 'active';
}));
$pendingReqs = array_values(array_filter($requests, static function ($r) {
    return ($r['status'] ?? '') === 'pending';
}));
?>
<div class="sk-page-header d-flex flex-wrap align-items-center justify-content-between gap-2">
  <div>
    <h5 class="sk-page-title mb-1"><i class="bi bi-whatsapp text-success me-2"></i>Connect WhatsApp</h5>
    <div class="small text-muted">
      Request a WhatsApp number → marketplace admin connects it with Embed Login → then create templates for your store.
    </div>
  </div>
  <div class="d-flex flex-wrap gap-2">
    <a href="<?= site_url('shopkart/whatsapp/templates?vendor_id='.$vendorId) ?>" class="btn btn-sm btn-outline-success">Templates</a>
    <a href="<?= site_url('shopkart/whatsapp') ?>" class="btn btn-sm btn-outline-secondary">Inbox</a>
  </div>
</div>

<?php if ($this->session->flashdata('error')): ?>
  <div class="alert alert-danger"><?= htmlspecialchars($this->session->flashdata('error')) ?></div>
<?php endif; ?>
<?php if ($this->session->flashdata('success')): ?>
  <div class="alert alert-success"><?= htmlspecialchars($this->session->flashdata('success')) ?></div>
<?php endif; ?>

<div class="card sk-table-card shadow-sm mb-3">
  <div class="card-header bg-white border-0 py-3 fw-semibold">Request a WhatsApp number</div>
  <div class="card-body">
    <p class="small text-muted mb-3">
      Submit the display phone you want connected. The <strong>master admin</strong> will run Facebook Embed Login
      for your store and save Phone Number ID, WABA, and token under vendor #<?= (int)$vendorId ?>.
    </p>
    <form method="post" action="<?= site_url('admin/whatsapp_requests/submit') ?>" class="row g-2 align-items-end">
      <div class="col-md-4">
        <label class="form-label small">Display phone</label>
        <input name="display_phone" class="form-control form-control-sm" placeholder="e.g. +60 12 345 6789" required>
      </div>
      <div class="col-md-5">
        <label class="form-label small">Note for admin</label>
        <input name="note" class="form-control form-control-sm" placeholder="Business name / why you need this number">
      </div>
      <div class="col-md-3">
        <button class="btn btn-primary btn-sm w-100" type="submit">
          <i class="bi bi-send me-1"></i> Submit to admin
        </button>
      </div>
    </form>
  </div>
</div>

<?php if (!empty($requests)): ?>
<div class="card sk-table-card shadow-sm mb-3">
  <div class="card-header bg-white border-0 py-3 fw-semibold">
    Your requests
    <?php if (count($pendingReqs)): ?>
      <span class="badge bg-warning text-dark"><?= count($pendingReqs) ?> pending</span>
    <?php endif; ?>
  </div>
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead>
          <tr>
            <th>Phone</th>
            <th>Note</th>
            <th>Status</th>
            <th>Submitted</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($requests as $r): ?>
            <tr>
              <td class="font-monospace small"><?= htmlspecialchars($r['display_phone'] ?: $r['phone_number_id'] ?: '—') ?></td>
              <td class="small"><?= htmlspecialchars($r['note'] ?? '') ?></td>
              <td>
                <?php
                  $st = (string)($r['status'] ?? '');
                  $cls = $st === 'approved' ? 'bg-success' : ($st === 'rejected' ? 'bg-danger' : 'bg-warning text-dark');
                ?>
                <span class="badge <?= $cls ?>"><?= htmlspecialchars($st ?: 'pending') ?></span>
              </td>
              <td class="small text-muted"><?= htmlspecialchars($r['created_at'] ?? '') ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<?php endif; ?>

<div class="card sk-table-card shadow-sm mb-3">
  <div class="card-header bg-white border-0 py-3 fw-semibold">
    Connected numbers
    <span class="badge bg-success"><?= count($active) ?> active</span>
    <?php if (count($inactive)): ?><span class="badge bg-secondary"><?= count($inactive) ?> inactive</span><?php endif; ?>
  </div>
  <div class="card-body p-0">
    <?php if (empty($accounts)): ?>
      <div class="text-muted text-center py-4 px-3">
        No numbers yet. Submit a request above — after the admin completes Embed Login, your Phone ID and token appear here and you can create templates.
      </div>
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
                    <a href="<?= site_url('shopkart/whatsapp/templates?vendor_id='.$vendorId) ?>" class="btn btn-sm btn-success">Templates</a>
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
                    <span class="small text-muted">Ask admin to reconnect via Embed Login</span>
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
