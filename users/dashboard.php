<?php
define('VALKYRIN_EXEC', true);

// Start Session & Authenticate Endpoint Access
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    header("Location: /login.php?error=" . urlencode("Authentication required. Please log in to access your terminal."));
    exit();
}

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/nav.php';

$userId = (int)$_SESSION['user_id'];
$errors = [];
$successMessage = '';

// Default Asset Definitions
$defaultAvatar = 'default_avatar.png';
$defaultBanner = 'default_banner.png';

// Fetch Updated User Record
if (isset($pdo)) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            session_destroy();
            header("Location: /login.php");
            exit();
        }
    } catch (PDOException $e) {
        die("System fault: Unable to retrieve user record. Details: " . $e->getMessage());
    }

    // Default reputation fallback
    $baseRep = (int)($user['reputation_points'] ?? $user['reputation'] ?? 0);
    $connectionRep = 0;
    $postsRep = 0;

    // 1. Safe Connections Count
    try {
        $connStmt = $pdo->prepare("
            SELECT COUNT(*) FROM connections 
            WHERE (requester_id = :uid OR addressee_id = :uid) AND status = 'accepted'
        ");
        $connStmt->execute([':uid' => $userId]);
        $connectionRep = ((int)$connStmt->fetchColumn()) * 5;
    } catch (PDOException $e) { 
        /* connections table missing or empty - default to 0 */ 
    }

    // 2. Safe Posts Count
    try {
        $postStmt = $pdo->prepare("SELECT COUNT(*) FROM posts WHERE user_id = :uid");
        $postStmt->execute([':uid' => $userId]);
        $postsRep = ((int)$postStmt->fetchColumn()) * 10;
    } catch (PDOException $e) { 
        /* posts table missing or empty - default to 0 */ 
    }

    // Combined Reputation
    $reputation = max(0, $baseRep + $connectionRep + $postsRep);
}

// Gamification Calculation Engine
if ($reputation >= 5000) {
    $rankTitle = 'VALKYRIE PRIME';
    $badgeClass = 'bg-danger text-light border border-danger';
    $badgeIcon = 'fa-solid fa-crown';
    $level = 5;
    $nextLevelRep = 10000;
} elseif ($reputation >= 2000) {
    $rankTitle = 'VALHALLA COMMANDER';
    $badgeClass = 'bg-warning text-dark';
    $badgeIcon = 'fa-solid fa-shield-halved';
    $level = 4;
    $nextLevelRep = 5000;
} elseif ($reputation >= 750) {
    $rankTitle = 'SHIELDBEARER';
    $badgeClass = 'bg-accent text-dark';
    $badgeIcon = 'fa-solid fa-shield';
    $level = 3;
    $nextLevelRep = 2000;
} elseif ($reputation >= 200) {
    $rankTitle = 'BERSERKER';
    $badgeClass = 'bg-info text-dark';
    $badgeIcon = 'fa-solid fa-bolt';
    $level = 2;
    $nextLevelRep = 750;
} else {
    $rankTitle = 'INITIATE NODE';
    $badgeClass = 'bg-secondary text-light';
    $badgeIcon = 'fa-solid fa-seedling';
    $level = 1;
    $nextLevelRep = 200;
}

$progressPercent = min(100, round(($reputation / $nextLevelRep) * 100));

// Badge Collection Definitions
$allBadges = [
    [
        'id' => 'initiate',
        'title' => 'Initiate Node',
        'req' => 'Join the VALKYRIN Network',
        'icon' => 'fa-solid fa-seedling',
        'color' => 'text-secondary',
        'unlocked' => $level >= 1
    ],
    [
        'id' => 'berserker',
        'title' => 'Berserker',
        'req' => 'Reach 200 Reputation Points',
        'icon' => 'fa-solid fa-bolt',
        'color' => 'text-info',
        'unlocked' => $level >= 2
    ],
    [
        'id' => 'shieldbearer',
        'title' => 'Shieldbearer',
        'req' => 'Reach 750 Reputation Points',
        'icon' => 'fa-solid fa-shield',
        'color' => 'text-success',
        'unlocked' => $level >= 3
    ],
    [
        'id' => 'commander',
        'title' => 'Valhalla Commander',
        'req' => 'Reach 2,000 Reputation Points',
        'icon' => 'fa-solid fa-shield-halved',
        'color' => 'text-warning',
        'unlocked' => $level >= 4
    ],
    [
        'id' => 'prime',
        'title' => 'Valkyrie Prime',
        'req' => 'Reach 5,000 Reputation Points',
        'icon' => 'fa-solid fa-crown',
        'color' => 'text-danger',
        'unlocked' => $level >= 5
    ],
    [
        'id' => 'networker',
        'title' => 'Mesh Connector',
        'req' => 'Establish active node connections',
        'icon' => 'fa-solid fa-diagram-project',
        'color' => 'text-primary',
        'unlocked' => ($connectionRep > 0)
    ]
];

