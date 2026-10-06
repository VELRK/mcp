<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title><?= htmlspecialchars($title ?? 'Talk AI Pilot Admin') ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
  <meta name="csrf-token-name" content="<?= htmlspecialchars($this->security->get_csrf_token_name(), ENT_QUOTES) ?>">
  <meta name="csrf-token" content="<?= htmlspecialchars($this->security->get_csrf_hash(), ENT_QUOTES) ?>">
  <link rel="shortcut icon" href="<?= base_url('assets/aquiry/images/logo-sm.png') ?>">
  <link rel="stylesheet" href="<?= base_url('assets/aquiry/libs/simplebar/simplebar.min.css') ?>">
  <link href="<?= base_url('assets/aquiry/css/bootstrap.min.css') ?>" id="bootstrap-style" rel="stylesheet" type="text/css">
  <link href="<?= base_url('assets/aquiry/css/icons.min.css') ?>" rel="stylesheet" type="text/css">
  <link href="<?= base_url('assets/aquiry/css/app.min.css') ?>" id="app-style" rel="stylesheet" type="text/css">
  <link rel="stylesheet" href="https://cdn.datatables.net/1.13.8/css/dataTables.bootstrap5.min.css">
  <link rel="stylesheet" href="<?= base_url('assets/admin/css/admin-theme.css') ?>">
  <style>
    .brand-mark { font-weight: 700; letter-spacing: -0.02em; line-height: 1; white-space: nowrap; }
    .brand-mark .brand-ai { color: var(--bs-primary); }
    .logo-light .brand-mark { color: #fff; }
  </style>
</head>
<body>
<div id="layout-wrapper">
    <header id="page-topbar">
        <div class="navbar-header">
            <div class="navbar-logo-box">
                <a href="<?= site_url('admin/dashboard') ?>" class="logo logo-dark">
                    <span class="logo-sm">
                        <span class="brand-mark fs-16">AI</span>
                    </span>
                    <span class="logo-lg">
                        <span class="brand-mark fs-16 text-body">Talk <span class="brand-ai">AI</span> Pilot</span>
                    </span>
                </a>
                <a href="<?= site_url('admin/dashboard') ?>" class="logo logo-light">
                    <span class="logo-sm">
                        <span class="brand-mark fs-16">AI</span>
                    </span>
                    <span class="logo-lg">
                        <span class="brand-mark fs-16">Talk <span class="brand-ai">AI</span> Pilot</span>
                    </span>
                </a>
                <button type="button" class="btn btn-icon top-icon sidebar-btn" id="sidebar-btn" aria-label="Toggle navigation">
                    <i class="mdi mdi-menu-open align-middle fs-17"></i>
                </button>
            </div>

            <div class="d-flex justify-content-between menu-sm px-4 ms-auto">
                <div class="d-flex align-items-center gap-2">
                    <?php if (!empty($impersonating)): ?>
                    <span class="badge badge-label-info d-none d-md-inline-flex align-items-center gap-1">
                        Viewing as vendor
                        <a href="<?= site_url('admin/vendors/stop_impersonate') ?>" class="text-reset fw-semibold">Exit</a>
                    </span>
                    <?php elseif (!empty($vendor_logged_in)): ?>
                    <span class="badge badge-label-primary d-none d-md-inline-flex"><?= htmlspecialchars($admin['shop_name'] ?? $admin['name'] ?? 'Store') ?></span>
                    <?php endif; ?>
                </div>
                <div class="d-flex align-items-center gap-3">
                    <form class="app-search d-none d-lg-block me-2" action="<?= site_url('admin/products') ?>" method="get">
                        <div class="position-relative">
                            <input type="text" class="form-control" name="search" placeholder="Search...">
                            <i data-eva="search-outline" class="align-middle"></i>
                        </div>
                    </form>
                    <button type="button" class="btn btn-icon top-icon d-none d-md-block" id="light-dark-mode" aria-label="Toggle Light/Dark">
                        <i class="mdi mdi-brightness-7 align-middle"></i>
                        <i class="mdi mdi-white-balance-sunny align-middle"></i>
                    </button>
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
                    <a href="<?= site_url('admin/whatsapp_requests/pending') ?>" class="btn btn-icon top-icon position-relative" aria-label="WhatsApp requests">
                        <i class="mdi mdi-bell-ring-outline fs-17"></i>
                        <?php if ($pending_count > 0): ?>
                        <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger"><?= (int) $pending_count ?></span>
                        <?php endif; ?>
                    </a>
                    <?php endif; ?>
                    <div class="dropdown d-inline-block ps-3 ms-2 border-start admin-user-info">
                        <button type="button" class="btn btn-sm p-0" id="page-header-user-dropdown" data-bs-toggle="dropdown" aria-expanded="false">
                            <span class="avatar avatar-xs avatar-circle bg-primary-subtle text-primary fw-semibold d-inline-flex align-items-center justify-content-center">
                                <?= strtoupper(substr((string) ($admin['name'] ?? 'A'), 0, 1)) ?>
                            </span>
                            <span class="d-none d-xl-inline-block ms-1 fw-semibold fs-14 admin-name"><?= htmlspecialchars($admin['name'] ?? 'Admin') ?></span>
                            <i class="mdi mdi-chevron-down align-middle fs-16 d-none d-xl-inline-block"></i>
                        </button>
                        <div class="dropdown-menu dropdown-menu-end dropdown-menu-animated">
                            <div class="dropdown-header">
                                <h6 class="mb-0"><?= htmlspecialchars($admin['name'] ?? 'Admin') ?></h6>
                                <span class="text-muted fs-12"><?= htmlspecialchars($admin['email'] ?? '') ?></span>
                            </div>
                            <div class="dropdown-divider"></div>
                            <?php if (!empty($vendor_logged_in)): ?>
                            <a class="dropdown-item" href="<?= site_url('admin/vendor/account/password') ?>"><i class="mdi mdi-shield-lock-outline me-2"></i>Change Password</a>
                            <?php endif; ?>
                            <a class="dropdown-item text-danger" href="<?= site_url(!empty($vendor_logged_in) ? 'admin/vendor/logout' : 'admin/logout') ?>">
                                <i class="mdi mdi-logout me-2"></i>Logout
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </header>
