-- Migration 015: Add youtube_playlist_id column to videos table
-- This adds the foreign key to link videos with their YouTube playlists

-- Add playlist ID column to videos table
ALTER TABLE videos 
ADD COLUMN youtube_playlist_id VARCHAR(255) DEFAULT NULL AFTER youtube_id;

-- Add index for better query performance
ALTER TABLE videos 
ADD INDEX idx_playlist (youtube_playlist_id);
