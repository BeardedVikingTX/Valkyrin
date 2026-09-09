<?php
/**
 * VALKYRIN :: Signal Dispatch Module (Contact Interface)
 */
$pageTitle = "VALKYRIN :: Transmit Signal & Command Contact";
require_once __DIR__ . '/includes/header.php';

$dispatchSuccess = false;
$dispatchError   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'transmit_signal') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $dispatchError = "Security token mismatch. Telemetry signal rejected.";
    } else {
        $senderName    = htmlspecialchars(trim($_POST['sender_name'] ?? ''));
        $senderEmail   = filter_var(trim($_POST['sender_email'] ?? ''), FILTER_VALIDATE_EMAIL);
        $signalSubject = htmlspecialchars(trim($_POST['signal_subject'] ?? ''));
        $signalPayload = htmlspecialchars(trim($_POST['signal_payload'] ?? ''));

        if (!$senderEmail || empty($senderName) || empty($signalSubject) || empty($signalPayload)) {
            $dispatchError = "Incomplete telemetry payload. Please verify all mandatory fields.";
        } else {
            // STEP 1: Log Signal Dispatch to Database (Optional Log Backup)
            try {
                if (file_exists(__DIR__ . '/config/database.php')) {
                    require_once __DIR__ . '/config/database.php';
                    $pdo = get_db_connection();
                    
                    // Basic table initialization check/insert
                    $stmt = $pdo->prepare("
                        INSERT INTO llm_votes (voter_name, voter_email, selected_llm, ip_address, user_agent) 
                        VALUES (:name, :email, :llm, :ip, :ua)
                    ");
                }
            } catch (Exception $e) {
                // Silently bypass DB logging if table isn't assigned yet so email dispatch succeeds
                error_log("Contact DB Log Warning: " . $e->getMessage());
            }

            // STEP 2: Dispatch SMTP Signal to Lead Engineer
            $leadEngineerEmail = "info@beardedviking.org";
            $emailSubject      = "VALKYRIN DISPATCH :: {$signalSubject}";
            
            $emailBody  = "=== VALKYRIN INCOMING SIGNAL TRANSMISSION ===\n\n";
            $emailBody .= "Sender Name:   {$senderName}\n";
            $emailBody .= "Sender Email:  {$senderEmail}\n";
            $emailBody .= "Signal Subject:{$signalSubject}\n";
            $emailBody .= "Timestamp:     " . date('Y-m-d H:i:s') . "\n";
            $emailBody .= "Originating IP: " . ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0') . "\n\n";
            $emailBody .= "=== TRANSMITTED PAYLOAD ===\n";
            $emailBody .= $signalPayload . "\n\n";
            $emailBody .= "=============================================";

            $headers  = "From: Valkyrin Node <no-reply@beardedviking.org>\r\n";
            $headers .= "Reply-To: {$senderEmail}\r\n";
            $headers .= "X-Mailer: PHP/" . phpversion();

            if (@mail($leadEngineerEmail, $emailSubject, $emailBody, $headers)) {
                $dispatchSuccess = true;
            } else {
                $dispatchError = "Mail transport agent error. Unable to route packet to info@beardedviking.org.";
            }
        }
    }
}
?>

<!-- Contact Hero Banner -->
<div class="row mb-5 align-items-center">
    <div class="col-lg-10 mx-auto text-center">
        <span class="badge vk-status-badge px-3 py-2 fs-6 mb-3">
            <i class="fa-solid fa-satellite-dish me-2"></i>SUB-SPACE SIGNAL TRANSMISSION
        </span>
        <h1 class="display-4 fw-bold vk-glow-title mb-3">INITIATE CONTACT SIGNAL</h1>
        <p class="lead text-info font-monospace">
            Direct Telemetry Routing to Command HQ & Lead Engineer
        </p>
    </div>
</div>

