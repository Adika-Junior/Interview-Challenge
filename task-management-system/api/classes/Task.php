<?php
require_once 'Database.php';
require_once 'EmailService.php';

class Task {
    private $db;
    
    public function __construct() {
        $this->db = new Database();
    }
    
    public function getAllTasks() {
        return $this->db->safeFetchAll("
            SELECT t.*, 
                   u1.username as assigned_to_name,
                   u2.username as assigned_by_name
            FROM tasks t
            LEFT JOIN users u1 ON t.assigned_to = u1.id
            LEFT JOIN users u2 ON t.assigned_by = u2.id
            ORDER BY t.created_at DESC
        ");
    }
    
    public function getTasksByUser($userId) {
        return $this->db->safeFetchAll("
            SELECT t.*, 
                   u1.username as assigned_to_name,
                   u2.username as assigned_by_name
            FROM tasks t
            LEFT JOIN users u1 ON t.assigned_to = u1.id
            LEFT JOIN users u2 ON t.assigned_by = u2.id
            WHERE t.assigned_to = ?
            ORDER BY t.created_at DESC
        ", [$userId]);
    }
    
    public function getTaskById($id) {
        return $this->db->safeFetch("
            SELECT t.*, 
                   u1.username as assigned_to_name,
                   u2.username as assigned_by_name
            FROM tasks t
            LEFT JOIN users u1 ON t.assigned_to = u1.id
            LEFT JOIN users u2 ON t.assigned_by = u2.id
            WHERE t.id = ?
        ", [$id]);
    }
    
    public function createTask($title, $description, $assignedTo, $assignedBy, $deadline) {
        try {
            $this->db->safeQuery("
                INSERT INTO tasks (title, description, assigned_to, assigned_by, deadline, status) 
                VALUES (?, ?, ?, ?, ?, 'Pending')
            ", [$title, $description, $assignedTo, $assignedBy, $deadline]);
            
            $taskId = $this->db->lastInsertId();
            $this->sendTaskNotification($taskId);
            return $taskId;
        } catch (PDOException $e) {
            throw new Exception("Error creating task: " . $e->getMessage());
        }
    }

    // Only allow admin to update description
    public function updateTaskDescription($id, $description) {
        try {
            $this->db->safeQuery("
                UPDATE tasks 
                SET description = ?
                WHERE id = ?
            ", [$description, $id]);
            return true;
        } catch (PDOException $e) {
            throw new Exception("Error updating task description: " . $e->getMessage());
        }
    }
    
    public function updateTaskStatus($id, $status) {
        $validStatuses = ['Pending', 'In Progress', 'Completed'];
        
        if (!in_array($status, $validStatuses)) {
            throw new Exception("Invalid status");
        }
        
        try {
            $this->db->safeQuery("UPDATE tasks SET status = ? WHERE id = ?", [$status, $id]);
            return true;
        } catch (PDOException $e) {
            throw new Exception("Error updating task status: " . $e->getMessage());
        }
    }
    
    public function deleteTask($id) {
        try {
            $this->db->safeQuery("DELETE FROM tasks WHERE id = ?", [$id]);
            return true;
        } catch (PDOException $e) {
            throw new Exception("Error deleting task: " . $e->getMessage());
        }
    }
    
    public function getTaskStats() {
        $stats = $this->db->safeFetch("
            SELECT 
                COUNT(*) as total_tasks,
                SUM(CASE WHEN status = 'Pending' THEN 1 ELSE 0 END) as pending_tasks,
                SUM(CASE WHEN status = 'In Progress' THEN 1 ELSE 0 END) as in_progress_tasks,
                SUM(CASE WHEN status = 'Completed' THEN 1 ELSE 0 END) as completed_tasks
            FROM tasks
        ");
        
        return $stats;
    }

    public function getTasksDueSoon($hours = 24) {
        $now = date('Y-m-d');
        $future = date('Y-m-d', strtotime("+$hours hours"));
        return $this->db->safeFetchAll("
            SELECT t.*, u1.email as assigned_to_email, u1.username as assigned_to_name, u2.username as assigned_by_name
            FROM tasks t
            LEFT JOIN users u1 ON t.assigned_to = u1.id
            LEFT JOIN users u2 ON t.assigned_by = u2.id
            WHERE t.status != 'Completed'
              AND t.reminder_sent = 0
              AND t.deadline IS NOT NULL
              AND t.deadline > ?
              AND t.deadline <= ?
        ", [$now, $future]);
    }
    
    public function markReminderSent($taskId) {
        $this->db->safeQuery("UPDATE tasks SET reminder_sent = 1 WHERE id = ?", [$taskId]);
    }
    
    private function sendTaskNotification($taskId) {
        // Get task details
        $task = $this->getTaskById($taskId);
        
        if ($task) {
            // Get assigned user email
            $user = $this->db->safeFetch("SELECT email FROM users WHERE id = ?", [$task['assigned_to']]);
            
            if ($user) {
                $emailService = new EmailService();
                $emailService->sendTaskAssignmentEmail($user['email'], $task);
            }
        }
    }
}