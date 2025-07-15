<?php
require_once 'Database.php';

class User {
    private $db;
    
    public function __construct() {
        $this->db = new Database();
    }
    
    public function login($username, $password) {
        $user = $this->db->safeFetch(
            "SELECT * FROM users WHERE username = ? OR email = ?",
            [$username, $username]
        );
        
        if ($user && password_verify($password, $user['password'])) {
            if (session_status() !== PHP_SESSION_ACTIVE) {
                session_start();
            }
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['email'] = $user['email'];
            return true;
        }
        
        return false;
    }
    
    public function logout() {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        session_destroy();
    }
    
    public function isLoggedIn() {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        return isset($_SESSION['user_id']);
    }
    
    public function isAdmin() {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
    }
    
    public function getCurrentUser() {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        if ($this->isLoggedIn()) {
            return [
                'id' => $_SESSION['user_id'],
                'username' => $_SESSION['username'],
                'role' => $_SESSION['role'],
                'email' => $_SESSION['email']
            ];
        }
        return null;
    }
    
    public function getAllUsers() {
        return $this->db->safeFetchAll("SELECT id, username, email, role, created_at FROM users");
    }
    
    public function getUserById($id) {
        return $this->db->safeFetch("SELECT id, username, email, role FROM users WHERE id = ?", [$id]);
    }
    
    public function createUser($username, $email, $password, $role = 'user') {
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        
        try {
            $this->db->safeQuery(
                "INSERT INTO users (username, email, password, role) VALUES (?, ?, ?, ?)",
                [$username, $email, $hashedPassword, $role]
            );
            return $this->db->lastInsertId();
        } catch (PDOException $e) {
            throw new Exception("Error creating user: " . $e->getMessage());
        }
    }
    
    public function updateUser($id, $username, $email, $role) {
        try {
            $this->db->safeQuery(
                "UPDATE users SET username = ?, email = ?, role = ? WHERE id = ?",
                [$username, $email, $role, $id]
            );
            return true;
        } catch (PDOException $e) {
            throw new Exception("Error updating user: " . $e->getMessage());
        }
    }

    public function updateUserWithPassword($id, $username, $email, $role, $password) {
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        try {
            $this->db->safeQuery(
                "UPDATE users SET username = ?, email = ?, role = ?, password = ? WHERE id = ?",
                [$username, $email, $role, $hashedPassword, $id]
            );
            return true;
        } catch (PDOException $e) {
            throw new Exception("Error updating user with password: " . $e->getMessage());
        }
    }
    
    public function deleteUser($id) {
        try {
            $this->db->safeQuery("DELETE FROM users WHERE id = ?", [$id]);
            return true;
        } catch (PDOException $e) {
            throw new Exception("Error deleting user: " . $e->getMessage());
        }
    }
}