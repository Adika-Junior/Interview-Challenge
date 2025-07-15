<?php
// Include bootstrap for security and configuration
require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../classes/User.php';

// Ensure proper JSON response headers
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// Function to send JSON response and exit
function sendJsonResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

try {
    // Check request method
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        logError('Invalid request method', ['method' => $_SERVER['REQUEST_METHOD']]);
        sendJsonResponse(['error' => 'Method not allowed. Only POST requests are accepted.'], 405);
    }

    // Get and validate input
    $data = getSanitizedJsonInput();
    if (!$data) {
        $input = file_get_contents('php://input');
        if (empty($input)) {
            logError('Empty request body');
            sendJsonResponse(['error' => 'Request body is required.'], 400);
        }
        
        $data = json_decode($input, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            logError('Invalid JSON in request body', [
                'json_error' => json_last_error_msg(),
                'input' => substr($input, 0, 200)
            ]);
            sendJsonResponse(['error' => 'Invalid JSON format in request body.'], 400);
        }
    }

    // Validate required fields
    try {
        validateRequiredFields($data, ['username', 'password']);
    } catch (Exception $e) {
        logError('Missing required fields', ['error' => $e->getMessage(), 'provided_fields' => array_keys($data ?? [])]);
        sendJsonResponse(['error' => $e->getMessage()], 400);
    }

    // Attempt login
    $user = new User();
    if ($user->login($data['username'], $data['password'])) {
        $userInfo = $user->getCurrentUser();
        
        // Log successful login
        logAppEvent('user_login_success', [
            'username' => $data['username'],
            'user_id' => $userInfo['id']
        ]);
        
        sendJsonResponse(['success' => true, 'user' => $userInfo]);
    } else {
        // Log failed login attempt
        logAppEvent('user_login_failed', [
            'username' => $data['username'],
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
        ]);
        
        logError('Login failed - invalid credentials', [
            'username' => $data['username'],
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
        ]);
        
        sendJsonResponse(['error' => 'Invalid username or password.'], 401);
    }

} catch (Exception $e) {
    logError('Unexpected error during login', [
        'error' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString()
    ]);
    
    sendJsonResponse(['error' => 'An unexpected error occurred. Please try again later.'], 500);
}
