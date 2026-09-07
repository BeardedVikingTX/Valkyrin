<?php
/**
 * VALKYRIN :: Session & Cookie Initialization Module
 * Hardened for Shared Hosting Protocols
 */

// Force secure session cookie attributes before starting session
if (session_status() === PHP_SESSION_NONE) {
    $cookieParams = [
        'lifetime' => 86400, // 24 Hours
        'path'     => '/',
        'domain'   => $_SERVER['HTTP_HOST'] ?? '',
        'secure'   => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
        'httponly' => true,
        'samesite' => 'Strict'
    ];

    if (PHP_VERSION_ID >= 70300) {
        session_set_cookie_params($cookieParams);
    } else {
        session_set_cookie_params(
            $cookieParams['lifetime'],
            $cookieParams['path'],
            $cookieParams['domain'],
            $cookieParams['secure'],
            $cookieParams['httponly']
        );
    }

    session_start();
}

// Session Anti-Fixation Protection
if (!isset($_SESSION['vk_created'])) {
    session_regenerate_id(true);
    $_SESSION['vk_created'] = time();
} elseif (time() - $_SESSION['vk_created'] > 1800) {
    // Regenerate ID every 30 minutes
    session_regenerate_id(true);
    $_SESSION['vk_created'] = time();
}

// Generate CSRF Token for Dynamic Fetch Request Validation
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Helper function to set custom encrypted tracking cookies
function set_valkyrin_cookie(string $name, string $value, int $days = 30): bool {
    $expires = time() + (86400 * $days);
    $secure  = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on';
    
    return setcookie($name, $value, [
        'expires'  => $expires,
        'path'     => '/',
        'domain'   => $_SERVER['HTTP_HOST'] ?? '',
        'secure'   => $secure,
        'httponly' => true,
        'samesite' => 'Strict'
    ]);
}
?>