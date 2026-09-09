<?php
define('VALKYRIN_EXEC', true);
ob_start();

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 1. Authentication Guard (Check BEFORE layout output)
if (!isset($_SESSION['user_id'])) {
    header("Location: /login.php?error=" . urlencode("Authentication required to access transmission frequency."));
    exit();
}

require_once __DIR__ . '/../includes/header.php'; // Ensure DB connection is loaded early

$userId = $_SESSION['user_id'];
$errors = [];
$successMessage = '';

// Form Security: Generate Form Timestamp for Bot Time-Gating
if (!isset($_SESSION['post_form_time'])) {
    $_SESSION['post_form_time'] = time();
}

// 2. FORM PROCESSING LOGIC (MUST RUN BEFORE ANY HTML/NAV INCLUDES)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'transmit_post') {
    
    // BOT PROTECTION: Honeypot Trapping
    if (!empty($_POST['sec_signal_check'])) {
        die("Transmission rejected: Automated signal detected.");
    }

    // BOT PROTECTION: Submission Speed Check
    $formTime = $_POST['form_time'] ?? 0;
    if ((time() - (int)$formTime) < 2) {
        $errors[] = "Signal broadcast anomaly: Transmission speed too rapid. Please retry.";
    }

    $content    = trim(filter_input(INPUT_POST, 'content', FILTER_SANITIZE_SPECIAL_CHARS));
    $visibility = filter_input(INPUT_POST, 'visibility', FILTER_SANITIZE_SPECIAL_CHARS) ?: 'public';
    $tags       = trim(filter_input(INPUT_POST, 'tags', FILTER_SANITIZE_SPECIAL_CHARS));
    
    if (empty($content)) {
        $errors[] = "Payload error: Transmission content cannot be empty.";
    }

    if (mb_strlen($content) > 3000) {
        $errors[] = "Payload error: Content exceeds max node buffer length of 3,000 characters.";
    }

    // FILE ATTACHMENT HANDLING
    $attachmentName = null;
    $attachmentType = null;

    if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
        $fileTmpPath   = $_FILES['attachment']['tmp_name'];
        $fileName      = $_FILES['attachment']['name'];
        $fileSize      = $_FILES['attachment']['size'];
        $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

        $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp', 'gif', 'pdf', 'zip', 'mp4'];
        
        if (in_array($fileExtension, $allowedExtensions)) {
            if ($fileSize <= 10 * 1024 * 1024) { // 10MB Cap
                $uploadDir = __DIR__ . '/../uploads/attachments/';
                
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }

                $attachmentName = 'tx_' . $userId . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $fileExtension;
                $destPath = $uploadDir . $attachmentName;

                if (move_uploaded_file($fileTmpPath, $destPath)) {
                    $attachmentType = in_array($fileExtension, ['jpg', 'jpeg', 'png', 'webp', 'gif']) ? 'image' : 
                                     ($fileExtension === 'mp4' ? 'video' : 'document');
                } else {
                    $errors[] = "Storage fault: Failed to store file payload.";
                }
            } else {
                $errors[] = "Payload warning: Attachment size exceeds max limit of 10MB.";
            }
        } else {
            $errors[] = "Type error: Unsupported payload format (Allowed: Images, MP4, PDF, ZIP).";
        }
    }

    // DATABASE TRANSMISSION PERSISTENCE
    if (empty($errors) && isset($pdo)) {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO posts (user_id, content, visibility, attachment, attachment_type, tags, created_at)
                VALUES (:user_id, :content, :visibility, :attachment, :attachment_type, :tags, NOW())
            ");
            
            $stmt->execute([
                ':user_id'         => $userId,
                ':content'         => $content,
                ':visibility'      => $visibility,
                ':attachment'      => $attachmentName,
                ':attachment_type' => $attachmentType,
                ':tags'            => $tags
            ]);

            // Increment Reputation (+15 REP per post broadcast)
            try {
                $repStmt = $pdo->prepare("UPDATE users SET reputation = reputation + 15 WHERE id = :id");
                $repStmt->execute([':id' => $userId]);
            } catch (PDOException $e) {
                // Fallback column name check
                $repStmt = $pdo->prepare("UPDATE users SET reputation_points = reputation_points + 15 WHERE id = :id");
                $repStmt->execute([':id' => $userId]);
            }

            // Reset Form Time
            $_SESSION['post_form_time'] = time();

            // SUCCESSFUL REDIRECT (Works safely now because no HTML was output yet)
            header("Location: /feed.php?success=" . urlencode("Broadcast transmitted successfully across network. +15 REP Gained."));
            exit();

        } catch (PDOException $e) {
            $errors[] = "Network fault: Unable to broadcast transmission to node database.";
        }
    }
}

