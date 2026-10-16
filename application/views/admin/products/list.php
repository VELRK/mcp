<?php
$currency = sk_currency_symbol($settings);
$f = $filters ?? [];
$show_vendor_col = !empty($show_vendor_col);
?>

<?php
$page_title = 'Product List';
$breadcrumb = [
    ['label' => 'Ecommerce', 'url' => site_url('shopkart/products')],
    'Product List',
];
$this->load->view('admin/partials/page_title', compact('page_title', 'breadcrumb'));
?>

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

<div class="card" id="productListCard">
  <div class="card-header d-flex justify-content-between align-items-center gap-2">
    <h5 class="card-title mb-0">Product List</h5>
    <div class="d-flex align-items-center gap-2 flex-wrap">
      <button type="button" id="bulkDeleteProductsBtn" class="btn btn-sm btn-outline-danger d-none"
              data-url="<?= site_url('shopkart/products/bulk_delete') ?>">
        <i class="mdi mdi-trash-can-outline me-1"></i>Delete selected (<span id="bulkDeleteCount">0</span>)
      </button>
      <button type="button" id="syncCatalogButton"
         data-url="<?= site_url('shopkart/products/sync_catalog' . (!empty($vendor_id) ? '?vendor_id=' . rawurlencode((string)$vendor_id) : '')) ?>"
         class="btn btn-sm btn-outline-success"><i class="mdi mdi-cloud-sync me-1"></i>Sync Catalog</button>
      <a href="<?= site_url('shopkart/products/add') ?>" class="btn btn-sm btn-primary"><i class="mdi mdi-plus me-1"></i>Add Product</a>
    </div>
  </div>
  <div class="card-body">
    <div id="catalogSyncResult" class="alert d-none" role="alert" aria-live="polite"></div>
    <div class="table-responsive text-nowrap">
      <table class="table align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th style="width:36px;">
              <input type="checkbox" class="form-check-input" id="productSelectAll" aria-label="Select all products on this page">
            </th>
            <th>ID</th>
            <th>Product</th>
            <?php if ($show_vendor_col): ?><th>Vendor</th><?php endif; ?>
            <th>Category</th>
            <th>SKU</th>
            <th>Price</th>
            <th>Discount</th>
            <th>Stock</th>
            <th>Status</th>
            <th class="text-end">Actions</th>
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

  var selectAll = document.getElementById('productSelectAll');
  var bulkBtn = document.getElementById('bulkDeleteProductsBtn');
  var bulkCount = document.getElementById('bulkDeleteCount');

  function selectedProductIds() {
    return Array.prototype.map.call(
      document.querySelectorAll('.product-row-check:checked'),
      function(el) { return parseInt(el.value, 10); }
    ).filter(function(id) { return id > 0; });
  }

  function refreshBulkUi() {
    var ids = selectedProductIds();
    if (bulkCount) bulkCount.textContent = String(ids.length);
    if (bulkBtn) bulkBtn.classList.toggle('d-none', ids.length < 1);
    if (selectAll) {
      var checks = document.querySelectorAll('.product-row-check');
      selectAll.checked = checks.length > 0 && ids.length === checks.length;
      selectAll.indeterminate = ids.length > 0 && ids.length < checks.length;
    }
  }

  document.addEventListener('change', function(e) {
    if (!e.target) return;
    if (e.target.id === 'productSelectAll') {
      document.querySelectorAll('.product-row-check').forEach(function(cb) {
        cb.checked = !!e.target.checked;
      });
      refreshBulkUi();
      return;
    }
    if (e.target.classList && e.target.classList.contains('product-row-check')) {
      refreshBulkUi();
    }
  });

  if (bulkBtn) {
    bulkBtn.addEventListener('click', function() {
      var ids = selectedProductIds();
      if (!ids.length) return;
      if (!confirm('Permanently delete ' + ids.length + ' selected product' + (ids.length === 1 ? '' : 's') + '? This cannot be undone.')) {
        return;
      }
      var original = bulkBtn.innerHTML;
      bulkBtn.disabled = true;
      bulkBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Deleting...';
      var body = new URLSearchParams();
      ids.forEach(function(id) { body.append('ids[]', String(id)); });
      fetch(bulkBtn.dataset.url, {
        method: 'POST',
        headers: {
          'Accept': 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
          'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8'
        },
        body: body.toString()
      })
        .then(function(r) { return r.json().then(function(j) { return { ok: r.ok, j: j }; }); })
        .then(function(res) {
          if (!res.j || !res.j.success) {
            throw new Error((res.j && res.j.message) || 'Bulk delete failed.');
          }
          ids.forEach(function(id) {
            var row = document.getElementById('row-' + id);
            if (row) row.remove();
          });
          refreshBulkUi();
          if (!document.querySelector('#productListBody tr')) {
            loadProducts(currentPage);
          }
        })
        .catch(function(err) {
          alert(err.message || 'Bulk delete failed.');
        })
        .finally(function() {
          bulkBtn.disabled = false;
          bulkBtn.innerHTML = original;
        });
    });
  }

  if (tbody) {
    var mo = new MutationObserver(function() { refreshBulkUi(); });
    mo.observe(tbody, { childList: true });
  }
  refreshBulkUi();

  var syncButton = document.getElementById('syncCatalogButton');
  var syncResult = document.getElementById('catalogSyncResult');
  if (syncButton && syncResult) {
    syncButton.addEventListener('click', function() {
      if (syncButton.disabled) return;
      var originalHtml = syncButton.innerHTML;
      syncButton.disabled = true;
      syncButton.innerHTML = '<span class="spinner-border spinner-border-sm me-1" aria-hidden="true"></span>Syncing...';
      syncResult.className = 'alert alert-info';
      syncResult.textContent = 'Syncing products to the Meta catalog...';
      fetch(syncButton.dataset.url, {
        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
      })
        .then(function(response) {
          return response.json().catch(function() {
            throw new Error('The server returned an unreadable response. Check the application logs and try again.');
          }).then(function(payload) {
            if (!response.ok || !payload.success) {
              throw new Error(payload.message || 'Meta catalog sync failed.');
            }
            return payload;
          });
        })
        .then(function(payload) {
          syncResult.className = 'alert alert-success';
          syncResult.textContent = payload.message;
        })
        .catch(function(error) {
          syncResult.className = 'alert alert-danger';
          syncResult.textContent = error.message || 'Meta catalog sync failed.';
        })
        .finally(function() {
          syncButton.disabled = false;
          syncButton.innerHTML = originalHtml;
        });
    });
  }
})();
</script>
