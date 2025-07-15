<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
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
if (!$user || !isset($user['id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Access denied. Login required.']);
    exit;
}
$host = 'taskmanagement.mysql.database.azure.com';
$db   = 'task_management';
$dbuser = 'Pleasant';
$dbpass = 'Adika123';
$con = mysqli_init();
mysqli_ssl_set($con, NULL, NULL, NULL, NULL, NULL);
mysqli_options($con, MYSQLI_OPT_SSL_VERIFY_SERVER_CERT, false);
try {
    if (!mysqli_real_connect($con, $host, $dbuser, $dbpass, $db, 3306, NULL, MYSQLI_CLIENT_SSL)) {
        echo json_encode(['error' => 'Database connection failed: ' . mysqli_connect_error()]);
        exit;
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error', 'details' => $e->getMessage()]);
    exit;
}
$userId = $user['id'];
$stats = [
    'total_tasks' => 0,
    'pending_tasks' => 0,
    'in_progress_tasks' => 0,
    'completed_tasks' => 0
];
$res = $con->query("SELECT status, COUNT(*) as count FROM tasks WHERE assigned_to = " . intval($userId) . " GROUP BY status");
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $stats['total_tasks'] += $row['count'];
        if ($row['status'] === 'Pending') $stats['pending_tasks'] = $row['count'];
        if ($row['status'] === 'In Progress') $stats['in_progress_tasks'] = $row['count'];
        if ($row['status'] === 'Completed') $stats['completed_tasks'] = $row['count'];
    }
}
echo json_encode(['stats' => $stats]);
