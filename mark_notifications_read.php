<?php
define('VALKYRIN_EXEC', true);
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id'])) exit();

require_once __DIR__ . '/includes/header.php';

if (isset($pdo)) {
    $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?");
    $stmt->execute([(int)$_SESSION['user_id']]);
}