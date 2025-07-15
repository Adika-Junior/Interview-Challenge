<?php
header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('Connection: keep-alive');

require_once __DIR__ . '/../classes/Task.php';

// JWT secret (should match all endpoints)
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
} elseif (isset($headers['authorization'])) {
    $jwt = str_replace('Bearer ', '', $headers['authorization']);
}
$user = getUserFromJWT($jwt, $jwtSecret);
if (!$user || !isset($user['id'])) {
    http_response_code(401);
    echo ": error\n";
    echo "data: {\"error\":\"Access denied. Login required.\"}\n\n";
    exit;
}
try {
    $task = new Task();
    $userId = $user['id'];
    $lastHash = '';

    while (true) {
        $tasks = $task->getTasksByUser($userId);
        $hash = md5(json_encode($tasks));
        if ($hash !== $lastHash) {
            echo "data: " . json_encode(['tasks' => $tasks]) . "\n\n";
            ob_flush();
            flush();
            $lastHash = $hash;
        }
        // Send a comment to keep the connection alive every 10s
        echo ": keepalive\n\n";
        ob_flush();
        flush();
        sleep(10);
    }
} catch (Exception $e) {
    echo ": error\n";
    echo "data: {\"error\":\"Server error\",\"details\":\"{$e->getMessage()}\"}\n\n";
    exit;
} 