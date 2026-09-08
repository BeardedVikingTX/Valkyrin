<?php
/**
 * VALKYRIN :: User Command Node Dashboard
 */
// Load core session & cookies first
require_once __DIR__ . '/../includes/cookies.php';

// Auth Guard: Redirect unauthenticated nodes to login
if (!isset($_SESSION['user_id'])) {
    header("Location: /login.php");
    exit;
}

require_once __DIR__ . '/../config/database.php';
$pdo = get_db_connection();

// Fetch fresh user profile telemetry
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = :id LIMIT 1");
$stmt->execute([':id' => $_SESSION['user_id']]);
$user = $stmt->fetch();

if (!$user) {
    // Terminate invalid session
    session_destroy();
    header("Location: /login.php");
    exit;
}

$pageTitle = "VALKYRIN :: Node Command Hub (" . htmlspecialchars($user['username']) . ")";
require_once __DIR__ . '/../includes/header.php';

// Resolve profile avatar path
$avatarPath = "/users/images/avatars/" . htmlspecialchars($user['slug']) . "/" . htmlspecialchars($user['avatar']);
$defaultAvatar = "https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/icons/person-circle.svg";
?>

<div class="row g-4 mb-5">
    
    <!-- Left Column: User Telemetry Sidebar -->
    <div class="col-lg-4">
        <div class="vk-hud-card p-4 text-center">
            <div class="position-relative d-inline-block mb-3">
                <img src="<?= $avatarPath; ?>" 
                     onerror="this.onerror=null; this.src='<?= $defaultAvatar; ?>';" 
                     alt="User Avatar" 
                     class="img-fluid rounded-circle border border-info vk-float-img" 
                     style="width: 140px; height: 140px; object-fit: cover;">
                <span class="position-absolute bottom-0 end-0 badge rounded-pill bg-success p-2 border border-dark">
                    <span class="visually-hidden">Online</span>
                </span>
            </div>

            <h2 class="h3 text-info font-monospace mb-1"><?= htmlspecialchars($user['username']); ?></h2>
            <p class="badge bg-outline-info border border-info text-info font-monospace mb-3">
                TIER: <?= strtoupper(htmlspecialchars($user['user_role'])); ?>
            </p>

            <?php if ($user['registration_type'] === 'professional'): ?>
                <p class="small text-muted mb-1"><i class="fa-solid fa-id-card me-2"></i><?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></p>
                <p class="small text-muted mb-3"><i class="fa-solid fa-envelope me-2"></i><?= htmlspecialchars($user['email']); ?></p>
            <?php else: ?>
                <p class="small text-warning font-monospace mb-3"><i class="fa-solid fa-user-ninja me-2"></i>Anonymous Telemetry Mode Active</p>
            <?php endif; ?>

            <hr class="border-secondary">

            <!-- Reputation & Status Stats -->
            <div class="row text-center my-3 g-2">
                <div class="col-6">
                    <div class="p-2 border border-secondary rounded bg-dark">
                        <span class="d-block small text-muted font-monospace">REPUTATION</span>
                        <span class="h4 text-warning fw-bold"><?= number_format($user['reputation_points']); ?></span>
                    </div>
                </div>
                <div class="col-6">
                    <div class="p-2 border border-secondary rounded bg-dark">
                        <span class="d-block small text-muted font-monospace">NODE STATUS</span>
                        <span class="h6 text-success fw-bold text-uppercase"><?= htmlspecialchars($user['status']); ?></span>
                    </div>
                </div>
            </div>

            <div class="d-grid gap-2 mt-4">
                <a href="/logout.php" class="btn vk-btn-outline btn-sm text-danger border-danger">
                    <i class="fa-solid fa-power-off me-2"></i>TERMINATE SESSION (LOGOUT)
                </a>
            </div>
        </div>
    </div>

    <!-- Right Column: Command Feed & Operations -->
    <div class="col-lg-8">
        
        <!-- Welcome Hero HUD -->
        <div class="vk-hud-card p-4 mb-4 border-info">
            <h1 class="h3 text-warning mb-2"><i class="fa-solid fa-terminal me-2"></i>COMMAND NODE ACTIVE</h1>
            <p class="text-muted mb-0">
                Welcome back, <strong class="text-info"><?= htmlspecialchars($user['username']); ?></strong>. Your session key is encrypted and initialized. You are operating on node slot <span class="font-monospace text-success">#<?= str_pad($user['id'], 6, '0', STR_PAD_LEFT); ?></span>.
            </p>
        </div>

        <!-- Quick Action Hub -->
        <div class="row g-3 mb-4">
            <div class="col-md-4">
                <div class="vk-hud-card p-3 text-center h-100">
                    <i class="fa-solid fa-pen-to-square fa-2x text-info mb-2"></i>
                    <h2 class="h6 text-uppercase">Broadcast Transmission</h2>
                    <p class="small text-muted mb-3">Publish post payload to social feed.</p>
                    <button class="btn vk-btn-glow btn-sm w-100" disabled>Coms Offline</button>
                </div>
            </div>
            <div class="col-md-4">
                <div class="vk-hud-card p-3 text-center h-100">
                    <i class="fa-solid fa-comments fa-2x text-success mb-2"></i>
                    <h2 class="h6 text-uppercase">Encrypted Messages</h2>
                    <p class="small text-muted mb-3">Direct peer-to-peer telemetry signals.</p>
                    <button class="btn vk-btn-outline btn-sm w-100" disabled>Vault Offline</button>
                </div>
            </div>
            <div class="col-md-4">
                <div class="vk-hud-card p-3 text-center h-100">
                    <i class="fa-solid fa-shield-halved fa-2x text-warning mb-2"></i>
                    <h2 class="h6 text-uppercase">Reputation Badges</h2>
                    <p class="small text-muted mb-3">View unlocked achievement runes.</p>
                    <button class="btn vk-btn-outline btn-sm w-100" disabled>Badges (1)</button>
                </div>
            </div>
        </div>

        <!-- Node Activity Log Placeholder -->
        <div class="vk-hud-card p-4">
            <h2 class="h5 text-info mb-3"><i class="fa-solid fa-clock-rotate-left me-2"></i>RECENT NODE LOGS</h2>
            <div class="table-responsive">
                <table class="table table-dark table-hover font-monospace small mb-0">
                    <thead>
                        <tr>
                            <th>EVENT</th>
                            <th>TIMESTAMP</th>
                            <th>STATUS</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><i class="fa-solid fa-key me-2 text-success"></i>Session Established</td>
                            <td><?= date('Y-m-d H:i:s'); ?></td>
                            <td><span class="text-success">SUCCESS</span></td>
                        </tr>
                        <tr>
                            <td><i class="fa-solid fa-id-badge me-2 text-info"></i>Node Provisioned</td>
                            <td><?= htmlspecialchars($user['created_at']); ?></td>
                            <td><span class="text-info">ACTIVE</span></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

    </div>

</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>