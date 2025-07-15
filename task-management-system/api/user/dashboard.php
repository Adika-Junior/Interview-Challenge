<?php
require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../classes/Task.php';
require_once __DIR__ . '/../classes/User.php';

header('Content-Type: application/json');

// Check user authentication using User class
$user = new User();
if (!$user->isLoggedIn()) {
    http_response_code(403);
    echo json_encode(['error' => 'Access denied. Login required.']);
    exit;
}

$task = new Task();
$userId = $_SESSION['user_id'];
$tasks = $task->getTasksByUser($userId);

$stats = [
    'total_tasks' => count($tasks),
    'pending_tasks' => 0,
    'in_progress_tasks' => 0,
    'completed_tasks' => 0
];
foreach ($tasks as $t) {
    if ($t['status'] === 'Pending') $stats['pending_tasks']++;
    if ($t['status'] === 'In Progress') $stats['in_progress_tasks']++;
    if ($t['status'] === 'Completed') $stats['completed_tasks']++;
}
echo json_encode(['stats' => $stats]);
