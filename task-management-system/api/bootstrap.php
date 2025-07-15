<?php

// Load Composer autoloader
require_once __DIR__ . '/../vendor/autoload.php';

// Load configuration
require_once __DIR__ . '/config/Config.php';

// Load security middleware
require_once __DIR__ . '/middleware/SecurityMiddleware.php';

// Set error reporting based on environment
$config = Config::getInstance();
$appConfig = $config->getAppConfig();

if ($appConfig['debug']) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}

// Set timezone
date_default_timezone_set('UTC');

// Apply security headers
SecurityMiddleware::applySecurityHeaders();

// Set session parameters BEFORE session_start
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', 1);
ini_set('session.cookie_samesite', 'Strict');
ini_set('session.use_strict_mode', 1);
session_name('TASK_MANAGEMENT_SESSION');
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// Generate CSRF token if not exists
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = SecurityMiddleware::generateCSRFToken();
}

// Rate limiting check
$clientIP = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
if (!SecurityMiddleware::checkRateLimit($clientIP, 1000, 3600)) { // 1000 requests per hour
    http_response_code(429);
    echo json_encode(['error' => 'Rate limit exceeded. Please try again later.']);
    exit;
}

// Log security events for suspicious activities
if (isset($_SERVER['HTTP_USER_AGENT'])) {
    $suspiciousPatterns = [
        '/bot/i',
        '/crawler/i',
        '/spider/i',
        '/scraper/i',
        '/sqlmap/i',
        '/nikto/i',
        '/nmap/i'
    ];
    
    foreach ($suspiciousPatterns as $pattern) {
        if (preg_match($pattern, $_SERVER['HTTP_USER_AGENT'])) {
            SecurityMiddleware::logSecurityEvent('suspicious_user_agent', [
                'user_agent' => $_SERVER['HTTP_USER_AGENT']
            ]);
            break;
        }
    }
}

// Function to sanitize all input data
function sanitizeAllInput() {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $_POST = SecurityMiddleware::sanitizeInput($_POST);
    }
    
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $_GET = SecurityMiddleware::sanitizeInput($_GET);
    }
    
    // Sanitize JSON input
    $input = file_get_contents('php://input');
    if ($input) {
        $jsonData = json_decode($input, true);
        if ($jsonData !== null) {
            $sanitizedData = SecurityMiddleware::sanitizeInput($jsonData);
            // Store sanitized data for later use
            $GLOBALS['sanitized_json_input'] = $sanitizedData;
        }
    }
}

// Apply input sanitization
sanitizeAllInput();

// Function to get sanitized JSON input
function getSanitizedJsonInput() {
    return $GLOBALS['sanitized_json_input'] ?? null;
}

// Function to validate required fields
function validateRequiredFields($data, $requiredFields) {
    $missing = [];
    foreach ($requiredFields as $field) {
        if (!isset($data[$field]) || empty($data[$field])) {
            $missing[] = $field;
        }
    }
    
    if (!empty($missing)) {
        throw new Exception('Missing required fields: ' . implode(', ', $missing));
    }
    
    return true;
}

// Function to validate email format
function validateEmail($email) {
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new Exception('Invalid email format');
    }
    return true;
}

// Function to validate password strength
function validatePassword($password) {
    if (strlen($password) < 8) {
        throw new Exception('Password must be at least 8 characters long');
    }
    
    if (!preg_match('/[A-Z]/', $password)) {
        throw new Exception('Password must contain at least one uppercase letter');
    }
    
    if (!preg_match('/[a-z]/', $password)) {
        throw new Exception('Password must contain at least one lowercase letter');
    }
    
    if (!preg_match('/[0-9]/', $password)) {
        throw new Exception('Password must contain at least one number');
    }
    
    return true;
}

// Function to log application events
function logAppEvent($event, $details = []) {
    $logEntry = [
        'timestamp' => date('Y-m-d H:i:s'),
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'user_id' => $_SESSION['user_id'] ?? 'guest',
        'event' => $event,
        'details' => $details,
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
        'request_uri' => $_SERVER['REQUEST_URI'] ?? 'unknown',
        'request_method' => $_SERVER['REQUEST_METHOD'] ?? 'unknown'
    ];
    
    $logFile = __DIR__ . '/../logs/app.log';
    $logDir = dirname($logFile);
    
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }
    
    file_put_contents($logFile, json_encode($logEntry) . PHP_EOL, FILE_APPEND | LOCK_EX);
}

// Function to log errors with different levels
function logError($level, $message, $context = []) {
    $logEntry = [
        'timestamp' => date('Y-m-d H:i:s'),
        'level' => strtoupper($level),
        'message' => $message,
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'user_id' => $_SESSION['user_id'] ?? 'guest',
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
        'request_uri' => $_SERVER['REQUEST_URI'] ?? 'unknown',
        'request_method' => $_SERVER['REQUEST_METHOD'] ?? 'unknown',
        'context' => $context
    ];
    
    $logFile = __DIR__ . '/../logs/error.log';
    $logDir = dirname($logFile);
    
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }
    
    file_put_contents($logFile, json_encode($logEntry) . PHP_EOL, FILE_APPEND | LOCK_EX);
}

// Function to handle errors gracefully
function handleError($error, $context = []) {
    $appConfig = Config::getInstance()->getAppConfig();
    
    // Log the error
    logError('ERROR', $error->getMessage(), [
        'file' => $error->getFile(),
        'line' => $error->getLine(),
        'trace' => $error->getTraceAsString(),
        'context' => $context
    ]);
    
    if ($appConfig['debug']) {
        // In debug mode, show detailed error
        $response = [
            'error' => $error->getMessage(),
            'file' => $error->getFile(),
            'line' => $error->getLine(),
            'trace' => $error->getTraceAsString()
        ];
    } else {
        // In production, show generic error
        $response = [
            'error' => 'An error occurred. Please try again later.',
            'details' => 'Service temporarily unavailable'
        ];
    }
    
    return $response;
}

// Set error handler
set_error_handler(function($severity, $message, $file, $line) {
    if (!(error_reporting() & $severity)) {
        return;
    }
    
    $error = new ErrorException($message, 0, $severity, $file, $line);
    $response = handleError($error);
    
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode($response);
    exit;
});

// Set exception handler
set_exception_handler(function($error) {
    $response = handleError($error);
    
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode($response);
    exit;
});

// Log application startup
logAppEvent('application_started', [
    'version' => '1.0.0',
    'environment' => $appConfig['env']
]); 