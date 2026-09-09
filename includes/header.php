<?php
require_once __DIR__ . '/cookies.php';
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle ?? APP_NAME . ' | Next-Gen Social Platform'); ?></title>

    <!-- Local Vendor Styles -->
    <link rel="stylesheet" href="/assets/vendors/Bootstrap/css/bootstrap.min.css">
    <link rel="stylesheet" href="/assets/vendors/FontAwesome/css/all.min.css">
    <link rel="stylesheet" href="/assets/css/main.css">

    <!-- Custom Self-Hosted Font Definitions -->
    <style>
        @font-face {
            font-family: 'Cinzel';
            src: url('/assets/vendors/GoogleFonts/Cinzel-VariableFont_wght.ttf') format('truetype');
            font-display: swap;
        }

        :root {
            --valkyrin-bg: #0a0d14;
            --valkyrin-surface: #121824;
            --valkyrin-primary: #00f2fe;
            --valkyrin-secondary: #4facfe;
            --valkyrin-accent: #ff2a6d;
            --valkyrin-text: #e0e6ed;
            --valkyrin-font-title: 'Cinzel', serif;
        }

        body {
            background-color: var(--valkyrin-bg);
            color: var(--valkyrin-text);
            font-family: system-ui, -apple-system, sans-serif;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        .font-cinzel {
            font-family: var(--valkyrin-font-title);
        }

        .text-accent {
            color: var(--valkyrin-accent);
        }

        .text-gradient {
            background: linear-gradient(135deg, var(--valkyrin-primary), var(--valkyrin-secondary));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .valkyrin-card {
            background: var(--valkyrin-surface);
            border: 1px solid rgba(0, 242, 254, 0.15);
            border-radius: 12px;
            box-shadow: 0 8px 32px 0 rgba(0, 0, 0, 0.37);
        }
    </style>
</head>
<body>