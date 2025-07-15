<?php
// Include bootstrap for security and configuration
require_once 'bootstrap.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Include classes
require_once 'classes/Database.php';
require_once 'classes/User.php';
require_once 'classes/Task.php';
require_once 'classes/EmailService.php';

// Get the request method and URI
$method = $_SERVER['REQUEST_METHOD'];
$uri = $_SERVER['REQUEST_URI'];

// Parse the URI to get the endpoint
$path = parse_url($uri, PHP_URL_PATH);
$path = str_replace('/api', '', $path);
$segments = explode('/', trim($path, '/'));

// Initialize response
$response = ['success' => false, 'message' => '', 'data' => null];

try {
    // Route the request
    switch ($segments[0]) {
        case 'auth':
            handleAuth($method, $segments, $response);
            break;
        case 'users':
            handleUsers($method, $segments, $response);
            break;
        case 'tasks':
            handleTasks($method, $segments, $response);
            break;
        case 'dashboard':
            handleDashboard($method, $segments, $response);
            break;
        default:
            $response['message'] = 'Invalid endpoint';
            http_response_code(404);
    }
} catch (Exception $e) {
    $response['message'] = $e->getMessage();
    http_response_code(500);
}

echo json_encode($response);

// Authentication endpoints
function handleAuth($method, $segments, &$response) {
    $user = new User();
    
    if ($method === 'POST' && isset($segments[1])) {
        $data = json_decode(file_get_contents('php://input'), true);
        
        switch ($segments[1]) {
            case 'login':
                if ($user->login($data['username'], $data['password'])) {
                    $response['success'] = true;
                    $response['message'] = 'Login successful';
                    $response['data'] = $user->getCurrentUser();
                } else {
                    $response['message'] = 'Invalid credentials';
                    http_response_code(401);
                }
                break;
            case 'logout':
                $user->logout();
                $response['success'] = true;
                $response['message'] = 'Logout successful';
                break;
            case 'check':
                if ($user->isLoggedIn()) {
                    $response['success'] = true;
                    $response['data'] = $user->getCurrentUser();
                } else {
                    $response['message'] = 'Not authenticated';
                    http_response_code(401);
                }
                break;
        }
    }
}

// User management endpoints
function handleUsers($method, $segments, &$response) {
    $user = new User();
    
    if (!$user->isLoggedIn()) {
        $response['message'] = 'Authentication required';
        http_response_code(401);
        return;
    }
    
    switch ($method) {
        case 'GET':
            if ($user->isAdmin()) {
                $response['success'] = true;
                $response['data'] = $user->getAllUsers();
            } else {
                $response['message'] = 'Admin access required';
                http_response_code(403);
            }
            break;
        case 'POST':
            if ($user->isAdmin()) {
                $data = json_decode(file_get_contents('php://input'), true);
                $userId = $user->createUser($data['username'], $data['email'], $data['password'], $data['role']);
                $response['success'] = true;
                $response['message'] = 'User created successfully';
                $response['data'] = ['id' => $userId];
            } else {
                $response['message'] = 'Admin access required';
                http_response_code(403);
            }
            break;
        case 'PUT':
            if ($user->isAdmin() && isset($segments[1])) {
                $data = json_decode(file_get_contents('php://input'), true);
                $user->updateUser($segments[1], $data['username'], $data['email'], $data['role']);
                $response['success'] = true;
                $response['message'] = 'User updated successfully';
            } else {
                $response['message'] = 'Admin access required';
                http_response_code(403);
            }
            break;
        case 'DELETE':
            if ($user->isAdmin() && isset($segments[1])) {
                $user->deleteUser($segments[1]);
                $response['success'] = true;
                $response['message'] = 'User deleted successfully';
            } else {
                $response['message'] = 'Admin access required';
                http_response_code(403);
            }
            break;
    }
}

