<?php
require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../classes/User.php';
require_once __DIR__ . '/../classes/EmailService.php';
require_once __DIR__ . '/../config/Config.php';

header('Content-Type: application/json');

// Check admin authentication using User class
$user = new User();
if (!$user->isAdmin()) {
    http_response_code(403);
    echo json_encode(['error' => 'Access denied. Admins only.']);
    exit;
}

$config = Config::getInstance();
$appConfig = $config->getAppConfig();

switch ($_SERVER['REQUEST_METHOD']) {
    case 'GET':
        // List all users
        $users = $user->getAllUsers();
        echo json_encode(['users' => $users]);
        break;
    case 'POST':
        // Add new user
        $data = json_decode(file_get_contents('php://input'), true);
        if (!isset($data['username'], $data['email'], $data['password'], $data['role']) || empty($data['password'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing required fields or password.']);
            exit;
        }
        try {
            $userId = $user->createUser($data['username'], $data['email'], $data['password'], $data['role']);
            // Send credentials email
            $emailService = new EmailService();
            $subject = 'Welcome to Task Management System';
            $body = "<h2>Welcome, {$data['username']}!</h2>"
                  . "<p>Your account has been created. You can now log in with the following credentials:</p>"
                  . "<p><strong>Username/Email:</strong> {$data['email']}</p>"
                  . "<p><strong>Password:</strong> {$data['password']}</p>"
                  . "<p><a href='{$appConfig['url']}'>Login here</a></p>";
            $emailService->sendCustomEmail($data['email'], $subject, $body);
            echo json_encode(['success' => true, 'user_id' => $userId]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
        break;
    case 'PUT':
        // Edit user
        $data = json_decode(file_get_contents('php://input'), true);
        if (!isset($data['id'], $data['username'], $data['email'], $data['role'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing required fields.']);
            exit;
        }
        try {
            $emailService = new EmailService();
            $subject = 'Your Task Management Account Credentials Updated';
            if (!empty($data['password'])) {
                // If password is provided, update it
                $user->updateUserWithPassword($data['id'], $data['username'], $data['email'], $data['role'], $data['password']);
                // Send updated credentials email (with password)
                $body = "<h2>Your account credentials have been updated.</h2>"
                      . "<p><strong>Username/Email:</strong> {$data['email']}</p>"
                      . "<p><strong>Password:</strong> {$data['password']}</p>"
                      . "<p><em>Your password was changed by the admin.</em></p>"
                      . "<p><a href='{$appConfig['url']}'>Login here</a></p>";
            } else {
                // Otherwise, update without changing password
                $user->updateUser($data['id'], $data['username'], $data['email'], $data['role']);
                // Send updated credentials email (without password)
                $body = "<h2>Your account credentials have been updated.</h2>"
                      . "<p><strong>Username/Email:</strong> {$data['email']}</p>"
                      . "<p><em>Your password was not changed.</em></p>"
                      . "<p><a href='{$appConfig['url']}'>Login here</a></p>";
            }
            $sent = $emailService->sendCustomEmail($data['email'], $subject, $body);
            if (!$sent) {
                http_response_code(500);
                echo json_encode(['error' => 'Failed to send credentials email.']);
                exit;
            }
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
        break;
    case 'DELETE':
        // Delete user
        $data = json_decode(file_get_contents('php://input'), true);
        if (!isset($data['id'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing user id.']);
            exit;
        }
        try {
            $user->deleteUser($data['id']);
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
