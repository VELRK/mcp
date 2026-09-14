<?php
$requests = $requests ?? [];
?>
<div class="sk-page-header d-flex align-items-center justify-content-between">
  <div>
    <h5 class="sk-page-title mb-1">Request WhatsApp Number</h5>
    <div class="small text-muted">Submit a request for the marketplace admins to connect your WhatsApp number.</div>
  </div>
  <div>
    <a href="<?= site_url('shopkart/stores/edit/'.intval($vendor_context->vendor_id())) ?>" class="btn btn-link">Back to store</a>
  </div>
</div>

<?php if ($this->session->flashdata('error')): ?>
  <div class="alert alert-danger"><?= $this->session->flashdata('error') ?></div>
<?php endif; ?>
<?php if ($this->session->flashdata('success')): ?>
  <div class="alert alert-success"><?= $this->session->flashdata('success') ?></div>
<?php endif; ?>

<div class="card mb-3">
  <div class="card-body">
    <form method="post" action="<?= site_url('admin/whatsapp_requests/submit') ?>">
      <div class="row g-2">
        <div class="col-md-4">
          <label class="form-label small">Phone Number ID</label>
          <input name="phone_number_id" class="form-control form-control-sm font-monospace" placeholder="Optional if unknown">
        </div>
        <div class="col-md-3">
          <label class="form-label small">Display Phone</label>
          <input name="display_phone" class="form-control form-control-sm" placeholder="e.g. +1 234 567 8900">
        </div>
        <div class="col-md-3">
          <label class="form-label small">WABA ID</label>
          <input name="waba_id" class="form-control form-control-sm font-monospace" placeholder="Optional">
        </div>
        <div class="col-md-2">
          <label class="form-label small">Business ID</label>
          <input name="business_id" class="form-control form-control-sm font-monospace" placeholder="Optional">
        </div>
        <div class="col-12">
          <label class="form-label small">Note (optional)</label>
          <input name="note" class="form-control form-control-sm" placeholder="Any notes for admin">
        </div>
        <div class="col-12 text-end">
          <button class="btn btn-primary btn-sm">Submit request</button>
        </div>
      </div>
    </form>
  </div>
</div>

<div class="card">
  <div class="card-header">Your requests</div>
  <div class="card-body small">
    <?php if (empty($requests)): ?>
      <div class="text-muted">No requests yet.</div>
    <?php else: ?>
      <ul class="list-group">
        <?php foreach ($requests as $r): ?>
          <li class="list-group-item d-flex justify-content-between align-items-center">
            <div>
              <strong><?= htmlspecialchars($r['display_phone'] ?: $r['phone_number_id'] ?: '—') ?></strong>
              <div class="small text-muted">Status: <?= htmlspecialchars($r['status']) ?> • <?= htmlspecialchars($r['created_at']) ?></div>
            </div>
            <div class="small text-muted"><?php if ($r['admin_note']): ?>Admin: <?= htmlspecialchars($r['admin_note']) ?><?php endif; ?></div>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </div>
</div>
