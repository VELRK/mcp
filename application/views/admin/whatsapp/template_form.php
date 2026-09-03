<?php
$row = $row ?? null;
$id = (int)($row['id'] ?? 0);
$modules = $customer_modules ?? sk_wa_cloud_customer_modules();
$savedMap = sk_wa_cloud_decode_variable_map($row['variable_map'] ?? '');
$groups = [];
foreach ($modules as $key => $mod) {
    $groups[$mod['group']][$key] = $mod;
}
?>
<link rel="stylesheet" href="<?= base_url('assets/admin/css/whatsapp-cloud.css') ?>">
<div class="sk-page-header d-flex flex-wrap align-items-center justify-content-between gap-2">
  <h5 class="sk-page-title mb-0"><?= $row ? 'Edit template' : 'New template' ?></h5>
  <a class="btn btn-sm btn-outline-success" href="<?= site_url('shopkart/whatsapp/campaigns') ?>">Campaigns</a>
</div>

<form class="card sk-table-card shadow-sm" method="post" enctype="multipart/form-data"
      action="<?= site_url($id ? 'admin/whatsapp/templates/save/'.$id : 'admin/whatsapp/templates/save') ?>">
  <input type="hidden" name="variable_map" id="variableMap" value="<?= htmlspecialchars(json_encode($savedMap, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?>">
  <div class="card-body">
    <div class="row g-3">
      <div class="col-md-6">
        <label class="form-label">Template name</label>
        <input type="text" name="name" class="form-control" required
               value="<?= htmlspecialchars($row['name'] ?? '') ?>" placeholder="order_ready">
        <div class="form-text">Lowercase letters, numbers, underscore. Must be unique on Meta.</div>
      </div>
      <div class="col-md-3">
        <label class="form-label">Language</label>
        <input type="text" name="language" class="form-control" value="<?= htmlspecialchars($row['language'] ?? 'en') ?>">
      </div>
      <div class="col-md-3">
        <label class="form-label">Category</label>
        <select name="category" class="form-select">
          <?php foreach (['UTILITY', 'MARKETING', 'AUTHENTICATION'] as $cat): ?>
            <option value="<?= $cat ?>" <?= (($row['category'] ?? 'UTILITY') === $cat) ? 'selected' : '' ?>><?= $cat ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-4">
        <label class="form-label">Type</label>
        <select name="kind" id="tplKind" class="form-select">
          <option value="text" <?= (($row['kind'] ?? '') === 'text') ? 'selected' : '' ?>>Text</option>
          <option value="image" <?= (($row['kind'] ?? '') === 'image') ? 'selected' : '' ?>>Image</option>
          <option value="video" <?= (($row['kind'] ?? '') === 'video') ? 'selected' : '' ?>>Video</option>
        </select>
      </div>
      <div class="col-md-8" id="tplMediaWrap">
        <label class="form-label">Header media</label>
        <input type="file" name="media" class="form-control" accept="image/*,video/*">
        <?php if (!empty($row['media_url'])): ?>
          <div class="form-text">Current: <a href="<?= htmlspecialchars($row['media_url']) ?>" target="_blank">view file</a></div>
        <?php endif; ?>
      </div>
      <div class="col-md-12" id="tplHeaderWrap">
        <label class="form-label">Header text (optional, text type)</label>
        <input type="text" name="header_text" class="form-control" maxlength="60" value="<?= htmlspecialchars($row['header_text'] ?? '') ?>">
      </div>
      <div class="col-12">
        <label class="form-label">Body</label>
        <textarea name="body_text" id="tplBody" class="form-control" rows="5" required><?= htmlspecialchars($row['body_text'] ?? '') ?></textarea>
        <div class="d-flex flex-wrap gap-2 mt-2">
          <button type="button" class="btn btn-sm btn-outline-success" id="tplInsertVar">Insert next {{n}}</button>
          <span class="form-text mb-0">Use {{1}}, {{2}} then drag customer details onto each slot below.</span>
        </div>
      </div>
      <div class="col-12">
        <label class="form-label">Footer (optional)</label>
        <input type="text" name="footer_text" class="form-control" maxlength="60" value="<?= htmlspecialchars($row['footer_text'] ?? '') ?>">
      </div>
    </div>

    <div class="wa-map mt-4">
      <div class="wa-map-col">
        <div class="wa-map-title">Customer detail modules</div>
        <p class="small text-muted mb-2">Drag a field onto a variable slot, or click a field then a slot.</p>
        <?php foreach ($groups as $group => $items): ?>
          <div class="wa-map-group"><?= htmlspecialchars($group) ?></div>
          <div class="wa-chips">
            <?php foreach ($items as $key => $mod): ?>
              <button type="button" class="wa-chip" draggable="true" data-field="<?= htmlspecialchars($key) ?>">
                <?= htmlspecialchars($mod['label']) ?>
              </button>
            <?php endforeach; ?>
          </div>
        <?php endforeach; ?>
      </div>
      <div class="wa-map-col">
        <div class="wa-map-title">Template variables</div>
        <p class="small text-muted mb-2">Drop the matching customer value for each placeholder.</p>
        <div id="tplSlots" class="wa-slots"></div>
        <div id="tplSlotsEmpty" class="text-muted small">Add {{1}} in the body to create a mapping slot.</div>
      </div>
    </div>

    <div class="form-check mt-3">
      <input class="form-check-input" type="checkbox" name="push_meta" value="1" id="pushMeta" checked>
      <label class="form-check-label" for="pushMeta">Also submit to Meta for approval</label>
    </div>
  </div>
  <div class="card-footer d-flex gap-2">
    <button class="btn btn-success">Save template</button>
    <a class="btn btn-outline-secondary" href="<?= site_url('shopkart/whatsapp/templates') ?>">Cancel</a>
  </div>
