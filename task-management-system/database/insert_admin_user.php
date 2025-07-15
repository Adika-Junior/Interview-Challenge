<?php
/**
 * Insert Admin User Script
 * 
 * This script reads the .env file and inserts the admin user with credentials
 * from the environment variables. Run this after setting up your database.
 */

// Load Composer autoloader
require_once __DIR__ . '/../vendor/autoload.php';

// Load environment variables
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

// Database configuration from .env
// For Azure, use the full server name as host, e.g. taskmanagement.mysql.database.azure.com
// For Azure, username is just the admin name, e.g. Pleasant
$dbHost = $_ENV['DB_HOST'] ?? 'taskmanagement.mysql.database.azure.com';
$dbUsername = $_ENV['DB_USERNAME'] ?? 'Pleasant';
$dbPassword = $_ENV['DB_PASSWORD'] ?? 'Adika123';
$dbName = $_ENV['DB_NAME'] ?? 'task_management';

// Admin user configuration from .env
$adminUsername = $_ENV['ADMIN_USERNAME'] ?? 'admin';
$adminEmail = $_ENV['ADMIN_EMAIL'] ?? 'admin@example.com';
$adminPasswordHash = $_ENV['ADMIN_PASSWORD_HASH'] ?? '';

if (empty($adminPasswordHash)) {
    echo "Error: ADMIN_PASSWORD_HASH not found in .env file\n";
    echo "Please generate a password hash and add it to your .env file\n";
    exit(1);
}

try {
    // Connect to database
    $pdo = new PDO(
        "mysql:host={$dbHost};dbname={$dbName};charset=utf8mb4;port=3306",
        $dbUsername,
        $dbPassword,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4",
            PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT => false,
            PDO::MYSQL_ATTR_SSL_CA => null // Set to CA cert path if you want full verification
        ]
    );
    
    echo "✓ Connected to database successfully\n";
    
    // Check if admin user already exists
    $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
    $stmt->execute([$adminUsername, $adminEmail]);
    $existingUser = $stmt->fetch();
    
    if ($existingUser) {
        echo "⚠️  Admin user already exists (ID: {$existingUser['id']})\n";
        echo "Updating admin user credentials...\n";
        
        // Update existing admin user
        $stmt = $pdo->prepare("
            UPDATE users 
            SET username = ?, email = ?, password = ?, role = 'admin', updated_at = CURRENT_TIMESTAMP 
            WHERE id = ?
        ");
        $stmt->execute([$adminUsername, $adminEmail, $adminPasswordHash, $existingUser['id']]);
        
        echo "✓ Admin user updated successfully\n";
    } else {
        echo "Creating new admin user...\n";
        
        // Insert new admin user
        $stmt = $pdo->prepare("
            INSERT INTO users (username, email, password, role) 
            VALUES (?, ?, ?, 'admin')
        ");
        $stmt->execute([$adminUsername, $adminEmail, $adminPasswordHash]);
        
        $adminId = $pdo->lastInsertId();
        echo "✓ Admin user created successfully (ID: {$adminId})\n";
    }
    
    // Verify the admin user
    $stmt = $pdo->prepare("SELECT id, username, email, role, created_at FROM users WHERE username = ?");
    $stmt->execute([$adminUsername]);
    $adminUser = $stmt->fetch();
    
    if ($adminUser) {
        echo "\n=== Admin User Details ===\n";
        echo "ID: {$adminUser['id']}\n";
        echo "Username: {$adminUser['username']}\n";
        echo "Email: {$adminUser['email']}\n";
        echo "Role: {$adminUser['role']}\n";
        echo "Created: {$adminUser['created_at']}\n";
        echo "========================\n\n";
        
        echo "✓ Admin user is ready for login!\n";
        echo "Login credentials:\n";
        echo "  Username: {$adminUsername}\n";
        echo "  Email: {$adminEmail}\n";
        echo "  Password: (check your .env file for ADMIN_PASSWORD)\n";
    } else {
        echo "✗ Error: Could not verify admin user creation\n";
        exit(1);
    }
    
} catch (PDOException $e) {
    echo "✗ Database Error: " . $e->getMessage() . "\n";
    exit(1);
} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\n✓ Admin user setup completed successfully!\n"; 