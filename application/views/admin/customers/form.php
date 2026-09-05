<?php
$c = $customer ?? null;
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
  <div class="card-body">
    <div class="row g-3">
      <div class="col-md-6">
        <label class="form-label">Full Name <span class="text-danger">*</span></label>
        <input type="text" name="name" class="form-control" required
               value="<?= htmlspecialchars($c['name'] ?? '') ?>">
      </div>
      <div class="col-md-6">
        <label class="form-label">Email <span class="text-danger">*</span></label>
        <input type="email" name="email" class="form-control" required
               value="<?= htmlspecialchars($c['email'] ?? '') ?>">
      </div>
      <div class="col-md-6">
        <label class="form-label">Phone</label>
        <input type="text" name="phone" class="form-control"
               value="<?= htmlspecialchars($c['phone'] ?? '') ?>"
               placeholder="e.g. 60123456789">
        <div class="form-text">Include country code when possible (MY default 60).</div>
      </div>
      <div class="col-md-6">
        <label class="form-label"><?= $isEdit ? 'New Password' : 'Password' ?></label>
        <input type="password" name="password" class="form-control" autocomplete="new-password"
               placeholder="<?= $isEdit ? 'Leave blank to keep current password' : 'Leave blank to auto-generate' ?>"
               minlength="6">
      </div>
      <div class="col-12">
        <div class="form-check form-switch">
          <input class="form-check-input" type="checkbox" name="status" id="custStatus" value="1"
            <?= ($isEdit ? !empty($c['status']) : true) ? 'checked' : '' ?>>
          <label class="form-check-label" for="custStatus">Active (uncheck to block)</label>
        </div>
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
