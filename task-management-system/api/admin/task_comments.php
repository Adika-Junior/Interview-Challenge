<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

require_once __DIR__ . '/../classes/TaskComment.php';

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
if (!$user || $user['role'] !== 'admin') {
    http_response_code(401);
    echo json_encode(['error' => 'Access denied. Admins only.']);
    exit;
}

$comment = new TaskComment();

switch ($_SERVER['REQUEST_METHOD']) {
    case 'GET':
        if (!isset($_GET['task_id'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing task_id.']);
            exit;
        }
        try {
            $comments = $comment->getCommentsByTask($_GET['task_id']);
            echo json_encode(['comments' => $comments]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Server error', 'details' => $e->getMessage()]);
            exit;
        }
        break;
    case 'POST':
        $data = json_decode(file_get_contents('php://input'), true);
        if (!isset($data['task_id'], $data['comment'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing required fields.']);
            exit;
        }
        try {
            $userId = $user['id'];
            $commentId = $comment->addComment($data['task_id'], $userId, $data['comment'], 1);
            echo json_encode(['success' => true, 'comment_id' => $commentId]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Server error', 'details' => $e->getMessage()]);
            exit;
        }
        break;
    default:
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed.']);
        break;
} 