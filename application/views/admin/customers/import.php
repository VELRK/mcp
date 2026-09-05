<div class="sk-page-header d-flex justify-content-between align-items-center flex-wrap gap-2">
  <h5 class="sk-page-title mb-0"><i class="bi bi-upload me-2 text-warning"></i>Import Customers</h5>
  <a href="<?= site_url('admin/customers') ?>" class="btn btn-sm btn-outline-secondary">
    <i class="bi bi-arrow-left me-1"></i> Back
  </a>
</div>

<div class="row g-3">
  <div class="col-lg-7">
    <form method="post" action="<?= site_url('admin/customers/import') ?>" enctype="multipart/form-data"
          class="card sk-table-card shadow-sm">
      <div class="card-body">
        <p class="text-muted small mb-3">
          Upload a CSV exported from Excel. Required columns: <code>name</code>, <code>email</code>.
          Optional: <code>phone</code>, <code>password</code>, <code>status</code> (1/0 or Active/Blocked).
        </p>
        <div class="mb-3">
          <label class="form-label">CSV file <span class="text-danger">*</span></label>
          <input type="file" name="import_file" class="form-control" accept=".csv,text/csv" required>
        </div>
        <div class="alert alert-light border small mb-0">
          Duplicate emails/phones are skipped. Empty password cells get a random password.
        </div>
      </div>
      <div class="card-footer bg-white d-flex flex-wrap gap-2">
        <button type="submit" class="btn btn-warning fw-semibold">
          <i class="bi bi-upload me-1"></i> Import Customers
        </button>
        <a href="<?= site_url('admin/customers/import_template') ?>" class="btn btn-outline-secondary">
          <i class="bi bi-file-earmark-spreadsheet me-1"></i> Download template
        </a>
      </div>
    </form>
  </div>
  <div class="col-lg-5">
    <div class="card sk-table-card shadow-sm">
      <div class="card-header bg-white border-0 py-3 fw-semibold">How to import from Excel</div>
      <div class="card-body small text-muted">
        <ol class="mb-0 ps-3">
          <li class="mb-2">Download the template or export the current customer list.</li>
          <li class="mb-2">Open the file in Excel, edit rows, then <strong>Save As → CSV UTF-8</strong>.</li>
          <li class="mb-2">Upload that CSV here.</li>
          <li>Status values: <code>1</code> / Active, or <code>0</code> / Blocked.</li>
        </ol>
      </div>
    </div>
  </div>
</div>
