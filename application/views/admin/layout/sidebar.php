<?php
$uri = $this->uri->segment(2); // e.g. 'dashboard', 'products'
if (!function_exists('sk_active')) {
  function sk_active($seg, $match) { return $seg === $match ? 'active' : ''; }
}
?>
<!-- Sidebar -->
<aside id="sk-sidebar" class="sk-sidebar bg-dark text-white">
  <div class="sk-sidebar-inner pt-3 pb-5">

    <?php
      $waSide = $sidebar_wa ?? ['sent_today' => 0, 'sent_total' => 0, 'unread' => 0, 'mcp_open' => false, 'mcp_label' => 'Closed'];
    ?>
    <div class="sk-side-status mx-2 mb-3">
      <a href="<?= site_url('shopkart/whatsapp') ?>" class="sk-side-stat">
        <span class="sk-side-stat-label"><i class="bi bi-whatsapp me-1"></i>WhatsApp sent today</span>
        <strong><?= (int)$waSide['sent_today'] ?></strong>
      </a>
      <a href="<?= site_url('admin/settings?tab=wacloud') ?>" class="sk-side-stat">
        <span class="sk-side-stat-label"><i class="bi bi-hdd-network me-1"></i>MCP</span>
        <span class="sk-mcp-pill <?= !empty($waSide['mcp_open']) ? 'is-open' : 'is-closed' ?>">
          <?= htmlspecialchars($waSide['mcp_label'] ?? 'Closed') ?>
        </span>
      </a>
    </div>

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
      <li class="nav-item">
        <a href="<?= site_url('admin/whatsapp_requests') ?>" class="nav-link sk-nav-link <?= sk_active($uri,'whatsapp_requests') ?>">
          <i class="bi bi-whatsapp me-2"></i> Connect WhatsApp
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
          <?php if ((int)($waSide['sent_today'] ?? 0) > 0): ?>
          <span class="badge bg-success ms-auto"><?= (int)$waSide['sent_today'] ?></span>
          <?php endif; ?>
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

      <?php if (empty($impersonating) && empty($vendor_logged_in) && ($admin['role'] ?? '') === 'superadmin'): ?>
      <?php
        // Show pending WA provisioning requests count to super-admins
        $this->load->model('Sk_Wa_Provision_request_model');
        $pending = $this->Sk_Wa_Provision_request_model->get_pending();
        $pending_count = is_array($pending) ? count($pending) : 0;
      ?>
      <li class="nav-item">
        <a href="<?= site_url('admin/whatsapp_requests/pending') ?>"
           class="nav-link sk-nav-link <?= sk_active($uri,'whatsapp_requests') ?>">
          <i class="bi bi-telephone-forward me-2"></i> WA Requests
          <?php if ($pending_count > 0): ?>
            <span class="badge bg-danger ms-auto"><?= (int)$pending_count ?></span>
          <?php endif; ?>
        </a>
      </li>
      <?php endif; ?>

      <li class="nav-item mt-3">
        <small class="text-uppercase text-white-50 fw-bold px-2" style="font-size:.65rem;letter-spacing:.08em;">SaaS</small>
      </li>
      <li class="nav-item">
        <a href="<?= site_url('admin/saas-billing') ?>"
           class="nav-link sk-nav-link <?= ($uri === 'saas-billing' && !$this->uri->segment(3)) ? 'active' : '' ?>">
          <i class="bi bi-receipt me-2"></i> Billing
        </a>
      </li>
      <li class="nav-item">
        <a href="<?= site_url('admin/saas-billing/requests') ?>"
           class="nav-link sk-nav-link <?= ($uri === 'saas-billing' && $this->uri->segment(3) === 'requests') ? 'active' : '' ?>">
          <i class="bi bi-cpu me-2"></i> AI Requests
        </a>
      </li>
      <?php if (empty($impersonating) && empty($vendor_logged_in) && ($admin['role'] ?? '') === 'superadmin'): ?>
      <li class="nav-item">
        <a href="<?= site_url('admin/saas-billing/amounts') ?>"
           class="nav-link sk-nav-link <?= ($uri === 'saas-billing' && $this->uri->segment(3) === 'amounts') ? 'active' : '' ?>">
          <i class="bi bi-tag me-2"></i> Client Amounts
        </a>
      </li>
      <?php endif; ?>

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
</aside>

<!-- Main Content Area -->
<main class="sk-main p-4">
