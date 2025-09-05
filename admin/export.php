<?php
require_once __DIR__.'/../includes/functions.php';
require_admin();
$scope = $_GET['scope'] ?? 'week';
[$start,$end] = dt_range($scope);
$rows = fetch_all(
  "SELECT b.invoice_no, COUNT(*) AS entry_count, COALESCE(SUM(b.amount),0) AS total_amount, MIN(b.timestamp) AS first_seen, MAX(b.timestamp) AS last_seen
   FROM bypass b WHERE b.timestamp>=? AND b.timestamp<? GROUP BY b.invoice_no ORDER BY last_seen DESC",
  [$start,$end]
);
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="refs_'.$scope.'_'.date('Ymd_His').'.csv"');
$out = fopen('php://output', 'w');
fputcsv($out, ['invoice_no','entry_count','total_amount','first_seen','last_seen']);
foreach($rows as $r) fputcsv($out, [$r['invoice_no'],$r['entry_count'],$r['total_amount'],$r['first_seen'],$r['last_seen']]);
fclose($out);
exit;
