<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= $title ?? '2DEAL Admin' ?></title>
  <!-- Bootstrap 5 -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
  <!-- Bootstrap Icons -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <!-- DataTables -->
  <link rel="stylesheet" href="https://cdn.datatables.net/1.13.8/css/dataTables.bootstrap5.min.css">
  <!-- Custom Admin CSS -->
  <link rel="stylesheet" href="<?= base_url('assets/admin/css/admin.css') ?>?v=20260920d">
  <!-- Chart.js — must be in <head> so inline chart init scripts in views can use it -->
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
  <!-- Quill rich-text editor -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/quill@1.3.7/dist/quill.snow.css">
  <style>
    .ql-toolbar  { border-top-left-radius:6px; border-top-right-radius:6px; background:#f8f9fa; }
    .ql-container{ border-bottom-left-radius:6px; border-bottom-right-radius:6px; }
    .ql-editor   { min-height:180px; font-size:14px; line-height:1.6; }
    .ql-editor-lg .ql-editor { min-height:260px; }
    .ql-font .ql-picker-label,
    .ql-font .ql-picker-item { width:110px; }
    .ql-size .ql-picker-label,
    .ql-size .ql-picker-item { width:80px; }
    .ql-font .ql-picker-label::before { content: attr(data-value, 'Font'); }
    .ql-size .ql-picker-label::before { content: attr(data-value, 'Size'); }
    .ql-editor img { max-width:100%; height:auto; border-radius:4px; }

    /* Critical admin shell — last in head, beats cached/conflicting rules */
    html, body { margin:0 !important; padding:0 !important; width:100% !important; max-width:100% !important; overflow-x:hidden !important; background:#f4f6f9 !important; }
    .sk-topbar { height:56px !important; min-height:56px !important; }
    .sk-wrapper { display:block !important; margin:56px 0 0 0 !important; padding:0 !important; width:100% !important; max-width:100% !important; background:#f4f6f9 !important; }
    body.sk-has-panel-banner .sk-wrapper { margin-top:0 !important; }
    #sk-sidebar, aside.sk-sidebar {
      position:fixed !important;
      left:0 !important;
      top:56px !important;
      bottom:0 !important;
      right:auto !important;
      width:240px !important;
      max-width:240px !important;
      min-width:0 !important;
      margin:0 !important;
      padding:0 !important;
      height:auto !important;
      overflow-x:hidden !important;
      overflow-y:auto !important;
      z-index:1030 !important;
      background:#212529 !important;
      transform:none !important;
    }
    body.sk-has-panel-banner #sk-sidebar,
    body.sk-has-panel-banner aside.sk-sidebar { top:96px !important; }
    #sk-sidebar.collapsed, aside.sk-sidebar.collapsed {
      width:0 !important;
      max-width:0 !important;
      overflow:hidden !important;
    }
    main.sk-main, .sk-main {
      display:block !important;
      margin:0 0 0 240px !important;
      padding:1.5rem !important;
      width:calc(100% - 240px) !important;
      max-width:calc(100% - 240px) !important;
      min-width:0 !important;
      box-sizing:border-box !important;
      background:#f4f6f9 !important;
      position:relative !important;
      left:auto !important;
      right:auto !important;
      float:none !important;
      transform:none !important;
    }
    main.sk-main.expanded, .sk-main.expanded {
      margin-left:0 !important;
      width:100% !important;
      max-width:100% !important;
    }
    @media (max-width:768px) {
      main.sk-main, .sk-main { margin-left:0 !important; width:100% !important; max-width:100% !important; }
    }
  </style>
</head>
<?php
$has_panel_banner = !empty($impersonating) || !empty($vendor_logged_in);
$body_class = trim(
    ($has_panel_banner ? 'sk-has-panel-banner ' : '') .
    (!empty($vendor_logged_in) ? 'sk-vendor-panel ' : '') .
    (!empty($impersonating) ? 'sk-impersonating ' : '')
);
?>
<body class="<?= $body_class ?>">

<!-- Top Navbar -->
<nav class="navbar navbar-dark bg-dark fixed-top sk-topbar">
  <div class="container-fluid">
    <a class="navbar-brand fw-bold" href="<?= site_url('shopkart/dashboard') ?>">
      <i class="bi bi-bag-heart-fill text-warning me-1"></i> 2DEAL
    </a>
    <button type="button" class="btn btn-sm btn-outline-secondary" id="sidebarToggle" title="Toggle menu">
      <i class="bi bi-list"></i>
    </button>
    <div class="sk-topbar-right">
      <?php if (!empty($vendor_logged_in)): ?>
      <span class="badge bg-primary d-none d-md-inline">Vendor</span>
      <?php endif; ?>
      <?php if (empty($impersonating) && empty($vendor_logged_in) && ($admin['role'] ?? '') === 'superadmin'): ?>
      <?php
        $pending_count = 0;
        try {
          $this->load->model('Sk_Wa_Provision_request_model');
          if (isset($this->Sk_Wa_Provision_request_model)) {
            $pending = $this->Sk_Wa_Provision_request_model->get_pending();
            $pending_count = is_array($pending) ? count($pending) : 0;
          }
        } catch (Throwable $e) {
          $pending_count = 0;
        }
      ?>
      <a href="<?= site_url('admin/whatsapp_requests/pending') ?>" class="btn btn-sm btn-outline-light position-relative" title="WA Requests">
        <i class="bi bi-telephone-forward"></i>
        <?php if ($pending_count > 0): ?>
          <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger"><?= (int)$pending_count ?></span>
        <?php endif; ?>
      </a>
      <?php endif; ?>
      <span class="text-white-50 small d-none d-md-inline" title="<?= htmlspecialchars($admin['email'] ?? '') ?>">
        <?= htmlspecialchars($admin['name'] ?? 'Admin') ?>
      </span>
      <a href="<?= site_url(!empty($vendor_logged_in) ? 'admin/vendor/logout' : 'shopkart/logout') ?>" class="btn btn-sm btn-outline-danger" title="Logout">
        <i class="bi bi-box-arrow-right"></i>
      </a>
    </div>
  </div>
</nav>

<?php if (!empty($impersonating)): ?>
<div class="alert alert-info rounded-0 mb-0 py-2 text-center sk-panel-banner">
  <i class="bi bi-person-badge me-1"></i> Viewing as vendor
  <a href="<?= site_url('admin/vendors/stop_impersonate') ?>" class="alert-link ms-2 fw-semibold">Exit vendor view</a>
</div>
<?php elseif (!empty($vendor_logged_in)): ?>
<div class="alert alert-primary rounded-0 mb-0 py-2 text-center sk-panel-banner">
  <i class="bi bi-shop me-1"></i>
  Vendor panel — signed in as <strong><?= htmlspecialchars($admin['shop_name'] ?? $admin['name'] ?? 'Store') ?></strong>
</div>
<?php endif; ?>

<!-- Flash Messages -->
<div class="sk-flash-area">
<?php if ($this->session->flashdata('success')): ?>
  <div class="alert alert-success alert-dismissible fade show shadow" role="alert">
    <i class="bi bi-check-circle me-1"></i> <?= $this->session->flashdata('success') ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
  </div>
<?php endif; ?>
<?php if ($this->session->flashdata('error')): ?>
  <div class="alert alert-danger alert-dismissible fade show shadow" role="alert">
    <i class="bi bi-exclamation-triangle me-1"></i> <?= $this->session->flashdata('error') ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
  </div>
<?php endif; ?>
</div>

<div class="sk-wrapper">
