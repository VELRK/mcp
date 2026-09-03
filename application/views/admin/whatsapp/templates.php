<div class="sk-page-header d-flex flex-wrap align-items-center justify-content-between gap-2">
  <h5 class="sk-page-title mb-0"><i class="bi bi-file-earmark-text me-2 text-success"></i>WhatsApp Templates</h5>
  <div class="d-flex gap-2">
    <a href="<?= site_url('shopkart/whatsapp') ?>" class="btn btn-sm btn-outline-secondary">Inbox</a>
    <a href="<?= site_url('shopkart/whatsapp/campaigns') ?>" class="btn btn-sm btn-outline-success">Campaigns</a>
    <a href="<?= site_url('shopkart/whatsapp/templates/sync') ?>" class="btn btn-sm btn-outline-success">Sync from Meta</a>
    <a href="<?= site_url('shopkart/whatsapp/templates/add') ?>" class="btn btn-sm btn-success">New template</a>
  </div>
</div>

<?php if (empty($ready)): ?>
<div class="alert alert-warning">Meta Cloud API is not connected. You can still save drafts, then push after credentials are set.</div>
<?php endif; ?>

<div class="card sk-table-card shadow-sm">
  <div class="card-body p-0">
    <table class="table table-hover mb-0">
      <thead>
        <tr>
          <th>Name</th>
          <th>Type</th>
          <th>Language</th>
          <th>Category</th>
          <th>Status</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($templates)): ?>
        <tr><td colspan="6" class="text-muted p-4">No templates yet. Create text / image / video templates or sync from Meta.</td></tr>
        <?php endif; ?>
        <?php foreach ($templates as $t): ?>
        <tr>
          <td>
            <strong><?= htmlspecialchars($t['name']) ?></strong>
            <?php if (!empty($t['body_text'])): ?>
              <div class="small text-muted"><?= htmlspecialchars(mb_substr($t['body_text'], 0, 80)) ?></div>
            <?php endif; ?>
            <?php
              $vmap = function_exists('sk_wa_cloud_decode_variable_map') ? sk_wa_cloud_decode_variable_map($t['variable_map'] ?? '') : [];
              if ($vmap):
            ?>
              <div class="small text-success mt-1"><?= count($vmap) ?> customer field<?= count($vmap) === 1 ? '' : 's' ?> mapped</div>
            <?php endif; ?>
          </td>
          <td><span class="badge bg-light text-dark text-uppercase"><?= htmlspecialchars($t['kind']) ?></span></td>
          <td><?= htmlspecialchars($t['language']) ?></td>
          <td><?= htmlspecialchars($t['category']) ?></td>
          <td>
            <?php
              $st = strtoupper((string)$t['status']);
              $cls = $st === 'APPROVED' ? 'bg-success' : ($st === 'REJECTED' || $st === 'FAILED' ? 'bg-danger' : 'bg-secondary');
            ?>
            <span class="badge <?= $cls ?>"><?= htmlspecialchars($t['status']) ?></span>
          </td>
          <td class="text-nowrap">
            <a class="btn btn-sm btn-outline-dark" href="<?= site_url('shopkart/whatsapp/templates/edit/'.$t['id']) ?>">Edit</a>
            <a class="btn btn-sm btn-outline-primary" href="<?= site_url('shopkart/whatsapp/campaigns/add?template_id='.$t['id']) ?>">Campaign</a>
            <a class="btn btn-sm btn-outline-success" href="<?= site_url('shopkart/whatsapp/templates/push/'.$t['id']) ?>">Push to Meta</a>
            <a class="btn btn-sm btn-outline-danger" href="<?= site_url('shopkart/whatsapp/templates/delete/'.$t['id']) ?>" onclick="return confirm('Delete this template?')">Delete</a>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
