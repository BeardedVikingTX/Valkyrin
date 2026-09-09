<?php
define('VALKYRIN_EXEC', true);

ini_set('display_errors', 0);
error_reporting(E_ALL);

ob_start();

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/includes/header.php';

ob_clean();

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Authentication required.']);
    exit();
}

$userId = (int)$_SESSION['user_id'];
$targetId = isset($_POST['target_id']) ? (int)$_POST['target_id'] : 0;
$action = trim($_POST['action'] ?? '');

if ($targetId <= 0 || $targetId === $userId) {
    echo json_encode(['success' => false, 'error' => 'Invalid target node.']);
    exit();
}

if (!isset($pdo)) {
    echo json_encode(['success' => false, 'error' => 'Database connection unavailable.']);
    exit();
}

// Fetch sender details
$senderStmt = $pdo->prepare("SELECT username, display_name FROM users WHERE id = ?");
$senderStmt->execute([$userId]);
$sender = $senderStmt->fetch(PDO::FETCH_ASSOC);
$senderName = !empty($sender['display_name']) ? $sender['display_name'] : $sender['username'];

// Fetch recipient details
$recipientStmt = $pdo->prepare("SELECT email, username FROM users WHERE id = ?");
$recipientStmt->execute([$targetId]);
$recipient = $recipientStmt->fetch(PDO::FETCH_ASSOC);

