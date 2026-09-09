<?php
define('VALKYRIN_EXEC', true);
header('Content-Type: application/json');

require_once __DIR__ . '/../includes/db.php'; // Adjust path to your PDO db setup

$response = [
    'status' => 'success',
    'timestamp' => date('H:i:s'),
    'categories' => [
        'Standard Browser' => 0,
        'Crawler Bot' => 0,
        'Automated Tool' => 0
    ],
    'total_logs' => 0
];

if (isset($pdo)) {
    try {
        $stmt = $pdo->query("SELECT user_agent_category, COUNT(*) as count FROM telemetry_logs GROUP BY user_agent_category");
        while ($row = $stmt->fetch()) {
            if (isset($response['categories'][$row['user_agent_category']])) {
                $response['categories'][$row['user_agent_category']] = (int)$row['count'];
            }
        }
        $response['total_logs'] = (int)$pdo->query("SELECT COUNT(*) FROM telemetry_logs")->fetchColumn();
    } catch (Exception $e) {
        $response['status'] = 'error';
        $response['message'] = $e->getMessage();
    }
}

echo json_encode($response);
exit();