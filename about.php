<?php
/**
 * VALKYRIN :: Architectural Dossier & Multi-LLM Benchmark Manifesto
 */
$pageTitle = "VALKYRIN :: Operational Dossier & AI Benchmark Manifesto";
require_once __DIR__ . '/includes/header.php';

// Processing Voting Form Submissions
$voteSuccess = false;
$voteError = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'cast_vote') {
    // Validate CSRF Token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $voteError = "Security token mismatch. Telemetry signal rejected.";
    } else {
        $voterEmail = filter_var(trim($_POST['voter_email'] ?? ''), FILTER_VALIDATE_EMAIL);
        $voterName  = htmlspecialchars(trim($_POST['voter_name'] ?? ''));
        $selectedAi = htmlspecialchars(trim($_POST['selected_llm'] ?? ''));

        $validLlms = ['Gemini', 'Claude', 'ChatGPT', 'DeepSeek', 'CoPilot'];

        if (!$voterEmail || empty($voterName) || !in_array($selectedAi, $validLlms)) {
            $voteError = "Invalid telemetry inputs. Please complete all required voter fields.";
        } else {
            // STEP 1: Database Persistence Logic (PDO Prepared Statement)
            try {
                /*
                // Uncomment and set your active PDO connection ($pdo)
                $stmt = $pdo->prepare("INSERT INTO llm_votes (voter_name, voter_email, selected_llm, created_at) VALUES (:name, :email, :llm, NOW())");
                $stmt->execute([':name' => $voterName, ':email' => $voterEmail, ':llm' => $selectedAi]);
                */

                // STEP 2: Email Transmission Protocol (Voter Confirmation + Lead Engineer Alert)
                $leadEngineerEmail = "info@beardedviking.org";
                $subjectLead = "VALKYRIN :: New LLM Vote Cast by {$voterName}";
                $messageLead = "SYSTEM ALERT: A new vote has been logged in the Valkyrin Multi-LLM Race.\n\n"
                             . "Voter Name: {$voterName}\n"
                             . "Voter Email: {$voterEmail}\n"
                             . "Selected Node: {$selectedAi}\n"
                             . "Timestamp: " . date('Y-m-d H:i:s') . "\n";

                $subjectVoter = "VALKYRIN TELEMETRY :: Vote Confirmation";
                $messageVoter = "Greetings {$voterName},\n\n"
                              . "Your vote for '{$selectedAi}' has been permanently recorded in the Valkyrin Multi-LLM Benchmark DB.\n"
                              . "Thank you for participating in this 6-month digital social experiment.\n\n"
                              . "Skál,\nBearded Viking\nhttps://beardedviking.org";

                $headers = "From: Valkyrin Node <no-reply@beardedviking.org>\r\n"
                         . "Reply-To: info@beardedviking.org\r\n"
                         . "X-Mailer: PHP/" . phpversion();

                // Dispatch Emails
                @mail($leadEngineerEmail, $subjectLead, $messageLead, $headers);
                @mail($voterEmail, $subjectVoter, $messageVoter, $headers);

                $voteSuccess = true;
            } catch (Exception $e) {
                $voteError = "Database operational failure during vote insertion: " . $e->getMessage();
            }
        }
    }
}
?>

<!-- Dossier Hero Header -->
<div class="row mb-5 align-items-center">
    <div class="col-lg-10 mx-auto text-center">
        <span class="badge vk-status-badge px-3 py-2 fs-6 mb-3">
            <i class="fa-solid fa-book-bookmark me-2"></i>THE ARCHITECTURAL MANIFESTO
        </span>
        <h1 class="display-4 fw-bold vk-glow-title mb-3">BEHIND THE SHIELD OF VALKYRIN</h1>
        <p class="lead text-info font-monospace">
            An Academic Analysis of Resilient Architecture, Heritage, and Machine-Learning Benchmarking
        </p>
    </div>
</div>

<!-- Section 1: The Heritage & Operative Dossier -->
<section class="mb-5">
    <div class="vk-hud-card p-4 p-md-5">
        <div class="row align-items-center">
            <div class="col-lg-8">
                <h2 class="text-warning mb-3"><i class="fa-solid fa-shield-halved me-2"></i>OPERATIVE DOSSIER: THE BEARDED VIKING</h2>
                <p>
                    Every enduring system is forged over scarred, tested terrain. The Bearded Viking identity stems from an Irish-Viking ancestral lineage, blending raw Nordic resilience with modern cybernetic engineering. Having traversed life's crucible through adversity, grief, and rigorous self-reliance, the governing philosophy behind every project hosted on <strong>beardedviking.org</strong> is rooted in absolute durability and unyielding conviction.
                </p>
                <p>
                    Rather than treating software development as a sterile exercise in writing syntax, the operational objective is the construction of digital strongholds. Valkyrin represents the physical realization of this ethos: an interconnected, zero-trust social ecosystem designed to withstand external cybernetic threats while honoring the time-tested bonds of community, privacy, and technical mastery.
                </p>
            </div>
            <div class="col-lg-4 text-center mt-4 mt-lg-0">
                <div class="p-3 border border-info rounded bg-dark shadow-lg">
                    <i class="fa-solid fa-user-ninja fa-5x text-info mb-3"></i>
                    <h3 class="h5 text-uppercase">Operative Profile</h3>
                    <ul class="list-unstyled text-start small font-monospace mb-0">
                        <li><strong>Ancestral Telemetry:</strong> Irish Viking</li>
                        <li><strong>Resilience Rating:</strong> Unbreakable</li>
                        <li><strong>Core Domain:</strong> beardedviking.org</li>
                        <li><strong>Primary Stack:</strong> PHP, JS, MySQL, CSS3</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Section 2: The Multi-LLM Scientific Methodology -->
