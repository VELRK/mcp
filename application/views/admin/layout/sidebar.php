<?php
$admin = $admin ?? [];
$vendor_context = $vendor_context ?? null;
$uri = (string) $this->uri->segment(2);
$uri3 = (string) $this->uri->segment(3);
if (!function_exists('sk_active')) {
    function sk_active($seg, $match) { return $seg === $match ? 'active' : ''; }
}
$is_super = empty($impersonating) && empty($vendor_logged_in) && ($admin['role'] ?? '') === 'superadmin';
$is_vendor = !empty($impersonating) || (!empty($vendor_context) && $vendor_context->vendor_id());
$open = static function (array $keys) use ($uri) {
    return in_array($uri, $keys, true) ? 'mm-active' : '';
};
?>
    <div class="sidebar-left">
        <div class="sidebar-slide h-100" data-simplebar>
            <div id="sidebar-menu">
                <ul class="left-menu list-unstyled" id="side-menu">
                    <li class="<?= $open(['dashboard', '']) ?>">
                        <a href="<?= site_url('admin/dashboard') ?>" class="<?= sk_active($uri, 'dashboard') ?>">
                            <i data-eva="compass-outline"></i>
                            <span>Dashboard</span>
                        </a>
                    </li>

                    <li class="menu-title">Store</li>
                    <li class="<?= $open(['products']) ?>">
                        <a href="javascript:void(0);" class="has-arrow">
                            <i data-eva="shopping-bag-outline"></i>
                            <span>Products</span>
                        </a>
                        <ul class="sub-menu" aria-expanded="false">
                            <li><a href="<?= site_url('admin/products') ?>" class="<?= ($uri === 'products' && $uri3 !== 'add') ? 'active' : '' ?>">Product List</a></li>
                            <li><a href="<?= site_url('admin/products/add') ?>" class="<?= ($uri === 'products' && $uri3 === 'add') ? 'active' : '' ?>">Create Product</a></li>
                            <li><a href="<?= site_url('admin/categories') ?>">Categories</a></li>
                            <li><a href="<?= site_url('admin/brands') ?>">Brands</a></li>
                            <li><a href="<?= site_url('admin/inventory') ?>">Inventory</a></li>
                            <li><a href="<?= site_url('admin/variant-units') ?>">Variant Units</a></li>
                        </ul>
                    </li>
                    <li class="<?= $open(['orders']) ?>">
                        <a href="<?= site_url('admin/orders') ?>" class="<?= sk_active($uri, 'orders') ?>">
                            <i data-eva="shopping-cart-outline"></i>
                            <span>Orders</span>
                        </a>
                    </li>
                    <li class="<?= $open(['customers']) ?>">
                        <a href="<?= site_url('admin/customers') ?>" class="<?= sk_active($uri, 'customers') ?>">
                            <i data-eva="people-outline"></i>
                            <span>Customers</span>
                        </a>
                    </li>
                    <li>
                        <a href="<?= site_url('admin/banners') ?>" class="<?= sk_active($uri, 'banners') ?>">
                            <i data-eva="image-outline"></i>
                            <span>Banners</span>
                        </a>
                    </li>
                    <li>
                        <a href="<?= site_url('admin/promo') ?>" class="<?= sk_active($uri, 'promo') ?>">
                            <i data-eva="pricetags-outline"></i>
                            <span>Promo Codes</span>
                        </a>
                    </li>

                    <li class="menu-title">Apps</li>
                    <li class="<?= $open(['whatsapp', 'whatsapp-report', 'whatsapp_requests', 'meta']) ?>">
                        <a href="javascript:void(0);" class="has-arrow">
                            <i data-eva="message-circle-outline"></i>
                            <span>WhatsApp</span>
                        </a>
                        <ul class="sub-menu" aria-expanded="false">
                            <li><a href="<?= site_url('admin/whatsapp') ?>" class="<?= ($uri === 'whatsapp' && $uri3 === '') ? 'active' : '' ?>">Inbox</a></li>
                            <li><a href="<?= site_url('admin/whatsapp/templates') ?>">Templates</a></li>
                            <li><a href="<?= site_url('admin/whatsapp/order-templates') ?>" class="<?= ($uri === 'whatsapp' && $uri3 === 'order-templates') ? 'active' : '' ?>">Order templates</a></li>
                            <li><a href="<?= site_url('admin/whatsapp/campaigns') ?>">Campaigns</a></li>
                            <li><a href="<?= site_url('admin/whatsapp-report') ?>">Delivery report</a></li>
                            <li><a href="<?= site_url('admin/meta/agent') ?>" class="<?= ($uri === 'meta' && $uri3 === 'agent') ? 'active' : '' ?>">Meta Business Agent</a></li>
                            <?php if ($is_super): ?>
                            <li><a href="<?= site_url('admin/whatsapp_requests/pending') ?>">Numbers</a></li>
                            <li><a href="<?= site_url('admin/meta') ?>">Meta connect</a></li>
                            <?php else: ?>
                            <li><a href="<?= site_url('admin/whatsapp_requests') ?>">My numbers</a></li>
                            <?php endif; ?>
                        </ul>
                    </li>
                    <li>
                        <a href="<?= site_url('admin/automation_tasks') ?>" class="<?= sk_active($uri, 'automation_tasks') ?>">
                            <i data-eva="flash-outline"></i>
                            <span>Automation</span>
                        </a>
                    </li>
                    <li>
                        <a href="<?= site_url('admin/notifications') ?>" class="<?= sk_active($uri, 'notifications') ?>">
                            <i data-eva="bell-outline"></i>
                            <span>Notifications</span>
                        </a>
                    </li>
                    <li>
                        <a href="<?= site_url('admin/contacts') ?>" class="<?= sk_active($uri, 'contacts') ?>">
                            <i data-eva="email-outline"></i>
                            <span>Contacts</span>
                        </a>
                    </li>

                    <?php if ($is_vendor): ?>
                    <li class="menu-title">My Store</li>
                    <li>
                        <a href="<?= site_url('admin/stores/edit/'.($vendor_context->vendor_id() ?? '')) ?>" class="<?= sk_active($uri, 'stores') ?>">
                            <i data-eva="home-outline"></i>
                            <span>Store settings</span>
                        </a>
                    </li>
                    <?php if (!empty($vendor_logged_in)): ?>
                    <li>
                        <a href="<?= site_url('admin/vendor/account/password') ?>">
                            <i data-eva="lock-outline"></i>
                            <span>Change Password</span>
                        </a>
                    </li>
                    <?php endif; ?>
                    <?php endif; ?>

                    <?php if ($is_super): ?>
                    <li class="menu-title">Admin</li>
                    <li>
                        <a href="<?= site_url('admin/vendors') ?>" class="<?= sk_active($uri, 'vendors') ?>">
                            <i data-eva="briefcase-outline"></i>
                            <span>Vendors</span>
                        </a>
                    </li>
                    <li>
                        <a href="<?= site_url('admin/reports') ?>" class="<?= sk_active($uri, 'reports') ?>">
                            <i data-eva="bar-chart-outline"></i>
                            <span>Reports</span>
                        </a>
                    </li>
                    <li>
                        <a href="<?= site_url('admin/saas-billing') ?>" class="<?= sk_active($uri, 'saas-billing') ?>">
                            <i data-eva="credit-card-outline"></i>
                            <span>SaaS Billing</span>
                        </a>
                    </li>
                    <li>
                        <a href="<?= site_url('admin/settings') ?>" class="<?= sk_active($uri, 'settings') ?>">
                            <i data-eva="settings-outline"></i>
                            <span>Settings</span>
                        </a>
                    </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </div>

    <div class="main-content">
        <div class="page-content">
            <div class="container-fluid">
                <?php if ($this->session->flashdata('success')): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?= $this->session->flashdata('success') ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>
                <?php if ($this->session->flashdata('error')): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?= $this->session->flashdata('error') ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>
