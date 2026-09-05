<?php
$c = $customer ?? null;
$a = $address ?? [];
$isEdit = !empty($c['id']);
$action = $isEdit
    ? site_url('admin/customers/update/' . (int)$c['id'])
    : site_url('admin/customers/store');
?>

<div class="sk-page-header d-flex justify-content-between align-items-center flex-wrap gap-2">
  <h5 class="sk-page-title mb-0">
    <i class="bi bi-<?= $isEdit ? 'pencil-square' : 'person-plus' ?> me-2 text-warning"></i>
    <?= $isEdit ? 'Edit Customer' : 'Add Customer' ?>
  </h5>
  <div class="d-flex gap-2">
    <?php if ($isEdit): ?>
    <a href="<?= site_url('admin/customers/view/'.$c['id']) ?>" class="btn btn-sm btn-outline-primary">
      <i class="bi bi-eye me-1"></i> View
    </a>
    <?php endif; ?>
    <a href="<?= site_url('admin/customers') ?>" class="btn btn-sm btn-outline-secondary">
      <i class="bi bi-arrow-left me-1"></i> Back
    </a>
  </div>
</div>

<form method="post" action="<?= $action ?>" class="card sk-table-card shadow-sm">
  <?php if (!empty($a['id'])): ?>
  <input type="hidden" name="address_id" value="<?= (int)$a['id'] ?>">
  <?php endif; ?>
  <div class="card-body">
    <h6 class="fw-semibold mb-3">Basic details</h6>
    <div class="row g-3 mb-4">
      <div class="col-md-6">
        <label class="form-label">Full Name <span class="text-danger">*</span></label>
        <input type="text" name="name" class="form-control" required
               value="<?= htmlspecialchars($c['name'] ?? '') ?>"
               placeholder="Customer full name">
      </div>
      <div class="col-md-6">
        <label class="form-label">Phone</label>
        <input type="text" name="phone" class="form-control"
               value="<?= htmlspecialchars($c['phone'] ?? '') ?>"
               placeholder="e.g. 60123456789">
        <div class="form-text">Include country code when possible (MY default 60).</div>
      </div>
      <div class="col-md-6">
        <label class="form-label">Email <span class="text-muted fw-normal">(optional)</span></label>
        <input type="email" name="email" class="form-control"
               value="<?= htmlspecialchars($c['email'] ?? '') ?>"
               placeholder="customer@example.com">
      </div>
      <div class="col-md-6">
        <label class="form-label">Status</label>
        <div class="form-check form-switch mt-2">
          <input class="form-check-input" type="checkbox" name="status" id="custStatus" value="1"
            <?= ($isEdit ? !empty($c['status']) : true) ? 'checked' : '' ?>>
          <label class="form-check-label" for="custStatus">Active (uncheck to block)</label>
        </div>
      </div>
      <?php if ($isEdit): ?>
      <div class="col-md-6">
        <label class="form-label">New Password <span class="text-muted fw-normal">(optional)</span></label>
        <input type="password" name="password" class="form-control" autocomplete="new-password"
               placeholder="Leave blank to keep current password" minlength="6">
      </div>
      <?php endif; ?>
    </div>

    <h6 class="fw-semibold mb-3">Address <span class="text-muted fw-normal">(optional)</span></h6>
    <div class="row g-3">
      <div class="col-md-6">
        <label class="form-label">Company name</label>
        <input type="text" name="company_name" class="form-control"
               value="<?= htmlspecialchars($a['company_name'] ?? '') ?>">
      </div>
      <div class="col-md-6">
        <label class="form-label">Address label</label>
        <input type="text" name="address_label" class="form-control"
               value="<?= htmlspecialchars($a['label'] ?? 'Home') ?>"
               placeholder="Home / Office">
      </div>
      <div class="col-12">
        <label class="form-label">Address line 1</label>
        <input type="text" name="line1" class="form-control"
               value="<?= htmlspecialchars($a['line1'] ?? '') ?>"
               placeholder="Street, building, unit">
      </div>
      <div class="col-12">
        <label class="form-label">Address line 2</label>
        <input type="text" name="line2" class="form-control"
               value="<?= htmlspecialchars($a['line2'] ?? '') ?>">
      </div>
      <div class="col-md-4">
        <label class="form-label">City</label>
        <input type="text" name="city" class="form-control"
               value="<?= htmlspecialchars($a['city'] ?? '') ?>">
      </div>
      <div class="col-md-4">
        <label class="form-label">State</label>
        <input type="text" name="state" class="form-control"
               value="<?= htmlspecialchars($a['state'] ?? '') ?>">
      </div>
      <div class="col-md-4">
        <label class="form-label">Postcode</label>
        <input type="text" name="pincode" class="form-control"
               value="<?= htmlspecialchars($a['pincode'] ?? '') ?>">
      </div>
      <div class="col-md-6">
        <label class="form-label">Country</label>
        <input type="text" name="country" class="form-control"
               value="<?= htmlspecialchars($a['country'] ?? 'Malaysia') ?>">
      </div>
      <div class="col-md-6">
        <label class="form-label">Address phone</label>
        <input type="text" name="address_phone" class="form-control"
               value="<?= htmlspecialchars($a['phone'] ?? ($c['phone'] ?? '')) ?>"
               placeholder="Delivery contact phone">
      </div>
    </div>
  </div>
  <div class="card-footer bg-white d-flex gap-2">
    <button type="submit" class="btn btn-warning fw-semibold px-4">
      <i class="bi bi-check-lg me-1"></i> <?= $isEdit ? 'Save Customer' : 'Create Customer' ?>
    </button>
    <a href="<?= site_url($isEdit ? 'admin/customers/view/'.$c['id'] : 'admin/customers') ?>" class="btn btn-outline-secondary">Cancel</a>
  </div>
</form>
