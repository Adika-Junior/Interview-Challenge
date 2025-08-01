<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// JWT secret (should match all endpoints)
$jwtSecret = 'fe91e46f769cd291653f48b7e95aa58150f2a4c0094801cdc4f954ca670d3d47';
function base64url_encode($data) {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}
function base64url_decode($data) {
    $remainder = strlen($data) % 4;
    if ($remainder) {
        $padlen = 4 - $remainder;
        $data .= str_repeat('=', $padlen);
    }
    return base64_decode(strtr($data, '-_', '+/'));
}
function createJWT($payload, $secret) {
    $header = base64url_encode(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
    $payload = base64url_encode(json_encode($payload));
    $sig = base64url_encode(hash_hmac('sha256', "$header.$payload", $secret, true));
    return "$header.$payload.$sig";
}
function getUserFromJWT($jwt, $secret) {
    if (!$jwt) return null;
    $parts = explode('.', $jwt);
    if (count($parts) !== 3) return null;
    $header = $parts[0];
    $payload = $parts[1];
    $sig = $parts[2];
    $expected_sig = base64url_encode(hash_hmac('sha256', "$header.$payload", $secret, true));
    if (!hash_equals($expected_sig, $sig)) return null;
    return json_decode(base64url_decode($payload), true);
}

// Global error handler for fatal errors
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        http_response_code(500);
        echo json_encode(['error' => 'Internal server error (shutdown): ' . $error['message']]);
        exit;
    }
});
set_exception_handler(function($e) {
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error (exception): ' . $e->getMessage()]);
    exit;
});
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    http_response_code(500);
    echo json_encode(['error' => "Internal server error (handler): $errstr in $errfile on line $errline"]);
    exit;
});

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit(0);

function sendResponse($data, $code = 200) {
    http_response_code($code);
    echo json_encode($data);
    exit;
}

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse(['error' => 'Method not allowed'], 405);
    }

        $input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data || !isset($data['username']) || !isset($data['password'])) {
    sendResponse(['error' => 'Username and password required'], 400);
}

// Azure MySQL SSL connection using mysqli with server cert verification disabled
$host = 'taskmanagement.mysql.database.azure.com';
$db   = 'task_management';
$user = 'Pleasant';
$pass = 'Adika123';

$con = mysqli_init();
mysqli_ssl_set($con, NULL, NULL, NULL, NULL, NULL); // No CA cert
mysqli_options($con, MYSQLI_OPT_SSL_VERIFY_SERVER_CERT, false); // Disable verification
if (!mysqli_real_connect($con, $host, $user, $pass, $db, 3306, NULL, MYSQLI_CLIENT_SSL)) {
    sendResponse(['error' => 'Database connection failed: ' . mysqli_connect_error()], 500);
}

// Inline user check
$stmt = $con->prepare("SELECT * FROM users WHERE username = ? OR email = ?");
if (!$stmt) {
    sendResponse(['error' => 'Prepare failed: ' . $con->error], 500);
}
$stmt->bind_param('ss', $data['username'], $data['username']);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

if ($user && password_verify($data['password'], $user['password'])) {
    $payload = [
        'id' => $user['id'],
        'username' => $user['username'],
        'email' => $user['email'],
        'role' => $user['role']
    ];
    $token = createJWT($payload, $jwtSecret);
    sendResponse(['success' => true, 'user' => $payload, 'token' => $token]);
} else {
    sendResponse(['error' => 'Invalid credentials'], 401);
}

// At the end of the file, add a catch-all error handler
if (!headers_sent()) {
    http_response_code(500);
    echo json_encode(['error' => 'Unknown server error.']);
    exit;
}
