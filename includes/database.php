<?php
if (!defined('VALKYRIN_EXEC')) {
    header("HTTP/1.1 403 Forbidden");
    exit("Access Denied");
}

require_once __DIR__ . '/config.php';

try {
    $dsn = "mysql:host=" . ($_ENV['DB_HOST'] ?? 'localhost') . ";dbname=" . ($_ENV['DB_NAME'] ?? '') . ";charset=utf8mb4";
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];
    
    $pdo = new PDO($dsn, $_ENV['DB_USER'] ?? '', $_ENV['DB_PASS'] ?? '', $options);
} catch (PDOException $e) {
    // Hide real database error traces from potential attackers
    error_log("Database Connection Error: " . $e->getMessage());
    die(json_encode(["status" => 500, "message" => "Database Connection Failed"]));
}

// Near-ZK Telemetry Logger
if (!function_exists('logTelemetry')) {
    function logTelemetry($pdo) {
        $rawIp = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $dailySalt = date('Y-m-d') . APP_SALT;
        $ipHash = hash('sha256', $rawIp . $dailySalt);
        
        $rawAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
        $agentCategory = 'Standard Browser';
        if (preg_match('/bot|crawl|slurp|spider|mediapartners/i', $rawAgent)) {
            $agentCategory = 'Crawler Bot';
        } elseif (preg_match('/curl|wget|python|postman|guzzle/i', $rawAgent)) {
            $agentCategory = 'Automated Tool';
        }

        $uri = substr($_SERVER['REQUEST_URI'] ?? '/', 0, 255);
        $referer = isset($_SERVER['HTTP_REFERER']) ? substr($_SERVER['HTTP_REFERER'], 0, 255) : null;

        try {
            $stmt = $pdo->prepare("INSERT INTO telemetry_logs (ip_hash, user_agent_category, request_uri, referer) VALUES (?, ?, ?, ?)");
            $stmt->execute([$ipHash, $agentCategory, $uri, $referer]);
        } catch (Exception $e) {
            error_log("Telemetry logging failed: " . $e->getMessage());
        }
    }
}