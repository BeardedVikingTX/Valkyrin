<?php
/**
 * VALKYRIN :: Core Header Assembly
 */

// Load security session & cookie management first
require_once __DIR__ . '/cookies.php';

// Hardened Security Headers
header("X-Frame-Options: SAMEORIGIN");
header("X-Content-Type-Options: nosniff");
header("X-XSS-Protection: 1; mode=block");
header("Referrer-Policy: strict-origin-when-cross-origin");

// Page Title Dynamic Handler
$pageTitle = isset($pageTitle) ? htmlspecialchars($pageTitle) . " | VALKYRIN" : "VALKYRIN :: Next-Gen Secure Social Node";
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <meta name="csrf-token" content="<?= $_SESSION['csrf_token']; ?>">
    <title><?= $pageTitle; ?></title>

    <!-- Google Fonts: Futuristic Sci-Fi Typography -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@400;600;800;900&family=Rajdhani:wght@400;500;600;700&family=Share+Tech+Mono&display=swap" rel="stylesheet">

    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">

    <!-- Font-Awesome Pro / Free CDN -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" integrity="sha512-DTOQO9RWCH3ppGqcWaEA1BIZOC6xxalwEsw9c2QQeAIftl+Vegovlnee1c9QX4TctnWMn13TZye+giMm8e2LwA==" crossorigin="anonymous" referrerpolicy="no-referrer" />

    <!-- Valkyrin Custom Sci-Fi Theme CSS -->
    <link rel="stylesheet" href="/assets/css/main.css?v=<?= filemtime(__DIR__ . '/../assets/css/main.css') ?? time(); ?>">
</head>
<body class="valkyrin-telemetry-body">

    <!-- Dynamic Navigation Inclusion -->
    <?php 
    if (file_exists(__DIR__ . '/nav.php')) {
        include_once __DIR__ . '/nav.php';
    } 
    ?>

    <!-- Main Viewport Node -->
    <main class="valkyrin-viewport container-fluid py-4">