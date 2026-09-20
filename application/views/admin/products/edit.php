<?php
function opt_e($list, $selected) {
    $out = '';
    foreach ($list as $v) {
        $label = is_array($v) ? htmlspecialchars($v['name'] . ($v['state'] ? ' (' . $v['state'] . ')' : '')) : htmlspecialchars($v);
        $value = is_array($v) ? htmlspecialchars($v['name']) : htmlspecialchars($v);
        $sel   = $value === htmlspecialchars($selected ?? '') ? 'selected' : '';
        $out  .= "<option value=\"$value\" $sel>$label</option>";
    }
    return $out;
}
$p = $product; // shorthand
$saleStartLocal = !empty($p['sale_start_at']) ? date('Y-m-d\TH:i', strtotime($p['sale_start_at'])) : '';
$saleEndLocal = !empty($p['sale_end_at']) ? date('Y-m-d\TH:i', strtotime($p['sale_end_at'])) : '';
?>

<div class="sk-page-header">
  <h5 class="sk-page-title"><i class="bi bi-pencil me-2 text-warning"></i>Edit: <?= htmlspecialchars($p['name']) ?></h5>
  <a href="<?= site_url('admin/products') ?>" class="btn btn-sm btn-outline-secondary">
    <i class="bi bi-arrow-left me-1"></i> Back
  </a>
</div>

