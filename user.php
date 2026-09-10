<?php
define('VALKYRIN_EXEC', true);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    header("Location: /login.php?error=" . urlencode("Authentication required. Please log in to view network nodes."));
    exit();
}

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/nav.php';

$currentUserId = (int)$_SESSION['user_id'];
$targetUserId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$targetUsername = isset($_GET['u']) ? trim($_GET['u']) : '';

// 1. Fetch Target Profile
$profileUser = null;
if (isset($pdo)) {
    try {
        if ($targetUserId > 0) {
            $stmt = $pdo->prepare("SELECT * FROM users WHERE id = :id LIMIT 1");
            $stmt->execute([':id' => $targetUserId]);
            $profileUser = $stmt->fetch(PDO::FETCH_ASSOC);
        } elseif (!empty($targetUsername)) {
            $stmt = $pdo->prepare("SELECT * FROM users WHERE username = :u LIMIT 1");
            $stmt->execute([':u' => $targetUsername]);
            $profileUser = $stmt->fetch(PDO::FETCH_ASSOC);
        }
    } catch (PDOException $e) {
        // Safe fallback
    }
}

// Redirect if profile doesn't exist
if (!$profileUser) {
    echo '<div class="container py-5 text-center"><div class="alert alert-danger bg-danger bg-opacity-20 border-0 text-light"><i class="fa-solid fa-triangle-exclamation me-2"></i>Node Signal Lost: Requested profile does not exist on VALKYRIN.</div><a href="/nodes.php" class="btn btn-info rounded-pill px-4 fw-bold text-dark mt-3">Return to Node Directory</a></div>';
    require_once __DIR__ . '/includes/footer.php';
    exit();
}

$profileId = (int)$profileUser['id'];
$isSelf = ($profileId === $currentUserId);

if ($isSelf) {
    header("Location: /users/dashboard.php");
    exit();
}

