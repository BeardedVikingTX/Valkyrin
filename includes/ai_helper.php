<?php
// includes/ai_helper.php
require_once __DIR__ . '/database.php';

function generateValkyrinResponse(PDO $pdo, int $userId, string $userPrompt): string {
    // 1. Extract Current User Account Data
    $uStmt = $pdo->prepare("
        SELECT username, display_name, role, created_at, 
               (SELECT COUNT(*) FROM connections WHERE (requester_id = u.id OR addressee_id = u.id) AND status = 'accepted') as connection_count
        FROM users u WHERE id = ?
    ");
    $uStmt->execute([$userId]);
    $userData = $uStmt->fetch(PDO::FETCH_ASSOC);

    // 2. Extract System / Platform Telemetry
    $sysStmt = $pdo->query("
        SELECT 
            (SELECT COUNT(*) FROM users) as total_users,
            (SELECT COUNT(*) FROM messages WHERE created_at >= NOW() - INTERVAL 24 HOUR) as msgs_24h,
            (SELECT COUNT(*) FROM connections WHERE status = 'pending') as pending_invites
    ");
    $sysData = $sysStmt->fetch(PDO::FETCH_ASSOC);

    // 3. Assemble System Prompt with Injected State
    $systemPrompt = "You are VALKYRIN Core AI, the central neural node of VALKYRIN (beardedviking.org).
Your tone is concise, tactical, highly direct, and technical.

CURRENT USER TELEMETRY:
- User ID: {$userId}
- Username: {$userData['username']}
- Display Name: {$userData['display_name']}
- Role/Rank: {$userData['role']}
- Connections: {$userData['connection_count']} active nodes
- Account Age: Created on {$userData['created_at']}

VALKYRIN SYSTEM HEALTH & OPERATIONS:
- Network Nodes (Users): {$sysData['total_users']} total registered
- 24h Signal Density: {$sysData['msgs_24h']} transmissions
- Pending Handshakes: {$sysData['pending_invites']} awaiting authorization
- Operational Status: All systems nominal (99.97% Uptime)

RULES:
1. Do NOT loop default greetings like 'VALKYRIN neural node online' if conversation is already underway.
2. Address the user by display name or username when answering account questions.
3. If asked about site health or operational stats, cite the telemetry data provided above.
4. Keep answers brief, structured, and authoritative.";

    // 4. Dispatch Request to Gemini API
    $apiKey = GEMINI_API_KEY; // Defined in config.php
    $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key=" . $apiKey;

    $payload = [
        "contents" => [
            [
                "role" => "user",
                "parts" => [
                    ["text" => $systemPrompt . "\n\nUser Query: " . $userPrompt]
                ]
            ]
        ],
        "generationConfig" => [
            "temperature" => 0.4,
            "maxOutputTokens" => 400
        ]
    ];

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_TIMEOUT => 15
    ]);

    $response = curl_exec($ch);
    curl_close($ch);

    if ($response) {
        $result = json_decode($response, true);
        if (isset($result['candidates'][0]['content']['parts'][0]['text'])) {
            return trim($result['candidates'][0]['content']['parts'][0]['text']);
        }
    }

    return "VALKYRIN Core AI encountered a telemetry link drop. Please re-transmit your query.";
}