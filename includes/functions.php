<?php
require_once __DIR__ . '/config.php';

date_default_timezone_set('Africa/Nairobi');

function dt_range(string $scope): array {
    $now = new DateTime('now');
    if ($scope === 'today') {
        $start = new DateTime('today');
        $end = (clone $start)->modify('+1 day');
    } elseif ($scope === 'week') {
        $start = (clone $now)->modify('monday this week')->setTime(0,0,0);
        $end = (clone $start)->modify('+7 days');
    } elseif ($scope === 'month') {
        $start = new DateTime($now->format('Y-m-01').' 00:00:00');
        $end = (clone $start)->modify('+1 month');
    } elseif ($scope === 'year') {
        $start = new DateTime($now->format('Y-01-01').' 00:00:00');
        $end = (clone $start)->modify('+1 year');
    } else {
        $start = new DateTime('today');
        $end = (clone $start)->modify('+1 day');
    }
    return [$start->format('Y-m-d H:i:s'), $end->format('Y-m-d H:i:s')];
}

function fetch_one(string $sql, array $p = []) {
    $st = db()->prepare($sql); $st->execute($p); return $st->fetch();
}
function fetch_all(string $sql, array $p = []): array {
    $st = db()->prepare($sql); $st->execute($p); return $st->fetchAll();
}
function json_ok($data = []) {
    header('Content-Type: application/json'); echo json_encode(['ok'=>true,'data'=>$data]); exit;
}
function json_err(string $msg, $code = 400) {
    http_response_code($code); header('Content-Type: application/json'); echo json_encode(['ok'=>false,'error'=>$msg]); exit;
}
function commission_pct_for(string $invoice_no, int $client_id): ?float {
    $r = fetch_one('SELECT commission_pct FROM commissions WHERE invoice_no=? AND client_id=? LIMIT 1', [$invoice_no,$client_id]);
    if ($r && $r['commission_pct'] !== null) return (float)$r['commission_pct'];
    $r2 = fetch_one('SELECT commission_pct FROM agent_queue WHERE invoice_no=? AND client_id=? ORDER BY created_at DESC LIMIT 1', [$invoice_no,$client_id]);
    if ($r2 && $r2['commission_pct'] !== null) return (float)$r2['commission_pct'];
    return null;
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function db(): PDO {
    static $pdo = null;
    if ($pdo) return $pdo;
    $dsn = 'mysql:host='.DB_HOST.';dbname='.DB_NAME.';charset=utf8mb4';
    $opt = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ];
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $opt);
    return $pdo;
}

function redirect(string $path) {
    header('Location: ' . $path);
    exit;
}

function csrf_token(): string {
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}
function csrf_check(?string $t): bool {
    return isset($_SESSION['csrf'], $t) && hash_equals($_SESSION['csrf'], $t);
}

/* ---------- Admin auth ---------- */
function admin_login(string $email, string $password): bool {
    $stmt = db()->prepare('SELECT * FROM users_admin WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    $u = $stmt->fetch();
    if (!$u) return false;
    if (!password_verify($password, $u['password_hash'])) return false;

    $upd = db()->prepare('UPDATE users_admin SET last_login_at = NOW() WHERE id = ?');
    $upd->execute([$u['id']]);

    $_SESSION['admin_id'] = (int)$u['id'];
    $_SESSION['admin_name'] = $u['name'];
    return true;
}
function admin_logged_in(): bool {
    return !empty($_SESSION['admin_id']);
}
function require_admin() {
    if (!admin_logged_in()) redirect(APP_URL . '/admin/login.php');
}

/* ---------- Agent auth ---------- */
function agent_by_slug(string $slug): ?array {
    $q = db()->prepare('SELECT aa.*, c.name AS client_name FROM agents_auth aa INNER JOIN clients c ON c.id = aa.client_id WHERE aa.url_slug = ? AND aa.is_active = 1 LIMIT 1');
    $q->execute([$slug]);
    $agent = $q->fetch();
    return $agent ?: null;
}
function agent_login(string $slug, string $password): bool {
    $a = agent_by_slug($slug);
    if (!$a) return false;
    if (!password_verify($password, $a['password_hash'])) return false;

    $upd = db()->prepare('UPDATE agents_auth SET last_login_at = NOW() WHERE id = ?');
    $upd->execute([$a['id']]);

    $_SESSION['agent_id'] = (int)$a['id'];
    $_SESSION['agent_client_id'] = (int)$a['client_id'];
    $_SESSION['agent_slug'] = $a['url_slug'];
    $_SESSION['agent_name'] = $a['client_name'];
    return true;
}
function agent_logged_in(): bool {
    return !empty($_SESSION['agent_id']);
}
function require_agent() {
    if (!agent_logged_in()) redirect(APP_URL . '/agent/login.php');
}
