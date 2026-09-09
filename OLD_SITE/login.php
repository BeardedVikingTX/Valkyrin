<?php
/**
 * VALKYRIN :: Authentication Gateway (Login)
 */
$pageTitle = "VALKYRIN :: Node Authentication Access";
require_once __DIR__ . '/includes/header.php';

// Auth Guard: If already logged in, redirect to dashboard
if (isset($_SESSION['user_id'])) {
    header("Location: /users/dashboard.php");
    exit;
}

$loginError = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'authenticate_user') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $loginError = "Security token mismatch. Access signal denied.";
    } else {
        require_once __DIR__ . '/config/database.php';
        $pdo = get_db_connection();

        $identity = trim($_POST['identity'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($identity) || empty($password)) {
            $loginError = "Please enter both your account identity and access key.";
        } else {
            // Allow login by Username or Email
            $stmt = $pdo->prepare("
                SELECT id, username, slug, password_hash, user_role, avatar, status 
                FROM users 
                WHERE username = :identity_user OR (email = :identity_email AND email IS NOT NULL) 
                LIMIT 1
            ");
            $stmt->execute([
                ':identity_user'  => $identity,
                ':identity_email' => $identity
            ]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password_hash'])) {
                if ($user['status'] === 'suspended') {
                    $loginError = "Node access suspended. Contact security telemetry for clearance.";
                } else {
                    // Update Last Login Timestamp
                    $updateStmt = $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = :id");
                    $updateStmt->execute([':id' => $user['id']]);

                    // Initialize Session Variables
                    $_SESSION['user_id']   = $user['id'];
                    $_SESSION['username']  = $user['username'];
                    $_SESSION['user_slug'] = $user['slug'];
                    $_SESSION['user_role'] = $user['user_role'];
                    $_SESSION['user_avatar'] = $user['avatar'];

                    header("Location: /users/dashboard.php");
                    exit;
                }
            } else {
                $loginError = "Invalid node identity credentials or password key.";
            }
        }
    }
}
?>

<div class="row justify-content-center mb-5">
    <div class="col-lg-6 col-md-8">
        <div class="vk-hud-card p-4 p-md-5 border-info">
            <div class="text-center mb-4">
                <i class="fa-solid fa-right-to-bracket fa-3x text-info mb-2"></i>
                <h1 class="h2 vk-glow-title">NODE AUTHENTICATION</h1>
                <p class="small text-muted font-monospace">Enter your callsign or email to decrypt session state.</p>
            </div>

            <?php if (!empty($loginError)): ?>
                <div class="alert alert-danger font-monospace small mb-4"><?= $loginError; ?></div>
            <?php endif; ?>

            <form action="login.php" method="POST" class="bg-dark p-4 border border-secondary rounded">
                <input type="hidden" name="action" value="authenticate_user">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token']; ?>">

                <div class="mb-3">
                    <label for="identity" class="form-label text-info font-monospace">CALLSIGN ALIAS OR EMAIL *</label>
                    <input type="text" class="form-control bg-secondary text-white border-info" id="identity" name="identity" required placeholder="Username or name@domain.com">
                </div>

                <div class="mb-4">
                    <label for="password" class="form-label text-info font-monospace">ACCESS PASSWORD KEY *</label>
                    <input type="password" class="form-control bg-secondary text-white border-info" id="password" name="password" required placeholder="••••••••">
                </div>

                <button type="submit" class="btn vk-btn-glow w-100 py-2 mb-3">
                    <i class="fa-solid fa-lock-open me-2"></i>INITIALIZE ACCESS KEY
                </button>

                <div class="text-center">
                    <span class="small text-muted font-monospace">Unregistered Node? </span>
                    <a href="/register.php" class="small text-info font-monospace">Provision New Account</a>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>