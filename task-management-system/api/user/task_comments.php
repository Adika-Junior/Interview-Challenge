<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Add direct mysqli connection and queries after JWT validation
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
$headers = function_exists('getallheaders') ? getallheaders() : [];
$jwt = null;
if (isset($headers['Authorization'])) {
    $jwt = str_replace('Bearer ', '', $headers['Authorization']);
} elseif (isset($headers['authorization'])) {
    $jwt = str_replace('Bearer ', '', $headers['authorization']);
}
$user = getUserFromJWT($jwt, $jwtSecret);
if (!$user || !isset($user['id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Access denied. Login required.']);
    exit;
}
try {
    $method = $_SERVER['REQUEST_METHOD'];
    $userId = $user['id'];

    if ($method === 'GET') {
        if (!isset($_GET['task_id'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing task_id.']);
            exit;
        }
        $taskId = (int)$_GET['task_id'];
        // Only allow comments for tasks assigned to this user
        $taskRes = $con->prepare("SELECT id FROM tasks WHERE id = ? AND assigned_to = ?");
        $taskRes->bind_param('ii', $taskId, $userId);
        $taskRes->execute();
        $taskCheck = $taskRes->get_result()->fetch_assoc();
        if (!$taskCheck) {
            http_response_code(403);
            echo json_encode(['error' => 'You can only view comments for your own tasks.']);
            exit;
        }
        $res = $con->prepare("SELECT c.*, u.username FROM task_comments c LEFT JOIN users u ON c.user_id = u.id WHERE c.task_id = ? ORDER BY c.created_at ASC");
        $res->bind_param('i', $taskId);
        $res->execute();
        $result = $res->get_result();
        $comments = [];
        while ($row = $result->fetch_assoc()) $comments[] = $row;
        echo json_encode(['comments' => $comments]);
    } elseif ($method === 'POST') {
        $data = json_decode(file_get_contents('php://input'), true);
        if (!isset($data['task_id'], $data['comment'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing required fields.']);
            exit;
        }
        $taskId = (int)$data['task_id'];
        // Only allow comments for tasks assigned to this user
        $taskRes = $con->prepare("SELECT id FROM tasks WHERE id = ? AND assigned_to = ?");
        $taskRes->bind_param('ii', $taskId, $userId);
        $taskRes->execute();
        $taskCheck = $taskRes->get_result()->fetch_assoc();
        if (!$taskCheck) {
            http_response_code(403);
            echo json_encode(['error' => 'You can only comment on your own tasks.']);
            exit;
        }
        $stmt = $con->prepare("INSERT INTO task_comments (task_id, user_id, comment) VALUES (?, ?, ?)");
        $stmt->bind_param('iis', $taskId, $userId, $data['comment']);
        $ok = $stmt->execute();
        if ($ok) {
            echo json_encode(['success' => true, 'comment_id' => $con->insert_id]);
        } else {
            http_response_code(500);
            echo json_encode(['error' => $stmt->error]);
        }
    } else {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed.']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error', 'details' => $e->getMessage()]);
    exit;
} 