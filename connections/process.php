<?php
define('VALKYRIN_EXEC', true);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    header("Location: /login.php");
    exit();
}

require_once __DIR__ . '/../includes/header.php'; // Ensures $pdo is loaded

$userId = $_SESSION['user_id'];
$action = $_POST['action'] ?? '';
$targetUserId = filter_input(INPUT_POST, 'target_user_id', FILTER_VALIDATE_INT);
$connectionId = filter_input(INPUT_POST, 'connection_id', FILTER_VALIDATE_INT);

// Action 1: Send Request or Auto-Mutuate Connection
if ($action === 'send_request' && $targetUserId) {
    if ($targetUserId === $userId) {
        header("Location: /connections.php?err=" . urlencode("Cannot connect with self node."));
        exit();
    }

    try {
        // 1. Insert or update requester -> target link
        $stmt = $pdo->prepare("
            INSERT INTO connections (requester_id, addressee_id, status) 
            VALUES (:req, :add, 'pending')
            ON DUPLICATE KEY UPDATE status = VALUES(status)
        ");
        $stmt->execute([':req' => $userId, ':add' => $targetUserId]);

        // 2. Check if target user ALREADY sent us a request
        $checkStmt = $pdo->prepare("SELECT id FROM connections WHERE requester_id = :target AND addressee_id = :user AND status = 'pending'");
        $checkStmt->execute([':target' => $targetUserId, ':user' => $userId]);
        $reciprocal = $checkStmt->fetch();

        if ($reciprocal) {
            // Auto-accept both sides to make connection mutual!
            $updStmt = $pdo->prepare("UPDATE connections SET status = 'accepted' WHERE (requester_id = :user AND addressee_id = :target) OR (requester_id = :target AND addressee_id = :user)");
            $updStmt->execute([':user' => $userId, ':target' => $targetUserId]);
            
            // Reward +10 REP to both
            $pdo->prepare("UPDATE users SET reputation_points = reputation_points + 10 WHERE id IN (:user, :target)")
                ->execute([':user' => $userId, ':target' => $targetUserId]);

            header("Location: /connections.php?msg=" . urlencode("Mutual connection established! +10 REP awarded."));
            exit();
        }

        header("Location: /connections.php?msg=" . urlencode("Connection signal transmitted successfully."));
        exit();

    } catch (PDOException $e) {
        header("Location: /connections.php?err=" . urlencode("Database error processing link request."));
        exit();
    }
}

// Action 2: Accept Pending Request
if ($action === 'accept' && $connectionId) {
    try {
        // Fetch the connection record
        $stmt = $pdo->prepare("SELECT requester_id, addressee_id FROM connections WHERE id = :id AND addressee_id = :user");
        $stmt->execute([':id' => $connectionId, ':user' => $userId]);
        $conn = $stmt->fetch();

        if ($conn) {
            $requesterId = $conn['requester_id'];

            // Mark incoming request as accepted
            $upd1 = $pdo->prepare("UPDATE connections SET status = 'accepted' WHERE id = :id");
            $upd1->execute([':id' => $connectionId]);

            // Create or update reverse record (target -> requester) as accepted to finalize bi-directional graph
            $upd2 = $pdo->prepare("
                INSERT INTO connections (requester_id, addressee_id, status) 
                VALUES (:user, :req, 'accepted')
                ON DUPLICATE KEY UPDATE status = 'accepted'
            ");
            $upd2->execute([':user' => $userId, ':req' => $requesterId]);

            // Award +10 Reputation Points for establishing a link
            $repStmt = $pdo->prepare("UPDATE users SET reputation_points = reputation_points + 10 WHERE id IN (:user, :req)");
            $repStmt->execute([':user' => $userId, ':req' => $requesterId]);

            header("Location: /connections.php?msg=" . urlencode("Connection accepted. Frequency synchronized!"));
            exit();
        }
    } catch (PDOException $e) {
        header("Location: /connections.php?err=" . urlencode("Database fault accepting request."));
        exit();
    }
}

// Action 3: Reject Connection Request
if ($action === 'reject' && $connectionId) {
    try {
        $stmt = $pdo->prepare("DELETE FROM connections WHERE id = :id AND addressee_id = :user");
        $stmt->execute([':id' => $connectionId, ':user' => $userId]);

        header("Location: /connections.php?msg=" . urlencode("Connection signal purged."));
        exit();
    } catch (PDOException $e) {
        header("Location: /connections.php?err=" . urlencode("Database error rejecting signal."));
        exit();
    }
}

header("Location: /connections.php");
exit();