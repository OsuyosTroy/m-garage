<?php
require_once __DIR__ . '/config.php';

/** Returns a shared PDO connection (prepared statements, exceptions on error). */
function db(): PDO {
    static $pdo = null;
    if ($pdo instanceof PDO) return $pdo;
    try {
        $pdo = new PDO(
            'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
            DB_USER, DB_PASS,
            [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]
        );
        $pdo->exec("SET time_zone = '+08:00'");
    } catch (PDOException $e) {
        http_response_code(500);
        $detail = APP_DEBUG ? htmlspecialchars($e->getMessage()) : 'Please contact the administrator.';
        exit('<h2>Database connection failed</h2><p>Make sure MySQL is running in XAMPP and that '
           . '<code>m_garage.sql</code> has been imported.</p><p><small>' . $detail . '</small></p>');
    }
    return $pdo;
}
