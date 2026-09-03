<?php
$timings = $store['store_timings'] ?? [];
$holidays = $store['holiday_settings']['dates'] ?? [];
$social = $store['social_links'] ?? [];
$delivery = $store['delivery_settings'] ?? [];
?>

<div class="sk-page-header d-flex justify-content-between align-items-center flex-wrap gap-2">
  <h5 class="sk-page-title mb-0"><i class="bi bi-shop me-2 text-primary"></i><?= htmlspecialchars($title) ?></h5>
  <?php if (empty($impersonating) && ($admin['role'] ?? '') === 'superadmin'): ?>
  <a href="<?= site_url('admin/vendors/view/'.$vendor['id']) ?>" class="btn btn-sm btn-outline-secondary">Back to Vendor</a>
  <?php endif; ?>
</div>

<div class="alert alert-light border mb-3 d-flex flex-wrap align-items-center justify-content-between gap-2">
  <div class="small mb-0">
    <i class="bi bi-whatsapp text-success me-1"></i>
    Customer order status WhatsApp alerts are configured under <strong>Settings → Order WhatsApp</strong> (platform Askeva).
  </div>
  <a href="<?= site_url('admin/settings?tab=whatsapp') ?>" class="btn btn-sm btn-outline-success">Open Order WhatsApp</a>
</div>

