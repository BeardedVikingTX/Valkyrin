<?php
/**
 * VALKYRIN :: Hardened Database Connector (PDO)
 */

// Replace these placeholders with your actual Namecheap MySQL credentials
define('DB_HOST', 'localhost');
define('DB_NAME', 'beardedviking_valkyrin');
define('DB_USER', 'beardedviking_admin_bvsec');
define('DB_PASS', '{f8m*q5bm*Jg^4ZRM&');
define('DB_CHARSET', 'utf8mb4');

function get_db_connection(): PDO {
    static $pdo = null;

    if ($pdo === null) {
        $dsn = sprintf("mysql:host=%s;dbname=%s;charset=%s", DB_HOST, DB_NAME, DB_CHARSET);
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false, // Disables emulation for true prepared statements
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES " . DB_CHARSET
        ];

        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            // Log error privately and halt execution without exposing credentials
            error_log("VALKYRIN DB ERROR: " . $e->getMessage());
            die(json_encode([
                'status' => 'error',
                'message' => 'Critical database telemetry signal lost. Please contact system administrator.'
            ]));
        }
    }

    return $pdo;
}