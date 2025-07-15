<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
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

// Get user tasks and stats
$stats = [
    'total_tasks' => 0,
    'pending_tasks' => 0,
    'in_progress_tasks' => 0,
    'completed_tasks' => 0
];
$userTasks = [];
try {
    $userId = $user['id'];
    $res = $con->prepare("SELECT t.*, u1.username as assigned_to_name, u2.username as assigned_by_name FROM tasks t LEFT JOIN users u1 ON t.assigned_to = u1.id LEFT JOIN users u2 ON t.assigned_by = u2.id WHERE t.assigned_to = ? ORDER BY t.created_at DESC");
    $res->bind_param('i', $userId);
    $res->execute();
    $result = $res->get_result();
    while ($row = $result->fetch_assoc()) {
        $userTasks[] = $row;
        $stats['total_tasks']++;
        if ($row['status'] === 'Pending') $stats['pending_tasks']++;
        if ($row['status'] === 'In Progress') $stats['in_progress_tasks']++;
        if ($row['status'] === 'Completed') $stats['completed_tasks']++;
    }
    echo json_encode([
        'tasks' => $userTasks,
        'stats' => $stats
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error', 'details' => $e->getMessage()]);
    exit;
}