// Handle Profile Updates & Media Uploads
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_profile') {
    $displayName = trim(filter_input(INPUT_POST, 'display_name', FILTER_SANITIZE_SPECIAL_CHARS));
    $bio         = trim(filter_input(INPUT_POST, 'bio', FILTER_SANITIZE_SPECIAL_CHARS));
    
    $avatarName = !empty($user['avatar']) ? $user['avatar'] : $defaultAvatar;
    $bannerName = !empty($user['banner']) ? $user['banner'] : $defaultBanner;

    $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp'];
    $uploadDir = __DIR__ . '/../uploads/profiles/';

    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    // 1) Avatar Upload Handler
    if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
        $fileTmpPath   = $_FILES['avatar']['tmp_name'];
        $fileName      = $_FILES['avatar']['name'];
        $fileSize      = $_FILES['avatar']['size'];
        $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

        if (in_array($fileExtension, $allowedExtensions)) {
            if ($fileSize <= 2 * 1024 * 1024) {
                $newAvatarName = 'avatar_node_' . $userId . '_' . time() . '.' . $fileExtension;
                if (move_uploaded_file($fileTmpPath, $uploadDir . $newAvatarName)) {
                    $avatarName = $newAvatarName;
                } else {
                    $errors[] = "Error moving uploaded avatar to storage directory.";
                }
            } else {
                $errors[] = "Avatar file size exceeds max limit of 2MB.";
            }
        } else {
            $errors[] = "Invalid avatar file type. Allowed: JPG, PNG, WEBP.";
        }
    }

    // 2) Banner Upload Handler
    if (isset($_FILES['banner']) && $_FILES['banner']['error'] === UPLOAD_ERR_OK) {
        $fileTmpPath   = $_FILES['banner']['tmp_name'];
        $fileName      = $_FILES['banner']['name'];
        $fileSize      = $_FILES['banner']['size'];
        $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

        if (in_array($fileExtension, $allowedExtensions)) {
            if ($fileSize <= 4 * 1024 * 1024) {
                $newBannerName = 'banner_node_' . $userId . '_' . time() . '.' . $fileExtension;
                if (move_uploaded_file($fileTmpPath, $uploadDir . $newBannerName)) {
                    $bannerName = $newBannerName;
                } else {
                    $errors[] = "Error moving uploaded banner to storage directory.";
                }
            } else {
                $errors[] = "Banner file size exceeds max limit of 4MB.";
            }
        } else {
            $errors[] = "Invalid banner file type. Allowed: JPG, PNG, WEBP.";
        }
    }

    // Database Profile Update
    if (empty($errors) && isset($pdo)) {
        try {
            $updateStmt = $pdo->prepare("
                UPDATE users 
                SET display_name = :display_name, bio = :bio, avatar = :avatar, banner = :banner 
                WHERE id = :id
            ");
            $updateStmt->execute([
                ':display_name' => $displayName,
                ':bio'          => $bio,
                ':avatar'       => $avatarName,
                ':banner'       => $bannerName,
                ':id'           => $userId
            ]);

            $successMessage = "Node profile settings updated successfully.";
            
            $user['display_name'] = $displayName;
            $user['bio']          = $bio;
            $user['avatar']       = $avatarName;
            $user['banner']       = $bannerName;
        } catch (PDOException $e) {
            $errors[] = "Database fault: Failed to persist profile changes.";
        }
    }
}

// Compute Image Paths Cleanly
$avatarPath = !empty($user['avatar'])
    ? ((strpos($user['avatar'], '/') === 0 || strpos($user['avatar'], 'uploads/') === 0) ? $user['avatar'] : '/uploads/profiles/' . $user['avatar'])
    : '/uploads/profiles/' . $defaultAvatar;

$bannerPath = !empty($user['banner'])
    ? ((strpos($user['banner'], '/') === 0 || strpos($user['banner'], 'uploads/') === 0) ? $user['banner'] : '/uploads/profiles/' . $user['banner'])
    : '/uploads/profiles/' . $defaultBanner;
?>

