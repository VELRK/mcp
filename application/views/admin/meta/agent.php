<?php
$cfg = $cfg ?? [];
$rows = $rows ?? [];
$platform_ready = !empty($platform_ready);
$webhook = $webhook_uri ?? '';
?>
<div class="sk-page-header d-flex justify-content-between align-items-center flex-wrap gap-2">
  <h5 class="sk-page-title mb-0"><i class="bi bi-robot me-2 text-success"></i>Meta Business Agent</h5>
  <a href="<?= site_url('admin/meta') ?>" class="btn btn-sm btn-outline-secondary">Meta connect</a>
</div>

<?php if ($this->session->flashdata('success')): ?>
  <div class="alert alert-success"><?= htmlspecialchars($this->session->flashdata('success')) ?></div>
<?php endif; ?>
<?php if ($this->session->flashdata('error')): ?>
  <div class="alert alert-danger"><?= htmlspecialchars($this->session->flashdata('error')) ?></div>
<?php endif; ?>

<div class="row g-3 mb-3">
  <div class="col-lg-8">
    <div class="card sk-table-card shadow-sm">
      <div class="card-body">
        <h6 class="mb-2">Platform configuration (.env)</h6>
        <p class="small text-muted mb-3">
          Meta Business Agent is the primary WhatsApp responder. This app stays on
          <code>standby</code> / <code>messaging_handovers</code> and serves commerce connectors.
          <?= !empty($cfg['soft_handoff'])
            ? 'Handoff is <strong>soft</strong>: staff get notified, AI keeps chatting until a human sends a reply (then use Release to give AI back).'
            : 'Handoff is <strong>hard</strong>: AI releases control and stops until Release.' ?>
        </p>
        <div class="row g-2 small">
          <div class="col-md-4"><strong>Enabled:</strong> <?= !empty($cfg['enabled']) ? 'yes' : 'no' ?></div>
          <div class="col-md-4"><strong>API version:</strong> <?= htmlspecialchars((string)($cfg['api_version'] ?? '')) ?></div>
          <div class="col-md-4"><strong>Audience default:</strong> <?= htmlspecialchars((string)($cfg['default_audience'] ?? '')) ?></div>
          <div class="col-md-4"><strong>System token:</strong> <?= !empty($cfg['has_system_token']) ? 'set' : 'missing' ?></div>
          <div class="col-md-4"><strong>Connector key:</strong> <?= !empty($cfg['has_connector_key']) ? 'set' : 'missing' ?></div>
          <div class="col-md-4"><strong>Ready:</strong> <?= $platform_ready ? '<span class="text-success">yes</span>' : '<span class="text-danger">no</span>' ?></div>
          <div class="col-12">
            <strong>Connector base URL:</strong>
            <code><?= htmlspecialchars((string)($cfg['connector_base_url'] ?? '')) ?></code>
          </div>
          <div class="col-12">
            <strong>Webhook:</strong>
            <code><?= htmlspecialchars((string)$webhook) ?></code>
            <span class="text-muted">— subscribe <code>messages</code>, <code>standby</code>, <code>messaging_handovers</code></span>
          </div>
        </div>
        <p class="small text-muted mt-3 mb-0">
          SaaS: each shop’s Meta token and BA billable account ID are stored on that WhatsApp number
          (<code>vendor_whatsapp_accounts</code>), not in <code>.env</code>.
        </p>
        <?php if (!$platform_ready): ?>
          <div class="alert alert-warning mt-3 mb-0 small">
            Copy <code>.env.example</code> to <code>.env</code> and set
            <code>META_BA_ENABLED=1</code>,
            <code>META_BA_CONNECTOR_API_KEY</code>, and a public
            <code>META_BA_CONNECTOR_BASE_URL</code> (HTTPS in production).
            Per-shop WhatsApp tokens come from Meta connect, not <code>META_BA_SYSTEM_USER_TOKEN</code>.
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
  <div class="col-lg-4">
    <div class="card sk-table-card shadow-sm h-100">
      <div class="card-body">
        <h6 class="mb-2">Rollout order</h6>
        <ol class="small mb-0 ps-3">
          <li>Connect WhatsApp number</li>
          <li>Eligibility + onboard run automatically</li>
          <li>Sync tools / Sync skills (click)</li>
          <li>Test (click)</li>
          <li>Enable allowlist (click)</li>
          <li>Enable live when billing ready (click)</li>
        </ol>
      </div>
    </div>
  </div>
