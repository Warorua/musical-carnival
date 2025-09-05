<?php
require_once __DIR__ . '/../includes/functions.php';
//require_agent();  // replace with:
if (!agent_logged_in()) {
    header('Content-Type: application/json');
    http_response_code(401);
    echo json_encode(['ok'=>false,'error'=>'Not logged in']);
    exit;
}
$cid = (int)$_SESSION['agent_client_id'];
$rows = fetch_all(
  "SELECT aq.invoice_no, aq.created_at, aq.declared_amount, aq.commission_pct, aq.declared_paid, aq.status,
          (SELECT COALESCE(SUM(b.amount),0) FROM bypass b WHERE b.invoice_no=aq.invoice_no) AS linked_amount,
          (SELECT COUNT(*) FROM bypass b WHERE b.invoice_no=aq.invoice_no) AS entry_count
   FROM agent_queue aq
   WHERE aq.client_id=?
   ORDER BY aq.created_at DESC LIMIT 10000", [$cid]
);
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="my_queue_'.date('Ymd_His').'.csv"');
$out = fopen('php://output','w');
fputcsv($out, ['invoice_no','created_at','declared_amount','commission_pct','declared_paid','status','linked_amount','entry_count']);
foreach($rows as $r) fputcsv($out, [$r['invoice_no'],$r['created_at'],$r['declared_amount'],$r['commission_pct'],$r['declared_paid'],$r['status'],$r['linked_amount'],$r['entry_count']]);
fclose($out);
exit;