// Task management endpoints
function handleTasks($method, $segments, &$response) {
    $user = new User();
    $task = new Task();
    
    if (!$user->isLoggedIn()) {
        $response['message'] = 'Authentication required';
        http_response_code(401);
        return;
    }
    
    $currentUser = $user->getCurrentUser();
    
    switch ($method) {
        case 'GET':
            if (isset($segments[1]) && $segments[1] === 'my') {
                // Get tasks for current user
                $response['success'] = true;
                $response['data'] = $task->getTasksByUser($currentUser['id']);
            } elseif ($user->isAdmin()) {
                // Get all tasks (admin only)
                $response['success'] = true;
                $response['data'] = $task->getAllTasks();
            } else {
                $response['message'] = 'Access denied';
                http_response_code(403);
            }
            break;
        case 'POST':
            if ($user->isAdmin()) {
                $data = json_decode(file_get_contents('php://input'), true);
                $taskId = $task->createTask(
                    $data['title'],
                    $data['description'],
                    $data['assigned_to'],
                    $currentUser['id'],
                    $data['deadline']
                );
                $response['success'] = true;
                $response['message'] = 'Task created successfully';
                $response['data'] = ['id' => $taskId];
            } else {
                $response['message'] = 'Admin access required';
                http_response_code(403);
            }
            break;
        case 'PUT':
            if (isset($segments[1])) {
                $data = json_decode(file_get_contents('php://input'), true);
                
                if (isset($data['status'])) {
                    // Update task status (allowed for assigned user)
                    $taskInfo = $task->getTaskById($segments[1]);
                    if ($taskInfo && ($taskInfo['assigned_to'] == $currentUser['id'] || $user->isAdmin())) {
                        $task->updateTaskStatus($segments[1], $data['status']);
                        $response['success'] = true;
                        $response['message'] = 'Task status updated successfully';
                    } else {
                        $response['message'] = 'Access denied';
                        http_response_code(403);
                    }
                } else {
                    // Update task details (admin only)
                    if ($user->isAdmin()) {
                        $task->updateTask(
                            $segments[1],
                            $data['title'],
                            $data['description'],
                            $data['assigned_to'],
                            $data['deadline']
                        );
                        $response['success'] = true;
                        $response['message'] = 'Task updated successfully';
                    } else {
                        $response['message'] = 'Admin access required';
                        http_response_code(403);
                    }
                }
            }
            break;
        case 'DELETE':
            if ($user->isAdmin() && isset($segments[1])) {
                $task->deleteTask($segments[1]);
                $response['success'] = true;
                $response['message'] = 'Task deleted successfully';
            } else {
                $response['message'] = 'Admin access required';
                http_response_code(403);
            }
            break;
    }
}

// Dashboard endpoints
function handleDashboard($method, $segments, &$response) {
    $user = new User();
    $task = new Task();
    
    if (!$user->isLoggedIn()) {
        $response['message'] = 'Authentication required';
        http_response_code(401);
        return;
    }
    
    if ($method === 'GET') {
        $currentUser = $user->getCurrentUser();
        
        if ($user->isAdmin()) {
            // Admin dashboard
            $stats = $task->getTaskStats();
            $recentTasks = $task->getAllTasks();
            $response['success'] = true;
            $response['data'] = [
                'stats' => $stats,
                'recent_tasks' => array_slice($recentTasks, 0, 5)
            ];
        } else {
            // User dashboard
            $userTasks = $task->getTasksByUser($currentUser['id']);
            $response['success'] = true;
            $response['data'] = [
                'tasks' => $userTasks,
                'stats' => [
                    'total_tasks' => count($userTasks),
                    'pending_tasks' => count(array_filter($userTasks, fn($t) => $t['status'] === 'Pending')),
                    'in_progress_tasks' => count(array_filter($userTasks, fn($t) => $t['status'] === 'In Progress')),
                    'completed_tasks' => count(array_filter($userTasks, fn($t) => $t['status'] === 'Completed'))
                ]
            ];
        }
    }
}
?>