<?php
require_once 'Database.php';

class TaskComment {
    private $db;
    public function __construct() {
        $this->db = new Database();
    }

    public function addComment($taskId, $userId, $comment, $isAdmin = 0) {
        $this->db->safeQuery(
            "INSERT INTO task_comments (task_id, user_id, comment, is_admin) VALUES (?, ?, ?, ?)",
            [$taskId, $userId, $comment, $isAdmin]
        );
        return $this->db->lastInsertId();
    }

    public function getCommentsByTask($taskId) {
        return $this->db->safeFetchAll(
            "SELECT tc.*, u.username, u.role FROM task_comments tc JOIN users u ON tc.user_id = u.id WHERE tc.task_id = ? ORDER BY tc.created_at ASC",
            [$taskId]
        );
    }
} 