// 3. NOW RENDER THE HTML / LAYOUT INCLUDES
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/nav.php';
?>

<header class="py-4 bg-dark border-bottom border-secondary">
    <div class="container">
        <div class="d-flex align-items-center justify-content-between">
            <div>
                <span class="badge bg-info text-dark px-3 py-1 rounded-pill mb-2">
                    <i class="fa-solid fa-tower-broadcast me-1"></i>TRANSMISSION CONSOLE
                </span>
                <h1 class="font-cinzel display-6 fw-bold text-white mb-0">CREATE NETWORK BROADCAST</h1>
            </div>
            <div>
                <a href="/feed.php" class="btn btn-outline-secondary rounded-pill px-4 btn-sm fw-bold">
                    <i class="fa-solid fa-arrow-left me-2"></i>Abort to Feed
                </a>
            </div>
        </div>
    </div>
</header>

<main class="py-5">
    <div class="container" style="max-width: 850px;">

        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger bg-danger bg-opacity-20 border-0 text-light mb-4">
                <i class="fa-solid fa-triangle-exclamation me-2"></i><strong>Signal Interrupted:</strong>
                <ul class="mb-0 small mt-1">
                    <?php foreach ($errors as $err): ?>
                        <li><?= $err; ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <div class="vk-card p-4 p-md-5">
            <form method="POST" action="" enctype="multipart/form-data" id="transmissionForm">
                
                <div style="display:none !important;" aria-hidden="true">
                    <input type="text" name="sec_signal_check" tabindex="-1" autocomplete="off">
                </div>
                <input type="hidden" name="form_time" value="<?= time(); ?>">
                <input type="hidden" name="action" value="transmit_post">

                <div class="mb-4">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <label class="form-label text-info fw-bold mb-0">
                            <i class="fa-solid fa-code me-2"></i>BROADCAST PAYLOAD (CONTENT)
                        </label>
                        <small class="text-muted-custom" id="charCounter">0 / 3000</small>
                    </div>
                    <textarea name="content" id="payloadContent" rows="6" 
                              class="form-control bg-dark text-light border-secondary p-3" 
                              placeholder="Type your transmission here... Use #hashtags to tag network nodes." required></textarea>
                </div>

                <div class="row g-3 mb-4">
                    <div class="col-md-6">
                        <label class="form-label text-muted-custom small fw-bold">
                            <i class="fa-solid fa-lock me-1"></i>CLEARANCE / VISIBILITY
                        </label>
                        <select name="visibility" class="form-select bg-dark text-light border-secondary">
                            <option value="public" selected>🌐 Public Broadcast (All Nodes)</option>
                            <option value="network">🔒 Network Only (Authenticated Nodes)</option>
                            <option value="classified">👁️ Classified Log (Private to You)</option>
                        </select>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label text-muted-custom small fw-bold">
                            <i class="fa-solid fa-hashtag me-1"></i>INDEX TAGS
                        </label>
                        <input type="text" name="tags" class="form-control bg-dark text-light border-secondary" 
                               placeholder="e.g. #valkyrin #dev #updates">
                    </div>
                </div>

                <div class="mb-4">
                    <label class="form-label text-muted-custom small fw-bold">
                        <i class="fa-solid fa-paperclip me-1"></i>ATTACH FILE PAYLOAD (MAX 10MB)
                    </label>
                    <input type="file" name="attachment" id="attachmentInput" class="form-control bg-dark text-light border-secondary" accept="image/*,video/mp4,.pdf,.zip">
                    <small class="text-muted-custom extra-small mt-1 d-block">Supported Formats: JPG, PNG, WEBP, GIF, MP4, PDF, ZIP</small>
                </div>

                <div class="d-flex align-items-center justify-content-between border-top border-secondary pt-4">
                    <div class="d-flex align-items-center text-muted-custom small">
                        <i class="fa-solid fa-shield-halved text-success me-2 fs-5"></i>
                        <span>End-to-End Node Authenticated</span>
                    </div>
                    <button type="submit" class="btn btn-info rounded-pill px-5 py-2 fw-bold text-dark">
                        <i class="fa-solid fa-paper-plane me-2"></i>Transmit Signal
                    </button>
                </div>

            </form>
        </div>

    </div>
</main>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const textarea = document.getElementById('payloadContent');
    const counter = document.getElementById('charCounter');
    
    if (textarea && counter) {
        textarea.addEventListener('input', function() {
            const len = this.value.length;
            counter.textContent = `${len} / 3000`;
            if (len > 2800) {
                counter.classList.add('text-danger');
            } else {
                counter.classList.remove('text-danger');
            }
        });
    }
});
</script>

<?php
require_once __DIR__ . '/../includes/footer.php';
ob_end_flush();
?>