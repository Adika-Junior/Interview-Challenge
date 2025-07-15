<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

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
error_log('ADMIN TASKS JWT payload: ' . json_encode($user));
if (!$user || $user['role'] !== 'admin') {
    http_response_code(401);
    echo json_encode(['error' => 'Access denied. Admins only.']);
    exit;
}
require_once __DIR__ . '/../classes/EmailService.php';
try {
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
        $res = $con->query("SELECT * FROM tasks");
        $tasks = [];
        while ($row = $res->fetch_assoc()) $tasks[] = $row;
        echo json_encode(['tasks' => $tasks]);
    } elseif ($method === 'POST') {
        $data = json_decode(file_get_contents('php://input'), true);
        if (!isset($data['title'], $data['description'], $data['assigned_to'], $data['deadline'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing required fields.']);
            exit;
        }
        $assignedBy = $user['id'];
        $stmt = $con->prepare("INSERT INTO tasks (title, description, assigned_to, assigned_by, deadline) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param('ssiss', $data['title'], $data['description'], $data['assigned_to'], $assignedBy, $data['deadline']);
        $ok = $stmt->execute();
        if ($ok) {
            $insertId = $con->insert_id;
            error_log('Task insert_id: ' . $insertId);
            if (!$insertId) {
                http_response_code(500);
                echo json_encode(['error' => 'Task creation failed: insert_id is 0.']);
                exit;
            }
            // Fetch assigned user's email and username
            $userStmt = $con->prepare("SELECT email, username FROM users WHERE id = ?");
            $userStmt->bind_param('i', $data['assigned_to']);
            $userStmt->execute();
            $userResult = $userStmt->get_result();
            $assignedUser = $userResult->fetch_assoc();
            if ($assignedUser) {
                $taskDetails = [
                    'title' => $data['title'],
                    'description' => $data['description'],
                    'deadline' => $data['deadline'],
                    'assigned_by_name' => $user['username'] ?? 'Admin',
                ];
                $emailService = new EmailService();
                $emailService->sendTaskAssignmentEmail($assignedUser['email'], $taskDetails);
            }
            echo json_encode(['success' => true, 'task_id' => $insertId]);
        } else {
            error_log('Task creation error: ' . $stmt->error);
            http_response_code(500);
            echo json_encode(['error' => $stmt->error]);
        }
    } elseif ($method === 'PUT') {
        $data = json_decode(file_get_contents('php://input'), true);
        if (!isset($data['id'], $data['description'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing required fields.']);
            exit;
        }
        $stmt = $con->prepare("UPDATE tasks SET description = ? WHERE id = ?");
        $stmt->bind_param('si', $data['description'], $data['id']);
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
            echo json_encode(['error' => 'Missing task id.']);
            exit;
        }
        $stmt = $con->prepare("DELETE FROM tasks WHERE id = ?");
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
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error', 'details' => $e->getMessage()]);
    exit;
}
