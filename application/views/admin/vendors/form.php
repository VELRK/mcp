<?php
$is_edit = !empty($vendor);
$action  = $is_edit ? site_url('admin/vendors/update/'.$vendor['id']) : site_url('admin/vendors/store');
$store   = $vendor['store'] ?? [];
?>

<div class="sk-page-header">
  <h5 class="sk-page-title">
    <i class="bi bi-shop me-2 text-primary"></i><?= $is_edit ? 'Edit Vendor' : 'Add Vendor' ?>
  </h5>
  <a href="<?= site_url('admin/vendors') ?>" class="btn btn-outline-secondary btn-sm">Back to list</a>
</div>

<form method="post" action="<?= $action ?>" class="card sk-table-card shadow-sm">
  <div class="card-body">
    <div class="row g-3">
      <div class="col-12"><h6 class="text-muted text-uppercase small fw-bold">Business</h6></div>
      <div class="col-md-6">
        <label class="form-label">Business Name <span class="text-danger">*</span></label>
        <input type="text" name="business_name" class="form-control" required value="<?= htmlspecialchars($vendor['business_name'] ?? '') ?>">
      </div>
      <div class="col-md-6">
        <label class="form-label">Store Name</label>
        <input type="text" name="store_name" class="form-control" value="<?= htmlspecialchars($store['store_name'] ?? '') ?>">
      </div>
      <div class="col-md-6">
        <label class="form-label">Owner Name <span class="text-danger">*</span></label>
        <input type="text" name="owner_name" class="form-control" required value="<?= htmlspecialchars($vendor['owner_name'] ?? '') ?>">
      </div>
      <div class="col-md-6">
        <label class="form-label">Email <span class="text-danger">*</span></label>
        <input type="email" name="email" class="form-control" required value="<?= htmlspecialchars($vendor['email'] ?? '') ?>">
      </div>
      <div class="col-md-4">
        <label class="form-label">Phone</label>
        <input type="text" name="phone" class="form-control" inputmode="numeric" maxlength="14"
               value="<?= htmlspecialchars($vendor['phone'] ?? '') ?>" placeholder="9876543210">
      </div>
      <div class="col-md-4">
        <label class="form-label">Password <?= $is_edit ? '(leave blank to keep)' : '' ?></label>
        <input type="password" name="password" class="form-control" <?= $is_edit ? '' : '' ?>>
      </div>
      <div class="col-md-4">
        <label class="form-label">Commission Rate (%)</label>
        <input type="number" step="0.01" name="commission_rate" class="form-control" value="<?= htmlspecialchars($vendor['commission_rate'] ?? '10') ?>">
      </div>

      <div class="col-12 mt-2"><h6 class="text-muted text-uppercase small fw-bold">Store & Tax</h6></div>
      <div class="col-md-4">
        <label class="form-label">Tax ID</label>
        <input type="text" name="gst_vat" class="form-control" value="<?= htmlspecialchars($store['gst_vat'] ?? '') ?>">
      </div>
      <div class="col-md-4">
        <label class="form-label">Business Registration No</label>
        <input type="text" name="business_reg_no" class="form-control" value="<?= htmlspecialchars($store['business_reg_no'] ?? '') ?>">
      </div>
      <div class="col-md-4">
        <label class="form-label">Subscription Plan</label>
        <select name="subscription_plan" class="form-select">
          <?php foreach (['basic','standard','premium'] as $plan): ?>
          <option value="<?= $plan ?>" <?= ($vendor['subscription_plan'] ?? 'basic') === $plan ? 'selected' : '' ?>><?= ucfirst($plan) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-12">
        <label class="form-label">Description</label>
        <textarea name="description" class="form-control" rows="3"><?= htmlspecialchars($store['description'] ?? '') ?></textarea>
      </div>

      <div class="col-12 mt-2"><h6 class="text-muted text-uppercase small fw-bold">Pickup Address</h6></div>
      <div class="col-md-6">
        <label class="form-label">Address Line</label>
        <input type="text" name="pickup_line1" class="form-control" value="<?= htmlspecialchars($store['pickup_line1'] ?? '') ?>">
      </div>
      <div class="col-md-3">
        <label class="form-label">City</label>
        <input type="text" name="pickup_city" class="form-control" value="<?= htmlspecialchars($store['pickup_city'] ?? '') ?>">
      </div>
      <div class="col-md-3">
        <label class="form-label">State</label>
        <input type="text" name="pickup_state" class="form-control" value="<?= htmlspecialchars($store['pickup_state'] ?? '') ?>">
      </div>
      <div class="col-md-3">
        <label class="form-label">Pincode</label>
        <input type="text" name="pickup_pincode" class="form-control" value="<?= htmlspecialchars($store['pickup_pincode'] ?? '') ?>">
      </div>

      <div class="col-12 mt-2"><h6 class="text-muted text-uppercase small fw-bold">Status</h6></div>
      <div class="col-md-4">
        <label class="form-label">Vendor Status</label>
        <select name="status" class="form-select">
          <?php foreach (['pending','approved','rejected','suspended','inactive'] as $st): ?>
          <option value="<?= $st ?>" <?= ($vendor['status'] ?? 'pending') === $st ? 'selected' : '' ?>><?= ucfirst($st) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-4">
        <label class="form-label">Verification</label>
        <select name="verification_status" class="form-select">
          <?php foreach (['unverified','pending','verified','rejected'] as $vs): ?>
          <option value="<?= $vs ?>" <?= ($vendor['verification_status'] ?? 'unverified') === $vs ? 'selected' : '' ?>><?= ucfirst($vs) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-12">
        <label class="form-label">Admin Notes</label>
        <textarea name="notes" class="form-control" rows="2"><?= htmlspecialchars($vendor['notes'] ?? '') ?></textarea>
      </div>
    </div>
  </div>
  <div class="card-footer d-flex justify-content-end gap-2">
    <a href="<?= site_url('admin/vendors') ?>" class="btn btn-outline-secondary">Cancel</a>
    <button type="submit" class="btn btn-primary"><?= $is_edit ? 'Update Vendor' : 'Create Vendor' ?></button>
  </div>
</form>
