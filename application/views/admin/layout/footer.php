            </div> <!-- container-fluid -->
        </div> <!-- page-content -->
        <footer class="footer">
            <div class="container-fluid">
                <div class="row">
                    <div class="col-sm-6">
                        <script>document.write(new Date().getFullYear())</script> © 2DEAL.
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
</script>
<?php if (isset($extra_js)): ?>
  <?= $extra_js ?>
<?php endif; ?>
</body>
</html>
