<?php
$uri = $this->uri->segment(2); // e.g. 'dashboard', 'products'
function sk_active($seg, $match) { return $seg === $match ? 'active' : ''; }
?>
<!-- Sidebar -->
<nav id="sk-sidebar" class="sk-sidebar bg-dark text-white">
  <div class="sk-sidebar-inner pt-3 pb-5">

    <div class="px-3 mb-3">
      <small class="text-uppercase text-white-50 fw-bold" style="font-size:.65rem;letter-spacing:.08em;">Main Menu</small>
    </div>

    <ul class="nav flex-column gap-1 px-2">

      <li class="nav-item">
        <a href="<?= site_url('shopkart/dashboard') ?>"
           class="nav-link sk-nav-link <?= sk_active($uri,'dashboard') ?>">
          <i class="bi bi-speedometer2 me-2"></i> Dashboard
        </a>
      </li>

      <?php if (empty($impersonating) && empty($vendor_logged_in) && ($admin['role'] ?? '') === 'superadmin'): ?>
      <li class="nav-item mt-2">
        <small class="text-uppercase text-white-50 fw-bold px-2" style="font-size:.65rem;letter-spacing:.08em;">Marketplace</small>
      </li>
      <li class="nav-item">
        <a href="<?= site_url('shopkart/vendors') ?>" class="nav-link sk-nav-link <?= sk_active($uri,'vendors') ?>">
          <i class="bi bi-shop-window me-2"></i> Vendors
        </a>
      </li>
      <?php endif; ?>

      <?php if (!empty($impersonating) || !empty($vendor_context) && $vendor_context->vendor_id()): ?>
      <li class="nav-item">
        <a href="<?= site_url('shopkart/stores/edit/'.($vendor_context->vendor_id() ?? '')) ?>" class="nav-link sk-nav-link <?= sk_active($uri,'stores') ?>">
          <i class="bi bi-shop me-2"></i> My Store
        </a>
      </li>
      <?php if (!empty($vendor_logged_in)): ?>
      <li class="nav-item">
        <a href="<?= site_url('admin/vendor/account/password') ?>" class="nav-link sk-nav-link <?= strpos((string) uri_string(), 'vendor/account') !== false ? 'active' : '' ?>">
          <i class="bi bi-shield-lock me-2"></i> Change Password
        </a>
      </li>
      <?php endif; ?>
      <?php endif; ?>

      <li class="nav-item mt-2">
        <small class="text-uppercase text-white-50 fw-bold px-2" style="font-size:.65rem;letter-spacing:.08em;">Catalog</small>
      </li>

      <li class="nav-item">
        <a href="<?= site_url('shopkart/products') ?>"
           class="nav-link sk-nav-link <?= sk_active($uri,'products') ?>">
          <i class="bi bi-box-seam me-2"></i> Products
        </a>
      </li>

      <li class="nav-item">
        <a href="<?= site_url('shopkart/inventory') ?>"
           class="nav-link sk-nav-link <?= sk_active($uri,'inventory') ?>">
          <i class="bi bi-boxes me-2"></i> Inventory
        </a>
      </li>

      <li class="nav-item">
        <a href="<?= site_url('shopkart/categories') ?>"
           class="nav-link sk-nav-link <?= sk_active($uri,'categories') ?>">
          <i class="bi bi-diagram-3 me-2"></i> Categories
        </a>
      </li>

      <li class="nav-item">
        <a href="<?= site_url('shopkart/brands') ?>"
           class="nav-link sk-nav-link <?= sk_active($uri,'brands') ?>">
          <i class="bi bi-shop me-2"></i> Brands
        </a>
      </li>
      <li class="nav-item">
        <a href="<?= site_url('shopkart/variant-units') ?>"
           class="nav-link sk-nav-link <?= $uri==='variant-units'?'active':'' ?>">
          <i class="bi bi-rulers me-2"></i> Variant Units
        </a>
      </li>

      <li class="nav-item">
        <a href="<?= site_url('shopkart/banners') ?>"
           class="nav-link sk-nav-link <?= sk_active($uri,'banners') ?>">
          <i class="bi bi-images me-2"></i> Banners
        </a>
      </li>

      <li class="nav-item">
        <a href="<?= site_url('shopkart/wishlists') ?>"
           class="nav-link sk-nav-link <?= sk_active($uri,'wishlists') ?>">
          <i class="bi bi-heart me-2"></i> Wishlists
        </a>
      </li>

      <li class="nav-item">
        <a href="<?= site_url('shopkart/reviews') ?>"
           class="nav-link sk-nav-link <?= sk_active($uri,'reviews') ?>">
          <i class="bi bi-star-half me-2"></i> Reviews
        </a>
      </li>

      <li class="nav-item">
        <a href="<?= site_url('shopkart/testimonials') ?>"
           class="nav-link sk-nav-link <?= sk_active($uri,'testimonials') ?>">
          <i class="bi bi-chat-quote me-2"></i> Testimonials
        </a>
      </li>

      <li class="nav-item">
        <a href="<?= site_url('shopkart/blogs') ?>"
           class="nav-link sk-nav-link <?= sk_active($uri,'blogs') ?>">
          <i class="bi bi-journal-richtext me-2"></i> Blogs
        </a>
      </li>

      <li class="nav-item">
        <a href="<?= site_url('shopkart/contacts') ?>"
           class="nav-link sk-nav-link <?= sk_active($uri,'contacts') ?>">
          <i class="bi bi-envelope me-2"></i> Contacts
        </a>
      </li>

      <li class="nav-item">
        <a href="<?= site_url('shopkart/orders') ?>"
           class="nav-link sk-nav-link <?= sk_active($uri,'orders') ?>">
          <i class="bi bi-cart-check me-2"></i> Orders
        </a>
      </li>

      <li class="nav-item">
        <a href="<?= site_url('shopkart/customers') ?>"
           class="nav-link sk-nav-link <?= sk_active($uri,'customers') ?>">
          <i class="bi bi-people me-2"></i> Customers
        </a>
      </li>

      <li class="nav-item">
        <a href="<?= site_url('shopkart/promo') ?>"
           class="nav-link sk-nav-link <?= sk_active($uri,'promo') ?>">
          <i class="bi bi-ticket-perforated me-2"></i> Promo Codes
        </a>
      </li>

      <li class="nav-item">
        <a href="<?= site_url('shopkart/reports') ?>"
           class="nav-link sk-nav-link <?= sk_active($uri,'reports') ?>">
          <i class="bi bi-bar-chart-line me-2"></i> Reports
        </a>
      </li>

      <li class="nav-item">
        <a href="<?= site_url('shopkart/coupon-report') ?>"
           class="nav-link sk-nav-link <?= $uri==='coupon-report'?'active':'' ?>">
          <i class="bi bi-ticket-perforated me-2"></i> Coupon Report
        </a>
      </li>

      <?php // Notifications menu hidden — push delivery is on hold. ?>
      <?php if (FALSE): ?>
      <li class="nav-item">
        <a href="<?= site_url('shopkart/notifications') ?>"
           class="nav-link sk-nav-link <?= $uri==='notifications'?'active':'' ?>">
          <i class="bi bi-bell me-2"></i> Notifications
        </a>
      </li>
      <?php endif; ?>

      <li class="nav-item">
        <a href="<?= site_url('shopkart/whatsapp') ?>"
           class="nav-link sk-nav-link <?= ($uri === 'whatsapp' && !$this->uri->segment(3)) ? 'active' : '' ?>">
          <i class="bi bi-whatsapp me-2"></i> WhatsApp Inbox
        </a>
      </li>
      <li class="nav-item">
        <a href="<?= site_url('shopkart/whatsapp/templates') ?>"
           class="nav-link sk-nav-link <?= ($this->uri->segment(3)==='templates') ? 'active' : '' ?>">
          <i class="bi bi-file-earmark-richtext me-2"></i> WA Templates
        </a>
      </li>
      <li class="nav-item">
        <a href="<?= site_url('shopkart/whatsapp/campaigns') ?>"
           class="nav-link sk-nav-link <?= ($this->uri->segment(3)==='campaigns') ? 'active' : '' ?>">
          <i class="bi bi-megaphone me-2"></i> WA Campaigns
        </a>
      </li>
      <li class="nav-item">
        <a href="<?= site_url('shopkart/whatsapp-report') ?>"
           class="nav-link sk-nav-link <?= $uri==='whatsapp-report'?'active':'' ?>">
          <i class="bi bi-clipboard-data me-2"></i> WhatsApp Report
        </a>
      </li>

      <li class="nav-item mt-3">
        <small class="text-uppercase text-white-50 fw-bold px-2" style="font-size:.65rem;letter-spacing:.08em;">System</small>
      </li>

      <li class="nav-item">
        <a href="<?= site_url('shopkart/api-explorer') ?>"
           class="nav-link sk-nav-link <?= $uri==='api-explorer'?'active':'' ?>">
          <i class="bi bi-braces-asterisk me-2"></i> Mobile API Explorer
        </a>
      </li>

      <li class="nav-item">
        <a href="<?= site_url('shopkart/seo') ?>"
           class="nav-link sk-nav-link <?= sk_active($uri,'seo') ?>">
          <i class="bi bi-search me-2"></i> SEO Manager
        </a>
      </li>

      <li class="nav-item">
        <a href="<?= site_url('shopkart/settings') ?>"
           class="nav-link sk-nav-link <?= sk_active($uri,'settings') ?>">
          <i class="bi bi-gear me-2"></i> Settings
        </a>
      </li>

      <?php if (empty($impersonating) && empty($vendor_logged_in) && ($admin['role'] ?? '') === 'superadmin'): ?>
      <li class="nav-item">
        <a href="<?= site_url('shopkart/roles') ?>" class="nav-link sk-nav-link <?= sk_active($uri,'roles') ?>">
          <i class="bi bi-shield-lock me-2"></i> Roles
        </a>
      </li>
      <?php endif; ?>

      <li class="nav-item">
        <a href="<?= site_url(!empty($vendor_logged_in) ? 'admin/vendor/logout' : 'shopkart/logout') ?>" class="nav-link sk-nav-link text-danger">
          <i class="bi bi-box-arrow-left me-2"></i> Logout
        </a>
      </li>

    </ul>
  </div>
</nav>

<!-- Main Content Area -->
<main class="sk-main flex-grow-1 p-4">
