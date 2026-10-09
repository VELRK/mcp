<div class="sk-page-header d-flex flex-wrap align-items-center justify-content-between gap-2">
  <div>
    <h5 class="sk-page-title mb-0">Order templates</h5>
    <div class="small text-muted">The same order created, updated, cancelled, and delivered messages for every shop. Sent with the Meta API.</div>
  </div>
  <a class="btn btn-sm btn-outline-secondary" href="<?= site_url('admin/whatsapp/templates') ?><?= !empty($vendor_id) ? '?vendor_id='.(int)$vendor_id : '' ?>">Other templates</a>
</div>

<?php if (empty($vendor_id)): ?>
<div class="card sk-table-card shadow-sm">
  <div class="card-body">
    <p class="mb-3">Choose a shop. Each vendor gets these four templates when WhatsApp is connected.</p>
    <div class="list-group">
      <?php foreach ($vendors as $v): ?>
        <a class="list-group-item list-group-item-action" href="<?= site_url('admin/whatsapp/order-templates?vendor_id='.(int)$v['id']) ?>">
          <?= htmlspecialchars($v['store_name'] ?: ($v['business_name'] ?? ('Vendor #'.$v['id']))) ?>
        </a>
      <?php endforeach; ?>
      <?php if (empty($vendors)): ?>
        <div class="text-muted">No shops found.</div>
      <?php endif; ?>
    </div>
  </div>
</div>
<?php else: ?>
<?php if (empty($ready)): ?>
<div class="alert alert-warning">Connect this shop's WhatsApp number first. Templates are saved now and submitted to Meta as soon as the number is active.</div>
<?php endif; ?>
<div class="card sk-table-card shadow-sm">
  <div class="card-body p-0">
    <table class="table table-hover mb-0">
      <thead>
        <tr>
          <th>Event</th>
          <th>Template</th>
          <th>Message</th>
          <th>Meta status</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php
          $byEvent = [];
          foreach ($templates as $t) {
              $byEvent[(string)$t['event_key']] = $t;
          }
          $labels = [
              'order_created'   => 'Order created',
              'order_updated'   => 'Order updated',
              'order_cancelled' => 'Order cancelled',
              'order_delivered' => 'Order delivered',
          ];
        ?>
        <?php foreach ($defs as $def):
            $t = $byEvent[$def['event_key']] ?? null;
            $st = strtoupper((string)($t['status'] ?? 'MISSING'));
            $cls = $st === 'APPROVED' ? 'bg-success' : ($st === 'REJECTED' || $st === 'FAILED' ? 'bg-danger' : 'bg-secondary');
        ?>
        <tr>
          <td class="fw-semibold"><?= htmlspecialchars($labels[$def['event_key']] ?? $def['event_key']) ?></td>
          <td><code><?= htmlspecialchars($def['name']) ?></code></td>
          <td class="small text-muted"><?= htmlspecialchars(mb_substr((string)($t['body_text'] ?? $def['body']), 0, 110)) ?></td>
          <td><span class="badge <?= $cls ?>"><?= htmlspecialchars($st) ?></span></td>
          <td class="text-nowrap">
            <?php if ($t): ?>
            <a class="btn btn-sm btn-outline-dark" href="<?= site_url('shopkart/whatsapp/templates/edit/'.$t['id']) ?>?vendor_id=<?= (int)$vendor_id ?>">Edit</a>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>
