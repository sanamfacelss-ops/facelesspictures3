-- Migration: Add content_categories to users table and content_type to videos table
-- This allows users to select multiple content types they want to create

-- Add content_categories column to store JSON array of selected categories
ALTER TABLE users ADD COLUMN content_categories JSON DEFAULT NULL AFTER role;

-- Update existing users to have their current role as their content category
UPDATE users SET content_categories = JSON_ARRAY(role) WHERE content_categories IS NULL;

-- Add content_type column to videos table to track what type of content each video is
ALTER TABLE videos ADD COLUMN content_type ENUM('actor','director','writer') DEFAULT NULL AFTER title;

-- Update existing videos to use the user's role as content_type
UPDATE videos v 
JOIN users u ON v.user_id = u.id 
SET v.content_type = u.role 
WHERE v.content_type IS NULL;

-- Update the unique constraint to be per user, season, AND content_type
-- First drop the old constraint
ALTER TABLE videos DROP INDEX unique_user_season;

-- Add new constraint allowing one video per user per season per content type
ALTER TABLE videos ADD UNIQUE KEY unique_user_season_type (user_id, season_id, content_type);

-- Add index on content_type for filtering
ALTER TABLE videos ADD INDEX idx_content_type (content_type);

-- Note: The role column is kept for backward compatibility
-- content_categories stores: ["actor"], ["director"], ["writer"] or combinations like ["actor", "writer"]
