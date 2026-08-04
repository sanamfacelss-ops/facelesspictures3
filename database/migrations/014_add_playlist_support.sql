-- Migration 014: Add YouTube Playlist Support
-- This migration adds playlist support for organizing videos by role and audition type

-- Add playlists table to track created playlists
CREATE TABLE IF NOT EXISTS youtube_playlists (
    id INT AUTO_INCREMENT PRIMARY KEY,
    playlist_id VARCHAR(255) NOT NULL UNIQUE,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    role ENUM('actor', 'director', 'writer') NOT NULL,
    audition_type VARCHAR(100) DEFAULT NULL COMMENT 'For actors: audition, song_audition, etc.',
    season_id INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (season_id) REFERENCES seasons(id) ON DELETE SET NULL,
    INDEX idx_role (role),
    INDEX idx_audition_type (audition_type),
    INDEX idx_season (season_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add settings for playlist management (using existing settings table structure)
INSERT INTO settings (setting_key, setting_value, description) 
VALUES 
    ('youtube_playlist_enabled', '1', 'Enable automatic playlist organization'),
    ('youtube_playlist_per_season', '0', 'Create separate playlists for each season')
ON DUPLICATE KEY UPDATE setting_value = setting_value;
