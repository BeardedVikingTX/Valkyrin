<?php
define('VALKYRIN_EXEC', true);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    header("Location: /login.php?error=" . urlencode("Authentication required to manage node links."));
    exit();
}

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/nav.php';

$userId = $_SESSION['user_id'];
$message = $_GET['msg'] ?? '';
$error = $_GET['err'] ?? '';

// Helper function for reputation ranks
function getNodeBadge($rep) {
    $rep = (int)$rep;
    if ($rep >= 5000) return ['title' => 'VALKYRIE PRIME', 'class' => 'bg-danger text-light', 'icon' => 'fa-crown'];
    if ($rep >= 2000) return ['title' => 'COMMANDER', 'class' => 'bg-warning text-dark', 'icon' => 'fa-shield-halved'];
    if ($rep >= 750)  return ['title' => 'SHIELDBEARER', 'class' => 'bg-accent text-dark', 'icon' => 'fa-shield'];
    if ($rep >= 200)  return ['title' => 'BERSERKER', 'class' => 'bg-info text-dark', 'icon' => 'fa-bolt'];
    return ['title' => 'INITIATE', 'class' => 'bg-secondary text-light', 'icon' => 'fa-seedling'];
}

// 1. Pending Incoming Requests (Nodes wanting to connect with CURRENT user)
$pendingRequests = [];
// 2. Mutual Active Connections (Users connected bi-directionally)
$mutualConnections = [];
// 3. Sent Pending Requests (Requests waiting for approval)
$sentRequests = [];

