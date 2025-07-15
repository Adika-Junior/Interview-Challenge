<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, PUT');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

require_once __DIR__ . '/../classes/Task.php';

// JWT secret (should match all endpoints)
$jwtSecret = 'fe91e46f769cd291653f48b7e95aa58150f2a4c0094801cdc4f954ca670d3d47';
function getUserFromJWT($jwt, $secret) {
    if (!$jwt) return null;
    $parts = explode('.', $jwt);
    if (count($parts) !== 3) return null;
    $payload = json_decode(base64_decode($parts[1]), true);
    $sig = hash_hmac('sha256', $parts[0] . '.' . $parts[1], $secret, true);
    if (base64_encode($sig) !== strtr($parts[2], '-_', '+/')) return null;
    return $payload;
}
$headers = function_exists('getallheaders') ? getallheaders() : [];
$jwt = null;
if (isset($headers['Authorization'])) {
    $jwt = str_replace('Bearer ', '', $headers['Authorization']);
}
$user = getUserFromJWT($jwt, $jwtSecret);
if (!$user || !isset($user['id'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Access denied. Login required.']);
    exit;
}

$task = new Task();
$userId = $user['id'];

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
