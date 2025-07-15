<?php

class SecurityMiddleware {
    
    /**
     * Apply security headers
     */
    public static function applySecurityHeaders() {
        // Prevent XSS attacks
        header('X-XSS-Protection: 1; mode=block');
        
        // Prevent MIME type sniffing
        header('X-Content-Type-Options: nosniff');
        
        // Prevent clickjacking
        header('X-Frame-Options: DENY');
        
        // Strict transport security (HTTPS only)
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
        
        // Content Security Policy
        header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; font-src 'self'; connect-src 'self';");
        
        // Referrer Policy
        header('Referrer-Policy: strict-origin-when-cross-origin');
        
        // Permissions Policy
        header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
    }
    
    /**
     * Validate and sanitize input data
     */
    public static function sanitizeInput($data) {
        if (is_array($data)) {
            return array_map([self::class, 'sanitizeInput'], $data);
        }
        
        if (is_string($data)) {
            // Remove null bytes
            $data = str_replace(chr(0), '', $data);
            
            // Trim whitespace
            $data = trim($data);
            
            // Convert special characters to HTML entities
            $data = htmlspecialchars($data, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            
            // Remove any potential script tags
            $data = preg_replace('/<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>/mi', '', $data);
            
            // Remove any potential SQL injection patterns
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
                if (preg_match($pattern, $data)) {
                    throw new Exception('Potentially malicious input detected');
                }
            }
        }
        
        return $data;
    }
    
    /**
     * Validate CSRF token
     */
    public static function validateCSRFToken($token) {
        if (!isset($_SESSION['csrf_token'])) {
            return false;
        }
        
        return hash_equals($_SESSION['csrf_token'], $token);
    }
    
    /**
     * Generate CSRF token
     */
    public static function generateCSRFToken() {
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        
        return $_SESSION['csrf_token'];
    }
    
    /**
     * Rate limiting check
     */
    public static function checkRateLimit($identifier, $maxRequests = 100, $timeWindow = 3600) {
        $cacheFile = sys_get_temp_dir() . '/rate_limit_' . md5($identifier) . '.txt';
        
        if (file_exists($cacheFile)) {
            $data = json_decode(file_get_contents($cacheFile), true);
            
            if ($data['timestamp'] > time() - $timeWindow) {
                if ($data['count'] >= $maxRequests) {
                    return false; // Rate limit exceeded
                }
                $data['count']++;
            } else {
                $data = ['timestamp' => time(), 'count' => 1];
            }
        } else {
            $data = ['timestamp' => time(), 'count' => 1];
        }
        
        file_put_contents($cacheFile, json_encode($data));
        return true;
    }
    
    /**
     * Validate file upload
     */
    public static function validateFileUpload($file, $allowedTypes = ['jpg', 'jpeg', 'png', 'gif'], $maxSize = 5242880) {
        if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            throw new Exception('Invalid file upload');
        }
        
        // Check file size
        if ($file['size'] > $maxSize) {
            throw new Exception('File too large');
        }
        
        // Check file type
        $fileInfo = pathinfo($file['name']);
        $extension = strtolower($fileInfo['extension']);
        
        if (!in_array($extension, $allowedTypes)) {
            throw new Exception('File type not allowed');
        }
        
        // Check MIME type
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        
        $allowedMimes = [
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif'
        ];
        
        if (!isset($allowedMimes[$extension]) || $allowedMimes[$extension] !== $mimeType) {
            throw new Exception('Invalid MIME type');
        }
        
        return true;
    }
    
    /**
     * Log security events
     */
    public static function logSecurityEvent($event, $details = []) {
        $logEntry = [
            'timestamp' => date('Y-m-d H:i:s'),
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            'event' => $event,
            'details' => $details
        ];
        
        $logFile = __DIR__ . '/../../logs/security.log';
        $logDir = dirname($logFile);
        
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        
        file_put_contents($logFile, json_encode($logEntry) . PHP_EOL, FILE_APPEND | LOCK_EX);
    }
    
    /**
     * Validate session
     */
    public static function validateSession() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        // Regenerate session ID periodically
        if (!isset($_SESSION['last_regeneration'])) {
            $_SESSION['last_regeneration'] = time();
        } elseif (time() - $_SESSION['last_regeneration'] > 300) { // 5 minutes
            session_regenerate_id(true);
            $_SESSION['last_regeneration'] = time();
        }
        
        // Check session timeout
        if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > 1800)) { // 30 minutes
            session_unset();
            session_destroy();
            return false;
        }
        
        $_SESSION['last_activity'] = time();
        return true;
    }
} 