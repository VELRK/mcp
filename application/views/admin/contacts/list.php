<?php
$filters = $filters ?? ['status' => '', 'source' => '', 'search' => ''];
$status_badges = [
    'new'     => 'bg-warning text-dark',
    'read'    => 'bg-secondary',
    'replied' => 'bg-info text-dark',
    'closed'  => 'bg-success',
];
?>
<div class="sk-page-header d-flex flex-wrap align-items-center justify-content-between gap-2">
  <div>
    <h5 class="sk-page-title mb-1"><i class="bi bi-envelope me-2 text-info"></i>Contact Enquiries</h5>
    <small class="text-muted">Messages from website &amp; mobile app contact form</small>
  </div>
  <span class="badge bg-info text-dark"><?= count($contacts) ?> shown<?= !empty($new_count) ? ' · '.(int)$new_count.' new' : '' ?></span>
</div>

<div id="alertBox" class="mt-2"></div>

<div class="card shadow-sm mb-3">
  <div class="card-body py-3">
    <form method="get" action="<?= site_url('shopkart/contacts') ?>" class="row g-2 align-items-end">
      <div class="col-md-4">
        <label class="form-label small mb-1">Search</label>
        <input type="text" name="search" class="form-control form-control-sm" value="<?= htmlspecialchars($filters['search'] ?? '') ?>" placeholder="Name, email, phone, subject…">
      </div>
      <div class="col-md-2">
        <label class="form-label small mb-1">Status</label>
        <select name="status" class="form-select form-select-sm">
          <option value="">All</option>
          <?php foreach (['new','read','replied','closed'] as $st): ?>
          <option value="<?= $st ?>" <?= ($filters['status'] ?? '') === $st ? 'selected' : '' ?>><?= ucfirst($st) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-2">
        <label class="form-label small mb-1">Source</label>
        <select name="source" class="form-select form-select-sm">
          <option value="">All</option>
          <option value="app" <?= ($filters['source'] ?? '') === 'app' ? 'selected' : '' ?>>App</option>
          <option value="web" <?= ($filters['source'] ?? '') === 'web' ? 'selected' : '' ?>>Web</option>
        </select>
      </div>
      <div class="col-md-3">
        <button type="submit" class="btn btn-sm btn-primary">Filter</button>
        <a href="<?= site_url('shopkart/contacts') ?>" class="btn btn-sm btn-outline-secondary">Reset</a>
      </div>
    </form>
  </div>
</div>

<div class="card sk-table-card shadow-sm">
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead class="sk-table-head">
          <tr>
            <th style="width:50px">#</th>
            <th>Name</th>
            <th>Business</th>
            <th>Contact</th>
            <th>Subject</th>
            <th>Message</th>
            <th style="width:70px">Source</th>
            <th style="width:90px">Status</th>
            <th style="width:130px">Date</th>
            <th class="text-end" style="width:120px">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($contacts as $c): ?>
          <tr id="row-<?= (int)$c['id'] ?>" class="<?= ($c['status'] ?? '') === 'new' ? 'fw-semibold table-warning' : '' ?>">
            <td class="text-muted small"><?= (int)$c['id'] ?></td>
            <td><?= htmlspecialchars($c['name'] ?? '') ?></td>
            <td class="small">
              <?= !empty($c['business_name']) ? htmlspecialchars($c['business_name']) : '<span class="text-muted">—</span>' ?>
              <?php if (!empty($c['industry'])): ?>
                <div class="text-muted"><?= htmlspecialchars($c['industry']) ?></div>
              <?php endif; ?>
            </td>
            <td class="small">
              <a href="mailto:<?= htmlspecialchars($c['email'] ?? '') ?>"><?= htmlspecialchars($c['email'] ?? '') ?></a>
              <?php if (!empty($c['phone'])): ?>
                <div class="text-muted"><?= htmlspecialchars($c['phone']) ?></div>
              <?php endif; ?>
            </td>
            <td class="small"><?= !empty($c['subject']) ? htmlspecialchars($c['subject']) : '<span class="text-muted">—</span>' ?></td>
            <td class="text-muted small" style="max-width:260px;">
              <span title="<?= htmlspecialchars($c['message'] ?? '') ?>">
                <?= htmlspecialchars(mb_substr((string)($c['message'] ?? ''), 0, 100)) ?><?= mb_strlen((string)($c['message'] ?? '')) > 100 ? '…' : '' ?>
              </span>
            </td>
            <td><span class="badge bg-light text-dark border"><?= htmlspecialchars(strtoupper($c['source'] ?? 'app')) ?></span></td>
            <td>
              <span class="badge <?= $status_badges[$c['status'] ?? 'new'] ?? 'bg-secondary' ?>" id="status-<?= (int)$c['id'] ?>">
                <?= ucfirst($c['status'] ?? 'new') ?>
              </span>
            </td>
            <td class="small text-muted"><?= !empty($c['created_at']) ? date('d M Y, h:i A', strtotime($c['created_at'])) : '—' ?></td>
            <td class="text-end text-nowrap">
              <a href="<?= site_url('shopkart/contacts/view/'.(int)$c['id']) ?>" class="btn btn-outline-primary btn-sm" title="View"><i class="bi bi-eye"></i></a>
              <?php if (($c['status'] ?? '') === 'new'): ?>
              <button type="button" class="btn btn-outline-success btn-sm" title="Mark read" onclick="markRead(<?= (int)$c['id'] ?>)"><i class="bi bi-check2"></i></button>
              <?php endif; ?>
              <button type="button" class="btn btn-outline-danger btn-sm" title="Delete" onclick="deleteContact(<?= (int)$c['id'] ?>)"><i class="bi bi-trash"></i></button>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php if (empty($contacts)): ?>
          <tr><td colspan="10" class="text-center text-muted py-4">No contact messages yet. Website Contact form and app submissions appear here.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<script>
function showAlert(msg, type='success') {
  document.getElementById('alertBox').innerHTML =
    `<div class="alert alert-${type} alert-dismissible fade show py-2 px-3">${msg}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>`;
}
function markRead(id) {
  const fd = new FormData();
  fd.append('status', 'read');
  fetch(`<?= site_url('shopkart/contacts/mark_read') ?>/${id}`, { method: 'POST', body: fd })
    .then(r => r.json())
    .then(res => {
      if (res.success) {
        const badge = document.getElementById('status-' + id);
        if (badge) { badge.className = 'badge bg-secondary'; badge.textContent = 'Read'; }
        document.getElementById('row-' + id)?.classList.remove('fw-semibold', 'table-warning');
        showAlert('Marked as read.');
      }
    });
}
function deleteContact(id) {
  if (!confirm('Delete this enquiry?')) return;
  fetch(`<?= site_url('shopkart/contacts/delete') ?>/${id}`, { method: 'POST' })
    .then(r => r.json())
    .then(res => {
      if (res.success) {
        document.getElementById('row-' + id)?.remove();
        showAlert('Enquiry deleted.');
      }
    });
}
</script>