if (isset($pdo)) {
    try {
        // Pending Incoming
        $stmtPending = $pdo->prepare("
            SELECT c.id AS connection_id, c.created_at, u.id AS user_id, u.username, u.display_name, u.avatar, u.reputation_points
            FROM connections c
            JOIN users u ON c.requester_id = u.id
            WHERE c.addressee_id = :user_id AND c.status = 'pending'
            ORDER BY c.created_at DESC
        ");
        $stmtPending->execute([':user_id' => $userId]);
        $pendingRequests = $stmtPending->fetchAll(PDO::FETCH_ASSOC);

        // Mutual Active Connections
        $stmtMutual = $pdo->prepare("
            SELECT DISTINCT u.id AS user_id, u.username, u.display_name, u.avatar, u.reputation_points
            FROM connections c1
            JOIN connections c2 ON c1.addressee_id = c2.requester_id AND c1.requester_id = c2.addressee_id
            JOIN users u ON c1.addressee_id = u.id
            WHERE c1.requester_id = :user_id 
              AND c1.status = 'accepted' 
              AND c2.status = 'accepted'
        ");
        $stmtMutual->execute([':user_id' => $userId]);
        $mutualConnections = $stmtMutual->fetchAll(PDO::FETCH_ASSOC);

        // Sent Pending Requests
        $stmtSent = $pdo->prepare("
            SELECT c.id AS connection_id, c.created_at, u.id AS user_id, u.username, u.display_name, u.avatar, u.reputation_points
            FROM connections c
            JOIN users u ON c.addressee_id = u.id
            WHERE c.requester_id = :user_id AND c.status = 'pending'
            ORDER BY c.created_at DESC
        ");
        $stmtSent->execute([':user_id' => $userId]);
        $sentRequests = $stmtSent->fetchAll(PDO::FETCH_ASSOC);

    } catch (PDOException $e) {
        $error = "Database exception: Unable to fetch connection topology.";
    }
}
?>

<!-- Header hero -->
<header class="py-4 bg-dark border-bottom border-secondary">
    <div class="container">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
            <div>
                <span class="badge bg-info text-dark px-3 py-1 rounded-pill mb-2">
                    <i class="fa-solid fa-link me-1"></i>TOPOLOGY CONTROL
                </span>
                <h1 class="font-cinzel display-6 fw-bold text-white mb-0">NETWORK CONNECTIONS</h1>
            </div>
            <div>
                <a href="/search.php" class="btn btn-outline-info rounded-pill px-4 fw-bold">
                    <i class="fa-solid fa-magnifying-glass me-2"></i>Discover Nodes
                </a>
            </div>
        </div>
    </div>
</header>

<main class="py-5">
    <div class="container" style="max-width: 900px;">

        <?php if (!empty($message)): ?>
            <div class="alert alert-success bg-success bg-opacity-20 border-0 text-light mb-4">
                <i class="fa-solid fa-circle-check me-2"></i><?= htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($error)): ?>
            <div class="alert alert-danger bg-danger bg-opacity-20 border-0 text-light mb-4">
                <i class="fa-solid fa-triangle-exclamation me-2"></i><?= htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <!-- Incoming Connection Requests -->
        <section class="mb-5">
            <h4 class="font-cinzel text-light border-bottom border-secondary pb-2 mb-3">
                <i class="fa-solid fa-satellite-dish me-2 text-info"></i>Pending Signal Approvals
                <span class="badge bg-info text-dark rounded-pill extra-small ms-2"><?= count($pendingRequests); ?></span>
            </h4>

            <?php if (empty($pendingRequests)): ?>
                <div class="vk-card p-4 text-center">
                    <p class="text-muted-custom mb-0 small">No pending request transmissions targeting your frequency.</p>
                </div>
            <?php else: ?>
                <div class="row g-3">
                    <?php foreach ($pendingRequests as $req): ?>
                        <?php 
                            $badge = getNodeBadge($req['reputation_points']);
                            $avatarPath = (!empty($req['avatar']) && file_exists(__DIR__ . '/uploads/profiles/' . $req['avatar']))
                                ? '/uploads/profiles/' . $req['avatar']
                                : '/assets/images/default_avatar.png';
                        ?>
                        <div class="col-md-6">
                            <div class="vk-card p-3 d-flex align-items-center justify-content-between">
                                <div class="d-flex align-items-center gap-3">
                                    <img src="<?= htmlspecialchars($avatarPath); ?>" class="rounded-circle border border-info" width="48" height="48" style="object-fit:cover;" alt="Avatar">
                                    <div>
                                        <h6 class="font-cinzel text-light mb-0 fw-bold"><?= htmlspecialchars($req['display_name'] ?: $req['username']); ?></h6>
                                        <small class="text-muted-custom extra-small">@<?= htmlspecialchars($req['username']); ?></small>
                                        <div class="mt-1">
                                            <span class="badge <?= $badge['class']; ?> extra-small rounded-pill">
                                                <i class="fa-solid <?= $badge['icon']; ?> me-1"></i><?= $badge['title']; ?>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                                <div class="d-flex gap-2">
                                    <form action="/connections/process.php" method="POST">
                                        <input type="hidden" name="connection_id" value="<?= $req['connection_id']; ?>">
                                        <input type="hidden" name="action" value="accept">
                                        <button type="submit" class="btn btn-sm btn-info rounded-circle" title="Accept Connection">
                                            <i class="fa-solid fa-check text-dark"></i>
                                        </button>
                                    </form>
                                    <form action="/connections/process.php" method="POST">
                                        <input type="hidden" name="connection_id" value="<?= $req['connection_id']; ?>">
                                        <input type="hidden" name="action" value="reject">
                                        <button type="submit" class="btn btn-sm btn-outline-danger rounded-circle" title="Reject Transmission">
                                            <i class="fa-solid fa-xmark"></i>
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>

        <!-- Mutual Network Connections -->
        <section class="mb-5">
            <h4 class="font-cinzel text-light border-bottom border-secondary pb-2 mb-3">
                <i class="fa-solid fa-network-wired me-2 text-info"></i>Active Mutual Nodes
                <span class="badge bg-secondary text-light rounded-pill extra-small ms-2"><?= count($mutualConnections); ?></span>
            </h4>

            <?php if (empty($mutualConnections)): ?>
                <div class="vk-card p-4 text-center">
                    <p class="text-muted-custom mb-0 small">No active mutual connections. Connect with users to build your feed pipeline.</p>
                </div>
            <?php else: ?>
                <div class="row g-3">
                    <?php foreach ($mutualConnections as $conn): ?>
                        <?php 
                            $badge = getNodeBadge($conn['reputation_points']);
                            $avatarPath = (!empty($conn['avatar']) && file_exists(__DIR__ . '/uploads/profiles/' . $conn['avatar']))
                                ? '/uploads/profiles/' . $conn['avatar']
                                : '/assets/images/default_avatar.png';
                        ?>
                        <div class="col-md-6 col-lg-4">
                            <div class="vk-card p-3 text-center">
                                <img src="<?= htmlspecialchars($avatarPath); ?>" class="rounded-circle border border-info mb-2" width="60" height="60" style="object-fit:cover;" alt="Avatar">
                                <h6 class="font-cinzel text-light mb-0 fw-bold"><?= htmlspecialchars($conn['display_name'] ?: $conn['username']); ?></h6>
                                <p class="text-muted-custom extra-small mb-2">@<?= htmlspecialchars($conn['username']); ?></p>
                                <span class="badge <?= $badge['class']; ?> extra-small rounded-pill mb-3">
                                    <i class="fa-solid <?= $badge['icon']; ?> me-1"></i><?= $badge['title']; ?>
                                </span>
                                <div>
                                    <a href="/profile.php?username=<?= urlencode($conn['username']); ?>" class="btn btn-sm btn-outline-info rounded-pill px-3 w-100">
                                        <i class="fa-solid fa-user me-1"></i>Inspect Node
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>

        <!-- Sent Requests -->
        <section>
            <h4 class="font-cinzel text-light border-bottom border-secondary pb-2 mb-3">
                <i class="fa-solid fa-paper-plane me-2 text-info"></i>Sent Signal Requests
            </h4>

            <?php if (empty($sentRequests)): ?>
                <div class="vk-card p-4 text-center">
                    <p class="text-muted-custom mb-0 small">You have no outbound pending requests.</p>
                </div>
            <?php else: ?>
                <div class="row g-3">
                    <?php foreach ($sentRequests as $sent): ?>
                        <?php 
                            $avatarPath = (!empty($sent['avatar']) && file_exists(__DIR__ . '/uploads/profiles/' . $sent['avatar']))
                                ? '/uploads/profiles/' . $sent['avatar']
                                : '/assets/images/default_avatar.png';
                        ?>
                        <div class="col-md-6">
                            <div class="vk-card p-3 d-flex align-items-center justify-content-between">
                                <div class="d-flex align-items-center gap-3">
                                    <img src="<?= htmlspecialchars($avatarPath); ?>" class="rounded-circle border border-secondary" width="40" height="40" style="object-fit:cover;" alt="Avatar">
                                    <div>
                                        <h6 class="font-cinzel text-light mb-0 fw-bold"><?= htmlspecialchars($sent['display_name'] ?: $sent['username']); ?></h6>
                                        <small class="text-muted-custom extra-small">Awaiting acceptance...</small>
                                    </div>
                                </div>
                                <div>
                                    <span class="badge bg-dark border border-warning text-warning extra-small">
                                        <i class="fa-solid fa-clock me-1"></i>Pending
                                    </span>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>

    </div>
</main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>