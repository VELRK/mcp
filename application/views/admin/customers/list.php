<?php
$filters = $filters ?? ['search' => $search ?? '', 'status' => '', 'date_from' => '', 'date_to' => ''];
$counts  = $counts ?? ['total' => (int)($total ?? 0), 'active' => 0, 'blocked' => 0];
$qs = http_build_query(array_filter([
    'search'    => $filters['search'] ?? '',
    'status'    => ($filters['status'] ?? '') === '' ? null : $filters['status'],
    'date_from' => $filters['date_from'] ?? '',
    'date_to'   => $filters['date_to'] ?? '',
], static function ($v) {
    return $v !== null && $v !== '';
}));
?>

<div class="sk-page-header d-flex flex-wrap align-items-center justify-content-between gap-2">
  <div>
    <h5 class="sk-page-title mb-1"><i class="bi bi-people me-2 text-warning"></i>Customers</h5>
    <small class="text-muted">
      <?= (int)$counts['total'] ?> total ·
      <?= (int)$counts['active'] ?> active ·
      <?= (int)$counts['blocked'] ?> blocked
    </small>
  </div>
  <div class="d-flex flex-wrap gap-2">
    <a href="<?= site_url('admin/customers/export?' . $qs) ?>" class="btn btn-outline-secondary btn-sm">
      <i class="bi bi-download me-1"></i> Export Excel
    </a>
    <a href="<?= site_url('admin/customers/import') ?>" class="btn btn-outline-secondary btn-sm">
      <i class="bi bi-upload me-1"></i> Import
    </a>
    <a href="<?= site_url('admin/customers/add') ?>" class="btn btn-warning btn-sm fw-semibold">
      <i class="bi bi-plus-lg me-1"></i> Add Customer
    </a>
  </div>
</div>

<div class="card sk-table-card shadow-sm mb-3">
  <div class="card-body py-3">
    <form method="get" class="row g-2 align-items-end">
      <div class="col-md-3">
        <label class="form-label small mb-1">Search</label>
        <input type="text" name="search" class="form-control form-control-sm"
               value="<?= htmlspecialchars($filters['search'] ?? '') ?>"
               placeholder="Name, email, phone...">
      </div>
      <div class="col-md-2">
        <label class="form-label small mb-1">Status</label>
        <select name="status" class="form-select form-select-sm">
          <option value="">All statuses</option>
          <option value="1" <?= (string)($filters['status'] ?? '') === '1' ? 'selected' : '' ?>>Active</option>
          <option value="0" <?= (string)($filters['status'] ?? '') === '0' ? 'selected' : '' ?>>Blocked</option>
        </select>
      </div>
      <div class="col-md-2">
        <label class="form-label small mb-1">Joined from</label>
        <input type="date" name="date_from" class="form-control form-control-sm"
               value="<?= htmlspecialchars($filters['date_from'] ?? '') ?>">
      </div>
      <div class="col-md-2">
        <label class="form-label small mb-1">Joined to</label>
        <input type="date" name="date_to" class="form-control form-control-sm"
               value="<?= htmlspecialchars($filters['date_to'] ?? '') ?>">
      </div>
      <div class="col-md-1">
        <button type="submit" class="btn btn-sm btn-dark w-100">Filter</button>
      </div>
      <div class="col-md-2">
        <a href="<?= site_url('admin/customers') ?>" class="btn btn-sm btn-outline-secondary w-100">Reset</a>
      </div>
    </form>
  </div>
</div>

<div class="card sk-table-card shadow-sm">
  <div class="card-body p-0">
    <table class="table table-hover align-middle mb-0">
      <thead>
        <tr>
          <th>Name</th>
          <th>Email</th>
          <th>Phone</th>
          <th>Joined</th>
          <th>Status</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($customers as $c): ?>
        <tr id="row-<?= (int)$c['id'] ?>">
          <td>
            <div class="d-flex align-items-center gap-2">
              <div class="rounded-circle bg-warning text-dark d-flex align-items-center justify-content-center fw-bold"
                   style="width:36px;height:36px;font-size:14px;">
                <?= strtoupper(substr($c['name'] ?: '?', 0, 1)) ?>
              </div>
              <span class="fw-semibold"><?= htmlspecialchars($c['name']) ?></span>
            </div>
          </td>
          <td><?= htmlspecialchars($c['email'] ?? '') ?></td>
          <td><?= htmlspecialchars($c['phone'] ?? '-') ?></td>
          <td><?= !empty($c['created_at']) ? date('d M Y', strtotime($c['created_at'])) : '-' ?></td>
          <td>
            <span class="badge <?= !empty($c['status']) ? 'bg-success' : 'bg-danger' ?>">
              <?= !empty($c['status']) ? 'Active' : 'Blocked' ?>
            </span>
          </td>
          <td class="d-flex gap-1 flex-wrap">
            <a href="<?= site_url('admin/customers/view/'.$c['id']) ?>" class="btn btn-sm btn-outline-primary" title="View">
              <i class="bi bi-eye"></i>
            </a>
            <a href="<?= site_url('admin/customers/edit/'.$c['id']) ?>" class="btn btn-sm btn-outline-warning" title="Edit">
              <i class="bi bi-pencil"></i>
            </a>
            <button onclick="skToggleStatus('<?= site_url('admin/customers/toggle/'.$c['id']) ?>')"
                    class="btn btn-sm <?= !empty($c['status']) ? 'btn-outline-danger' : 'btn-outline-success' ?>"
                    title="<?= !empty($c['status']) ? 'Block' : 'Activate' ?>">
              <i class="bi bi-<?= !empty($c['status']) ? 'slash-circle' : 'check-circle' ?>"></i>
            </button>
            <button type="button"
                    class="btn btn-sm btn-outline-danger"
                    title="Delete permanently"
                    onclick="skConfirmDelete('<?= site_url('admin/customers/delete/'.$c['id']) ?>', 'row-<?= (int)$c['id'] ?>')">
              <i class="bi bi-trash"></i>
            </button>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($customers)): ?>
        <tr><td colspan="6" class="text-center py-5 text-muted">No customers found.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
  <?php
    $pages = $limit > 0 ? (int)ceil($total / $limit) : 1;
    $from = $total ? (($page - 1) * $limit) + 1 : 0;
    $to = min($page * $limit, $total);
  ?>
  <div class="card-footer bg-white d-flex flex-wrap justify-content-between align-items-center gap-2">
    <small class="text-muted">
      Showing <?= (int)$from ?>–<?= (int)$to ?> of <?= (int)$total ?> · 100 per page
    </small>
    <?php if ($pages > 1): ?>
    <nav>
      <ul class="pagination pagination-sm mb-0 flex-wrap">
        <?php
          $start = max(1, $page - 3);
          $end = min($pages, $page + 3);
          if ($page > 1):
        ?>
          <li class="page-item">
            <a class="page-link" href="?page=<?= $page - 1 ?>&<?= $qs ?>">Prev</a>
          </li>
        <?php endif; ?>
        <?php for ($i = $start; $i <= $end; $i++): ?>
          <li class="page-item <?= $i === $page ? 'active' : '' ?>">
            <a class="page-link" href="?page=<?= $i ?>&<?= $qs ?>"><?= $i ?></a>
          </li>
        <?php endfor; ?>
        <?php if ($page < $pages): ?>
          <li class="page-item">
            <a class="page-link" href="?page=<?= $page + 1 ?>&<?= $qs ?>">Next</a>
          </li>
        <?php endif; ?>
      </ul>
    </nav>
    <?php endif; ?>
  </div>
</div>
