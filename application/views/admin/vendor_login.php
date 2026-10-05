<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Vendor Login - Talk AI Pilot</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <link rel="stylesheet" href="<?= base_url('assets/admin/css/admin.css') ?>">
</head>
<body>

<div class="sk-login-wrapper">
  <div class="sk-login-card card p-4 p-md-5">
    <div class="text-center mb-4">
      <div class="mb-2">
        <i class="bi bi-shop-window text-primary" style="font-size:2.5rem;"></i>
      </div>
      <h4 class="fw-bold mb-0">Vendor Login</h4>
      <p class="text-muted small">Sign in to manage your store</p>
    </div>

    <?php if ($this->session->flashdata('error')): ?>
      <div class="alert alert-danger py-2">
        <i class="bi bi-exclamation-triangle me-1"></i>
        <?= $this->session->flashdata('error') ?>
      </div>
    <?php endif; ?>

    <?php if ($this->session->flashdata('success')): ?>
      <div class="alert alert-success py-2"><?= $this->session->flashdata('success') ?></div>
    <?php endif; ?>

    <form action="<?= site_url('admin/vendor/login/submit') ?>" method="POST">
      <input type="hidden" name="<?= $this->security->get_csrf_token_name(); ?>" value="<?= $this->security->get_csrf_hash(); ?>">
      <div class="mb-3">
        <label class="form-label">Vendor Email</label>
        <div class="input-group">
          <span class="input-group-text"><i class="bi bi-envelope"></i></span>
          <input type="email" name="email" class="form-control" placeholder="vendor@2deal.com" required autofocus>
        </div>
      </div>

      <div class="mb-4">
        <label class="form-label">Password</label>
        <div class="input-group">
          <span class="input-group-text"><i class="bi bi-lock"></i></span>
          <input type="password" name="password" id="loginPass" class="form-control" placeholder="••••••••" required>
          <button class="btn btn-outline-secondary" type="button" onclick="togglePass()">
            <i class="bi bi-eye" id="passIcon"></i>
          </button>
        </div>
        <div class="text-end mt-1">
          <a href="<?= site_url('admin/vendor/forgot-password') ?>" class="small">Forgot password?</a>
        </div>
      </div>

      <button type="submit" class="btn btn-primary w-100 fw-bold py-2">
        <i class="bi bi-box-arrow-in-right me-1"></i> Vendor Sign In
      </button>
    </form>

    <p class="text-center small mt-4 mb-0">
      <a href="<?= site_url('admin/login') ?>">Admin login</a>
    </p>
  </div>
</div>

<script>
function togglePass() {
  var f = document.getElementById('loginPass');
  var i = document.getElementById('passIcon');
  if (f.type === 'password') { f.type='text'; i.className='bi bi-eye-slash'; }
  else { f.type='password'; i.className='bi bi-eye'; }
}
</script>
</body>
</html>
