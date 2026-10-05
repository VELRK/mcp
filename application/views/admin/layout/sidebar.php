<?php
$uri = $this->uri->segment(2); // e.g. 'dashboard', 'products'
if (!function_exists('sk_active')) {
  function sk_active($seg, $match) { return $seg === $match ? 'active' : ''; }
}
?>
    <!-- Start Sidebar -->
    <div class="app-menu navbar-menu">
        <!-- LOGO -->
        <div class="navbar-brand-box text-center">
            <a href="<?= site_url('shopkart/dashboard') ?>" class="logo logo-dark">
                <span class="logo-sm">
                    <h4 class="mt-4 mb-0 text-white"><i class="bi bi-bag-heart-fill text-warning"></i></h4>
                </span>
                <span class="logo-lg">
                    <h4 class="mt-4 mb-0 text-white"><i class="bi bi-bag-heart-fill text-warning me-1"></i> 2DEAL</h4>
                </span>
            </a>
            <a href="<?= site_url('shopkart/dashboard') ?>" class="logo logo-light">
                <span class="logo-sm">
                    <h4 class="mt-4 mb-0 text-white"><i class="bi bi-bag-heart-fill text-warning"></i></h4>
                </span>
                <span class="logo-lg">
                    <h4 class="mt-4 mb-0 text-white"><i class="bi bi-bag-heart-fill text-warning me-1"></i> 2DEAL</h4>
                </span>
            </a>
        </div>

        <div data-simplebar class="h-100">
            <!--- Sidemenu -->
            <div id="sidebar-menu">
                <ul class="metismenu list-unstyled" id="side-menu">
                    <li class="menu-title">Main Menu</li>

                    <li>
                        <a href="<?= site_url('shopkart/dashboard') ?>" class="<?= sk_active($uri,'dashboard') ?>">
                            <i class="mdi mdi-speedometer"></i>
                            <span>Dashboard</span>
                        </a>
                    </li>

                    <?php if (empty($impersonating) && empty($vendor_logged_in) && ($admin['role'] ?? '') === 'superadmin'): ?>
                    <li class="menu-title">Marketplace</li>
                    <li>
                        <a href="<?= site_url('shopkart/vendors') ?>" class="<?= sk_active($uri,'vendors') ?>">
                            <i class="mdi mdi-store"></i>
                            <span>Vendors</span>
                        </a>
                    </li>
                    <?php endif; ?>

                    <?php if (!empty($impersonating) || (!empty($vendor_context) && $vendor_context->vendor_id())): ?>
                    <li>
                        <a href="<?= site_url('shopkart/stores/edit/'.($vendor_context->vendor_id() ?? '')) ?>" class="<?= sk_active($uri,'stores') ?>">
                            <i class="mdi mdi-store"></i>
                            <span>My Store</span>
                        </a>
                    </li>
                    <?php if (!empty($vendor_logged_in)): ?>
                    <li>
                        <a href="<?= site_url('admin/vendor/account/password') ?>" class="<?= strpos((string) uri_string(), 'vendor/account') !== false ? 'active' : '' ?>">
                            <i class="mdi mdi-shield-lock"></i>
                            <span>Change Password</span>
                        </a>
                    </li>
                    <?php endif; ?>
                    <?php endif; ?>

                    <li class="menu-title">Catalog</li>
                    <li>
                        <a href="<?= site_url('shopkart/products') ?>" class="<?= sk_active($uri,'products') ?>">
                            <i class="mdi mdi-package-variant"></i>
                            <span>Products</span>
                        </a>
                    </li>
                    <li>
                        <a href="<?= site_url('shopkart/inventory') ?>" class="<?= sk_active($uri,'inventory') ?>">
                            <i class="mdi mdi-clipboard-list"></i>
                            <span>Inventory</span>
                        </a>
                    </li>
                    <li>
                        <a href="<?= site_url('shopkart/categories') ?>" class="<?= sk_active($uri,'categories') ?>">
                            <i class="mdi mdi-sitemap"></i>
                            <span>Categories</span>
                        </a>
                    </li>
                    <li>
                        <a href="<?= site_url('shopkart/brands') ?>" class="<?= sk_active($uri,'brands') ?>">
                            <i class="mdi mdi-tag"></i>
                            <span>Brands</span>
                        </a>
                    </li>
                    <li>
                        <a href="<?= site_url('shopkart/variant-units') ?>" class="<?= sk_active($uri,'variant-units') ?>">
                            <i class="mdi mdi-ruler"></i>
                            <span>Variant Units</span>
                        </a>
                    </li>

                    <li class="menu-title">Orders & Content</li>
                    <li>
                        <a href="<?= site_url('shopkart/orders') ?>" class="<?= sk_active($uri,'orders') ?>">
                            <i class="mdi mdi-cart"></i>
                            <span>Orders</span>
                        </a>
                    </li>
                    <li>
                        <a href="<?= site_url('shopkart/banners') ?>" class="<?= sk_active($uri,'banners') ?>">
                            <i class="mdi mdi-image-multiple"></i>
                            <span>Banners</span>
                        </a>
                    </li>
                    <li>
                        <a href="<?= site_url('shopkart/promos') ?>" class="<?= sk_active($uri,'promos') ?>">
                            <i class="mdi mdi-ticket-percent"></i>
                            <span>Promo Codes</span>
                        </a>
                    </li>
                    <li>
                        <a href="<?= site_url('shopkart/customers') ?>" class="<?= sk_active($uri,'customers') ?>">
                            <i class="mdi mdi-account-group"></i>
                            <span>Customers</span>
                        </a>
                    </li>

                    <?php if (empty($impersonating) && empty($vendor_logged_in) && ($admin['role'] ?? '') === 'superadmin'): ?>
                    <li class="menu-title">Settings</li>
                    <li>
                        <a href="<?= site_url('shopkart/settings') ?>" class="<?= sk_active($uri,'settings') ?>">
                            <i class="mdi mdi-cog"></i>
                            <span>Site Settings</span>
                        </a>
                    </li>
                    <?php endif; ?>
                </ul>
            </div>
            <!-- Sidebar -->
        </div>
    </div>
    <!-- End Sidebar -->

    <!-- ============================================================== -->
    <!-- Start right Content here -->
    <!-- ============================================================== -->
    <div class="main-content">
        <div class="page-content">
            <div class="container-fluid">
                
                <!-- Flash Messages -->
                <div class="sk-flash-area mb-3">
                <?php if ($this->session->flashdata('success')): ?>
                <div class="alert alert-success alert-dismissible fade show shadow-sm" role="alert">
                    <i class="mdi mdi-check-circle me-1"></i> <?= $this->session->flashdata('success') ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>
                <?php if ($this->session->flashdata('error')): ?>
                <div class="alert alert-danger alert-dismissible fade show shadow-sm" role="alert">
                    <i class="mdi mdi-alert-circle me-1"></i> <?= $this->session->flashdata('error') ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>
                </div>
