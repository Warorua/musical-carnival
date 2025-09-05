<?php
$who = 'Guest';
if (!empty($_SESSION['admin_id'])) $who = 'Admin: ' . ($_SESSION['admin_name'] ?? '');
if (!empty($_SESSION['agent_id'])) $who = 'Agent: ' . ($_SESSION['agent_name'] ?? '');
?>
<nav class="navbar navbar-expand-lg bg-white border-bottom mb-4">
  <div class="container-fluid">
    <a class="navbar-brand" href="<?= APP_URL ?>/index.php">Payments Manager</a>
    <div class="d-flex">
      <span class="navbar-text me-3"><?= htmlspecialchars($who) ?></span>
      <?php if (!empty($_SESSION['admin_id'])): ?>
        <a class="btn btn-outline-danger btn-sm" href="<?= APP_URL ?>/admin/logout.php">Logout</a>
      <?php elseif (!empty($_SESSION['agent_id'])): ?>
        <a class="btn btn-outline-danger btn-sm" href="<?= APP_URL ?>/agent/logout.php">Logout</a>
      <?php endif; ?>
    </div>
  </div>
</nav>
<div class="container">
