<?php
$rules = is_array($rules ?? null) ? $rules : [];
$steps = is_array($rules['steps'] ?? null) ? $rules['steps'] : [];
$labels = [
    'catalog' => 'Browse Products',
    'offers'  => 'Offers & Deals',
    'search'  => 'Search Product',
    'track'   => 'Track Order',
    'human'   => 'Talk to Team',
    'address' => 'Ask for a delivery address before payment',
    'payment' => 'Ask for payment after the address is saved',
];
?>
<div class="sk-page-header d-flex justify-content-between align-items-center flex-wrap gap-2">
  <div>
    <h5 class="sk-page-title mb-0">Shop chat steps</h5>
    <small class="text-muted">Each shop sells its own products. These steps are not limited to sarees.</small>
  </div>
</div>

<div class="card shadow-sm">
  <div class="card-body">
    <form method="post" action="<?= site_url('admin/whatsapp/shop-steps') ?>">
      <input type="hidden" name="<?= $this->security->get_csrf_token_name() ?>" value="<?= $this->security->get_csrf_hash() ?>">
      <?php if (!empty($is_super)): ?>
      <div class="mb-3">
        <label class="form-label">Shop</label>
        <select name="vendor_id" class="form-select" onchange="location.href='<?= site_url('admin/whatsapp/shop-steps') ?>?vendor_id='+this.value">
          <option value="0">Choose a shop</option>
          <?php foreach (($vendors ?? []) as $vendor): ?>
          <option value="<?= (int)$vendor['id'] ?>" <?= (int)($vendor_id ?? 0) === (int)$vendor['id'] ? 'selected' : '' ?>><?= htmlspecialchars($vendor['store_name'] ?: ($vendor['business_name'] ?? ('Shop '.$vendor['id']))) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <?php else: ?>
      <input type="hidden" name="vendor_id" value="<?= (int)($vendor_id ?? 0) ?>">
      <?php endif; ?>

      <div class="mb-3">
        <label class="form-label">What this shop sells</label>
        <input type="text" name="product_label" class="form-control" maxlength="40" value="<?= htmlspecialchars((string)($rules['product_label'] ?? 'products')) ?>" placeholder="products">
        <div class="form-text">Saved with this shop. The welcome menu does not send a product photo, voice note, or product caption.</div>
      </div>
      <div class="mb-3">
        <label class="form-label">Welcome message</label>
        <textarea name="welcome_text" class="form-control" rows="3" maxlength="500" placeholder="Leave blank for: Welcome to the shop. Choose an option."><?= htmlspecialchars((string)($rules['welcome_text'] ?? '')) ?></textarea>
      </div>

      <div class="mb-3">
        <label class="form-label">Steps</label>
        <?php foreach ($steps as $step):
            $key = (string)($step['key'] ?? '');
            if ($key === '') continue;
        ?>
        <div class="border rounded p-2 mb-2">
          <div class="form-check">
            <input class="form-check-input" type="checkbox" name="step_enabled[]" value="<?= htmlspecialchars($key) ?>" id="step_<?= htmlspecialchars($key) ?>" <?= !empty($step['enabled']) ? 'checked' : '' ?>>
            <label class="form-check-label" for="step_<?= htmlspecialchars($key) ?>"><?= htmlspecialchars($labels[$key] ?? $key) ?></label>
          </div>
          <input type="text" name="step_title[<?= htmlspecialchars($key) ?>]" class="form-control form-control-sm mt-2" maxlength="24" value="<?= htmlspecialchars((string)($step['title'] ?? '')) ?>">
        </div>
        <?php endforeach; ?>
        <div class="form-text">Browse Products, Offers &amp; Deals, Search Product, Track Order, and Talk to Team appear in the main menu. Browse Products shows all products. Address and payment stay in checkout.</div>
      </div>
      <button class="btn btn-warning" <?= (int)($vendor_id ?? 0) < 1 ? 'disabled' : '' ?>>Save steps</button>
    </form>
  </div>
</div>