// 2. Determine Connection Status
$connectionStatus = 'none'; 
if (isset($pdo)) {
    try {
        $connStmt = $pdo->prepare("
            SELECT requester_id, addressee_id, status 
            FROM connections 
            WHERE (requester_id = :c1 AND addressee_id = :p1) 
               OR (requester_id = :p2 AND addressee_id = :c2)
            LIMIT 1
        ");
        $connStmt->execute([
            ':c1' => $currentUserId, ':p1' => $profileId,
            ':p2' => $profileId,     ':c2' => $currentUserId
        ]);
        $connRecord = $connStmt->fetch(PDO::FETCH_ASSOC);

        if ($connRecord) {
            if ($connRecord['status'] === 'accepted') {
                $connectionStatus = 'accepted';
            } elseif ($connRecord['status'] === 'pending') {
                $connectionStatus = ($connRecord['requester_id'] === $currentUserId) ? 'pending_sent' : 'pending_received';
            }
        }
    } catch (PDOException $e) {}
}

// 3. Compute Dynamic Reputation
$baseRep = (int)($profileUser['reputation_points'] ?? $profileUser['reputation'] ?? 0);
$connectionRep = 0;
$postsRep = 0;

try {
    $connCountStmt = $pdo->prepare("SELECT COUNT(*) FROM connections WHERE (requester_id = :uid OR addressee_id = :uid) AND status = 'accepted'");
    $connCountStmt->execute([':uid' => $profileId]);
    $connectionRep = ((int)$connCountStmt->fetchColumn()) * 5;
} catch (PDOException $e) {}

try {
    $postCountStmt = $pdo->prepare("SELECT COUNT(*) FROM posts WHERE user_id = :uid");
    $postCountStmt->execute([':uid' => $profileId]);
    $postsRep = ((int)$postCountStmt->fetchColumn()) * 10;
} catch (PDOException $e) {}

$reputation = max(0, $baseRep + $connectionRep + $postsRep);

// Rank Tiers
if ($reputation >= 5000) {
    $rankTitle = 'VALKYRIE PRIME';
    $badgeClass = 'bg-danger text-light border border-danger';
    $badgeIcon = 'fa-solid fa-crown';
    $level = 5;
} elseif ($reputation >= 2000) {
    $rankTitle = 'VALHALLA COMMANDER';
    $badgeClass = 'bg-warning text-dark';
    $badgeIcon = 'fa-solid fa-shield-halved';
    $level = 4;
} elseif ($reputation >= 750) {
    $rankTitle = 'SHIELDBEARER';
    $badgeClass = 'bg-accent text-dark';
    $badgeIcon = 'fa-solid fa-shield';
    $level = 3;
} elseif ($reputation >= 200) {
    $rankTitle = 'BERSERKER';
    $badgeClass = 'bg-info text-dark';
    $badgeIcon = 'fa-solid fa-bolt';
    $level = 2;
} else {
    $rankTitle = 'INITIATE NODE';
    $badgeClass = 'bg-secondary text-light';
    $badgeIcon = 'fa-solid fa-seedling';
    $level = 1;
}

// Badge Setup
$allBadges = [
    ['title' => 'Initiate Node', 'icon' => 'fa-solid fa-seedling', 'color' => 'text-secondary', 'unlocked' => $level >= 1],
    ['title' => 'Berserker', 'icon' => 'fa-solid fa-bolt', 'color' => 'text-info', 'unlocked' => $level >= 2],
    ['title' => 'Shieldbearer', 'icon' => 'fa-solid fa-shield', 'color' => 'text-success', 'unlocked' => $level >= 3],
    ['title' => 'Valhalla Commander', 'icon' => 'fa-solid fa-shield-halved', 'color' => 'text-warning', 'unlocked' => $level >= 4],
    ['title' => 'Valkyrie Prime', 'icon' => 'fa-solid fa-crown', 'color' => 'text-danger', 'unlocked' => $level >= 5],
    ['title' => 'Mesh Connector', 'icon' => 'fa-solid fa-diagram-project', 'color' => 'text-primary', 'unlocked' => ($connectionRep > 0)]
];

// Profile Assets
$defaultAvatar = 'default_avatar.png';
$defaultBanner = 'default_banner.png';

$avatarPath = !empty($profileUser['avatar'])
    ? ((strpos($profileUser['avatar'], '/') === 0 || strpos($profileUser['avatar'], 'uploads/') === 0) ? $profileUser['avatar'] : '/uploads/profiles/' . $profileUser['avatar'])
    : '/uploads/profiles/' . $defaultAvatar;

$bannerPath = !empty($profileUser['banner'])
    ? ((strpos($profileUser['banner'], '/') === 0 || strpos($profileUser['banner'], 'uploads/') === 0) ? $profileUser['banner'] : '/uploads/profiles/' . $profileUser['banner'])
    : '/uploads/profiles/' . $defaultBanner;
?>

<!-- Header Banner -->
<header class="position-relative bg-dark border-bottom border-secondary overflow-hidden" 
        style="background: linear-gradient(180deg, rgba(0,0,0,0.4) 0%, rgba(15,15,20,0.95) 100%), url('<?= htmlspecialchars($bannerPath); ?>') center/cover no-repeat; min-height: 240px;">
    <div class="container h-100 d-flex flex-column justify-content-end pt-5 pb-4 position-relative" style="z-index: 2;">
        <div class="d-flex flex-wrap align-items-end justify-content-between gap-3">
            <div>
                <div class="d-flex align-items-center gap-2 mb-2">
                    <span class="badge bg-info text-dark px-3 py-1 rounded-pill">
                        <i class="fa-solid fa-network-wired me-1"></i>NODE SIGNAL
                    </span>
                    <span class="badge <?= $badgeClass; ?> px-3 py-1 rounded-pill">
                        <i class="<?= $badgeIcon; ?> me-1"></i><?= $rankTitle; ?> (LVL <?= $level; ?>)
                    </span>
                </div>
                <h1 class="font-cinzel display-5 fw-bold text-white mb-0">
                    <?= htmlspecialchars($profileUser['display_name'] ?: $profileUser['username']); ?>
                </h1>
                <small class="text-info font-monospace">@<?= htmlspecialchars($profileUser['username']); ?></small>
            </div>
            
            <!-- Connection Controls -->
            <div id="connectionActionArea">
                <?php if ($connectionStatus === 'accepted'): ?>
                    <button class="btn btn-outline-danger rounded-pill px-4 fw-bold" onclick="handleNodeAction(<?= $profileId; ?>, 'sever')">
                        <i class="fa-solid fa-link-slash me-2"></i>Sever Node Link
                    </button>
                <?php elseif ($connectionStatus === 'pending_sent'): ?>
                    <button class="btn btn-secondary rounded-pill px-4 fw-bold" disabled>
                        <i class="fa-solid fa-clock me-2"></i>Link Request Pending
                    </button>
                <?php elseif ($connectionStatus === 'pending_received'): ?>
                    <button class="btn btn-success rounded-pill px-4 fw-bold me-2" onclick="handleNodeAction(<?= $profileId; ?>, 'accept')">
                        <i class="fa-solid fa-check me-2"></i>Accept Link (+5 Rep)
                    </button>
                <?php else: ?>
                    <button class="btn btn-info rounded-pill px-4 fw-bold text-dark" onclick="handleNodeAction(<?= $profileId; ?>, 'request')">
                        <i class="fa-solid fa-user-plus me-2"></i>Establish Link Request
                    </button>
                <?php endif; ?>
            </div>
        </div>
    </div>
</header>

<main class="py-5">
    <div class="container">

        <!-- Stats Bar -->
        <div class="row g-4 mb-5">
            <div class="col-md-4">
                <div class="vk-card p-3 text-center h-100 d-flex flex-column justify-content-center">
                    <small class="text-muted-custom d-block mb-1">REPUTATION & TIER</small>
                    <span class="fs-5 fw-bold text-warning">
                        <i class="<?= $badgeIcon; ?> me-1"></i><?= number_format($reputation); ?> REP
                    </span>
                </div>
            </div>
            <div class="col-md-4">
                <div class="vk-card p-3 text-center h-100 d-flex flex-column justify-content-center">
                    <small class="text-muted-custom d-block mb-1">LINK STATUS</small>
                    <?php if ($connectionStatus === 'accepted'): ?>
                        <span class="fs-5 fw-bold text-success"><i class="fa-solid fa-circle-check me-1"></i>ACTIVE LINK</span>
                    <?php else: ?>
                        <span class="fs-5 fw-bold text-muted"><i class="fa-solid fa-lock me-1"></i>UNLINKED</span>
                    <?php endif; ?>
                </div>
            </div>
            <div class="col-md-4">
                <div class="vk-card p-3 text-center h-100 d-flex flex-column justify-content-center">
                    <small class="text-muted-custom d-block mb-1">NODE SINCE</small>
                    <span class="fs-6 fw-bold text-light"><?= date('M j, Y', strtotime($profileUser['created_at'])); ?></span>
                </div>
            </div>
        </div>

        <div class="row g-4 mb-5">
            <!-- Left Column: Avatar & Dynamic Metadata -->
            <div class="col-lg-4">
                <div class="vk-card p-4 text-center h-100 d-flex flex-column">
                    <div class="position-relative d-inline-block mb-3 align-self-center">
                        <img src="<?= htmlspecialchars($avatarPath); ?>" 
                             class="rounded-circle border border-2 border-info bg-dark p-1" 
                             width="130" height="130" 
                             alt="Avatar" 
                             style="object-fit: cover;"
                             onerror="this.onerror=null; this.src='/uploads/profiles/default_avatar.png';">
                    </div>
                    <h4 class="font-cinzel fw-bold mb-1"><?= htmlspecialchars($profileUser['display_name'] ?: $profileUser['username']); ?></h4>
                    <p class="text-muted-custom small mb-3">@<?= htmlspecialchars($profileUser['username']); ?></p>

                    <!-- Role Badge (If defined) -->
                    <?php if (!empty($profileUser['role'])): ?>
                        <div class="mb-3">
                            <span class="badge bg-outline-info border border-info text-info px-3 py-1">
                                <i class="fa-solid fa-user-gear me-1"></i><?= htmlspecialchars(strtoupper($profileUser['role'])); ?>
                            </span>
                        </div>
                    <?php endif; ?>

                    <!-- Bio Block -->
                    <div class="p-3 bg-dark rounded border border-secondary text-start mb-3">
                        <small class="text-info d-block fw-bold mb-1"><i class="fa-solid fa-id-card me-1"></i> Public Bio:</small>
                        <p class="text-muted-custom small mb-0">
                            <?= nl2br(htmlspecialchars($profileUser['bio'] ?: 'No system manifest published yet.')); ?>
                        </p>
                    </div>

                    <!-- Dynamic Node Attributes -->
                    <div class="p-3 bg-dark rounded border border-secondary text-start mt-auto">
                        <small class="text-info d-block fw-bold mb-2"><i class="fa-solid fa-sliders me-1"></i> Node Parameters:</small>
                        <ul class="list-unstyled text-muted-custom small mb-0 d-flex flex-column gap-2">
                            <?php if (!empty($profileUser['location'])): ?>
                                <li class="d-flex align-items-center gap-2">
                                    <i class="fa-solid fa-location-dot text-info width-20"></i>
                                    <span><?= htmlspecialchars($profileUser['location']); ?></span>
                                </li>
                            <?php endif; ?>

                            <?php if (!empty($profileUser['website'])): ?>
                                <li class="d-flex align-items-center gap-2">
                                    <i class="fa-solid fa-globe text-info width-20"></i>
                                    <a href="<?= htmlspecialchars($profileUser['website']); ?>" target="_blank" rel="noopener" class="text-info text-decoration-none text-truncate">
                                        <?= htmlspecialchars(preg_replace('#^https?://#', '', $profileUser['website'])); ?>
                                    </a>
                                </li>
                            <?php endif; ?>

                            <?php if (!empty($profileUser['github'])): ?>
                                <li class="d-flex align-items-center gap-2">
                                    <i class="fa-brands fa-github text-info width-20"></i>
                                    <a href="https://github.com/<?= htmlspecialchars($profileUser['github']); ?>" target="_blank" rel="noopener" class="text-info text-decoration-none">
                                        @<?= htmlspecialchars($profileUser['github']); ?>
                                    </a>
                                </li>
                            <?php endif; ?>

                            <?php if (!empty($profileUser['twitter']) || !empty($profileUser['x_handle'])): ?>
                                <?php $xHandle = $profileUser['twitter'] ?? $profileUser['x_handle']; ?>
                                <li class="d-flex align-items-center gap-2">
                                    <i class="fa-brands fa-x-twitter text-info width-20"></i>
                                    <a href="https://x.com/<?= htmlspecialchars($xHandle); ?>" target="_blank" rel="noopener" class="text-info text-decoration-none">
                                        @<?= htmlspecialchars($xHandle); ?>
                                    </a>
                                </li>
                            <?php endif; ?>

                            <?php if (empty($profileUser['location']) && empty($profileUser['website']) && empty($profileUser['github']) && empty($profileUser['twitter']) && empty($profileUser['x_handle'])): ?>
                                <li class="text-muted fst-italic">No extended parameters configured.</li>
                            <?php endif; ?>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- Right Column: Badge Display Case -->
            <div class="col-lg-8">
                <div class="vk-card p-4 p-md-5 h-100">
                    <h3 class="font-cinzel text-accent mb-4">
                        <i class="fa-solid fa-award me-2"></i>Unlocked Node Badges
                    </h3>

                    <div class="row g-3">
                        <?php foreach ($allBadges as $badge): ?>
                            <div class="col-6 col-md-4">
                                <div class="p-3 bg-dark border <?= $badge['unlocked'] ? 'border-info' : 'border-secondary opacity-50'; ?> rounded text-center h-100 d-flex flex-column align-items-center justify-content-center position-relative">
                                    <div class="mb-2 fs-2 <?= $badge['unlocked'] ? $badge['color'] : 'text-secondary'; ?>">
                                        <i class="<?= $badge['icon']; ?>"></i>
                                    </div>
                                    <h6 class="font-cinzel small fw-bold mb-0 <?= $badge['unlocked'] ? 'text-light' : 'text-muted'; ?>">
                                        <?= $badge['title']; ?>
                                    </h6>
                                    <?php if (!$badge['unlocked']): ?>
                                        <span class="position-absolute top-0 end-0 p-2 text-muted" title="Locked">
                                            <i class="fa-solid fa-lock extra-small"></i>
                                        </span>
                                    <?php else: ?>
                                        <span class="position-absolute top-0 end-0 p-2 text-info" title="Unlocked">
                                            <i class="fa-solid fa-circle-check extra-small"></i>
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>

    </div>
</main>

<script>
function handleNodeAction(targetId, action) {
    if (action === 'sever' && !confirm("Are you sure you want to sever this link? This will reduce both nodes by 5 reputation points.")) {
        return;
    }

    const formData = new FormData();
    formData.append('target_id', targetId);
    formData.append('action', action);

    fetch('/connections_action.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert(data.error || 'Operation failed.');
        }
    })
    .catch(() => alert('Network error processing request.'));
}
</script>

<?php
require_once __DIR__ . '/includes/footer.php';
?>