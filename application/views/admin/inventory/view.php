<?php
/** @var array $product */ /** @var array $order_rows */ /** @var int $order_total */ /** @var int $page */ /** @var int $limit */
$currency = sk_currency_symbol($settings);
$alert = (int)($product['low_stock_alert'] ?? 5);
$variants = $product['variants'] ?? [];
$orderPages = max(1, (int)ceil($order_total / $limit));
?>
<div class="sk-page-header d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
  <div>
    <h5 class="sk-page-title mb-0">
      <i class="bi bi-boxes text-warning me-2"></i><?= htmlspecialchars($product['name'] ?? '') ?>
    </h5>
    <div class="small text-muted mt-1">Stock detail and orders that used this inventory</div>
  </div>
  <div class="d-flex gap-2">
    <a href="<?= site_url('shopkart/inventory') ?>" class="btn btn-sm btn-outline-secondary">
      <i class="bi bi-arrow-left me-1"></i> All inventory
    </a>
    <a href="<?= site_url('shopkart/products/edit/'.$product['id']) ?>" class="btn btn-sm btn-outline-primary">
      <i class="bi bi-pencil me-1"></i> Edit product
    </a>
  </div>
</div>

<div class="row g-3">
  <div class="col-lg-4">
    <div class="card shadow-sm mb-3">
      <div class="card-header bg-white fw-semibold">Stock summary</div>
      <div class="card-body small">
        <div class="mb-2"><span class="text-muted">SKU:</span> <?= htmlspecialchars($product['sku'] ?? '—') ?></div>
        <div class="mb-2"><span class="text-muted">Category:</span> <?= htmlspecialchars($product['category_name'] ?? '—') ?></div>
        <div class="mb-2">
          <span class="text-muted">Product stock (total):</span>
          <strong id="invProductStock"><?= number_format((int)$product['stock']) ?></strong>
          <?php if ((int)$product['stock'] <= 0): ?>
            <span class="badge bg-dark ms-1">Out of stock</span>
          <?php elseif ((int)$product['stock'] <= $alert): ?>
            <span class="badge bg-danger ms-1">Low</span>
          <?php endif; ?>
        </div>
        <div class="mb-2"><span class="text-muted">Low stock alert:</span> <?= $alert ?></div>
        <div class="mb-2"><span class="text-muted">Total sold:</span> <?= number_format((int)($product['total_sold'] ?? 0)) ?></div>
        <div class="mb-0"><span class="text-muted">Status:</span> <?= ucfirst($product['status'] ?? '') ?></div>
      </div>
    </div>

    <?php if (!empty($variants)): ?>
    <div class="card shadow-sm">
      <div class="card-header bg-white fw-semibold">Variant stock (edit inline)</div>
      <div class="table-responsive">
        <table class="table table-sm mb-0 align-middle">
          <thead>
            <tr>
              <th>Variant</th>
              <th>SKU</th>
              <th style="width:140px;">Stock</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($variants as $v): ?>
            <tr>
              <td><?= htmlspecialchars($v['label'] ?? '—') ?></td>
              <td class="small text-muted"><?= htmlspecialchars($v['sku'] ?? '—') ?></td>
              <td>
                <div class="d-flex align-items-center gap-1">
                  <input type="number" min="0" class="form-control form-control-sm inv-stock-input"
                    style="width:80px;"
                    data-product-id="<?= (int)$product['id'] ?>"
                    data-variant-id="<?= (int)$v['id'] ?>"
                    value="<?= (int)$v['stock'] ?>">
                  <button type="button" class="btn btn-sm btn-outline-success inv-stock-save"><i class="bi bi-check-lg"></i></button>
                </div>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <div class="card-footer bg-white small text-muted">Total updates automatically as sum of pack stocks.</div>
    </div>
    <?php else: ?>
    <div class="card shadow-sm">
      <div class="card-header bg-white fw-semibold">Set stock</div>
      <div class="card-body">
        <div class="d-flex align-items-center gap-2">
          <input type="number" min="0" class="form-control form-control-sm inv-stock-input"
            style="width:100px;"
            data-product-id="<?= (int)$product['id'] ?>"
            data-variant-id="0"
            value="<?= (int)$product['stock'] ?>">
          <button type="button" class="btn btn-sm btn-warning inv-stock-save">Save stock</button>
        </div>
        <div class="small text-success inv-stock-msg d-none mt-2"></div>
      </div>
    </div>
    <?php endif; ?>
  </div>

  <div class="col-lg-8">
    <div class="card shadow-sm">
      <div class="card-header bg-white fw-semibold d-flex justify-content-between align-items-center">
        <span>Orders using this product</span>
        <span class="badge bg-secondary"><?= number_format($order_total) ?></span>
      </div>
      <div class="table-responsive">
        <table class="table table-hover mb-0 align-middle">
          <thead class="table-light">
            <tr>
              <th>Order</th>
              <th>Date</th>
              <th>Status</th>
              <th>Variant</th>
              <th>Qty</th>
              <th>Subtotal</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($order_rows as $row): ?>
            <tr>
              <td><code><?= htmlspecialchars($row['order_number'] ?? '') ?></code></td>
              <td class="small text-muted"><?= !empty($row['created_at']) ? date('d M Y, H:i', strtotime($row['created_at'])) : '—' ?></td>
              <td><span class="badge bg-secondary"><?= ucfirst($row['status'] ?? '') ?></span></td>
              <td class="small"><?= htmlspecialchars($row['variant_label'] ?? '—') ?></td>
              <td><?= (int)($row['quantity'] ?? 0) ?></td>
              <td><?= $currency . number_format((float)($row['subtotal'] ?? 0), 2) ?></td>
              <td>
                <a href="<?= site_url('shopkart/orders/view/'.$row['id']) ?>" class="btn btn-sm btn-outline-primary">
                  Open
                </a>
              </td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($order_rows)): ?>
            <tr><td colspan="7" class="text-center py-4 text-muted">No orders yet for this product.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
      <?php if ($orderPages > 1): ?>
      <div class="card-footer bg-white d-flex justify-content-end py-2">
        <nav>
          <ul class="pagination pagination-sm mb-0">
            <?php if ($page > 1): ?>
            <li class="page-item">
              <a class="page-link" href="?page=<?= $page - 1 ?>">Prev</a>
            </li>
            <?php endif; ?>
            <li class="page-item disabled"><span class="page-link"><?= $page ?> / <?= $orderPages ?></span></li>
            <?php if ($page < $orderPages): ?>
            <li class="page-item">
              <a class="page-link" href="?page=<?= $page + 1 ?>">Next</a>
            </li>
            <?php endif; ?>
          </ul>
        </nav>
      </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<script>
