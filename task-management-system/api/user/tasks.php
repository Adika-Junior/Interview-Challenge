<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, PUT');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

require_once __DIR__ . '/../classes/Task.php';

// JWT secret (should match all endpoints)
$jwtSecret = 'fe91e46f769cd291653f48b7e95aa58150f2a4c0094801cdc4f954ca670d3d47';
function base64url_encode($data) {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}
function base64url_decode($data) {
    $remainder = strlen($data) % 4;
    if ($remainder) {
        $padlen = 4 - $remainder;
        $data .= str_repeat('=', $padlen);
    }
    return base64_decode(strtr($data, '-_', '+/'));
}
function getUserFromJWT($jwt, $secret) {
    if (!$jwt) return null;
    $parts = explode('.', $jwt);
    if (count($parts) !== 3) return null;
    $header = $parts[0];
    $payload = $parts[1];
    $sig = $parts[2];
    $expected_sig = base64url_encode(hash_hmac('sha256', "$header.$payload", $secret, true));
    if (!hash_equals($expected_sig, $sig)) return null;
    return json_decode(base64url_decode($payload), true);
}
function logDebug($data) {
    $logFile = '/tmp/user_tasks_debug.log';
    $entry = [
        'timestamp' => date('c'),
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'headers' => function_exists('getallheaders') ? getallheaders() : [],
        'jwt' => $GLOBALS['jwt'] ?? null,
        'user' => $GLOBALS['user'] ?? null,
        'request_method' => $_SERVER['REQUEST_METHOD'],
        'uri' => $_SERVER['REQUEST_URI'],
        'data' => $data
    ];
    file_put_contents($logFile, json_encode($entry) . PHP_EOL, FILE_APPEND | LOCK_EX);
}
$headers = function_exists('getallheaders') ? getallheaders() : [];
$jwt = null;
if (isset($headers['Authorization'])) {
    $jwt = str_replace('Bearer ', '', $headers['Authorization']);
} elseif (isset($headers['authorization'])) {
    $jwt = str_replace('Bearer ', '', $headers['authorization']);
}
$GLOBALS['jwt'] = $jwt;
$user = getUserFromJWT($jwt, $jwtSecret);
$GLOBALS['user'] = $user;
if (!$user || !isset($user['id'])) {
    logDebug(['error' => 'Access denied. Login required.']);
    http_response_code(401);
    echo json_encode(['error' => 'Access denied. Login required.']);
    exit;
}

$task = new Task();
$userId = $user['id'];

try {
    switch ($_SERVER['REQUEST_METHOD']) {
        case 'GET':
            // List tasks assigned to the logged-in user
            try {
            $tasks = $task->getTasksByUser($userId);
            echo json_encode(['tasks' => $tasks]);
            } catch (Exception $e) {
                http_response_code(500);
                echo json_encode(['error' => 'Server error', 'details' => $e->getMessage()]);
                exit;
            }
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
            try {
            $taskDetails = $task->getTaskById($data['id']);
            if (!$taskDetails || $taskDetails['assigned_to'] != $userId) {
                    logDebug(['error' => 'You can only update your own tasks.', 'taskDetails' => $taskDetails, 'userId' => $userId]);
                http_response_code(403);
                echo json_encode(['error' => 'You can only update your own tasks.']);
                exit;
            }
                $task->updateTaskStatus($data['id'], $data['status']);
                echo json_encode(['success' => true]);
            } catch (Exception $e) {
                http_response_code(500);
                echo json_encode(['error' => 'Server error', 'details' => $e->getMessage()]);
                exit;
            }
            break;
        default:
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed.']);
            break;
    }
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
    exit;
}
