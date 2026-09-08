<?php
/**
 * VALKYRIN :: Dynamic Navigation Module
 */
$currentPage = basename($_SERVER['PHP_SELF']);
$isLoggedIn  = isset($_SESSION['user_id']);
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
            <ul class="navbar-nav ms-auto align-items-lg-center gap-lg-2 mt-3 mt-lg-0">
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
                
                <!-- Dynamic Authentication State Group -->
                <?php if ($isLoggedIn): ?>
                    <?php 
                        $navUserSlug   = $_SESSION['user_slug'] ?? 'default';
                        $navAvatarFile = $_SESSION['user_avatar'] ?? 'default.png';
                        $navAvatarPath = "/users/images/avatars/" . htmlspecialchars($navUserSlug) . "/" . htmlspecialchars($navAvatarFile);
                        $navDefault    = "https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/icons/person-circle.svg";
                    ?>
                    <li class="nav-item dropdown ms-lg-3 mt-2 mt-lg-0">
                        <a class="btn vk-btn-outline dropdown-toggle d-flex align-items-center gap-2 w-100" href="#" id="userNodeDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <img src="<?= $navAvatarPath; ?>" 
                                 onerror="this.onerror=null; this.src='<?= $navDefault; ?>';" 
                                 alt="Avatar" 
                                 class="rounded-circle border border-info" 
                                 style="width: 24px; height: 24px; object-fit: cover;">
                            <span class="font-monospace text-info"><?= htmlspecialchars($_SESSION['username']); ?></span>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end dropdown-menu-dark bg-dark border-info shadow-lg mt-2" aria-labelledby="userNodeDropdown">
                            <li class="dropdown-header font-monospace text-warning small border-bottom border-secondary mb-1">
                                <i class="fa-solid fa-shield me-1"></i> TIER: <?= strtoupper(htmlspecialchars($_SESSION['user_role'] ?? 'Shieldman')); ?>
                            </li>
                            <li>
                                <a class="dropdown-item text-white <?= ($currentPage === 'dashboard.php') ? 'active bg-secondary' : ''; ?>" href="/users/dashboard.php">
                                    <i class="fa-solid fa-gauge-high text-info me-2"></i>Command Dashboard
                                </a>
                            </li>
                            <li><hr class="dropdown-divider border-secondary"></li>
                            <li>
                                <a class="dropdown-item text-danger" href="/logout.php">
                                    <i class="fa-solid fa-power-off me-2"></i>Terminate Session
                                </a>
                            </li>
                        </ul>
                    </li>
                <?php else: ?>
                    <li class="nav-item ms-lg-3 d-flex align-items-center gap-2 mt-2 mt-lg-0">
                        <a href="/login.php" class="btn vk-btn-outline w-100 w-lg-auto">
                            <i class="fa-solid fa-right-to-bracket me-1"></i> LOGIN
                        </a>
                        <a href="/register.php" class="btn vk-btn-glow w-100 w-lg-auto">
                            <i class="fa-solid fa-user-plus me-1"></i> REGISTER
                        </a>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>