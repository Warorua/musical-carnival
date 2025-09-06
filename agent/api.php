<?php
const API_DEBUG = false;

ob_start();
ini_set('display_errors', '0');

require_once __DIR__ . '/../includes/functions.php';

set_error_handler(function ($severity, $message, $file, $line) {
    if (!(error_reporting() & $severity)) return;
    throw new ErrorException($message, 0, $severity, $file, $line);
});

function respond(array $payload, int $code = 200): void
{
    http_response_code($code);
    if (ob_get_length()) {
        ob_clean();
    }
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}
function safe_fetch_all(string $sql, array $p = []): array
{
    $st = db()->prepare($sql);
    $st->execute($p);
    return $st->fetchAll();
}
function safe_fetch_one(string $sql, array $p = [])
{
    $st = db()->prepare($sql);
    $st->execute($p);
    return $st->fetch();
}

/* --- derive logged-in agent's client_id robustly --- */
function current_client_id(): int
{
    $cid = (int)($_SESSION['agent_client_id'] ?? 0);
    if ($cid > 0) return $cid;

    // try agents_auth.id
    $aid = (int)($_SESSION['agent_id'] ?? 0);
    if ($aid > 0) {
        $row = safe_fetch_one('SELECT client_id FROM agents_auth WHERE id=? LIMIT 1', [$aid]);
        if ($row && isset($row['client_id'])) {
            $_SESSION['agent_client_id'] = (int)$row['client_id'];
            return (int)$row['client_id'];
        }
    }
    // try agents_auth.url_slug
    $slug = $_SESSION['agent_slug'] ?? null;
    if ($slug) {
        $row = safe_fetch_one('SELECT client_id FROM agents_auth WHERE url_slug=? LIMIT 1', [$slug]);
        if ($row && isset($row['client_id'])) {
            $_SESSION['agent_client_id'] = (int)$row['client_id'];
            return (int)$row['client_id'];
        }
    }
    return 0;
}

