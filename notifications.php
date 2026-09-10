<?php
define('VALKYRIN_EXEC', true);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    header("Location: /login.php");
    exit();
}

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/nav.php';

$userId = (int)$_SESSION['user_id'];
$notifications = [];

if (isset($pdo)) {
    // Mark notifications as read upon landing on the page
    $markRead = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?");
    $markRead->execute([$userId]);

    // Fetch notifications joined with actor details
    $stmt = $pdo->prepare("
        SELECT n.*, u.username, u.display_name, u.avatar 
        FROM notifications n 
        JOIN users u ON n.actor_id = u.id 
        WHERE n.user_id = ? 
        ORDER BY n.created_at DESC
    ");
    $stmt->execute([$userId]);
    $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>

<header class="py-4 bg-dark border-bottom border-secondary">
    <div class="container d-flex align-items-center justify-content-between">
        <div>
            <span class="badge bg-info text-dark px-3 py-1 rounded-pill mb-2">
                <i class="fa-solid fa-satellite-dish me-1"></i>TELEMETRY CENTER
            </span>
            <h1 class="font-cinzel display-6 fw-bold text-white mb-0">NETWORK NOTIFICATIONS</h1>
        </div>
        <a href="/nodes.php" class="btn btn-outline-info btn-sm rounded-pill px-3">
            <i class="fa-solid fa-users me-1"></i>View Nodes
        </a>
    </div>
</header>

<main class="py-5">
    <div class="container" style="max-width: 850px;">
        <div class="vk-card border border-secondary rounded p-4 bg-dark shadow-lg">
            <?php if (empty($notifications)): ?>
                <div class="text-center py-5 text-muted">
                    <i class="fa-solid fa-bell-slash fa-3x mb-3 text-secondary"></i>
                    <h5 class="font-cinzel text-light">No Telemetry Signals</h5>
                    <p class="small mb-0">You have no network notifications at this time.</p>
                </div>
            <?php else: ?>
                <div class="d-flex flex-column gap-3">
                    <?php foreach ($notifications as $notif): ?>
                        <?php 
                            $actorName = !empty($notif['display_name']) ? $notif['display_name'] : $notif['username'];
                            
                            // Avatar Fallback Logic
                            if (!empty($notif['avatar'])) {
                                $avatarUrl = (strpos($notif['avatar'], '/') === 0 || strpos($notif['avatar'], 'uploads/') === 0) 
                                    ? $notif['avatar'] 
                                    : '/uploads/profiles/' . $notif['avatar'];
                            } else {
                                $avatarUrl = '/uploads/profiles/default_avatar.png';
                            }

                            $entityId = (int)($notif['entity_id'] ?? 0);
                            $type = strtolower($notif['type']);

                            // Type-specific Visual Badges
                            $typeBadge = '<span class="badge bg-secondary text-dark"><i class="fa-solid fa-bell me-1"></i>Signal</span>';
                            if (in_array($type, ['post_comment', 'comment_added'])) {
                                $typeBadge = '<span class="badge bg-warning text-dark"><i class="fa-solid fa-comment me-1"></i>Comment</span>';
                            } elseif (in_array($type, ['post_reaction', 'like'])) {
                                $typeBadge = '<span class="badge bg-danger text-white"><i class="fa-solid fa-heart me-1"></i>Reaction</span>';
                            } elseif (in_array($type, ['new_message', 'message', 'direct_message', 'group_message', 'dm'])) {
                                $typeBadge = '<span class="badge bg-info text-dark"><i class="fa-solid fa-paper-plane me-1"></i>Transmission</span>';
                            } elseif ($type === 'connection_request') {
                                $typeBadge = '<span class="badge bg-primary text-white"><i class="fa-solid fa-user-plus me-1"></i>Node Link</span>';
                            }
                        ?>
                        <div class="p-3 bg-dark border border-secondary rounded d-flex align-items-center justify-content-between gap-3 vk-notif-item">
                            <div class="d-flex align-items-center gap-3">
                                <a href="/user.php?id=<?= $notif['actor_id']; ?>" title="View Profile">
                                    <img src="<?= htmlspecialchars($avatarUrl); ?>" 
                                         alt="Avatar" 
                                         class="rounded-circle border border-info" 
                                         style="width: 48px; height: 48px; object-fit: cover;"
                                         onerror="this.onerror=null; this.src='/uploads/profiles/default_avatar.png';">
                                </a>
                                <div>
                                    <div class="mb-1">
                                        <?= $typeBadge; ?>
                                        <span class="text-light ms-1 fw-semibold"><?= htmlspecialchars($notif['message']); ?></span>
                                    </div>
                                    <small class="text-muted font-monospace">
                                        <i class="fa-regular fa-clock me-1"></i><?= date('M j, Y - H:i', strtotime($notif['created_at'])); ?>
                                    </small>
                                </div>
                            </div>

                            <!-- Contextual Action Routing -->
                            <div class="d-flex align-items-center gap-2 notif-action-wrapper">
                                <?php if ($type === 'connection_request'): ?>
                                    <button class="btn btn-sm btn-success notif-action-btn" data-actor-id="<?= $notif['actor_id']; ?>" data-action="accept">
                                        <i class="fa-solid fa-check me-1"></i>Accept
                                    </button>
                                    <button class="btn btn-sm btn-outline-danger notif-action-btn" data-actor-id="<?= $notif['actor_id']; ?>" data-action="sever">
                                        <i class="fa-solid fa-xmark me-1"></i>Reject
                                    </button>

                                <?php elseif (in_array($type, ['post_comment', 'comment_added', 'post_reaction', 'like', 'network_post'])): ?>
                                    <a href="/feed.php#post-<?= $entityId; ?>" class="btn btn-sm btn-outline-info rounded-pill px-3">
                                        <i class="fa-solid fa-arrow-right-to-bracket me-1"></i>View Signal
                                    </a>

                                <?php elseif (in_array($type, ['new_message', 'message', 'direct_message', 'group_message', 'dm'])): ?>
                                    <a href="/users/messages.php?conversation_id=<?= $entityId; ?>" class="btn btn-sm btn-outline-info rounded-pill px-3">
                                        <i class="fa-solid fa-comments me-1"></i>Open Transmission
                                    </a>

                                <?php elseif ($type === 'article_published'): ?>
                                    <a href="/article.php?id=<?= $entityId; ?>" class="btn btn-sm btn-outline-info rounded-pill px-3">
                                        <i class="fa-solid fa-newspaper me-1"></i>Read Article
                                    </a>

                                <?php else: ?>
                                    <a href="/user.php?id=<?= $notif['actor_id']; ?>" class="btn btn-sm btn-outline-secondary rounded-pill px-3">
                                        <i class="fa-solid fa-user me-1"></i>View Profile
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</main>

<script>
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.notif-action-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const targetId = this.dataset.actorId;
            const action = this.dataset.action; // 'accept' or 'sever'
            const actionWrapper = this.closest('.notif-action-wrapper');
            const card = this.closest('.vk-notif-item');

            // Disable buttons during request
            actionWrapper.querySelectorAll('button').forEach(b => b.disabled = true);

            fetch('/connections_action.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: `target_id=${encodeURIComponent(targetId)}&action=${encodeURIComponent(action)}`
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    card.classList.add('opacity-75');
                    
                    if (action === 'accept') {
                        actionWrapper.innerHTML = `
                            <span class="badge bg-success text-dark px-3 py-2 rounded-pill font-monospace">
                                <i class="fa-solid fa-link me-1"></i>Accepted
                            </span>`;
                    } else {
                        actionWrapper.innerHTML = `
                            <span class="badge bg-danger text-white px-3 py-2 rounded-pill font-monospace">
                                <i class="fa-solid fa-user-xmark me-1"></i>Rejected
                            </span>`;
                    }
                } else {
                    alert(data.error || 'Action failed.');
                    actionWrapper.querySelectorAll('button').forEach(b => b.disabled = false);
                }
            })
            .catch(err => {
                alert('Server error: ' + err.message);
                actionWrapper.querySelectorAll('button').forEach(b => b.disabled = false);
            });
        });
    });
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>