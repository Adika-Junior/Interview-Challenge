<?php
require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../classes/Task.php';
require_once __DIR__ . '/../classes/User.php';

header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('Connection: keep-alive');

$user = new User();
if (!$user->isLoggedIn()) {
    http_response_code(403);
    echo ": error\n";
    echo "data: {\"error\":\"Access denied. Login required.\"}\n\n";
    exit;
}

$task = new Task();
$userId = $_SESSION['user_id'];
$lastHash = '';

while (true) {
    $tasks = $task->getTasksByUser($userId);
    $hash = md5(json_encode($tasks));
    if ($hash !== $lastHash) {
        echo "data: " . json_encode(['tasks' => $tasks]) . "\n\n";
        ob_flush();
        flush();
        $lastHash = $hash;
    }
    // Send a comment to keep the connection alive every 10s
    echo ": keepalive\n\n";
    ob_flush();
    flush();
    sleep(10);
} 