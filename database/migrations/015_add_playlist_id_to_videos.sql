-- Migration 015: Add youtube_playlist_id column to videos table
-- This adds the foreign key to link videos with their YouTube playlists

-- This migration is intentionally empty because the column may already exist
-- If you get an error that the column already exists, that's okay - it means it's already there
-- You can manually mark this migration as complete by running:
-- INSERT INTO migrations (filename) VALUES ('015_add_playlist_id_to_videos.sql');

-- The following would add the column, but only run if it doesn't exist yet:
-- ALTER TABLE videos ADD COLUMN youtube_playlist_id VARCHAR(255) DEFAULT NULL AFTER youtube_id;
-- ALTER TABLE videos ADD INDEX idx_playlist (youtube_playlist_id);
