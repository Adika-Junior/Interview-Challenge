<?php
require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../classes/User.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed.']);
    exit;
}

try {
    // Log the logout event BEFORE destroying the session
    logAppEvent('user_logout', [
        'user_id' => $_SESSION['user_id'] ?? 'unknown',
        'username' => $_SESSION['username'] ?? 'unknown'
    ]);
    
    $user = new User();
    $user->logout();
    
    echo json_encode(['success' => true, 'message' => 'Logged out successfully.']);
} catch (Exception $e) {
    logError('ERROR', 'Logout failed', [
        'error' => $e->getMessage(),
        'user_id' => $_SESSION['user_id'] ?? 'unknown'
    ]);
    
    http_response_code(500);
    echo json_encode(['error' => 'Logout failed. Please try again.']);
}
