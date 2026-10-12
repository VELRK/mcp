<?php
// Helper: select option
function opt($list, $val) {
    $out = '';
    foreach ($list as $v) {
        $sel = (is_array($v) ? $v['name'] : $v) === $val ? 'selected' : '';
        $label = is_array($v) ? htmlspecialchars($v['name'] . ($v['state'] ? ' (' . $v['state'] . ')' : '')) : htmlspecialchars($v);
        $value = is_array($v) ? htmlspecialchars($v['name']) : htmlspecialchars($v);
        $out .= "<option value=\"$value\" $sel>$label</option>";
    }
    return $out;
}
?>

<?php
$page_title = 'Create Product';
$breadcrumb = [
    ['label' => 'Product List', 'url' => site_url('shopkart/products')],
    'Create Product',
];
$this->load->view('admin/partials/page_title', compact('page_title', 'breadcrumb'));
?>
<div class="d-flex justify-content-end mb-3">
  <a href="<?= site_url('shopkart/products') ?>" class="btn btn-sm btn-light"><i class="mdi mdi-arrow-left me-1"></i> Back</a>
</div>

<form action="<?= site_url('admin/products/store') ?>" method="POST" enctype="multipart/form-data">

  <div class="row g-3">

    <!-- ── LEFT COLUMN ─────────────────────────────────────── -->
    <div class="col-lg-8">

      <!-- Basic Info -->
      <div class="card sk-table-card shadow-sm mb-3">
        <div class="card-header bg-white border-0 py-3 fw-semibold">
          <i class="bi bi-info-circle me-1 text-warning"></i> Basic Information
        </div>
        <div class="card-body">
          <?php if (!empty($vendors) && empty($vendor_id)): ?>
          <div class="mb-3">
            <label class="form-label">Vendor <span class="text-danger">*</span></label>
            <select name="vendor_id" class="form-select" required>
              <option value="">Select vendor</option>
              <?php foreach ($vendors as $v): ?>
              <option value="<?= $v['id'] ?>"><?= htmlspecialchars($v['business_name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <?php elseif (!empty($vendor_id)): ?>
          <input type="hidden" name="vendor_id" value="<?= (int)$vendor_id ?>">
          <?php endif; ?>
          <div class="mb-3">
            <label class="form-label">Product Name <span class="text-danger">*</span></label>
            <input type="text" name="name" id="product_name" class="form-control"
                   placeholder="e.g. Organic Turmeric Powder 500g" required>
          </div>
          <div class="row g-2">
            <div class="col-md-4">
              <label class="form-label">SKU / Code</label>
              <input type="text" name="sku" class="form-control" placeholder="e.g. KJV-RED-001">
            </div>
            <div class="col-md-4">
              <label class="form-label">Category <span class="text-danger">*</span></label>
              <select name="category_id" class="form-select" required>
                <option value="">Select Category</option>
                <?php foreach ($categories as $c): ?>
                  <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label">Brand</label>
              <select name="brand_id" class="form-select">
                <option value="">No Brand</option>
                <?php foreach ($brands as $b): ?>
                  <option value="<?= $b['id'] ?>"><?= htmlspecialchars($b['name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>
          <div class="row g-2 mt-1" id="subcategory-row" style="display:none;">
            <div class="col-md-4">
              <label class="form-label">Sub Category</label>
              <select name="subcategory_id" id="subcategory_id" class="form-select">
                <option value="">-- Select Sub Category --</option>
              </select>
            </div>
          </div>
          <div class="mt-3">
            <label class="form-label">Subtitle <small class="text-muted">(product tagline)</small></label>
            <input type="text" name="subtitle" class="form-control" placeholder="e.g. Exclusive Designer Collection">
          </div>
          <div class="mt-3">
            <label class="form-label">Short Description <small class="text-muted">(shown in product info panel — keep it brief)</small></label>
            <div id="quill-short-desc"></div>
            <input type="hidden" name="short_desc" value="">
          </div>
          <div class="mt-3">
            <label class="form-label">Full Description <small class="text-muted">(HTML editor)</small></label>
            <div id="quill-description"></div>
            <input type="hidden" name="description" value="">
          </div>
          <div class="mt-3">
            <label class="form-label">Tags <small class="text-muted">(comma separated)</small></label>
            <input type="text" name="tags" class="form-control" placeholder="silk, kanjivaram, wedding, zari, red">
          </div>
        </div>
      </div>

      <!-- Saree Attributes (hidden — legacy fields kept for DB compatibility) -->
      <div class="card sk-table-card shadow-sm mb-3 d-none" id="saree-attributes-card" style="border-left:4px solid #f59e0b;">
        <div class="card-header bg-white border-0 py-3 fw-semibold">
          <i class="bi bi-stars me-1 text-warning"></i> Saree Attributes
        </div>
        <div class="card-body">
          <div class="row g-3">

            <div class="col-md-4">
              <label class="form-label">Saree Type / Style</label>
              <select name="saree_type" class="form-select">
                <option value="">Select Style</option>
                <?= opt($saree_styles, '') ?>
              </select>
            </div>

            <div class="col-md-4">
              <label class="form-label">Fabric / Material</label>
              <select name="fabric" class="form-select">
                <option value="">Select Fabric</option>
                <?= opt($fabrics, '') ?>
              </select>
            </div>

            <div class="col-md-4">
              <label class="form-label">Occasion</label>
              <select name="occasion" class="form-select">
                <option value="">Select Occasion</option>
                <?= opt($occasions, '') ?>
              </select>
            </div>

            <div class="col-md-4">
              <label class="form-label">Work Type / Embellishment</label>
              <select name="work_type" class="form-select">
                <option value="">Select Work</option>
                <?= opt($work_types, '') ?>
              </select>
            </div>

            <div class="col-md-4">
              <label class="form-label">Origin State</label>
              <select name="origin_state" class="form-select">
                <option value="">Select State</option>
                <?= opt($origin_states, '') ?>
              </select>
            </div>

            <div class="col-md-4">
              <label class="form-label">Weave Type</label>
              <select name="weave_type" class="form-select">
                <option value="">Select</option>
                <option>Hand-woven</option>
                <option>Power-loom</option>
                <option>Machine-woven</option>
                <option>Handblock Printed</option>
                <option>Handpainted</option>
              </select>
            </div>

            <!-- Color Variants -->
            <div class="col-12">
              <label class="form-label fw-semibold">Color Variants <small class="text-muted fw-normal">(add all available colors with a swatch image)</small></label>
              <div id="color-variants-list">
                <div class="color-variant-row d-flex gap-2 align-items-end mb-2">
                  <div style="flex:2">
                    <input type="text" name="color_variants[0][name]" class="form-control form-control-sm" placeholder="Color name e.g. Ruby Red">
                  </div>
                  <div style="flex:0 0 44px">
                    <input type="color" name="color_variants[0][hex]" class="form-control form-control-color form-control-sm w-100" value="#cc0000" title="Hex">
                  </div>
                  <div style="flex:3">
                    <input type="file" name="color_variant_images[]" class="form-control form-control-sm" accept=".jpg,.jpeg,.png,image/jpeg,image/png" title="Swatch image for this color">
                  </div>
                </div>
              </div>
              <button type="button" class="btn btn-sm btn-outline-secondary mt-1" id="add-color-variant">
                <i class="bi bi-plus-lg me-1"></i> Add Color
              </button>
              <input type="hidden" name="color_variant_count" id="color_variant_count" value="1">
            </div>
            <!-- Legacy single color fields (first variant auto-populates these) -->
            <input type="hidden" name="color" id="color_primary">
            <input type="hidden" name="color_hex" id="color_hex_primary">
            <input type="hidden" name="color2" id="color2_secondary">
            <div class="col-md-4">
              <label class="form-label">Border Type</label>
              <input type="text" name="border_type" class="form-control" placeholder="e.g. Gold Zari Border">
            </div>

            <!-- Dimensions -->
            <div class="col-md-3">
              <label class="form-label">Saree Length (meters)</label>
              <input type="number" name="saree_length" class="form-control" step="0.25" min="5" max="9" value="5.50">
            </div>
            <div class="col-md-3">
              <label class="form-label">Zari Type</label>
              <select name="zari_type" class="form-select">
                <option value="">None / NA</option>
                <option>Real Zari (Gold)</option>
                <option>Real Zari (Silver)</option>
                <option>Artificial Zari</option>
                <option>Copper Zari</option>
              </select>
            </div>
            <div class="col-md-3">
              <label class="form-label">Transparency</label>
              <select name="transparency" class="form-select">
                <option value="opaque">Opaque</option>
                <option value="semi-sheer">Semi-Sheer</option>
                <option value="sheer">Sheer</option>
              </select>
            </div>
            <div class="col-md-3">
              <label class="form-label">Net Weight (grams)</label>
              <input type="number" name="net_weight" class="form-control" step="50" placeholder="e.g. 650">
            </div>

            <div class="col-md-4">
              <label class="form-label">Sizes <small class="text-muted">(comma separated)</small></label>
              <input type="text" name="sizes" class="form-control" placeholder="S, M, L, XL, XXL">
            </div>

            <!-- Blouse -->
            <div class="col-md-4">
              <label class="form-label">Set Contains</label>
              <select name="set_contains" class="form-select">
                <option value="Saree Only">Saree Only</option>
                <option value="Saree + Blouse Piece">Saree + Blouse Piece</option>
                <option value="Saree + Stitched Blouse">Saree + Stitched Blouse</option>
                <option value="Saree + Blouse + Petticoat">Saree + Blouse + Petticoat</option>
              </select>
            </div>
            <div class="col-md-4 d-flex align-items-end">
              <div class="form-check form-switch mb-2">
                <input class="form-check-input" type="checkbox" name="blouse_included" value="1" id="blouseCheck">
                <label class="form-check-label" for="blouseCheck">Blouse Piece Included</label>
              </div>
            </div>
            <div class="col-md-4">
              <label class="form-label">Blouse Piece Length (meters)</label>
              <input type="number" name="blouse_length" class="form-control" step="0.10" value="0.80">
            </div>

            <!-- Care & Suitability -->
            <div class="col-md-6">
              <label class="form-label">Wash Care</label>
              <select name="wash_care" class="form-select">
                <option value="">Select</option>
                <?= opt($wash_cares, '') ?>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label">Suitable For</label>
              <input type="text" name="suitable_for" class="form-control" placeholder="Women, Girls, Plus Size, Maternity">
            </div>
            <div class="col-md-6">
              <label class="form-label">Return Policy <small class="text-muted">(HTML)</small></label>
              <div id="quill-return-policy"></div>
              <input type="hidden" name="return_policy" value="">
            </div>
            <div class="col-md-6">
              <label class="form-label">Shipping Info <small class="text-muted">(HTML)</small></label>
              <div id="quill-shipping-info"></div>
              <input type="hidden" name="shipping_info" value="">
            </div>

          </div>
        </div>
      </div>

      <?php $product_variants = $product_variants ?? []; $this->load->view('admin/products/_variant_fields', compact('variant_units', 'product_variants')); ?>

      <!-- Price / stock / sale: set per pack under Unit & Variants (optional product-level defaults). -->
      <input type="hidden" name="price" value="0">
      <input type="hidden" name="sale_price" value="">
      <input type="hidden" name="stock" value="0">
      <input type="hidden" name="weight" value="">
      <input type="hidden" name="sale_start_at" value="">
      <input type="hidden" name="sale_end_at" value="">

      <?php $seo = []; $this->load->view('admin/partials/seo_fields', compact('seo')); ?>

    </div>

    <!-- ── RIGHT COLUMN ────────────────────────────────────── -->
    <div class="col-lg-4">

      <!-- Publish -->
      <div class="card sk-table-card shadow-sm mb-3">
        <div class="card-header bg-white border-0 py-3 fw-semibold">Publish</div>
        <div class="card-body">
          <div class="mb-3">
            <label class="form-label">Status</label>
            <select name="status" class="form-select">
              <option value="active">Active (Live)</option>
              <option value="inactive">Inactive</option>
              <option value="draft">Draft</option>
            </select>
          </div>
          <div class="form-check form-switch mb-2">
            <input class="form-check-input" type="checkbox" name="featured" value="1" id="featuredCheck">
            <label class="form-check-label" for="featuredCheck">New Arrival</label>
          </div>
          <div class="form-check form-switch mb-2">
            <input class="form-check-input" type="checkbox" name="special_product" value="1" id="specialProductCheck">
            <label class="form-check-label" for="specialProductCheck">Curated for you</label>
          </div>
          <div class="form-check form-switch mb-2">
            <input type="hidden" name="hot_sale" value="0">
            <input class="form-check-input" type="checkbox" name="hot_sale" value="1" id="hotSaleCheck">
            <label class="form-check-label" for="hotSaleCheck">Offer Collections</label>
          </div>
        </div>
      </div>

      <!-- Thumbnail -->
      <div class="card sk-table-card shadow-sm mb-3">
        <div class="card-header bg-white border-0 py-3 fw-semibold">Main Image</div>
        <div class="card-body text-center">
          <img id="thumbPreview" src="#" alt="Preview"
               class="img-fluid rounded mb-2 border" style="display:none;max-height:200px;object-fit:contain;">
          <div class="border-2 border-dashed rounded p-3 bg-light">
            <i class="bi bi-cloud-upload fs-2 text-muted d-block mb-1"></i>
            <small class="text-muted">JPEG or PNG only, max 8MB</small>
            <input type="file" name="thumbnail" class="form-control form-control-sm mt-2 sk-img-preview-input"
                   data-target="#thumbPreview" accept=".jpg,.jpeg,.png,image/jpeg,image/png">
          </div>
        </div>
      </div>

      <!-- Gallery -->
      <div class="card sk-table-card shadow-sm mb-3">
        <div class="card-header bg-white border-0 py-3 fw-semibold">Gallery Images</div>
        <div class="card-body">
          <small class="text-muted d-block mb-2">Add multiple product photos</small>
          <input type="file" name="images[]" class="form-control form-control-sm" multiple accept=".jpg,.jpeg,.png,image/jpeg,image/png">
          <small class="text-muted">JPEG or PNG only</small>
        </div>
      </div>

      <!-- Quick Tips -->
      <div class="card border-0 bg-warning bg-opacity-10">
        <div class="card-body py-3">
          <p class="fw-semibold text-warning mb-2 small"><i class="bi bi-lightbulb me-1"></i>Product Tips</p>
          <ul class="small text-muted mb-0 ps-3">
            <li>Select the correct unit (kg, gram, box, bottle, etc.)</li>
            <li>Set price and stock on each pack in Unit &amp; Variants</li>
            <li>Use clear product photos</li>
            <li>Fill HSN and tax for compliance</li>
          </ul>
        </div>
      </div>

    </div>
  </div>

  <div class="mt-3 d-flex gap-2 pb-4">
    <button type="submit" class="btn btn-warning fw-semibold px-5 py-2">
      <i class="bi bi-check-lg me-1"></i> Save Product
    </button>
    <a href="<?= site_url('admin/products') ?>" class="btn btn-outline-secondary py-2">Cancel</a>
  </div>

</form>

<script>
document.addEventListener('DOMContentLoaded', function () {
  var catSel = document.querySelector('select[name="category_id"]');
  var subSel = document.getElementById('subcategory_id');
  var subRow = document.getElementById('subcategory-row');
  var ajaxBase = '<?= site_url('admin/products/subcategories/') ?>';

  catSel.addEventListener('change', function () {
    subSel.innerHTML = '<option value="">-- Select Sub Category --</option>';
    subRow.style.display = 'none';
    if (!this.value) return;
    fetch(ajaxBase + this.value)
      .then(function (r) { return r.json(); })
      .then(function (subs) {
        if (subs && subs.length) {
          subs.forEach(function (s) {
            var o = document.createElement('option');
            o.value = s.id;
            o.textContent = s.name;
            subSel.appendChild(o);
          });
          subRow.style.display = '';
        }
      });
  });
});
</script>
