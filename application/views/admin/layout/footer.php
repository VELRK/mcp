</main><!-- end .sk-main -->
</div><!-- end .sk-wrapper -->

<!-- Scripts -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.8/js/dataTables.bootstrap5.min.js"></script>
<script src="<?= base_url('assets/admin/js/admin.js') ?>"></script>
<script src="https://cdn.jsdelivr.net/npm/quill@1.3.7/dist/quill.min.js"></script>
<script>
// Auto-dismiss flash messages
setTimeout(() => { document.querySelectorAll('.sk-flash-area .alert').forEach(a => a.classList.remove('show')); }, 4000);

// Force shell layout (guards against cached/conflicting CSS)
(function skForceShellLayout() {
  var side = document.getElementById('sk-sidebar');
  var main = document.querySelector('.sk-main');
  var wrap = document.querySelector('.sk-wrapper');
  if (wrap) {
    wrap.style.setProperty('display', 'block', 'important');
    wrap.style.setProperty('margin-top', document.body.classList.contains('sk-has-panel-banner') ? '0' : '56px', 'important');
    wrap.style.setProperty('width', '100%', 'important');
  }
  if (side) {
    var top = document.body.classList.contains('sk-has-panel-banner') ? '96px' : '56px';
    side.style.setProperty('position', 'fixed', 'important');
    side.style.setProperty('left', '0px', 'important');
    side.style.setProperty('right', 'auto', 'important');
    side.style.setProperty('top', top, 'important');
    side.style.setProperty('bottom', '0px', 'important');
    side.style.setProperty('width', side.classList.contains('collapsed') ? '0px' : '240px', 'important');
    side.style.setProperty('max-width', side.classList.contains('collapsed') ? '0px' : '240px', 'important');
    side.style.setProperty('min-width', '0px', 'important');
    side.style.setProperty('overflow-x', 'hidden', 'important');
    side.style.setProperty('overflow-y', 'auto', 'important');
    side.style.setProperty('z-index', '1030', 'important');
    side.style.setProperty('background', '#212529', 'important');
  }
  if (main) {
    var collapsed = side && side.classList.contains('collapsed');
    main.style.setProperty('display', 'block', 'important');
    main.style.setProperty('margin-left', collapsed ? '0px' : '240px', 'important');
    main.style.setProperty('width', collapsed ? '100%' : 'calc(100% - 240px)', 'important');
    main.style.setProperty('max-width', collapsed ? '100%' : 'calc(100% - 240px)', 'important');
    main.style.setProperty('box-sizing', 'border-box', 'important');
    main.style.setProperty('float', 'none', 'important');
    main.style.setProperty('position', 'relative', 'important');
    main.style.setProperty('left', 'auto', 'important');
  }
})();

document.getElementById('sidebarToggle')?.addEventListener('click', () => {
  document.getElementById('sk-sidebar')?.classList.toggle('collapsed');
  document.querySelector('.sk-main')?.classList.toggle('expanded');
  document.querySelector('.sk-wrapper')?.classList.toggle('sk-sidebar-collapsed');
  // re-apply after toggle
  var side = document.getElementById('sk-sidebar');
  var main = document.querySelector('.sk-main');
  var collapsed = side && side.classList.contains('collapsed');
  if (side) {
    side.style.setProperty('width', collapsed ? '0px' : '240px', 'important');
    side.style.setProperty('max-width', collapsed ? '0px' : '240px', 'important');
  }
  if (main) {
    main.style.setProperty('margin-left', collapsed ? '0px' : '240px', 'important');
    main.style.setProperty('width', collapsed ? '100%' : 'calc(100% - 240px)', 'important');
    main.style.setProperty('max-width', collapsed ? '100%' : 'calc(100% - 240px)', 'important');
  }
});
// Init DataTables
if (window.$ && $.fn && $.fn.dataTable) {
  $.fn.dataTable.ext.errMode = 'console';
}
document.querySelectorAll('.sk-datatable').forEach(t => {
  if (!$.fn.DataTable.isDataTable(t)) $(t).DataTable({ pageLength: 15, order: [] });
});
</script>
<?php if (isset($extra_js)): ?>
  <?= $extra_js ?>
<?php endif; ?>
</body>
</html>
