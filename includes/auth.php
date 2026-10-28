<?php
require_once __DIR__ . '/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    $isHttps = (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off')
        || (($_SERVER['SERVER_PORT'] ?? null) === '443');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

/** Sidebar modules: key => [label, icon, path, allowed roles] */
function modules(): array {
    $both = ['admin', 'cashier'];
    return [
        ['Dashboard',               'fa-gauge-high',     'dashboard.php',               $both],
        ['Inventory',               'fa-boxes-stacked',  'inventory/index.php',         $both],
        ['Categories',              'fa-tags',           'inventory/categories.php',    $both],
        ['Suppliers',               'fa-truck-field',    'inventory/suppliers.php',     $both],
        ['Stock Receiving',         'fa-dolly',          'inventory/receiving.php',     $both],
        ['Repair and Service Jobs', 'fa-screwdriver-wrench', 'repairs/index.php',       $both],
        ['Point of Sale',           'fa-cash-register',  'sales/pos.php',               $both],
        ['Payments and Transactions','fa-receipt',       'payments/index.php',          $both],
        ['Reports',                 'fa-chart-line',     'reports/index.php',           $both],
        ['User Management',         'fa-user-shield',    'admin/users.php',             ['admin']],
        ['Activity Logs',           'fa-clock-rotate-left','admin/activity_logs.php',   ['admin']],
        ['Settings',                'fa-sliders',        'admin/settings.php',          ['admin']],
    ];
}

function current_user(): ?array { return $_SESSION['user'] ?? null; }
function is_admin(): bool { return (current_user()['role'] ?? '') === 'admin'; }
function is_logged_in(): bool { return isset($_SESSION['user']['id']); }

/** Every protected page calls this first. Re-checks the account is still active. */
function require_login(): void {
    if (!is_logged_in()) { redirect('login.php'); }
    $stmt = db()->prepare('SELECT id, username, full_name, role, is_active, must_change_password FROM users WHERE id = ?');
    $stmt->execute([$_SESSION['user']['id']]);
    $u = $stmt->fetch();
    if (!$u || !$u['is_active']) { logout_user(); session_start(); flash('error', 'Your account is not active.'); redirect('login.php'); }
    $_SESSION['user'] = ['id' => (int)$u['id'], 'username' => $u['username'], 'full_name' => $u['full_name'], 'role' => $u['role']];
    $self = basename($_SERVER['SCRIPT_NAME']);
    if ($u['must_change_password'] && $self !== 'change_password.php' && $self !== 'logout.php') redirect('change_password.php');
}

/** Backend authorization: require_role('admin') or require_role('admin','cashier'). */
function require_role(string ...$roles): void {
    require_login();
    if (!in_array(current_user()['role'], $roles, true)) {
        http_response_code(403);
        exit('<h2>403 - Access denied</h2><p>Your role is not allowed to open this page.</p><p><a href="' . e(url('dashboard.php')) . '">Back to dashboard</a></p>');
    }
}

/** Returns null on success, or an error message. */
function attempt_login(string $username, string $password): ?string {
    $now = time();
    $_SESSION['login_fail'] = array_values(array_filter($_SESSION['login_fail'] ?? [], fn($t) => $t > $now - 300));
    if (count($_SESSION['login_fail']) >= 5) return 'Too many failed attempts. Please wait 5 minutes and try again.';

    $stmt = db()->prepare('SELECT * FROM users WHERE username = ?');
    $stmt->execute([$username]);
    $u = $stmt->fetch();
    // Always run password_verify to keep timing similar for unknown usernames.
    $hash = $u['password_hash'] ?? '$2y$10$abcdefghijklmnopqrstuuABCDEFGHIJKLMNOPQRSTUVWXYZ012345';
    $ok = password_verify($password, $hash);
    if (!$u || !$ok) {
        $_SESSION['login_fail'][] = $now;
        log_activity('login_failed', 'auth', 'Failed login for username: ' . mb_substr($username, 0, 50), $u['id'] ?? null);
        return 'Invalid username or password.';
    }
    if (!$u['is_active']) return 'This account has been deactivated. Contact the administrator.';

    session_regenerate_id(true);
    $_SESSION['user'] = ['id' => (int)$u['id'], 'username' => $u['username'], 'full_name' => $u['full_name'], 'role' => $u['role']];
    $_SESSION['login_fail'] = [];
    if (password_needs_rehash($u['password_hash'], PASSWORD_DEFAULT)) {
        db()->prepare('UPDATE users SET password_hash = ? WHERE id = ?')->execute([password_hash($password, PASSWORD_DEFAULT), $u['id']]);
    }
    db()->prepare('UPDATE users SET last_login = NOW() WHERE id = ?')->execute([$u['id']]);
    log_activity('login', 'auth', 'User logged in', (int)$u['id']);
    return null;
}

function logout_user(): void {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}
