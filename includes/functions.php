<?php
require_once __DIR__ . '/../config/database.php';

function e($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function url(string $path = ''): string { return BASE_URL . '/' . ltrim($path, '/'); }
function redirect(string $path): never { header('Location: ' . url($path)); exit; }
function money($v): string { return '₱' . number_format((float)$v, 2); }
function is_post(): bool { return ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST'; }
function input(string $key, $default = ''): string { return trim((string)($_POST[$key] ?? $_GET[$key] ?? $default)); }
function client_ip(): string { return $_SERVER['REMOTE_ADDR'] ?? ''; }

/* ---- Flash messages ---- */
function flash(string $type, string $msg): void { $_SESSION['flash'][] = ['type' => $type, 'msg' => $msg]; }
function get_flashes(): array { $f = $_SESSION['flash'] ?? []; unset($_SESSION['flash']); return $f; }

/* ---- CSRF protection ---- */
function csrf_token(): string {
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf'];
}
function csrf_field(): string { return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">'; }
function csrf_verify(): bool {
    $t = $_POST['csrf_token'] ?? '';
    return is_string($t) && !empty($_SESSION['csrf']) && hash_equals($_SESSION['csrf'], $t);
}
/** Call at the top of every POST handler; stops the request if the token is bad. */
function require_csrf(): void {
    if (!csrf_verify()) { http_response_code(419); exit('Security token expired. Go back, refresh the page and try again.'); }
}

/* ---- One-time form tokens (prevent double submission / duplicate stock deduction) ---- */
function form_token_field(string $form): string {
    $t = bin2hex(random_bytes(16));
    $_SESSION['form_tokens'][$form][$t] = time();
    return '<input type="hidden" name="request_token" value="' . $t . '">';
}
/** Returns the token once if valid, then burns it. Returns null on reuse/invalid. */
function consume_form_token(string $form): ?string {
    $t = $_POST['request_token'] ?? '';
    if (!is_string($t) || !isset($_SESSION['form_tokens'][$form][$t])) return null;
    unset($_SESSION['form_tokens'][$form][$t]);
    return $t;
}

/* ---- Activity log ---- */
function log_activity(string $action, string $module, string $description = '', ?int $userId = null): void {
    try {
        $uid = $userId ?? ($_SESSION['user']['id'] ?? null);
        db()->prepare('INSERT INTO activity_logs (user_id, action, module, description, ip_address) VALUES (?,?,?,?,?)')
            ->execute([$uid, $action, $module, mb_substr($description, 0, 500), client_ip()]);
    } catch (Throwable $e) { /* logging must never break the main action */ }
}

/* ---- Settings ---- */
function setting(string $key, string $default = ''): string {
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        foreach (db()->query('SELECT setting_key, setting_value FROM settings') as $r) $cache[$r['setting_key']] = $r['setting_value'];
    }
    return $cache[$key] ?? $default;
}

/** Sequential-looking unique numbers, e.g. next_number('JOB') => JOB-20261021-0001 */
function next_number(string $prefix, string $table, string $column, string $dateCol = 'created_at'): string {
    $stmt = db()->prepare("SELECT COUNT(*) FROM $table WHERE DATE($dateCol) = CURDATE()");
    $stmt->execute();
    $n = (int)$stmt->fetchColumn() + 1;
    do {
        $no = sprintf('%s-%s-%04d', $prefix, date('Ymd'), $n++);
        $chk = db()->prepare("SELECT 1 FROM $table WHERE $column = ?");
        $chk->execute([$no]);
    } while ($chk->fetchColumn());
    return $no;
}

/* ===================== Shared business logic ===================== */

/** Run $fn(PDO) inside a DB transaction; rolls back and rethrows on any failure. */
function in_transaction(callable $fn) {
    $pdo = db();
    $pdo->beginTransaction();
    try { $r = $fn($pdo); $pdo->commit(); return $r; }
    catch (Throwable $e) { if ($pdo->inTransaction()) $pdo->rollBack(); throw $e; }
}

/** Validates a non-negative money/decimal string. Returns float or null. */
function parse_money($v, bool $allowZero = true): ?float {
    $v = trim(str_replace(',', '', (string)$v));
    if ($v === '' || !preg_match('/^\d{1,10}(\.\d{1,2})?$/', $v)) return null;
    $f = round((float)$v, 2);
    return ($f > 0 || ($allowZero && $f == 0)) ? $f : null;
}
/** Validates a positive (or zero) integer. Returns int or null. */
function parse_int($v, bool $allowZero = false): ?int {
    $v = trim((string)$v);
    if (!preg_match('/^\d{1,9}$/', $v)) return null;
    $i = (int)$v;
    return ($i > 0 || ($allowZero && $i === 0)) ? $i : null;
}
function valid_date(string $d): bool { $x = DateTime::createFromFormat('Y-m-d', $d); return $x && $x->format('Y-m-d') === $d; }
function old(string $k, $default = ''): string { return e($_POST[$k] ?? $default); }

/**
 * Change stock safely: locks the row, refuses negative stock, writes inventory_movements.
 * MUST be called inside in_transaction().
 */
function stock_change(PDO $pdo, int $itemId, int $delta, string $type, ?string $refType, ?int $refId, string $reason, int $userId): void {
    $s = $pdo->prepare('SELECT name, stock_qty FROM inventory_items WHERE id = ? FOR UPDATE');
    $s->execute([$itemId]);
    $r = $s->fetch();
    if (!$r) throw new RuntimeException('Inventory item not found.');
    $before = (int)$r['stock_qty'];
    $after = $before + $delta;
    if ($after < 0) throw new RuntimeException("Not enough stock for \"{$r['name']}\" (available: $before, needed: " . abs($delta) . ').');
    $pdo->prepare('UPDATE inventory_items SET stock_qty = ? WHERE id = ?')->execute([$after, $itemId]);
    $pdo->prepare('INSERT INTO inventory_movements (item_id, movement_type, quantity_change, qty_before, qty_after, reference_type, reference_id, reason, created_by) VALUES (?,?,?,?,?,?,?,?,?)')
        ->execute([$itemId, $type, $delta, $before, $after, $refType, $refId, mb_substr($reason, 0, 255), $userId]);
}

/** Recomputes repair totals on the server (never trusts the browser). Call inside a transaction. */
function recalc_job(PDO $pdo, int $jobId): void {
    $pdo->prepare('SELECT id FROM repair_jobs WHERE id = ? FOR UPDATE')->execute([$jobId]);
    $q = function (string $sql) use ($pdo, $jobId) { $s = $pdo->prepare($sql); $s->execute([$jobId]); return (float)$s->fetchColumn(); };
    $parts = $q("SELECT COALESCE(SUM(line_subtotal),0) FROM repair_parts_used WHERE repair_job_id=? AND status='active'");
    $labor = $q("SELECT COALESCE(SUM(amount),0) FROM labor_entries WHERE repair_job_id=? AND status='active'");
    $paid  = $q("SELECT COALESCE(SUM(amount_applied),0) FROM payments WHERE repair_job_id=? AND status='completed'");
    $d = $pdo->prepare('SELECT discount FROM repair_jobs WHERE id=?'); $d->execute([$jobId]);
    $discount = (float)$d->fetchColumn();
    if ($discount > $parts + $labor) $discount = $parts + $labor;
    $total = round($parts + $labor - $discount, 2);
    $status = ($total > 0 && $paid >= $total) ? 'Paid' : ($paid > 0 ? 'Partially Paid' : 'Unpaid');
    $pdo->prepare('UPDATE repair_jobs SET parts_subtotal=?, labor_subtotal=?, discount=?, total_due=?, amount_paid=?, payment_status=? WHERE id=?')
        ->execute([$parts, $labor, $discount, $total, $paid, $status, $jobId]);
}

function stock_badge(int $qty, int $min): string {
    if ($qty <= 0) return '<span class="badge b-out">Out of stock</span>';
    if ($qty <= $min) return '<span class="badge b-low">Low stock</span>';
    return '<span class="badge b-ok">In stock</span>';
}
function status_badge(string $s): string { return '<span class="badge s-' . e(strtolower(str_replace(' ', '-', $s))) . '">' . e($s) . '</span>'; }

/** Safe record fetch helper. */
function fetch_row(string $sql, array $params = []): ?array { $s = db()->prepare($sql); $s->execute($params); $r = $s->fetch(); return $r ?: null; }
function fetch_all(string $sql, array $params = []): array { $s = db()->prepare($sql); $s->execute($params); return $s->fetchAll(); }
function active_categories(): array { return fetch_all('SELECT id, name FROM categories WHERE is_active=1 ORDER BY name'); }
function active_suppliers(): array { return fetch_all('SELECT id, name FROM suppliers WHERE is_active=1 ORDER BY name'); }

/** Common page-guard for module pages. Both roles can use operational modules. */
function guard(): void { require_role('admin', 'cashier'); }
