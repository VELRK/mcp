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
// Sidebar toggle
document.getElementById('sidebarToggle')?.addEventListener('click', () => {
  document.getElementById('sk-sidebar')?.classList.toggle('collapsed');
  document.querySelector('.sk-main')?.classList.toggle('expanded');
  document.querySelector('.sk-wrapper')?.classList.toggle('sk-sidebar-collapsed');
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