</div>

<div class="card sk-table-card shadow-sm">
  <div class="card-body">
    <h6 class="mb-3">WhatsApp numbers</h6>
    <?php if (!$rows): ?>
      <p class="text-muted mb-0">No active WhatsApp numbers yet. Connect one under Meta / My numbers.</p>
    <?php else: ?>
      <div class="table-responsive">
        <table class="table table-sm align-middle">
          <thead>
            <tr>
              <th>Number</th>
              <th>Vendor</th>
              <th>Agent</th>
              <th>BA billable</th>
              <th>Status</th>
              <th style="min-width:280px">Actions</th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($rows as $row):
              $acct = $row['account'] ?? [];
              $agent = $row['agent'] ?? null;
              $vendor = $row['vendor'] ?? null;
              $phoneId = (string)($acct['phone_number_id'] ?? '');
              $display = trim((string)($acct['display_phone'] ?? '')) ?: $phoneId;
              $shop = trim((string)($vendor['business_name'] ?? $vendor['owner_name'] ?? '')) ?: ('Vendor #' . (int)($acct['vendor_id'] ?? 0));
          ?>
            <tr data-phone="<?= htmlspecialchars($phoneId) ?>" data-needs-prepare="<?= !empty($row['needs_prepare']) ? '1' : '0' ?>">
              <td>
                <div class="fw-semibold"><?= htmlspecialchars($display) ?></div>
                <div class="small text-muted font-monospace"><?= htmlspecialchars($phoneId) ?></div>
              </td>
              <td class="small"><?= htmlspecialchars($shop) ?></td>
              <td class="small">
                <?php if ($agent && !empty($agent['agent_id'])): ?>
                  <code><?= htmlspecialchars((string)$agent['agent_id']) ?></code>
                <?php else: ?>
                  <span class="text-muted">—</span>
                <?php endif; ?>
                <?php if ($agent && !empty($agent['connector_id'])): ?>
                  <div class="text-muted">connector set</div>
                <?php endif; ?>
              </td>
              <td class="small">
                <?php if (!empty($acct['ba_account_id'])): ?>
                  <code><?= htmlspecialchars((string)$acct['ba_account_id']) ?></code>
                <?php else: ?>
                  <span class="text-muted">—</span>
                <?php endif; ?>
              </td>
              <td class="small">
                <?php
                  $bits = [];
                  if (!empty($agent['eligible'])) $bits[] = 'eligible';
                  if (!empty($agent['onboarded'])) $bits[] = 'onboarded';
                  if (!empty($agent['agent_enabled'])) $bits[] = 'enabled';
                  if (!empty($agent['ai_audience'])) $bits[] = (string)$agent['ai_audience'];
                  if (!empty($agent['sync_status'])) $bits[] = (string)$agent['sync_status'];
                  echo $bits ? htmlspecialchars(implode(' · ', $bits)) : '<span class="text-muted">auto-preparing…</span>';
                  if (!empty($agent['last_error'])):
                ?>
                  <div class="text-danger"><?= htmlspecialchars((string)$agent['last_error']) ?></div>
                <?php endif; ?>
              </td>
              <td>
                <div class="d-flex flex-wrap gap-1">
                  <button type="button" class="btn btn-sm btn-outline-primary mba-op" data-op="sync_billing">Sync billing ID</button>
                  <button type="button" class="btn btn-sm btn-outline-success mba-op" data-op="sync">Sync tools</button>
                  <button type="button" class="btn btn-sm btn-outline-secondary mba-op" data-op="list_connectors">Connectors</button>
                  <button type="button" class="btn btn-sm btn-outline-secondary mba-op" data-op="connector_logs">Conn. logs</button>
                  <button type="button" class="btn btn-sm btn-outline-info mba-op" data-op="list_skills">List skills</button>
                  <button type="button" class="btn btn-sm btn-outline-info mba-op" data-op="sync_skills">Sync skills</button>
                  <button type="button" class="btn btn-sm btn-outline-info mba-op" data-op="list_ui_skills">UI skills</button>
                  <button type="button" class="btn btn-sm btn-outline-info mba-op" data-op="sync_ui_skills">Sync UI</button>
                  <button type="button" class="btn btn-sm btn-outline-warning mba-op" data-op="release">Release AI</button>
                  <button type="button" class="btn btn-sm btn-outline-secondary mba-op" data-op="test">Test</button>
                  <button type="button" class="btn btn-sm btn-success mba-op" data-op="enable_allowlist">Enable allowlist</button>
                  <button type="button" class="btn btn-sm btn-warning mba-op" data-op="enable_live">Enable live</button>
                  <button type="button" class="btn btn-sm btn-outline-danger mba-op" data-op="disable">Disable</button>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</div>

