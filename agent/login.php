<?php
require_once __DIR__.'/../includes/functions.php';

$slug = $_GET['slug'] ?? '';
$agent = $slug ? agent_by_slug($slug) : null;

if (!$agent) {
    // If accessed without/with bad slug, show info.
    require_once __DIR__.'/../includes/head.php';
    require_once __DIR__.'/../includes/navbar.php';
    ?>
    <div class="alert alert-warning">Invalid or missing agent URL. Use your private link: <code><?= APP_URL ?>/a/&lt;your-slug&gt;</code></div>
    <?php include __DIR__.'/../includes/footer.php'; exit;
}

$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check($_POST['csrf'] ?? '')) {
        $error = 'Invalid session token.';
    } else {
        $ok = agent_login($slug, $_POST['password'] ?? '');
        if ($ok) redirect(APP_URL . '/agent/dashboard.php');
        $error = 'Invalid password.';
    }
}

require_once __DIR__.'/../includes/head.php';
require_once __DIR__.'/../includes/navbar.php';
?>
<div class="row justify-content-center">
  <div class="col-md-5">
    <div class="card shadow-sm">
      <div class="card-body">
        <h5 class="mb-3">Agent Login</h5>
        <div class="mb-2 text-muted">Agent: <strong><?= htmlspecialchars($agent['client_name']) ?></strong></div>
        <?php if ($error): ?>
          <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <form method="post" autocomplete="off">
          <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
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
