<?php

use Dotenv\Dotenv;

class Config {
    private static $instance = null;
    private $env;
    
    private function __construct() {
        // Load .env file from the project root (2 levels up from api/config/)
        $dotenv = Dotenv::createImmutable(__DIR__ . '/../../');
        $dotenv->load();
        
        $this->env = $_ENV;
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function get($key, $default = null) {
        return $this->env[$key] ?? $default;
    }
    
    public function getDatabaseConfig() {
        return [
            // For Azure, use the full server name as host, e.g. taskmanagement.mysql.database.azure.com
            'host' => $this->get('DB_HOST', 'taskmanagement.mysql.database.azure.com'),
            // For Azure, username is just the admin name (not admin@server), e.g. Pleasant
            'username' => $this->get('DB_USERNAME', 'Pleasant'),
            'password' => $this->get('DB_PASSWORD', 'Adika123'),
            'database' => $this->get('DB_NAME', 'task_management')
        ];
    }
    
    public function getEmailConfig() {
        return [
            'host' => $this->get('SMTP_HOST', 'smtp.gmail.com'),
            'port' => $this->get('SMTP_PORT', 587),
            'username' => $this->get('SMTP_USERNAME', ''),
            'password' => $this->get('SMTP_PASSWORD', ''),
            'encryption' => $this->get('SMTP_ENCRYPTION', 'tls'),
            'from_email' => $this->get('FROM_EMAIL', 'noreply@taskmanagement.com'),
            'from_name' => $this->get('FROM_NAME', 'Task Management System')
        ];
    }
    
    public function getAppConfig() {
        return [
            'env' => $this->get('APP_ENV', 'development'),
            'debug' => $this->get('APP_DEBUG', 'true') === 'true',
            'url' => $this->get('APP_URL', 'http://localhost:8000'),
            'session_secret' => $this->get('SESSION_SECRET', 'default-secret-key'),
            'jwt_secret' => $this->get('JWT_SECRET', 'default-jwt-secret')
        ];
    }
} 