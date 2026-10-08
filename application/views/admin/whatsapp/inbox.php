<?php
$page_title = 'Chat';
$breadcrumb = [
    ['label' => 'Apps', 'url' => site_url('admin/whatsapp')],
    'Chat',
];
$this->load->view('admin/partials/page_title', compact('page_title', 'breadcrumb'));
?>

<?php if (empty($ready)): ?>
<div class="alert alert-warning">
  Connect <strong>Meta WhatsApp Cloud API</strong> in Settings → WhatsApp Cloud.
  Webhook URL: <code class="user-select-all"><?= htmlspecialchars($webhook_url) ?></code>
</div>
<?php endif; ?>

<div class="d-flex flex-wrap gap-2 justify-content-end mb-3">
  <a href="<?= site_url('admin/whatsapp/templates') ?>" class="btn btn-sm btn-light">Templates</a>
  <a href="<?= site_url('admin/whatsapp/campaigns') ?>" class="btn btn-sm btn-light">Campaigns</a>
  <a href="<?= site_url('admin/meta/agent') ?>" class="btn btn-sm btn-success">Meta Business Agent</a>
</div>

<style>
#waApp.main-chat-content { align-items: stretch; }
#waApp .chat-area { min-width: 0; display: flex; }
#waApp .chat-area > .chat-wrapper,
#waApp .chat-contact > .chat-wrapper {
  display: flex;
  flex-direction: column;
  width: 100%;
  height: calc(100vh - 186px);
  min-height: 420px;
  overflow: hidden !important;
}
#waApp .chat-area .chat-conversation,
#waApp #waList {
  flex: 1 1 auto;
  min-height: 0 !important;
  max-height: none !important;
  height: auto !important;
  overflow: auto;
}
#waApp #waComposer { flex: 0 0 auto; }
#waApp #waSendBtn { position: relative; z-index: 2; flex: 0 0 auto; }
#waApp #waBody { resize: none; max-height: 120px; }
</style>
<div class="d-flex flex-wrap flex-xl-nowrap gap-xl-4 main-chat-content" id="waApp"
     data-conv-url="<?= site_url('admin/whatsapp/conversations') ?>"
     data-thread-url="<?= site_url('admin/whatsapp/thread') ?>"
     data-send-url="<?= site_url('admin/whatsapp/send') ?>"
     data-start-url="<?= site_url('admin/whatsapp/start') ?>"
     data-control-url="<?= site_url('admin/whatsapp/thread_control') ?>">
  <div class="chat-contact flex-grow-0">
    <div class="card chat-wrapper overflow-hidden">
      <div class="d-flex gap-3 p-4">
        <div class="app-search w-100">
          <div class="position-relative">
            <input type="search" id="waSearch" class="form-control" placeholder="Search Here..">
            <i data-eva="search-outline" class="align-middle"></i>
          </div>
        </div>
        <div class="flex-shrink-0">
          <button type="button" class="btn btn-outline-light btn-icon" data-bs-toggle="modal" data-bs-target="#waStartModal" aria-label="New chat">
            <i class="mdi mdi-plus"></i>
          </button>
        </div>
      </div>
      <h6 class="text-muted fw-normal mb-3 px-4 mt-1">Chats</h6>
      <div class="rich-list rounded-0 rich-list-action" id="waList"></div>
    </div>
  </div>

  <div class="w-100 chat-area">
    <div class="card chat-wrapper overflow-hidden">
      <div class="p-4 d-flex flex-wrap gap-2 align-items-center justify-content-between border-bottom" id="waHead">
        <div class="d-flex align-items-end gap-3">
          <div class="avatar-xs avatar avatar-circle bg-primary-subtle text-primary fw-semibold d-flex align-items-center justify-content-center" id="waHeadInitial">WA</div>
          <div>
            <span class="mb-0 text-body d-block lh-1 fw-semibold" id="waHeadName">Select a chat</span>
            <small class="text-muted" id="waHeadPhone"></small>
            <small class="d-block mt-1" id="waThreadOwner"></small>
          </div>
        </div>
        <div class="d-flex flex-wrap gap-1" id="waControlBtns">
          <button type="button" class="btn btn-sm btn-outline-primary" id="waTakeBtn" disabled>Take control</button>
          <button type="button" class="btn btn-sm btn-outline-success" id="waReleaseBtn" disabled>Release to AI</button>
        </div>
      </div>
      <div class="chat-conversation">
        <div class="p-4 h-100">
          <div class="chat" id="waThread">
            <div class="text-center text-muted py-5 wa-thread-empty">Choose a conversation or start a new chat.</div>
          </div>
        </div>
      </div>
      <form id="waComposer" enctype="multipart/form-data">
        <input type="hidden" name="conversation_id" id="waConvId" value="">
        <input type="hidden" name="type" id="waType" value="text">
        <div class="px-4 pt-3">
          <select id="waTemplate" class="form-select form-select-sm">
            <option value="">Send approved template…</option>
            <?php foreach (($templates ?? []) as $t): ?>
              <option value="<?= (int) $t['id'] ?>"><?= htmlspecialchars($t['name']) ?> (<?= htmlspecialchars($t['kind']) ?> · <?= htmlspecialchars($t['status']) ?>)</option>
            <?php endforeach; ?>
          </select>
        </div>
        <div id="waMediaPreview" class="px-4 pt-2 d-none">
          <div class="d-flex align-items-center gap-2 border rounded px-2 py-1 bg-body">
            <img id="waMediaThumb" alt="" class="rounded d-none" style="width:36px;height:36px;object-fit:cover;">
            <span id="waMediaName" class="small text-truncate"></span>
            <button type="button" class="btn btn-sm btn-link text-danger p-0 ms-auto" id="waMediaClear">Remove</button>
          </div>
        </div>
        <div class="px-4 py-3 bg-body-secondary border-top">
          <div class="d-flex align-items-end gap-2">
            <label class="btn btn-light btn-icon mb-0 flex-shrink-0" title="Attach media" for="waMedia">
              <i class="mdi mdi-paperclip fs-20"></i>
            </label>
            <input type="file" name="media" id="waMedia" class="d-none" accept="image/*,video/*,audio/*,.pdf,.doc,.docx,.xls,.xlsx,.txt">
            <textarea name="body" id="waBody" class="form-control" placeholder="Type a message..." rows="1"></textarea>
            <button type="submit" class="btn btn-primary btn-icon" id="waSendBtn" <?= empty($ready) ? 'disabled' : '' ?> aria-label="Send" title="Send">
              <i class="mdi mdi-send"></i>
            </button>
          </div>
        </div>
      </form>
    </div>
  </div>
