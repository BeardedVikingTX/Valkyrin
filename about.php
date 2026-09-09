<?php
define('VALKYRIN_EXEC', true);

$pageTitle = "VALKYRIN | Mission, AI Race Metrics & Live Telemetry";
$pageDesc = "Complete architectural analysis of the AI Code-A-Thon, updated model rankings (Gemini, Claude, Copilot, ChatGPT, DeepSeek), and live MySQL database telemetry.";

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/nav.php';
?>

<!-- Page Header Hero -->
<header class="py-5 bg-opacity-10 border-bottom border-secondary">
    <div class="container text-center">
        <div class="badge bg-danger text-light px-3 py-2 rounded-pill mb-3">
            <i class="fa-solid fa-microchip me-2"></i>THE ULTIMATE AI CODE-A-THON: PART 5
        </div>
        <h1 class="display-4 font-cinzel fw-bold mb-3">
            ARCHITECTURAL <span class="text-gradient">MANIFESTO</span>
        </h1>
        <p class="lead text-muted-custom mx-auto mb-0" style="max-width: 800px;">
            A detailed analysis of the VALKYRIN framework, real-time database telemetry, Near-ZK privacy vectors, and comprehensive rankings across 5 major AI models.
        </p>
    </div>
</header>

<main class="py-5">
    <div class="container">
        
        <!-- Section 1: Context & Standings -->
        <article class="mb-5">
            <div class="row g-4 align-items-center">
                <div class="col-lg-7">
                    <h2 class="font-cinzel h3 text-gradient mb-3">The Genesis & Rules of the Challenge</h2>
                    <p class="text-muted-custom">
                        The <strong>Ultimate AI Code-A-Thon Challenge</strong> was created by Chief Systems Architect <strong>Bearded Viking</strong> to push top artificial intelligence models past basic code snippets into real-world production engineering. Unlike traditional benchmarks, this challenge requires full-stack execution: designing dynamic UI/UX layouts, constructing database schema, ensuring path stability, and maintaining absolute operational security on self-hosted infrastructure.
                    </p>
                    <p class="text-muted-custom">
                        The evaluation criteria reward models that build modular PHP/MySQL architectures while penalizing phantom pathing, route loops, and weak credential handling. As privacy threats escalate, the platform enforces <strong>Near-Zero Knowledge (Near-ZK)</strong> telemetry hashing across all incoming requests.
                    </p>
                </div>
                <div class="col-lg-5">
                    <div class="vk-card p-4 text-center">
                        <i class="fa-solid fa-trophy text-accent display-3 mb-3"></i>
                        <h4 class="font-cinzel mb-2">The AI Race Standings</h4>
                        <p class="small text-muted-custom mb-3">Evaluated on UI/UX, Logic, & System Reliability</p>
                        <div class="list-group list-group-flush text-start">
                            <div class="list-group-item bg-transparent text-light border-secondary d-flex justify-content-between align-items-center">
                                <span><i class="fa-solid fa-crown text-warning me-2"></i>Gemini AI</span>
                                <span class="badge bg-success">1st Place / Leader</span>
                            </div>
                            <div class="list-group-item bg-transparent text-light border-secondary d-flex justify-content-between align-items-center">
                                <span><i class="fa-solid fa-bolt text-info me-2"></i>Claude</span>
                                <span class="badge bg-info text-dark">2nd Place / Challenger</span>
                            </div>
                            <div class="list-group-item bg-transparent text-light border-secondary d-flex justify-content-between align-items-center">
                                <span><i class="fa-solid fa-code text-primary me-2"></i>Copilot</span>
                                <span class="badge bg-primary">3rd Place / Solid</span>
                            </div>
                            <div class="list-group-item bg-transparent text-light border-secondary d-flex justify-content-between align-items-center">
                                <span><i class="fa-solid fa-comments text-secondary me-2"></i>ChatGPT</span>
                                <span class="badge bg-secondary">4th Place (5.0 Score)</span>
                            </div>
                            <div class="list-group-item bg-transparent text-light border-secondary d-flex justify-content-between align-items-center">
                                <span class="text-muted"><i class="fa-solid fa-skull text-danger me-2"></i>DeepSeek</span>
                                <span class="badge bg-danger">Disqualified</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </article>

        <!-- Section 2: Real-Time Dynamic Database Telemetry -->
        <section class="mb-5">
            <div class="vk-card p-4 p-md-5">
                <div class="row align-items-center">
                    <div class="col-lg-5 mb-4 mb-lg-0">
                        <div class="d-flex align-items-center justify-content-between mb-3">
                            <h3 class="font-cinzel text-accent mb-0">Live Database Telemetry</h3>
                            <span class="badge bg-success border border-light" id="liveIndicator">
                                <i class="fa-solid fa-signal me-1"></i>LIVE FEED
                            </span>
                        </div>
                        <p class="text-muted-custom">
                            This tracker queries the MySQL <code>telemetry_logs</code> table via an asynchronous backend API every 3 seconds. Incoming traffic is classified dynamically into security buckets while raw IPs are obscured using dynamic SHA-256 salted hashing.
                        </p>
                        
                        <!-- Dynamic Metric Cards -->
                        <div class="row g-2 mb-3">
                            <div class="col-6">
                                <div class="bg-dark p-3 rounded border border-secondary text-center">
                                    <small class="text-muted-custom d-block">TOTAL LOGS</small>
                                    <span class="fs-4 fw-bold text-info" id="statTotalLogs">--</span>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="bg-dark p-3 rounded border border-secondary text-center">
                                    <small class="text-muted-custom d-block">LAST SYNC</small>
                                    <span class="fs-6 fw-bold text-gradient d-block mt-1" id="statLastSync">--:--:--</span>
                                </div>
                            </div>
                        </div>

                        <div class="p-3 border border-secondary rounded bg-dark">
                            <small class="text-info font-monospace"><i class="fa-solid fa-terminal me-2"></i>Client Node Hash:</small>
                            <code class="d-block text-truncate text-muted small mt-1">
                                <?= hash('sha256', ($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1') . date('Y-m-d')); ?>
                            </code>
                        </div>
                    </div>
                    
                    <div class="col-lg-7">
                        <div class="p-3 bg-dark rounded border border-secondary">
                            <h5 class="font-cinzel text-center small mb-3 text-muted">REAL-TIME TRAFFIC CLASSIFICATION (POLLING MYSQL)</h5>
                            <div style="height: 280px; position: relative;">
                                <canvas id="telemetryChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Section 3: Full AI Model Competitor Matrix -->
        <section class="mb-5">
            <h2 class="font-cinzel h3 text-center mb-4">Complete Competitor Performance Matrix</h2>
            <div class="table-responsive">
                <table class="table table-dark table-hover align-middle border border-secondary vk-card">
                    <thead>
                        <tr class="table-active font-cinzel">
                            <th>AI Model</th>
                            <th>UI/UX Precision</th>
                            <th>Back-End Logic</th>
                            <th>Path Integrity</th>
                            <th>Rank & Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td class="fw-bold"><i class="fa-solid fa-robot text-info me-2"></i>Gemini AI</td>
                            <td><span class="text-info">9.5/10</span> (Sleek Cyberpunk UX)</td>
                            <td><span class="text-success">9.5/10</span> (Hardened PDO Engine)</td>
                            <td><span class="text-success">10/10</span> (Flawless Routing)</td>
                            <td><span class="badge bg-success">1st Place / Leader</span></td>
                        </tr>
                        <tr>
                            <td class="fw-bold"><i class="fa-solid fa-bolt text-info me-2"></i>Claude</td>
                            <td><span class="text-info">9.2/10</span> (Clean Structure)</td>
                            <td><span class="text-info">9.0/10</span> (Strong OOP Syntax)</td>
                            <td><span class="text-info">9.0/10</span> (High Accuracy)</td>
                            <td><span class="badge bg-info text-dark">2nd Place / Contender</span></td>
                        </tr>
                        <tr>
                            <td class="fw-bold"><i class="fa-solid fa-code text-primary me-2"></i>Copilot</td>
                            <td><span class="text-warning">7.8/10</span> (Standard Modern)</td>
                            <td><span class="text-info">8.0/10</span> (Reliable Snippets)</td>
                            <td><span class="text-warning">7.5/10</span> (Minor Path Drift)</td>
                            <td><span class="badge bg-primary">3rd Place / Solid</span></td>
                        </tr>
                        <tr>
                            <td class="fw-bold"><i class="fa-solid fa-comments text-secondary me-2"></i>ChatGPT</td>
                            <td><span class="text-warning">6.0/10</span> (Generic Layouts)</td>
                            <td><span class="text-warning">6.0/10</span> (Loop Susceptible)</td>
                            <td><span class="text-warning">5.0/10</span> (Needs Guidance)</td>
                            <td><span class="badge bg-secondary">4th Place (5.0)</span></td>
                        </tr>
                        <tr>
                            <td class="fw-bold text-muted"><i class="fa-solid fa-skull text-danger me-2"></i>DeepSeek</td>
                            <td><span class="text-success">9.0/10</span> (Great Aesthetics)</td>
                            <td><span class="text-danger">2.0/10</span> (Broken PDO Logic)</td>
                            <td><span class="text-danger">1.0/10</span> (Phantom Directories)</td>
                            <td><span class="badge bg-danger">Disqualified</span></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <!-- Section 4: Lead Engineer Dossier -->
        <section class="mb-4">
            <div class="vk-card p-4 p-md-5">
                <div class="row align-items-center g-4">
                    <div class="col-md-3 text-center">
                        <i class="fa-solid fa-user-shield text-accent display-1 mb-3"></i>
                        <h5 class="font-cinzel fw-bold mb-0">Bearded Viking</h5>
                        <small class="text-muted-custom">Chief Systems Architect</small>
                    </div>
                    <div class="col-md-9">
                        <h3 class="font-cinzel text-gradient mb-2">Behind the Console</h3>
                        <p class="text-muted-custom">
                            Operating out of Texas and Illinois headquarters, <strong>Bearded Viking</strong> is an independent developer, web security researcher, and creator of the <strong>VALKYRIN</strong> framework. Maintaining active project hubs across GitHub, Medium, LinkedIn, and X, the Bearded Viking initiative focuses on exposing security vulnerabilities in raw AI-generated code while building resilient, user-sovereign web software.
                        </p>
                        <div class="d-flex flex-wrap gap-2 mt-3">
                            <span class="badge bg-dark border border-secondary text-info"><i class="fa-solid fa-shield-halved me-1"></i> Web Security</span>
                            <span class="badge bg-dark border border-secondary text-info"><i class="fa-code me-1"></i> Full-Stack PHP/MySQL</span>
                            <span class="badge bg-dark border border-secondary text-info"><i class="fa-brands fa-linux me-1"></i> LiteSpeed Server Admin</span>
                            <span class="badge bg-dark border border-secondary text-info"><i class="fa-solid fa-lock me-1"></i> Near-ZK Architecture</span>
                        </div>
                    </div>
                </div>
            </div>
        </section>

    </div>
</main>

<!-- Live Telemetry Polling Script -->
<script>
document.addEventListener('DOMContentLoaded', () => {
    const ctx = document.getElementById('telemetryChart');
    let telemetryChart = null;

    if (ctx && typeof Chart !== 'undefined') {
        // Initialize Chart with empty datasets
        telemetryChart = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: ['Standard Browsers', 'Crawler Bots', 'Automated Tools'],
                datasets: [{
                    data: [0, 0, 0],
                    backgroundColor: ['#00f2fe', '#ff2a6d', '#4facfe'],
                    borderColor: '#0f141d',
                    borderWidth: 3
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                animation: { duration: 500 },
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: { color: '#e0e6ed', font: { family: 'Cinzel' } }
                    }
                }
            }
        });

        // Function to Fetch Data from Real-Time Endpoint
        async function updateTelemetryChart() {
            try {
                const response = await fetch('/api/telemetry.php');
                const data = await response.json();

                if (data.status === 'success') {
                    // Update Chart Data Arrays
                    telemetryChart.data.datasets[0].data = [
                        data.categories['Standard Browser'] || 0,
                        data.categories['Crawler Bot'] || 0,
                        data.categories['Automated Tool'] || 0
                    ];
                    telemetryChart.update();

                    // Update Metric Badges
                    document.getElementById('statTotalLogs').innerText = data.total_logs;
                    document.getElementById('statLastSync').innerText = data.timestamp;
                }
            } catch (err) {
                console.error("Telemetry fetch error:", err);
            }
        }

        // Trigger initial fetch and set 3-second live poll interval
        updateTelemetryChart();
        setInterval(updateTelemetryChart, 3000);
    }
});
</script>

<?php
require_once __DIR__ . '/includes/footer.php';
?>