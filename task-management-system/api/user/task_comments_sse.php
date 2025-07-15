<?php
require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../classes/TaskComment.php';
require_once __DIR__ . '/../classes/User.php';
require_once __DIR__ . '/../classes/Task.php';

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

if (!isset($_GET['task_id'])) {
    echo ": error\n";
    echo "data: {\"error\":\"Missing task_id.\"}\n\n";
    exit;
}

$taskId = (int)$_GET['task_id'];
$task = new Task();
$taskInfo = $task->getTaskById($taskId);
$userId = $_SESSION['user_id'];
if (!$taskInfo || $taskInfo['assigned_to'] != $userId) {
    echo ": error\n";
    echo "data: {\"error\":\"You can only view comments for your own tasks.\"}\n\n";
    exit;
}

$comment = new TaskComment();
$lastHash = '';
while (true) {
    $comments = $comment->getCommentsByTask($taskId);
    $hash = md5(json_encode($comments));
    if ($hash !== $lastHash) {
        echo "data: " . json_encode(['comments' => $comments]) . "\n\n";
        ob_flush();
        flush();
        $lastHash = $hash;
    }
    echo ": keepalive\n\n";
    ob_flush();
    flush();
    sleep(10);
} 