</div>

<div class="modal fade" id="waStartModal" tabindex="-1">
  <div class="modal-dialog">
    <form class="modal-content" id="waStartForm">
      <div class="modal-header">
        <h6 class="modal-title">New chat</h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <label class="form-label">Phone (10-digit Indian mobile)</label>
        <input type="text" name="phone" class="form-control" inputmode="numeric" placeholder="9876543210" required>
        <label class="form-label mt-2">Name (optional)</label>
        <input type="text" name="name" class="form-control">
      </div>
      <div class="modal-footer">
        <button type="submit" class="btn btn-primary">Open chat</button>
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
  var controlUrl = app.dataset.controlUrl;
  var activeId = 0;
  var lastMsgId = 0;

  function setThreadOwner(owner) {
    var el = document.getElementById('waThreadOwner');
    var take = document.getElementById('waTakeBtn');
    var release = document.getElementById('waReleaseBtn');
    var label = owner || 'meta_agent';
    var map = { meta_agent: 'Owner: Meta Business Agent', app: 'Owner: App / teammate', human: 'Owner: Human handoff' };
    if (el) {
      el.textContent = map[label] || ('Owner: ' + label);
      el.className = 'd-block mt-1 small ' + (label === 'meta_agent' ? 'text-success' : 'text-primary');
    }
    if (take) take.disabled = !activeId || label === 'app' || label === 'human';
    if (release) release.disabled = !activeId || label === 'meta_agent';
  }
  function threadControl(action) {
    if (!activeId || !controlUrl) return;
    var fd = new FormData();
    fd.append('conversation_id', String(activeId));
    fd.append('action', action);
    fetch(controlUrl, { method: 'POST', body: fd, headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .then(function (d) {
        if (!d.success) { alert(d.message || 'Thread control failed'); return; }
        setThreadOwner(d.thread_owner || (action === 'release' ? 'meta_agent' : 'app'));
      });
  }
  var takeBtn = document.getElementById('waTakeBtn');
  var releaseBtn = document.getElementById('waReleaseBtn');
  if (takeBtn) takeBtn.addEventListener('click', function () { threadControl('take'); });
  if (releaseBtn) releaseBtn.addEventListener('click', function () { threadControl('release'); });

  function esc(s) {
    return String(s || '').replace(/[&<>"']/g, function (c) {
      return ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]);
    });
  }
  function clock(s) {
    var raw = String(s || '');
    var t = raw.slice(11, 16);
    if (t.length < 5) return '';
    var h = parseInt(t.slice(0, 2), 10);
    if (isNaN(h)) return t;
    var m = t.slice(3, 5);
    var ap = h >= 12 ? 'pm' : 'am';
    h = h % 12;
    if (h === 0) h = 12;
    return h + ':' + m + ' ' + ap;
  }
  function initials(name, phone) {
    var n = (name || phone || 'WA').trim();
    return n.slice(0, 2).toUpperCase();
  }
  function renderList(rows) {
    var box = document.getElementById('waList');
    var target = box.querySelector('.simplebar-content') || box;
    if (!rows.length) {
      target.innerHTML = '<div class="p-4 text-muted">No chats yet.</div>';
      return;
    }
    target.innerHTML = rows.map(function (r) {
      var unread = Number(r.unread || 0);
      return '<a href="#" class="rich-list-item px-4 border-bottom align-items-start' + (Number(r.id) === activeId ? ' active' : '') + '" data-id="' + r.id + '">'
        + '<div class="rich-list-prepend"><div class="avatar-xs avatar avatar-circle bg-primary-subtle text-primary fw-semibold d-flex align-items-center justify-content-center">'
        + esc(initials(r.name, r.phone)) + '</div></div>'
        + '<div class="rich-list-content"><h6 class="rich-list-title">' + esc(r.name || r.phone) + '</h6>'
        + '<span class="rich-list-subtitle">' + esc(r.last_message || '') + '</span></div>'
        + '<div class="rich-list-append gap-1 flex-column align-items-end"><small class="text-muted">' + esc(clock(r.last_at)) + '</small>'
        + (unread ? '<span class="badge badge-primary rounded-pill message-badge fs-11">' + unread + '</span>' : '')
        + '</div></a>';
    }).join('');
    target.querySelectorAll('[data-id]').forEach(function (el) {
      el.addEventListener('click', function (e) { e.preventDefault(); openThread(Number(el.dataset.id)); });
    });
  }
  function bubbleHtml(m) {
    var end = m.direction === 'out';
    var url = String(m.media_url || '');
    var http = url.indexOf('http') === 0;
    var media = '';
    var type = String(m.type || '');
    if (http && (type === 'image' || type === 'sticker')) {
      media = '<img src="' + esc(url) + '" alt="" class="rounded mb-2" style="max-width:220px;">';
    } else if (http && type === 'video') {
      media = '<video src="' + esc(url) + '" controls playsinline class="rounded mb-2" style="max-width:220px;"></video>';
    } else if (http && type === 'audio') {
      media = '<audio src="' + esc(url) + '" controls class="mb-2"></audio>';
    } else if (http && type === 'document') {
      media = '<a class="d-block mb-2" href="' + esc(url) + '" target="_blank" rel="noopener">' + esc(m.body || 'Open file') + '</a>';
    }
    var label = String(m.body || '');
    var skipText = media && (label === '' || label.toLowerCase() === type || type === 'document');
    return '<div class="chat-item ' + (end ? 'chat-item-end' : 'chat-item-start') + '" data-mid="' + m.id + '">'
      + '<div class="chat-content">' + media
      + (skipText ? '' : (label ? '<p class="chat-bubble">' + esc(label).replace(/\n/g, '<br>') + '</p>' : ''))
      + '<span class="chat-time text-muted fs-12">' + esc(clock(m.created_at)) + (m.status ? ' · ' + esc(m.status) : '') + '</span>'
      + '</div></div>';
  }
  function renderThread(payload, append) {
    var thread = document.getElementById('waThread');
    var msgs = payload.messages || [];
    if (!append) {
      thread.innerHTML = msgs.length ? msgs.map(bubbleHtml).join('') : '<div class="text-center text-muted py-5 wa-thread-empty">No messages yet.</div>';
      lastMsgId = msgs.length ? Number(msgs[msgs.length - 1].id) : 0;
    } else if (msgs.length) {
      appendBubbles(msgs);
    }
    scrollThread();
    if (payload.conversation) {
      var name = payload.conversation.name || payload.conversation.phone;
      document.getElementById('waHeadName').textContent = name;
      document.getElementById('waHeadPhone').textContent = payload.conversation.phone;
      document.getElementById('waHeadInitial').textContent = initials(payload.conversation.name, payload.conversation.phone);
      document.getElementById('waConvId').value = payload.conversation.id;
      setThreadOwner(payload.thread_owner || payload.conversation.thread_owner || 'meta_agent');
    }
  }
  function scrollThread() {
    var thread = document.getElementById('waThread');
    var scroller = thread.closest('.simplebar-content-wrapper') || thread.closest('.chat-conversation') || thread.parentElement;
    if (scroller) scroller.scrollTop = scroller.scrollHeight;
  }
  function appendBubbles(msgs) {
    var thread = document.getElementById('waThread');
    var empty = thread.querySelector('.wa-thread-empty');
    if (empty) empty.remove();
    msgs.forEach(function (m) {
      var id = Number(m.id);
      if (!id || thread.querySelector('[data-mid="' + id + '"]')) {
        if (id) lastMsgId = Math.max(lastMsgId, id);
        return;
      }
      thread.insertAdjacentHTML('beforeend', bubbleHtml(m));
      lastMsgId = Math.max(lastMsgId, id);
    });
    scrollThread();
  }
  function clearMedia() {
    var input = document.getElementById('waMedia');
    input.value = '';
    document.getElementById('waType').value = document.getElementById('waTemplate').value ? 'template' : 'text';
    document.getElementById('waMediaPreview').classList.add('d-none');
    document.getElementById('waMediaThumb').classList.add('d-none');
    document.getElementById('waMediaThumb').removeAttribute('src');
    document.getElementById('waMediaName').textContent = '';
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
    var type = document.getElementById('waTemplate').value ? 'template' : 'text';
    var preview = document.getElementById('waMediaPreview');
    var thumb = document.getElementById('waMediaThumb');
    if (!f) { clearMedia(); return; }
    var mime = f.type || '';
    if (mime.indexOf('video') === 0) type = 'video';
    else if (mime.indexOf('audio') === 0) type = 'audio';
    else if (mime.indexOf('image') === 0) type = 'image';
    else type = 'document';
    document.getElementById('waType').value = type;
    document.getElementById('waMediaName').textContent = f.name;
    preview.classList.remove('d-none');
    if (type === 'image') {
      var reader = new FileReader();
      reader.onload = function () { thumb.src = reader.result; thumb.classList.remove('d-none'); };
      reader.readAsDataURL(f);
    } else {
      thumb.classList.add('d-none');
      thumb.removeAttribute('src');
    }
  });
  document.getElementById('waMediaClear').addEventListener('click', clearMedia);
  document.getElementById('waBody').addEventListener('keydown', function (e) {
    if (e.key === 'Enter' && !e.shiftKey) {
      e.preventDefault();
      var form = document.getElementById('waComposer');
      if (typeof form.requestSubmit === 'function') form.requestSubmit();
      else form.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }));
    }
  });
  var sending = false;
  document.getElementById('waComposer').addEventListener('submit', function (e) {
    e.preventDefault();
    if (sending) return;
    if (!activeId) { alert('Select a chat first.'); return; }
    var tpl = document.getElementById('waTemplate').value;
    var hasFile = document.getElementById('waMedia').files && document.getElementById('waMedia').files.length;
    if (tpl && !hasFile) document.getElementById('waType').value = 'template';
    var body = document.getElementById('waBody').value.trim();
    if (!tpl && !hasFile && body === '') { alert('Type a message or attach a file.'); return; }
    var fd = new FormData(this);
    if (tpl) fd.set('template_id', tpl);
    var btn = document.getElementById('waSendBtn');
    var icon = btn.innerHTML;
    sending = true;
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-label="Sending"></span>';
    fetch(sendUrl, { method: 'POST', body: fd, headers: { 'Accept': 'application/json' } })
      .then(function (r) { return r.text(); })
      .then(function (t) {
        try { return JSON.parse(t); } catch (err) { return { success: false, message: 'Send failed.' }; }
      })
      .then(function (d) {
        if (d.chat_message) appendBubbles([d.chat_message]);
        if (d.thread_owner) setThreadOwner(d.thread_owner);
        if (!d.success) { alert(d.message || 'Send failed'); return; }
        document.getElementById('waBody').value = '';
        document.getElementById('waTemplate').value = '';
        clearMedia();
        loadList();
      })
      .catch(function () { alert('Send failed.'); })
      .then(function () {
        sending = false;
        btn.disabled = <?= empty($ready) ? 'true' : 'false' ?>;
        btn.innerHTML = icon;
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
