<?php
// api/chat/messages.php
header('Content-Type: application/json');
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/messaging_helper.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

$currentUserId  = (int)$_SESSION['user_id'];
$conversationId = (int)($_REQUEST['conversation_id'] ?? 0);

if (!$conversationId || !isParticipant($pdo, $conversationId, $currentUserId)) {
    echo json_encode(['success' => false, 'error' => 'Access denied or invalid conversation.']);
    exit();
}

$method = $_SERVER['REQUEST_METHOD'];

// GET: Load Message History
if ($method === 'GET') {
    $stmt = $pdo->prepare("
        SELECT 
            m.id, 
            m.sender_id, 
            m.body, 
            m.created_at,
            u.display_name, 
            u.username, 
            u.avatar
        FROM messages m
        JOIN users u ON m.sender_id = u.id
        WHERE m.conversation_id = ?
        ORDER BY m.id ASC
    ");
    $stmt->execute([$conversationId]);
    $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Update last_read_at timestamp for current user
    $readStmt = $pdo->prepare("
        UPDATE conversation_participants 
        SET last_read_at = NOW() 
        WHERE conversation_id = ? AND user_id = ?
    ");
    $readStmt->execute([$conversationId, $currentUserId]);

    echo json_encode(['success' => true, 'messages' => $messages]);
    exit();
}

// POST: Send New Message
if ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $body  = trim($input['body'] ?? '');

    if (empty($body)) {
        echo json_encode(['success' => false, 'error' => 'Message body cannot be empty.']);
        exit();
    }

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("INSERT INTO messages (conversation_id, sender_id, body) VALUES (?, ?, ?)");
        $stmt->execute([$conversationId, $currentUserId, $body]);
        $messageId = $pdo->lastInsertId();

        // Touch updated_at on conversation
        $touchStmt = $pdo->prepare("UPDATE conversations SET updated_at = NOW() WHERE id = ?");
        $touchStmt->execute([$conversationId]);

        $pdo->commit();

        echo json_encode([
            'success' => true,
            'message' => [
                'id'              => $messageId,
                'conversation_id' => $conversationId,
                'sender_id'       => $currentUserId,
                'body'            => htmlspecialchars($body, ENT_QUOTES, 'UTF-8'),
                'created_at'      => date('Y-m-d H:i:s')
            ]
        ]);
    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'error' => 'Failed to send message.']);
    }
    exit();
}