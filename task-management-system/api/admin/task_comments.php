<?php
require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../classes/TaskComment.php';
require_once __DIR__ . '/../classes/User.php';

header('Content-Type: application/json');

// Check admin authentication using User class
$user = new User();
if (!$user->isAdmin()) {
    http_response_code(403);
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
        $comments = $comment->getCommentsByTask($_GET['task_id']);
        echo json_encode(['comments' => $comments]);
        break;
    case 'POST':
        $data = json_decode(file_get_contents('php://input'), true);
        if (!isset($data['task_id'], $data['comment'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing required fields.']);
            exit;
        }
        $userId = $_SESSION['user_id'];
        $commentId = $comment->addComment($data['task_id'], $userId, $data['comment'], 1);
        echo json_encode(['success' => true, 'comment_id' => $commentId]);
        break;
    default:
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed.']);
        break;
} 