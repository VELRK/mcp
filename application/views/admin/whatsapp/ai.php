<?php
$ai = $ai ?? [];
$vid = (int)($vendor_id ?? 0);
$isVendor = (($admin['role'] ?? '') === 'vendor');
?>
<div class="sk-page-header d-flex flex-wrap align-items-center justify-content-between gap-2">
  <div>
    <h5 class="sk-page-title mb-0"><i class="bi bi-stars me-2 text-success"></i>WhatsApp AI replies</h5>
    <div class="small text-muted">Each vendor uses their own OpenAI or Gemini key. Keys are not shared.</div>
  </div>
  <a href="<?= site_url('shopkart/whatsapp') ?>" class="btn btn-sm btn-outline-secondary">Inbox</a>
</div>

<?php if (!$isVendor): ?>
<form method="get" action="<?= site_url('shopkart/whatsapp/ai') ?>" class="card sk-table-card shadow-sm mb-3">
  <div class="card-body d-flex flex-wrap gap-2 align-items-end">
    <div>
      <label class="form-label">Vendor</label>
      <select name="vendor_id" class="form-select" onchange="this.form.submit()">
        <option value="">Select a vendor</option>
        <?php foreach (($vendors ?? []) as $v): ?>
          <?php $label = trim((string)($v['business_name'] ?? '')) ?: trim((string)($v['name'] ?? '')); ?>
          <option value="<?= (int)$v['id'] ?>" <?= $vid === (int)$v['id'] ? 'selected' : '' ?>>
            #<?= (int)$v['id'] ?> <?= htmlspecialchars($label !== '' ? $label : 'Vendor') ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
  </div>
</form>
<?php endif; ?>

<?php if ($vid < 1): ?>
<div class="alert alert-warning">Select a vendor to save that vendor’s OpenAI or Gemini key.</div>
<?php else: ?>
<form method="post" action="<?= site_url('shopkart/whatsapp/ai/save') ?>" class="card sk-table-card shadow-sm">
  <input type="hidden" name="vendor_id" value="<?= $vid ?>">
  <div class="card-body">
    <div class="form-check form-switch mb-3">
      <input class="form-check-input" type="checkbox" name="enabled" value="1" id="aiOn"
        <?= !empty($ai['enabled']) && $ai['enabled'] !== '0' ? 'checked' : '' ?>>
      <label class="form-check-label" for="aiOn">Reply to this vendor’s WhatsApp chats with AI</label>
    </div>
    <div class="row g-3">
      <div class="col-md-4">
        <label class="form-label">Provider</label>
        <select name="provider" class="form-select">
          <option value="openai" <?= (($ai['provider'] ?? 'openai') === 'openai') ? 'selected' : '' ?>>OpenAI</option>
          <option value="gemini" <?= (($ai['provider'] ?? '') === 'gemini') ? 'selected' : '' ?>>Gemini</option>
        </select>
      </div>
      <div class="col-md-4">
        <label class="form-label">OpenAI model</label>
        <input type="text" name="openai_model" class="form-control font-monospace"
               value="<?= htmlspecialchars($ai['openai_model'] ?? 'gpt-4.1-mini') ?>">
      </div>
      <div class="col-md-4">
        <label class="form-label">Gemini model</label>
        <input type="text" name="gemini_model" class="form-control font-monospace"
               value="<?= htmlspecialchars($ai['gemini_model'] ?? 'gemini-3.8-flash') ?>">
      </div>
      <div class="col-md-6">
        <label class="form-label">OpenAI API key</label>
        <input type="password" name="openai_key" class="form-control font-monospace" autocomplete="new-password" value=""
               placeholder="<?= !empty($ai['has_openai']) ? 'Saved — leave blank to keep' : 'sk-...' ?>">
      </div>
      <div class="col-md-6">
        <label class="form-label">Gemini API key</label>
        <input type="password" name="gemini_key" class="form-control font-monospace" autocomplete="new-password" value=""
               placeholder="<?= !empty($ai['has_gemini']) ? 'Saved — leave blank to keep' : 'AIza...' ?>">
      </div>
    </div>
    <p class="form-text mt-3 mb-0">A customer message on this vendor’s WhatsApp number uses only this key. The platform key in Settings is not used for vendor chats.</p>
  </div>
  <div class="card-footer bg-white">
    <button type="submit" class="btn btn-success">Save AI credentials</button>
  </div>
</form>
<?php endif; ?>
