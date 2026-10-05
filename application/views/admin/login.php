<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title><?= htmlspecialchars($title ?? 'Sign In') ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
  <link rel="shortcut icon" href="<?= base_url('assets/aquiry/images/logo-sm.png') ?>">
  <link href="<?= base_url('assets/aquiry/css/bootstrap.min.css') ?>" id="bootstrap-style" rel="stylesheet" type="text/css">
  <link href="<?= base_url('assets/aquiry/css/icons.min.css') ?>" rel="stylesheet" type="text/css">
  <link href="<?= base_url('assets/aquiry/css/app.min.css') ?>" id="app-style" rel="stylesheet" type="text/css">
</head>
<body>
<?php $img = base_url('assets/aquiry/images/'); ?>
<div class="position-relative min-vh-100">
  <div class="row gx-0">
    <div class="col-xl-5">
      <div class="row justify-content-center align-items-center p-10 min-vh-100 bg-body-secondary position-relative">
        <div class="col-md-7 col-lg-6 col-xl-8 col-xxl-7">
          <a href="<?= site_url('admin/dashboard') ?>" class="text-nowrap d-block w-100 text-decoration-none">
            <span class="fw-bold fs-4 text-body">Talk <span class="text-primary">AI</span> Pilot</span>
          </a>
          <h3 class="mb-3 mt-8">Sign In</h3>
          <p class="text-muted mb-8">Access your admin dashboard to manage the store.</p>
          <?php if ($this->session->flashdata('error')): ?>
            <div class="alert alert-danger"><?= $this->session->flashdata('error') ?></div>
          <?php endif; ?>
          <form action="<?= site_url('admin/login/submit') ?>" method="post">
            <input type="hidden" name="<?= $this->security->get_csrf_token_name(); ?>" value="<?= $this->security->get_csrf_hash(); ?>">
            <div class="mb-5">
              <label for="Email" class="form-label">Email</label>
              <input type="email" class="form-control" id="Email" name="email" placeholder="name@example.com" required autofocus>
            </div>
            <div class="mb-5">
              <label for="Password" class="form-label">Password</label>
              <input type="password" class="form-control" id="Password" name="password" placeholder="Password" required>
            </div>
            <button type="submit" class="btn btn-primary w-100">Sign In</button>
            <div class="mt-5">
              <p class="mb-0">Vendor account? <a href="<?= site_url('admin/vendor/login') ?>" class="fw-medium text-primary">Vendor sign in</a></p>
            </div>
            <div class="text-muted pt-14">
              <p>© <script>document.write(new Date().getFullYear())</script> Talk AI Pilot.</p>
            </div>
          </form>
        </div>
      </div>
    </div>
    <div class="col-xl-7 d-none d-md-block">
      <div class="h-100 d-flex align-items-center overflow-hidden justify-content-center position-relative z-2 hero-section bg-body">
        <div class="floating-card position-absolute card-6">
          <div class="w-72 shadow-lg rounded-3 overflow-hidden">
            <img src="<?= $img ?>auth/img-1.png" alt="" class="img-fluid h-100 w-100 object-fit-cover">
          </div>
        </div>
        <div class="text-center z-index-2 position-relative">
          <p class="display-5 text-body fw-normal mb-6">
            Store admin<br>
            <span class="text-primary display-6 fw-normal">Talk AI Pilot Dashboard</span>
          </p>
          <p class="mb-8 px-4 fs-14 max-w-75 mx-auto">Manage products, orders, and customers from one place.</p>
        </div>
      </div>
    </div>
  </div>
</div>
<script src="<?= base_url('assets/aquiry/libs/bootstrap/js/bootstrap.bundle.min.js') ?>"></script>
</body>
</html>
