<?php
$status_badges = [
  'pending'   => 'bg-warning text-dark',
  'approved'  => 'bg-success',
  'rejected'  => 'bg-danger',
  'suspended' => 'bg-secondary',
  'inactive'  => 'bg-dark',
];
?>

<div class="sk-page-header d-flex flex-wrap align-items-center justify-content-between gap-2">
  <div>
    <h5 class="sk-page-title mb-1"><i class="bi bi-shop-window me-2 text-primary"></i>Vendors</h5>
    <small class="text-muted">
      <?= (int)$counts['total'] ?> total ·
      <?= (int)$counts['pending'] ?> pending ·
      <?= (int)$counts['approved'] ?> approved
    </small>
  </div>
  <div class="d-flex gap-2">
    <a href="<?= site_url('admin/vendors/export?format=csv&'.http_build_query($filters)) ?>" class="btn btn-outline-secondary btn-sm">
      <i class="bi bi-download me-1"></i> Export CSV
    </a>
    <a href="<?= site_url('admin/vendors/add') ?>" class="btn btn-primary btn-sm fw-semibold">
      <i class="bi bi-plus-lg me-1"></i> Add Vendor
    </a>
  </div>
</div>

<div class="card sk-table-card shadow-sm mb-3">
  <div class="card-body py-3">
    <form method="get" class="row g-2 align-items-end">
      <div class="col-md-5">
        <label class="form-label small mb-1">Search</label>
        <input type="text" name="search" class="form-control form-control-sm" value="<?= htmlspecialchars($filters['search'] ?? '') ?>" placeholder="Name, email, phone...">
      </div>
      <div class="col-md-3">
        <label class="form-label small mb-1">Status</label>
        <select name="status" class="form-select form-select-sm">
          <option value="">All statuses</option>
          <?php foreach (['pending','approved','rejected','suspended','inactive'] as $st): ?>
          <option value="<?= $st ?>" <?= ($filters['status'] ?? '') === $st ? 'selected' : '' ?>><?= ucfirst($st) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-2">
        <button type="submit" class="btn btn-sm btn-dark w-100">Filter</button>
      </div>
      <div class="col-md-2">
        <a href="<?= site_url('admin/vendors') ?>" class="btn btn-sm btn-outline-secondary w-100">Reset</a>
      </div>
    </form>
  </div>
</div>

<div class="card sk-table-card shadow-sm">
  <div class="card-body p-0">
    <table class="table table-hover align-middle mb-0 sk-datatable">
      <thead>
        <tr>
          <th>Vendor</th>
          <th>Contact</th>
          <th>Commission</th>
          <th>Status</th>
          <th>Joined</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($vendors)): ?>
        <tr><td colspan="6" class="text-center text-muted py-4">No vendors found.</td></tr>
        <?php endif; ?>
        <?php foreach ($vendors as $v): ?>
        <tr>
          <td>
            <div class="fw-semibold"><?= htmlspecialchars($v['business_name']) ?></div>
            <small class="text-muted"><?= htmlspecialchars($v['store_name'] ?? $v['slug']) ?></small>
          </td>
          <td>
            <div><?= htmlspecialchars($v['owner_name']) ?></div>
            <small class="text-muted"><?= htmlspecialchars($v['email']) ?></small>
          </td>
          <td><?= number_format((float)$v['commission_rate'], 2) ?>%</td>
          <td>
            <span class="badge <?= $status_badges[$v['status']] ?? 'bg-secondary' ?>">
              <?= ucfirst($v['status']) ?>
            </span>
          </td>
          <td><?= date('d M Y', strtotime($v['created_at'])) ?></td>
          <td class="text-end">
            <div class="btn-group btn-group-sm">
              <a href="<?= site_url('admin/vendors/view/'.$v['id']) ?>" class="btn btn-outline-primary" title="View"><i class="bi bi-eye"></i></a>
              <a href="<?= site_url('admin/vendors/edit/'.$v['id']) ?>" class="btn btn-outline-secondary" title="Edit"><i class="bi bi-pencil"></i></a>
              <?php if ($v['status'] === 'approved'): ?>
              <a href="<?= site_url('admin/vendors/login_as/'.$v['id']) ?>" class="btn btn-outline-info" title="Login as vendor"><i class="bi bi-box-arrow-in-right"></i></a>
              <?php endif; ?>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php if ($total > $limit): ?>
  <div class="card-footer d-flex justify-content-between align-items-center">
    <small class="text-muted">Showing <?= min($offset + 1, $total) ?>–<?= min($offset + $limit, $total) ?> of <?= $total ?></small>
    <nav>
      <ul class="pagination pagination-sm mb-0">
        <?php $pages = ceil($total / $limit); ?>
        <?php for ($i = 1; $i <= $pages; $i++): ?>
        <li class="page-item <?= $i === $page ? 'active' : '' ?>">
          <a class="page-link" href="?<?= http_build_query(array_merge($filters, ['page' => $i])) ?>"><?= $i ?></a>
        </li>
        <?php endfor; ?>
      </ul>
    </nav>
  </div>
  <?php endif; ?>
</div>
