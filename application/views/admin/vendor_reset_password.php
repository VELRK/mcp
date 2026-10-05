<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Vendor Reset Password - Talk AI Pilot</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <link rel="stylesheet" href="<?= base_url('assets/admin/css/admin.css') ?>">
</head>
<body>

<div class="sk-login-wrapper">
  <div class="sk-login-card card p-4 p-md-5">
    <div class="text-center mb-4">
      <div class="mb-2"><i class="bi bi-shield-lock text-primary" style="font-size:2.5rem;"></i></div>
      <h4 class="fw-bold mb-0">Reset Password</h4>
      <p class="text-muted small">Enter the code sent to <strong><?= htmlspecialchars($email ?? '') ?></strong></p>
    </div>

    <?php if ($this->session->flashdata('error')): ?>
      <div class="alert alert-danger py-2"><?= $this->session->flashdata('error') ?></div>
    <?php endif; ?>
    <?php if ($this->session->flashdata('success')): ?>
      <div class="alert alert-success py-2"><?= $this->session->flashdata('success') ?></div>
    <?php endif; ?>

    <form action="<?= site_url('admin/vendor/reset-password/submit') ?>" method="POST">
      <input type="hidden" name="email" value="<?= htmlspecialchars($email ?? '') ?>">
      <div class="mb-3">
        <label class="form-label">Email Code</label>
        <input type="text" name="code" class="form-control" maxlength="6" pattern="\d{6}" inputmode="numeric"
               placeholder="6-digit code" required autofocus autocomplete="one-time-code">
      </div>
      <div class="mb-3">
        <label class="form-label">New Password</label>
        <input type="password" name="password" class="form-control" minlength="6" required autocomplete="new-password">
      </div>
      <div class="mb-4">
        <label class="form-label">Confirm Password</label>
        <input type="password" name="password_confirm" class="form-control" minlength="6" required autocomplete="new-password">
      </div>
      <button type="submit" class="btn btn-primary w-100 fw-bold py-2">Update Password</button>
    </form>

    <p class="text-center small mt-4 mb-0">
      <a href="<?= site_url('admin/vendor/forgot-password') ?>">Resend code</a>
      ·
      <a href="<?= site_url('admin/vendor/login') ?>">Sign in</a>
    </p>
  </div>
</div>
</body>
</html>
