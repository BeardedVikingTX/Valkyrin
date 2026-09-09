<?php
if (!defined('VALKYRIN_EXEC')) {
    header("HTTP/1.1 403 Forbidden");
    exit("Access Denied");
}
?>
    <footer class="vk-footer py-4 mt-auto">
        <div class="container text-center text-md-between d-flex flex-column flex-md-row justify-content-between align-items-center gap-3">
            <div class="text-muted-custom small">
                &copy; <?= date('Y'); ?> <strong class="text-gradient">VALKYRIN</strong>. Built Near-ZK. Zero Data Exploitation.
            </div>
            <div class="d-flex gap-3 fs-5">
                <a href="https://github.com/BeardedVikingTX" target="_blank" class="text-muted-custom"><i class="fa-brands fa-github"></i></a>
                <a href="https://medium.com" target="_blank" class="text-muted-custom"><i class="fa-brands fa-medium"></i></a>
                <a href="https://x.com" target="_blank" class="text-muted-custom"><i class="fa-brands fa-x-twitter"></i></a>
            </div>
        </div>
    </footer>

    <!-- Local Core Script Stack -->
    <script src="/assets/vendors/Bootstrap/js/bootstrap.bundle.min.js"></script>
    <script src="/assets/vendors/DOMPurify/purify.min.js"></script>
    <script src="/assets/vendors/Canvas-Confetti/confetti.browser.min.js"></script>
    <script src="/assets/vendors/ChartJS/chart.umd.js"></script>
    <script src="/assets/js/main.js"></script>
    <script>
        // Browser Push Notification Integration
        if ('Notification' in window && Notification.permission !== 'granted' && Notification.permission !== 'denied') {
            // Request permission on first user interaction
            document.addEventListener('click', function askPermission() {
                Notification.requestPermission().then(permission => {
                    if (permission === 'granted') {
                        console.log('VALKYRIN Push Notifications Authorized.');
                    }
                });
                document.removeEventListener('click', askPermission);
            }, { once: true });
        }
        
        // Function to trigger a browser push popup dynamically
        function triggerPushNotification(title, body, url = '/notifications.php') {
            if ('Notification' in window && Notification.permission === 'granted') {
                const notif = new Notification(title, {
                    body: body,
                    icon: '/assets/images/logo.png' // Replace with your logo path
                });
                notif.onclick = function() {
                    window.location.href = url;
                };
            }
        }
    </script>
</body>
</html>