try {
    if (!agent_logged_in()) respond(['ok' => false, 'error' => 'Not logged in'], 401);

    $action = $_GET['action'] ?? '';
    $cid = current_client_id();
    if ($cid <= 0) respond(['ok' => false, 'error' => 'Session missing client context'], 401);

    $agentNameRow = safe_fetch_one('SELECT name FROM clients WHERE id=? LIMIT 1', [$cid]);
    $agentName = $agentNameRow['name'] ?? '';

    if ($action === 'whoami') {
        respond(['ok' => true, 'data' => [
            'agent_id' => (int)($_SESSION['agent_id'] ?? 0),
            'agent_client_id' => $cid,
            'agent_slug' => $_SESSION['agent_slug'] ?? null,
            'agent_name' => $agentName
        ]]);
    }

    if ($action === 'kpis') {
        [$start, $end] = dt_range($_GET['scope'] ?? 'today');
        $tot = safe_fetch_one(
            'SELECT COALESCE(SUM(b.amount),0) AS total, COUNT(*) AS entries
               FROM bypass b
               JOIN clients c ON c.id=? AND c.name COLLATE utf8mb4_unicode_ci = b.client COLLATE utf8mb4_unicode_ci
              WHERE b.timestamp>=? AND b.timestamp<?',
            [$cid, $start, $end]
        );
        $refs = safe_fetch_one(
            'SELECT COUNT(*) AS cnt FROM (
                 SELECT b.invoice_no
                   FROM bypass b
                   JOIN clients c ON c.id=? AND c.name COLLATE utf8mb4_unicode_ci = b.client COLLATE utf8mb4_unicode_ci
                  WHERE b.timestamp>=? AND b.timestamp<?
                 UNION
                 SELECT aq.invoice_no
                   FROM agent_queue aq
                  WHERE aq.client_id=? AND aq.created_at>=? AND aq.created_at<?
             ) t',
            [$cid, $start, $end, $cid, $start, $end]
        );
        $decl = safe_fetch_one(
            'SELECT SUM(declared_paid=1) AS paid, SUM(declared_paid=0) AS unpaid
               FROM agent_queue
              WHERE client_id=? AND created_at>=? AND created_at<?',
            [$cid, $start, $end]
        );
        respond(['ok' => true, 'data' => [
            'total_amount' => (float)($tot['total'] ?? 0),
            'entries' => (int)($tot['entries'] ?? 0),
            'refs' => (int)($refs['cnt'] ?? 0),
            'declared_paid' => (int)($decl['paid'] ?? 0),
            'declared_unpaid' => (int)($decl['unpaid'] ?? 0),
            'range' => [$start, $end]
        ]]);
    }

    if ($action === 'week_series') {
        [$ws, $we] = dt_range('week');
        $rows = safe_fetch_all(
            'SELECT DATE(b.timestamp) d, COALESCE(SUM(b.amount),0) total
               FROM bypass b
               JOIN clients c ON c.id=? AND c.name COLLATE utf8mb4_unicode_ci = b.client COLLATE utf8mb4_unicode_ci
              WHERE b.timestamp>=? AND b.timestamp<?
              GROUP BY DATE(b.timestamp)',
            [$cid, $ws, $we]
        );
        $map = [];
        foreach ($rows as $r) $map[$r['d']] = (float)$r['total'];
        $out = [];
        $d = new DateTime($ws);
        while ($d < new DateTime($we)) {
            $k = $d->format('Y-m-d');
            $out[] = ['date' => $k, 'total' => $map[$k] ?? 0.0];
            $d->modify('+1 day');
        }
        respond(['ok' => true, 'data' => $out]);
    }

    if ($action === 'queue_list') {
        $rows = safe_fetch_all(
            "SELECT aq.*,
                    (SELECT COALESCE(SUM(b.amount),0)
                       FROM bypass b
                       JOIN clients c2 ON c2.id=aq.client_id
                      WHERE b.invoice_no COLLATE utf8mb4_unicode_ci = aq.invoice_no COLLATE utf8mb4_unicode_ci
                        AND b.client     COLLATE utf8mb4_unicode_ci = c2.name   COLLATE utf8mb4_unicode_ci
                    ) AS linked_amount,
                    (SELECT COUNT(*)
                       FROM bypass b
                       JOIN clients c2 ON c2.id=aq.client_id
                      WHERE b.invoice_no COLLATE utf8mb4_unicode_ci = aq.invoice_no COLLATE utf8mb4_unicode_ci
                        AND b.client     COLLATE utf8mb4_unicode_ci = c2.name   COLLATE utf8mb4_unicode_ci
                    ) AS entry_count
               FROM agent_queue aq
              WHERE aq.client_id=?
              ORDER BY aq.created_at DESC
              LIMIT 500",
            [$cid]
        );
        respond(['ok' => true, 'data' => $rows]);
    }

    if ($action === 'discovered') {
        $rows = safe_fetch_all(
            "SELECT b.invoice_no,
                    COUNT(*) AS entry_count,
                    COALESCE(SUM(b.amount),0) AS total_amount,
                    MIN(b.timestamp) AS first_seen,
                    MAX(b.timestamp) AS last_seen
               FROM bypass b
               JOIN clients c ON c.id=? AND c.name COLLATE utf8mb4_unicode_ci = b.client COLLATE utf8mb4_unicode_ci
              WHERE b.invoice_no COLLATE utf8mb4_unicode_ci NOT IN (
                        SELECT invoice_no COLLATE utf8mb4_unicode_ci FROM agent_queue WHERE client_id=?)
                AND b.invoice_no COLLATE utf8mb4_unicode_ci NOT IN (
                        SELECT invoice_no COLLATE utf8mb4_unicode_ci FROM commissions WHERE client_id=?)
              GROUP BY b.invoice_no
              ORDER BY last_seen DESC
              LIMIT 500",
            [$cid, $cid, $cid]
        );
        respond(['ok' => true, 'data' => $rows]);
    }

    /* ===== All refs with sort_id, paid_status, note ===== */
    if ($action === 'all_refs') {
        $all = safe_fetch_all(
            "SELECT
                 t.invoice_no,

                 COALESCE(agg.total_amount,0) AS total_amount,
                 COALESCE(agg.entry_count,0)  AS entry_count,
                 agg.last_seen,

                 aq_last.aq_last_id,
                 bl.b_last_id,
                 GREATEST(COALESCE(aq_last.aq_last_id,0), COALESCE(bl.b_last_id,0)) AS sort_id,

                 aq_latest.declared_amount,
                 aq_latest.commission_pct     AS aq_commission_pct,
                 aq_latest.declared_paid,
                 aq_latest.note                AS note,

                 cm.commission_pct            AS final_commission_pct,

                 ars.status                   AS paid_status,
                 ars.partial_amount           AS partial_amount

            FROM (
                 SELECT invoice_no FROM agent_queue WHERE client_id=?
                 UNION
                 SELECT b.invoice_no
                   FROM bypass b
                   JOIN clients c
                     ON c.id=? AND c.name COLLATE utf8mb4_unicode_ci = b.client COLLATE utf8mb4_unicode_ci
            ) t

            LEFT JOIN (
                 SELECT b.invoice_no,
                        COUNT(*) AS entry_count,
                        COALESCE(SUM(b.amount),0) AS total_amount,
                        MAX(b.timestamp) AS last_seen
                   FROM bypass b
                   JOIN clients c
                     ON c.id=? AND c.name COLLATE utf8mb4_unicode_ci = b.client COLLATE utf8mb4_unicode_ci
                  GROUP BY b.invoice_no
            ) agg
              ON agg.invoice_no COLLATE utf8mb4_unicode_ci = t.invoice_no COLLATE utf8mb4_unicode_ci

            LEFT JOIN (
                 SELECT x.invoice_no, MAX(x.id) AS aq_last_id
                   FROM agent_queue x
                  WHERE x.client_id=?
                  GROUP BY x.invoice_no
            ) aq_last
              ON aq_last.invoice_no COLLATE utf8mb4_unicode_ci = t.invoice_no COLLATE utf8mb4_unicode_ci

            LEFT JOIN (
                 SELECT b.invoice_no, MAX(b.id) AS b_last_id
                   FROM bypass b
                   JOIN clients c
                     ON c.id=? AND c.name COLLATE utf8mb4_unicode_ci = b.client COLLATE utf8mb4_unicode_ci
                  GROUP BY b.invoice_no
            ) bl
              ON bl.invoice_no COLLATE utf8mb4_unicode_ci = t.invoice_no COLLATE utf8mb4_unicode_ci

            /* latest AQ row per (client, invoice) for declared_amount/commission/note */
            LEFT JOIN (
                 SELECT a1.invoice_no, a1.declared_amount, a1.commission_pct, a1.declared_paid, a1.note, a1.created_at
                   FROM agent_queue a1
                   JOIN (
                      SELECT invoice_no, MAX(created_at) AS mx
                        FROM agent_queue
                       WHERE client_id=?
                       GROUP BY invoice_no
                   ) a2
                     ON a2.invoice_no = a1.invoice_no AND a2.mx = a1.created_at
                  WHERE a1.client_id=?
            ) aq_latest
              ON aq_latest.invoice_no COLLATE utf8mb4_unicode_ci = t.invoice_no COLLATE utf8mb4_unicode_ci

            LEFT JOIN commissions cm
              ON cm.client_id=? AND cm.invoice_no COLLATE utf8mb4_unicode_ci = t.invoice_no COLLATE utf8mb4_unicode_ci

            LEFT JOIN agent_ref_status ars
              ON ars.client_id=? AND ars.invoice_no COLLATE utf8mb4_unicode_ci = t.invoice_no COLLATE utf8mb4_unicode_ci

            GROUP BY t.invoice_no
            ORDER BY sort_id DESC",
            // 1    2    3    4    5    6    7    8    9  (nine params)
            [$cid, $cid, $cid, $cid, $cid, $cid, $cid, $cid, $cid]
        );
        respond(['ok' => true, 'data' => $all]);
    }

    /* ===== Ref entries: timestamp + amount + receipt_msg ===== */
    if ($action === 'ref_entries') {
        $ref = trim($_GET['invoice_no'] ?? '');
        if ($ref === '') respond(['ok' => false, 'error' => 'Missing ref'], 400);

        $rows = safe_fetch_all(
            'SELECT b.timestamp, b.amount, b.receipt_msg AS receipt_msg
               FROM bypass b
               JOIN clients c ON c.id=? AND c.name COLLATE utf8mb4_unicode_ci = b.client COLLATE utf8mb4_unicode_ci
              WHERE b.invoice_no COLLATE utf8mb4_unicode_ci = ? COLLATE utf8mb4_unicode_ci
              ORDER BY b.timestamp ASC',
            [$cid, $ref]
        );

        $totRow = safe_fetch_one(
            'SELECT COALESCE(SUM(b.amount),0) AS total
               FROM bypass b
               JOIN clients c ON c.id=? AND c.name COLLATE utf8mb4_unicode_ci = b.client COLLATE utf8mb4_unicode_ci
              WHERE b.invoice_no COLLATE utf8mb4_unicode_ci = ? COLLATE utf8mb4_unicode_ci',
            [$cid, $ref]
        );
        $total = (float)($totRow['total'] ?? 0);
        $st = safe_fetch_one(
            'SELECT status, partial_amount
               FROM agent_ref_status
              WHERE client_id=? AND invoice_no COLLATE utf8mb4_unicode_ci = ? COLLATE utf8mb4_unicode_ci
              LIMIT 1',
            [$cid, $ref]
        );
        $status = $st['status'] ?? 'not_paid';
        $partial = (float)($st['partial_amount'] ?? 0);
        $paid = ($status === 'paid') ? $total : (($status === 'partial') ? min($partial, $total) : 0.0);
        $balance = max(0.0, $total - $paid);

        respond(['ok' => true, 'data' => $rows, 'meta' => [
            'total_amount' => $total,
            'paid_status' => $status,
            'partial_amount' => $partial,
            'paid' => $paid,
            'balance' => $balance
        ]]);
    }

    /* ===== Add ref to queue ===== */
    if ($action === 'add_ref' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!csrf_check($_POST['csrf'] ?? '')) respond(['ok' => false, 'error' => 'Bad token'], 403);
        $invoice_no = trim($_POST['invoice_no'] ?? '');
        if ($invoice_no === '') respond(['ok' => false, 'error' => 'Ref required']);
        $declared_amount = $_POST['declared_amount'] !== '' ? (float)$_POST['declared_amount'] : null;
        $commission_pct  = $_POST['commission_pct']  !== '' ? (float)$_POST['commission_pct']  : null;
        $declared_paid   = isset($_POST['declared_paid']) ? 1 : 0;
        $note = trim($_POST['note'] ?? '');
        $st = db()->prepare(
            'INSERT INTO agent_queue (client_id,invoice_no,declared_amount,commission_pct,declared_paid,note,status,created_at)
             VALUES (?,?,?,?,?,?,?,NOW())'
        );
        $st->execute([$cid, $invoice_no, $declared_amount, $commission_pct, $declared_paid, $note, 'awaiting']);
        respond(['ok' => true, 'data' => ['id' => db()->lastInsertId()]]);
    }

    /* ===== Save/override commission % for this agent/ref ===== */
    if ($action === 'set_commission' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!csrf_check($_POST['csrf'] ?? '')) respond(['ok' => false, 'error' => 'Bad token'], 403);
        $invoice_no = trim($_POST['invoice_no'] ?? '');
        $pct = isset($_POST['commission_pct']) ? (float)$_POST['commission_pct'] : null;
        if ($invoice_no === '' || $pct === null) respond(['ok' => false, 'error' => 'Missing']);
        $exists = safe_fetch_one('SELECT id FROM commissions WHERE invoice_no=? AND client_id=? LIMIT 1', [$invoice_no, $cid]);
        if ($exists) {
            $u = db()->prepare('UPDATE commissions SET commission_pct=?, source="agent", updated_at=NOW() WHERE id=?');
            $u->execute([$pct, $exists['id']]);
        } else {
            $i = db()->prepare('INSERT INTO commissions (invoice_no,client_id,commission_pct,source,created_at) VALUES (?,?,?,?,NOW())');
            $i->execute([$invoice_no, $cid, $pct, 'agent']);
        }
        respond(['ok' => true]);
    }

    /* ===== set paid status (not_paid / partial / paid) ===== */
    if ($action === 'set_paid_status' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!csrf_check($_POST['csrf'] ?? '')) respond(['ok' => false, 'error' => 'Bad token'], 403);

        $invoice_no = trim($_POST['invoice_no'] ?? '');
        $status = trim($_POST['status'] ?? 'not_paid');
        $partial = isset($_POST['partial_amount']) ? (float)$_POST['partial_amount'] : 0.0;

        if ($invoice_no === '') respond(['ok' => false, 'error' => 'Missing invoice_no']);
        if (!in_array($status, ['not_paid', 'partial', 'paid'], true)) respond(['ok' => false, 'error' => 'Invalid status']);

        $totRow = safe_fetch_one(
            'SELECT COALESCE(SUM(b.amount),0) AS total
               FROM bypass b
               JOIN clients c ON c.id=? AND c.name COLLATE utf8mb4_unicode_ci = b.client COLLATE utf8mb4_unicode_ci
              WHERE b.invoice_no COLLATE utf8mb4_unicode_ci = ? COLLATE utf8mb4_unicode_ci',
            [$cid, $invoice_no]
        );
        $total = (float)($totRow['total'] ?? 0);

        if ($status === 'partial') {
            if ($partial < 0) respond(['ok' => false, 'error' => 'Partial cannot be negative']);
            if ($partial > $total) respond(['ok' => false, 'error' => 'Partial exceeds total']);
        } else {
            $partial = ($status === 'paid') ? $total : 0.0;
        }

        $exists = safe_fetch_one(
            'SELECT id FROM agent_ref_status WHERE client_id=? AND invoice_no COLLATE utf8mb4_unicode_ci = ? COLLATE utf8mb4_unicode_ci LIMIT 1',
            [$cid, $invoice_no]
        );
        if ($exists) {
            $u = db()->prepare('UPDATE agent_ref_status SET status=?, partial_amount=?, updated_at=NOW() WHERE id=?');
            $u->execute([$status, $partial, $exists['id']]);
        } else {
            $i = db()->prepare('INSERT INTO agent_ref_status (client_id,invoice_no,status,partial_amount,updated_at) VALUES (?,?,?,?,NOW())');
            $i->execute([$cid, $invoice_no, $status, $partial]);
        }

        $balance = max(0.0, $total - $partial);
        respond(['ok' => true, 'data' => [
            'total' => $total,
            'status' => $status,
            'partial' => $partial,
            'balance' => $balance
        ]]);
    }

    /* ===== set/update a common NOTE for this agent/ref ===== */
    if ($action === 'set_ref_note' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!csrf_check($_POST['csrf'] ?? '')) respond(['ok' => false, 'error' => 'Bad token'], 403);

        $invoice_no = trim($_POST['invoice_no'] ?? '');
        $note = trim($_POST['note'] ?? '');
        if ($invoice_no === '') respond(['ok' => false, 'error' => 'Missing invoice_no']);

        $row = safe_fetch_one(
            'SELECT id FROM agent_queue WHERE client_id=? AND invoice_no COLLATE utf8mb4_unicode_ci = ? COLLATE utf8mb4_unicode_ci ORDER BY created_at DESC LIMIT 1',
            [$cid, $invoice_no]
        );
        if ($row && !empty($row['id'])) {
            $u = db()->prepare('UPDATE agent_queue SET note=? WHERE id=?');
            $u->execute([$note, $row['id']]);
        } else {
            $i = db()->prepare(
                'INSERT INTO agent_queue (client_id, invoice_no, note, status, created_at)
                 VALUES (?,?,?,?, NOW())'
            );
            $i->execute([$cid, $invoice_no, $note, 'awaiting']);
        }
        respond(['ok' => true]);
    }

    respond(['ok' => false, 'error' => 'Unknown action'], 404);
} catch (Throwable $e) {
    $msg = API_DEBUG ? ($e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine()) : 'Server error';
    respond(['ok' => false, 'error' => $msg], 500);
}
