<?php
// api/create_conversation.php
ob_start();
header('Content-Type: application/json');

session_start();
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/database.php';

// 1. Session and Auth Check
if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Unauthorized: Session missing or expired.']);
    exit();
}

$currentUserId = (int)$_SESSION['user_id'];

// 2. Validate Creator Exists in Users Table
$userCheck = $pdo->prepare("SELECT id FROM users WHERE id = ? LIMIT 1");
$userCheck->execute([$currentUserId]);
if (!$userCheck->fetch()) {
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Invalid session user: Account does not exist in database.']);
    exit();
}

// 3. Parse and Validate Request Input
$type = $_POST['type'] ?? 'private';
$title = trim($_POST['title'] ?? '');
$participants = $_POST['participants'] ?? [];

if (!is_array($participants)) {
    $participants = [$participants];
}

// Clean and sanitize participant IDs
$participantIds = array_map('intval', $participants);
$participantIds = array_filter($participantIds, function($id) use ($currentUserId) {
    return $id > 0 && $id !== $currentUserId;
});

if (empty($participantIds)) {
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Please select at least one valid recipient node.']);
    exit();
}

// 4. Force Private vs Group Logic
if ($type === 'private') {
    // Take only the first participant for 1-on-1 chats
    $targetUserId = reset($participantIds);

    // Check if a private conversation already exists between these two users
    $existingStmt = $pdo->prepare("
        SELECT c.id 
        FROM conversations c
        JOIN conversation_participants cp1 ON c.id = cp1.conversation_id AND cp1.user_id = ?
        JOIN conversation_participants cp2 ON c.id = cp2.conversation_id AND cp2.user_id = ?
        WHERE c.type = 'private'
        LIMIT 1
    ");
    $existingStmt->execute([$currentUserId, $targetUserId]);
    $existingId = $existingStmt->fetchColumn();

    if ($existingId) {
        // Fetch peer display name for UI
        $peerStmt = $pdo->prepare("SELECT display_name, username FROM users WHERE id = ? LIMIT 1");
        $peerStmt->execute([$targetUserId]);
        $peer = $peerStmt->fetch(PDO::FETCH_ASSOC);
        $chatTitle = !empty($peer['display_name']) ? $peer['display_name'] : $peer['username'];

        ob_clean();
        echo json_encode([
            'success' => true, 
            'conversation_id' => (int)$existingId,
            'title' => $chatTitle,
            'is_existing' => true
        ]);
        exit();
    }
}

try {
    $pdo->beginTransaction();

    // 5. Insert Conversation Row (Fixes FK constraint by passing valid $currentUserId)
    $convStmt = $pdo->prepare("
        INSERT INTO conversations (creator_id, type, is_encrypted, title, created_at) 
        VALUES (?, ?, 0, ?, NOW())
    ");
    $convStmt->execute([$currentUserId, $type, $title ?: null]);
    $conversationId = (int)$pdo->lastInsertId();

    // 6. Insert All Participants (Creator + Selected Recipients)
    $allParticipants = array_unique(array_merge([$currentUserId], $participantIds));

    $partStmt = $pdo->prepare("
        INSERT INTO conversation_participants (conversation_id, user_id, joined_at) 
        VALUES (?, ?, NOW())
    ");

    foreach ($allParticipants as $pId) {
        $partStmt->execute([$conversationId, (int)$pId]);
    }

    $pdo->commit();

    // Determine return title for front-end active state
    $displayTitle = $title;
    if ($type === 'private') {
        $peerStmt = $pdo->prepare("SELECT display_name, username FROM users WHERE id = ? LIMIT 1");
        $peerStmt->execute([reset($participantIds)]);
        $peer = $peerStmt->fetch(PDO::FETCH_ASSOC);
        $displayTitle = !empty($peer['display_name']) ? $peer['display_name'] : $peer['username'];
    } elseif (empty($displayTitle)) {
        $displayTitle = 'Group Stream';
    }

    ob_clean();
    echo json_encode([
        'success' => true,
        'conversation_id' => $conversationId,
        'title' => $displayTitle
    ]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Database failure: ' . $e->getMessage()]);
}