<form method="post" action="<?= site_url('admin/stores/update/'.$vendor['id']) ?>" enctype="multipart/form-data" class="row g-3">
  <div class="col-lg-8">
    <div class="card shadow-sm mb-3">
      <div class="card-header fw-semibold">Store Identity &amp; Invoice Details</div>
      <div class="card-body row g-3">
        <div class="col-md-6">
          <label class="form-label">Store Name</label>
          <input type="text" name="store_name" class="form-control" value="<?= htmlspecialchars($store['store_name'] ?? '') ?>" required>
        </div>
        <div class="col-md-6">
          <label class="form-label">Tax ID</label>
          <input type="text" name="gst_vat" class="form-control" value="<?= htmlspecialchars($store['gst_vat'] ?? '') ?>" placeholder="Shown on tax invoices">
        </div>
        <div class="col-md-6">
          <label class="form-label">PAN</label>
          <input type="text" name="pan_no" class="form-control" value="<?= htmlspecialchars($store['pan_no'] ?? '') ?>">
        </div>
        <div class="col-md-4">
          <label class="form-label">State / Region Code</label>
          <input type="text" name="state_code" class="form-control" value="<?= htmlspecialchars($store['state_code'] ?? '') ?>" placeholder="Optional">
        </div>
        <div class="col-md-4">
          <label class="form-label">Invoice Prefix</label>
          <input type="text" name="invoice_prefix" class="form-control" value="<?= htmlspecialchars($store['invoice_prefix'] ?? 'INV') ?>" maxlength="20">
        </div>
        <div class="col-md-4">
          <label class="form-label">Business Reg. No.</label>
          <input type="text" name="business_reg_no" class="form-control" value="<?= htmlspecialchars($store['business_reg_no'] ?? '') ?>">
        </div>
        <div class="col-12">
          <label class="form-label">Invoice Footer Note</label>
          <textarea name="invoice_footer" class="form-control" rows="2" placeholder="Thank you for shopping with us."><?= htmlspecialchars($store['invoice_footer'] ?? '') ?></textarea>
        </div>
        <div class="col-12">
          <label class="form-label">Description</label>
          <textarea name="description" class="form-control" rows="3"><?= htmlspecialchars($store['description'] ?? '') ?></textarea>
        </div>
        <div class="col-md-6">
          <label class="form-label">Logo</label>
          <input type="file" name="logo" class="form-control" accept="image/*">
          <?php if (!empty($store['logo'])): ?><img src="<?= base_url($store['logo']) ?>" class="mt-2 rounded" height="60"><?php endif; ?>
        </div>
        <div class="col-md-6">
          <label class="form-label">Banner</label>
          <input type="file" name="banner" class="form-control" accept="image/*">
          <?php if (!empty($store['banner'])): ?><img src="<?= base_url($store['banner']) ?>" class="mt-2 rounded w-100" style="max-height:80px;object-fit:cover;"><?php endif; ?>
        </div>
      </div>
    </div>

    <div class="card shadow-sm mb-3">
      <div class="card-header fw-semibold">Contact & Pickup</div>
      <div class="card-body row g-3">
        <div class="col-md-6"><label class="form-label">Contact Email</label><input type="email" name="contact_email" class="form-control" value="<?= htmlspecialchars($store['contact_email'] ?? '') ?>"></div>
        <div class="col-md-6"><label class="form-label">Contact Phone</label><input type="text" name="contact_phone" class="form-control" value="<?= htmlspecialchars($store['contact_phone'] ?? '') ?>"></div>
        <div class="col-md-6"><label class="form-label">Address</label><input type="text" name="pickup_line1" class="form-control" value="<?= htmlspecialchars($store['pickup_line1'] ?? '') ?>"></div>
        <div class="col-md-6"><label class="form-label">Line 2</label><input type="text" name="pickup_line2" class="form-control" value="<?= htmlspecialchars($store['pickup_line2'] ?? '') ?>"></div>
        <div class="col-md-4"><label class="form-label">City</label><input type="text" name="pickup_city" class="form-control" value="<?= htmlspecialchars($store['pickup_city'] ?? '') ?>"></div>
        <div class="col-md-4"><label class="form-label">State</label><input type="text" name="pickup_state" class="form-control" value="<?= htmlspecialchars($store['pickup_state'] ?? '') ?>"></div>
        <div class="col-md-4"><label class="form-label">Pincode</label><input type="text" name="pickup_pincode" class="form-control" value="<?= htmlspecialchars($store['pickup_pincode'] ?? '') ?>"></div>
      </div>
    </div>

    <div class="card shadow-sm mb-3">
      <div class="card-header fw-semibold">Store Timings</div>
      <div class="card-body p-0">
        <table class="table table-sm mb-0">
          <thead><tr><th>Day</th><th>Open</th><th>Close</th><th>Closed</th></tr></thead>
          <tbody>
            <?php foreach ($days as $day):
              $t = $timings[$day] ?? ['open'=>'09:00','close'=>'18:00','closed'=>false];
            ?>
            <tr>
              <td class="text-capitalize ps-3"><?= $day ?></td>
              <td><input type="time" name="timing_<?= $day ?>_open" class="form-control form-control-sm" value="<?= htmlspecialchars($t['open'] ?? '09:00') ?>"></td>
              <td><input type="time" name="timing_<?= $day ?>_close" class="form-control form-control-sm" value="<?= htmlspecialchars($t['close'] ?? '18:00') ?>"></td>
              <td class="text-center"><input type="checkbox" name="timing_<?= $day ?>_closed" value="1" <?= !empty($t['closed']) ? 'checked' : '' ?>></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>

    <div class="card shadow-sm mb-3">
      <div class="card-header fw-semibold">Holidays</div>
      <div class="card-body">
        <label class="form-label">Holiday dates (one per line, YYYY-MM-DD)</label>
        <textarea name="holiday_dates" class="form-control" rows="4"><?= htmlspecialchars(implode("\n", $holidays)) ?></textarea>
      </div>
    </div>
  </div>

  <div class="col-lg-4">
    <div class="card shadow-sm mb-3">
      <div class="card-header fw-semibold">SEO</div>
      <div class="card-body">
        <div class="mb-3"><label class="form-label">Meta Title</label><input type="text" name="meta_title" class="form-control" value="<?= htmlspecialchars($store['meta_title'] ?? '') ?>"></div>
        <div class="mb-0"><label class="form-label">Meta Description</label><textarea name="meta_desc" class="form-control" rows="3"><?= htmlspecialchars($store['meta_desc'] ?? '') ?></textarea></div>
      </div>
    </div>

    <div class="card shadow-sm mb-3">
      <div class="card-header fw-semibold">Social Links</div>
      <div class="card-body">
        <?php foreach (['facebook','instagram','twitter','youtube','website'] as $net): ?>
        <div class="mb-2">
          <label class="form-label text-capitalize small"><?= $net ?></label>
          <input type="url" name="social_<?= $net ?>" class="form-control form-control-sm" value="<?= htmlspecialchars($social[$net] ?? '') ?>" placeholder="https://">
        </div>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="card shadow-sm mb-3">
      <div class="card-header fw-semibold">Delivery Settings</div>
      <div class="card-body">
        <div class="mb-2"><label class="form-label small">Free shipping above (<?= htmlspecialchars(sk_currency_symbol($settings)) ?>)</label><input type="number" step="0.01" name="free_shipping_above" class="form-control form-control-sm" value="<?= htmlspecialchars($delivery['free_shipping_above'] ?? '') ?>"></div>
        <div class="mb-2"><label class="form-label small">Flat rate (<?= htmlspecialchars(sk_currency_symbol($settings)) ?>)</label><input type="number" step="0.01" name="flat_rate" class="form-control form-control-sm" value="<?= htmlspecialchars($delivery['flat_rate'] ?? '') ?>"></div>
        <div class="mb-2"><label class="form-label small">Processing days</label><input type="number" name="processing_days" class="form-control form-control-sm" value="<?= (int)($delivery['processing_days'] ?? 2) ?>"></div>
        <div class="form-check"><input type="checkbox" name="cod_enabled" value="1" class="form-check-input" id="cod" <?= !empty($delivery['cod_enabled']) ? 'checked' : '' ?>><label class="form-check-label" for="cod">COD enabled</label></div>
      </div>
    </div>

    <button type="submit" class="btn btn-primary w-100">Save Store Settings</button>
  </div>
</form>
