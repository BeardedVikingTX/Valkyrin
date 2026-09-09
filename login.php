<?php
define('VALKYRIN_EXEC', true);

// Enable temporary error output for diagnostics
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Redirect if already authenticated
if (isset($_SESSION['user_id'])) {
    header("Location: /users/dashboard.php");
    exit();
}

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/nav.php';

$errors = [];
$statusMessage = filter_input(INPUT_GET, 'message', FILTER_SANITIZE_SPECIAL_CHARS);
$errorMessage  = filter_input(INPUT_GET, 'error', FILTER_SANITIZE_SPECIAL_CHARS);

if ($errorMessage) {
    $errors[] = $errorMessage;
}

// Handle Terminal Authentication Strategy
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'authenticate_terminal') {
    $identifier = trim(filter_input(INPUT_POST, 'identifier', FILTER_SANITIZE_SPECIAL_CHARS));
    $password   = $_POST['password'] ?? '';

    if (empty($identifier)) {
        $errors[] = "Network handle or email routing address is required.";
    }

    if (empty($password)) {
        $errors[] = "Security Key phrase is required.";
    }

    if (empty($errors) && isset($pdo)) {
        try {
            // Retrieve User Node by Username or Email
            $stmt = $pdo->prepare("
                SELECT id, username, email, password_hash, display_name, role, account_status 
                FROM users 
                WHERE username = :username OR email = :email 
                LIMIT 1
            ");
            
            // Pass both parameters explicitly
            $stmt->execute([
                ':username' => $identifier,
                ':email'    => $identifier
            ]);
            
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user) {
                // Verify Account Status
                if ($user['account_status'] === 'suspended') {
                    $errors[] = "Terminal access revoked. Node account is currently suspended.";
                } elseif (password_verify($password, $user['password_hash'])) {
                    
                    // Determine supported algo safely (Argon2id fallback to BCRYPT)
                    $algo = defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_BCRYPT;
                    $algoOptions = ($algo === PASSWORD_ARGON2ID) 
                        ? ['memory_cost' => 65536, 'time_cost' => 4, 'threads' => 2]
                        : ['cost' => 12];

                    // Rehash Password if Hash Algorithm Needs Updating
                    if (password_needs_rehash($user['password_hash'], $algo, $algoOptions)) {
                        $newHash = password_hash($password, $algo, $algoOptions);
                        if ($newHash) {
                            $rehashStmt = $pdo->prepare("UPDATE users SET password_hash = :hash WHERE id = :id");
                            $rehashStmt->execute([':hash' => $newHash, ':id' => $user['id']]);
                        }
                    }

                    // Regenerate Session ID to Prevent Session Fixation
                    session_regenerate_id(true);

                    // Establish Authenticated Session State
                    $_SESSION['user_id']      = $user['id'];
                    $_SESSION['username']     = $user['username'];
                    $_SESSION['display_name'] = $user['display_name'] ?: $user['username'];
                    $_SESSION['role']         = $user['role'];
                    $_SESSION['login_time']   = time();

                    // Update Last Login Timestamp safely using standard formatted datetime string
                    $currentTimestamp = date('Y-m-d H:i:s');
                    $updateStmt = $pdo->prepare("UPDATE users SET last_login = :last_login WHERE id = :id");
                    $updateStmt->execute([
                        ':last_login' => $currentTimestamp,
                        ':id'         => $user['id']
                    ]);

                    header("Location: /users/dashboard.php");
                    exit();
                } else {
                    $errors[] = "Invalid authentication credentials. Check your handle and security key phrase.";
                }
            } else {
                $errors[] = "Invalid authentication credentials. Check your handle and security key phrase.";
            }
        } catch (PDOException $e) {
            // Outputs exact PDO Exception while debugging
            $errors[] = "Database Fault: " . $e->getMessage();
        } catch (Exception $e) {
            $errors[] = "System Execution Fault: " . $e->getMessage();
        }
    }
}
?>

<header class="py-5 bg-opacity-10 border-bottom border-secondary">
    <div class="container text-center">
        <div class="badge bg-outline-info text-gradient px-3 py-2 rounded-pill border border-info mb-3">
            <i class="fa-solid fa-terminal me-2"></i>TERMINAL AUTHENTICATION
        </div>
        <h1 class="display-4 font-cinzel fw-bold mb-2">
            ACCESS <span class="text-gradient">VALKYRIN</span>
        </h1>
        <p class="lead text-muted-custom mx-auto mb-0" style="max-width: 650px;">
            Authenticate your identity to connect to your sovereign network node.
        </p>
    </div>
</header>

<main class="py-5">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-6 col-md-8">
                <div class="vk-card p-4 p-md-5">

                    <?php if ($statusMessage): ?>
                        <div class="alert alert-success bg-success bg-opacity-20 border-0 text-light text-center mb-4">
                            <i class="fa-solid fa-circle-check me-2"></i><?= htmlspecialchars($statusMessage); ?>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($errors)): ?>
                        <div class="alert alert-danger bg-danger bg-opacity-20 border-0 text-light mb-4">
                            <h6 class="fw-bold mb-2"><i class="fa-solid fa-triangle-exclamation me-2"></i>Authentication Fault:</h6>
                            <ul class="mb-0 small">
                                <?php foreach ($errors as $error): ?>
                                    <li><?= htmlspecialchars($error); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="" novalidate>
                        <input type="hidden" name="action" value="authenticate_terminal">

                        <div class="mb-3">
                            <label class="form-label text-muted-custom small fw-bold">Network Handle or Email *</label>
                            <div class="input-group">
                                <span class="input-group-text bg-dark border-secondary text-info"><i class="fa-solid fa-user"></i></span>
                                <input type="text" name="identifier" class="form-control bg-dark text-light border-secondary" placeholder="viking_node or node@beardedviking.org" value="<?= htmlspecialchars($_POST['identifier'] ?? ''); ?>" required autofocus>
                            </div>
                        </div>

                        <div class="mb-4">
                            <label class="form-label text-muted-custom small fw-bold">Security Key *</label>
                            <div class="input-group">
                                <span class="input-group-text bg-dark border-secondary text-info"><i class="fa-solid fa-lock"></i></span>
                                <input type="password" name="password" class="form-control bg-dark text-light border-secondary" placeholder="••••••••••••" required>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-info btn-lg rounded-pill w-100 fw-bold text-dark shadow-sm">
                            <i class="fa-solid fa-right-to-bracket me-2"></i>Authenticate Terminal
                        </button>

                        <div class="text-center mt-4 pt-3 border-top border-secondary">
                            <span class="text-muted-custom small">Need to initialize a new node?</span>
                            <a href="/register.php" class="text-info text-decoration-none small fw-bold ms-1">Register Node Here</a>
                        </div>
                    </form>

                </div>
            </div>
        </div>
    </div>
</main>

<?php
require_once __DIR__ . '/includes/footer.php';
?>