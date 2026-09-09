<?php
if (!defined('VALKYRIN_EXEC')) {
    header("HTTP/1.1 403 Forbidden");
    exit("Access Denied");
}

$isLoggedIn = isset($_SESSION['user_id']);
?>
<nav class="navbar navbar-expand-lg vk-navbar sticky-top">
    <div class="container">
        <a class="navbar-brand font-cinzel text-gradient fw-bold fs-4" href="/index.php">
            <i class="fa-solid fa-shield-halved me-2 text-accent"></i>VALKYRIN
        </a>
        <button class="navbar-toggler border-0 text-light" type="button" data-bs-toggle="collapse" data-bs-target="#vkNav">
            <i class="fa-solid fa-bars fs-3"></i>
        </button>
        <div class="collapse navbar-collapse" id="vkNav">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0 ms-lg-4">
                <li class="nav-item"><a class="nav-link vk-nav-link" href="/index.php">Terminal</a></li>
                <li class="nav-item"><a class="nav-link vk-nav-link" href="/about.php">Mission & Race</a></li>
                <li class="nav-item"><a class="nav-link vk-nav-link" href="/contact.php">Contact HQ</a></li>
            </ul>
            <div class="d-flex align-items-center gap-2">
                <?php if ($isLoggedIn): ?>
                    <a href="/users/dashboard.php" class="btn btn-outline-info btn-sm rounded-pill px-3">
                        <i class="fa-solid fa-gauge-high me-1"></i> Dashboard
                    </a>
                    <a href="/logout.php" class="btn btn-danger btn-sm rounded-pill px-3">Logout</a>
                <?php else: ?>
                    <a href="/login.php" class="btn btn-link text-decoration-none text-light btn-sm">Login</a>
                    <a href="/register.php" class="btn btn-info btn-sm fw-bold px-3 rounded-pill text-dark">
                        Join Network
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</nav>