<form action="<?= site_url('admin/products/update/'.$p['id']) ?>" method="POST" enctype="multipart/form-data">
  <?php /* First file input so PHP max_file_uploads cannot drop the main photo behind variant/color files. */ ?>
  <input type="file" name="thumbnail" id="productThumbnail" class="sk-img-preview-input"
         data-target="#thumbPreview" accept="image/*"
         style="position:absolute;left:-9999px;width:1px;height:1px;overflow:hidden;">

  <div class="row g-3">

    <!-- ── LEFT COLUMN ─────────────────────────────────────── -->
    <div class="col-lg-8">

      <!-- Basic Info -->
      <div class="card sk-table-card shadow-sm mb-3">
        <div class="card-header bg-white border-0 py-3 fw-semibold">
          <i class="bi bi-info-circle me-1 text-warning"></i> Basic Information
        </div>
        <div class="card-body">
          <div class="mb-3">
            <label class="form-label">Product Name <span class="text-danger">*</span></label>
            <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($p['name']) ?>" required>
          </div>
          <div class="row g-2">
            <div class="col-md-4">
              <label class="form-label">SKU</label>
              <input type="text" name="sku" class="form-control" value="<?= htmlspecialchars($p['sku'] ?? '') ?>">
            </div>
            <div class="col-md-4">
              <label class="form-label">Category <span class="text-danger">*</span></label>
              <select name="category_id" class="form-select" required>
                <?php foreach ($categories as $c): ?>
                  <option value="<?= $c['id'] ?>" <?= $c['id']==$p['category_id']?'selected':'' ?>>
                    <?= htmlspecialchars($c['name']) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label">Brand</label>
              <select name="brand_id" class="form-select">
                <option value="">No Brand</option>
                <?php foreach ($brands as $b): ?>
                  <option value="<?= $b['id'] ?>" <?= $b['id']==$p['brand_id']?'selected':'' ?>>
                    <?= htmlspecialchars($b['name']) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>
          <div class="row g-2 mt-1" id="subcategory-row" <?= empty($subcategories) ? 'style="display:none;"' : '' ?>>
            <div class="col-md-4">
              <label class="form-label">Sub Category</label>
              <select name="subcategory_id" id="subcategory_id" class="form-select">
                <option value="">-- Select Sub Category --</option>
                <?php foreach ($subcategories as $sub): ?>
                  <option value="<?= $sub['id'] ?>" <?= $sub['id'] == ($p['subcategory_id'] ?? '') ? 'selected' : '' ?>>
                    <?= htmlspecialchars($sub['name']) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>
          <div class="mt-3">
            <label class="form-label">Subtitle</label>
            <input type="text" name="subtitle" class="form-control" value="<?= htmlspecialchars($p['subtitle'] ?? '') ?>">
          </div>
          <div class="mt-3">
            <label class="form-label">Short Description</label>
            <div id="quill-short-desc"></div>
            <input type="hidden" name="short_desc" value="<?= htmlspecialchars($p['short_desc'] ?? '') ?>">
          </div>
          <div class="mt-3">
            <label class="form-label">Full Description <small class="text-muted">(HTML editor)</small></label>
            <div id="quill-description"></div>
            <input type="hidden" name="description" value="<?= htmlspecialchars($p['description'] ?? '') ?>">
          </div>
          <div class="mt-3">
            <label class="form-label">Tags</label>
            <input type="text" name="tags" class="form-control" value="<?= htmlspecialchars($p['tags'] ?? '') ?>">
          </div>
          <!-- Product Payment Link & Razorpay Generator -->
          <div class="mt-3 p-3 rounded-3 bg-light border">
            <div class="mb-2">
              <label class="form-label fw-bold mb-0">
                <i class="bi bi-credit-card-2-front text-primary me-1"></i> Payment Link <span class="text-danger">*</span>
              </label>
            </div>

            <!-- Active link banner (shown when created or populated) -->
            <div id="paymentLinkBanner" class="alert alert-success d-flex align-items-center justify-content-between p-3 mb-2 rounded-3 border-success-subtle" style="<?= empty($p['payment_link']) ? 'display:none;' : '' ?>">
              <div class="d-flex align-items-center gap-2 text-truncate me-2">
                <i class="bi bi-check-circle-fill text-success fs-4 flex-shrink-0"></i>
                <div class="overflow-hidden">
                  <div class="fw-semibold small text-success">Active Payment Link</div>
                  <a href="<?= htmlspecialchars($p['payment_link'] ?? '#') ?>" target="_blank" rel="noopener noreferrer" id="paymentLinkAnchor" class="small text-decoration-underline text-break fw-medium">
                    <span id="paymentLinkText"><?= htmlspecialchars($p['payment_link'] ?? '') ?></span> <i class="bi bi-box-arrow-up-right ms-1 small"></i>
                  </a>
                </div>
              </div>
              <div class="d-flex gap-2 flex-shrink-0">
                <a href="<?= htmlspecialchars($p['payment_link'] ?? '#') ?>" target="_blank" id="paymentLinkOpenBtn" class="btn btn-sm btn-success">
                  <i class="bi bi-box-arrow-up-right me-1"></i>Pay / Open
                </a>
                <button type="button" class="btn btn-sm btn-outline-secondary" id="btnCopyLink" onclick="copyPaymentLink(this)">
                  <i class="bi bi-clipboard"></i> Copy
                </button>
              </div>
            </div>

            <div id="paymentLinkFieldWrap" class="input-group" style="display:none;">
              <span class="input-group-text"><i class="bi bi-link-45deg"></i></span>
              <input type="url" name="payment_link" id="payment_link_input" class="form-control" placeholder="https://rzp.io/rzp/... or https://buy.stripe.com/..." required value="<?= htmlspecialchars($p['payment_link'] ?? '') ?>">
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
                    <input type="number" step="0.01" min="1" id="rzp_amount_input" class="form-control" placeholder="e.g. 1499.00" value="<?= htmlspecialchars((string)($p['sale_price'] ?: ($p['price'] ?: ''))) ?>">
                  </div>
                </div>
                <div class="col-md-5">
                  <button type="button" class="btn btn-sm btn-primary w-100" id="btnCreateRzp" onclick="createRazorpayPaymentLink()">
                    <i class="bi bi-plus-circle me-1"></i> <span id="btnCreateRzpText"><?= empty($p['payment_link']) ? 'Create Link' : 'Update Link' ?></span>
                  </button>
                </div>
              </div>
              <input type="hidden" id="rzp_title_input" value="<?= htmlspecialchars($p['name'] ?? '') ?>">
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
                <?= opt_e($saree_styles, $p['saree_type'] ?? '') ?>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label">Fabric</label>
              <select name="fabric" class="form-select">
                <option value="">Select Fabric</option>
                <?= opt_e($fabrics, $p['fabric'] ?? '') ?>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label">Occasion</label>
              <select name="occasion" class="form-select">
                <option value="">Select Occasion</option>
                <?= opt_e($occasions, $p['occasion'] ?? '') ?>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label">Work Type</label>
              <select name="work_type" class="form-select">
                <option value="">Select Work</option>
                <?= opt_e($work_types, $p['work_type'] ?? '') ?>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label">Origin State</label>
              <select name="origin_state" class="form-select">
                <option value="">Select State</option>
                <?= opt_e($origin_states, $p['origin_state'] ?? '') ?>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label">Weave Type</label>
              <select name="weave_type" class="form-select">
                <option value="">Select</option>
                <?php foreach (['Hand-woven','Power-loom','Machine-woven','Handblock Printed','Handpainted'] as $w): ?>
                  <option <?= ($p['weave_type']??'')===$w?'selected':'' ?>><?= $w ?></option>
                <?php endforeach; ?>
              </select>
            </div>

            <!-- Color Variants -->
            <div class="col-12">
              <label class="form-label fw-semibold">Color Variants <small class="text-muted fw-normal">(each color gets its own swatch image)</small></label>
              <div id="color-variants-list">
                <?php
                $existingColors = [];
                if (!empty($p['colors_json'])) {
                    $existingColors = is_array($p['colors_json']) ? $p['colors_json'] : json_decode($p['colors_json'], true) ?? [];
                }
                if (empty($existingColors) && !empty($p['color'])) {
                    $existingColors[] = ['name' => $p['color'], 'hex' => $p['color_hex'] ?? '', 'image' => ''];
                    if (!empty($p['color2'])) $existingColors[] = ['name' => $p['color2'], 'hex' => '', 'image' => ''];
                }
                foreach ($existingColors as $ci => $cv):
                ?>
                <div class="color-variant-row d-flex gap-2 align-items-end mb-2">
                  <div style="flex:2">
                    <input type="text" name="color_variants[<?= $ci ?>][name]" class="form-control form-control-sm"
                           value="<?= htmlspecialchars($cv['name'] ?? '') ?>" placeholder="Color name">
                  </div>
                  <div style="flex:0 0 44px">
                    <input type="color" name="color_variants[<?= $ci ?>][hex]" class="form-control form-control-color form-control-sm w-100"
                           value="<?= htmlspecialchars(!empty($cv['hex']) ? $cv['hex'] : '#cccccc') ?>">
                  </div>
                  <div style="flex:3">
                    <?php if (!empty($cv['image'])): ?>
                      <div class="d-flex align-items-center gap-1 mb-1">
                        <img src="<?= base_url($cv['image']) ?>" width="32" height="32" class="rounded border" style="object-fit:cover">
                        <small class="text-muted text-truncate" style="max-width:120px"><?= basename($cv['image']) ?></small>
                      </div>
                    <?php endif; ?>
                    <input type="file" name="color_variant_images[]" class="form-control form-control-sm" accept="image/*">
                    <input type="hidden" name="color_variants[<?= $ci ?>][existing_image]" value="<?= htmlspecialchars($cv['image'] ?? '') ?>">
                  </div>
                  <?php if ($ci > 0): ?>
                  <button type="button" class="btn btn-sm btn-outline-danger remove-color-row" style="flex:0 0 auto">
                    <i class="bi bi-trash"></i>
                  </button>
                  <?php endif; ?>
                </div>
                <?php endforeach; ?>
                <?php if (empty($existingColors)): ?>
                <div class="color-variant-row d-flex gap-2 align-items-end mb-2">
                  <div style="flex:2"><input type="text" name="color_variants[0][name]" class="form-control form-control-sm" placeholder="Color name"></div>
                  <div style="flex:0 0 44px"><input type="color" name="color_variants[0][hex]" class="form-control form-control-color form-control-sm w-100" value="#cccccc"></div>
                  <div style="flex:3"><input type="file" name="color_variant_images[]" class="form-control form-control-sm" accept="image/"></div>
                </div>
                <?php endif; ?>
              </div>
              <button type="button" class="btn btn-sm btn-outline-secondary mt-1" id="add-color-variant">
                <i class="bi bi-plus-lg me-1"></i> Add Color
              </button>
              <input type="hidden" name="color_variant_count" id="color_variant_count" value="<?= max(1, count($existingColors)) ?>">
            </div>
            <input type="hidden" name="color" value="<?= htmlspecialchars($p['color'] ?? '') ?>">
            <input type="hidden" name="color_hex" value="<?= htmlspecialchars($p['color_hex'] ?? '') ?>">
            <input type="hidden" name="color2" value="<?= htmlspecialchars($p['color2'] ?? '') ?>">
            <div class="col-md-4">
              <label class="form-label">Border Type</label>
              <input type="text" name="border_type" class="form-control" value="<?= htmlspecialchars($p['border_type'] ?? '') ?>">
            </div>

            <div class="col-md-3">
              <label class="form-label">Saree Length (m)</label>
              <input type="number" name="saree_length" class="form-control" step="0.25" value="<?= $p['saree_length'] ?? '5.50' ?>">
            </div>
            <div class="col-md-3">
              <label class="form-label">Zari Type</label>
              <select name="zari_type" class="form-select">
                <option value="">None / NA</option>
                <?php foreach (['Real Zari (Gold)','Real Zari (Silver)','Artificial Zari','Copper Zari'] as $z): ?>
                  <option <?= ($p['zari_type']??'')===$z?'selected':'' ?>><?= $z ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-3">
              <label class="form-label">Transparency</label>
              <select name="transparency" class="form-select">
                <?php foreach (['opaque','semi-sheer','sheer'] as $t): ?>
                  <option value="<?= $t ?>" <?= ($p['transparency']??'opaque')===$t?'selected':'' ?>><?= ucfirst($t) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-3">
              <label class="form-label">Net Weight (grams)</label>
              <input type="number" name="net_weight" class="form-control" step="50" value="<?= $p['net_weight'] ?? '' ?>">
            </div>

            <div class="col-md-4">
              <label class="form-label">Sizes <small class="text-muted">(comma separated)</small></label>
              <input type="text" name="sizes" class="form-control" value="<?= htmlspecialchars(implode(', ', $p['sizes'] ?? [])) ?>" placeholder="S, M, L, XL, XXL">
            </div>

            <div class="col-md-4">
              <label class="form-label">Set Contains</label>
              <select name="set_contains" class="form-select">
                <?php foreach (['Saree Only','Saree + Blouse Piece','Saree + Stitched Blouse','Saree + Blouse + Petticoat'] as $sc): ?>
                  <option <?= ($p['set_contains']??'')===$sc?'selected':'' ?>><?= $sc ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-4 d-flex align-items-end pb-1">
              <div class="form-check form-switch">
                <input class="form-check-input" type="checkbox" name="blouse_included" value="1"
                       id="blouseCheck" <?= $p['blouse_included'] ? 'checked' : '' ?>>
                <label class="form-check-label" for="blouseCheck">Blouse Piece Included</label>
              </div>
            </div>
            <div class="col-md-4">
              <label class="form-label">Blouse Length (m)</label>
              <input type="number" name="blouse_length" class="form-control" step="0.10"
                     value="<?= $p['blouse_length'] ?? '0.80' ?>">
            </div>

            <div class="col-md-6">
              <label class="form-label">Wash Care</label>
              <select name="wash_care" class="form-select">
                <option value="">Select</option>
                <?= opt_e($wash_cares, $p['wash_care'] ?? '') ?>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label">Suitable For</label>
              <input type="text" name="suitable_for" class="form-control" value="<?= htmlspecialchars($p['suitable_for'] ?? '') ?>">
            </div>
            <div class="col-md-6">
              <label class="form-label">Return Policy <small class="text-muted">(HTML)</small></label>
              <div id="quill-return-policy"></div>
              <input type="hidden" name="return_policy" value="<?= htmlspecialchars($p['return_policy'] ?? '') ?>">
            </div>
            <div class="col-md-6">
              <label class="form-label">Shipping Info <small class="text-muted">(HTML)</small></label>
              <div id="quill-shipping-info"></div>
              <input type="hidden" name="shipping_info" value="<?= htmlspecialchars($p['shipping_info'] ?? '') ?>">
            </div>

          </div>
        </div>
      </div>

      <?php $seo = $p; $this->load->view('admin/partials/seo_fields', compact('seo')); ?>

      <?php $product_variants = $product_variants ?? []; $this->load->view('admin/products/_variant_fields', compact('variant_units', 'product_variants')); ?>

      <!-- Price / stock / sale stay as saved values. Change them on each pack in Unit & Variants. -->
      <input type="hidden" name="price" value="<?= htmlspecialchars((string)$p['price']) ?>">
      <input type="hidden" name="sale_price" value="<?= htmlspecialchars((string)($p['sale_price'] ?? '')) ?>">
      <input type="hidden" name="stock" value="<?= htmlspecialchars((string)$p['stock']) ?>">
      <input type="hidden" name="weight" value="<?= htmlspecialchars((string)($p['weight'] ?? '')) ?>">
      <input type="hidden" name="sale_start_at" value="<?= htmlspecialchars($saleStartLocal) ?>">
      <input type="hidden" name="sale_end_at" value="<?= htmlspecialchars($saleEndLocal) ?>">
    </div>

    <!-- ── RIGHT COLUMN ────────────────────────────────────── -->
    <div class="col-lg-4">

      <div class="card sk-table-card shadow-sm mb-3">
        <div class="card-header bg-white border-0 py-3 fw-semibold">Publish</div>
        <div class="card-body">
          <div class="mb-3">
            <label class="form-label">Status</label>
            <select name="status" class="form-select">
              <?php foreach (['active','inactive','draft'] as $s): ?>
                <option value="<?= $s ?>" <?= $p['status']===$s?'selected':'' ?>><?= ucfirst($s) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-check form-switch">
            <input class="form-check-input" type="checkbox" name="featured" value="1" id="featuredCheck"
                   <?= $p['featured'] ? 'checked' : '' ?>>
            <label class="form-check-label" for="featuredCheck">New Arrival</label>
          </div>
          <div class="form-check form-switch mt-2">
            <input class="form-check-input" type="checkbox" name="special_product" value="1" id="specialProductCheck"
                   <?= !empty($p['special_product']) ? 'checked' : '' ?>>
            <label class="form-check-label" for="specialProductCheck">Curated for you</label>
          </div>
          <div class="form-check form-switch mt-2">
            <input type="hidden" name="hot_sale" value="0">
            <input class="form-check-input" type="checkbox" name="hot_sale" value="1" id="hotSaleCheck"
                   <?= !empty($p['hot_sale']) ? 'checked' : '' ?>>
            <label class="form-check-label" for="hotSaleCheck">Offer Collections</label>
          </div>
        </div>
      </div>

      <div class="card sk-table-card shadow-sm mb-3">
        <div class="card-header bg-white border-0 py-3 fw-semibold">Main Image</div>
        <div class="card-body text-center">
          <?php if ($p['thumbnail']): ?>
            <img src="<?= base_url($p['thumbnail']) ?>?v=<?= (int)@filemtime(FCPATH . $p['thumbnail']) ?>" class="img-fluid rounded mb-2 border"
                 style="max-height:180px;object-fit:contain;" id="thumbPreview">
          <?php else: ?>
            <img id="thumbPreview" src="#" style="display:none;max-height:180px;" class="img-fluid rounded mb-2 border">
          <?php endif; ?>
          <button type="button" class="btn btn-sm btn-outline-secondary w-100"
                  onclick="document.getElementById('productThumbnail').click()">
            Choose image
          </button>
          <small class="text-muted">JPG, PNG, GIF or WebP, max 8MB. Leave blank to keep current.</small>
        </div>
      </div>

      <div class="card sk-table-card shadow-sm mb-3">
        <div class="card-header bg-white border-0 py-3 fw-semibold">Gallery</div>
        <div class="card-body">
          <?php if (!empty($p['images'])): ?>
            <div class="d-flex flex-wrap gap-2 mb-3" id="galleryGrid">
              <?php foreach ($p['images'] as $img): ?>
                <div class="position-relative" id="imgWrap-<?= $img['id'] ?>" style="width:72px;height:72px;">
                  <img src="<?= base_url($img['image']) ?>" width="72" height="72"
                       class="rounded border" style="object-fit:cover;width:72px;height:72px;">
                  <button type="button"
                    onclick="deleteGalleryImage(<?= $img['id'] ?>, <?= $p['id'] ?>)"
                    class="position-absolute top-0 end-0 btn btn-danger btn-sm p-0 d-flex align-items-center justify-content-center"
                    style="width:20px;height:20px;border-radius:50%;font-size:10px;line-height:1;"
                    title="Remove image">
                    <i class="bi bi-x"></i>
                  </button>
                </div>
              <?php endforeach; ?>
            </div>
          <?php else: ?>
            <p class="text-muted small mb-2">No gallery images yet.</p>
          <?php endif; ?>
          <input type="file" name="images[]" class="form-control form-control-sm" multiple accept="image/*">
          <small class="text-muted">Add new images to the gallery</small>
        </div>
      </div>

      <!-- Product variant summary (hidden legacy saree card) -->
      <div class="card border-0 bg-success bg-opacity-10 d-none">
        <div class="card-body py-3">
          <p class="fw-semibold text-success mb-2 small"><i class="bi bi-check2-circle me-1"></i>Saree Details</p>
          <div class="small text-muted">
            <div><b>Type:</b> <?= $p['saree_type'] ?: '—' ?></div>
            <div><b>Fabric:</b> <?= $p['fabric'] ?: '—' ?></div>
            <div><b>Color:</b> <?= $p['color'] ?: '—' ?></div>
            <div><b>Occasion:</b> <?= $p['occasion'] ?: '—' ?></div>
          </div>
        </div>
      </div>

    </div>
  </div>

  <div class="mt-3 d-flex gap-2 pb-4">
    <button type="submit" class="btn btn-warning fw-semibold px-5 py-2">
      <i class="bi bi-check-lg me-1"></i> Update Product
    </button>
    <a href="<?= site_url('admin/products') ?>" class="btn btn-outline-secondary">Cancel</a>
  </div>