<div class="card shadow-sm mt-3">
  <div class="card-body">
    <h6 class="mb-1">UI skills</h6>
    <p class="small text-muted mb-3">
      These tell the Meta agent when to send a button, image, list, location, carousel, or Flow on this WhatsApp number.
      The instruction must say when to send it and the exact text, labels, and URLs the component needs.
      A Flow skill needs a published <code>flow_id</code>. Other types must not send <code>flow_id</code>.
    </p>
    <div class="row g-2 align-items-end mb-3">
      <div class="col-md-4">
        <label class="form-label small mb-1">WhatsApp phone number</label>
        <select id="uiSkillPhone" class="form-select form-select-sm">
          <?php foreach ($rows as $row):
              $acct = $row['account'] ?? [];
              $pid = trim((string)($acct['phone_number_id'] ?? ''));
              if ($pid === '') continue;
              $label = trim((string)($acct['display_phone'] ?? '')) ?: $pid;
          ?>
          <option value="<?= htmlspecialchars($pid) ?>"><?= htmlspecialchars($label) ?> (<?= htmlspecialchars($pid) ?>)</option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-2">
        <button type="button" class="btn btn-sm btn-outline-secondary w-100" id="uiSkillLoad">Load from Meta</button>
      </div>
    </div>
    <form id="uiSkillForm" class="row g-2">
      <div class="col-md-4">
        <label class="form-label small mb-1">Title</label>
        <input type="text" name="title" class="form-control form-control-sm" maxlength="190" required placeholder="Shop catalog button">
      </div>
      <div class="col-md-3">
        <label class="form-label small mb-1">Component</label>
        <select name="component_type" id="uiSkillType" class="form-select form-select-sm" required>
          <?php foreach (['cta_url','image','interactive_list','interactive_reply_buttons','location','location_request','carousel_url','carousel_quick_reply','flow'] as $type): ?>
          <option value="<?= $type ?>"><?= $type ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-2">
        <label class="form-label small mb-1">Status</label>
        <select name="status" class="form-select form-select-sm">
          <option value="enabled">enabled</option>
          <option value="disabled">disabled</option>
        </select>
      </div>
      <div class="col-md-3" id="uiSkillFlowWrap" hidden>
        <label class="form-label small mb-1">Flow ID</label>
        <input type="number" name="flow_id" class="form-control form-control-sm" min="1" step="1" placeholder="Published flow id">
      </div>
      <div class="col-12">
        <label class="form-label small mb-1">Instruction</label>
        <textarea name="instruction" class="form-control form-control-sm" rows="3" required placeholder="If a customer asks for the shop link, send a button with body text &quot;Browse the catalog&quot;, button label &quot;Open shop&quot;, and URL https://example.com"></textarea>
      </div>
      <div class="col-12">
        <button type="submit" class="btn btn-sm btn-warning">Create UI skill</button>
      </div>
    </form>
    <div id="uiSkillList" class="mt-3"></div>
  </div>
</div>

<div id="mbaResult" class="alert alert-secondary mt-3 d-none small" style="white-space:pre-wrap"></div>
<p class="small text-muted mt-2 mb-0">Responses stay here until you refresh the page to update the table.</p>