<section class="mb-5">
    <div class="vk-hud-card p-4 p-md-5">
        <h2 class="text-info mb-3"><i class="fa-solid fa-vials me-2"></i>THE SCIENTIFIC EXPERIMENT & BENCHMARK METHODOLOGY</h2>
        <p>
            The contemporary landscape of Artificial Intelligence is saturated with marketing assertions regarding model capability, logic depth, and full-stack software synthesis. To cut through speculative rhetoric, we initiated a rigorous, empirical 6-month experiment. Five premiere Large Language Model architectures—Google Gemini, Claude, ChatGPT, DeepSeek, and CoPilot—were tasked with independently engineering, deploying, and maintaining a high-performance, fully encrypted social network in real-time under identical hosting constraints on shared server infrastructure.
        </p>
        <p>
            Each LLM must satisfy identical functional parameters: implementing client-side/server-side AES-256 encryption, managing asynchronous AJAX updates, maintaining state integrity, constructing modular CSS components, and securing application vectors against OWASP Top 10 vulnerabilities. The final objective is evaluating which AI companion acts as the ultimate developer-tier collaborator, bridging structural accuracy with creative design execution.
        </p>

        <!-- Floating Competitor Visual Nodes -->
        <div class="row g-4 mt-4">
            <div class="col-md-4 text-center">
                <img src="assets/img/media/Gemini_Homepage.png" alt="Gemini Engine Preview" class="img-fluid vk-float-img mb-2">
                <h4 class="h6 text-info">GEMINI NODE</h4>
            </div>
            <div class="col-md-4 text-center">
                <img src="assets/img/media/Claude_Homepage.png" alt="Claude Engine Preview" class="img-fluid vk-float-img mb-2">
                <h4 class="h6 text-light">CLAUDE NODE</h4>
            </div>
            <div class="col-md-4 text-center">
                <img src="assets/img/media/ChatGPT_Homepage.png" alt="ChatGPT Engine Preview" class="img-fluid vk-float-img mb-2">
                <h4 class="h6 text-success">CHATGPT NODE</h4>
            </div>
        </div>
    </div>
</section>

<!-- Section 3: Dual-Step Multi-LLM Public Voting Engine -->
<section id="voting-module" class="mb-5">
    <div class="vk-hud-card p-4 p-md-5 border-info">
        <div class="row align-items-center">
            <div class="col-lg-6 mb-4 mb-lg-0">
                <h2 class="text-warning mb-3"><i class="fa-solid fa-check-to-slot me-2"></i>CAST YOUR TELEMETRY VOTE</h2>
                <p>
                    Who is demonstrating superior software engineering capability in this competition? Cast your vote below to influence the experiment's public ledger.
                </p>
                <p class="small text-muted font-monospace">
                    * Multi-Step Validation Protocol: Submissions are stored in the Valkyrin DB before initiating secure SMTP confirmation alerts to your email and the lead engineer at info@beardedviking.org.
                </p>
            </div>

            <div class="col-lg-6">
                <?php if ($voteSuccess): ?>
                    <div class="alert alert-success border-success text-center p-4 rounded" role="alert">
                        <i class="fa-solid fa-circle-check fa-3x mb-3 text-success"></i>
                        <h3 class="h5">TELEMETRY SIGNAL RECORDED</h3>
                        <p class="mb-0">Your vote has been saved to the database. Confirmation dispatches have been transmitted to your email and the lead engineer.</p>
                    </div>
                <?php else: ?>
                    <?php if (!empty($voteError)): ?>
                        <div class="alert alert-danger mb-3 font-monospace small"><?= $voteError; ?></div>
                    <?php endif; ?>

                    <form action="about.php#voting-module" method="POST" class="p-3 border border-secondary rounded bg-dark">
                        <input type="hidden" name="action" value="cast_vote">
                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token']; ?>">

                        <div class="mb-3">
                            <label for="voter_name" class="form-label text-info font-monospace">VOTER IDENTIFIER / NAME</label>
                            <input type="text" class="form-control bg-secondary text-white border-info" id="voter_name" name="voter_name" required placeholder="e.g., Alex Ragnarok">
                        </div>

                        <div class="mb-3">
                            <label for="voter_email" class="form-label text-info font-monospace">DISPATCH EMAIL ADDRESS</label>
                            <input type="email" class="form-control bg-secondary text-white border-info" id="voter_email" name="voter_email" required placeholder="name@domain.com">
                        </div>

                        <div class="mb-4">
                            <label for="selected_llm" class="form-label text-info font-monospace">SELECT LEADING LLM NODE</label>
                            <select class="form-select bg-secondary text-white border-info" id="selected_llm" name="selected_llm" required>
                                <option value="" selected disabled>Choose candidate...</option>
                                <option value="Gemini">Google Gemini (Valkyrin Engine)</option>
                                <option value="Claude">Claude (RavenWarp Engine)</option>
                                <option value="ChatGPT">ChatGPT (Nexora Engine)</option>
                                <option value="DeepSeek">DeepSeek (Nexus Valhalla Engine)</option>
                                <option value="CoPilot">CoPilot (SagaSphere Engine)</option>
                            </select>
                        </div>

                        <button type="submit" class="btn vk-btn-glow w-100 py-2">
                            <i class="fa-solid fa-paper-plane me-2"></i>TRANSMIT VOTE SIGNAL
                        </button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>