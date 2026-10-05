<?php
$page_title = $page_title ?? '';
$crumbs = $breadcrumb ?? [];
?>
<div class="row">
  <div class="col-12">
    <div class="page-title-box d-flex align-items-center justify-content-between">
      <h4 class="mb-sm-0"><?= htmlspecialchars($page_title) ?></h4>
      <nav aria-label="breadcrumb" class="page-title-right">
        <ol class="breadcrumb border-0 mb-0">
          <li class="breadcrumb-item">
            <a href="<?= site_url('shopkart/dashboard') ?>">
              <i class="mdi mdi-home-outline fs-18 lh-1"></i>
              <span class="visually-hidden">Home</span>
            </a>
          </li>
          <?php foreach ($crumbs as $i => $crumb):
            $label = is_array($crumb) ? ($crumb['label'] ?? '') : $crumb;
            $url = is_array($crumb) ? ($crumb['url'] ?? '') : '';
            $last = $i === count($crumbs) - 1;
          ?>
          <li class="breadcrumb-item<?= $last ? ' active' : '' ?>"<?= $last ? ' aria-current="page"' : '' ?>>
            <?php if (!$last && $url !== ''): ?>
              <a href="<?= $url ?>"><?= htmlspecialchars($label) ?></a>
            <?php else: ?>
              <?= htmlspecialchars($label) ?>
            <?php endif; ?>
          </li>
          <?php endforeach; ?>
        </ol>
      </nav>
    </div>
  </div>
</div>
