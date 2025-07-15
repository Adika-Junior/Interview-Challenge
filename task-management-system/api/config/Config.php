<?php

use Dotenv\Dotenv;

class Config {
    private static $instance = null;
    private $env;
    
    private function __construct() {
        // Load .env file from the project root (2 levels up from api/config/) if it exists
        $dotenvPath = __DIR__ . '/../../';
        if (file_exists($dotenvPath . '.env')) {
            $dotenv = Dotenv\Dotenv::createImmutable($dotenvPath);
            $dotenv->load();
        }
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
            'host' => $this->get('DB_HOST', 'localhost'),
            'username' => $this->get('DB_USERNAME', 'root'),
            'password' => $this->get('DB_PASSWORD', ''),
            'database' => $this->get('DB_NAME', 'task_management')
        ];
    }
    
    public function getEmailConfig() {
        return [
            'host' => 'smtp.gmail.com',
            'port' => 587,
            'username' => 'pleasantview076@gmail.com',
            'password' => 'fxud nkim kvnk raqa',
            'encryption' => 'tls',
            'from_email' => 'pleasantview076@gmail.com',
            'from_name' => 'Task Management System'
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