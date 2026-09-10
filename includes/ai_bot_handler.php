<?php
// includes/ai_bot_handler.php
if (!defined('VALKYRIN_EXEC')) {
    exit('Direct access denied.');
}

require_once __DIR__ . '/GeminiEngine.php';

function handleAiBotReply(PDO $pdo, int $conversationId, int $userId, string $userPrompt): void {
    try {
        // 1. Resolve API Key (.env -> DB User 9999 -> Config constant)
        $apiKey = getenv('GEMINI_API_KEY') ?: ($_ENV['GEMINI_API_KEY'] ?? '');

        if (empty($apiKey)) {
            $aiKeyStmt = $pdo->prepare("SELECT gemini_api_key FROM users WHERE id = 9999 LIMIT 1");
            $aiKeyStmt->execute();
            $aiRow = $aiKeyStmt->fetch(PDO::FETCH_ASSOC);
            $apiKey = $aiRow['gemini_api_key'] ?? (defined('GEMINI_API_KEY') ? GEMINI_API_KEY : '');
        }

        $apiKey = trim($apiKey);

        if (empty($apiKey)) {
            error_log("VALKYRIN AI Error: GEMINI_API_KEY is missing from .env and database.");
            $replyStmt = $pdo->prepare("INSERT INTO messages (conversation_id, sender_id, body, created_at) VALUES (?, 9999, ?, NOW())");
            $replyStmt->execute([$conversationId, "My neural link configuration is missing an API key!"]);
            return;
        }

        // 2. Fetch Authenticated User Metrics
        $uStmt = $pdo->prepare("
            SELECT username, display_name, role, created_at, bio, location,
                   (SELECT COUNT(*) FROM connections WHERE (requester_id = u.id OR addressee_id = u.id) AND status = 'accepted') as conn_count
            FROM users u WHERE id = ? LIMIT 1
        ");
        $uStmt->execute([$userId]);
        $user = $uStmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            return;
        }

        $displayName = !empty($user['display_name']) ? $user['display_name'] : $user['username'];

        // 3. Fetch Real-time Platform Telemetry
        $sysUserCount = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
        $sysMsgCount  = $pdo->query("SELECT COUNT(*) FROM messages WHERE created_at >= NOW() - INTERVAL 24 HOUR")->fetchColumn();
        $sysPending   = $pdo->query("SELECT COUNT(*) FROM connections WHERE status = 'pending'")->fetchColumn();

        // 4. Fetch Conversation History
        $msgStmt = $pdo->prepare("
            SELECT sender_id, body 
            FROM messages 
            WHERE conversation_id = ? 
            ORDER BY id DESC LIMIT 8
        ");
        $msgStmt->execute([$conversationId]);
        $recentMsgs = array_reverse($msgStmt->fetchAll(PDO::FETCH_ASSOC));

        $historyContext = [];
        foreach ($recentMsgs as $m) {
            $role = ((int)$m['sender_id'] === 9999) ? 'model' : 'user';
            $senderPrefix = ($role === 'user') ? "{$displayName}: " : "";
            $historyContext[] = [
                'role'    => $role,
                'content' => $senderPrefix . $m['body']
            ];
        }

        // 5. Construct System Prompt
        $systemInstructions = "You are VALKYRIN Core AI, the central neural intelligence of the VALKYRIN platform (beardedviking.org).
Your personality is sharp, helpful, direct, intelligent, and grounded with a touch of Viking warrior tactical wit.

AUTHENTICATED SENDER DATA:
- ID: {$userId}
- Name: {$displayName} (@{$user['username']})
- Role: {$user['role']}
- Established Connections: {$user['conn_count']} active nodes
- Location: " . ($user['location'] ?? 'Unknown') . "
- Joined: {$user['created_at']}

VALKYRIN LIVE PLATFORM TELEMETRY:
- Registered Users: {$sysUserCount}
- 24-Hour Transmissions: {$sysMsgCount}
- Pending Connection Requests: {$sysPending}
- Operational Status: 100% Core Online / All Systems Nominal

OPERATIONAL DIRECTIVES:
1. Speak naturally and conversationally. Never repeat static loop greetings like 'Telemetry verified' or 'Neural node online'.
2. When asked about user account info or platform health, cite the exact telemetry provided above.
3. Be attentive to the conversation thread history.
4. Keep responses direct, formatted cleanly for chat, and engaging.";

        // 6. Query Gemini via GeminiEngine ( targeting gemini-2.5-flash )
        $engine = new GeminiEngine($apiKey, 'gemini-3.6-flash');
        $response = $engine->generateResponse($userPrompt, $systemInstructions, $historyContext);

        if ($response['success']) {
            $aiReply = trim($response['text']);
        } else {
            error_log("VALKYRIN AI Engine Error: " . $response['error']);
            $aiReply = "My neural link encountered an interface issue: " . $response['error'];
        }

        // 7. Save AI Reply
        $replyStmt = $pdo->prepare("
            INSERT INTO messages (conversation_id, sender_id, body, created_at) 
            VALUES (?, 9999, ?, NOW())
        ");
        $replyStmt->execute([$conversationId, $aiReply]);

    } catch (Exception $e) {
        error_log("VALKYRIN AI Exception: " . $e->getMessage());
    }
}