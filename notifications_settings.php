<?php
define('VALKYRIN_EXEC', true);
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: /login.php");
    exit();
}

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/nav.php';

$userId = (int)$_SESSION['user_id'];
$message = '';

// Handle Settings Update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $channel = $_POST['channel'] ?? 'both';
    $connReq = isset($_POST['notify_connection_request']) ? 1 : 0;
    $react = isset($_POST['notify_post_reaction']) ? 1 : 0;
    $comment = isset($_POST['notify_post_comment']) ? 1 : 0;
    $netPost = isset($_POST['notify_network_post']) ? 1 : 0;

    $stmt = $pdo->prepare("
        INSERT INTO user_notification_settings (user_id, channel, notify_connection_request, notify_post_reaction, notify_post_comment, notify_network_post)
        VALUES (:uid, :channel, :conn, :react, :comm, :post)
        ON DUPLICATE KEY UPDATE 
            channel = VALUES(channel),
            notify_connection_request = VALUES(notify_connection_request),
            notify_post_reaction = VALUES(notify_post_reaction),
            notify_post_comment = VALUES(notify_post_comment),
            notify_network_post = VALUES(notify_network_post)
    ");
    $stmt->execute([
        ':uid' => $userId,
        ':channel' => $channel,
        ':conn' => $connReq,
        ':react' => $react,
        ':comm' => $comment,
        ':post' => $netPost
    ]);

    $message = "Notification matrix updated successfully!";
}

// Fetch current preferences
$stmt = $pdo->prepare("SELECT * FROM user_notification_settings WHERE user_id = :uid");
$stmt->execute([':uid' => $userId]);
$settings = $stmt->fetch(PDO::FETCH_ASSOC) ?: [
    'channel' => 'both',
    'notify_connection_request' => 1,
    'notify_post_reaction' => 1,
    'notify_post_comment' => 1,
    'notify_network_post' => 1
];
?>

<main class="py-5">
    <div class="container" style="max-width: 650px;">
        <div class="vk-card p-4 border border-secondary rounded">
            <h2 class="font-cinzel text-info mb-3"><i class="fa-solid fa-sliders me-2"></i>Telemetry Preferences</h2>
            <p class="text-muted small mb-4">Configure how VALKYRIN transmits network signals and alerts to your node.</p>

            <?php if (!empty($message)): ?>
                <div class="alert alert-success bg-success bg-opacity-20 text-light border-0 mb-4"><?= htmlspecialchars($message); ?></div>
            <?php endif; ?>

            <form method="POST" action="">
                
                <h5 class="text-light font-cinzel mb-3">Delivery Channel</h5>
                <div class="mb-4">
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="radio" name="channel" id="c_both" value="both" <?= $settings['channel'] === 'both' ? 'checked' : ''; ?>>
                        <label class="form-check-label text-light" for="c_both">
                            <strong>Both (Recommended)</strong> — Receive browser push alerts & emails.
                        </label>
                    </div>
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="radio" name="channel" id="c_push" value="push" <?= $settings['channel'] === 'push' ? 'checked' : ''; ?>>
                        <label class="form-check-label text-light" for="c_push">
                            <strong>Push Notifications Only</strong> — Browser-based popups only.
                        </label>
                    </div>
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="radio" name="channel" id="c_email" value="email" <?= $settings['channel'] === 'email' ? 'checked' : ''; ?>>
                        <label class="form-check-label text-light" for="c_email">
                            <strong>Email Notifications Only</strong> — Send to your registered email.
                        </label>
                    </div>
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="radio" name="channel" id="c_none" value="none" <?= $settings['channel'] === 'none' ? 'checked' : ''; ?>>
                        <label class="form-check-label text-light" for="c_none">
                            <strong>None</strong> — Silent mode (Navigation icon badge only).
                        </label>
                    </div>
                </div>

                <hr class="border-secondary mb-4">

                <h5 class="text-light font-cinzel mb-3">Alert Triggers</h5>
                <div class="form-check form-switch mb-3">
                    <input class="form-check-input" type="checkbox" name="notify_connection_request" id="trig_conn" value="1" <?= $settings['notify_connection_request'] ? 'checked' : ''; ?>>
                    <label class="form-check-label text-light" for="trig_conn">Connection Requests & Approvals</label>
                </div>
                <div class="form-check form-switch mb-3">
                    <input class="form-check-input" type="checkbox" name="notify_post_reaction" id="trig_react" value="1" <?= $settings['notify_post_reaction'] ? 'checked' : ''; ?>>
                    <label class="form-check-label text-light" for="trig_react">Reactions on your broadcasts</label>
                </div>
                <div class="form-check form-switch mb-3">
                    <input class="form-check-input" type="checkbox" name="notify_post_comment" id="trig_comm" value="1" <?= $settings['notify_post_comment'] ? 'checked' : ''; ?>>
                    <label class="form-check-label text-light" for="trig_comm">Comments on your broadcasts</label>
                </div>
                <div class="form-check form-switch mb-4">
                    <input class="form-check-input" type="checkbox" name="notify_network_post" id="trig_post" value="1" <?= $settings['notify_network_post'] ? 'checked' : ''; ?>>
                    <label class="form-check-label text-light" for="trig_post">New broadcasts from connected nodes</label>
                </div>

                <button type="submit" class="btn btn-info rounded-pill px-4 fw-bold text-dark">Save Telemetry Matrix</button>
            </form>

        </div>
    </div>
</main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>