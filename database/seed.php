<?php
/**
 * Database Seeder - Run once to create admin account
 * Usage: php database/seed.php
 */

require_once __DIR__ . '/../app/config/config.php';

use App\Config\Database;

echo "=== Faceless Pitcher 3 - Database Seeder ===\n\n";

try {
    $db = Database::getConnection();
    echo "✓ Database connected\n";
    
    // Admin credentials
    $adminEmail = 'sanamfacelss@gmail.com';
    $adminPassword = 'FP3@dmin2024!';  // Strong password
    $adminName = 'Sanam Admin';
    
    // Check if admin exists
    $stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$adminEmail]);
    
    if ($stmt->fetch()) {
        echo "! Admin account already exists\n";
        
        // Update to ensure is_admin is set
        $stmt = $db->prepare("UPDATE users SET is_admin = 1 WHERE email = ?");
        $stmt->execute([$adminEmail]);
        echo "✓ Admin privileges confirmed\n";
    } else {
        // Create admin account
        $hashedPassword = password_hash($adminPassword, PASSWORD_BCRYPT);
        $stmt = $db->prepare(
            "INSERT INTO users (name, email, password, role, is_admin, created_at) VALUES (?, ?, ?, 'admin', 1, NOW())"
        );
        $stmt->execute([$adminName, $adminEmail, $hashedPassword]);
        echo "✓ Admin account created\n";
    }
    
    echo "\n=== Admin Credentials ===\n";
    echo "Email: $adminEmail\n";
    echo "Password: $adminPassword\n";
    echo "\n⚠️  IMPORTANT: Change this password after first login!\n";
    echo "=========================\n";
    
} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
    exit(1);
}
