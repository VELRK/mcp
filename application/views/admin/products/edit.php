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

<?php
$page_title = 'Product Overview';
$breadcrumb = [
    ['label' => 'Product List', 'url' => site_url('shopkart/products')],
    $p['name'],
];
$this->load->view('admin/partials/page_title', compact('page_title', 'breadcrumb'));
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <h5 class="mb-0"><?= htmlspecialchars($p['name']) ?></h5>
  <a href="<?= site_url('shopkart/products') ?>" class="btn btn-sm btn-light"><i class="mdi mdi-arrow-left me-1"></i> Back</a>
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

});
</script>

