-- Migration 014: Add YouTube Playlist Support
-- This migration adds playlist support for organizing videos by role and audition type

-- Add playlist ID column to videos table
ALTER TABLE videos 
ADD COLUMN youtube_playlist_id VARCHAR(255) DEFAULT NULL AFTER youtube_id,
ADD INDEX idx_playlist (youtube_playlist_id);

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

-- Add settings for playlist management
INSERT INTO settings (setting_key, setting_value, category, description) 
VALUES 
    ('youtube_playlist_enabled', '1', 'youtube', 'Enable automatic playlist organization'),
    ('youtube_playlist_per_season', '0', 'youtube', 'Create separate playlists for each season (0 = single playlist per role/type)')
ON DUPLICATE KEY UPDATE setting_value = setting_value;
