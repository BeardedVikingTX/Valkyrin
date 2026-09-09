<?php
// Prevent Direct Access
if (!defined('VALKYRIN_EXEC')) {
    define('VALKYRIN_EXEC', true);
}

// Security Headers (Hardened Baseline)
header("X-Frame-Options: DENY");
header("X-Content-Type-Options: nosniff");
header("X-XSS-Protection: 1; mode=block");
header("Referrer-Policy: strict-origin-when-cross-origin");

// Simple Native .env Loader
if (!function_exists('loadEnv')) {
    function loadEnv($filePath) {
        if (!file_exists($filePath)) {
            return false;
        }
        $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            if (strpos(trim($line), '#') === 0) continue;
            list($name, $value) = explode('=', $line, 2);
            $_ENV[trim($name)] = trim($value);
        }
        return true;
    }
}

loadEnv(__DIR__ . '/../.env');

// Core App Definitions
define('APP_NAME', 'VALKYRIN');
define('APP_SALT', $_ENV['APP_SALT'] ?? 'Default_Valkyrin_Fallback_Salt_2026');