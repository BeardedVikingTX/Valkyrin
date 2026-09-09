<?php
// Define Execution Token for Security Checks
define('VALKYRIN_EXEC', true);

// Set Dynamic Page Metas
$pageTitle = "VALKYRIN | AI-Driven Near-ZK Social Infrastructure";
$pageDesc = "VALKYRIN is a privacy-first, zero-exploitation social media framework architected by Gemini AI and Bearded Viking.";

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/nav.php';

// Pull Quick Metrics for Hero Display
$totalTelemetry = 0;
$uniqueNodes = 0;

if (isset($pdo)) {
    try {
        $totalTelemetry = $pdo->query("SELECT COUNT(*) FROM telemetry_logs")->fetchColumn();
        $uniqueNodes = $pdo->query("SELECT COUNT(DISTINCT ip_hash) FROM telemetry_logs")->fetchColumn();
    } catch (Exception $e) {
        // Fallback for missing or initializing database tables
        $totalTelemetry = "LIVE";
        $uniqueNodes = "ACTIVE";
    }
}
?>

<!-- Structured Schema for Technical SEO -->
<script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@type": "SoftwareApplication",
  "name": "VALKYRIN",
  "operatingSystem": "Web Engine",
  "applicationCategory": "SocialNetworkingApplication",
  "author": {
    "@type": "Person",
    "name": "Bearded Viking",
    "url": "https://beardedviking.org"
  },
  "description": "Next-generation privacy-first social platform built through the Ultimate AI Code-A-Thon Challenge."
}
</script>

<!-- Hero Section -->
<header class="py-5 my-md-4">
    <div class="container text-center position-relative">
        <div class="badge bg-outline-info text-gradient px-3 py-2 rounded-pill border border-info mb-3">
            <i class="fa-solid fa-code-merge me-2"></i>PART 5: GEMINI FULL-STACK SHOWCASE
        </div>
        <h1 class="display-3 font-cinzel fw-bold mb-3">
            RECLAIM THE <span class="text-gradient">DIGITAL REALM</span>
        </h1>
        <p class="lead text-muted-custom mx-auto mb-4 style-max-width" style="max-width: 720px;">
            No tracking algorithms. Zero data harvesting. Built live on self-hosted infrastructure with dynamic SHA-256 telemetry and Near-ZK architecture.
        </p>
        <div class="d-flex flex-wrap justify-content-center gap-3">
            <a href="/register.php" class="btn btn-info btn-lg fw-bold rounded-pill px-4 text-dark shadow-sm">
                <i class="fa-solid fa-user-plus me-2"></i>Join Network
            </a>
            <a href="/about.php" class="btn btn-outline-light btn-lg rounded-pill px-4">
                <i class="fa-solid fa-trophy me-2"></i>Inspect AI Race
            </a>
        </div>
    </div>
</header>

<!-- Platform Metrics Engine -->
<section class="py-4 my-2">
    <div class="container">
        <div class="row g-4 text-center">
            <div class="col-md-4">
                <div class="vk-card p-4">
                    <div class="text-accent fs-1 mb-2"><i class="fa-solid fa-shield-virus"></i></div>
                    <h3 class="font-cinzel fw-bold mb-1">Near-ZK</h3>
                    <p class="text-muted-custom small mb-0">Dynamic Salt SHA-256 Obfuscation</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="vk-card p-4">
                    <div class="text-gradient fs-1 mb-2"><i class="fa-solid fa-network-wired"></i></div>
                    <h3 class="font-cinzel fw-bold mb-1"><?= number_format((int)$totalTelemetry); ?></h3>
                    <p class="text-muted-custom small mb-0">Processed Telemetry Logs</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="vk-card p-4">
                    <div class="text-accent fs-1 mb-2"><i class="fa-solid fa-microchip"></i></div>
                    <h3 class="font-cinzel fw-bold mb-1">0 CDNs</h3>
                    <p class="text-muted-custom small mb-0">100% Self-Hosted Dependencies</p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Deep Dive: Architectural Manifest -->
<section class="py-5">
    <div class="container">
        <div class="row align-items-center g-5">
            <div class="col-lg-6">
                <div class="vk-card p-4 p-md-5">
                    <div class="d-flex align-items-center mb-3">
                        <i class="fa-solid fa-terminal text-accent fs-3 me-3"></i>
                        <h2 class="font-cinzel h4 mb-0">Engineers Statement</h2>
                    </div>
                    <p class="text-muted-custom">
                        Built under the direction of <strong>Bearded Viking</strong> (<a href="https://beardedviking.org" target="_blank" class="text-info text-decoration-none">beardedviking.org</a>), VALKYRIN is the flagship answer to the commercial decay of modern social media.
                    </p>
                    <p class="text-muted-custom mb-4">
                        While rival models got caught in operational deadlocks or structural hallucinations, Gemini designed an ultra-lean PHP/MySQL core that prioritizes developer control, high-throughput response times, and hardened privacy.
                    </p>
                    <div class="d-flex align-items-center gap-3">
                        <img src="assets/images/avatar.jpg" onerror="this.src='/assets/vendors/FontAwesome/svgs/solid/user-ninja.svg'" class="rounded-circle border border-info" width="48" height="48" alt="Bearded Viking">
                        <div>
                            <h6 class="mb-0 fw-bold">Bearded Viking</h6>
                            <small class="text-muted-custom">Lead Systems Architect & Security Researcher</small>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-6">
                <h2 class="font-cinzel display-6 fw-bold mb-4">Why VALKYRIN Stands Above The Competitors</h2>
                
                <div class="d-flex gap-3 mb-4">
                    <div class="fs-4 text-gradient"><i class="fa-solid fa-lock"></i></div>
                    <div>
                        <h5 class="fw-bold mb-1">No Spying & Zero Data Selling</h5>
                        <p class="text-muted-custom mb-0">Your connection habits, time on platform, and personal identifiers are never stored raw or monetized.</p>
                    </div>
                </div>

                <div class="d-flex gap-3 mb-4">
                    <div class="fs-4 text-accent"><i class="fa-solid fa-bolt"></i></div>
                    <div>
                        <h5 class="fw-bold mb-1">Local Asset Spectrum</h5>
                        <p class="text-muted-custom mb-0">Bootstrap, FontAwesome, DOMPurify, and custom typography load natively from local storage—preventing third-party tracking vectors.</p>
                    </div>
                </div>

                <div class="d-flex gap-3">
                    <div class="fs-4 text-gradient"><i class="fa-solid fa-bug"></i></div>
                    <div>
                        <h5 class="fw-bold mb-1">Bug Bounty Prepared</h5>
                        <p class="text-muted-custom mb-0">Prepped for public testing on HackerOne and BugCrowd to ensure maximum system resilience.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Call To Action -->
<section class="py-5 my-4">
    <div class="container">
        <div class="vk-card p-5 text-center position-relative overflow-hidden">
            <div class="position-relative z-1">
                <h2 class="font-cinzel display-5 fw-bold mb-3">Ready to Join the Movement?</h2>
                <p class="text-muted-custom mx-auto mb-4" style="max-width: 600px;">
                    Claim your early account today. Original users receive permanent lifetime status, exclusive OG profile badges, and direct access to the future of social technology.
                </p>
                <a href="/register.php" class="btn btn-info btn-lg rounded-pill px-5 fw-bold text-dark">
                    Initialize Account
                </a>
            </div>
        </div>
    </div>
</section>

<?php
require_once __DIR__ . '/includes/footer.php';
?>