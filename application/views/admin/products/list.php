<?php
$currency = sk_currency_symbol($settings);
$f = $filters ?? [];
$show_vendor_col = !empty($show_vendor_col);
?>

<div class="sk-page-header">
  <h5 class="sk-page-title"><i class="bi bi-box-seam me-2 text-warning"></i>Products</h5>
  <a href="<?= site_url('shopkart/products/add') ?>" class="btn btn-warning btn-sm fw-semibold">
    <i class="bi bi-plus-lg me-1"></i> Add Product
  </a>
</div>

<!-- Filters (auto AJAX) -->
<div class="card sk-table-card shadow-sm mb-3">
  <div class="card-body py-2">
    <form class="d-flex flex-nowrap align-items-end gap-2 overflow-x-auto pb-1" id="productFilterForm" onsubmit="return false;">
      <div style="min-width:150px;flex:1 1 150px;">
        <label class="form-label small mb-1">Search</label>
        <input type="text" name="search" id="filterSearch" class="form-control form-control-sm" placeholder="Name or SKU..."
               value="<?= htmlspecialchars($f['search'] ?? '') ?>">
      </div>
      <div style="min-width:130px;flex:0 0 130px;">
        <label class="form-label small mb-1">Category</label>
        <select name="category_id" id="filterCategoryId" class="form-select form-select-sm sk-product-filter">
          <option value="">All categories</option>
          <?php foreach ($categories ?? [] as $c): ?>
            <option value="<?= $c['id'] ?>" <?= ((int)($f['category_id'] ?? 0) === (int)$c['id']) ? 'selected' : '' ?>>
              <?= htmlspecialchars($c['name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div style="min-width:130px;flex:0 0 130px;">
        <label class="form-label small mb-1">Subcategory</label>
        <select name="subcategory_id" id="filterSubcategoryId" class="form-select form-select-sm sk-product-filter">
          <option value="">All subcategories</option>
          <?php foreach ($subcategories ?? [] as $s): ?>
            <option value="<?= $s['id'] ?>" data-category="<?= (int)$s['category_id'] ?>"
              <?= ((int)($f['subcategory_id'] ?? 0) === (int)$s['id']) ? 'selected' : '' ?>>
              <?= htmlspecialchars($s['name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div style="min-width:110px;flex:0 0 110px;">
        <label class="form-label small mb-1">Status</label>
        <select name="status" class="form-select form-select-sm sk-product-filter">
          <option value="">All status</option>
          <?php foreach (['active', 'inactive', 'draft'] as $st): ?>
            <option value="<?= $st ?>" <?= ($f['status'] ?? '') === $st ? 'selected' : '' ?>><?= ucfirst($st) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div style="min-width:80px;flex:0 0 80px;">
        <label class="form-label small mb-1">Min <?= htmlspecialchars($currency) ?></label>
        <input type="number" name="min_price" class="form-control form-control-sm sk-product-filter-delay" step="0.01" min="0"
               value="<?= htmlspecialchars((string)($f['min_price'] ?? '')) ?>" placeholder="0">
      </div>
      <div style="min-width:80px;flex:0 0 80px;">
        <label class="form-label small mb-1">Max <?= htmlspecialchars($currency) ?></label>
        <input type="number" name="max_price" class="form-control form-control-sm sk-product-filter-delay" step="0.01" min="0"
               value="<?= htmlspecialchars((string)($f['max_price'] ?? '')) ?>" placeholder="Any">
      </div>
      <?php if (!empty($vendors)): ?>
      <div style="min-width:130px;flex:0 0 130px;">
        <label class="form-label small mb-1">Vendor</label>
        <select name="vendor_id" class="form-select form-select-sm sk-product-filter">
          <option value="">All vendors</option>
          <?php foreach ($vendors as $v): ?>
          <option value="<?= $v['id'] ?>" <?= ($vendor_id ?? '') == $v['id'] ? 'selected' : '' ?>><?= htmlspecialchars($v['business_name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <?php endif; ?>
      <div class="d-flex align-items-center gap-2 flex-shrink-0 pb-1">
        <div class="form-check mb-0">
          <input class="form-check-input sk-product-filter" type="checkbox" name="low_stock" value="1" id="lowStockCheck"
                 <?= !empty($f['low_stock']) ? 'checked' : '' ?>>
          <label class="form-check-label small text-nowrap" for="lowStockCheck">Low stock</label>
        </div>
        <button type="button" class="btn btn-sm btn-outline-secondary text-nowrap" id="productFilterReset">Reset</button>
        <span id="productFilterLoading" class="small text-muted d-none text-nowrap"><span class="spinner-border spinner-border-sm"></span></span>
      </div>
    </form>
  </div>
</div>

<div class="card sk-table-card shadow-sm" id="productListCard">
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead>
          <tr>
            <th style="width:60px;">Image</th>
            <th>Name</th>
            <?php if ($show_vendor_col): ?><th>Vendor</th><?php endif; ?>
            <th>Category</th>
            <th>Subcategory</th>
            <th>Price</th>
            <th>Stock</th>
            <th>Status</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody id="productListBody">
          <?php $this->load->view('admin/products/_list_rows', compact('products', 'settings', 'show_vendor_col')); ?>
        </tbody>
      </table>
    </div>
  </div>
  <div id="productListPaginationWrap">
    <?php $this->load->view('admin/products/_list_pagination', compact('total', 'page', 'limit')); ?>
  </div>
</div>

<script>
(function() {
  var form = document.getElementById('productFilterForm');
  var tbody = document.getElementById('productListBody');
  var paginationWrap = document.getElementById('productListPaginationWrap');
  var loadingEl = document.getElementById('productFilterLoading');
  var catSel = document.getElementById('filterCategoryId');
  var subSel = document.getElementById('filterSubcategoryId');
  var filterUrl = <?= json_encode(site_url('shopkart/products/filter')) ?>;
  var listUrl = <?= json_encode(site_url('shopkart/products')) ?>;
  var currentPage = <?= (int)($page ?? 1) ?>;
  var debounceTimer = null;
  var activeRequest = null;

  function filterSubcategories() {
    if (!catSel || !subSel) return;
    var catId = catSel.value;
    var selected = subSel.value;
    Array.from(subSel.options).forEach(function(opt, idx) {
      if (idx === 0) { opt.hidden = false; return; }
      var match = !catId || opt.dataset.category === catId;
      opt.hidden = !match;
      if (!match && opt.selected) subSel.value = '';
    });
    if (selected && subSel.querySelector('option[value="' + selected + '"]')?.hidden) {
      subSel.value = '';
    }
  }

  function buildParams(page) {
    var fd = new FormData(form);
    var params = new URLSearchParams();
    fd.forEach(function(val, key) {
      if (key === 'low_stock') return;
      if (val !== '') params.set(key, val);
    });
    if (form.querySelector('[name="low_stock"]')?.checked) {
      params.set('low_stock', '1');
    }
    params.set('page', String(page || 1));
    return params;
  }

  function setLoading(on) {
    if (!loadingEl) return;
    loadingEl.classList.toggle('d-none', !on);
    if (tbody) tbody.style.opacity = on ? '0.55' : '1';
  }

  function loadProducts(page) {
    page = page || 1;
    currentPage = page;
    var params = buildParams(page);

    if (activeRequest) activeRequest.abort();
    setLoading(true);

    activeRequest = new AbortController();
    fetch(filterUrl + '?' + params.toString(), {
      headers: { 'X-Requested-With': 'XMLHttpRequest' },
      signal: activeRequest.signal
    })
      .then(function(r) { return r.json(); })
      .then(function(res) {
        if (!res || !res.success) return;
        if (tbody) tbody.innerHTML = res.rows_html || '';
        if (paginationWrap) paginationWrap.innerHTML = res.pagination_html || '';
        bindPagination();
        var qs = params.toString();
        history.replaceState(null, '', listUrl + (qs ? ('?' + qs) : ''));
      })
      .catch(function(err) {
        if (err && err.name === 'AbortError') return;
      })
      .finally(function() {
        setLoading(false);
        activeRequest = null;
      });
  }

  function bindPagination() {
    document.querySelectorAll('.sk-product-page-link').forEach(function(link) {
      link.addEventListener('click', function(e) {
        e.preventDefault();
        loadProducts(parseInt(link.dataset.page, 10) || 1);
      });
    });
  }

  function scheduleLoad() {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(function() { loadProducts(1); }, 350);
  }

  form.querySelectorAll('.sk-product-filter').forEach(function(el) {
    el.addEventListener('change', function() {
      if (el === catSel) filterSubcategories();
      loadProducts(1);
    });
  });

  form.querySelectorAll('.sk-product-filter-delay').forEach(function(el) {
    el.addEventListener('input', scheduleLoad);
  });

  var searchInput = document.getElementById('filterSearch');
  if (searchInput) searchInput.addEventListener('input', scheduleLoad);

  document.getElementById('productFilterReset')?.addEventListener('click', function() {
    form.reset();
    filterSubcategories();
    loadProducts(1);
    history.replaceState(null, '', listUrl);
  });

  if (catSel) {
    catSel.addEventListener('change', filterSubcategories);
    filterSubcategories();
  }

  bindPagination();
})();
</script>
