<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
  <title><?= htmlspecialchars($name) ?> — <?= htmlspecialchars($shop_name) ?></title>
  <meta name="description" content="<?= htmlspecialchars(substr(strip_tags($description ?: $name), 0, 155)) ?>">

  <!-- Fonts & Icons -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">

  <style>
    :root {
      --primary: #059669;
      --primary-dark: #047857;
      --primary-light: #ecfdf5;
      --primary-ring: rgba(5, 150, 105, 0.25);
      --slate-900: #0f172a;
      --slate-800: #1e293b;
      --slate-700: #334155;
      --slate-600: #475569;
      --slate-500: #64748b;
      --slate-400: #94a3b8;
      --slate-200: #e2e8f0;
      --slate-100: #f1f5f9;
      --slate-50:  #f8fafc;
      --wa-green:  #25D366;
      --wa-dark:   #128C7E;
      --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
      --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -2px rgba(0, 0, 0, 0.1);
      --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -4px rgba(0, 0, 0, 0.1);
      --shadow-xl: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 8px 10px -6px rgba(0, 0, 0, 0.1);
      --radius-sm: 8px;
      --radius-md: 12px;
      --radius-lg: 18px;
      --radius-full: 9999px;
    }

    * { box-sizing: border-box; margin: 0; padding: 0; }

    body {
      font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
      background: #f8fafc;
      color: var(--slate-900);
      min-height: 100vh;
      -webkit-font-smoothing: antialiased;
      line-height: 1.5;
    }

    /* Top Navigation */
    .top-navbar {
      background: #ffffff;
      border-bottom: 1px solid var(--slate-200);
      position: sticky;
      top: 0;
      z-index: 40;
      box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
    }
    .nav-container {
      max-width: 1100px;
      margin: 0 auto;
      padding: 14px 20px;
      display: flex;
      align-items: center;
      justify-content: space-between;
    }
    .brand-group {
      display: flex;
      align-items: center;
      gap: 10px;
      text-decoration: none;
      color: inherit;
    }
    .brand-icon {
      width: 36px;
      height: 36px;
      border-radius: var(--radius-sm);
      background: linear-gradient(135deg, #059669, #10b981);
      display: flex;
      align-items: center;
      justify-content: center;
      color: #fff;
      font-size: 18px;
      box-shadow: var(--shadow-sm);
    }
    .brand-title {
      font-weight: 700;
      font-size: 17px;
      color: var(--slate-900);
      letter-spacing: -0.01em;
    }
    .nav-actions {
      display: flex;
      align-items: center;
      gap: 12px;
    }
    .cart-btn {
      display: flex;
      align-items: center;
      gap: 8px;
      background: var(--slate-50);
      border: 1px solid var(--slate-200);
      padding: 8px 14px;
      border-radius: var(--radius-full);
      font-size: 14px;
      font-weight: 600;
      color: var(--slate-700);
      cursor: pointer;
      transition: all 0.2s ease;
    }
    .cart-btn:hover {
      background: #fff;
      border-color: var(--primary);
      color: var(--primary);
      box-shadow: var(--shadow-sm);
    }
    .cart-badge {
      background: var(--primary);
      color: #fff;
      font-size: 11px;
      font-weight: 700;
      padding: 2px 7px;
      border-radius: var(--radius-full);
      min-width: 18px;
      text-align: center;
      transition: transform 0.2s cubic-bezier(0.34, 1.56, 0.64, 1);
    }
    .cart-badge.bounce {
      transform: scale(1.3);
    }

    /* Main Container */
    .page-wrap {
      max-width: 1100px;
      margin: 28px auto 60px;
      padding: 0 20px;
    }

    /* Product Card Grid */
    .product-grid {
      background: #ffffff;
      border-radius: var(--radius-lg);
      border: 1px solid var(--slate-200);
      box-shadow: var(--shadow-sm);
      overflow: hidden;
      display: grid;
      grid-template-columns: 1fr;
    }
    @media (min-width: 820px) {
      .product-grid {
        grid-template-columns: 1fr 1.15fr;
      }
    }

    /* Media Left Column */
    .media-col {
      padding: 28px;
      background: #fafbfc;
      display: flex;
      flex-direction: column;
      border-bottom: 1px solid var(--slate-200);
    }
    @media (min-width: 820px) {
      .media-col {
        border-bottom: none;
        border-right: 1px solid var(--slate-200);
        padding: 36px;
      }
    }
    .hero-photo-wrap {
      position: relative;
      width: 100%;
      padding-top: 100%; /* 1:1 Aspect ratio */
      border-radius: var(--radius-md);
      overflow: hidden;
      background: #ffffff;
      border: 1px solid var(--slate-200);
      box-shadow: var(--shadow-sm);
    }
    .hero-photo {
      position: absolute;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      object-fit: contain;
      padding: 16px;
      transition: transform 0.3s ease;
    }
    .hero-photo:hover {
      transform: scale(1.03);
    }

    .badge-discount {
      position: absolute;
      top: 14px;
      left: 14px;
      background: #ef4444;
      color: #fff;
      font-size: 12px;
      font-weight: 700;
      padding: 4px 10px;
      border-radius: var(--radius-full);
      box-shadow: 0 2px 4px rgba(239, 68, 68, 0.3);
      letter-spacing: 0.02em;
      text-transform: uppercase;
    }
    .badge-stock {
      position: absolute;
      bottom: 14px;
      left: 14px;
      background: rgba(255, 255, 255, 0.95);
      backdrop-filter: blur(4px);
      color: var(--primary-dark);
      border: 1px solid #d1fae5;
      font-size: 12px;
      font-weight: 700;
      padding: 4px 10px;
      border-radius: var(--radius-full);
      display: inline-flex;
      align-items: center;
      gap: 5px;
      box-shadow: var(--shadow-sm);
    }
    .stock-dot {
      width: 7px;
      height: 7px;
      border-radius: 50%;
      background: var(--primary);
      display: inline-block;
    }

    /* Thumbnail Gallery */
    .gallery-row {
      display: flex;
      gap: 10px;
      margin-top: 18px;
      overflow-x: auto;
      padding-bottom: 6px;
      scrollbar-width: thin;
    }
    .gallery-thumb {
      width: 64px;
      height: 64px;
      border-radius: var(--radius-sm);
      border: 2px solid var(--slate-200);
      background: #fff;
      padding: 4px;
      cursor: pointer;
      flex-shrink: 0;
      transition: all 0.2s ease;
      object-fit: contain;
    }
    .gallery-thumb:hover {
      border-color: var(--slate-400);
    }
    .gallery-thumb.active {
      border-color: var(--primary);
      box-shadow: 0 0 0 2px var(--primary-ring);
    }

    /* Details Right Column */
    .details-col {
      padding: 28px;
      display: flex;
      flex-direction: column;
      justify-content: space-between;
    }
    @media (min-width: 820px) {
      .details-col {
        padding: 36px 40px;
      }
    }

    .category-tag {
      font-size: 12px;
      font-weight: 700;
      color: var(--primary);
      text-transform: uppercase;
      letter-spacing: 0.08em;
      margin-bottom: 8px;
    }
    .product-title {
      font-size: 26px;
      font-weight: 800;
      color: var(--slate-900);
      line-height: 1.25;
      margin-bottom: 14px;
      letter-spacing: -0.02em;
    }

    /* Price Section */
    .price-box {
      display: flex;
      align-items: baseline;
      gap: 12px;
      padding-bottom: 18px;
      border-bottom: 1px solid var(--slate-200);
      margin-bottom: 22px;
    }
    .price-current {
      font-size: 32px;
      font-weight: 800;
      color: var(--primary);
      letter-spacing: -0.02em;
    }
    .price-mrp {
      font-size: 18px;
      color: var(--slate-400);
      text-decoration: line-through;
      font-weight: 500;
    }
    .price-save {
      background: #dcfce7;
      color: var(--primary-dark);
      font-size: 13px;
      font-weight: 700;
      padding: 3px 9px;
      border-radius: var(--radius-sm);
    }

    /* Section Headings */
    .section-label {
      font-size: 14px;
      font-weight: 700;
      color: var(--slate-800);
      margin-bottom: 10px;
      display: flex;
      align-items: center;
      justify-content: space-between;
    }
    .section-label-val {
      font-weight: 600;
      color: var(--primary);
      font-size: 13px;
    }

    /* Variant Pills Container */
    .variant-group {
      margin-bottom: 22px;
    }
    .pill-list {
      display: flex;
      flex-wrap: wrap;
      gap: 10px;
    }
    .size-pill {
      min-width: 52px;
      height: 44px;
      padding: 0 16px;
      border-radius: var(--radius-sm);
      border: 2px solid var(--slate-200);
      background: #ffffff;
      color: var(--slate-800);
      font-size: 15px;
      font-weight: 600;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      cursor: pointer;
      transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
      user-select: none;
      position: relative;
    }
    .size-pill:hover {
      border-color: var(--slate-400);
      background: var(--slate-50);
    }
    .size-pill.active {
      border-color: var(--primary);
      background: var(--primary-light);
      color: var(--primary-dark);
      font-weight: 800;
      box-shadow: 0 0 0 1px var(--primary), var(--shadow-sm);
      transform: translateY(-1px);
    }
    .size-pill.active::after {
      content: '✓';
      position: absolute;
      top: -6px;
      right: -6px;
      background: var(--primary);
      color: #fff;
      font-size: 10px;
      font-weight: 800;
      width: 16px;
      height: 16px;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      box-shadow: 0 2px 4px rgba(5, 150, 105, 0.4);
    }
    .size-pill.out-of-stock {
      opacity: 0.45;
      text-decoration: line-through;
      cursor: not-allowed;
      border-style: dashed;
    }

    /* Quantity Stepper */
    .stepper-group {
      margin-bottom: 26px;
    }
    .stepper-wrap {
      display: inline-flex;
      align-items: center;
      border: 2px solid var(--slate-200);
      border-radius: var(--radius-sm);
      background: #ffffff;
      overflow: hidden;
      box-shadow: var(--shadow-sm);
    }
    .stepper-btn {
      width: 44px;
      height: 44px;
      border: none;
      background: transparent;
      color: var(--slate-700);
      font-size: 20px;
      font-weight: 600;
      cursor: pointer;
      display: flex;
      align-items: center;
      justify-content: center;
      transition: background 0.15s ease;
      user-select: none;
    }
    .stepper-btn:hover:not(:disabled) {
      background: var(--slate-100);
      color: var(--slate-900);
    }
    .stepper-btn:disabled {
      color: var(--slate-300);
      cursor: not-allowed;
    }
    .stepper-val {
      width: 52px;
      height: 44px;
      border: none;
      border-left: 1px solid var(--slate-200);
      border-right: 1px solid var(--slate-200);
      text-align: center;
      font-size: 16px;
      font-weight: 700;
      color: var(--slate-900);
      background: #fafbfc;
      outline: none;
    }

    /* Action Buttons */
    .actions-wrap {
      display: flex;
      flex-direction: column;
      gap: 12px;
      margin-top: 10px;
    }
    .btn-add-cart {
      width: 100%;
      height: 52px;
      border: none;
      border-radius: var(--radius-md);
      background: linear-gradient(135deg, #059669 0%, #047857 100%);
      color: #ffffff;
      font-size: 16px;
      font-weight: 700;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 10px;
      cursor: pointer;
      box-shadow: 0 4px 14px rgba(5, 150, 105, 0.35);
      transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
    }
    .btn-add-cart:hover {
      background: linear-gradient(135deg, #10b981 0%, #059669 100%);
      transform: translateY(-2px);
      box-shadow: 0 6px 20px rgba(5, 150, 105, 0.45);
    }
    .btn-add-cart:active {
      transform: translateY(0);
    }
    .btn-add-cart.loading {
      opacity: 0.85;
      pointer-events: none;
    }
    .btn-add-cart.success {
      background: #10b981;
    }

    .btn-whatsapp {
      width: 100%;
      height: 50px;
      border: 1px solid #bbf7d0;
      border-radius: var(--radius-md);
      background: #f0fdf4;
      color: var(--wa-dark);
      font-size: 15px;
      font-weight: 700;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 10px;
      text-decoration: none;
      cursor: pointer;
      transition: all 0.2s ease;
    }
    .btn-whatsapp:hover {
      background: #dcfce7;
      color: #064e3b;
      transform: translateY(-1px);
    }

    /* Trust Highlights */
    .trust-row {
      display: grid;
      grid-template-columns: repeat(3, 1fr);
      gap: 10px;
      margin-top: 26px;
      padding-top: 20px;
      border-top: 1px solid var(--slate-200);
      text-align: center;
    }
    .trust-item {
      font-size: 12px;
      font-weight: 600;
      color: var(--slate-600);
      display: flex;
      flex-direction: column;
      align-items: center;
      gap: 4px;
    }
    .trust-icon {
      font-size: 18px;
    }

    /* Description Tab / Accordion */
    .desc-box {
      margin-top: 24px;
      padding: 18px 20px;
      background: var(--slate-50);
      border-radius: var(--radius-md);
      border: 1px solid var(--slate-200);
    }
    .desc-title {
      font-size: 14px;
      font-weight: 700;
      color: var(--slate-800);
      margin-bottom: 8px;
    }
    .desc-content {
      font-size: 14px;
      color: var(--slate-600);
      line-height: 1.6;
    }

    /* Slide-over Cart Drawer */
    .cart-drawer-backdrop {
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background: rgba(15, 23, 42, 0.6);
      backdrop-filter: blur(4px);
      z-index: 90;
      opacity: 0;
      visibility: hidden;
      transition: all 0.3s ease;
    }
    .cart-drawer-backdrop.open {
      opacity: 1;
      visibility: visible;
    }
    .cart-drawer {
      position: fixed;
      top: 0;
      right: 0;
      width: 100%;
      max-width: 420px;
      height: 100%;
      background: #ffffff;
      z-index: 100;
      box-shadow: -10px 0 25px rgba(0, 0, 0, 0.15);
      transform: translateX(100%);
      transition: transform 0.3s cubic-bezier(0.16, 1, 0.3, 1);
      display: flex;
      flex-direction: column;
    }
    .cart-drawer.open {
      transform: translateX(0);
    }
    .drawer-header {
      padding: 20px;
      border-bottom: 1px solid var(--slate-200);
      display: flex;
      align-items: center;
      justify-content: space-between;
    }
    .drawer-title {
      font-size: 18px;
      font-weight: 800;
      color: var(--slate-900);
      display: flex;
      align-items: center;
      gap: 8px;
    }
    .drawer-close {
      width: 32px;
      height: 32px;
      border-radius: 50%;
      border: none;
      background: var(--slate-100);
      color: var(--slate-700);
      font-size: 16px;
      cursor: pointer;
      display: flex;
      align-items: center;
      justify-content: center;
      transition: background 0.15s;
    }
    .drawer-close:hover {
      background: var(--slate-200);
    }
    .drawer-body {
      flex: 1;
      overflow-y: auto;
      padding: 20px;
    }
    .drawer-empty {
      text-align: center;
      padding: 40px 20px;
      color: var(--slate-500);
    }
    .drawer-item {
      display: flex;
      gap: 14px;
      padding: 14px 0;
      border-bottom: 1px solid var(--slate-100);
    }
    .drawer-item-img {
      width: 64px;
      height: 64px;
      border-radius: var(--radius-sm);
      object-fit: cover;
      background: var(--slate-100);
      border: 1px solid var(--slate-200);
    }
    .drawer-item-info {
      flex: 1;
    }
    .drawer-item-title {
      font-size: 14px;
      font-weight: 700;
      color: var(--slate-900);
      line-height: 1.3;
      margin-bottom: 4px;
    }
    .drawer-item-meta {
      font-size: 12px;
      color: var(--slate-500);
      margin-bottom: 6px;
    }
    .drawer-item-price {
      font-size: 14px;
      font-weight: 700;
      color: var(--primary);
    }
    .drawer-footer {
      padding: 20px;
      border-top: 1px solid var(--slate-200);
      background: var(--slate-50);
    }
    .drawer-subtotal {
      display: flex;
      justify-content: space-between;
      align-items: baseline;
      margin-bottom: 14px;
    }
    .drawer-subtotal-label {
      font-size: 15px;
      font-weight: 600;
      color: var(--slate-700);
    }
    .drawer-subtotal-val {
      font-size: 22px;
      font-weight: 800;
      color: var(--slate-900);
    }
    .btn-checkout {
      width: 100%;
      height: 48px;
      border-radius: var(--radius-md);
      background: var(--primary);
      color: #fff;
      font-size: 15px;
      font-weight: 700;
      border: none;
      cursor: pointer;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 8px;
      box-shadow: 0 4px 12px rgba(5, 150, 105, 0.3);
      text-decoration: none;
    }
    .btn-checkout:hover {
      background: var(--primary-dark);
    }

    /* Floating Toast */
    .toast-notify {
      position: fixed;
      top: 20px;
      right: 20px;
      background: #ffffff;
      border-left: 4px solid var(--primary);
      border-radius: var(--radius-sm);
      box-shadow: var(--shadow-xl);
      padding: 14px 18px;
      display: flex;
      align-items: center;
      gap: 12px;
      z-index: 110;
      transform: translateY(-80px);
      opacity: 0;
      transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
      max-width: 380px;
    }
    .toast-notify.show {
      transform: translateY(0);
      opacity: 1;
    }
    .toast-icon {
      font-size: 20px;
      color: var(--primary);
    }
    .toast-title {
      font-size: 14px;
      font-weight: 700;
      color: var(--slate-900);
    }
    .toast-desc {
      font-size: 12px;
      color: var(--slate-500);
    }
  </style>
</head>
<body>

  <!-- Top Store Navbar -->
  <header class="top-navbar">
    <div class="nav-container">
      <a href="<?= site_url() ?>" class="brand-group">
        <div class="brand-icon">🛍️</div>
        <div>
          <div class="brand-title"><?= htmlspecialchars($shop_name) ?></div>
        </div>
      </a>
      <div class="nav-actions">
        <button type="button" class="cart-btn" id="open-cart-btn" onclick="toggleCartDrawer(true)">
          <span>🛒 Cart</span>
          <span class="cart-badge" id="cart-badge">0</span>
        </button>
      </div>
    </div>
  </header>

  <!-- Product Container -->
  <main class="page-wrap">
    <article class="product-grid" id="product-card-root">
      
      <!-- Left Media Column -->
      <section class="media-col">
        <div class="hero-photo-wrap">
          <?php if ($mrp > $price): ?>
            <?php $pct = round((($mrp - $price) / $mrp) * 100); ?>
            <div class="badge-discount" id="badge-discount"><?= $pct ?>% OFF</div>
          <?php endif; ?>
          <div class="badge-stock">
            <span class="stock-dot"></span>
            <span id="stock-text"><?= $in_stock ? 'In Stock' : 'Out of Stock' ?></span>
          </div>
          <img src="<?= htmlspecialchars($image ?: base_url('assets/images/placeholder.png')) ?>" 
               alt="<?= htmlspecialchars($name) ?>" 
               id="main-product-photo" 
               class="hero-photo">
        </div>

        <?php if (count($gallery) > 1): ?>
          <div class="gallery-row" id="gallery-row">
            <?php foreach ($gallery as $idx => $gUrl): ?>
              <img src="<?= htmlspecialchars($gUrl) ?>" 
                   alt="Angle <?= $idx + 1 ?>" 
                   class="gallery-thumb <?= $idx === 0 ? 'active' : '' ?>" 
                   onclick="selectThumb(this, '<?= htmlspecialchars($gUrl, ENT_QUOTES) ?>')">
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </section>

      <!-- Right Details & Actions Column -->
      <section class="details-col">
        <div>
          <?php if ($category !== ''): ?>
            <div class="category-tag"><?= htmlspecialchars($category) ?></div>
          <?php endif; ?>
          
          <h1 class="product-title"><?= htmlspecialchars($name) ?></h1>

          <!-- Price Display -->
          <div class="price-box">
            <div class="price-current" id="display-price">₹<?= number_format($price, 0) ?></div>
            <?php if ($mrp > $price): ?>
              <div class="price-mrp" id="display-mrp">₹<?= number_format($mrp, 0) ?></div>
              <div class="price-save" id="display-save">Save ₹<?= number_format($mrp - $price, 0) ?></div>
            <?php endif; ?>
          </div>

          <!-- Variant Size Pills -->
          <?php if (!empty($variants)): ?>
            <div class="variant-group">
              <div class="section-label">
                <span>Select Size:</span>
                <span class="section-label-val" id="selected-size-label">M</span>
              </div>
              <div class="pill-list" id="size-pill-list">
                <?php 
                  $firstSelected = null;
                  foreach ($variants as $idx => $v): 
                    $isSelected = !empty($v['is_default']) || ($firstSelected === null && $idx === 0);
                    if ($isSelected && $firstSelected === null) {
                      $firstSelected = $v;
                    }
                ?>
                  <button type="button" 
                          class="size-pill <?= $isSelected ? 'active' : '' ?> <?= (int)$v['stock'] <= 0 ? 'out-of-stock' : '' ?>"
                          data-variant-id="<?= (int)$v['id'] ?>"
                          data-variant-sku="<?= htmlspecialchars($v['sku'] ?: $sku) ?>"
                          data-variant-label="<?= htmlspecialchars($v['label']) ?>"
                          data-variant-price="<?= (float)$v['price'] ?>"
                          data-variant-mrp="<?= (float)$v['mrp'] ?>"
                          data-variant-stock="<?= (int)$v['stock'] ?>"
                          onclick="selectVariant(this)">
                    <?= htmlspecialchars($v['label']) ?>
                  </button>
                <?php endforeach; ?>
              </div>
            </div>
          <?php endif; ?>

          <!-- Quantity Stepper -->
          <div class="stepper-group">
            <div class="section-label">Quantity:</div>
            <div class="stepper-wrap">
              <button type="button" class="stepper-btn" id="btn-qty-minus" onclick="adjustQty(-1)" disabled>−</button>
              <input type="text" id="qty-input" class="stepper-val" value="1" readonly>
              <button type="button" class="stepper-btn" id="btn-qty-plus" onclick="adjustQty(1)">+</button>
            </div>
          </div>
        </div>

        <!-- Action Buttons -->
        <div>
          <div class="actions-wrap">
            <button type="button" class="btn-add-cart" id="btn-add-to-cart" onclick="addToCart()">
              <span id="btn-cart-icon">🛒</span>
              <span id="btn-cart-text">Add to Cart</span>
              <span id="btn-cart-total" style="opacity: 0.85; font-size: 14px;">• ₹<?= number_format($price, 0) ?></span>
            </button>

            <?php if ($wa_phone !== ''): ?>
              <a href="#" target="_blank" class="btn-whatsapp" id="btn-order-wa" onclick="orderViaWhatsApp(event)">
                <span>💬</span>
                <span>Order on WhatsApp</span>
              </a>
            <?php endif; ?>
          </div>

          <!-- Trust Badges -->
          <div class="trust-row">
            <div class="trust-item">
              <span class="trust-icon">🚚</span>
              <span>Fast Shipping</span>
            </div>
            <div class="trust-item">
              <span class="trust-icon">🔄</span>
              <span>Easy Return</span>
            </div>
            <div class="trust-item">
              <span class="trust-icon">🔒</span>
              <span>Secure Pay</span>
            </div>
          </div>

          <!-- Product Description -->
          <?php if ($description !== '' || $specs !== ''): ?>
            <div class="desc-box">
              <div class="desc-title">Product Details</div>
              <div class="desc-content">
                <?= nl2br(htmlspecialchars($description)) ?>
                <?php if ($specs !== ''): ?>
                  <div style="margin-top: 8px; font-weight: 500;">
                    <?= nl2br(htmlspecialchars($specs)) ?>
                  </div>
                <?php endif; ?>
              </div>
            </div>
          <?php endif; ?>
        </div>

      </section>
    </article>
  </main>

  <!-- Slide-over Mini-Cart Drawer -->
  <div class="cart-drawer-backdrop" id="cart-backdrop" onclick="toggleCartDrawer(false)"></div>
  <aside class="cart-drawer" id="cart-drawer">
    <div class="drawer-header">
      <div class="drawer-title">
        <span>🛒</span>
        <span>Your Shopping Bag</span>
      </div>
      <button type="button" class="drawer-close" onclick="toggleCartDrawer(false)">✕</button>
    </div>
    <div class="drawer-body" id="drawer-items">
      <div class="drawer-empty" id="drawer-empty-state">
        <p>Your bag is empty.</p>
      </div>
    </div>
    <div class="drawer-footer">
      <div class="drawer-subtotal">
        <span class="drawer-subtotal-label">Subtotal</span>
        <span class="drawer-subtotal-val" id="drawer-subtotal-val">₹0</span>
      </div>
      <button type="button" class="btn-checkout" onclick="proceedToCheckout()">
        <span>Proceed to Checkout</span>
        <span>→</span>
      </button>
    </div>
  </aside>

  <!-- Toast Alert -->
  <div class="toast-notify" id="toast-notify">
    <div class="toast-icon">✓</div>
    <div>
      <div class="toast-title" id="toast-title">Added to Bag</div>
      <div class="toast-desc" id="toast-desc">Item successfully added to your cart.</div>
    </div>
  </div>

  <script>
    // Product State
    const productData = {
      id: <?= (int)$id ?>,
      name: <?= json_encode($name) ?>,
      basePrice: <?= (float)$price ?>,
      baseMrp: <?= (float)$mrp ?>,
      baseStock: <?= (int)$stock ?>,
      baseSku: <?= json_encode($sku) ?>,
      image: <?= json_encode($image) ?>,
      waPhone: <?= json_encode($wa_phone) ?>,
      shopName: <?= json_encode($shop_name) ?>,
      apiUrl: <?= json_encode(site_url('shopkart-api/cart/add')) ?>,
      getCartUrl: <?= json_encode(site_url('shopkart-api/cart')) ?>
    };

    let selectedVariant = {
      id: 0,
      label: 'M',
      sku: productData.baseSku,
      price: productData.basePrice,
      mrp: productData.baseMrp,
      stock: productData.baseStock
    };

    let currentQty = 1;
    let localCart = [];

    // Initialize variant from default pill
    document.addEventListener('DOMContentLoaded', () => {
      const activePill = document.querySelector('.size-pill.active');
      if (activePill) {
        setVariantFromPill(activePill);
      }
      refreshCartFromStorage();
    });

    // Gallery thumbnail click
    function selectThumb(thumbEl, imgUrl) {
      document.querySelectorAll('.gallery-thumb').forEach(el => el.classList.remove('active'));
      thumbEl.classList.add('active');
      const mainImg = document.getElementById('main-product-photo');
      if (mainImg) {
        mainImg.src = imgUrl;
      }
    }

    // Size / Variant pill click
    function selectVariant(pillEl) {
      if (pillEl.classList.contains('out-of-stock')) return;
      document.querySelectorAll('.size-pill').forEach(el => el.classList.remove('active'));
      pillEl.classList.add('active');
      setVariantFromPill(pillEl);
    }

    function setVariantFromPill(pillEl) {
      const vid = parseInt(pillEl.getAttribute('data-variant-id') || '0', 10);
      const vlabel = pillEl.getAttribute('data-variant-label') || '';
      const vsku = pillEl.getAttribute('data-variant-sku') || productData.baseSku;
      const vprice = parseFloat(pillEl.getAttribute('data-variant-price') || productData.basePrice);
      const vmrp = parseFloat(pillEl.getAttribute('data-variant-mrp') || productData.baseMrp);
      const vstock = parseInt(pillEl.getAttribute('data-variant-stock') || productData.baseStock, 10);

      selectedVariant = {
        id: vid,
        label: vlabel,
        sku: vsku,
        price: vprice,
        mrp: vmrp,
        stock: vstock
      };

      // Update UI elements
      const sizeLabel = document.getElementById('selected-size-label');
      if (sizeLabel) sizeLabel.textContent = vlabel;

      const priceEl = document.getElementById('display-price');
      if (priceEl) priceEl.textContent = '₹' + Math.round(vprice).toLocaleString('en-IN');

      const mrpEl = document.getElementById('display-mrp');
      const saveEl = document.getElementById('display-save');
      const discountBadge = document.getElementById('badge-discount');

      if (vmrp > vprice) {
        if (mrpEl) {
          mrpEl.style.display = 'block';
          mrpEl.textContent = '₹' + Math.round(vmrp).toLocaleString('en-IN');
        }
        if (saveEl) {
          saveEl.style.display = 'inline-block';
          saveEl.textContent = 'Save ₹' + Math.round(vmrp - vprice).toLocaleString('en-IN');
        }
        if (discountBadge) {
          discountBadge.style.display = 'block';
          discountBadge.textContent = Math.round(((vmrp - vprice) / vmrp) * 100) + '% OFF';
        }
      } else {
        if (mrpEl) mrpEl.style.display = 'none';
        if (saveEl) saveEl.style.display = 'none';
        if (discountBadge) discountBadge.style.display = 'none';
      }

      updateStepperUI();
    }

    // Quantity Stepper
    function adjustQty(delta) {
      const maxStock = Math.max(1, selectedVariant.stock || productData.baseStock || 99);
      currentQty = Math.max(1, Math.min(maxStock, currentQty + delta));
      updateStepperUI();
    }

    function updateStepperUI() {
      const maxStock = Math.max(1, selectedVariant.stock || productData.baseStock || 99);
      const qtyInput = document.getElementById('qty-input');
      const minusBtn = document.getElementById('btn-qty-minus');
      const plusBtn = document.getElementById('btn-qty-plus');
      const btnTotal = document.getElementById('btn-cart-total');

      if (qtyInput) qtyInput.value = currentQty;
      if (minusBtn) minusBtn.disabled = currentQty <= 1;
      if (plusBtn) plusBtn.disabled = currentQty >= maxStock;

      const totalVal = Math.round(selectedVariant.price * currentQty);
      if (btnTotal) btnTotal.textContent = '• ₹' + totalVal.toLocaleString('en-IN');
    }

    // Add to Cart Action
    async function addToCart() {
      const btn = document.getElementById('btn-add-to-cart');
      const btnText = document.getElementById('btn-cart-text');
      const btnIcon = document.getElementById('btn-cart-icon');

      btn.classList.add('loading');
      btnText.textContent = 'Adding...';

      const payload = {
        product_id: productData.id,
        variant_id: selectedVariant.id > 0 ? selectedVariant.id : null,
        sku: selectedVariant.sku,
        quantity: currentQty
      };

      try {
        const res = await fetch(productData.apiUrl, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify(payload)
        });
        const data = await res.json();
      } catch (err) {
        // Fallback or network error: still maintain local state
        console.warn('API sync error, adding to local cart:', err);
      }

      // Add to local state
      localCart.push({
        id: productData.id,
        name: productData.name,
        size: selectedVariant.label,
        price: selectedVariant.price,
        qty: currentQty,
        image: productData.image
      });
      saveLocalCart();

      // UI Success feedback
      btn.classList.remove('loading');
      btn.classList.add('success');
      btnIcon.textContent = '✓';
      btnText.textContent = 'Added to Cart!';

      showToast(`Added ${currentQty} × ${productData.name} (${selectedVariant.label}) to cart.`);
      animateCartBadge();
      updateDrawerUI();

      setTimeout(() => {
        btn.classList.remove('success');
        btnIcon.textContent = '🛒';
        btnText.textContent = 'Add to Cart';
        updateStepperUI();
        toggleCartDrawer(true);
      }, 700);
    }

    // WhatsApp Order Action
    function orderViaWhatsApp(e) {
      e.preventDefault();
      if (!productData.waPhone) return;

      const total = Math.round(selectedVariant.price * currentQty);
      const text = `Hi! I would like to order:
• Product: ${productData.name}
• Size: ${selectedVariant.label}
• Quantity: ${currentQty}
• Total: ₹${total.toLocaleString('en-IN')}

Please share payment and delivery details.`;

      const cleanPhone = productData.waPhone.replace(/\D/g, '');
      const url = `https://wa.me/${cleanPhone}?text=${encodeURIComponent(text)}`;
      window.open(url, '_blank');
    }

    // Cart Drawer Controls
    function toggleCartDrawer(open) {
      const drawer = document.getElementById('cart-drawer');
      const backdrop = document.getElementById('cart-backdrop');
      if (open) {
        drawer.classList.add('open');
        backdrop.classList.add('open');
      } else {
        drawer.classList.remove('open');
        backdrop.classList.remove('open');
      }
    }

    function updateDrawerUI() {
      const container = document.getElementById('drawer-items');
      const emptyState = document.getElementById('drawer-empty-state');
      const subtotalEl = document.getElementById('drawer-subtotal-val');
      const badge = document.getElementById('cart-badge');

      let totalItems = 0;
      let subtotal = 0;

      container.innerHTML = '';

      if (localCart.length === 0) {
        container.innerHTML = '<div class="drawer-empty"><p>Your bag is empty.</p></div>';
        if (subtotalEl) subtotalEl.textContent = '₹0';
        if (badge) badge.textContent = '0';
        return;
      }

      localCart.forEach((item, idx) => {
        totalItems += item.qty;
        subtotal += (item.price * item.qty);

        const itemEl = document.createElement('div');
        itemEl.className = 'drawer-item';
        itemEl.innerHTML = `
          <img src="${item.image || ''}" class="drawer-item-img" alt="">
          <div class="drawer-item-info">
            <div class="drawer-item-title">${escapeHtml(item.name)}</div>
            <div class="drawer-item-meta">Size: ${escapeHtml(item.size)} • Qty: ${item.qty}</div>
            <div class="drawer-item-price">₹${Math.round(item.price * item.qty).toLocaleString('en-IN')}</div>
          </div>
        `;
        container.appendChild(itemEl);
      });

      if (subtotalEl) subtotalEl.textContent = '₹' + Math.round(subtotal).toLocaleString('en-IN');
      if (badge) badge.textContent = totalItems;
    }

    function animateCartBadge() {
      const badge = document.getElementById('cart-badge');
      if (badge) {
        badge.classList.add('bounce');
        setTimeout(() => badge.classList.remove('bounce'), 300);
      }
    }

    function showToast(msg) {
      const toast = document.getElementById('toast-notify');
      const desc = document.getElementById('toast-desc');
      if (desc) desc.textContent = msg;
      if (toast) {
        toast.classList.add('show');
        setTimeout(() => toast.classList.remove('show'), 3500);
      }
    }

    function proceedToCheckout() {
      if (productData.waPhone) {
        let lines = localCart.map(i => `• ${i.name} (Size: ${i.size}) × ${i.qty} = ₹${Math.round(i.price * i.qty).toLocaleString('en-IN')}`);
        let total = localCart.reduce((acc, i) => acc + (i.price * i.qty), 0);
        let msg = `Hi! I would like to checkout my cart:\n${lines.join('\n')}\n\n*Total: ₹${Math.round(total).toLocaleString('en-IN')}*\nPlease confirm my order.`;
        window.open(`https://wa.me/${productData.waPhone.replace(/\D/g, '')}?text=${encodeURIComponent(msg)}`, '_blank');
      } else {
        alert('Cart confirmed! Please contact the seller to finalize payment.');
      }
    }

    function saveLocalCart() {
      try {
        localStorage.setItem('talkai_cart', JSON.stringify(localCart));
      } catch (e) {}
    }

    function refreshCartFromStorage() {
      try {
        const stored = localStorage.getItem('talkai_cart');
        if (stored) {
          localCart = JSON.parse(stored);
          updateDrawerUI();
        }
      } catch (e) {}
    }

    function escapeHtml(text) {
      const div = document.createElement('div');
      div.textContent = text;
      return div.innerHTML;
    }
  </script>
</body>
</html>