<!-- Dynamic Node Header Banner -->
<header class="position-relative bg-dark border-bottom border-secondary overflow-hidden" 
        style="background: linear-gradient(180deg, rgba(0,0,0,0.3) 0%, rgba(15,15,20,0.95) 100%), url('<?= htmlspecialchars($bannerPath); ?>') center/cover no-repeat; min-height: 240px;">
    <div class="container h-100 d-flex flex-column justify-content-end pt-5 pb-4 position-relative" style="z-index: 2;">
        <div class="d-flex flex-wrap align-items-end justify-content-between gap-3">
            <div>
                <div class="d-flex align-items-center gap-2 mb-2">
                    <span class="badge bg-info text-dark px-3 py-1 rounded-pill">
                        <i class="fa-solid fa-microchip me-1"></i>NODE TERMINAL
                    </span>
                    <span class="badge <?= $badgeClass; ?> px-3 py-1 rounded-pill">
                        <i class="<?= $badgeIcon; ?> me-1"></i><?= $rankTitle; ?> (LVL <?= $level; ?>)
                    </span>
                </div>
                <h1 class="font-cinzel display-5 fw-bold text-white mb-0">
                    WELCOME, <span class="text-gradient"><?= htmlspecialchars($user['display_name'] ?: $user['username']); ?></span>
                </h1>
            </div>
            
            <div class="d-flex flex-wrap gap-2">
                <a href="/feed.php" class="btn btn-info rounded-pill px-4 fw-bold text-dark">
                    <i class="fa-solid fa-rss me-2"></i>Network Feed
                </a>
                <a href="/posts/create.php" class="btn btn-outline-info rounded-pill px-4 fw-bold">
                    <i class="fa-solid fa-pen-to-square me-2"></i>Transmit Post
                </a>
                <a href="/logout.php" class="btn btn-outline-danger rounded-pill px-3 fw-bold">
                    <i class="fa-solid fa-power-off me-1"></i>Disconnect
                </a>
            </div>
        </div>
    </div>
</header>

