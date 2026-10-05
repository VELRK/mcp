<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title><?= $title ?? '2DEAL Admin' ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="shortcut icon" href="<?= base_url('assets/aquiry/images/logo-sm.png') ?>">
  <!-- Bootstrap Css -->
  <link href="<?= base_url('assets/aquiry/css/bootstrap.min.css') ?>" id="bootstrap-style" rel="stylesheet" type="text/css">
  <!-- Icons Css -->
  <link href="<?= base_url('assets/aquiry/css/icons.min.css') ?>" rel="stylesheet" type="text/css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <!-- App Css-->
  <link href="<?= base_url('assets/aquiry/css/app.min.css') ?>" id="app-style" rel="stylesheet" type="text/css">
  <!-- Custom Admin CSS -->
  <link rel="stylesheet" href="<?= base_url('assets/admin/css/admin.css') ?>?v=20260920d">
  <link rel="stylesheet" href="<?= base_url('assets/admin/css/admin_modern.css?v='.time()) ?>">
  <!-- Chart.js and DataTables -->
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
  <link rel="stylesheet" href="https://cdn.datatables.net/1.13.8/css/dataTables.bootstrap5.min.css">
</head>
<?php
$has_panel_banner = !empty($impersonating) || !empty($vendor_logged_in);
$body_class = trim(
    ($has_panel_banner ? 'sk-has-panel-banner ' : '') .
    (!empty($vendor_logged_in) ? 'sk-vendor-panel ' : '') .
    (!empty($impersonating) ? 'sk-impersonating ' : '')
);
?>
<body class="<?= $body_class ?>" data-sidebar="dark">

<div id="layout-wrapper">
    <!-- Start topbar -->
    <header id="page-topbar">
        <div class="navbar-header">
            <div class="navbar-logo-box">
                <a href="<?= site_url('shopkart/dashboard') ?>" class="logo logo-dark">
                    <span class="logo-lg"><h4 class="mt-4 mb-0 text-white"><i class="bi bi-bag-heart-fill text-warning me-1"></i> 2DEAL</h4></span>
                </a>
                <a href="<?= site_url('shopkart/dashboard') ?>" class="logo logo-light">
                    <span class="logo-lg"><h4 class="mt-4 mb-0 text-white"><i class="bi bi-bag-heart-fill text-warning me-1"></i> 2DEAL</h4></span>
                </a>
                <button type="button" class="btn btn-icon top-icon sidebar-btn" id="sidebar-btn" aria-label="Toggle navigation">
                    <i class="mdi mdi-menu-open align-middle fs-17"></i>
                </button>
            </div>
    
            <div class="d-flex justify-content-between menu-sm px-4 ms-auto">
                <div class="d-flex align-items-center gap-2">
                    <?php if (!empty($impersonating)): ?>
                    <div class="alert alert-info rounded-pill mb-0 py-1 px-3 d-none d-md-flex align-items-center">
                      <i class="bi bi-person-badge me-2"></i> Viewing as vendor
                      <a href="<?= site_url('admin/vendors/stop_impersonate') ?>" class="alert-link ms-2 fw-semibold">Exit</a>
                    </div>
                    <?php elseif (!empty($vendor_logged_in)): ?>
                    <div class="alert alert-primary rounded-pill mb-0 py-1 px-3 d-none d-md-flex align-items-center">
                      <i class="bi bi-shop me-2"></i> <?= htmlspecialchars($admin['shop_name'] ?? $admin['name'] ?? 'Store') ?>
                    </div>
                    <?php endif; ?>
                </div>
                
                <div class="d-flex align-items-center gap-3">
                    <?php if (empty($impersonating) && empty($vendor_logged_in) && ($admin['role'] ?? '') === 'superadmin'): ?>
                    <?php
                        $pending_count = 0;
                        try {
                        $this->load->model('Sk_Wa_Provision_request_model');
                        if (isset($this->Sk_Wa_Provision_request_model)) {
                            $pending = $this->Sk_Wa_Provision_request_model->get_pending();
                            $pending_count = is_array($pending) ? count($pending) : 0;
                        }
                        } catch (Throwable $e) {}
                    ?>
                    <div class="dropdown d-inline-block">
                        <a href="<?= site_url('admin/whatsapp_requests/pending') ?>" class="btn btn-icon top-icon position-relative">
                            <i class="mdi mdi-whatsapp fs-17"></i>
                            <?php if ($pending_count > 0): ?>
                            <span class="position-absolute top-25 start-100 translate-middle badge rounded-pill bg-danger"><?= (int)$pending_count ?></span>
                            <?php endif; ?>
                        </a>
                    </div>
                    <?php endif; ?>

                    <div class="dropdown d-inline-block">
                        <button type="button" class="btn btn-icon top-icon" id="page-header-user-dropdown" data-bs-toggle="dropdown" aria-expanded="false">
                            <div class="avatar-sm rounded-circle bg-primary bg-opacity-10 text-primary d-flex align-items-center justify-content-center fw-bold header-profile-user">
                                <?= substr(htmlspecialchars($admin['name'] ?? 'A'), 0, 1) ?>
                            </div>
                            <span class="d-none d-xl-inline-block ms-1"><?= htmlspecialchars($admin['name'] ?? 'Admin') ?></span>
                            <i class="mdi mdi-chevron-down d-none d-xl-inline-block"></i>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="<?= site_url(!empty($vendor_logged_in) ? 'admin/vendor/logout' : 'shopkart/logout') ?>"><i class="mdi mdi-logout font-size-16 align-middle me-1 text-danger"></i> <span class="text-danger">Logout</span></a></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </header>
