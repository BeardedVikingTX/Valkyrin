<?php
// api/get_conversations.php
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

try {
    $stmt = $pdo->prepare("
        SELECT 
            c.id, 
            c.type, 
            c.title,
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
            peer.display_name AS peer_name,
            peer.username AS peer_username,
            peer.avatar AS peer_avatar
        FROM conversations c
        JOIN conversation_participants cp ON c.id = cp.conversation_id
        LEFT JOIN conversation_participants cp_peer ON (c.id = cp_peer.conversation_id AND cp_peer.user_id != ? AND c.type = 'private')
        LEFT JOIN users peer ON cp_peer.user_id = peer.id
        WHERE cp.user_id = ?
        ORDER BY last_message_time DESC, c.id DESC
    ");
    $stmt->execute([$currentUserId, $currentUserId]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $conversations = [];
    foreach ($rows as $row) {
        $displayTitle = $row['title'];
        $avatar = 'default_avatar.png';

        if ($row['type'] === 'private') {
            $displayTitle = !empty($row['peer_name']) ? $row['peer_name'] : ($row['peer_username'] ?: 'Node Connection');
            if (!empty($row['peer_avatar'])) {
                $avatar = $row['peer_avatar'];
            }
        } elseif (empty($displayTitle)) {
            $displayTitle = 'Group Stream';
        }

        $timeAgo = $row['last_message_time'] ? date('M j, g:i a', strtotime($row['last_message_time'])) : 'New';

        $conversations[] = [
            'id' => (int)$row['id'],
            'type' => $row['type'],
            'title' => $displayTitle,
            'avatar' => $avatar,
            'last_message' => $row['last_message'] ?: 'No transmissions yet',
            'time_ago' => $timeAgo
        ];
    }

    ob_clean();
    echo json_encode(['success' => true, 'conversations' => $conversations]);

} catch (Exception $e) {
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}