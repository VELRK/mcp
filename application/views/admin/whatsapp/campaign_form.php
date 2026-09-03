<?php
$templates = $templates ?? [];
$customers = $customers ?? [];
$selected = (int)($selected_template_id ?? 0);
$byId = [];
foreach ($templates as $t) {
    $byId[(int)$t['id']] = $t;
}
?>
<link rel="stylesheet" href="<?= base_url('assets/admin/css/whatsapp-cloud.css') ?>">
<div class="sk-page-header d-flex flex-wrap align-items-center justify-content-between gap-2">
  <h5 class="sk-page-title mb-0">New WhatsApp campaign</h5>
  <a class="btn btn-sm btn-outline-secondary" href="<?= site_url('shopkart/whatsapp/campaigns') ?>">All campaigns</a>
</div>

<?php if (empty($ready)): ?>
<div class="alert alert-warning">Connect Meta Cloud API before sending. You can still save a draft audience.</div>
<?php endif; ?>

<form class="card sk-table-card shadow-sm" method="post" action="<?= site_url('admin/whatsapp/campaigns/save') ?>" id="waCampaignForm">
  <div class="card-body">
    <div class="row g-3">
      <div class="col-md-6">
        <label class="form-label">Campaign name</label>
        <input type="text" name="name" class="form-control" required placeholder="May promo blast">
      </div>
      <div class="col-md-6">
        <label class="form-label">Template</label>
        <select name="template_id" id="waCampTpl" class="form-select" required>
          <option value="">Choose template</option>
          <?php foreach ($templates as $t): ?>
            <option value="<?= (int)$t['id'] ?>" <?= $selected === (int)$t['id'] ? 'selected' : '' ?>
                    data-body="<?= htmlspecialchars(json_encode((string)($t['body_text'] ?? ''), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), ENT_QUOTES) ?>"
                    data-map="<?= htmlspecialchars(json_encode(sk_wa_cloud_decode_variable_map($t['variable_map'] ?? ''), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), ENT_QUOTES) ?>"
                    data-status="<?= htmlspecialchars($t['status'] ?? '') ?>">
              <?= htmlspecialchars($t['name']) ?> · <?= htmlspecialchars($t['language']) ?> · <?= htmlspecialchars($t['status']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-12">
        <div class="wa-camp-preview" id="waCampPreview">Select a template to see mapped customer fields.</div>
      </div>
      <div class="col-12">
        <label class="form-label">Audience</label>
        <div class="d-flex flex-wrap gap-3">
          <label class="form-check">
            <input class="form-check-input" type="radio" name="audience" value="all" id="audAll" checked>
            <span class="form-check-label">All customers with a phone (<?= (int)($customer_count ?? 0) ?>)</span>
          </label>
          <label class="form-check">
            <input class="form-check-input" type="radio" name="audience" value="selected" id="audSel">
            <span class="form-check-label">Selected customers</span>
          </label>
        </div>
      </div>
    </div>

    <div id="waCustWrap" class="mt-3" style="display:none;">
      <div class="d-flex flex-wrap gap-2 align-items-center mb-2">
        <input type="search" id="waCustFilter" class="form-control form-control-sm" style="max-width:280px" placeholder="Filter this list">
        <label class="form-check mb-0">
          <input class="form-check-input" type="checkbox" id="waCustAll">
          <span class="form-check-label">Select visible</span>
        </label>
        <span class="small text-muted" id="waCustPicked">0 selected</span>
      </div>
      <div class="table-responsive" style="max-height:360px;overflow:auto;">
        <table class="table table-sm table-hover mb-0">
          <thead class="sticky-top bg-white">
            <tr><th></th><th>Name</th><th>Phone</th><th>Email</th></tr>
          </thead>
          <tbody>
            <?php foreach ($customers as $u): ?>
            <tr class="wa-cust-row">
              <td><input type="checkbox" class="form-check-input wa-cust-cb" name="customer_ids[]" value="<?= (int)$u['id'] ?>"></td>
              <td><?= htmlspecialchars($u['name'] ?? '') ?></td>
              <td><?= htmlspecialchars($u['phone'] ?? '') ?></td>
              <td><?= htmlspecialchars($u['email'] ?? '') ?></td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($customers)): ?>
            <tr><td colspan="4" class="text-muted">No customers with a phone number found.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
      <div class="form-text">Showing up to 250 customers. Use “All customers with a phone” for the full list.</div>
    </div>
  </div>
  <div class="card-footer d-flex flex-wrap gap-2">
    <button class="btn btn-outline-secondary" name="send_now" value="0">Save draft</button>
    <button class="btn btn-success" name="send_now" value="1" <?= empty($ready) ? 'disabled' : '' ?>>Save and send</button>
  </div>
</form>
<script>
(function () {
  var modules = <?= json_encode(sk_wa_cloud_customer_modules(), JSON_UNESCAPED_UNICODE) ?>;
  var sel = document.getElementById('waCampTpl');
  var preview = document.getElementById('waCampPreview');
  function showPreview() {
    var opt = sel.options[sel.selectedIndex];
    if (!opt || !opt.value) {
      preview.textContent = 'Select a template to see mapped customer fields.';
      return;
    }
    var body = '';
    var map = {};
    try { body = JSON.parse(opt.getAttribute('data-body') || '""') || ''; } catch (e) { body = ''; }
    try { map = JSON.parse(opt.getAttribute('data-map') || '{}') || {}; } catch (e) { map = {}; }
    var found = [];
    var re = /\{\{\s*(\d+)\s*\}\}/g, m;
    while ((m = re.exec(body))) {
      var n = parseInt(m[1], 10);
      if (n > 0 && found.indexOf(n) === -1) found.push(n);
    }
    found.sort(function (a, b) { return a - b; });
    if (!found.length) {
      preview.innerHTML = '<span class="text-muted">This template has no {{n}} variables.</span>';
      return;
    }
    preview.innerHTML = found.map(function (n) {
      var field = map[String(n)] || '';
      var label = field && modules[field] ? modules[field].label : (field || 'not mapped');
      return '<span class="badge text-bg-light me-1 mb-1">{{' + n + '}} → ' + label + '</span>';
    }).join(' ');
  }
  sel.addEventListener('change', showPreview);
  showPreview();

  var audAll = document.getElementById('audAll');
  var audSel = document.getElementById('audSel');
  var wrap = document.getElementById('waCustWrap');
  function syncAud() { wrap.style.display = audSel.checked ? '' : 'none'; }
  audAll.addEventListener('change', syncAud);
  audSel.addEventListener('change', syncAud);
  syncAud();

  var filter = document.getElementById('waCustFilter');
  var all = document.getElementById('waCustAll');
  var picked = document.getElementById('waCustPicked');
  function rows() { return Array.prototype.slice.call(document.querySelectorAll('.wa-cust-row')); }
  function countPicked() {
    var n = document.querySelectorAll('.wa-cust-cb:checked').length;
    picked.textContent = n + ' selected';
  }
  filter.addEventListener('input', function () {
    var q = filter.value.toLowerCase();
    rows().forEach(function (tr) {
      tr.style.display = tr.textContent.toLowerCase().indexOf(q) !== -1 ? '' : 'none';
    });
  });
  all.addEventListener('change', function () {
    rows().forEach(function (tr) {
      if (tr.style.display === 'none') return;
      var cb = tr.querySelector('.wa-cust-cb');
      if (cb) cb.checked = all.checked;
    });
    countPicked();
  });
  document.querySelectorAll('.wa-cust-cb').forEach(function (cb) {
    cb.addEventListener('change', countPicked);
  });
})();
</script>
