<?php
require_once __DIR__.'/../includes/functions.php';

$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check($_POST['csrf'] ?? '')) {
        $error = 'Invalid session token.';
    } else {
        $ok = admin_login(trim($_POST['email'] ?? ''), $_POST['password'] ?? '');
        if ($ok) {
            redirect(APP_URL . '/admin/dashboard.php');
        } else {
            $error = 'Invalid credentials.';
        }
    }
}

require_once __DIR__.'/../includes/head.php';
require_once __DIR__.'/../includes/navbar.php';
?>
<div class="row justify-content-center">
  <div class="col-md-5">
    <div class="card shadow-sm">
      <div class="card-body">
        <h5 class="mb-3">Admin Login</h5>
        <?php if ($error): ?>
          <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <form method="post" autocomplete="off">
          <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
          <div class="mb-3">
            <label class="form-label">Email</label>
            <input name="email" type="email" class="form-control" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Password</label>
            <input name="password" type="password" class="form-control" required>
          </div>
          <button class="btn btn-primary w-100">Login</button>
        </form>
      </div>
    </div>
  </div>
</div>
<?php include __DIR__.'/../includes/footer.php'; ?>
