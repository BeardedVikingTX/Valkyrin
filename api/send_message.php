<?php
// api/send_message.php
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
$conversationId = (int)($_POST['conversation_id'] ?? 0);
$body = trim($_POST['body'] ?? '');

if (!$conversationId) {
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Missing conversation ID.']);
    exit();
}

// 1. Verify membership in conversation_participants
$authStmt = $pdo->prepare("SELECT 1 FROM conversation_participants WHERE conversation_id = ? AND user_id = ? LIMIT 1");
$authStmt->execute([$conversationId, $currentUserId]);
if (!$authStmt->fetch()) {
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Access denied.']);
    exit();
}

// 2. Handle File Attachment Processing
$attachmentPath = null;
$attachmentType = null;

if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
    $fileTmpPath = $_FILES['attachment']['tmp_name'];
    $fileName = $_FILES['attachment']['name'];
    $fileSize = $_FILES['attachment']['size'];
    
    // 25MB Limit Check
    if ($fileSize > 25 * 1024 * 1024) {
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'File exceeds 25MB limit.']);
        exit();
    }

    $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
    $allowedImageExts = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    $allowedVideoExts = ['mp4', 'webm', 'ogg', 'mov'];

    if (in_array($fileExtension, $allowedImageExts)) {
        $attachmentType = 'image';
    } elseif (in_array($fileExtension, $allowedVideoExts)) {
        $attachmentType = 'video';
    } else {
        $attachmentType = 'file';
    }

    $uploadDir = __DIR__ . '/../uploads/chat_attachments/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $newFileName = md5(time() . $fileName) . '.' . $fileExtension;
    $destPath = $uploadDir . $newFileName;

    if (move_uploaded_file($fileTmpPath, $destPath)) {
        $attachmentPath = '/uploads/chat_attachments/' . $newFileName;
    } else {
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'Failed to upload attachment.']);
        exit();
    }
}

if (empty($body) && !$attachmentPath) {
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Message cannot be empty.']);
    exit();
}

try {
    $pdo->beginTransaction();

    // 3. Insert user message into messages table
    $stmt = $pdo->prepare("
        INSERT INTO messages (conversation_id, sender_id, body, attachment_path, attachment_type, created_at) 
        VALUES (?, ?, ?, ?, ?, NOW())
    ");
    $stmt->execute([$conversationId, $currentUserId, $body, $attachmentPath, $attachmentType]);
    $messageId = (int)$pdo->lastInsertId();

    // 4. Get sender info
    $uStmt = $pdo->prepare("SELECT display_name, username FROM users WHERE id = ? LIMIT 1");
    $uStmt->execute([$currentUserId]);
    $sender = $uStmt->fetch(PDO::FETCH_ASSOC);
    $senderName = !empty($sender['display_name']) ? $sender['display_name'] : $sender['username'];

    // 5. Notify participants and evaluate AI Bot triggering
    $pStmt = $pdo->prepare("
        SELECT cp.user_id 
        FROM conversation_participants cp 
        WHERE cp.conversation_id = ? AND cp.user_id != ?
    ");
    $pStmt->execute([$conversationId, $currentUserId]);
    $recipients = $pStmt->fetchAll(PDO::FETCH_COLUMN);

    $notifMsg = "New transmission from " . $senderName;
    
    $nStmt = $pdo->prepare("
        INSERT INTO notifications (user_id, actor_id, type, entity_id, message, is_read, created_at) 
        VALUES (?, ?, 'new_message', ?, ?, 0, NOW())
    ");
    
    $isTagged = (bool)preg_match('/@(VALKYRIN_AI|VALKYRIN|9999)\b/i', $body);
    $isBotTarget = $isTagged;

    foreach ($recipients as $recipientId) {
        $recIdInt = (int)$recipientId;
        
        if ($recIdInt === 9999) {
            $isBotTarget = true;
            continue; // Skip creating database notifications for the AI node
        }

        $nStmt->execute([$recIdInt, $currentUserId, $conversationId, $notifMsg]);
    }

    $pdo->commit();

    // 6. Execute Context-Aware AI Bot Handler outside transaction
    if ($isBotTarget) {
        if (!defined('VALKYRIN_EXEC')) {
            define('VALKYRIN_EXEC', true);
        }
        
        $aiHandlerPath = __DIR__ . '/../includes/ai_bot_handler.php';
        if (file_exists($aiHandlerPath)) {
            require_once $aiHandlerPath;
            if (function_exists('handleAiBotReply')) {
                handleAiBotReply($pdo, $conversationId, $currentUserId, $body);
            }
        }
    }

    ob_clean();
    echo json_encode([
        'success' => true,
        'message' => [
            'id' => $messageId,
            'sender_id' => $currentUserId,
            'sender_name' => $senderName,
            'body' => $body,
            'attachment_path' => $attachmentPath,
            'attachment_type' => $attachmentType,
            'is_owner' => true,
            'created_at' => date('g:i a')
        ]
    ]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Database exception: ' . $e->getMessage()]);
}