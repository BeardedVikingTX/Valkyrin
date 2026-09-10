<?php
// api/get_messages.php
ob_start();
header('Content-Type: application/json');

session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/database.php';

if (!isset($_SESSION['user_id'])) {
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$currentUserId = (int)$_SESSION['user_id'];
$conversationId = isset($_GET['conversation_id']) ? (int)$_GET['conversation_id'] : 0;

if (!$conversationId) {
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Invalid conversation ID.']);
    exit();
}

try {
    // Check membership on composite key conversation_participants
    $authStmt = $pdo->prepare("SELECT 1 FROM conversation_participants WHERE conversation_id = ? AND user_id = ? LIMIT 1");
    $authStmt->execute([$conversationId, $currentUserId]);
    if (!$authStmt->fetch()) {
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'Access denied.']);
        exit();
    }

    $stmt = $pdo->prepare("
        SELECT 
            m.id, 
            m.sender_id, 
            m.body, 
            m.attachment_path,
            m.attachment_type,
            m.created_at,
            u.display_name, 
            u.username
        FROM messages m
        JOIN users u ON m.sender_id = u.id
        WHERE m.conversation_id = ?
        ORDER BY m.id ASC
    ");
    $stmt->execute([$conversationId]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $messages = [];
    foreach ($rows as $r) {
        $senderName = !empty($r['display_name']) ? $r['display_name'] : $r['username'];
        $messages[] = [
            'id' => (int)$r['id'],
            'sender_id' => (int)$r['sender_id'],
            'sender_name' => $senderName,
            'body' => $r['body'],
            'attachment_path' => $r['attachment_path'],
            'attachment_type' => $r['attachment_type'],
            'is_owner' => ((int)$r['sender_id'] === $currentUserId),
            'created_at' => date('g:i a', strtotime($r['created_at']))
        ];
    }

    ob_clean();
    echo json_encode(['success' => true, 'messages' => $messages]);

} catch (Exception $e) {
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Failed pulling messages: ' . $e->getMessage()]);
}