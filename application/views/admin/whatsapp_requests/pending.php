<?php
$requests = $requests ?? [];
?>
<div class="sk-page-header d-flex align-items-center justify-content-between">
  <div>
    <h5 class="sk-page-title mb-1">WhatsApp Provision Requests</h5>
    <div class="small text-muted">Pending requests from vendors</div>
  </div>
</div>

<?php if ($this->session->flashdata('error')): ?>
  <div class="alert alert-danger"><?= $this->session->flashdata('error') ?></div>
<?php endif; ?>
<?php if ($this->session->flashdata('success')): ?>
  <div class="alert alert-success"><?= $this->session->flashdata('success') ?></div>
<?php endif; ?>

<div class="card">
  <div class="card-body small">
    <?php if (empty($requests)): ?>
      <div class="text-muted">No pending requests.</div>
    <?php else: ?>
      <table class="table table-sm">
        <thead>
          <tr><th>ID</th><th>Vendor</th><th>Phone</th><th>Note</th><th>Created</th><th></th></tr>
        </thead>
        <tbody>
          <?php foreach ($requests as $r): ?>
            <tr>
              <td><?= (int)$r['id'] ?></td>
              <td>
                <a href="<?= site_url('admin/vendors/view/'.(int)$r['vendor_id']) ?>" class="fw-semibold"><?= htmlspecialchars($r['vendor_name'] ?? ('Vendor #'.(int)$r['vendor_id'])) ?></a>
                <?php if (!empty($r['vendor_email'])): ?><div class="small text-muted"><?= htmlspecialchars($r['vendor_email']) ?></div><?php endif; ?>
              </td>
              <td><?= htmlspecialchars($r['display_phone'] ?: $r['phone_number_id'] ?: '') ?></td>
              <td><?= htmlspecialchars($r['note'] ?? '') ?></td>
              <td><?= htmlspecialchars($r['created_at']) ?></td>
              <td class="text-end">
                <a href="<?= site_url('admin/whatsapp_requests/approve/'.$r['id']) ?>" class="btn btn-sm btn-success">Connect</a>
                <form method="post" action="<?= site_url('admin/whatsapp_requests/reject/'.$r['id']) ?>" style="display:inline">
                  <button class="btn btn-sm btn-danger" type="submit">Reject</button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>
</div>
