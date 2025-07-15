<?php
require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../classes/Task.php';
require_once __DIR__ . '/../classes/User.php';

header('Content-Type: application/json');

// Check admin authentication using User class
$user = new User();
if (!$user->isAdmin()) {
    http_response_code(403);
    echo json_encode(['error' => 'Access denied. Admins only.']);
    exit;
}

$task = new Task();
$stats = $task->getTaskStats();
echo json_encode(['stats' => $stats]);
