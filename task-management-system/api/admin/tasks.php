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

switch ($_SERVER['REQUEST_METHOD']) {
    case 'GET':
        // List all tasks
        $tasks = $task->getAllTasks();
        echo json_encode(['tasks' => $tasks]);
        break;
    case 'POST':
        // Add new task
        $data = json_decode(file_get_contents('php://input'), true);
        if (!isset($data['title'], $data['description'], $data['assigned_to'], $data['deadline'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing required fields.']);
            exit;
        }
        try {
            $assignedBy = $_SESSION['user_id'];
            $taskId = $task->createTask($data['title'], $data['description'], $data['assigned_to'], $assignedBy, $data['deadline']);
            echo json_encode(['success' => true, 'task_id' => $taskId]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
        break;
    case 'PUT':
        // Edit task (description only)
        $data = json_decode(file_get_contents('php://input'), true);
        if (!isset($data['id'], $data['description'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing required fields.']);
            exit;
        }
        try {
            $task->updateTaskDescription($data['id'], $data['description']);
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
        break;
    case 'DELETE':
        // Delete task
        $data = json_decode(file_get_contents('php://input'), true);
        if (!isset($data['id'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing task id.']);
            exit;
        }
        try {
            $task->deleteTask($data['id']);
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
