<?php
// api/chat/list.php
header('Content-Type: application/json');
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/database.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

$currentUserId = (int)$_SESSION['user_id'];

$query = "
    SELECT 
        c.id AS conversation_id,
        c.type,
        c.title,
        c.updated_at,
        (
            SELECT m.body 
            FROM messages m 
            WHERE m.conversation_id = c.id 
            ORDER BY m.id DESC LIMIT 1
        ) AS last_message,
        (
            SELECT m.created_at 
            FROM messages m 
            WHERE m.conversation_id = c.id 
            ORDER BY m.id DESC LIMIT 1
        ) AS last_message_time,
        (
            SELECT COUNT(*) 
            FROM messages m 
            WHERE m.conversation_id = c.id 
              AND m.created_at > COALESCE(cp.last_read_at, '1970-01-01 00:00:00')
              AND m.sender_id != ?
        ) AS unread_count
    FROM conversations c
    JOIN conversation_participants cp ON c.id = cp.conversation_id
    WHERE cp.user_id = ?
    ORDER BY c.updated_at DESC
";

$stmt = $pdo->prepare($query);
$stmt->execute([$currentUserId, $currentUserId]);
$conversations = $stmt->fetchAll(PDO::FETCH_ASSOC);

// For 1-on-1 chats without a title, populate title/avatar with the counter-party's info
foreach ($conversations as &$chat) {
    if ($chat['type'] === 'private') {
        $pStmt = $pdo->prepare("
            SELECT u.id, u.display_name, u.username, u.avatar 
            FROM conversation_participants cp
            JOIN users u ON cp.user_id = u.id
            WHERE cp.conversation_id = ? AND cp.user_id != ?
            LIMIT 1
        ");
        $pStmt->execute([$chat['conversation_id'], $currentUserId]);
        $peer = $pStmt->fetch(PDO::FETCH_ASSOC);
        if ($peer) {
            $chat['title']  = $peer['display_name'] ?: $peer['username'];
            $chat['avatar'] = $peer['avatar'];
        }
    }
}

echo json_encode(['success' => true, 'conversations' => $conversations]);