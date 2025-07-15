<?php
require_once __DIR__ . '/../config/Config.php';

class Database {
    private $host;
    private $username;
    private $password;
    private $database;
    private $connection;
    private $config;
    
    public function __construct() {
        $this->config = Config::getInstance();
        $dbConfig = $this->config->getDatabaseConfig();
        
        $this->host = $dbConfig['host'];
        $this->username = $dbConfig['username'];
        $this->password = $dbConfig['password'];
        $this->database = $dbConfig['database'];
    }
    
    public function connect() {
        try {
            $this->connection = new PDO(
                "mysql:host={$this->host};dbname={$this->database};charset=utf8mb4",
                $this->username,
                $this->password,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
                ]
            );
            return $this->connection;
        } catch (PDOException $e) {
            // Log the error with context but don't expose sensitive details
            $this->logDatabaseError('Database connection failed', [
                'host' => $this->host,
                'database' => $this->database,
                'error_code' => $e->getCode(),
                'error_message' => $e->getMessage()
            ]);
            
            // Return a generic error response
            header('Content-Type: application/json; charset=utf-8');
            http_response_code(500);
            echo json_encode([
                'error' => 'Database connection failed. Please try again later.',
                'details' => 'Service temporarily unavailable'
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }
    }
    
    public function getConnection() {
        if (!$this->connection) {
            $this->connect();
        }
        return $this->connection;
    }
    
    public function query($sql, $params = []) {
        $stmt = $this->getConnection()->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }
    
    public function fetch($sql, $params = []) {
        return $this->query($sql, $params)->fetch();
    }
    
    public function fetchAll($sql, $params = []) {
        return $this->query($sql, $params)->fetchAll();
    }
    
    public function lastInsertId() {
        return $this->getConnection()->lastInsertId();
    }
    
    /**
     * Enhanced SQL injection protection - validates and sanitizes input
     */
    public function sanitizeInput($input) {
        if (is_array($input)) {
            return array_map([$this, 'sanitizeInput'], $input);
        }
        
        if (is_string($input)) {
            // Remove any potential SQL injection patterns
            $input = trim($input);
            $input = stripslashes($input);
            $input = htmlspecialchars($input, ENT_QUOTES, 'UTF-8');
            
            // Additional SQL injection pattern detection
            $sqlPatterns = [
                '/\b(union|select|insert|update|delete|drop|create|alter|exec|execute|script)\b/i',
                '/[\'";]/',
                '/--/',
                '/\/\*.*\*\//',
                '/xp_cmdshell/',
                '/sp_/',
                '/@@/'
            ];
            
            foreach ($sqlPatterns as $pattern) {
                if (preg_match($pattern, $input)) {
                    throw new Exception('Potentially malicious input detected');
                }
            }
        }
        
        return $input;
    }
    
    /**
     * Safe query execution with enhanced validation
     */
    public function safeQuery($sql, $params = []) {
        // Validate SQL statement
        $this->validateSQL($sql);
        
        // Sanitize parameters
        $sanitizedParams = $this->sanitizeInput($params);
        
        // Execute prepared statement
        return $this->query($sql, $sanitizedParams);
    }
    
    /**
     * Validate SQL statement for potential injection
     */
    private function validateSQL($sql) {
        $sql = strtolower(trim($sql));
        
        // Check for multiple statements (potential injection)
        if (strpos($sql, ';') !== false && strpos($sql, ';') !== strlen($sql) - 1) {
            throw new Exception('Multiple SQL statements not allowed');
        }
        
        // Check for dangerous SQL operations in certain contexts
        $dangerousOperations = ['drop', 'delete', 'truncate', 'alter', 'create'];
        foreach ($dangerousOperations as $operation) {
            if (strpos($sql, $operation) !== false) {
                // Log suspicious activity
                error_log("Suspicious SQL operation detected: $operation in query: $sql");
            }
        }
    }
    
    /**
     * Safe fetch with enhanced protection
     */
    public function safeFetch($sql, $params = []) {
        return $this->safeQuery($sql, $params)->fetch();
    }
    
    /**
     * Safe fetchAll with enhanced protection
     */
    public function safeFetchAll($sql, $params = []) {
        return $this->safeQuery($sql, $params)->fetchAll();
    }
    
    /**
     * Begin transaction with error handling
     */
    public function beginTransaction() {
        return $this->getConnection()->beginTransaction();
    }
    
    /**
     * Commit transaction
     */
    public function commit() {
        return $this->getConnection()->commit();
    }
    
    /**
     * Rollback transaction
     */
    public function rollback() {
        return $this->getConnection()->rollback();
    }
    
    /**
     * Check if connection is active
     */
    public function isConnected() {
        try {
            $this->getConnection()->query('SELECT 1');
            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Log database errors to a separate log file
     */
    private function logDatabaseError($message, $context = []) {
        $logEntry = [
            'timestamp' => date('Y-m-d H:i:s'),
            'level' => 'DATABASE_ERROR',
            'message' => $message,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'request_uri' => $_SERVER['REQUEST_URI'] ?? 'unknown',
            'context' => $context
        ];
        
        $logFile = __DIR__ . '/../../logs/database.log';
        $logDir = dirname($logFile);
        
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        
        file_put_contents($logFile, json_encode($logEntry) . PHP_EOL, FILE_APPEND | LOCK_EX);
    }
}