</form>
<script>
(function () {
  var kind = document.getElementById('tplKind');
  var media = document.getElementById('tplMediaWrap');
  var header = document.getElementById('tplHeaderWrap');
  var body = document.getElementById('tplBody');
  var slotsEl = document.getElementById('tplSlots');
  var emptyEl = document.getElementById('tplSlotsEmpty');
  var mapInput = document.getElementById('variableMap');
  var modules = <?= json_encode($modules, JSON_UNESCAPED_UNICODE) ?>;
  var map = {};
  try { map = JSON.parse(mapInput.value || '{}') || {}; } catch (e) { map = {}; }

  function syncKind() {
    var k = kind.value;
    media.style.display = (k === 'image' || k === 'video') ? '' : 'none';
    header.style.display = k === 'text' ? '' : 'none';
  }
  kind.addEventListener('change', syncKind);
  syncKind();

  function indexesFromBody() {
    var text = body.value || '';
    var found = [];
    var re = /\{\{\s*(\d+)\s*\}\}/g;
    var m;
    while ((m = re.exec(text))) {
      var n = parseInt(m[1], 10);
      if (n > 0 && found.indexOf(n) === -1) found.push(n);
    }
    found.sort(function (a, b) { return a - b; });
    return found;
  }

  function persist() {
    var clean = {};
    indexesFromBody().forEach(function (n) {
      if (map[String(n)]) clean[String(n)] = map[String(n)];
    });
    map = clean;
    mapInput.value = JSON.stringify(map);
  }

  function labelFor(field) {
    return (modules[field] && modules[field].label) ? modules[field].label : field;
  }

  var pendingField = '';
  function renderSlots() {
    var idxs = indexesFromBody();
    slotsEl.innerHTML = '';
    emptyEl.style.display = idxs.length ? 'none' : '';
    idxs.forEach(function (n) {
      var key = String(n);
      var field = map[key] || '';
      var slot = document.createElement('div');
      slot.className = 'wa-slot' + (field ? ' filled' : '');
      slot.dataset.index = key;
      slot.innerHTML =
        '<span class="wa-slot-num">{{' + n + '}}</span>' +
        '<span class="wa-slot-val">' + (field ? labelFor(field) : 'Drop customer detail here') + '</span>' +
        (field ? '<button type="button" class="btn-close wa-slot-clear" aria-label="Clear"></button>' : '');
      slot.addEventListener('dragover', function (e) { e.preventDefault(); slot.classList.add('over'); });
      slot.addEventListener('dragleave', function () { slot.classList.remove('over'); });
      slot.addEventListener('drop', function (e) {
        e.preventDefault();
        slot.classList.remove('over');
        var f = e.dataTransfer.getData('text/plain') || pendingField;
        assign(key, f);
      });
      slot.addEventListener('click', function (e) {
        if (e.target.classList.contains('wa-slot-clear')) {
          assign(key, '');
          return;
        }
        if (pendingField) assign(key, pendingField);
      });
      var clear = slot.querySelector('.wa-slot-clear');
      if (clear) {
        clear.addEventListener('click', function (e) {
          e.stopPropagation();
          assign(key, '');
        });
      }
      slotsEl.appendChild(slot);
    });
    persist();
  }

  function assign(index, field) {
    if (field) map[String(index)] = field;
    else delete map[String(index)];
    pendingField = '';
    document.querySelectorAll('.wa-chip').forEach(function (c) { c.classList.remove('picked'); });
    renderSlots();
  }

  document.querySelectorAll('.wa-chip').forEach(function (chip) {
    chip.addEventListener('dragstart', function (e) {
      pendingField = chip.getAttribute('data-field') || '';
      e.dataTransfer.setData('text/plain', pendingField);
      e.dataTransfer.effectAllowed = 'copy';
    });
    chip.addEventListener('click', function () {
      pendingField = chip.getAttribute('data-field') || '';
      document.querySelectorAll('.wa-chip').forEach(function (c) { c.classList.remove('picked'); });
      chip.classList.add('picked');
      var empty = slotsEl.querySelector('.wa-slot:not(.filled)');
      if (empty && pendingField) assign(empty.dataset.index, pendingField);
    });
  });

  document.getElementById('tplInsertVar').addEventListener('click', function () {
    var idxs = indexesFromBody();
    var next = idxs.length ? (Math.max.apply(null, idxs) + 1) : 1;
    var token = '{{' + next + '}}';
    var start = body.selectionStart || body.value.length;
    var end = body.selectionEnd || start;
    body.value = body.value.slice(0, start) + token + body.value.slice(end);
    body.focus();
    body.selectionStart = body.selectionEnd = start + token.length;
    renderSlots();
  });

  body.addEventListener('input', renderSlots);
  renderSlots();
})();
</script>
