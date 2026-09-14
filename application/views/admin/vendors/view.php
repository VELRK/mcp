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
      <div class="card-header fw-semibold">WhatsApp Numbers</div>
      <div class="card-body">
        <?php
        $this->load->model('Sk_Vendor_whatsapp_account_model');
        $wa_accounts = $this->Sk_Vendor_whatsapp_account_model->get_for_vendor((int)$vendor['id']);
        ?>
        <?php if ($wa_accounts): ?>
          <?php foreach ($wa_accounts as $acc): ?>
            <div class="border rounded p-2 mb-2 <?= !empty($acc['is_default']) ? 'border-success bg-success-subtle' : '' ?>">
              <div class="d-flex justify-content-between align-items-center gap-2 mb-1">
                <strong><?= htmlspecialchars($acc['display_phone'] ?: 'WhatsApp Number') ?></strong>
                <div class="d-flex gap-2 align-items-center">
                  <?php if (!empty($acc['is_default'])): ?><span class="badge bg-success">Active</span><?php endif; ?>
                  <a href="<?= site_url('admin/vendors/whatsapp_report/'.$acc['id']) ?>" class="btn btn-outline-secondary btn-sm">View report</a>
                </div>
              </div>
              <div class="small text-muted mb-1">Phone ID: <span class="font-monospace text-dark"><?= htmlspecialchars($acc['phone_number_id']) ?></span></div>
              <div class="small text-muted mb-2">WABA: <span class="font-monospace text-dark"><?= htmlspecialchars($acc['waba_id'] ?: '—') ?></span></div>
              <form method="post" action="<?= site_url('admin/vendors/set_whatsapp_account_default/'.$vendor['id']) ?>">
                <input type="hidden" name="wa_account_id" value="<?= (int)$acc['id'] ?>">
                <button type="submit" class="btn btn-sm <?= !empty($acc['is_default']) ? 'btn-success disabled' : 'btn-outline-success' ?>" <?= !empty($acc['is_default']) ? 'disabled' : '' ?>><?= !empty($acc['is_default']) ? 'Current default' : 'Set as active' ?></button>
              </form>
            </div>
          <?php endforeach; ?>
        <?php else: ?>
          <div class="text-muted small">No WhatsApp numbers saved for this vendor yet.</div>
        <?php endif; ?>

        <hr class="my-3">
        <form method="post" action="<?= site_url('admin/vendors/add_whatsapp_account/'.$vendor['id']) ?>" class="mt-2">
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
            <input type="text" name="wa_display_phone" class="form-control form-control-sm" placeholder="+1 234 567 8900">
          </div>
          <div class="mb-2">
            <label class="form-label small">Business ID</label>
            <input type="text" name="wa_business_id" class="form-control form-control-sm font-monospace" placeholder="Facebook business id">
          </div>
          <div class="form-check mb-3">
            <input type="checkbox" class="form-check-input" id="wa_make_default_<?= (int)$vendor['id'] ?>" name="wa_make_default" value="1">
            <label class="form-check-label" for="wa_make_default_<?= (int)$vendor['id'] ?>">Set as active/default</label>
          </div>
          <button type="submit" class="btn btn-primary btn-sm w-100">Add WhatsApp Number</button>
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
