<link rel="stylesheet" href="<?= base_url('assets/admin/css/whatsapp-cloud.css') ?>">
<div class="sk-page-header d-flex flex-wrap align-items-center justify-content-between gap-2">
  <h5 class="sk-page-title mb-0"><i class="bi bi-whatsapp me-2 text-success"></i>WhatsApp Inbox</h5>
  <div class="d-flex gap-2">
    <a href="<?= site_url('shopkart/whatsapp/templates') ?>" class="btn btn-sm btn-outline-success">Templates</a>
    <a href="<?= site_url('shopkart/whatsapp/campaigns') ?>" class="btn btn-sm btn-outline-success">Campaigns</a>
    <a href="<?= site_url('admin/settings?tab=wacloud') ?>" class="btn btn-sm btn-outline-secondary">Meta API</a>
  </div>
</div>

<?php if (empty($ready)): ?>
<div class="alert alert-warning">
  Connect <strong>Meta WhatsApp Cloud API</strong> in Settings → WhatsApp Cloud.
  Webhook URL: <code class="user-select-all"><?= htmlspecialchars($webhook_url) ?></code>
</div>
<?php endif; ?>

<div class="wa-app" id="waApp"
     data-conv-url="<?= site_url('admin/whatsapp/conversations') ?>"
     data-thread-url="<?= site_url('admin/whatsapp/thread') ?>"
     data-send-url="<?= site_url('admin/whatsapp/send') ?>"
     data-start-url="<?= site_url('admin/whatsapp/start') ?>">
  <aside class="wa-side">
    <div class="wa-side-head">
      <h6>Chats</h6>
      <button type="button" class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#waStartModal">
        <i class="bi bi-plus-lg"></i>
      </button>
    </div>
    <div class="wa-search">
      <input type="search" id="waSearch" class="form-control form-control-sm" placeholder="Search name or number">
    </div>
    <div class="wa-list" id="waList"></div>
  </aside>
  <section class="wa-main">
    <div class="wa-main-head" id="waHead">
      <div class="wa-avatar">WA</div>
      <div>
        <div class="fw-semibold" id="waHeadName">Select a chat</div>
        <div class="small text-muted" id="waHeadPhone"></div>
      </div>
    </div>
    <div class="wa-thread" id="waThread">
      <div class="wa-empty">Choose a conversation or start a new chat.</div>
    </div>
    <div class="wa-tpl-bar">
      <select id="waTemplate" class="form-select form-select-sm">
        <option value="">Send approved template…</option>
        <?php foreach (($templates ?? []) as $t): ?>
          <option value="<?= (int)$t['id'] ?>"><?= htmlspecialchars($t['name']) ?> (<?= htmlspecialchars($t['kind']) ?> · <?= htmlspecialchars($t['status']) ?>)</option>
        <?php endforeach; ?>
      </select>
    </div>
    <form class="wa-composer" id="waComposer" enctype="multipart/form-data">
      <input type="hidden" name="conversation_id" id="waConvId" value="">
      <input type="hidden" name="type" id="waType" value="text">
      <label class="btn btn-light btn-sm mb-0" title="Image / video">
        <i class="bi bi-paperclip"></i>
        <input type="file" name="media" id="waMedia" accept="image/*,video/*" hidden>
      </label>
      <textarea name="body" id="waBody" placeholder="Type a message" rows="1"></textarea>
      <button type="submit" class="btn btn-wa" <?= empty($ready) ? 'disabled' : '' ?>>
        <i class="bi bi-send-fill"></i>
      </button>
    </form>
  </section>
</div>

<div class="modal fade" id="waStartModal" tabindex="-1">
  <div class="modal-dialog">
    <form class="modal-content" id="waStartForm">
      <div class="modal-header">
        <h6 class="modal-title">New chat</h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <label class="form-label">Phone (with country code)</label>
        <input type="text" name="phone" class="form-control" placeholder="60123456789" required>
        <label class="form-label mt-2">Name (optional)</label>
        <input type="text" name="name" class="form-control">
      </div>
      <div class="modal-footer">
        <button type="submit" class="btn btn-success">Open chat</button>
      </div>
    </form>
  </div>
</div>

