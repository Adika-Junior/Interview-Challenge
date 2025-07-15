<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit(0);

function sendResponse($data, $code = 200) {
    http_response_code($code);
    echo json_encode($data);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse(['error' => 'Method not allowed'], 405);
}

$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data || !isset($data['username']) || !isset($data['password'])) {
    sendResponse(['error' => 'Username and password required'], 400);
}

// Azure MySQL SSL connection using mysqli
$host = 'taskmanagement.mysql.database.azure.com';
$db   = 'task_management';
$user = 'Pleasant@taskmanagement';
$pass = 'Adika123';
$certPath = __DIR__ . '/../certs/BaltimoreCyberTrustRoot.crt.pem';

$con = mysqli_init();
mysqli_ssl_set($con, NULL, NULL, $certPath, NULL, NULL);
if (!mysqli_real_connect($con, $host, $user, $pass, $db, 3306, NULL, MYSQLI_CLIENT_SSL)) {
    sendResponse(['error' => 'Database connection failed: ' . mysqli_connect_error()], 500);
}

// Inline user check
$stmt = $con->prepare("SELECT * FROM users WHERE username = ? OR email = ?");
if (!$stmt) {
    sendResponse(['error' => 'Prepare failed: ' . $con->error], 500);
}
$stmt->bind_param('ss', $data['username'], $data['username']);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

if ($user && password_verify($data['password'], $user['password'])) {
    sendResponse(['success' => true, 'user' => [
        'id' => $user['id'],
        'username' => $user['username'],
        'role' => $user['role'],
        'email' => $user['email']
    ]]);
} else {
    sendResponse(['error' => 'Invalid credentials'], 401);
}
