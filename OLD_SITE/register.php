<?php
/**
 * VALKYRIN :: User Registration Engine (Dual-Tier Auth)
 */
$pageTitle = "VALKYRIN :: Node Provisioning & Registration";
require_once __DIR__ . '/includes/header.php';

$registerError   = '';
$registerSuccess = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'provision_user') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $registerError = "Security token validation failed. Provisioning aborted.";
    } else {
        require_once __DIR__ . '/config/database.php';
        $pdo = get_db_connection();

        $regType  = htmlspecialchars(trim($_POST['reg_type'] ?? 'anonymous'));
        $username = htmlspecialchars(trim($_POST['username'] ?? ''));
        $password = $_POST['password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        // Generate sanitized directory slug (removes spaces and special characters)
        $slug = strtolower(preg_replace('/[^a-zA-Z0-9_]/', '_', trim($username)));
        $slug = preg_replace('/_+/', '_', $slug); // Collapse duplicate underscores

        $firstName = ($regType === 'professional') ? htmlspecialchars(trim($_POST['first_name'] ?? '')) : null;
        $lastName  = ($regType === 'professional') ? htmlspecialchars(trim($_POST['last_name'] ?? '')) : null;
        $email     = ($regType === 'professional') ? filter_var(trim($_POST['email'] ?? ''), FILTER_VALIDATE_EMAIL) : null;

        // Validation Rules
        if (empty($username) || empty($password) || empty($confirmPassword)) {
            $registerError = "All mandatory signal fields must be populated.";
        } elseif ($password !== $confirmPassword) {
            $registerError = "Cryptographic password hashes do not match.";
        } elseif (strlen($password) < 8) {
            $registerError = "Password key must be at least 8 characters long.";
        } elseif ($regType === 'professional' && !$email) {
            $registerError = "A valid return email address is required for Professional provisioning.";
        } else {
            // Check for existing username or email
            $checkStmt = $pdo->prepare("SELECT id FROM users WHERE username = :username OR slug = :slug OR (email = :email AND email IS NOT NULL)");
            $checkStmt->execute([':username' => $username, ':slug' => $slug, ':email' => $email]);

            if ($checkStmt->fetch()) {
                $registerError = "Callsign alias or email vector is already registered on this network.";
            } else {
                // STEP 1: Directory Setup
                $avatarDir = __DIR__ . "/users/images/avatars/{$slug}/";
                $bannerDir = __DIR__ . "/users/images/banners/{$slug}/";

                if (!is_dir($avatarDir)) {
                    mkdir($avatarDir, 0755, true);
                }
                if (!is_dir($bannerDir)) {
                    mkdir($bannerDir, 0755, true);
                }

                // Handle Avatar Upload if provided
                $avatarFilename = 'default.png';
                if (isset($_FILES['avatar_file']) && $_FILES['avatar_file']['error'] === UPLOAD_ERR_OK) {
                    $fileTmp  = $_FILES['avatar_file']['tmp_name'];
                    $fileName = basename($_FILES['avatar_file']['name']);
                    $ext      = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

                    $allowedExts = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
                    if (in_array($ext, $allowedExts)) {
                        $newFileName = "avatar_" . time() . ".{$ext}";
                        if (move_uploaded_file($fileTmp, $avatarDir . $newFileName)) {
                            $avatarFilename = $newFileName;
                        }
                    }
                }

                // STEP 2: Password Hashing & Database Insertion
                $passwordHash = password_hash($password, PASSWORD_DEFAULT);

                try {
                    $insertStmt = $pdo->prepare("
                        INSERT INTO users (registration_type, username, slug, first_name, last_name, email, password_hash, avatar)
                        VALUES (:reg_type, :username, :slug, :first_name, :last_name, :email, :password_hash, :avatar)
                    ");

                    $insertStmt->execute([
                        ':reg_type'      => $regType,
                        ':username'      => $username,
                        ':slug'          => $slug,
                        ':first_name'    => $firstName,
                        ':last_name'     => $lastName,
                        ':email'         => $email,
                        ':password_hash' => $passwordHash,
                        ':avatar'        => $avatarFilename
                    ]);

                    $newUserId = $pdo->lastInsertId();

                    // STEP 3: Dispatch Warm Welcoming Email for Professional Accounts
                    if ($regType === 'professional' && $email) {
                        $mailSubject = "Welcome to Valkyrin Network :: Provisioning Confirmed";
                        $mailBody    = "Greetings {$firstName} '{$username}' {$lastName},\n\n"
                                     . "Your node credentials have been successfully registered on Valkyrin.\n\n"
                                     . "User Alias: {$username}\n"
                                     . "Security Status: Shieldman Tier Active\n"
                                     . "Dashboard Access: https://" . $_SERVER['HTTP_HOST'] . "/users/dashboard.php\n\n"
                                     . "Welcome to the digital realm.\n\n"
                                     . "Skál,\nBearded Viking Engineering Team";

                        $mailHeaders = "From: Valkyrin Telemetry <no-reply@beardedviking.org>\r\n"
                                     . "X-Mailer: PHP/" . phpversion();

                        @mail($email, $mailSubject, $mailBody, $mailHeaders);
                    }

                    // STEP 4: Session Allocation & Redirect
                    $_SESSION['user_id']   = $newUserId;
                    $_SESSION['username']  = $username;
                    $_SESSION['user_slug'] = $slug;
                    $_SESSION['user_role'] = 'shieldman';

                    header("Location: /users/dashboard.php");
                    exit;
                } catch (Exception $e) {
                    $registerError = "Database fault during node insertion: " . $e->getMessage();
                }
            }
        }
    }
}
?>

<div class="row justify-content-center mb-5">
    <div class="col-lg-8">
        <div class="vk-hud-card p-4 p-md-5 border-info">
            <div class="text-center mb-4">
                <i class="fa-solid fa-id-card-clip fa-3x text-info mb-2"></i>
                <h1 class="h2 vk-glow-title">PROVISION NEW NODE ACCOUNT</h1>
                <p class="small text-muted font-monospace">Choose your provisioning level to initialize session keys.</p>
            </div>

            <?php if (!empty($registerError)): ?>
                <div class="alert alert-danger font-monospace small mb-4"><?= $registerError; ?></div>
            <?php endif; ?>

            <!-- Registration Type Tabs -->
            <ul class="nav nav-pills nav-justified mb-4 vk-telemetry-box p-1 rounded" id="regTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active vk-nav-link fw-bold" id="anon-tab" data-bs-toggle="pill" data-bs-target="#anon-panel" type="button" onclick="document.getElementById('reg_type').value='anonymous';">
                        <i class="fa-solid fa-user-ninja me-2"></i>ANONYMOUS TIER
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link vk-nav-link fw-bold" id="pro-tab" data-bs-toggle="pill" data-bs-target="#pro-panel" type="button" onclick="document.getElementById('reg_type').value='professional';">
                        <i class="fa-solid fa-user-shield me-2"></i>PROFESSIONAL TIER
                    </button>
                </li>
            </ul>

            <form action="register.php" method="POST" enctype="multipart/form-data" class="bg-dark p-4 border border-secondary rounded">
                <input type="hidden" name="action" value="provision_user">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token']; ?>">
                <input type="hidden" name="reg_type" id="reg_type" value="anonymous">

                <div class="tab-content" id="regTabsContent">
                    
                    <!-- Anonymous Panel -->
                    <div class="tab-pane fade show active" id="anon-panel" role="tabpanel">
                        <div class="mb-3">
                            <label for="anon_username" class="form-label text-info font-monospace">CALLSIGN / ALIAS NAME *</label>
                            <input type="text" class="form-control bg-secondary text-white border-info" id="anon_username" name="username" required placeholder="e.g., CyberViking_99">
                        </div>
                    </div>

                    <!-- Professional Panel -->
                    <div class="tab-pane fade" id="pro-panel" role="tabpanel">
                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label for="first_name" class="form-label text-info font-monospace">FIRST NAME *</label>
                                <input type="text" class="form-control bg-secondary text-white border-info" id="first_name" name="first_name" placeholder="First Name">
                            </div>
                            <div class="col-md-6">
                                <label for="last_name" class="form-label text-info font-monospace">LAST NAME *</label>
                                <input type="text" class="form-control bg-secondary text-white border-info" id="last_name" name="last_name" placeholder="Last Name">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="pro_email" class="form-label text-info font-monospace">DISPATCH EMAIL ADDRESS *</label>
                            <input type="email" class="form-control bg-secondary text-white border-info" id="pro_email" name="email" placeholder="name@domain.com">
                        </div>
                    </div>

                </div>

                <!-- Shared Credentials Section -->
                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label for="password" class="form-label text-info font-monospace">ACCESS PASSWORD KEY *</label>
                        <input type="password" class="form-control bg-secondary text-white border-info" id="password" name="password" required placeholder="••••••••">
                    </div>
                    <div class="col-md-6">
                        <label for="confirm_password" class="form-label text-info font-monospace">CONFIRM ACCESS KEY *</label>
                        <input type="password" class="form-control bg-secondary text-white border-info" id="confirm_password" name="confirm_password" required placeholder="••••••••">
                    </div>
                </div>

                <div class="mb-4">
                    <label for="avatar_file" class="form-label text-info font-monospace">PROFILE AVATAR (OPTIONAL)</label>
                    <input class="form-control bg-secondary text-white border-info" type="file" id="avatar_file" name="avatar_file" accept="image/*">
                </div>

                <button type="submit" class="btn vk-btn-glow w-100 py-2">
                    <i class="fa-solid fa-key me-2"></i>PROVISION VALKYRIN NODE
                </button>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>