<main class="py-5">
    <div class="container">

        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger bg-danger bg-opacity-20 border-0 text-light mb-4">
                <ul class="mb-0 small">
                    <?php foreach ($errors as $err): ?>
                        <li><?= $err; ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <?php if ($successMessage): ?>
            <div class="alert alert-success bg-success bg-opacity-20 border-0 text-light mb-4">
                <i class="fa-solid fa-circle-check me-2"></i><?= $successMessage; ?>
            </div>
        <?php endif; ?>

        <!-- Telemetry Metrics -->
        <div class="row g-4 mb-5">
            <div class="col-md-3">
                <div class="vk-card p-3 text-center h-100 d-flex flex-column justify-content-center">
                    <small class="text-muted-custom d-block mb-1">NETWORK HANDLE</small>
                    <span class="fs-5 fw-bold text-info">@<?= htmlspecialchars($user['username']); ?></span>
                </div>
            </div>
            <div class="col-md-3">
                <div class="vk-card p-3 text-center h-100 d-flex flex-column justify-content-center">
                    <small class="text-muted-custom d-block mb-1">REPUTATION & RANK</small>
                    <span class="fs-5 fw-bold text-warning mb-1">
                        <i class="<?= $badgeIcon; ?> me-1"></i><?= number_format($reputation); ?> REP
                    </span>
                    <div class="progress bg-dark border border-secondary" style="height: 6px;">
                        <div class="progress-bar bg-warning" role="progressbar" style="width: <?= $progressPercent; ?>%;" aria-valuenow="<?= $progressPercent; ?>" aria-valuemin="0" aria-valuemax="100"></div>
                    </div>
                    <small class="text-muted-custom extra-small mt-1"><?= $progressPercent; ?>% to Next Tier (<?= number_format($nextLevelRep); ?> REP)</small>
                </div>
            </div>
            <div class="col-md-3">
                <div class="vk-card p-3 text-center h-100 d-flex flex-column justify-content-center">
                    <small class="text-muted-custom d-block mb-1">ROLE CLEARANCE</small>
                    <span class="fs-5 fw-bold text-accent"><?= ucfirst($user['role'] ?? 'node'); ?></span>
                </div>
            </div>
            <div class="col-md-3">
                <div class="vk-card p-3 text-center h-100 d-flex flex-column justify-content-center">
                    <small class="text-muted-custom d-block mb-1">NODE CREATED</small>
                    <span class="fs-6 fw-bold text-light"><?= date('M j, Y', strtotime($user['created_at'])); ?></span>
                </div>
            </div>
        </div>

        <div class="row g-4 mb-5">
            <!-- Left Column: Profile Card Preview -->
            <div class="col-lg-4">
                <div class="vk-card p-4 text-center h-100">
                    <div class="position-relative d-inline-block mb-3">
                        <img src="<?= htmlspecialchars($avatarPath); ?>" 
                             class="rounded-circle border border-2 border-info bg-dark p-1" 
                             width="120" height="120" 
                             alt="Node Avatar" 
                             style="object-fit: cover;"
                             onerror="this.onerror=null; this.src='/uploads/profiles/default_avatar.png';">
                    </div>
                    <h4 class="font-cinzel fw-bold mb-1"><?= htmlspecialchars($user['display_name'] ?: $user['username']); ?></h4>
                    <p class="text-muted-custom small mb-2">@<?= htmlspecialchars($user['username']); ?></p>
                    
                    <div class="mb-3">
                        <span class="badge <?= $badgeClass; ?> rounded-pill px-3 py-1">
                            <i class="<?= $badgeIcon; ?> me-1"></i><?= $rankTitle; ?>
                        </span>
                    </div>

                    <div class="p-3 bg-dark rounded border border-secondary text-start mb-3">
                        <small class="text-info d-block fw-bold mb-1"><i class="fa-solid fa-id-card me-1"></i> Public Bio:</small>
                        <p class="text-muted-custom small mb-0">
                            <?= nl2br(htmlspecialchars($user['bio'] ?: 'No system manifest provided yet. Update your profile to display details on the network.')); ?>
                        </p>
                    </div>
                    <small class="text-muted-custom extra-small">
                        <i class="fa-solid fa-lock me-1"></i> Telemetry Sync Active
                    </small>
                </div>
            </div>

            <!-- Right Column: Edit Profile & Customization Settings -->
            <div class="col-lg-8">
                <div class="vk-card p-4 p-md-5">
                    <h3 class="font-cinzel text-accent mb-4">
                        <i class="fa-solid fa-user-pen me-2"></i>Configure Profile Attributes
                    </h3>

                    <form method="POST" action="" enctype="multipart/form-data">
                        <input type="hidden" name="action" value="update_profile">

                        <div class="mb-3">
                            <label class="form-label text-muted-custom small fw-bold">Routing Email Address</label>
                            <input type="email" class="form-control bg-dark text-muted border-secondary" value="<?= htmlspecialchars($user['email']); ?>" disabled>
                            <small class="text-muted-custom extra-small">Email routing addresses cannot be modified directly.</small>
                        </div>

                        <div class="mb-3">
                            <label class="form-label text-muted-custom small fw-bold">Display Signature / Name</label>
                            <input type="text" name="display_name" class="form-control bg-dark text-light border-secondary" value="<?= htmlspecialchars($user['display_name'] ?? ''); ?>" placeholder="Public Display Signature">
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6 mb-3 mb-md-0">
                                <label class="form-label text-muted-custom small fw-bold">Avatar Image Upload</label>
                                <input type="file" name="avatar" class="form-control bg-dark text-light border-secondary" accept="image/png, image/jpeg, image/webp">
                                <small class="text-muted-custom extra-small">Max size 2MB (PNG, JPG, WEBP).</small>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label text-muted-custom small fw-bold">Header Banner Upload</label>
                                <input type="file" name="banner" class="form-control bg-dark text-light border-secondary" accept="image/png, image/jpeg, image/webp">
                                <small class="text-muted-custom extra-small">Max size 4MB (PNG, JPG, WEBP).</small>
                            </div>
                        </div>

                        <div class="mb-4">
                            <label class="form-label text-muted-custom small fw-bold">Node Bio / System Manifest</label>
                            <textarea name="bio" rows="4" class="form-control bg-dark text-light border-secondary" placeholder="Write your public bio..."><?= htmlspecialchars($user['bio'] ?? ''); ?></textarea>
                        </div>

                        <button type="submit" class="btn btn-info rounded-pill px-4 fw-bold text-dark">
                            <i class="fa-solid fa-floppy-disk me-2"></i>Persist Profile Changes
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Badge Showcase Section -->
        <div class="vk-card p-4 p-md-5">
            <div class="d-flex align-items-center justify-content-between mb-4">
                <div>
                    <h3 class="font-cinzel text-accent mb-1">
                        <i class="fa-solid fa-award me-2"></i>Badge Display Case
                    </h3>
                    <p class="text-muted-custom small mb-0">Network achievements and tier badges earned by this node.</p>
                </div>
                <span class="badge bg-dark border border-secondary text-info px-3 py-2 rounded-pill">
                    <?= count(array_filter($allBadges, fn($b) => $b['unlocked'])); ?> / <?= count($allBadges); ?> Badges Unlocked
                </span>
            </div>

            <div class="row g-3">
                <?php foreach ($allBadges as $badge): ?>
                    <div class="col-6 col-md-4 col-lg-2">
                        <div class="p-3 bg-dark border <?= $badge['unlocked'] ? 'border-info' : 'border-secondary opacity-50'; ?> rounded text-center h-100 d-flex flex-column align-items-center justify-content-center position-relative">
                            <div class="mb-2 fs-2 <?= $badge['unlocked'] ? $badge['color'] : 'text-secondary'; ?>">
                                <i class="<?= $badge['icon']; ?>"></i>
                            </div>
                            <h6 class="font-cinzel small fw-bold mb-1 <?= $badge['unlocked'] ? 'text-light' : 'text-muted'; ?>">
                                <?= $badge['title']; ?>
                            </h6>
                            <small class="extra-small text-muted-custom"><?= $badge['req']; ?></small>
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
</main>

<?php
require_once __DIR__ . '/../includes/footer.php';
?>