<?php /** @var array $variant_units */ /** @var array $product_variants */ ?>
<?php
$default_unit_id = '';
$default_unit_value = 1;
if (!empty($product_variants[0])) {
    $default_unit_id = $product_variants[0]['unit_id'] ?? '';
    $default_unit_value = $product_variants[0]['unit_value'] ?? 1;
}
$rows = !empty($product_variants)
    ? $product_variants
    : [['unit_id' => '', 'unit_value' => 1, 'label' => '', 'price' => '', 'sale_price' => '', 'stock' => '', 'sku' => '', 'image' => '', 'is_default' => 1]];
?>
<div class="card sk-table-card shadow-sm mb-3" style="border-left:4px solid #10b981;">
  <div class="card-header bg-white border-0 py-3 fw-semibold">
    <i class="bi bi-box-seam me-1 text-success"></i> Unit &amp; Variants
  </div>
  <div class="card-body">
    <p class="small text-muted mb-3">
      Add sellable pack sizes (250g, 500g, 1kg, 1 box, etc.). Each variant can have its own <strong>image, price, sale price and stock</strong>.
      Leave price/stock empty to keep the product's saved price and stock.
    </p>

    <div class="row g-2 mb-3">
      <div class="col-md-4">
        <label class="form-label">Quick-fill Unit</label>
        <select id="default_unit_id" class="form-select">
          <option value="">Select unit</option>
          <?php foreach ($variant_units as $vu): ?>
            <option value="<?= $vu['id'] ?>" data-symbol="<?= htmlspecialchars($vu['symbol']) ?>" <?= ((int)$default_unit_id === (int)$vu['id']) ? 'selected' : '' ?>>
              <?= htmlspecialchars($vu['name']) ?> (<?= htmlspecialchars($vu['symbol']) ?>)
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-3">
        <label class="form-label">Quick-fill Qty</label>
        <input type="text" inputmode="decimal" id="default_unit_value" class="form-control" value="<?= htmlspecialchars((string)$default_unit_value) ?>" placeholder="e.g. 1, 500">
      </div>
      <div class="col-md-5 d-flex align-items-end gap-2">
        <button type="button" class="btn btn-outline-secondary btn-sm" id="apply-default-unit">
          <i class="bi bi-arrow-down-circle me-1"></i> Apply to new row
        </button>
        <button type="button" class="btn btn-outline-success btn-sm" id="add-variant-row">
          <i class="bi bi-plus-lg me-1"></i> Add Variant
        </button>
      </div>
    </div>

    <div id="variant-rows-list">
      <?php foreach ($rows as $vi => $vr): ?>
      <div class="variant-row border rounded p-3 mb-2 bg-light">
        <div class="row g-2 align-items-end">
          <div class="col-md-2">
            <label class="form-label small">Image</label>
            <?php $imgPath = trim($vr['image'] ?? ''); ?>
            <div class="mb-1 variant-img-preview-wrap<?= $imgPath === '' ? ' d-none' : '' ?>">
              <img src="<?= $imgPath !== '' ? base_url($imgPath) : '' ?>" alt="" class="rounded border variant-img-preview" style="width:64px;height:64px;object-fit:cover;">
              <input type="hidden" name="product_variants[<?= $vi ?>][existing_image]" class="variant-existing-image" value="<?= htmlspecialchars($imgPath) ?>">
            </div>
            <input type="file" name="variant_images[<?= $vi ?>]" class="form-control form-control-sm variant-image-input" accept="image/*">
          </div>
          <div class="col-md-2">
            <label class="form-label small">Unit <span class="text-danger">*</span></label>
            <select name="product_variants[<?= $vi ?>][unit_id]" class="form-select form-select-sm variant-unit-select" required>
              <option value="">Unit</option>
              <?php foreach ($variant_units as $vu): ?>
                <option value="<?= $vu['id'] ?>" <?= ((int)($vr['unit_id'] ?? 0) === (int)$vu['id']) ? 'selected' : '' ?>>
                  <?= htmlspecialchars($vu['symbol']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-1">
            <label class="form-label small">Qty</label>
            <input type="text" inputmode="decimal" name="product_variants[<?= $vi ?>][unit_value]" class="form-control form-control-sm" value="<?= htmlspecialchars($vr['unit_value'] ?? '1') ?>">
          </div>
          <div class="col-md-2">
            <label class="form-label small">Label</label>
            <input type="text" name="product_variants[<?= $vi ?>][label]" class="form-control form-control-sm" value="<?= htmlspecialchars($vr['label'] ?? '') ?>" placeholder="Auto">
          </div>
          <div class="col-md-1">
            <label class="form-label small">Price (<?= htmlspecialchars(sk_currency_symbol($settings ?? null)) ?>)</label>
            <input type="text" inputmode="decimal" name="product_variants[<?= $vi ?>][price]" class="form-control form-control-sm variant-price" value="<?= htmlspecialchars($vr['price'] ?? '') ?>" placeholder="Main">
          </div>
          <div class="col-md-1">
            <label class="form-label small">Sale (<?= htmlspecialchars(sk_currency_symbol($settings ?? null)) ?>)</label>
            <input type="text" inputmode="decimal" name="product_variants[<?= $vi ?>][sale_price]" class="form-control form-control-sm" value="<?= htmlspecialchars($vr['sale_price'] ?? '') ?>">
          </div>
          <div class="col-md-1">
            <label class="form-label small">Stock</label>
            <input type="text" inputmode="numeric" name="product_variants[<?= $vi ?>][stock]" class="form-control form-control-sm variant-stock" value="<?= htmlspecialchars($vr['stock'] ?? '') ?>" placeholder="Main">
          </div>
          <div class="col-md-1">
            <label class="form-label small">SKU</label>
            <input type="text" name="product_variants[<?= $vi ?>][sku]" class="form-control form-control-sm" value="<?= htmlspecialchars($vr['sku'] ?? '') ?>">
          </div>
          <div class="col-md-1">
            <div class="form-check mt-4">
              <input class="form-check-input variant-default" type="radio" name="variant_default" value="<?= $vi ?>" <?= !empty($vr['is_default']) ? 'checked' : '' ?>>
              <label class="form-check-label small">Def</label>
            </div>
            <input type="hidden" name="product_variants[<?= $vi ?>][is_default]" class="variant-is-default" value="<?= !empty($vr['is_default']) ? '1' : '0' ?>">
          </div>
          <div class="col-md-1 text-end">
            <button type="button" class="btn btn-sm btn-outline-danger remove-variant-row" title="Remove"><i class="bi bi-x-lg"></i></button>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <input type="hidden" id="variant_row_count" value="<?= max(1, count($rows)) ?>">
  </div>
</div>

<script>
(function() {
  const unitsOptions = <?= json_encode(array_map(function($u) {
    return ['id' => $u['id'], 'symbol' => $u['symbol'], 'name' => $u['name']];
  }, $variant_units)) ?>;

  function unitSelectHtml(idx, selected) {
    let html = '<select name="product_variants[' + idx + '][unit_id]" class="form-select form-select-sm variant-unit-select" required><option value="">Unit</option>';
    unitsOptions.forEach(u => {
      html += '<option value="' + u.id + '"' + (String(selected) === String(u.id) ? ' selected' : '') + '>' + u.symbol + '</option>';
    });
    html += '</select>';
    return html;
  }

  function syncDefaultFlags() {
    document.querySelectorAll('.variant-row').forEach((row, i) => {
      const radio = row.querySelector('.variant-default');
      const hidden = row.querySelector('.variant-is-default');
      if (radio && hidden) hidden.value = radio.checked ? '1' : '0';
      if (radio) radio.value = i;
    });
  }

  function reindexVariantRows() {
    document.querySelectorAll('.variant-row').forEach((row, i) => {
      row.querySelectorAll('[name^="product_variants["]').forEach(el => {
        el.name = el.name.replace(/product_variants\[\d+\]/, 'product_variants[' + i + ']');
      });
      const fileInput = row.querySelector('.variant-image-input');
      if (fileInput) fileInput.name = 'variant_images[' + i + ']';
      const radio = row.querySelector('.variant-default');
      if (radio) radio.value = i;
    });
    const countEl = document.getElementById('variant_row_count');
    if (countEl) countEl.value = document.querySelectorAll('.variant-row').length;
  }

  document.getElementById('apply-default-unit')?.addEventListener('click', function() {
    const unit = document.getElementById('default_unit_id')?.value || '';
    const qty = document.getElementById('default_unit_value')?.value || '1';
    const last = document.querySelector('.variant-row:last-child');
    if (!last) return;
    const sel = last.querySelector('.variant-unit-select');
    const qtyIn = last.querySelector('input[name*="[unit_value]"]');
    if (sel && unit) sel.value = unit;
    if (qtyIn) qtyIn.value = qty;
  });

  document.getElementById('add-variant-row')?.addEventListener('click', function() {
    const idx = parseInt(document.getElementById('variant_row_count').value, 10);
    const defaultUnit = document.getElementById('default_unit_id')?.value || '';
    const defaultQty = document.getElementById('default_unit_value')?.value || '1';
    const wrap = document.createElement('div');
    wrap.className = 'variant-row border rounded p-3 mb-2 bg-light';
    wrap.innerHTML = `
      <div class="row g-2 align-items-end">
        <div class="col-md-2"><label class="form-label small">Image</label><div class="mb-1 variant-img-preview-wrap d-none"><img src="" alt="" class="rounded border variant-img-preview" style="width:64px;height:64px;object-fit:cover;"><input type="hidden" name="product_variants[${idx}][existing_image]" class="variant-existing-image" value=""></div><input type="file" name="variant_images[${idx}]" class="form-control form-control-sm variant-image-input" accept="image/*"></div>
        <div class="col-md-2"><label class="form-label small">Unit *</label>${unitSelectHtml(idx, defaultUnit)}</div>
        <div class="col-md-1"><label class="form-label small">Qty</label><input type="text" inputmode="decimal" name="product_variants[${idx}][unit_value]" class="form-control form-control-sm" value="${defaultQty}"></div>
        <div class="col-md-2"><label class="form-label small">Label</label><input type="text" name="product_variants[${idx}][label]" class="form-control form-control-sm" placeholder="Auto"></div>
        <div class="col-md-1"><label class="form-label small">Price <?= htmlspecialchars(sk_currency_symbol($settings ?? null)) ?></label><input type="text" inputmode="decimal" name="product_variants[${idx}][price]" class="form-control form-control-sm variant-price" placeholder="Main"></div>
        <div class="col-md-1"><label class="form-label small">Sale <?= htmlspecialchars(sk_currency_symbol($settings ?? null)) ?></label><input type="text" inputmode="decimal" name="product_variants[${idx}][sale_price]" class="form-control form-control-sm"></div>
        <div class="col-md-1"><label class="form-label small">Stock</label><input type="text" inputmode="numeric" name="product_variants[${idx}][stock]" class="form-control form-control-sm variant-stock" placeholder="Main"></div>
        <div class="col-md-1"><label class="form-label small">SKU</label><input type="text" name="product_variants[${idx}][sku]" class="form-control form-control-sm"></div>
        <div class="col-md-1"><div class="form-check mt-4"><input class="form-check-input variant-default" type="radio" name="variant_default" value="${idx}"><label class="form-check-label small">Def</label></div><input type="hidden" name="product_variants[${idx}][is_default]" class="variant-is-default" value="0"></div>
        <div class="col-md-1 text-end"><button type="button" class="btn btn-sm btn-outline-danger remove-variant-row"><i class="bi bi-x-lg"></i></button></div>
      </div>`;
    document.getElementById('variant-rows-list').appendChild(wrap);
    document.getElementById('variant_row_count').value = idx + 1;
    syncDefaultFlags();
  });

  document.getElementById('variant-rows-list')?.addEventListener('click', function(e) {
    const btn = e.target.closest('.remove-variant-row');
    if (!btn) return;
    const rows = document.querySelectorAll('.variant-row');
    if (rows.length <= 1) return alert('At least one variant row is required.');
    btn.closest('.variant-row').remove();
    reindexVariantRows();
    syncDefaultFlags();
  });

  document.getElementById('variant-rows-list')?.addEventListener('change', function(e) {
    if (e.target.classList.contains('variant-default')) syncDefaultFlags();
    if (e.target.classList.contains('variant-image-input') && e.target.files && e.target.files[0]) {
      const row = e.target.closest('.variant-row');
      const wrap = row?.querySelector('.variant-img-preview-wrap');
      const img = wrap?.querySelector('.variant-img-preview');
      if (!img || !wrap) return;
      const reader = new FileReader();
      reader.onload = function(ev) {
        img.src = ev.target.result;
        wrap.classList.remove('d-none');
      };
      reader.readAsDataURL(e.target.files[0]);
    }
  });

  const priceInput = document.querySelector('input[name="price"]');
  const stockInput = document.querySelector('input[name="stock"]');
  const saleInput = document.querySelector('input[name="sale_price"]');
  function fillEmptyFromMain() {
    document.querySelectorAll('.variant-row').forEach(row => {
      const vp = row.querySelector('.variant-price');
      const vs = row.querySelector('.variant-stock');
      if (vp && !vp.value && priceInput) vp.placeholder = priceInput.value || 'Main';
      if (vs && !vs.value && stockInput) vs.placeholder = stockInput.value || 'Main';
    });
  }
  priceInput?.addEventListener('input', fillEmptyFromMain);
  stockInput?.addEventListener('input', fillEmptyFromMain);
  saleInput?.addEventListener('input', fillEmptyFromMain);

  document.querySelector('form')?.addEventListener('submit', function() {
    reindexVariantRows();
    document.querySelectorAll('.variant-row').forEach(row => {
      const vp = row.querySelector('.variant-price');
      const vs = row.querySelector('.variant-stock');
      if (vp && !vp.value && priceInput?.value) vp.value = priceInput.value;
      if (vs && !vs.value && stockInput?.value) vs.value = stockInput.value;
    });
    syncDefaultFlags();
  });

  syncDefaultFlags();
  fillEmptyFromMain();
})();
</script>
