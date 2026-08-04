-- Fix migration 014 by marking it as completed
-- Run this directly in your MySQL database or via phpMyAdmin

-- Mark migration 014 as completed (it will be skipped on next run)
INSERT INTO migrations (filename) VALUES ('014_add_playlist_support.sql')
ON DUPLICATE KEY UPDATE filename = filename;

-- Verify the youtube_playlists table was created
SELECT 'youtube_playlists table' AS item, 
       CASE WHEN COUNT(*) > 0 THEN 'EXISTS' ELSE 'MISSING' END AS status
FROM information_schema.tables 
WHERE table_schema = DATABASE() 
AND table_name = 'youtube_playlists';

-- Verify settings were added
SELECT setting_key, setting_value 
FROM settings 
WHERE setting_key IN ('youtube_playlist_enabled', 'youtube_playlist_per_season');
