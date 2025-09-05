<?php
require_once __DIR__.'/includes/functions.php';
require_once __DIR__.'/includes/head.php';
require_once __DIR__.'/includes/navbar.php';
?>
<div class="row">
  <div class="col-lg-6">
    <div class="card shadow-sm">
      <div class="card-body">
        <h5 class="card-title mb-3">Admin</h5>
        <a class="btn btn-primary" href="<?= APP_URL ?>/admin/login.php">Go to Admin Login</a>
      </div>
    </div>
  </div>
  <div class="col-lg-6 mt-3 mt-lg-0">
    <div class="card shadow-sm">
      <div class="card-body">
        <h5 class="card-title mb-3">Agent</h5>
        <p class="text-muted">Use your private URL: <code><?= APP_URL ?>/a/&lt;your-slug&gt;</code></p>
      </div>
    </div>
  </div>
</div>
<?php include __DIR__.'/includes/footer.php'; ?>
