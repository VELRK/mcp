<div class="sk-page-header">
  <h5 class="sk-page-title"><i class="bi bi-ticket-perforated me-2 text-warning"></i>Promo Codes</h5>
  <button class="btn btn-warning btn-sm fw-semibold" data-bs-toggle="modal" data-bs-target="#promoModal">
    <i class="bi bi-plus-lg me-1"></i> Add Promo Code
  </button>
</div>

<div class="card sk-table-card shadow-sm">
  <div class="card-body p-0">
    <table class="table table-hover align-middle mb-0 sk-datatable">
      <thead>
        <tr><th>Code</th><th>Type</th><th>Value</th><th>Min Order</th><th>Usage</th><th>Expires</th><th>Status</th><th></th></tr>
      </thead>
      <tbody>
        <?php foreach ($promos as $p): ?>
        <tr>
          <td>
            <span class="badge bg-dark fs-6 font-monospace"><?= htmlspecialchars($p['code']) ?></span>
            <?php if ($p['description']): ?>
              <small class="text-muted d-block"><?= htmlspecialchars($p['description']) ?></small>
            <?php endif; ?>
          </td>
          <td><?= ucfirst($p['type']) ?></td>
          <td>
            <?= $p['type']==='percent' ? $p['value'].'%' : sk_currency_symbol($settings).number_format($p['value'],2) ?>
            <?php if ($p['max_discount']): ?>
              <small class="text-muted d-block">Max <?= sk_currency_symbol($settings) ?><?= number_format($p['max_discount'],2) ?></small>
            <?php endif; ?>
          </td>
          <td><?= sk_currency_symbol($settings) ?><?= number_format($p['min_order'],2) ?></td>
          <td>
            <?= $p['usage_count'] ?><?= $p['usage_limit'] ? '/' . $p['usage_limit'] : '' ?>
          </td>
          <td><?= $p['expires_at'] ? date('d M Y', strtotime($p['expires_at'])) : '<span class="text-muted">Never</span>' ?></td>
          <td>
            <span class="badge <?= $p['status'] ? 'bg-success' : 'bg-secondary' ?>">
              <?= $p['status'] ? 'Active' : 'Inactive' ?>
            </span>
          </td>
          <td class="d-flex gap-1">
            <button class="btn btn-sm btn-outline-primary" onclick='editPromo(<?= json_encode($p) ?>)'>
              <i class="bi bi-pencil"></i>
            </button>
            <button class="btn btn-sm btn-outline-danger"
                    onclick="skConfirmDelete('<?= site_url('admin/promo/delete/'.$p['id']) ?>','<?= htmlspecialchars($p['code']) ?>')">
              <i class="bi bi-trash"></i>
            </button>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Promo Modal -->
<div class="modal fade" id="promoModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <form class="modal-content sk-ajax-form" id="promoForm" action="<?= site_url('admin/promo/store') ?>" method="POST">
      <div class="modal-header">
        <h5 class="modal-title fw-bold" id="promoModalTitle">Add Promo Code</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" name="id" id="promoId">
        <div class="row g-2">
          <div class="col-md-6">
            <label class="form-label">Code <span class="text-danger">*</span></label>
            <input type="text" name="code" id="promoCode" class="form-control text-uppercase" required placeholder="SAVE20">
          </div>
          <div class="col-md-6">
            <label class="form-label">Description</label>
            <input type="text" name="description" id="promoDesc" class="form-control">
          </div>
          <div class="col-md-4">
            <label class="form-label">Discount Type</label>
            <select name="type" id="promoType" class="form-select">
              <option value="percent">Percentage (%)</option>
              <option value="fixed">Fixed Amount (<?= htmlspecialchars(sk_currency_symbol($settings)) ?>)</option>
            </select>
          </div>
          <div class="col-md-4">
            <label class="form-label">Value</label>
            <input type="number" name="value" id="promoValue" class="form-control" step="0.01" min="0" required>
          </div>
          <div class="col-md-4">
            <label class="form-label">Max Discount (<?= htmlspecialchars(sk_currency_symbol($settings)) ?>)</label>
            <input type="number" name="max_discount" id="promoMaxDisc" class="form-control" step="0.01">
          </div>
          <div class="col-md-4">
            <label class="form-label">Min Order (<?= htmlspecialchars(sk_currency_symbol($settings)) ?>)</label>
            <input type="number" name="min_order" id="promoMinOrder" class="form-control" value="0" min="0">
          </div>
          <div class="col-md-4">
            <label class="form-label">Usage Limit</label>
            <input type="number" name="usage_limit" id="promoLimit" class="form-control" placeholder="Unlimited">
          </div>
          <div class="col-md-4">
            <label class="form-label">Per User Limit</label>
            <input type="number" name="per_user_limit" id="promoPerUser" class="form-control" value="1" min="1">
          </div>
          <div class="col-md-4">
            <label class="form-label">Start Date</label>
            <input type="date" name="starts_at" id="promoStart" class="form-control">
          </div>
          <div class="col-md-4">
            <label class="form-label">Expiry Date</label>
            <input type="date" name="expires_at" id="promoExpiry" class="form-control">
          </div>
          <div class="col-md-4">
            <label class="form-label">Status</label>
            <select name="status" id="promoStatus" class="form-select">
              <option value="1">Active</option><option value="0">Inactive</option>
            </select>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" class="btn btn-warning fw-semibold">Save</button>
      </div>
    </form>
  </div>
</div>

<script>
const promoStoreUrl  = '<?= site_url("admin/promo/store") ?>';
const promoUpdateBase = '<?= site_url("admin/promo/update") ?>/';

document.getElementById('promoModal').addEventListener('hidden.bs.modal', function() {
  document.getElementById('promoForm').action = promoStoreUrl;
  document.getElementById('promoModalTitle').textContent = 'Add Promo Code';
  document.getElementById('promoForm').reset();
});

function editPromo(p) {
  document.getElementById('promoModalTitle').textContent = 'Edit Promo Code';
  document.getElementById('promoForm').action = promoUpdateBase + p.id;
  document.getElementById('promoId').value = p.id;
  document.getElementById('promoCode').value = p.code;
  document.getElementById('promoDesc').value = p.description || '';
  document.getElementById('promoType').value = p.type;
  document.getElementById('promoValue').value = p.value;
  document.getElementById('promoMaxDisc').value = p.max_discount || '';
  document.getElementById('promoMinOrder').value = p.min_order || 0;
  document.getElementById('promoLimit').value = p.usage_limit || '';
  document.getElementById('promoPerUser').value = p.per_user_limit || 1;
  document.getElementById('promoStart').value = p.starts_at || '';
  document.getElementById('promoExpiry').value = p.expires_at || '';
  document.getElementById('promoStatus').value = p.status;
  new bootstrap.Modal(document.getElementById('promoModal')).show();
}
</script>
