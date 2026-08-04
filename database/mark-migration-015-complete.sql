-- Mark migration 015 as complete
-- The column already exists, so we just need to mark the migration as applied

INSERT INTO migrations (filename) VALUES ('015_add_playlist_id_to_videos.sql')
ON DUPLICATE KEY UPDATE filename = filename;

-- Verify the column exists
SELECT 
    COLUMN_NAME, 
    COLUMN_TYPE, 
    IS_NULLABLE,
    COLUMN_DEFAULT
FROM information_schema.COLUMNS 
WHERE TABLE_SCHEMA = DATABASE() 
  AND TABLE_NAME = 'videos' 
  AND COLUMN_NAME = 'youtube_playlist_id';

-- Verify the index exists
SELECT 
    INDEX_NAME,
    COLUMN_NAME,
    NON_UNIQUE
FROM information_schema.STATISTICS
WHERE TABLE_SCHEMA = DATABASE() 
  AND TABLE_NAME = 'videos' 
  AND INDEX_NAME = 'idx_playlist';

-- Check all migrations status
SELECT * FROM migrations ORDER BY id DESC LIMIT 5;
