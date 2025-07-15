<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

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
if (!$user || $user['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['error' => 'Access denied. Admins only.']);
    exit;
}
$host = 'taskmanagement.mysql.database.azure.com';
$db   = 'task_management';
$dbuser = 'Pleasant';
$dbpass = 'Adika123';
$con = mysqli_init();
mysqli_ssl_set($con, NULL, NULL, NULL, NULL, NULL);
mysqli_options($con, MYSQLI_OPT_SSL_VERIFY_SERVER_CERT, false);
if (!mysqli_real_connect($con, $host, $dbuser, $dbpass, $db, 3306, NULL, MYSQLI_CLIENT_SSL)) {
    echo json_encode(['error' => 'Database connection failed: ' . mysqli_connect_error()]);
    exit;
}
$method = $_SERVER['REQUEST_METHOD'];
if ($method === 'GET') {
    $res = $con->query("SELECT id, username, email, role, created_at FROM users");
    $users = [];
    while ($row = $res->fetch_assoc()) $users[] = $row;
    echo json_encode(['users' => $users]);
} elseif ($method === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    if (!isset($data['username'], $data['email'], $data['password'], $data['role']) || empty($data['password'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing required fields or password.']);
        exit;
    }
    $stmt = $con->prepare("INSERT INTO users (username, email, password, role) VALUES (?, ?, ?, ?)");
    $hashedPassword = password_hash($data['password'], PASSWORD_DEFAULT);
    $stmt->bind_param('ssss', $data['username'], $data['email'], $hashedPassword, $data['role']);
    $ok = $stmt->execute();
    if ($ok) {
        echo json_encode(['success' => true, 'user_id' => $con->insert_id]);
    } else {
        http_response_code(500);
        echo json_encode(['error' => $stmt->error]);
    }
} elseif ($method === 'PUT') {
    $data = json_decode(file_get_contents('php://input'), true);
    if (!isset($data['id'], $data['username'], $data['email'], $data['role'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing required fields.']);
        exit;
    }
    if (!empty($data['password'])) {
        $stmt = $con->prepare("UPDATE users SET username = ?, email = ?, role = ?, password = ? WHERE id = ?");
        $hashedPassword = password_hash($data['password'], PASSWORD_DEFAULT);
        $stmt->bind_param('ssssi', $data['username'], $data['email'], $data['role'], $hashedPassword, $data['id']);
    } else {
        $stmt = $con->prepare("UPDATE users SET username = ?, email = ?, role = ? WHERE id = ?");
        $stmt->bind_param('sssi', $data['username'], $data['email'], $data['role'], $data['id']);
    }
    $ok = $stmt->execute();
    if ($ok) {
        echo json_encode(['success' => true]);
    } else {
        http_response_code(500);
        echo json_encode(['error' => $stmt->error]);
    }
} elseif ($method === 'DELETE') {
    $data = json_decode(file_get_contents('php://input'), true);
    if (!isset($data['id'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing user id.']);
        exit;
    }
    $stmt = $con->prepare("DELETE FROM users WHERE id = ?");
    $stmt->bind_param('i', $data['id']);
    $ok = $stmt->execute();
    if ($ok) {
        echo json_encode(['success' => true]);
    } else {
        http_response_code(500);
        echo json_encode(['error' => $stmt->error]);
    }
} else {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed.']);
}