<script>
(function () {
  var app = document.getElementById('waApp');
  if (!app) return;
  var convUrl = app.dataset.convUrl;
  var threadUrl = app.dataset.threadUrl;
  var sendUrl = app.dataset.sendUrl;
  var startUrl = app.dataset.startUrl;
  var activeId = 0;
  var lastMsgId = 0;

  function esc(s) {
    return String(s || '').replace(/[&<>"']/g, function (c) {
      return ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]);
    });
  }
  function initials(name, phone) {
    var n = (name || phone || 'WA').trim();
    return n.slice(0, 2).toUpperCase();
  }
  function renderList(rows) {
    var box = document.getElementById('waList');
    if (!rows.length) {
      box.innerHTML = '<div class="p-3 text-muted small">No chats yet.</div>';
      return;
    }
    box.innerHTML = rows.map(function (r) {
      var unread = Number(r.unread || 0);
      return '<div class="wa-item' + (Number(r.id) === activeId ? ' active' : '') + '" data-id="' + r.id + '">'
        + '<div class="wa-avatar">' + esc(initials(r.name, r.phone)) + '</div>'
        + '<div class="wa-item-body"><div class="wa-item-top"><span class="wa-item-name">'
        + esc(r.name || r.phone) + '</span><span class="wa-item-time">'
        + esc((r.last_at || '').slice(11, 16)) + '</span></div>'
        + '<div class="wa-item-preview">' + esc(r.last_message || '') + '</div></div>'
        + (unread ? '<span class="wa-unread">' + unread + '</span>' : '')
        + '</div>';
    }).join('');
    box.querySelectorAll('.wa-item').forEach(function (el) {
      el.addEventListener('click', function () { openThread(Number(el.dataset.id)); });
    });
  }
  function bubbleHtml(m) {
    var cls = m.direction === 'out' ? 'out' : 'in';
    var media = '';
    if (m.type === 'image' && m.media_url && m.media_url.indexOf('http') === 0) {
      media = '<img src="' + esc(m.media_url) + '" alt="">';
    }
    if (m.type === 'video' && m.media_url && m.media_url.indexOf('http') === 0) {
      media = '<video src="' + esc(m.media_url) + '" controls></video>';
    }
    return '<div class="wa-bubble ' + cls + '" data-mid="' + m.id + '">'
      + media
      + (m.body ? '<div>' + esc(m.body).replace(/\n/g, '<br>') + '</div>' : '')
      + '<div class="wa-meta">' + esc(m.status || '') + ' · ' + esc((m.created_at || '').slice(11, 16)) + '</div>'
      + '</div>';
  }
  function renderThread(payload, append) {
    var thread = document.getElementById('waThread');
    var msgs = payload.messages || [];
    if (!append) {
      thread.innerHTML = msgs.length ? msgs.map(bubbleHtml).join('') : '<div class="wa-empty">No messages yet.</div>';
      lastMsgId = msgs.length ? Number(msgs[msgs.length - 1].id) : 0;
      thread.scrollTop = thread.scrollHeight;
    } else if (msgs.length) {
      var empty = thread.querySelector('.wa-empty');
      if (empty) empty.remove();
      msgs.forEach(function (m) { thread.insertAdjacentHTML('beforeend', bubbleHtml(m)); lastMsgId = Number(m.id); });
      thread.scrollTop = thread.scrollHeight;
    }
    if (payload.conversation) {
      document.getElementById('waHeadName').textContent = payload.conversation.name || payload.conversation.phone;
      document.getElementById('waHeadPhone').textContent = payload.conversation.phone;
      document.getElementById('waConvId').value = payload.conversation.id;
    }
  }
  function loadList() {
    var q = document.getElementById('waSearch').value;
    fetch(convUrl + '?q=' + encodeURIComponent(q), { headers: { 'Accept': 'application/json' } })
      .then(function (r) { return r.json(); })
      .then(function (d) { if (d.success) renderList(d.conversations || []); });
  }
  function openThread(id) {
    activeId = id;
    lastMsgId = 0;
    fetch(threadUrl + '/' + id, { headers: { 'Accept': 'application/json' } })
      .then(function (r) { return r.json(); })
      .then(function (d) { if (d.success) { renderThread(d, false); loadList(); } });
  }
  function poll() {
    if (!activeId) { loadList(); return; }
    fetch(threadUrl + '/' + activeId + '?after=' + lastMsgId, { headers: { 'Accept': 'application/json' } })
      .then(function (r) { return r.json(); })
      .then(function (d) { if (d.success) renderThread(d, true); loadList(); });
  }
  document.getElementById('waSearch').addEventListener('input', loadList);
  document.getElementById('waMedia').addEventListener('change', function () {
    var f = this.files && this.files[0];
    document.getElementById('waType').value = f && f.type.indexOf('video') === 0 ? 'video' : (f ? 'image' : 'text');
  });
  document.getElementById('waComposer').addEventListener('submit', function (e) {
    e.preventDefault();
    if (!activeId) { alert('Select a chat first.'); return; }
    var tpl = document.getElementById('waTemplate').value;
    if (tpl) document.getElementById('waType').value = 'template';
    var fd = new FormData(this);
    if (tpl) fd.set('template_id', tpl);
    fetch(sendUrl, { method: 'POST', body: fd, headers: { 'Accept': 'application/json' } })
      .then(function (r) { return r.json(); })
      .then(function (d) {
        if (!d.success) { alert(d.message || 'Send failed'); return; }
        document.getElementById('waBody').value = '';
        document.getElementById('waMedia').value = '';
        document.getElementById('waType').value = 'text';
        document.getElementById('waTemplate').value = '';
        openThread(activeId);
      });
  });
  document.getElementById('waStartForm').addEventListener('submit', function (e) {
    e.preventDefault();
    var fd = new FormData(this);
    fetch(startUrl, { method: 'POST', body: fd, headers: { 'Accept': 'application/json' } })
      .then(function (r) { return r.json(); })
      .then(function (d) {
        if (!d.success) { alert(d.message || 'Could not start chat'); return; }
        bootstrap.Modal.getOrCreateInstance(document.getElementById('waStartModal')).hide();
        openThread(Number(d.conversation.id));
      });
  });
  loadList();
  setInterval(poll, 5000);
})();
</script>
