-- Migration: Add password resets table
-- Run this on existing databases

CREATE TABLE IF NOT EXISTS password_resets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL,
    otp VARCHAR(6) NOT NULL,
    expires_at TIMESTAMP NOT NULL,
    used TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_email (email),
    INDEX idx_otp (otp),
    INDEX idx_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Update admin user role to allow 'admin' value
ALTER TABLE users MODIFY COLUMN role ENUM('actor','director','writer','admin') NOT NULL;

-- Insert/Update admin account
-- Password: FP3@dmin2024!
INSERT INTO users (name, email, password, role, is_admin) 
VALUES ('Sanam Admin', 'sanamfacelss@gmail.com', '$2y$10$YK8rK5QbVqH5ZcVtNqWxPuJgKmN4VhZsGEoQK3U9H5LxJ0mYwPx6e', 'admin', 1)
ON DUPLICATE KEY UPDATE is_admin = 1, role = 'admin';