<div class="row g-4 mb-5">
    
    <!-- Left Column: Command HQ Telemetry & Social Channels -->
    <div class="col-lg-5">
        <div class="vk-hud-card p-4 h-100">
            <h2 class="text-warning mb-4"><i class="fa-solid fa-compass-drafting me-2"></i>COMMAND HQ NODES</h2>
            
            <!-- Geographical Command Posts -->
            <div class="mb-4 p-3 border border-info rounded bg-dark">
                <h3 class="h6 text-info text-uppercase font-monospace mb-2"><i class="fa-solid fa-location-dot me-2"></i>Primary Datacenter Operations</h3>
                <p class="small text-muted mb-1"><strong>Node Alpha:</strong> Texas, United States</p>
                <p class="small text-muted mb-0"><strong>Node Beta:</strong> Illinois, United States</p>
            </div>

            <div class="mb-4 p-3 border border-secondary rounded bg-dark">
                <h3 class="h6 text-info text-uppercase font-monospace mb-2"><i class="fa-solid fa-envelope-open-text me-2"></i>Direct Dispatch Address</h3>
                <a href="mailto:info@beardedviking.org" class="vk-footer-link fs-6">info@beardedviking.org</a>
            </div>

            <!-- Social Media Array -->
            <h3 class="h6 text-warning text-uppercase font-monospace mb-3"><i class="fa-solid fa-share-nodes me-2"></i>Public Signal Relay Array</h3>
            <div class="d-flex flex-column gap-2">
                <a href="https://beardedviking.medium.com/" target="_blank" class="btn vk-btn-outline btn-sm text-start">
                    <i class="fa-brands fa-medium me-2 text-warning"></i>Medium Articles
                </a>
                <a href="https://github.com/BeardedVikingTX" target="_blank" class="btn vk-btn-outline btn-sm text-start">
                    <i class="fa-brands fa-github me-2 text-info"></i>GitHub Repositories
                </a>
                <a href="https://www.linkedin.com/in/bearded-viking-3112a8431/" target="_blank" class="btn vk-btn-outline btn-sm text-start">
                    <i class="fa-brands fa-linkedin me-2 text-primary"></i>LinkedIn Network
                </a>
                <a href="https://x.com/TXBeardedViking" target="_blank" class="btn vk-btn-outline btn-sm text-start">
                    <i class="fa-brands fa-x-twitter me-2 text-light"></i>X (Twitter) Feed
                </a>
                <a href="https://www.facebook.com/BeardedVikingTX" target="_blank" class="btn vk-btn-outline btn-sm text-start">
                    <i class="fa-brands fa-facebook me-2 text-primary"></i>Facebook Page
                </a>
                <a href="https://www.tiktok.com/@beardedvikingtx" target="_blank" class="btn vk-btn-outline btn-sm text-start">
                    <i class="fa-brands fa-tiktok me-2 text-danger"></i>TikTok Broadcasts
                </a>
            </div>
        </div>
    </div>

    <!-- Right Column: Encrypted Contact Dispatch Form -->
    <div class="col-lg-7">
        <div class="vk-hud-card p-4 p-md-5 h-100 border-info">
            <h2 class="text-info mb-3"><i class="fa-solid fa-paper-plane me-2"></i>TRANSMIT DISPATCH PAYLOAD</h2>
            <p class="small text-muted font-monospace mb-4">
                Transmissions sent via this module are sanitized and delivered directly to the Lead Engineer's desk at info@beardedviking.org.
            </p>

            <?php if ($dispatchSuccess): ?>
                <div class="alert alert-success border-success text-center p-4 rounded" role="alert">
                    <i class="fa-solid fa-satellite-dish fa-3x mb-3 text-success"></i>
                    <h3 class="h5">SIGNAL DELIVERED</h3>
                    <p class="mb-0">Your dispatch packet has been successfully routed to info@beardedviking.org. Standby for a response signal.</p>
                </div>
            <?php else: ?>
                <?php if (!empty($dispatchError)): ?>
                    <div class="alert alert-danger mb-3 font-monospace small"><?= $dispatchError; ?></div>
                <?php endif; ?>

                <form action="contact.php" method="POST" class="p-3 border border-secondary rounded bg-dark">
                    <input type="hidden" name="action" value="transmit_signal">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token']; ?>">

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="sender_name" class="form-label text-info font-monospace">SENDER CALLSIGN / NAME</label>
                            <input type="text" class="form-control bg-secondary text-white border-info" id="sender_name" name="sender_name" required placeholder="e.g., Operative Odin">
                        </div>

                        <div class="col-md-6">
                            <label for="sender_email" class="form-label text-info font-monospace">RETURN DISPATCH EMAIL</label>
                            <input type="email" class="form-control bg-secondary text-white border-info" id="sender_email" name="sender_email" required placeholder="name@domain.com">
                        </div>

                        <div class="col-12">
                            <label for="signal_subject" class="form-label text-info font-monospace">SIGNAL SUBJECT</label>
                            <input type="text" class="form-control bg-secondary text-white border-info" id="signal_subject" name="signal_subject" required placeholder="e.g., Security Inquiry / Partnership Signal">
                        </div>

                        <div class="col-12">
                            <label for="signal_payload" class="form-label text-info font-monospace">TRANSMISSION PAYLOAD (MESSAGE)</label>
                            <textarea class="form-control bg-secondary text-white border-info" id="signal_payload" name="signal_payload" rows="5" required placeholder="Type your encrypted message here..."></textarea>
                        </div>

                        <div class="col-12 mt-4">
                            <button type="submit" class="btn vk-btn-glow w-100 py-2">
                                <i class="fa-solid fa-bolt me-2"></i>DISPATCH SIGNAL TO HQ
                            </button>
                        </div>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </div>

</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>