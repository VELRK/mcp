<div class="sk-page-header d-flex flex-wrap align-items-center justify-content-between gap-2">
  <h5 class="sk-page-title mb-0"><i class="bi bi-megaphone me-2 text-success"></i>WhatsApp Campaigns</h5>
  <div class="d-flex gap-2">
    <a href="<?= site_url('shopkart/whatsapp/templates') ?>" class="btn btn-sm btn-outline-secondary">Templates</a>
    <a href="<?= site_url('shopkart/whatsapp/campaigns/add') ?>" class="btn btn-sm btn-success">New campaign</a>
  </div>
</div>

<?php if (empty($ready)): ?>
<div class="alert alert-warning">Meta Cloud API is not connected. You can prepare campaigns, then send after credentials are set.</div>
<?php endif; ?>

<div class="card sk-table-card shadow-sm">
  <div class="card-body p-0">
    <table class="table table-hover mb-0">
      <thead>
        <tr>
          <th>Campaign</th>
          <th>Template</th>
          <th>Status</th>
          <th>Recipients</th>
          <th>Sent</th>
          <th>Delivered</th>
          <th>Read</th>
          <th>Failed</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($campaigns)): ?>
        <tr><td colspan="9" class="text-muted p-4">No campaigns yet. Map customer details on a template, then send to customers.</td></tr>
        <?php endif; ?>
        <?php foreach (($campaigns ?? []) as $c): ?>
        <tr>
          <td>
            <strong><?= htmlspecialchars($c['name']) ?></strong>
            <div class="small text-muted"><?= htmlspecialchars($c['created_at']) ?></div>
          </td>
          <td><?= htmlspecialchars($c['template_name'] ?? '—') ?></td>
          <td>
            <?php
              $st = strtolower((string)$c['status']);
              $cls = $st === 'sent' ? 'bg-success' : ($st === 'failed' ? 'bg-danger' : ($st === 'sending' ? 'bg-primary' : 'bg-secondary'));
            ?>
            <span class="badge <?= $cls ?>"><?= htmlspecialchars($c['status']) ?></span>
          </td>
          <td><?= (int)$c['total'] ?></td>
          <td><?= (int)$c['sent'] ?></td>
          <td><?= (int)$c['delivered'] ?></td>
          <td><?= (int)($c['read_count'] ?? 0) ?></td>
          <td><?= (int)$c['failed'] ?></td>
          <td><a class="btn btn-sm btn-outline-dark" href="<?= site_url('shopkart/whatsapp/campaigns/view/'.$c['id']) ?>">Track</a></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
