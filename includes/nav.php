<?php
/**
 * VALKYRIN :: Dynamic Navigation Module
 */
$currentPage = basename($_SERVER['PHP_SELF']);
?>
<nav class="navbar navbar-expand-lg vk-navbar sticky-top">
    <div class="container-fluid px-4">
        <!-- Site Brand / Home Link -->
        <a class="navbar-brand vk-brand d-flex align-items-center" href="/index.php">
            <i class="fa-solid fa-shield-halved vk-brand-icon me-2"></i>
            <span class="vk-brand-text">VALKYRIN</span>
            <span class="vk-status-badge ms-3 d-none d-sm-inline-block">
                <span class="vk-pulse-dot"></span> NODE ONLINE
            </span>
        </a>

        <!-- Mobile Toggle Button -->
        <button class="navbar-toggler vk-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#vkNavbarContent" aria-controls="vkNavbarContent" aria-expanded="false" aria-label="Toggle Navigation">
            <i class="fa-solid fa-bars-staggered"></i>
        </button>

        <!-- Dynamic Navigation Links -->
        <div class="collapse navbar-collapse" id="vkNavbarContent">
            <ul class="navbar-collapse navbar-nav ms-auto align-items-lg-center gap-lg-2 mt-3 mt-lg-0">
                <li class="nav-item">
                    <a class="nav-link vk-nav-link <?= ($currentPage === 'index.php') ? 'active' : ''; ?>" href="/index.php">
                        <i class="fa-solid fa-house-signal me-1"></i> HOME
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link vk-nav-link <?= ($currentPage === 'about.php') ? 'active' : ''; ?>" href="/about.php">
                        <i class="fa-solid fa-circle-info me-1"></i> ABOUT
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link vk-nav-link <?= ($currentPage === 'contact.php') ? 'active' : ''; ?>" href="/contact.php">
                        <i class="fa-solid fa-satellite-dish me-1"></i> CONTACT
                    </a>
                </li>
                
                <!-- Authentication Node Group -->
                <li class="nav-item ms-lg-3 d-flex align-items-center gap-2 mt-2 mt-lg-0">
                    <a href="/login.php" class="btn vk-btn-outline w-100 w-lg-auto">
                        <i class="fa-solid fa-right-to-bracket me-1"></i> LOGIN
                    </a>
                    <a href="/register.php" class="btn vk-btn-glow w-100 w-lg-auto">
                        <i class="fa-solid fa-user-plus me-1"></i> REGISTER
                    </a>
                </li>
            </ul>
        </div>
    </div>
</nav>