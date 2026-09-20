<?php
$currency = $currency ?? '₹';
$vendor_names = $vendor_names ?? [];
?>

<div class="sk-page-header d-flex flex-wrap align-items-center justify-content-between gap-2">
  <div>
    <h5 class="sk-page-title mb-1"><i class="bi bi-tag me-2 text-warning"></i>Client Amounts</h5>
    <small class="text-muted">Global defaults + per-vendor overrides · master only</small>
  </div>
  <a href="<?= site_url('admin/saas-billing') ?>" class="btn btn-sm btn-outline-secondary">
    <i class="bi bi-arrow-left me-1"></i> Billing
  </a>
</div>

<div class="row g-3">
  <div class="col-lg-5">
    <form method="post" action="<?= site_url('admin/saas-billing/amounts/save') ?>" class="card sk-table-card shadow-sm">
      <div class="card-header bg-white border-0 py-3 fw-semibold">Add / update rate</div>
      <div class="card-body">
        <div class="mb-3">
          <label class="form-label">Scope</label>
          <select name="vendor_id" class="form-select">
            <option value="">Global default</option>
            <?php foreach ($vendors as $v): ?>
            <option value="<?= (int)$v['id'] ?>"><?= htmlspecialchars($v['business_name'] ?: ('#'.$v['id'])) ?></option>
            <?php endforeach; ?>
          </select>
          <div class="form-text">Leave Global for platform default. Pick a vendor to override.</div>
        </div>
        <div class="mb-3">
          <label class="form-label">Request type</label>
          <input type="text" name="request_type" class="form-control" value="ai_chat" required list="saasTypes">
          <datalist id="saasTypes">
            <option value="ai_chat">
            <option value="whatsapp_ai">
            <option value="custom">
          </datalist>
        </div>
        <div class="mb-3">
          <label class="form-label">Unit amount</label>
          <div class="input-group">
            <span class="input-group-text"><?= htmlspecialchars($currency) ?></span>
            <input type="number" step="0.0001" min="0" name="unit_amount" class="form-control" value="0.0100" required>
          </div>
        </div>
        <div class="mb-3">
          <label class="form-label">Currency</label>
          <input type="text" name="currency" class="form-control" value="INR" maxlength="8">
        </div>
        <div class="mb-3">
          <label class="form-label">Label</label>
          <input type="text" name="label" class="form-control" placeholder="Optional display name">
        </div>
        <div class="form-check form-switch mb-0">
          <input class="form-check-input" type="checkbox" name="status" value="1" id="amtStatus" checked>
          <label class="form-check-label" for="amtStatus">Active</label>
        </div>
      </div>
      <div class="card-footer bg-white">
        <button type="submit" class="btn btn-warning fw-semibold">Save amount</button>
      </div>
    </form>
  </div>

  <div class="col-lg-7">
    <div class="card sk-table-card shadow-sm">
      <div class="card-header bg-white border-0 py-3 fw-semibold">Rate card</div>
      <div class="card-body p-0">
        <table class="table table-hover align-middle mb-0">
          <thead>
            <tr>
              <th>Scope</th>
              <th>Type</th>
              <th class="text-end">Unit</th>
              <th>Status</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($rows)): ?>
            <tr><td colspan="5" class="text-center text-muted py-4">No rates yet.</td></tr>
            <?php endif; ?>
            <?php foreach ($rows as $r): ?>
            <tr>
              <td>
                <?php if (empty($r['vendor_id'])): ?>
                  <span class="badge bg-primary">Global</span>
                <?php else: ?>
                  <?= htmlspecialchars($vendor_names[(int)$r['vendor_id']] ?? ('#'.$r['vendor_id'])) ?>
                <?php endif; ?>
                <?php if (!empty($r['label'])): ?>
                  <div class="small text-muted"><?= htmlspecialchars($r['label']) ?></div>
                <?php endif; ?>
              </td>
              <td><code><?= htmlspecialchars($r['request_type']) ?></code></td>
              <td class="text-end fw-semibold">
                <?= htmlspecialchars($r['currency'] ?: $currency) ?> <?= number_format((float)$r['unit_amount'], 4) ?>
              </td>
              <td>
                <span class="badge <?= !empty($r['status']) ? 'bg-success' : 'bg-secondary' ?>">
                  <?= !empty($r['status']) ? 'Active' : 'Off' ?>
                </span>
              </td>
              <td class="text-end">
                <a class="btn btn-sm btn-outline-danger"
                   href="<?= site_url('admin/saas-billing/amounts/delete/'.(int)$r['id']) ?>"
                   onclick="return confirm('Delete this rate?');">
                  <i class="bi bi-trash"></i>
                </a>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
    <div class="alert alert-light border small mt-3 mb-0">
      Price resolve order: <strong>vendor override</strong> → <strong>global default</strong> → <strong>0</strong> (usage still logged).
      Set <code>saas_default_vendor_id</code> and <code>saas_billing_token</code> / <code>saas_cron_key</code> in Settings for API + WhatsApp sync + HTTP cron.
    </div>
  </div>
</div>
