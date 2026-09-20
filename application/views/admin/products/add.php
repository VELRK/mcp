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

<div class="sk-page-header">
  <h5 class="sk-page-title"><i class="bi bi-plus-circle me-2 text-warning"></i>Add Product</h5>
  <a href="<?= site_url('admin/products') ?>" class="btn btn-sm btn-outline-secondary">
    <i class="bi bi-arrow-left me-1"></i> Back
  </a>
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
          <!-- Product Payment Link & Razorpay Generator -->
          <div class="mt-3 p-3 rounded-3 bg-light border">
            <div class="mb-2">
              <label class="form-label fw-bold mb-0">
                <i class="bi bi-credit-card-2-front text-primary me-1"></i> Payment Link <span class="text-danger">*</span>
              </label>
            </div>

            <!-- Active link banner (shown when created or populated) -->
            <div id="paymentLinkBanner" class="alert alert-success d-flex align-items-center justify-content-between p-3 mb-2 rounded-3 border-success-subtle" style="display:none;">
              <div class="d-flex align-items-center gap-2 text-truncate me-2">
                <i class="bi bi-check-circle-fill text-success fs-4 flex-shrink-0"></i>
                <div class="overflow-hidden">
                  <div class="fw-semibold small text-success">Active Payment Link</div>
                  <a href="#" target="_blank" rel="noopener noreferrer" id="paymentLinkAnchor" class="small text-decoration-underline text-break fw-medium">
                    <span id="paymentLinkText"></span> <i class="bi bi-box-arrow-up-right ms-1 small"></i>
                  </a>
                </div>
              </div>
              <div class="d-flex gap-2 flex-shrink-0">
                <a href="#" target="_blank" id="paymentLinkOpenBtn" class="btn btn-sm btn-success">
                  <i class="bi bi-box-arrow-up-right me-1"></i>Pay / Open
                </a>
                <button type="button" class="btn btn-sm btn-outline-secondary" id="btnCopyLink" onclick="copyPaymentLink(this)">
                  <i class="bi bi-clipboard"></i> Copy
                </button>
              </div>
            </div>

            <div id="paymentLinkFieldWrap" class="input-group" style="display:none;">
              <span class="input-group-text"><i class="bi bi-link-45deg"></i></span>
              <input type="url" name="payment_link" id="payment_link_input" class="form-control" placeholder="https://rzp.io/rzp/... or https://buy.stripe.com/..." required value="<?= htmlspecialchars(set_value('payment_link', '')) ?>">
              <button class="btn btn-outline-secondary" type="button" onclick="copyPaymentLink(this)" title="Copy Payment Link">
                <i class="bi bi-clipboard"></i>
              </button>
            </div>

            <!-- Razorpay Link Generator Box -->
            <div id="rzpGenPanel" class="p-3 bg-white rounded-3 border mt-3 shadow-sm">
              <div class="d-flex align-items-center justify-content-between mb-2">
                <span class="fw-semibold small text-primary">
                  <i class="bi bi-cpu me-1"></i> Razorpay Payment Link Generator
                </span>
                <span class="badge bg-primary-subtle text-primary border border-primary-subtle small">Direct API</span>
              </div>
              <div class="row g-2 align-items-end">
                <div class="col-md-7">
                  <label class="form-label small mb-1 fw-semibold">Amount to Charge (<?= sk_currency_symbol($settings ?? []) ?>) <span class="text-danger">*</span></label>
                  <div class="input-group input-group-sm">
                    <span class="input-group-text"><?= sk_currency_symbol($settings ?? []) ?></span>
                    <input type="number" step="0.01" min="1" id="rzp_amount_input" class="form-control" placeholder="e.g. 1499.00">
                  </div>
                </div>
                <div class="col-md-5">
                  <button type="button" class="btn btn-sm btn-primary w-100" id="btnCreateRzp" onclick="createRazorpayPaymentLink()">
                    <i class="bi bi-plus-circle me-1"></i> <span id="btnCreateRzpText">Create Link</span>
                  </button>
                </div>
              </div>
              <input type="hidden" id="rzp_title_input" value="">
              <div id="rzpGenAlert" class="mt-2 small" style="display:none;"></div>
            </div>
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
                    <input type="file" name="color_variant_images[]" class="form-control form-control-sm" accept="image/*" title="Swatch image for this color">
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
            <small class="text-muted">JPG, PNG, WebP · Max 2MB</small>
            <input type="file" name="thumbnail" class="form-control form-control-sm mt-2 sk-img-preview-input"
                   data-target="#thumbPreview" accept="image/*">
          </div>
        </div>
      </div>

      <!-- Gallery -->
      <div class="card sk-table-card shadow-sm mb-3">
        <div class="card-header bg-white border-0 py-3 fw-semibold">Gallery Images</div>
        <div class="card-body">
          <small class="text-muted d-block mb-2">Add multiple product photos</small>
          <input type="file" name="images[]" class="form-control form-control-sm" multiple accept="image/*">
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

  var linkInp = document.getElementById('payment_link_input');
  if (linkInp && linkInp.value) {
    showPaymentLinkBanner(linkInp.value);
    showPaymentLinkField(true);
  } else {
    showPaymentLinkField(false);
  }

  var nameInp = document.querySelector('input[name="name"]');
  var titleInp = document.getElementById('rzp_title_input');
  if (titleInp && nameInp) {
    titleInp.value = nameInp.value.trim();
  }

  var priceInp = document.querySelector('input[name="price"]');
  var amountInp = document.getElementById('rzp_amount_input');
  if (priceInp && amountInp && (!amountInp.value || parseFloat(amountInp.value) <= 0)) {
    amountInp.value = priceInp.value;
  }
});

