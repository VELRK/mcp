<?php
$currency = sk_currency_symbol($settings);
$show_vendor_col = !empty($show_vendor_col);
$colspan = $show_vendor_col ? 10 : 9;
$statusBadge = static function (string $status): string {
    if ($status === 'active') return 'badge-label-success';
    if ($status === 'draft') return 'badge-label-warning';
    return 'badge-label-secondary';
};
?>
<?php foreach ($products as $p):
  $price = (float) ($p['price'] ?? 0);
  $sale = (float) ($p['sale_price'] ?? 0);
  $discount = ($sale > 0 && $price > $sale) ? (int) round((1 - ($sale / $price)) * 100) : 0;
  $stock = (int) ($p['stock'] ?? 0);
?>
<tr>
  <td><span class="text-muted">#PRD-<?= str_pad((string) $p['id'], 4, '0', STR_PAD_LEFT) ?></span></td>
  <td>
    <a href="<?= site_url('shopkart/products/edit/'.$p['id']) ?>" class="d-flex align-items-center gap-2 text-body">
      <?php if (!empty($p['thumbnail'])): ?>
        <span class="avatar avatar-sm avatar-border">
          <img src="<?= base_url($p['thumbnail']) ?>?v=<?= (int) @filemtime(FCPATH . $p['thumbnail']) ?>" alt="" class="rounded" style="width:36px;height:36px;object-fit:cover;">
        </span>
      <?php else: ?>
        <span class="avatar avatar-sm bg-light text-muted d-flex align-items-center justify-content-center"><i class="mdi mdi-image-outline"></i></span>
      <?php endif; ?>
      <span>
        <p class="fw-semibold mb-0"><?= htmlspecialchars($p['name']) ?></p>
        <?php if (!empty($p['brand_name'])): ?>
          <small class="text-muted"><strong class="text-body">by</strong> <?= htmlspecialchars($p['brand_name']) ?></small>
        <?php endif; ?>
      </span>
    </a>
  </td>
  <?php if ($show_vendor_col): ?>
  <td><small><?= htmlspecialchars($p['vendor_name'] ?? '—') ?></small></td>
  <?php endif; ?>
  <td>
    <span class="badge badge-light"><?= htmlspecialchars($p['category_name'] ?? '-') ?></span>
    <?php if (!empty($p['subcategory_name'])): ?>
      <small class="d-block text-muted"><?= htmlspecialchars($p['subcategory_name']) ?></small>
    <?php endif; ?>
  </td>
  <td><?= htmlspecialchars($p['sku'] ?: '—') ?></td>
  <td>
    <?php if (!empty($p['variants'])): ?>
      <?php $vr = $p['variants'][0]; ?>
      <?= $currency . number_format((float) (!empty($vr['sale_price']) ? $vr['sale_price'] : $vr['price']), 2) ?>
      <?php if (count($p['variants']) > 1): ?><small class="text-muted d-block">+<?= count($p['variants']) - 1 ?> variants</small><?php endif; ?>
    <?php elseif ($sale > 0): ?>
      <span class="fw-semibold"><?= $currency . number_format($sale, 2) ?></span>
      <del class="text-muted small d-block"><?= $currency . number_format($price, 2) ?></del>
    <?php else: ?>
      <?= $currency . number_format($price, 2) ?>
    <?php endif; ?>
  </td>
  <td><?= $discount > 0 ? '<span class="text-danger">-'.$discount.'%</span>' : '<span class="text-muted">—</span>' ?></td>
  <td>
    <?php if ($stock <= 5): ?>
      <span class="badge badge-label-danger"><?= $stock ?> Low</span>
    <?php else: ?>
      <?= number_format($stock) ?>
    <?php endif; ?>
  </td>
  <td>
    <button onclick="skToggleStatus('<?= site_url('shopkart/products/toggle/'.$p['id']) ?>', this)"
            class="btn btn-sm <?= $statusBadge((string) $p['status']) ?>">
      <?= ucfirst($p['status']) ?>
    </button>
  </td>
  <td class="text-end">
    <?php if (!empty($p['payment_link'])): ?>
      <a href="<?= htmlspecialchars($p['payment_link']) ?>" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-label-success btn-icon" title="Payment link" aria-label="Payment link">
        <i class="mdi mdi-credit-card-outline"></i>
      </a>
    <?php endif; ?>
    <a href="<?= site_url('shopkart/products/edit/'.$p['id']) ?>" class="btn btn-sm btn-label-primary btn-icon" aria-label="Edit">
      <i class="mdi mdi-pencil-outline"></i>
    </a>
    <button onclick="skConfirmDelete('<?= site_url('shopkart/products/delete/'.$p['id']) ?>','<?= htmlspecialchars($p['name'], ENT_QUOTES) ?>')"
            class="btn btn-sm btn-label-danger btn-icon" aria-label="Delete">
      <i class="mdi mdi-trash-can-outline"></i>
    </button>
  </td>
</tr>
<?php endforeach; ?>
<?php if (empty($products)): ?>
<tr><td colspan="<?= $colspan ?>" class="text-center py-5 text-muted">No products found.</td></tr>
<?php endif; ?>
