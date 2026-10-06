            </div> <!-- container-fluid -->
        </div> <!-- page-content -->
        <footer class="footer">
            <div class="container-fluid">
                <div class="row align-items-center">
                    <div class="col-sm-6">
                        <script>document.write(new Date().getFullYear())</script> © Talk AI Pilot.
                    </div>
                    <div class="col-sm-6">
                        <div class="text-sm-end d-none d-sm-block">Talk AI Pilot</div>
                    </div>
                </div>
            </div>
        </footer>
    </div> <!-- main-content -->
</div> <!-- END layout-wrapper -->

<!-- JAVASCRIPT -->
<script src="<?= base_url('assets/aquiry/libs/jquery/jquery.min.js') ?>"></script>
<script src="<?= base_url('assets/aquiry/libs/bootstrap/js/bootstrap.bundle.min.js') ?>"></script>
<script src="<?= base_url('assets/aquiry/libs/metismenu/metisMenu.min.js') ?>"></script>
<script src="<?= base_url('assets/aquiry/libs/simplebar/simplebar.min.js') ?>"></script>
<script src="<?= base_url('assets/aquiry/libs/eva-icons/eva.min.js') ?>"></script>

<script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.8/js/dataTables.bootstrap5.min.js"></script>
<script src="<?= base_url('assets/aquiry/js/app.js') ?>"></script>

<script>
// Auto-dismiss flash messages
setTimeout(() => { document.querySelectorAll('.sk-flash-area .alert').forEach(a => a.classList.remove('show')); }, 4000);

// Init DataTables
if (window.$ && $.fn && $.fn.dataTable) {
  $.fn.dataTable.ext.errMode = 'console';
}
document.querySelectorAll('.sk-datatable').forEach(t => {
  if (!$.fn.DataTable.isDataTable(t)) $(t).DataTable({ pageLength: 15, order: [] });
});

// CSRF for every admin form / fetch / jQuery POST (CodeIgniter csrf_protection).
(function () {
  var nameMeta = document.querySelector('meta[name="csrf-token-name"]');
  var hashMeta = document.querySelector('meta[name="csrf-token"]');
  var csrfName = nameMeta ? nameMeta.getAttribute('content') : 'csrf_token';
  var csrfHash = hashMeta ? hashMeta.getAttribute('content') : '';

  function csrfToken() {
    var m = document.cookie.match(/(?:^|; )csrf_cookie=([^;]*)/);
    return m ? decodeURIComponent(m[1]) : csrfHash;
  }

  function injectForm(form) {
    if (!form || !form.tagName || form.tagName !== 'FORM') return;
    var method = (form.getAttribute('method') || 'get').toLowerCase();
    if (method !== 'post') return;
    var input = form.querySelector('input[name="' + csrfName + '"]');
    if (!input) {
      input = document.createElement('input');
      input.type = 'hidden';
      input.name = csrfName;
      form.appendChild(input);
    }
    input.value = csrfToken();
  }

  function injectAllForms(root) {
    (root || document).querySelectorAll('form').forEach(injectForm);
  }

  injectAllForms();
  document.addEventListener('submit', function (e) {
    injectForm(e.target);
  }, true);

  var origFetch = window.fetch;
  window.fetch = function (input, init) {
    init = init ? Object.assign({}, init) : {};
    var method = String(init.method || 'GET').toUpperCase();
    if (method === 'POST' || method === 'PUT' || method === 'PATCH' || method === 'DELETE') {
      if (!init.credentials) init.credentials = 'same-origin';
      var body = init.body;
      var tok = csrfToken();
      if (body instanceof FormData) {
        body.set(csrfName, tok);
      } else if (body instanceof URLSearchParams) {
        body.set(csrfName, tok);
      } else if (typeof body === 'string' && body.length && body.charAt(0) !== '{' && body.charAt(0) !== '[') {
        var params = new URLSearchParams(body);
        params.set(csrfName, tok);
        init.body = params.toString();
      } else if (body == null || body === '') {
        var p = new URLSearchParams();
        p.set(csrfName, tok);
        init.body = p;
        var headers = new Headers(init.headers || {});
        if (!headers.has('Content-Type') && !headers.has('content-type')) {
          headers.set('Content-Type', 'application/x-www-form-urlencoded;charset=UTF-8');
        }
        init.headers = headers;
      }
    }
    return origFetch.call(this, input, init).then(function (res) {
      var next = csrfToken();
      if (next && hashMeta) hashMeta.setAttribute('content', next);
      csrfHash = next || csrfHash;
      injectAllForms();
      return res;
    });
  };

  if (window.jQuery) {
    jQuery.ajaxSetup({
      beforeSend: function (_xhr, settings) {
        var type = String(settings.type || 'GET').toUpperCase();
        if (type === 'GET' || type === 'HEAD') return;
        var tok = csrfToken();
        if (settings.data instanceof FormData) {
          settings.data.set(csrfName, tok);
        } else if (typeof settings.data === 'string') {
          var sp = new URLSearchParams(settings.data);
          sp.set(csrfName, tok);
          settings.data = sp.toString();
        } else if (settings.data && typeof settings.data === 'object') {
          settings.data[csrfName] = tok;
        } else {
          settings.data = csrfName + '=' + encodeURIComponent(tok);
        }
      }
    });
  }
})();
</script>
<?php if (isset($extra_js)): ?>
  <?= $extra_js ?>
<?php endif; ?>
</body>
</html>
