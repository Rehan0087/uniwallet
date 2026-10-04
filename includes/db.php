<?php
/**
 * PDO connection. Opened lazily and reused for the rest of the request.
 */

require_once __DIR__ . '/../config/config.php';

function db(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        $dsn = sprintf('mysql:host=%s;dbname=%s;charset=%s', DB_HOST, DB_NAME, DB_CHARSET);
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                // Turn SQL errors into exceptions instead of silent false returns.
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                // Real prepared statements, not client-side string interpolation.
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        } catch (PDOException $e) {
            // Friendly 500 page; the technical message only shows when APP_DEBUG is on.
            error_log('UniWallet DB connection failed: ' . $e->getMessage());
            require_once __DIR__ . '/error_page.php';
            show_error_page(500, $e);
        }
    }

    return $pdo;
}
