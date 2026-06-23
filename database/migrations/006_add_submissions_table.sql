-- Migration 006: Add public submissions table (no-login guest uploads)
-- Run with: php database/run-migrations.php

CREATE TABLE IF NOT EXISTS submissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    role ENUM('actor','director','writer') NOT NULL,
    audition_type VARCHAR(100) NOT NULL,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL,
    phone VARCHAR(30) NOT NULL,
    script_id INT NULL,
    script_title VARCHAR(255) NULL,
    notes TEXT NULL,
    file_path VARCHAR(500) NULL,
    file_type VARCHAR(50) NULL,
    file_size_bytes BIGINT NULL,
    video_id INT NULL,
    status ENUM('new','reviewed','shortlisted','rejected') DEFAULT 'new',
    admin_notes TEXT NULL,
    ip_address VARCHAR(45) NULL,
    submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    reviewed_at TIMESTAMP NULL,
    INDEX idx_role (role),
    INDEX idx_status (status),
    INDEX idx_email (email),
    INDEX idx_submitted (submitted_at),
    INDEX idx_video (video_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
