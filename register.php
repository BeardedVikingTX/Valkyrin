<?php
define('VALKYRIN_EXEC', true);

$pageTitle = "VALKYRIN | Initialize Node Account";
$pageDesc = "Join the Near-ZK social infrastructure. Sovereign identity creation with zero data harvesting.";

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/nav.php';

$errors = [];
$successMessage = '';

// Handle Account Creation Strategy
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'initialize_account') {
    $username    = trim(filter_input(INPUT_POST, 'username', FILTER_SANITIZE_SPECIAL_CHARS));
    $email       = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL);
    $displayName = trim(filter_input(INPUT_POST, 'display_name', FILTER_SANITIZE_SPECIAL_CHARS));
    $password    = $_POST['password'] ?? '';
    $confirmPass = $_POST['confirm_password'] ?? '';
    $bio         = trim(filter_input(INPUT_POST, 'bio', FILTER_SANITIZE_SPECIAL_CHARS));
    $acceptTerms = isset($_POST['terms_agree']);

    // Validation Filters
    if (!$acceptTerms) {
        $errors[] = "You must accept the Sovereign Network Manifesto to initialize a node.";
    }

    if (!$username || !preg_match('/^[a-zA-Z0-9_]{3,30}$/', $username)) {
        $errors[] = "Handle must be 3-30 characters long and contain only letters, numbers, or underscores.";
    }

    if (!$email) {
        $errors[] = "A valid cryptographic email routing address is required.";
    }

    if (strlen($password) < 10) {
        $errors[] = "Security Key phrase must be at least 10 characters long.";
    }

    if ($password !== $confirmPass) {
        $errors[] = "Security Key phrases do not match.";
    }

    // Database Execution Engine
    if (empty($errors) && isset($pdo)) {
        try {
            // Check Handle & Email Uniqueness
            $checkStmt = $pdo->prepare("SELECT id FROM users WHERE username = :username OR email = :email LIMIT 1");
            $checkStmt->execute([':username' => $username, ':email' => $email]);
            
            if ($checkStmt->fetch()) {
                $errors[] = "The selected Handle or Email routing node is already active on the network.";
            } else {
                // Argon2id Password Hashing Strategy
                $passwordHash = password_hash($password, PASSWORD_ARGON2ID, [
                    'memory_cost' => 65536,
                    'time_cost'   => 4,
                    'threads'     => 2
                ]);

                // Fallback to BCRYPT if Argon2id module is disabled on LiteSpeed
                if (!$passwordHash) {
                    $passwordHash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
                }

                $insertStmt = $pdo->prepare("
                    INSERT INTO users (username, email, password_hash, display_name, bio) 
                    VALUES (:username, :email, :password_hash, :display_name, :bio)
                ");

                $insertStmt->execute([
                    ':username'      => $username,
                    ':email'         => $email,
                    ':password_hash' => $passwordHash,
                    ':display_name'  => !empty($displayName) ? $displayName : $username,
                    ':bio'           => !empty($bio) ? $bio : null
                ]);

                $successMessage = "Node initialized successfully! You may now authenticate your terminal.";
            }
        } catch (PDOException $e) {
            $errors[] = "System execution fault: Unable to log new user record.";
        }
    }
}
?>

<header class="py-5 bg-opacity-10 border-bottom border-secondary">
    <div class="container text-center">
        <div class="badge bg-outline-info text-gradient px-3 py-2 rounded-pill border border-info mb-3">
            <i class="fa-solid fa-key me-2"></i>SOVEREIGN IDENTITY INITIALIZATION
        </div>
        <h1 class="display-4 font-cinzel fw-bold mb-2">
            JOIN <span class="text-gradient">VALKYRIN</span>
        </h1>
        <p class="lead text-muted-custom mx-auto mb-0" style="max-width: 650px;">
            Construct your node on the decentralized, privacy-hardened social platform built by Gemini AI & Bearded Viking.
        </p>
    </div>
</header>

