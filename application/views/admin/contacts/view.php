<?php $c = $contact; ?>
<div class="sk-page-header d-flex flex-wrap align-items-center justify-content-between gap-2">
  <div>
    <h5 class="sk-page-title mb-1"><i class="bi bi-envelope-open me-2 text-info"></i>Contact #<?= (int)$c['id'] ?></h5>
    <small class="text-muted"><?= !empty($c['created_at']) ? date('d M Y, h:i A', strtotime($c['created_at'])) : '' ?></small>
  </div>
  <a href="<?= site_url('shopkart/contacts') ?>" class="btn btn-outline-secondary btn-sm">← Back to list</a>
</div>

<div class="card shadow-sm" style="max-width:720px;">
  <div class="card-body">
    <div class="row g-3 mb-3">
      <div class="col-md-6">
        <div class="text-muted small">Name</div>
        <div class="fw-semibold"><?= htmlspecialchars($c['name'] ?? '') ?></div>
      </div>
      <div class="col-md-6">
        <div class="text-muted small">Status</div>
        <span class="badge bg-secondary"><?= ucfirst($c['status'] ?? 'new') ?></span>
        <span class="badge bg-light text-dark border ms-1"><?= htmlspecialchars(strtoupper($c['source'] ?? 'app')) ?></span>
      </div>
      <div class="col-md-6">
        <div class="text-muted small">Email</div>
        <a href="mailto:<?= htmlspecialchars($c['email'] ?? '') ?>"><?= htmlspecialchars($c['email'] ?? '') ?></a>
      </div>
      <div class="col-md-6">
        <div class="text-muted small">Phone</div>
        <div><?= !empty($c['phone']) ? htmlspecialchars($c['phone']) : '—' ?></div>
      </div>
      <div class="col-md-6">
        <div class="text-muted small">Business</div>
        <div><?= !empty($c['business_name']) ? htmlspecialchars($c['business_name']) : '—' ?></div>
      </div>
      <div class="col-md-6">
        <div class="text-muted small">Industry</div>
        <div><?= !empty($c['industry']) ? htmlspecialchars($c['industry']) : '—' ?></div>
      </div>
      <div class="col-12">
        <div class="text-muted small">Subject</div>
        <div><?= !empty($c['subject']) ? htmlspecialchars($c['subject']) : '—' ?></div>
      </div>
      <div class="col-12">
        <div class="text-muted small">Message</div>
        <div class="p-3 bg-light rounded" style="white-space:pre-wrap;"><?= htmlspecialchars($c['message'] ?? '') ?></div>
      </div>
    </div>
    <div class="d-flex gap-2">
      <a href="mailto:<?= htmlspecialchars($c['email'] ?? '') ?>?subject=Re:%20<?= rawurlencode($c['subject'] ?? 'Your enquiry') ?>" class="btn btn-success btn-sm">Reply by email</a>
      <button type="button" class="btn btn-outline-danger btn-sm" onclick="if(confirm('Delete?')) fetch('<?= site_url('shopkart/contacts/delete/'.(int)$c['id']) ?>',{method:'POST'}).then(()=>location.href='<?= site_url('shopkart/contacts') ?>')">Delete</button>
    </div>
  </div>
</div>
