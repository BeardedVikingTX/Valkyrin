<?php
/**
 * VALKYRIN :: Telemetry Footer Engine
 */
?>
    </main> <!-- Close Main Viewport Node -->

    <footer class="vk-footer mt-auto py-4">
        <div class="container-fluid px-4">
            <div class="row gy-4 align-items-center">
                
                <!-- Left: Branding & Core Meta -->
                <div class="col-lg-4 text-center text-lg-start">
                    <div class="d-flex align-items-center justify-content-center justify-content-lg-start mb-2">
                        <i class="fa-solid fa-terminal vk-footer-icon me-2"></i>
                        <span class="vk-footer-brand">VALKYRIN ENGINE v1.0</span>
                    </div>
                    <p class="vk-footer-subtext mb-0">
                        Zero-Trust Encrypted Network Node &copy; <?= date('Y'); ?> 
                        <a href="https://beardedviking.org" target="_blank" rel="noopener" class="vk-footer-link">Bearded Viking</a>. All Systems Operational.
                    </p>
                </div>

                <!-- Center: Telemetry Status (DOM/AJAX Target) -->
                <div class="col-lg-4 text-center">
                    <div class="vk-telemetry-box d-inline-flex align-items-center px-3 py-2 rounded">
                        <span class="vk-telemetry-item me-3">
                            <i class="fa-solid fa-microchip text-info me-1"></i> 
                            LATENCY: <span id="vk-ping-display">-- ms</span>
                        </span>
                        <span class="vk-telemetry-divider">|</span>
                        <span class="vk-telemetry-item ms-3">
                            <i class="fa-solid fa-lock text-success me-1"></i> 
                            CIPHER: <span class="text-uppercase">AES-256</span>
                        </span>
                    </div>
                </div>

                <!-- Right: Sitemap & Quick Nav -->
                <div class="col-lg-4 text-center text-lg-end">
                    <ul class="list-inline mb-0 vk-footer-nav">
                        <li class="list-inline-item me-3">
                            <a href="/sitemap.xml" class="vk-footer-link" target="_blank">
                                <i class="fa-solid fa-sitemap me-1"></i> SITEMAP
                            </a>
                        </li>
                        <li class="list-inline-item me-3">
                            <a href="/about.php" class="vk-footer-link">SYSTEM INFO</a>
                        </li>
                        <li class="list-inline-item">
                            <a href="/contact.php" class="vk-footer-link">DISPATCH SIGNAL</a>
                        </li>
                    </ul>
                </div>

            </div>
        </div>
    </footer>

    <!-- Core Scripts: Bootstrap 5 Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>

    <!-- Custom Valkyrin Functions (DOM/AJAX Telemetry Monitoring) -->
    <script src="/assets/js/functions.js?v=<?= filemtime(__DIR__ . '/../assets/js/functions.js') ?? time(); ?>"></script>
</body>
</html>