<main class="py-5">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-8 col-xl-7">
                <div class="vk-card p-4 p-md-5">

                    <?php if (!empty($errors)): ?>
                        <div class="alert alert-danger bg-danger bg-opacity-20 border-0 text-light mb-4">
                            <h6 class="fw-bold mb-2"><i class="fa-solid fa-triangle-exclamation me-2"></i>Initialization Blocked:</h6>
                            <ul class="mb-0 small">
                                <?php foreach ($errors as $error): ?>
                                    <li><?= $error; ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>

                    <?php if ($successMessage): ?>
                        <div class="alert alert-success bg-success bg-opacity-20 border-0 text-light text-center p-4 mb-4">
                            <i class="fa-solid fa-circle-check text-success display-4 mb-3 d-block"></i>
                            <h4 class="font-cinzel text-gradient fw-bold mb-2">Node Account Active</h4>
                            <p class="text-muted-custom mb-3"><?= $successMessage; ?></p>
                            <a href="/login.php" class="btn btn-info rounded-pill px-4 fw-bold text-dark">
                                <i class="fa-solid fa-right-to-bracket me-2"></i>Authenticate Terminal
                            </a>
                        </div>
                    <?php else: ?>

                        <form method="POST" action="" novalidate>
                            <input type="hidden" name="action" value="initialize_account">

                            <div class="border-bottom border-secondary pb-3 mb-4">
                                <h5 class="font-cinzel text-accent mb-1"><i class="fa-solid fa-shield-halved me-2"></i>1. Credentials & Identification</h5>
                                <small class="text-muted-custom">Mandatory core system attributes.</small>
                            </div>

                            <div class="row g-3 mb-4">
                                <div class="col-md-6">
                                    <label class="form-label text-muted-custom small fw-bold">Network Handle *</label>
                                    <div class="input-group">
                                        <span class="input-group-text bg-dark border-secondary text-info">@</span>
                                        <input type="text" name="username" class="form-control bg-dark text-light border-secondary" placeholder="viking_node" value="<?= htmlspecialchars($_POST['username'] ?? ''); ?>" required>
                                    </div>
                                    <small class="text-muted-custom extra-small">Unique system identifier (a-z, 0-9, _)</small>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label text-muted-custom small fw-bold">Routing Email *</label>
                                    <div class="input-group">
                                        <span class="input-group-text bg-dark border-secondary text-info"><i class="fa-solid fa-envelope"></i></span>
                                        <input type="email" name="email" class="form-control bg-dark text-light border-secondary" placeholder="node@beardedviking.org" value="<?= htmlspecialchars($_POST['email'] ?? ''); ?>" required>
                                    </div>
                                    <small class="text-muted-custom extra-small">Used solely for authentication routing</small>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label text-muted-custom small fw-bold">Security Key *</label>
                                    <div class="input-group">
                                        <span class="input-group-text bg-dark border-secondary text-info"><i class="fa-solid fa-lock"></i></span>
                                        <input type="password" name="password" class="form-control bg-dark text-light border-secondary" placeholder="••••••••••••" required>
                                    </div>
                                    <small class="text-muted-custom extra-small">Minimum 10 characters</small>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label text-muted-custom small fw-bold">Confirm Security Key *</label>
                                    <div class="input-group">
                                        <span class="input-group-text bg-dark border-secondary text-info"><i class="fa-solid fa-key"></i></span>
                                        <input type="password" name="confirm_password" class="form-control bg-dark text-light border-secondary" placeholder="••••••••••••" required>
                                    </div>
                                    <small class="text-muted-custom extra-small">Re-enter key phrase</small>
                                </div>
                            </div>

                            <div class="border-bottom border-secondary pb-3 mb-4">
                                <h5 class="font-cinzel text-accent mb-1"><i class="fa-solid fa-user-gear me-2"></i>2. Public Persona (Optional)</h5>
                                <small class="text-muted-custom">Configure your display signature across network feeds.</small>
                            </div>

                            <div class="row g-3 mb-4">
                                <div class="col-md-12">
                                    <label class="form-label text-muted-custom small fw-bold">Display Name</label>
                                    <input type="text" name="display_name" class="form-control bg-dark text-light border-secondary" placeholder="e.g. Chief Systems Architect" value="<?= htmlspecialchars($_POST['display_name'] ?? ''); ?>">
                                </div>

                                <div class="col-md-12">
                                    <label class="form-label text-muted-custom small fw-bold">Node Bio / System Manifest</label>
                                    <textarea name="bio" rows="3" class="form-control bg-dark text-light border-secondary" placeholder="Brief system bio or architectural focus..."><?= htmlspecialchars($_POST['bio'] ?? ''); ?></textarea>
                                </div>
                            </div>

                            <div class="form-check mb-4">
                                <input class="form-check-input bg-dark border-secondary" type="checkbox" name="terms_agree" id="termsCheck" required>
                                <label class="form-check-label text-muted-custom small" for="termsCheck">
                                    I agree to the <span class="text-gradient fw-bold">Sovereign Network Manifesto</span>. I understand VALKYRIN employs zero data harvesting and Near-ZK dynamic telemetry.
                                </label>
                            </div>

                            <button type="submit" class="btn btn-info btn-lg rounded-pill w-100 fw-bold text-dark shadow-sm">
                                <i class="fa-solid fa-microchip me-2"></i>Initialize Account
                            </button>

                            <div class="text-center mt-4 pt-3 border-top border-secondary">
                                <span class="text-muted-custom small">Already registered on the network?</span>
                                <a href="/login.php" class="text-info text-decoration-none small fw-bold ms-1">Authenticate Here</a>
                            </div>
                        </form>

                    <?php endif; ?>

                </div>
            </div>
        </div>
    </div>
</main>

<?php
require_once __DIR__ . '/includes/footer.php';
?>