function toggleRzpPanel() {
  var p = document.getElementById('rzpGenPanel');
  if (p) {
    p.style.display = (p.style.display === 'none') ? '' : 'none';
  }
}

function copyPaymentLink(btn) {
  var inp = document.getElementById('payment_link_input');
  if (!inp || !inp.value) {
    alert('No payment link to copy.');
    return;
  }
  navigator.clipboard.writeText(inp.value).then(function() {
    if (btn) {
      var orig = btn.innerHTML;
      btn.innerHTML = '<i class="bi bi-check"></i> Copied';
      setTimeout(function() { btn.innerHTML = orig; }, 2000);
    }
  });
}

function showPaymentLinkField(show) {
  var wrap = document.getElementById('paymentLinkFieldWrap');
  if (!wrap) return;
  wrap.style.display = show ? '' : 'none';
}

function showPaymentLinkBanner(url) {
  var banner = document.getElementById('paymentLinkBanner');
  var anchor = document.getElementById('paymentLinkAnchor');
  var text = document.getElementById('paymentLinkText');
  var openBtn = document.getElementById('paymentLinkOpenBtn');

  if (anchor) anchor.href = url;
  if (text) text.textContent = url;
  if (openBtn) openBtn.href = url;
  if (banner) banner.style.display = '';
  showPaymentLinkField(true);
}

function createRazorpayPaymentLink() {
  var amountInp = document.getElementById('rzp_amount_input');
  var titleInp = document.getElementById('rzp_title_input');
  var nameInp = document.querySelector('input[name="name"]');
  var alertBox = document.getElementById('rzpGenAlert');
  var btn = document.getElementById('btnCreateRzp');
  var btnText = document.getElementById('btnCreateRzpText');

  var amount = amountInp ? parseFloat(amountInp.value) : 0;
  if (!amount || amount <= 0) {
    // Try to fallback to price if available
    var priceInp = document.querySelector('input[name="price"]') || document.querySelector('input[name^="variants"][name$="[price]"]');
    if (priceInp && parseFloat(priceInp.value) > 0) {
      amount = parseFloat(priceInp.value);
      if (amountInp) amountInp.value = amount;
    }
  }

  if (!amount || amount <= 0) {
    alertBox.className = 'alert alert-danger p-2 mt-2 mb-0';
    alertBox.textContent = 'Please enter a valid amount greater than 0.';
    alertBox.style.display = '';
    return;
  }

  var title = (titleInp && titleInp.value.trim()) ? titleInp.value.trim() : (nameInp ? nameInp.value.trim() : 'Product Payment');

  alertBox.style.display = 'none';
  btn.disabled = true;
  var origText = btnText.innerHTML;
  btnText.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Creating...';

  var fd = new FormData();
  fd.append('amount', amount);
  fd.append('product_name', title);
  fd.append('product_id', '0');

  fetch('<?= site_url('admin/products/create_razorpay_payment_link') ?>', {
    method: 'POST',
    body: fd
  })
  .then(function(res) { return res.json(); })
  .then(function(data) {
    btn.disabled = false;
    btnText.innerHTML = origText;

    if (data.success && data.payment_link) {
      var linkInput = document.getElementById('payment_link_input');
      if (linkInput) linkInput.value = data.payment_link;
      showPaymentLinkBanner(data.payment_link);

      alertBox.className = 'alert alert-success p-2 mt-2 mb-0';
      alertBox.innerHTML = '<i class="bi bi-check-circle-fill me-1"></i> Razorpay payment link created for ' + (data.currency || '₹') + ' ' + Number(data.amount).toFixed(2) + '! Link added to form.';
      alertBox.style.display = '';
    } else {
      alertBox.className = 'alert alert-danger p-2 mt-2 mb-0';
      alertBox.textContent = data.message || 'Could not generate Razorpay payment link.';
      alertBox.style.display = '';
    }
  })
  .catch(function(err) {
    btn.disabled = false;
    btnText.innerHTML = origText;
    alertBox.className = 'alert alert-danger p-2 mt-2 mb-0';
    alertBox.textContent = 'Network or server error while calling Razorpay API.';
    alertBox.style.display = '';
  });
}
</script>

