<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/db.php'; // Your PDO connection
require_once __DIR__ . '/../includes/GeminiEngine.php';

session_start();

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$userPrompt = trim($_POST['prompt'] ?? '');
if (empty($userPrompt)) {
    echo json_encode(['success' => false, 'message' => 'Prompt cannot be empty']);
    exit;
}

try {
    // 1. Fetch system API key from User 9999 (VALKYRIN_AI) or current session user
    $stmt = $pdo->prepare("SELECT gemini_api_key FROM users WHERE id = 9999 LIMIT 1");
    $stmt->execute();
    $aiUser = $stmt->fetch(PDO::FETCH_ASSOC);

    $apiKey = $aiUser['gemini_api_key'] ?? null;

    if (!$apiKey) {
        // Fallback: Check if active user has personal key
        $userStmt = $pdo->prepare("SELECT gemini_api_key FROM users WHERE id = ?");
        $userStmt->execute([$_SESSION['user_id']]);
        $apiKey = $userStmt->fetchColumn();
    }

    // 2. Define System Persona
    $systemPersona = "You are VALKYRIN Core AI, the central autonomous intelligence for the VALKYRIN platform at beardedviking.org. "
                   . "Your tactical origin is Sovereign Node Cluster // Texas - Illinois. "
                   . "Respond concisely, precisely, and maintain a tactical, tech-sovereign persona.";

    // 3. Initialize Engine & Execute Query
    $engine = new GeminiEngine($apiKey, 'gemini-2.5-flash');
    $result = $engine->generateResponse($userPrompt, $systemPersona);

    if ($result['success']) {
        echo json_encode([
            'success' => true,
            'reply'   => $result['text'],
            'sender'  => 'VALKYRIN_AI'
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => $result['error']
        ]);
    }

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Internal Server Error: ' . $e->getMessage()]);
}