<?php $currency = sk_currency_symbol($settings); ?>

<div class="sk-page-header">
  <h5 class="sk-page-title"><i class="bi bi-person me-2 text-warning"></i><?= htmlspecialchars($customer['name']) ?></h5>
  <div class="d-flex gap-2">
    <a href="<?= site_url('admin/customers/edit/'.$customer['id']) ?>" class="btn btn-sm btn-warning">
      <i class="bi bi-pencil me-1"></i> Edit
    </a>
    <button type="button" class="btn btn-sm btn-outline-danger"
            onclick="(function(){
              var name = <?= json_encode($customer['name']) ?>;
              var url = <?= json_encode(site_url('admin/customers/delete/'.(int)$customer['id'])) ?>;
              var listUrl = <?= json_encode(site_url('admin/customers')) ?>;
              if (!confirm('Permanently delete \"' + name + '\"?\n\n• Account, addresses, cart and reviews will be removed.\n• Order history is KEPT for reports/invoices (customer link removed).\n\nThis cannot be undone.')) return;
              $.ajax({
                url: url,
                method: 'POST',
                dataType: 'json',
                success: function(res) {
                  if (res && res.success) { location.href = listUrl; }
                  else { alert((res && res.message) ? res.message : 'Delete failed.'); }
                },
                error: function(xhr) {
                  var msg = 'Delete failed.';
                  try {
                    var body = xhr.responseJSON || JSON.parse(xhr.responseText || '{}');
                    if (body && body.message) msg = body.message;
                  } catch (e) {}
                  alert(msg);
                }
              });
            })()">
      <i class="bi bi-trash me-1"></i> Delete
    </button>
    <a href="<?= site_url('admin/customers') ?>" class="btn btn-sm btn-outline-secondary">
      <i class="bi bi-arrow-left me-1"></i> Back
    </a>
  </div>
</div>

<div class="row g-3">
  <div class="col-lg-4">
    <div class="card sk-table-card shadow-sm mb-3">
      <div class="card-body text-center py-4">
        <div class="rounded-circle bg-warning text-dark d-inline-flex align-items-center justify-content-center fw-bold mb-3"
             style="width:72px;height:72px;font-size:28px;">
          <?= strtoupper(substr($customer['name'] ?: '?', 0, 1)) ?>
        </div>
        <h6 class="fw-bold mb-0"><?= htmlspecialchars($customer['name']) ?></h6>
        <p class="text-muted small mb-3"><?= htmlspecialchars($customer['email'] ?? '') ?></p>
        <p class="mb-1"><i class="bi bi-phone me-1"></i><?= htmlspecialchars($customer['phone'] ?? 'N/A') ?></p>
        <p class="mb-1"><i class="bi bi-calendar me-1"></i>Joined <?= !empty($customer['created_at']) ? date('d M Y', strtotime($customer['created_at'])) : '-' ?></p>
        <?php if (!empty($customer['last_login'])): ?>
        <p class="mb-1"><i class="bi bi-clock-history me-1"></i>Last login <?= date('d M Y H:i', strtotime($customer['last_login'])) ?></p>
        <?php endif; ?>
        <span class="badge <?= !empty($customer['status']) ? 'bg-success' : 'bg-danger' ?> mt-2">
          <?= !empty($customer['status']) ? 'Active' : 'Blocked' ?>
        </span>
      </div>
    </div>

    <div class="card sk-table-card shadow-sm mb-3">
      <div class="card-header bg-white border-0 py-3 fw-semibold">Addresses</div>
      <div class="card-body p-0">
        <?php if (empty($addresses)): ?>
          <p class="text-muted small text-center py-4 mb-0">No saved addresses.</p>
        <?php else: ?>
          <ul class="list-group list-group-flush">
            <?php foreach ($addresses as $a): ?>
            <li class="list-group-item">
              <div class="d-flex justify-content-between align-items-start gap-2">
                <div>
                  <div class="fw-semibold"><?= htmlspecialchars($a['full_name'] ?? '') ?></div>
                  <div class="small text-muted">
                    <?= htmlspecialchars($a['line1'] ?? '') ?>
                    <?php if (!empty($a['line2'])): ?>, <?= htmlspecialchars($a['line2']) ?><?php endif; ?>
                    <br>
                    <?= htmlspecialchars(trim(($a['city'] ?? '') . ', ' . ($a['state'] ?? '') . ' ' . ($a['pincode'] ?? ''))) ?>
                    <br><?= htmlspecialchars($a['country'] ?? '') ?>
                    <?php if (!empty($a['phone'])): ?><br><?= htmlspecialchars($a['phone']) ?><?php endif; ?>
                  </div>
                </div>
                <div class="text-end">
                  <span class="badge bg-light text-dark"><?= htmlspecialchars(ucfirst($a['address_type'] ?? 'shipping')) ?></span>
                  <?php if (!empty($a['is_default'])): ?>
                    <span class="badge bg-warning text-dark">Default</span>
                  <?php endif; ?>
                </div>
              </div>
            </li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <div class="col-lg-8">
    <div class="card sk-table-card shadow-sm">
      <div class="card-header bg-white border-0 py-3 d-flex justify-content-between align-items-center">
        <span class="fw-semibold">Order History</span>
        <span class="badge bg-secondary"><?= count($orders) ?> orders</span>
      </div>
      <div class="card-body p-0">
        <table class="table table-hover mb-0">
          <thead><tr><th>Order #</th><th>Total</th><th>Status</th><th>Date</th></tr></thead>
          <tbody>
            <?php foreach ($orders as $o): ?>
            <tr>
              <td><a href="<?= site_url('admin/orders/view/'.$o['id']) ?>" class="fw-semibold text-decoration-none">
                <?= htmlspecialchars($o['order_number']) ?>
              </a></td>
              <td><?= $currency . number_format($o['total'], 2) ?></td>
              <td><span class="badge badge-<?= $o['status'] ?>"><?= ucfirst($o['status']) ?></span></td>
              <td><?= date('d M Y', strtotime($o['created_at'])) ?></td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($orders)): ?>
            <tr><td colspan="4" class="text-center text-muted py-4">No orders yet.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