</form>

<script>
function deleteGalleryImage(imageId, productId) {
  if (!confirm('Remove this image from the gallery?')) return;
  fetch('<?= site_url('admin/products/delete_image') ?>/' + imageId + '/' + productId, { method: 'POST' })
    .then(function(r) { return r.json(); })
    .then(function(res) {
      if (res.success) {
        var el = document.getElementById('imgWrap-' + imageId);
        if (el) el.remove();
      }
    });
}

document.addEventListener('DOMContentLoaded', function () {
  var catSel = document.querySelector('select[name="category_id"]');
  var subSel = document.getElementById('subcategory_id');
  var subRow = document.getElementById('subcategory-row');
  var ajaxBase = '<?= site_url('admin/products/subcategories/') ?>';
  var currentSubId = '<?= (int)($p['subcategory_id'] ?? 0) ?>';

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
  var productId = '<?= (int)($p['id'] ?? 0) ?>';

  alertBox.style.display = 'none';
  btn.disabled = true;
  var origText = btnText.innerHTML;
  btnText.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Creating...';

  var fd = new FormData();
  fd.append('amount', amount);
  fd.append('product_name', title);
  fd.append('product_id', productId);

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

      var noAlert = document.getElementById('noPaymentLinkAlert');
      if (noAlert) noAlert.style.display = 'none';

      alertBox.className = 'alert alert-success p-2 mt-2 mb-0';
      alertBox.innerHTML = '<i class="bi bi-check-circle-fill me-1"></i> Razorpay payment link created for ' + (data.currency || '₹') + ' ' + Number(data.amount).toFixed(2) + '! Form is updated. Remember to click Update Product to save.';
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

