<?php
if (!defined('VALKYRIN_EXEC')) {
    header("HTTP/1.1 403 Forbidden");
    exit("Access Denied");
}

$isLoggedIn = isset($_SESSION['user_id']);

$unreadCount = 0;
$latestNotifications = [];

if (isset($_SESSION['user_id']) && isset($pdo)) {
    $navUserId = (int)$_SESSION['user_id'];
    
    // Count unread
    $stmtCount = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = :uid AND is_read = 0");
    $stmtCount->execute([':uid' => $navUserId]);
    $unreadCount = (int)$stmtCount->fetchColumn();

    // Fetch top 5 recent notifications
    $stmtNavNotifs = $pdo->prepare("
        SELECT n.*, u.username, u.display_name, u.avatar 
        FROM notifications n 
        JOIN users u ON n.actor_id = u.id 
        WHERE n.user_id = :uid 
        ORDER BY n.created_at DESC LIMIT 5
    ");
    $stmtNavNotifs->execute([':uid' => $navUserId]);
    $latestNotifications = $stmtNavNotifs->fetchAll(PDO::FETCH_ASSOC);
}
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
                <div class="dropdown me-3" id="notifDropdownContainer">
                    <button class="btn btn-dark border-secondary rounded-circle position-relative p-2" type="button" data-bs-toggle="dropdown" id="notifBellBtn" aria-expanded="false">
                        <i class="fa-solid fa-bell text-info"></i>
                        <?php if ($unreadCount > 0): ?>
                            <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" id="nav-notif-badge">
                                <?= $unreadCount > 99 ? '99+' : $unreadCount; ?>
                            </span>
                        <?php endif; ?>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end bg-dark border-secondary text-light p-2 shadow-lg" style="width: 320px; max-height: 400px; overflow-y: auto;">
                        <li class="d-flex justify-content-between align-items-center mb-2 px-2 pb-2 border-bottom border-secondary">
                            <span class="fw-bold text-info font-cinzel small"><i class="fa-solid fa-satellite-dish me-1"></i> TELEMETRY LOGS</span>
                            <a href="/notifications.php" class="extra-small text-muted text-decoration-none">View All</a>
                        </li>
        
                        <?php if (empty($latestNotifications)): ?>
                            <li class="text-center py-3 text-muted small">No recent telemetry signals.</li>
                        <?php else: ?>
                            <?php foreach ($latestNotifications as $notif): ?>
                                <li class="mb-1">
                                    <a href="/notifications.php" class="dropdown-item rounded p-2 text-wrap <?= $notif['is_read'] ? 'bg-dark' : 'bg-secondary bg-opacity-20'; ?> text-light border-bottom border-secondary">
                                        <div class="d-flex align-items-center gap-2">
                                            <i class="fa-solid fa-circle text-info" style="font-size: 6px;"></i>
                                            <div class="small"><?= htmlspecialchars($notif['message']); ?></div>
                                        </div>
                                        <div class="extra-small text-muted text-end mt-1"><?= date('M j, H:i', strtotime($notif['created_at'])); ?></div>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </ul>
                </div>                
                    <a href="/users/dashboard.php" class="btn btn-outline-info btn-sm rounded-pill px-3">
                        <i class="fa-solid fa-right-from-bracket"></i> Dashboard
                    </a>
                    <a href="/nodes.php" class="btn btn-outline-warning btn-sm rounded-pill px-3">
                        <i class="fa-solid fa-users"></i> Users
                    </a>
                    <a href="/users/messages.php" class="btn btn-outline-primary btn-sm rounded-pill px-3">
                        <i class="fa-solid fa-message"></i> Messages
                    </a>
                    <a href="/users/settings.php" class="btn btn-outline-success btn-sm rounded-pill px-3">
                        <i class="fa-solid fa-user-gear"></i> Settings
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

<script>
document.addEventListener('DOMContentLoaded', function() {
    const dropdownEl = document.getElementById('notifDropdownContainer');
    if (dropdownEl) {
        dropdownEl.addEventListener('show.bs.dropdown', function () {
            const badge = document.getElementById('nav-notif-badge');
            if (badge) {
                // Clear badge visually immediately
                badge.remove();
                // Send background request to mark read in DB
                fetch('/mark_notifications_read.php', { method: 'POST' });
            }
        });
    }
});
</script>