<div class="sk-page-header">
  <h5 class="sk-page-title"><i class="bi bi-heart me-2 text-warning"></i>Wishlists</h5>
</div>

<div id="alertBox"></div>

<!-- Search -->
<form method="GET" class="d-flex gap-2 mb-3">
  <input type="text" name="search" class="form-control form-control-sm w-auto"
         placeholder="Search user or product…" value="<?= htmlspecialchars($search ?? '') ?>">
  <button type="submit" class="btn btn-sm btn-warning">Search</button>
  <?php if ($search): ?>
    <a href="<?= site_url('admin/wishlists') ?>" class="btn btn-sm btn-outline-secondary">Clear</a>
  <?php endif; ?>
</form>

<div class="card sk-table-card shadow-sm">
  <div class="card-body p-0">
    <table class="table table-hover align-middle mb-0">
      <thead class="sk-table-head">
        <tr>
          <th>#</th>
          <th>Product</th>
          <th>Customer</th>
          <th>Email</th>
          <th>Price</th>
          <th>Saved On</th>
          <th class="text-end">Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($wishlists)): ?>
        <tr><td colspan="7" class="text-center text-muted py-4">No wishlist entries found.</td></tr>
        <?php else: ?>
        <?php foreach ($wishlists as $i => $w): ?>
        <tr id="wl-row-<?= $w['id'] ?>">
          <td class="text-muted small"><?= ($page - 1) * $limit + $i + 1 ?></td>
          <td>
            <div class="d-flex align-items-center gap-2">
              <?php if ($w['thumbnail']): ?>
                <img src="<?= base_url($w['thumbnail']) ?>" width="40" height="40"
                     class="rounded" style="object-fit:cover;">
              <?php else: ?>
                <div class="bg-light rounded d-flex align-items-center justify-content-center"
                     style="width:40px;height:40px;"><i class="bi bi-box text-muted"></i></div>
              <?php endif; ?>
              <div>
                <div class="fw-semibold small"><?= htmlspecialchars($w['product_name'] ?? '—') ?></div>
                <?php if ($w['product_id']): ?>
                  <a href="<?= site_url('admin/products/edit/'.$w['product_id']) ?>"
                     class="text-muted small">Edit product</a>
                <?php endif; ?>
              </div>
            </div>
          </td>
          <td><?= htmlspecialchars($w['user_name'] ?? '—') ?></td>
          <td class="text-muted small"><?= htmlspecialchars($w['user_email'] ?? '—') ?></td>
          <td>
            <?php if ($w['sale_price']): ?>
              <span class="fw-semibold text-success"><?= sk_currency_symbol($settings) ?><?= number_format($w['sale_price'], 0) ?></span>
              <span class="text-muted text-decoration-line-through small ms-1"><?= sk_currency_symbol($settings) ?><?= number_format($w['price'], 0) ?></span>
            <?php else: ?>
              <span class="fw-semibold"><?= sk_currency_symbol($settings) ?><?= number_format($w['price'] ?? 0, 0) ?></span>
            <?php endif; ?>
          </td>
          <td class="text-muted small"><?= date('d M Y, g:i a', strtotime($w['created_at'])) ?></td>
          <td class="text-end">
            <button class="btn btn-sm btn-outline-danger"
                    onclick="delWl(<?= $w['id'] ?>)">
              <i class="bi bi-trash"></i>
            </button>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Pagination -->
<?php
$pages = ceil($total / $limit);
if ($pages > 1):
?>
<nav class="mt-3">
  <ul class="pagination pagination-sm mb-0">
    <?php for ($p = 1; $p <= $pages; $p++): ?>
    <li class="page-item <?= $p == $page ? 'active' : '' ?>">
      <a class="page-link" href="?page=<?= $p ?><?= $search ? '&search='.urlencode($search) : '' ?>">
        <?= $p ?>
      </a>
    </li>
    <?php endfor; ?>
  </ul>
</nav>
<?php endif; ?>

<script>
function showAlert(msg, type = 'success') {
  document.getElementById('alertBox').innerHTML =
    `<div class="alert alert-${type} alert-dismissible fade show mt-2">${msg}
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>`;
}
function delWl(id) {
  if (!confirm('Remove this wishlist entry?')) return;
  fetch(`<?= base_url('admin/wishlists/delete') ?>/${id}`, { method: 'POST' })
    .then(r => r.json())
    .then(res => {
      if (res.success) {
        document.getElementById('wl-row-' + id)?.remove();
        showAlert('Removed.');
      }
    });
}
</script>
