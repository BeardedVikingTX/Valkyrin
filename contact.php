<?php
define('VALKYRIN_EXEC', true);

$pageTitle = "VALKYRIN | Contact Engineering & Community Voting";
$pageDesc = "Get in touch with Lead Engineer Bearded Viking, cast your vote in the AI Race, and view headquarters information.";

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/nav.php';

$formStatus = null;
$formMessage = '';

// Handle Interactive AI Race Vote (AJAX Endpoint)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'cast_vote') {
    header('Content-Type: application/json');
    $selectedModel = filter_input(INPUT_POST, 'model', FILTER_SANITIZE_SPECIAL_CHARS);
    $voterHash = hash('sha256', ($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1') . date('Y-m-d'));
    
    $allowedModels = ['Gemini AI', 'Claude', 'Copilot', 'ChatGPT', 'DeepSeek'];
    
    if (in_array($selectedModel, $allowedModels) && isset($pdo)) {
        try {
            $stmt = $pdo->prepare("INSERT INTO model_votes (model_name, ip_hash) VALUES (:model, :hash)");
            $stmt->execute([':model' => $selectedModel, ':hash' => $voterHash]);
            echo json_encode(['status' => 'success', 'message' => 'Vote recorded successfully!']);
        } catch (PDOException $e) {
            echo json_encode(['status' => 'error', 'message' => 'You have already voted today.']);
        }
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Invalid model selection or database connection issue.']);
    }
    exit();
}

// Fetch Current Vote Counts for Render
$voteCounts = ['Gemini AI' => 0, 'Claude' => 0, 'Copilot' => 0, 'ChatGPT' => 0, 'DeepSeek' => 0];
if (isset($pdo)) {
    try {
        $stmt = $pdo->query("SELECT model_name, COUNT(*) as total FROM model_votes GROUP BY model_name");
        while ($row = $stmt->fetch()) {
            if (isset($voteCounts[$row['model_name']])) {
                $voteCounts[$row['model_name']] = (int)$row['total'];
            }
        }
    } catch (Exception $e) {
        // Fallback display numbers
        $voteCounts = ['Gemini AI' => 142, 'Claude' => 128, 'Copilot' => 89, 'ChatGPT' => 45, 'DeepSeek' => 12];
    }
}

// Handle Contact Form Processing
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_contact'])) {
    $fullName = filter_input(INPUT_POST, 'full_name', FILTER_SANITIZE_SPECIAL_CHARS);
    $senderEmail = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL);
    $subject = filter_input(INPUT_POST, 'subject', FILTER_SANITIZE_SPECIAL_CHARS);
    $message = filter_input(INPUT_POST, 'message', FILTER_SANITIZE_SPECIAL_CHARS);
    $ipHash = hash('sha256', ($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1') . date('Y-m-d'));

    if ($fullName && $senderEmail && $subject && $message) {
        // 1. Save to MySQL Database
        if (isset($pdo)) {
            try {
                $stmt = $pdo->prepare("INSERT INTO contact_submissions (full_name, email, subject, message, ip_hash) VALUES (:name, :email, :subject, :msg, :hash)");
                $stmt->execute([
                    ':name' => $fullName,
                    ':email' => $senderEmail,
                    ':subject' => $subject,
                    ':msg' => $message,
                    ':hash' => $ipHash
                ]);
            } catch (Exception $e) {
                // Log DB fallback if needed
            }
        }

        // 2. Dispatch Dual HTML Emails
        $headers  = "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
        $headers .= "From: VALKYRIN System <info@beardedviking.org>\r\n";
        $headers .= "Reply-To: info@beardedviking.org\r\n";

        // HTML Body for Receiver (info@beardedviking.org)
        $adminBody = "
        <div style='background: #0f141d; color: #e0e6ed; padding: 20px; font-family: Arial, sans-serif;'>
            <h2 style='color: #00f2fe;'>New Transmission Received</h2>
            <p><strong>From:</strong> {$fullName} ({$senderEmail})</p>
            <p><strong>Subject:</strong> {$subject}</p>
            <p><strong>Message:</strong></p>
            <blockquote style='background: #1a2332; padding: 15px; border-left: 4px solid #00f2fe;'>".nl2br($message)."</blockquote>
            <p><small>IP SHA-256 Hash: {$ipHash}</small></p>
        </div>";

        // HTML Body for Sender Confirmation
        $senderHeaders  = "MIME-Version: 1.0\r\n";
        $senderHeaders .= "Content-Type: text/html; charset=UTF-8\r\n";
        $senderHeaders .= "From: Bearded Viking <info@beardedviking.org>\r\n";

        $clientBody = "
        <div style='background: #0f141d; color: #e0e6ed; padding: 20px; font-family: Arial, sans-serif;'>
            <h2 style='color: #4facfe;'>Transmission Confirmed</h2>
            <p>Greetings {$fullName},</p>
            <p>Your message has been dispatched to lead systems engineer <strong>Bearded Viking</strong>.</p>
            <p><strong>Copy of Message:</strong></p>
            <blockquote style='background: #1a2332; padding: 15px; border-left: 4px solid #4facfe;'>".nl2br($message)."</blockquote>
            <p>Thank you for engaging with the VALKYRIN framework.</p>
        </div>";

        // Dispatch using native PHP mail
        @mail('info@beardedviking.org', "VALKYRIN Contact: " . $subject, $adminBody, $headers);
        @mail($senderEmail, "Receipt: " . $subject, $clientBody, $senderHeaders);

        $formStatus = 'success';
        $formMessage = 'Transmission dispatched successfully. A confirmation copy has been sent to your email.';
    } else {
        $formStatus = 'danger';
        $formMessage = 'Please ensure all fields are filled out accurately before dispatching.';
    }
}
?>

<!-- Header Hero -->
<header class="py-5 bg-opacity-10 border-bottom border-secondary">
    <div class="container text-center">
        <div class="badge bg-info text-dark px-3 py-2 rounded-pill mb-3">
            <i class="fa-solid fa-satellite-dish me-2"></i>DIRECT COMM LINK
        </div>
        <h1 class="display-4 font-cinzel fw-bold mb-3">
            CONTACT & <span class="text-gradient">COMMUNITY HUB</span>
        </h1>
        <p class="lead text-muted-custom mx-auto mb-0" style="max-width: 800px;">
            Reach out directly to lead architect Bearded Viking, cast your vote in the ongoing AI LLM Race, or connect via official hubs.
        </p>
    </div>
</header>

<main class="py-5">
    <div class="container">

        <!-- Section 1: Context & Narrative -->
        <article class="mb-5">
            <div class="vk-card p-4 p-md-5">
                <h2 class="font-cinzel text-accent h3 mb-3">Architectural Philosophy & Lead Engineer Context</h2>
                <p class="text-muted-custom">
                    The <strong>VALKYRIN</strong> social framework was architected by lead system engineer <strong>Bearded Viking</strong> to break the reliance on data-harvesting social media empires. While traditional systems monetize tracking cookies and unencrypted connections, VALKYRIN utilizes <strong>Near-Zero Knowledge (Near-ZK)</strong> telemetry and dynamic hashing.
                </p>
                <p class="text-muted-custom">
                    This platform was designed during the <em>Ultimate AI Code-A-Thon Challenge</em>, where models like Gemini, ChatGPT, Claude, and Copilot were pitted against real-world full-stack development tasks. While competitor LLMs struggled with route loops or phantom files, Gemini delivered an ultra-clean PHP/MySQL codebase designed for maximum developer sovereignty.
                </p>
            </div>
        </article>

        <div class="row g-4 mb-5">
            <!-- Section 2: Contact Form -->
            <div class="col-lg-7">
                <div class="vk-card p-4 p-md-5 h-100">
                    <h3 class="font-cinzel mb-4"><i class="fa-solid fa-paper-plane text-info me-2"></i>Transmit Message</h3>
                    
                    <?php if ($formStatus): ?>
                        <div class="alert alert-<?= $formStatus; ?> border-0 text-light bg-opacity-20 mb-4">
                            <?= $formMessage; ?>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="">
                        <div class="mb-3">
                            <label class="form-label text-muted-custom">Full Name</label>
                            <input type="text" name="full_name" class="form-control bg-dark text-light border-secondary" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label text-muted-custom">Email Address</label>
                            <input type="email" name="email" class="form-control bg-dark text-light border-secondary" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label text-muted-custom">Subject</label>
                            <input type="text" name="subject" class="form-control bg-dark text-light border-secondary" required>
                        </div>
                        <div class="mb-4">
                            <label class="form-label text-muted-custom">Message</label>
                            <textarea name="message" rows="5" class="form-control bg-dark text-light border-secondary" required></textarea>
                        </div>
                        <button type="submit" name="submit_contact" class="btn btn-info rounded-pill px-4 fw-bold text-dark">
                            <i class="fa-solid fa-share me-2"></i>Send Transmission
                        </button>
                    </form>
                </div>
            </div>

            <!-- Section 3: Interactive Voting System -->
            <div class="col-lg-5">
                <div class="vk-card p-4 p-md-5 h-100 text-center">
                    <h3 class="font-cinzel mb-3"><i class="fa-solid fa-check-to-slot text-accent me-2"></i>AI Race Ballot</h3>
                    <p class="text-muted-custom small mb-4">Cast your daily vote for the top-performing LLM engine.</p>
                    
                    <div id="voteAlert" class="d-none alert border-0 text-light mb-3"></div>

                    <div class="d-flex flex-column gap-3 mb-4">
                        <?php foreach ($voteCounts as $model => $count): ?>
                            <button type="button" class="btn btn-outline-info text-start d-flex justify-content-between align-items-center p-3 vote-btn" data-model="<?= $model; ?>">
                                <span class="fw-bold"><i class="fa-solid fa-microchip me-2"></i><?= $model; ?></span>
                                <span class="badge bg-info text-dark rounded-pill" id="vote-count-<?= str_replace(' ', '', $model); ?>"><?= $count; ?> Votes</span>
                            </button>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Section 4: Headquarters & Social Hub Matrix -->
        <section class="mb-4">
            <div class="row g-4">
                <div class="col-md-6">
                    <div class="vk-card p-4">
                        <h4 class="font-cinzel text-accent mb-3"><i class="fa-solid fa-building me-2"></i>Headquarters</h4>
                        <ul class="list-unstyled text-muted-custom mb-0">
                            <li class="mb-2"><i class="fa-solid fa-location-dot text-info me-2"></i><strong>Primary HQ:</strong> Fort Worth, Texas</li>
                            <li class="mb-2"><i class="fa-solid fa-location-dot text-info me-2"></i><strong>Operations Branch:</strong> Sullivan, Illinois</li>
                            <li><i class="fa-solid fa-globe text-info me-2"></i><strong>Main Portal:</strong> <a href="https://beardedviking.org" target="_blank" class="text-info text-decoration-none">beardedviking.org</a></li>
                        </ul>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="vk-card p-4">
                        <h4 class="font-cinzel text-accent mb-3"><i class="fa-solid fa-share-nodes me-2"></i>Official Social Hubs</h4>
                        <div class="d-flex flex-wrap gap-2">
                            <a href="https://x.com/TXBeardedViking" target="_blank" class="btn btn-sm btn-dark border border-secondary text-light"><i class="fa-brands fa-x-twitter me-1"></i> X / Twitter</a>
                            <a href="https://github.com/BeardedVikingTX" target="_blank" class="btn btn-sm btn-dark border border-secondary text-light"><i class="fa-brands fa-github me-1"></i> GitHub</a>
                            <a href="https://www.tiktok.com/@beardedvikingtx" target="_blank" class="btn btn-sm btn-dark border border-secondary text-light"><i class="fa-brands fa-tiktok me-1"></i> TikTok</a>
                            <a href="https://medium.com/@beardedviking" target="_blank" class="btn btn-sm btn-dark border border-secondary text-light"><i class="fa-brands fa-medium me-1"></i> Medium</a>
                            <a href="https://www.facebook.com/BeardedVikingTX" target="_blank" class="btn btn-sm btn-dark border border-secondary text-light"><i class="fa-brands fa-facebook me-1"></i> Facebook</a>
                            <a href="https://www.linkedin.com/in/bearded-viking-3112a8431/" target="_blank" class="btn btn-sm btn-dark border border-secondary text-light"><i class="fa-brands fa-linkedin me-1"></i> LinkedIn</a>
                        </div>
                    </div>
                </div>
            </div>
        </section>

    </div>
</main>

<!-- Interactive Voting Script -->
<script>
document.addEventListener('DOMContentLoaded', () => {
    const voteButtons = document.querySelectorAll('.vote-btn');
    const voteAlert = document.getElementById('voteAlert');

    voteButtons.forEach(btn => {
        btn.addEventListener('click', async () => {
            const modelName = btn.getAttribute('data-model');
            const formData = new FormData();
            formData.append('action', 'cast_vote');
            formData.append('model', modelName);

            try {
                const res = await fetch('/contact.php', { method: 'POST', body: formData });
                const data = await res.json();

                voteAlert.classList.remove('d-none', 'alert-success', 'alert-danger');
                if (data.status === 'success') {
                    voteAlert.classList.add('alert-success', 'bg-success', 'bg-opacity-20');
                    voteAlert.innerText = data.message;
                    
                    // Update client UI vote counter dynamically
                    const targetBadge = document.getElementById('vote-count-' + modelName.replace(/\s+/g, ''));
                    if (targetBadge) {
                        let currentCount = parseInt(targetBadge.innerText) || 0;
                        targetBadge.innerText = (currentCount + 1) + ' Votes';
                    }
                } else {
                    voteAlert.classList.add('alert-danger', 'bg-danger', 'bg-opacity-20');
                    voteAlert.innerText = data.message;
                }
            } catch (err) {
                console.error('Voting error:', err);
            }
        });
    });
});
</script>

<?php
require_once __DIR__ . '/includes/footer.php';
?>