<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit(0);

session_start();

if (isset($_SESSION['user_id'])) {
    echo json_encode([
        'user' => [
            'id' => $_SESSION['user_id'],
            'username' => $_SESSION['username'] ?? null,
            'role' => $_SESSION['role'] ?? null,
            'email' => $_SESSION['email'] ?? null
        ]
    ]);
} else {
    http_response_code(401);
    echo json_encode(['error' => 'Not authenticated']);
} 