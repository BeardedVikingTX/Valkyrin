<?php
header('Content-Type: application/json');

// Use realpath to strictly locate includes directory relative to document root
$baseDir = dirname(__DIR__);
$dbFile = $baseDir . '/includes/database.php';
$engineFile = $baseDir . '/includes/GeminiEngine.php';

if (!file_exists($dbFile) || !file_exists($engineFile)) {
    echo json_encode([
        'success' => false, 
        'message' => 'Core dependency missing. Check includes folder path.'
    ]);
    exit;
}

require_once $dbFile;
require_once $engineFile;

session_start();

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$conversationId = filter_input(INPUT_POST, 'conversation_id', FILTER_VALIDATE_INT);
$userMessage    = trim($_POST['message'] ?? '');

if (!$conversationId || empty($userMessage)) {
    echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
    exit;
}

try {
    // 1. Check if conversation includes User 9999 OR if message tags @VALKYRIN_AI / @9999
    $isTagged = (bool) preg_match('/@(VALKYRIN_AI|VALKYRIN|9999)\b/i', $userMessage);

    $checkStmt = $pdo->prepare("
        SELECT 1 FROM conversation_participants 
        WHERE conversation_id = ? AND user_id = 9999
        LIMIT 1
    ");
    $checkStmt->execute([$conversationId]);
    $isParticipant = (bool) $checkStmt->fetchColumn();

    if (!$isTagged && !$isParticipant) {
        echo json_encode(['success' => true, 'triggered' => false]);
        exit;
    }

    // 2. Load VALKYRIN_AI's Gemini API key
    $keyStmt = $pdo->prepare("SELECT gemini_api_key FROM users WHERE id = 9999 LIMIT 1");
    $keyStmt->execute();
    $aiKey = $keyStmt->fetchColumn();

    if (empty($aiKey)) {
        echo json_encode(['success' => false, 'message' => 'System AI Key missing']);
        exit;
    }

    // 3. Retrieve last 10 messages for contextual memory
    $histStmt = $pdo->prepare("
        SELECT m.sender_id, m.body, u.username 
        FROM messages m
        JOIN users u ON m.sender_id = u.id
        WHERE m.conversation_id = ?
        ORDER BY m.id DESC
        LIMIT 10
    ");
    $histStmt->execute([$conversationId]);
    $rawHistory = array_reverse($histStmt->fetchAll(PDO::FETCH_ASSOC));

    $formattedHistory = [];
    foreach ($rawHistory as $msg) {
        $role = ($msg['sender_id'] == 9999) ? 'model' : 'user';
        $formattedHistory[] = [
            'role'    => $role,
            'content' => ($role === 'user' ? "{$msg['username']}: " : "") . $msg['body']
        ];
    }

    // 4. Define Autonomous System Persona
    $systemInstruction = "You are VALKYRIN Core AI (ID: 9999), the central sovereign node on beardedviking.org. "
                       . "You are participating in a multi-user real-time channel. "
                       . "Respond concisely (under 150 words unless detailed technical specs are requested). "
                       . "Keep your tone sharp, analytical, tactical, and helpful.";

    // 5. Query Gemini API with stable 2.5 Flash endpoint
    $engine = new GeminiEngine($aiKey, 'gemini-3.6-flash');
    $response = $engine->generateResponse($userMessage, $systemInstruction, $formattedHistory);

    if (!$response['success']) {
        echo json_encode(['success' => false, 'message' => $response['error']]);
        exit;
    }

    $aiReplyText = trim($response['text']);

    // 6. Insert AI Response into database
    $insertStmt = $pdo->prepare("
        INSERT INTO messages (conversation_id, sender_id, body, created_at) 
        VALUES (?, 9999, ?, NOW())
    ");
    $insertStmt->execute([$conversationId, $aiReplyText]);

    // 7. Update conversation active timestamp
    $updateConv = $pdo->prepare("UPDATE conversations SET updated_at = NOW() WHERE id = ?");
    $updateConv->execute([$conversationId]);

    echo json_encode([
        'success'   => true,
        'triggered' => true,
        'reply'     => $aiReplyText
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}