(function() {
  var url = <?= json_encode(site_url('shopkart/inventory/update_stock')) ?>;
  function saveStock(input, btn) {
    var productId = input.getAttribute('data-product-id');
    var variantId = input.getAttribute('data-variant-id') || '0';
    var stock = parseInt(input.value, 10);
    if (isNaN(stock) || stock < 0) stock = 0;
    input.value = stock;
    if (btn) btn.disabled = true;
    $.post(url, { product_id: productId, variant_id: variantId, stock: stock }, function(res) {
      if (btn) btn.disabled = false;
      if (!res || !res.success) {
        alert((res && res.message) || 'Could not update stock.');
        return;
      }
      if (typeof res.product_stock !== 'undefined') {
        var el = document.getElementById('invProductStock');
        if (el) el.textContent = (parseInt(res.product_stock, 10) || 0).toLocaleString();
      }
      var msg = document.querySelector('.inv-stock-msg');
      if (msg) {
        msg.textContent = res.message || 'Saved';
        msg.classList.remove('d-none');
        setTimeout(function() { msg.classList.add('d-none'); }, 2000);
      }
    }, 'json').fail(function() {
      if (btn) btn.disabled = false;
      alert('Network error.');
    });
  }
  document.querySelectorAll('.inv-stock-save').forEach(function(btn) {
    btn.addEventListener('click', function() {
      var wrap = btn.closest('div');
      var input = wrap ? wrap.querySelector('.inv-stock-input') : null;
      if (input) saveStock(input, btn);
    });
  });
  document.querySelectorAll('.inv-stock-input').forEach(function(input) {
    input.addEventListener('keydown', function(e) {
      if (e.key === 'Enter') {
        e.preventDefault();
        var wrap = input.closest('div');
        var btn = wrap ? wrap.querySelector('.inv-stock-save') : null;
        saveStock(input, btn);
      }
    });
  });
})();
</script>
