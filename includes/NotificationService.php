<?php
if (!defined('VALKYRIN_EXEC')) {
    exit('Direct access forbidden.');
}

class NotificationService {
    private $pdo;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    /**
     * Main method to trigger notifications
     */
    public function sendNotification(int $recipientId, int $actorId, string $type, ?int $entityId, string $message, string $targetUrl = '/dashboard.php') {
        // Do not notify self
        if ($recipientId === $actorId) {
            return false;
        }

        // Fetch recipient user and preferences
        $stmt = $this->pdo->prepare("
            SELECT u.email, u.username, u.display_name, 
                   COALESCE(ns.channel, 'both') as channel,
                   COALESCE(ns.notify_connection_request, 1) as notify_conn,
                   COALESCE(ns.notify_post_reaction, 1) as notify_react,
                   COALESCE(ns.notify_post_comment, 1) as notify_comm,
                   COALESCE(ns.notify_network_post, 1) as notify_post
            FROM users u
            LEFT JOIN user_notification_settings ns ON u.id = ns.user_id
            WHERE u.id = :id
        ");
        $stmt->execute([':id' => $recipientId]);
        $recipient = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$recipient) {
            return false;
        }

        // Check if user disabled this specific notification category
        if ($type === 'connection_request' && !$recipient['notify_conn']) return false;
        if ($type === 'post_reaction' && !$recipient['notify_react']) return false;
        if ($type === 'post_comment' && !$recipient['notify_comm']) return false;
        if ($type === 'network_post' && !$recipient['notify_post']) return false;

        // 1. Insert In-App Notification (Always stored so nav badge counts work)
        $insert = $this->pdo->prepare("
            INSERT INTO notifications (user_id, actor_id, type, entity_id, message) 
            VALUES (:user_id, :actor_id, :type, :entity_id, :message)
        ");
        $insert->execute([
            ':user_id' => $recipientId,
            ':actor_id' => $actorId,
            ':type' => $type,
            ':entity_id' => $entityId,
            ':message' => $message
        ]);

        $channel = $recipient['channel'];

        // 2. Dispatch Email Notification
        if (in_array($channel, ['email', 'both'])) {
            $this->sendEmail($recipient['email'], $recipient['display_name'] ?: $recipient['username'], $type, $message, $targetUrl);
        }

        return true;
    }

    /**
     * Simple HTML Email Sender
     */
    private function sendEmail(string $toEmail, string $recipientName, string $type, string $message, string $targetUrl) {
        $subject = "VALKYRIN System Alert: " . ucwords(str_replace('_', ' ', $type));
        $fullUrl = "https://" . ($_SERVER['HTTP_HOST'] ?? 'beardedviking.org') . $targetUrl;

        $body = "
        <html>
        <body style='background-color:#121212; color:#e0e0e0; font-family:Arial, sans-serif; padding:20px;'>
            <div style='max-width:600px; margin:0 auto; background:#1e1e1e; border:1px solid #00f0ff; border-radius:8px; padding:20px;'>
                <h2 style='color:#00f0ff; font-family:Georgia, serif; margin-top:0;'>VALKYRIN TELEMETRY ALERT</h2>
                <p>Greetings <strong>" . htmlspecialchars($recipientName) . "</strong>,</p>
                <p style='font-size:16px; background:#2a2a2a; padding:15px; border-left:4px solid #00f0ff; border-radius:4px;'>
                    " . htmlspecialchars($message) . "
                </p>
                <p style='margin-top:25px;'>
                    <a href='" . $fullUrl . "' style='background:#00f0ff; color:#000; padding:10px 20px; text-decoration:none; font-weight:bold; border-radius:20px; display:inline-block;'>Access Signal</a>
                </p>
                <hr style='border:0; border-top:1px solid #333; margin-top:30px;'>
                <p style='font-size:11px; color:#888;'>You received this telemetry alert based on your notification settings on VALKYRIN.</p>
            </div>
        </body>
        </html>
        ";

        $headers = "MIME-Version: 1.0" . "\r\n";
        $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
        $headers .= "From: VALKYRIN Telemetry <noreply@beardedviking.org>" . "\r\n";

        @mail($toEmail, $subject, $body, $headers);
    }
}