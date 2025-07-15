<?php
require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../classes/TaskComment.php';
require_once __DIR__ . '/../classes/User.php';
require_once __DIR__ . '/../classes/Task.php';

header('Content-Type: application/json');

// Check user authentication using User class
$user = new User();
if (!$user->isLoggedIn()) {
    http_response_code(403);
    echo json_encode(['error' => 'Access denied. Login required.']);
    exit;
}

$comment = new TaskComment();
$task = new Task();
$userId = $_SESSION['user_id'];

switch ($_SERVER['REQUEST_METHOD']) {
    case 'GET':
        if (!isset($_GET['task_id'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing task_id.']);
            exit;
        }
        $taskInfo = $task->getTaskById($_GET['task_id']);
        if (!$taskInfo || $taskInfo['assigned_to'] != $userId) {
            http_response_code(403);
            echo json_encode(['error' => 'You can only view comments for your own tasks.']);
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
        $taskInfo = $task->getTaskById($data['task_id']);
        if (!$taskInfo || $taskInfo['assigned_to'] != $userId) {
            http_response_code(403);
            echo json_encode(['error' => 'You can only comment on your own tasks.']);
            exit;
        }
        $commentId = $comment->addComment($data['task_id'], $userId, $data['comment'], 0);
        echo json_encode(['success' => true, 'comment_id' => $commentId]);
        break;
    default:
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed.']);
        break;
} 