<?php
if (!defined('VALKYRIN_EXEC')) {
    header("HTTP/1.1 403 Forbidden");
    exit("Access Denied");
}

require_once __DIR__ . '/database.php';

// Secure Session Initialization
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.cookie_use_only_cookies', 1);
    ini_set('session.cookie_samesite', 'Lax');
    
    if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
        ini_set('session.cookie_secure', 1);
    }
    
    session_start();
}

// Log traffic directly to Remote Database (Near-ZK method)
if (isset($pdo)) {
    logTelemetry($pdo);
}