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
            // No session logic, just return user data
            return $user;
        }
        return false;
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
    // JWT helper
    public static function getUserFromJWT($jwt, $secret) {
        if (!$jwt) return null;
        $parts = explode('.', $jwt);
        if (count($parts) !== 3) return null;
        $payload = json_decode(base64_decode($parts[1]), true);
        $sig = hash_hmac('sha256', $parts[0] . '.' . $parts[1], $secret, true);
        if (base64_encode($sig) !== strtr($parts[2], '-_', '+/')) return null;
        return $payload;
    }
    public static function isAdminJWT($jwt, $secret) {
        $user = self::getUserFromJWT($jwt, $secret);
        return $user && isset($user['role']) && $user['role'] === 'admin';
    }
}