<script>
(function () {
  var result = document.getElementById('mbaResult');
  var csrfName = <?= json_encode($this->security->get_csrf_token_name()) ?>;
  var actionUrl = <?= json_encode(site_url('admin/meta/agent/action')) ?>;
  var platformReady = <?= $platform_ready ? 'true' : 'false' ?>;

  function csrfToken() {
    var meta = document.querySelector('meta[name="csrf-token"]');
    if (meta && meta.getAttribute('content')) {
      return meta.getAttribute('content');
    }
    return <?= json_encode($this->security->get_csrf_hash()) ?>;
  }

  function rememberCsrf(j, res) {
    var next = '';
    if (j && j.csrf_hash) next = j.csrf_hash;
    if (!next && res && res.headers) next = res.headers.get('X-CSRF-TOKEN') || '';
    if (!next) return;
    var meta = document.querySelector('meta[name="csrf-token"]');
    if (meta) meta.setAttribute('content', next);
  }

  function show(ok, msg, data) {
    if (!result) return;
    result.classList.remove('d-none', 'alert-success', 'alert-danger', 'alert-secondary');
    result.classList.add(ok ? 'alert-success' : 'alert-danger');
    result.textContent = msg + (data ? '\n' + JSON.stringify(data, null, 2) : '');
  }

  function postOp(phone, op, extra) {
    var body = new FormData();
    body.append(csrfName, csrfToken());
    body.append('phone_number_id', phone);
    body.append('op', op);
    if (extra) {
      Object.keys(extra).forEach(function (k) {
        if (extra[k] !== undefined && extra[k] !== null) body.append(k, extra[k]);
      });
    }
    return fetch(actionUrl, {
      method: 'POST',
      body: body,
      credentials: 'same-origin',
      headers: { 'X-Requested-With': 'XMLHttpRequest' }
    }).then(function (r) {
      return r.text().then(function (t) {
        var j = null;
        try { j = JSON.parse(t); } catch (e) {}
        rememberCsrf(j, r);
        return { ok: r.ok, status: r.status, j: j, raw: t };
      });
    });
  }

  function handleResult(x) {
    if (!x.j) {
      var blocked = !x.ok || x.status === 403 || /not allowed/i.test(x.raw || '');
      show(false, blocked
        ? 'CSRF blocked this request. Refresh the page, then try again.'
        : (x.ok ? 'Unexpected response' : 'Request failed'));
      return;
    }
    var detail = x.j.data;
    if (x.j.op) {
      detail = Object.assign({ op: x.j.op }, detail && typeof detail === 'object' ? detail : {});
    }
    show(!!x.j.success, x.j.message || 'Done', detail);
  }

  var uiPhone = document.getElementById('uiSkillPhone');
  var uiForm = document.getElementById('uiSkillForm');
  var uiList = document.getElementById('uiSkillList');
  var uiType = document.getElementById('uiSkillType');
  var uiFlowWrap = document.getElementById('uiSkillFlowWrap');

  function selectedUiPhone() {
    return uiPhone ? uiPhone.value : '';
  }

  function toggleFlowField() {
    if (!uiType || !uiFlowWrap) return;
    var isFlow = uiType.value === 'flow';
    uiFlowWrap.hidden = !isFlow;
    var input = uiFlowWrap.querySelector('input');
    if (input) input.required = isFlow;
  }
  if (uiType) uiType.addEventListener('change', toggleFlowField);
  toggleFlowField();

  function renderUiSkills(payload) {
    if (!uiList) return;
    uiList.textContent = '';
    var items = (payload && payload.ui_skills) ? payload.ui_skills : [];
    if (!items.length) {
      var empty = document.createElement('p');
      empty.className = 'small text-muted mb-0';
      empty.textContent = 'No UI skills on this number.';
      uiList.appendChild(empty);
      return;
    }
    var table = document.createElement('table');
    table.className = 'table table-sm align-middle mb-0';
    var head = document.createElement('thead');
    head.innerHTML = '<tr><th>Title</th><th>Type</th><th>Status</th><th>Instruction</th><th></th></tr>';
    table.appendChild(head);
    var body = document.createElement('tbody');
    items.forEach(function (item) {
      var tr = document.createElement('tr');
      function cell(text) {
        var td = document.createElement('td');
        td.className = 'small';
        td.textContent = text || '';
        return td;
      }
      tr.appendChild(cell(item.title));
      var typeText = item.component_type || '';
      if (item.flow_id) typeText += ' #' + item.flow_id;
      tr.appendChild(cell(typeText));
      tr.appendChild(cell(item.status));
      var inst = cell(item.instruction || '');
      inst.style.maxWidth = '360px';
      tr.appendChild(inst);
      var actions = document.createElement('td');
      actions.className = 'text-nowrap';
      var toggle = document.createElement('button');
      toggle.type = 'button';
      toggle.className = 'btn btn-sm btn-outline-secondary me-1';
      toggle.textContent = item.status === 'enabled' ? 'Disable' : 'Enable';
      toggle.addEventListener('click', function () {
        var phone = selectedUiPhone();
        if (!phone || !item.id) return;
        toggle.disabled = true;
        postOp(phone, 'update_ui_skill', {
          instruction_id: item.id,
          status: item.status === 'enabled' ? 'disabled' : 'enabled'
        }).then(function (x) {
          handleResult(x);
          if (x.j && x.j.success) loadUiSkills();
        }).finally(function () { toggle.disabled = false; });
      });
      var del = document.createElement('button');
      del.type = 'button';
      del.className = 'btn btn-sm btn-outline-danger';
      del.textContent = 'Delete';
      del.addEventListener('click', function () {
        var phone = selectedUiPhone();
        if (!phone || !item.id) return;
        if (!window.confirm('Delete UI skill "' + (item.title || item.id) + '"?')) return;
        del.disabled = true;
        postOp(phone, 'delete_ui_skill', { instruction_id: item.id })
          .then(function (x) {
            handleResult(x);
            if (x.j && x.j.success) loadUiSkills();
          })
          .finally(function () { del.disabled = false; });
      });
      actions.appendChild(toggle);
      actions.appendChild(del);
      tr.appendChild(actions);
      body.appendChild(tr);
    });
    table.appendChild(body);
    uiList.appendChild(table);
  }

  function loadUiSkills() {
    var phone = selectedUiPhone();
    if (!phone) return;
    postOp(phone, 'list_ui_skills').then(function (x) {
      handleResult(x);
      if (x.j && x.j.success) renderUiSkills(x.j.data || {});
    });
  }

  var loadBtn = document.getElementById('uiSkillLoad');
  if (loadBtn) loadBtn.addEventListener('click', loadUiSkills);
  if (uiForm) {
    uiForm.addEventListener('submit', function (ev) {
      ev.preventDefault();
      var phone = selectedUiPhone();
      if (!phone) {
        show(false, 'Choose a WhatsApp phone number first.');
        return;
      }
      var data = new FormData(uiForm);
      var extra = {
        title: data.get('title'),
        component_type: data.get('component_type'),
        status: data.get('status'),
        instruction: data.get('instruction')
      };
      if (extra.component_type === 'flow') extra.flow_id = data.get('flow_id');
      var submit = uiForm.querySelector('button[type="submit"]');
      if (submit) submit.disabled = true;
      postOp(phone, 'create_ui_skill', extra)
        .then(function (x) {
          handleResult(x);
          if (x.j && x.j.success) {
            uiForm.reset();
            toggleFlowField();
            loadUiSkills();
          }
        })
        .finally(function () { if (submit) submit.disabled = false; });
    });
  }

  document.querySelectorAll('.mba-op').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var tr = btn.closest('tr');
      var phone = tr ? tr.getAttribute('data-phone') : '';
      var op = btn.getAttribute('data-op');
      if (!phone || !op) return;
      btn.disabled = true;
      var extra = {};
      if (op === 'test') {
        var msg = window.prompt('Test message', 'Hi');
        if (msg === null) { btn.disabled = false; return; }
        extra.message = msg;
      }
      if (op === 'release' || op === 'take') {
        var phoneTo = window.prompt('Customer WhatsApp number (with country code)', '');
        if (phoneTo === null || !String(phoneTo).trim()) { btn.disabled = false; return; }
        extra.to = String(phoneTo).trim();
      }
      postOp(phone, op, extra)
        .then(function (x) {
          handleResult(x);
          if (op === 'list_ui_skills' && x.j && x.j.success) {
            if (uiPhone) uiPhone.value = phone;
            renderUiSkills(x.j.data || {});
          }
        })
        .catch(function (e) { show(false, e.message || 'Request failed'); })
        .finally(function () { btn.disabled = false; });
    });
  });

  // Run after layout CSRF helper is ready. Eligibility/onboard are automatic.
  window.addEventListener('load', function () {
    if (!platformReady) return;
    var queue = [];
    document.querySelectorAll('tr[data-needs-prepare="1"]').forEach(function (tr) {
      var phone = tr.getAttribute('data-phone');
      if (phone) queue.push(phone);
    });
    function next() {
      if (!queue.length) return;
      var phone = queue.shift();
      postOp(phone, 'prepare')
        .then(function (x) {
          if (x.j && x.j.success) {
            show(true, (x.j.message || 'Prepared') + ' (' + phone + ')', x.j.data || null);
            var row = document.querySelector('tr[data-phone="' + phone + '"]');
            if (row) row.setAttribute('data-needs-prepare', '0');
          } else if (x.j) {
            show(false, x.j.message || 'Auto prepare failed', x.j.data || null);
          } else {
            handleResult(x);
          }
        })
        .catch(function () {})
        .finally(next);
    }
    next();
  });
})();
</script>
