<?php
/**
 * admin/api.php — robust JSON API for Admin dashboard
 * Endpoints:
 *   ?action=kpis&scope=today|week|month|year
 *   ?action=week_series
 *   ?action=awaiting
 *   ?action=refs_grouped&scope=...
 */

const API_DEBUG = false; // set true temporarily if you want detailed errors in JSON

ob_start();
ini_set('display_errors','0');

require_once __DIR__ . '/../includes/functions.php';

/* Convert warnings/notices into exceptions we can JSONify */
set_error_handler(function($severity,$message,$file,$line){
    if (!(error_reporting() & $severity)) return;
    throw new ErrorException($message, 0, $severity, $file, $line);
});

/* Unified JSON response */
function respond(array $payload, int $code = 200): void {
    http_response_code($code);
    if (ob_get_length()) { ob_clean(); } // strip any accidental output/BOM
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

function safe_fetch_one(string $sql, array $p = []) {
    $st = db()->prepare($sql); $st->execute($p); return $st->fetch();
}
function safe_fetch_all(string $sql, array $p = []): array {
    $st = db()->prepare($sql); $st->execute($p); return $st->fetchAll();
}

try {
    // JSON auth guard (do NOT redirect)
    if (!admin_logged_in()) {
        respond(['ok'=>false,'error'=>'Not logged in'], 401);
    }

    $action = $_GET['action'] ?? '';

    if ($action === 'kpis') {
        $scope = $_GET['scope'] ?? 'today';
        [$start,$end] = dt_range($scope);

        $a = safe_fetch_one(
            'SELECT COALESCE(SUM(amount),0) AS total, COUNT(*) AS entries
               FROM bypass
              WHERE timestamp>=? AND timestamp<?',
            [$start,$end]
        );

        $refs = safe_fetch_one(
            'SELECT COUNT(DISTINCT invoice_no) AS cnt
               FROM bypass
              WHERE timestamp>=? AND timestamp<?',
            [$start,$end]
        );

        $decl = safe_fetch_one(
            'SELECT SUM(declared_paid=1) AS paid,
                    SUM(declared_paid=0) AS unpaid
               FROM agent_queue
              WHERE created_at>=? AND created_at<?',
            [$start,$end]
        );

        respond(['ok'=>true,'data'=>[
            'total_amount'    => (float)($a['total'] ?? 0),
            'entries'         => (int)($a['entries'] ?? 0),
            'refs'            => (int)($refs['cnt'] ?? 0),
            'declared_paid'   => (int)($decl['paid'] ?? 0),
            'declared_unpaid' => (int)($decl['unpaid'] ?? 0),
            'range'           => [$start,$end],
        ]]);
    }

    if ($action === 'week_series') {
        [$ws,$we] = dt_range('week');

        $rows = safe_fetch_all(
            'SELECT DATE(`timestamp`) AS d, COALESCE(SUM(amount),0) AS total
               FROM bypass
              WHERE `timestamp`>=? AND `timestamp`<?
              GROUP BY DATE(`timestamp`)',
            [$ws,$we]
        );

        $map = [];
        foreach ($rows as $r) $map[$r['d']] = (float)$r['total'];

        $out = [];
        $d = new DateTime($ws);
        while ($d < new DateTime($we)) {
            $k = $d->format('Y-m-d');
            $out[] = ['date'=>$k, 'total'=> $map[$k] ?? 0.0];
            $d->modify('+1 day');
        }

        respond(['ok'=>true,'data'=>$out]);
    }

    if ($action === 'awaiting') {
        // Collation-safe linking to bypass by invoice_no for preview (systemwide)
        $rows = safe_fetch_all(
            "SELECT aq.*, c.name AS client_name,
                    (SELECT COALESCE(SUM(b.amount),0)
                       FROM bypass b
                      WHERE b.invoice_no COLLATE utf8mb4_unicode_ci
                            = aq.invoice_no COLLATE utf8mb4_unicode_ci
                    ) AS linked_amount,
                    (SELECT COUNT(*)
                       FROM bypass b
                      WHERE b.invoice_no COLLATE utf8mb4_unicode_ci
                            = aq.invoice_no COLLATE utf8mb4_unicode_ci
                    ) AS entry_count
               FROM agent_queue aq
               JOIN clients c ON c.id = aq.client_id
              WHERE aq.status='awaiting'
              ORDER BY aq.created_at DESC
              LIMIT 500"
        );

        respond(['ok'=>true,'data'=>$rows]);
    }

    if ($action === 'refs_grouped') {
        $scope = $_GET['scope'] ?? 'today';
        [$start,$end] = dt_range($scope);

        $rows = safe_fetch_all(
            "SELECT b.invoice_no,
                    COUNT(*)                       AS entry_count,
                    COALESCE(SUM(b.amount),0)      AS total_amount,
                    MIN(b.timestamp)               AS first_seen,
                    MAX(b.timestamp)               AS last_seen
               FROM bypass b
              WHERE b.timestamp>=? AND b.timestamp<?
              GROUP BY b.invoice_no
              ORDER BY last_seen DESC
              LIMIT 1000",
            [$start,$end]
        );

        respond(['ok'=>true,'data'=>$rows]);
    }

    // Unknown action
    respond(['ok'=>false,'error'=>'Unknown action'], 404);

} catch (Throwable $e) {
    $msg = API_DEBUG ? ($e->getMessage().' @ '.$e->getFile().':'.$e->getLine()) : 'Server error';
    respond(['ok'=>false,'error'=>$msg], 500);
}