try {
    switch ($action) {
        case 'request':
            $checkStmt = $pdo->prepare("
                SELECT id, status FROM connections 
                WHERE (requester_id = ? AND addressee_id = ?) 
                   OR (requester_id = ? AND addressee_id = ?)
            ");
            $checkStmt->execute([$userId, $targetId, $targetId, $userId]);
            $existing = $checkStmt->fetch(PDO::FETCH_ASSOC);

            if ($existing) {
                $updateStmt = $pdo->prepare("
                    UPDATE connections 
                    SET requester_id = ?, addressee_id = ?, status = 'pending', created_at = NOW() 
                    WHERE id = ?
                ");
                $updateStmt->execute([$userId, $targetId, $existing['id']]);
            } else {
                $insertStmt = $pdo->prepare("
                    INSERT INTO connections (requester_id, addressee_id, status, created_at) 
                    VALUES (?, ?, 'pending', NOW())
                ");
                $insertStmt->execute([$userId, $targetId]);
            }

            // In-App Notification
            $notifMsg = "{$senderName} requested to connect with your node.";
            $notifStmt = $pdo->prepare("
                INSERT INTO notifications (user_id, actor_id, type, message, is_read, created_at) 
                VALUES (?, ?, 'connection_request', ?, 0, NOW())
            ");
            $notifStmt->execute([$targetId, $userId, $notifMsg]);

            // Email Notification
            if (!empty($recipient['email'])) {
                $subject = "VALKYRIN Network Signal: Connection Request from {$senderName}";
                $body = "Greetings Node " . $recipient['username'] . ",\n\n"
                      . "User '{$senderName}' (@{$sender['username']}) has requested to establish a link with your network node on VALKYRIN.\n\n"
                      . "Review and manage your network links at: https://valkyrin.beardedviking.org/nodes.php\n\n"
                      . "--\nVALKYRIN Network Telemetry";
                $headers = "From: VALKYRIN Telemetry <noreply@beardedviking.org>\r\n"
                         . "Reply-To: noreply@beardedviking.org\r\n"
                         . "X-Mailer: PHP/" . phpversion();

                @mail($recipient['email'], $subject, $body, $headers);
            }

            echo json_encode(['success' => true, 'message' => 'Connection requested.']);
            break;

        case 'accept':
            // Check if connection is currently pending before accepting
            $checkStmt = $pdo->prepare("
                SELECT id, status FROM connections 
                WHERE (requester_id = ? AND addressee_id = ?) 
                   OR (requester_id = ? AND addressee_id = ?)
            ");
            $checkStmt->execute([$targetId, $userId, $userId, $targetId]);
            $conn = $checkStmt->fetch(PDO::FETCH_ASSOC);

            if ($conn && $conn['status'] !== 'accepted') {
                $pdo->beginTransaction();

                // 1. Update Connection Status
                $stmt = $pdo->prepare("UPDATE connections SET status = 'accepted' WHERE id = ?");
                $stmt->execute([$conn['id']]);

                // 2. Award +5 Reputation to BOTH nodes
                $repStmt = $pdo->prepare("UPDATE users SET reputation = reputation + 5 WHERE id IN (?, ?)");
                $repStmt->execute([$userId, $targetId]);

                // 3. Insert Notification
                $notifMsg = "{$senderName} accepted your connection request (+5 Rep).";
                $notifStmt = $pdo->prepare("
                    INSERT INTO notifications (user_id, actor_id, type, message, is_read, created_at) 
                    VALUES (?, ?, 'connection_accepted', ?, 0, NOW())
                ");
                $notifStmt->execute([$targetId, $userId, $notifMsg]);

                $pdo->commit();

                // Email Notification
                if (!empty($recipient['email'])) {
                    $subject = "VALKYRIN Network Signal: Link Accepted by {$senderName}";
                    $body = "Greetings Node " . $recipient['username'] . ",\n\n"
                          . "User '{$senderName}' has accepted your node connection request on VALKYRIN (+5 Reputation awarded!).\n\n"
                          . "View active nodes at: https://valkyrin.beardedviking.org/nodes.php\n\n"
                          . "--\nVALKYRIN Network Telemetry";
                    $headers = "From: VALKYRIN Telemetry <noreply@beardedviking.org>\r\n"
                             . "Reply-To: noreply@beardedviking.org\r\n"
                             . "X-Mailer: PHP/" . phpversion();

                    @mail($recipient['email'], $subject, $body, $headers);
                }
            }

            echo json_encode(['success' => true, 'message' => 'Connection accepted (+5 Rep).']);
            break;

        case 'sever':
            // Check current status prior to deletion
            $checkStmt = $pdo->prepare("
                SELECT id, status FROM connections 
                WHERE (requester_id = ? AND addressee_id = ?) 
                   OR (requester_id = ? AND addressee_id = ?)
            ");
            $checkStmt->execute([$userId, $targetId, $targetId, $userId]);
            $conn = $checkStmt->fetch(PDO::FETCH_ASSOC);

            if ($conn) {
                $pdo->beginTransaction();

                // Delete Connection
                $stmt = $pdo->prepare("DELETE FROM connections WHERE id = ?");
                $stmt->execute([$conn['id']]);

                // If connection was active ('accepted'), penalize -5 reputation from both nodes
                if ($conn['status'] === 'accepted') {
                    $repStmt = $pdo->prepare("UPDATE users SET reputation = GREATEST(0, reputation - 5) WHERE id IN (?, ?)");
                    $repStmt->execute([$userId, $targetId]);
                }

                $pdo->commit();
            }

            echo json_encode(['success' => true, 'message' => 'Connection severed.']);
            break;

        case 'hide':
            $stmt = $pdo->prepare("
                INSERT INTO connections (requester_id, addressee_id, status, created_at) 
                VALUES (?, ?, 'hidden', NOW())
                ON DUPLICATE KEY UPDATE status = 'hidden', requester_id = VALUES(requester_id)
            ");
            $stmt->execute([$userId, $targetId]);
            echo json_encode(['success' => true, 'message' => 'Node hidden successfully.']);
            break;

        case 'unhide':
            $stmt = $pdo->prepare("
                DELETE FROM connections 
                WHERE requester_id = ? AND addressee_id = ? AND status = 'hidden'
            ");
            $stmt->execute([$userId, $targetId]);
            echo json_encode(['success' => true, 'message' => 'Node unhidden.']);
            break;

        default:
            echo json_encode(['success' => false, 'error' => 'Unknown action request.']);
            break;
    }
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode(['success' => false, 'error' => 'Database operation error: ' . $e->getMessage()]);
}