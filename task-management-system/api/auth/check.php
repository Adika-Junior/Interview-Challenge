<?php
require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../classes/User.php';

header('Content-Type: application/json');

try {
    $user = new User();
    if ($user->isLoggedIn()) {
        echo json_encode(['user' => $user->getCurrentUser()]);
    } else {
        http_response_code(401);
        echo json_encode(['error' => 'Not authenticated']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
} 