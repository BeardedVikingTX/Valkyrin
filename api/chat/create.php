<?php
// api/chat/create.php
header('Content-Type: application/json');
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/database.php';
require_once __DIR__ . '/../../includes/messaging_helper.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

$currentUserId = (int)$_SESSION['user_id'];
$input = json_decode(file_get_contents('php://input'), true);

$type         = $input['type'] ?? 'private'; // 'private' or 'group'
$title        = trim($input['title'] ?? '');
$participants = $input['participants'] ?? []; // Array of user IDs

// Sanitize participant IDs and include current user
$participantIds = array_unique(array_map('intval', $participants));
if (!in_array($currentUserId, $participantIds)) {
    $participantIds[] = $currentUserId;
}

// 1. Validation Logic
if ($type === 'private') {
    $otherUsers = array_diff($participantIds, [$currentUserId]);
    if (count($otherUsers) !== 1) {
        echo json_encode(['success' => false, 'error' => 'Private chat requires exactly one recipient.']);
        exit();
    }
    $targetUser = reset($otherUsers);

    if (!isConnected($pdo, $currentUserId, $targetUser)) {
        echo json_encode(['success' => false, 'error' => 'You must be connected with this user to message them.']);
        exit();
    }

    // Reuse existing 1-on-1 chat if present
    $existingId = findExistingPrivateConversation($pdo, $currentUserId, $targetUser);
    if ($existingId) {
        echo json_encode(['success' => true, 'conversation_id' => $existingId, 'reused' => true]);
        exit();
    }

} else if ($type === 'group') {
    if (count($participantIds) < 3) {
        echo json_encode(['success' => false, 'error' => 'Group chat requires at least 2 other members.']);
        exit();
    }

    // Rule: Creator MUST be connected to EVERY invitee
    if (!isConnectedToAll($pdo, $currentUserId, $participantIds)) {
        echo json_encode(['success' => false, 'error' => 'You can only invite users who are in your connections list.']);
        exit();
    }
} else {
    echo json_encode(['success' => false, 'error' => 'Invalid chat type.']);
    exit();
}

// 2. Database Transaction
try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare("INSERT INTO conversations (creator_id, type, title) VALUES (?, ?, ?)");
    $stmt->execute([$currentUserId, $type, $type === 'group' ? $title : null]);
    $conversationId = (int)$pdo->lastInsertId();

    $partStmt = $pdo->prepare("INSERT INTO conversation_participants (conversation_id, user_id) VALUES (?, ?)");
    foreach ($participantIds as $pId) {
        $partStmt->execute([$conversationId, $pId]);
    }

    $pdo->commit();
    echo json_encode(['success' => true, 'conversation_id' => $conversationId]);

} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'error' => 'Failed to create conversation.']);
}