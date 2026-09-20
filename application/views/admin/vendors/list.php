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

<?php
$total_whatsapp_messages = 0;
$active_shops = 0;
$last_wa_count = 0;
foreach ($vendors as $v) {
  $count = (int)($v['whatsapp_ai_count'] ?? 0);
  $total_whatsapp_messages += $count;
  if ($count > 0) {
    $active_shops++;
  }
  if ($count > $last_wa_count) {
    $last_wa_count = $count;
  }
}
?>

<div class="row g-3 mb-3">
  <div class="col-md-4">
    <div class="card sk-stat-card shadow-sm h-100">
      <div class="card-body d-flex align-items-center gap-3">
        <div class="sk-stat-icon bg-primary bg-opacity-10 text-primary"><i class="bi bi-whatsapp"></i></div>
        <div><div class="fs-4 fw-bold"><?= number_format($total_whatsapp_messages) ?></div><div class="text-muted small">WhatsApp AI messages</div></div>
      </div>
    </div>
  </div>
  <div class="col-md-4">
    <div class="card sk-stat-card shadow-sm h-100">
      <div class="card-body d-flex align-items-center gap-3">
        <div class="sk-stat-icon bg-success bg-opacity-10 text-success"><i class="bi bi-shop-window"></i></div>
        <div><div class="fs-4 fw-bold"><?= number_format($active_shops) ?></div><div class="text-muted small">active shops</div></div>
      </div>
    </div>
  </div>
  <div class="col-md-4">
    <div class="card sk-stat-card shadow-sm h-100">
      <div class="card-body d-flex align-items-center gap-3">
        <div class="sk-stat-icon bg-warning bg-opacity-10 text-warning"><i class="bi bi-graph-up-arrow"></i></div>
        <div><div class="fs-4 fw-bold"><?= number_format($last_wa_count) ?></div><div class="text-muted small">highest shop count</div></div>
      </div>
    </div>
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
    <table class="table table-hover align-middle mb-0">
      <thead>
        <tr>
          <th>Vendor</th>
          <th>Contact</th>
          <th>Commission</th>
          <th>WhatsApp</th>
          <th>Status</th>
          <th>Joined</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($vendors)): ?>
        <tr><td colspan="7" class="text-center text-muted py-4">No vendors found.</td></tr>
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
            <?php $wa_count = (int)($v['whatsapp_ai_count'] ?? 0); ?>
            <?php if ($wa_count > 0): ?>
              <div class="fw-semibold text-success"><?= number_format($wa_count) ?></div>
              <small class="text-muted"><?php $ts = $v['last_whatsapp_at'] ?? null; echo $ts ? date('d M Y', strtotime($ts)) : 'No recent activity'; ?></small>
            <?php else: ?>
              <span class="text-muted small">0</span>
            <?php endif; ?>
          </td>
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
