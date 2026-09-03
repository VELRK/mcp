<?php
$currency = sk_currency_symbol($settings);
$show_vendor_col = !empty($show_vendor_col);
$colspan = $show_vendor_col ? 9 : 8;
?>
<?php foreach ($products as $p): ?>
<tr>
  <td>
    <?php if ($p['thumbnail']): ?>
      <img src="<?= base_url($p['thumbnail']) ?>?v=<?= (int)@filemtime(FCPATH . $p['thumbnail']) ?>" class="rounded" width="48" height="48" style="object-fit:cover;">
    <?php else: ?>
      <div class="bg-light rounded d-flex align-items-center justify-content-center" style="width:48px;height:48px;">
        <i class="bi bi-image text-muted"></i>
      </div>
    <?php endif; ?>
  </td>
  <td>
    <div class="fw-semibold"><?= htmlspecialchars($p['name']) ?></div>
    <small class="text-muted"><?= $p['sku'] ? 'SKU: ' . htmlspecialchars($p['sku']) : '' ?></small>
    <?php if ($p['saree_type'] ?? null): ?>
      <span class="badge bg-warning text-dark ms-1 small"><?= htmlspecialchars($p['saree_type']) ?></span>
    <?php endif; ?>
    <?php if ($p['fabric'] ?? null): ?>
      <span class="badge bg-light text-secondary border small"><?= htmlspecialchars($p['fabric']) ?></span>
    <?php endif; ?>
  </td>
  <?php if ($show_vendor_col): ?>
  <td><small><?= htmlspecialchars($p['vendor_name'] ?? '—') ?></small></td>
  <?php endif; ?>
  <td><?= htmlspecialchars($p['category_name'] ?? '-') ?></td>
  <td><?= htmlspecialchars($p['subcategory_name'] ?? '—') ?></td>
  <td>
    <?php if (!empty($p['variants'])): ?>
      <?php foreach ($p['variants'] as $vi => $vr): ?>
        <div class="<?= $vi > 0 ? 'mt-1' : '' ?>" style="white-space:nowrap;">
          <small class="text-muted"><?= htmlspecialchars($vr['unit_value'] . ($vr['unit_symbol'] ?? '')) ?>:</small>
          <?php if (!empty($vr['sale_price'])): ?>
            <span class="text-success fw-semibold"><?= $currency . number_format($vr['sale_price'],2) ?></span>
            <del class="text-muted small"><?= $currency . number_format($vr['price'],2) ?></del>
          <?php else: ?>
            <span><?= $currency . number_format($vr['price'],2) ?></span>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    <?php elseif ($p['sale_price']): ?>
      <span class="text-success fw-semibold"><?= $currency . number_format($p['sale_price'],2) ?></span>
      <del class="text-muted small ms-1"><?= $currency . number_format($p['price'],2) ?></del>
    <?php else: ?>
      <?= $currency . number_format($p['price'],2) ?>
    <?php endif; ?>
  </td>
  <td>
    <?php if (!empty($p['variants'])): ?>
      <?php foreach ($p['variants'] as $vi => $vr): ?>
        <?php $vStock = (int)($vr['stock'] ?? 0); ?>
        <div class="<?= $vi > 0 ? 'mt-1' : '' ?>" style="white-space:nowrap;">
          <small class="text-muted"><?= htmlspecialchars($vr['unit_value'] . ($vr['unit_symbol'] ?? '')) ?>:</small>
          <?php if ($vStock <= 5): ?>
            <span class="badge bg-danger"><?= $vStock ?> Low</span>
          <?php else: ?>
            <span><?= number_format($vStock) ?></span>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    <?php elseif ($p['stock'] <= 5): ?>
      <span class="badge bg-danger"><?= (int)$p['stock'] ?> Low</span>
    <?php else: ?>
      <?= number_format((int)$p['stock']) ?>
    <?php endif; ?>
  </td>
  <td>
    <button onclick="skToggleStatus('<?= site_url('shopkart/products/toggle/'.$p['id']) ?>', this)"
            class="btn btn-sm <?= $p['status']==='active' ? 'btn-success' : 'btn-secondary' ?>">
      <?= ucfirst($p['status']) ?>
    </button>
  </td>
  <td>
    <a href="<?= site_url('shopkart/products/edit/'.$p['id']) ?>" class="btn btn-sm btn-outline-primary me-1">
      <i class="bi bi-pencil"></i>
    </a>
    <button onclick="skConfirmDelete('<?= site_url('shopkart/products/delete/'.$p['id']) ?>','<?= htmlspecialchars($p['name']) ?>')"
            class="btn btn-sm btn-outline-danger">
      <i class="bi bi-trash"></i>
    </button>
  </td>
</tr>
<?php endforeach; ?>
<?php if (empty($products)): ?>
<tr><td colspan="<?= $colspan ?>" class="text-center py-5 text-muted">No products found.</td></tr>
<?php endif; ?>
