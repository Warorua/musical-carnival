<?php
require_once __DIR__.'/../includes/functions.php';
require_admin();
$ref = trim($_GET['invoice_no'] ?? '');
if ($ref==='') { redirect(APP_URL.'/admin/dashboard.php'); }
$rows = fetch_all('SELECT * FROM bypass WHERE invoice_no=? ORDER BY timestamp ASC', [$ref]);
$sum = 0; foreach ($rows as $r) $sum += (float)$r['amount'];
// 1) Try the agent_queue first (unchanged)
$agentGuess = fetch_one(
    'SELECT aq.client_id, c.name AS client_name
     FROM agent_queue aq
     JOIN clients c ON c.id = aq.client_id
     WHERE aq.invoice_no = ?
     ORDER BY aq.created_at DESC
     LIMIT 1',
    [$ref]
);

// 2) Fallback: take one client value from bypass, then map to clients by name
if (!$agentGuess) {
    $b = fetch_one(
        'SELECT client
         FROM bypass
         WHERE invoice_no = ?
         ORDER BY `timestamp` DESC
         LIMIT 1',
        [$ref]
    );
    if ($b && !empty($b['client'])) {
        $agentGuess = fetch_one(
            'SELECT id AS client_id, name AS client_name
             FROM clients
             WHERE name = ?
             LIMIT 1',
            [$b['client']]
        );
    }
}

if (!$agentGuess) {
    $agentGuess = fetch_one('SELECT c.id AS client_id, c.name AS client_name FROM clients c WHERE c.name IN (SELECT DISTINCT client FROM bypass WHERE invoice_no=? LIMIT 1)', [$ref]);
}
$client_id = $agentGuess['client_id'] ?? null;
$pct = ($client_id) ? commission_pct_for($ref, (int)$client_id) : null;
$comm_amount = ($pct!==null) ? ($sum * ($pct/100.0)) : null;

require_once __DIR__.'/../includes/head.php';
require_once __DIR__.'/../includes/navbar.php';
?>
<div class="card shadow-sm">
  <div class="card-body">
    <div class="d-flex justify-content-between align-items-center">
      <h5 class="mb-0">Payment Ref: <?= htmlspecialchars($ref) ?></h5>
      <a class="btn btn-outline-secondary btn-sm" href="dashboard.php">Back</a>
    </div>
    <hr>
    <div class="row g-3">
      <div class="col-md-3"><div class="border rounded p-3">
        <div class="text-muted">Total Amount</div>
        <div class="fs-4 fw-bold"><?= number_format($sum,2) ?></div>
      </div></div>
      <div class="col-md-3"><div class="border rounded p-3">
        <div class="text-muted">Entries</div>
        <div class="fs-4 fw-bold"><?= count($rows) ?></div>
      </div></div>
      <div class="col-md-6"><div class="border rounded p-3">
        <div class="text-muted">Agent</div>
        <div class="fw-bold"><?= htmlspecialchars($agentGuess['client_name'] ?? 'Unknown') ?></div>
        <div class="mt-2">Commission %: <strong><?= $pct!==null ? $pct : '—' ?></strong></div>
        <div>Commission Amount: <strong><?= $comm_amount!==null ? number_format($comm_amount,2) : '—' ?></strong></div>
      </div></div>
    </div>

    <h6 class="mt-4">Entries</h6>
    <div class="table-responsive">
      <table class="table table-sm align-middle">
        <thead><tr>
          <th>Amount</th><th>Master</th><th>Regular</th><th>Track</th><th>Note</th><th>Timestamp</th><th>Client</th><th>Ref</th><th>Route</th>
        </tr></thead>
        <tbody>
        <?php foreach ($rows as $r): ?>
          <tr>
            <td><?= number_format((float)$r['amount'],2) ?></td>
            <td><?= htmlspecialchars($r['master_status']) ?></td>
            <td><?= htmlspecialchars($r['regular_status']) ?></td>
            <td><?= htmlspecialchars($r['track']) ?></td>
            <td><?= htmlspecialchars($r['note']) ?></td>
            <td><?= htmlspecialchars($r['timestamp']) ?></td>
            <td><?= htmlspecialchars($r['client']) ?></td>
            <td><?= htmlspecialchars($r['ref']) ?></td>
            <td><?= htmlspecialchars($r['route']) ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<?php include __DIR__.'/../includes/footer.php'; ?>
