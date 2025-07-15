<?php
require_once 'Database.php';

/**
 * Class TaskComment
 * Handles adding and retrieving comments for tasks.
 */
class TaskComment {
    private $db;
    
    /**
     * TaskComment constructor.
     * Initializes the database connection.
     */
    public function __construct() {
        $this->db = new Database();
    }

    /**
     * Add a comment to a task.
     * @param int $taskId
     * @param int $userId
     * @param string $comment
     * @param int $isAdmin
     * @return int|false The inserted comment ID or false on failure.
     */
    public function addComment($taskId, $userId, $comment, $isAdmin = 0) {
        try {
            $this->db->safeQuery(
                "INSERT INTO task_comments (task_id, user_id, comment, is_admin) VALUES (?, ?, ?, ?)",
                [$taskId, $userId, $comment, $isAdmin]
            );
            return $this->db->lastInsertId();
        } catch (Exception $e) {
            // Log error and return false
            error_log('Error adding comment: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Retrieve all comments for a given task.
     * @param int $taskId
     * @return array|false List of comments or false on failure.
     */
    public function getCommentsByTask($taskId) {
        try {
            return $this->db->safeFetchAll(
                "SELECT tc.*, u.username, u.role FROM task_comments tc JOIN users u ON tc.user_id = u.id WHERE tc.task_id = ? ORDER BY tc.created_at ASC",
                [$taskId]
            );
        } catch (Exception $e) {
            // Log error and return false
            error_log('Error fetching comments: ' . $e->getMessage());
            return false;
        }
    }
} 