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

switch ($_SERVER['REQUEST_METHOD']) {
    case 'GET':
        // List tasks assigned to the logged-in user
        $tasks = $task->getTasksByUser($userId);
        echo json_encode(['tasks' => $tasks]);
        break;
    case 'PUT':
        // Update status of a task assigned to the user
        $data = json_decode(file_get_contents('php://input'), true);
        if (!isset($data['id'], $data['status'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing required fields.']);
            exit;
        }
        // Check if the task belongs to the user
        $taskDetails = $task->getTaskById($data['id']);
        if (!$taskDetails || $taskDetails['assigned_to'] != $userId) {
            http_response_code(403);
            echo json_encode(['error' => 'You can only update your own tasks.']);
            exit;
        }
        try {
            $task->updateTaskStatus($data['id'], $data['status']);
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
        break;
    default:
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed.']);
        break;
}
