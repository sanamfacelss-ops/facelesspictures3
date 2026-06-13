-- Migration: Add creator workflow fields
-- Adds support for script-based and text-based recording modes,
-- AI moderation status tracking, and manual approval flags

-- Add new columns to videos table
ALTER TABLE videos 
ADD COLUMN recording_mode ENUM('script', 'freeform') DEFAULT 'freeform' AFTER content_type,
ADD COLUMN script_content TEXT NULL AFTER recording_mode,
ADD COLUMN ai_status ENUM('pending', 'processing', 'approved', 'flagged', 'rejected') DEFAULT 'pending' AFTER status,
ADD COLUMN ai_score DECIMAL(5,2) NULL COMMENT 'AI quality score 0-100' AFTER ai_status,
ADD COLUMN ai_feedback JSON NULL COMMENT 'Detailed AI analysis feedback' AFTER ai_score,
ADD COLUMN needs_manual_review TINYINT(1) DEFAULT 0 COMMENT 'Flag for admin manual review' AFTER ai_feedback,
ADD COLUMN youtube_status ENUM('pending', 'uploading', 'published', 'failed') DEFAULT 'pending' AFTER youtube_id,
ADD COLUMN video_duration INT NULL COMMENT 'Duration in seconds' AFTER file_path,
ADD COLUMN thumbnail_path VARCHAR(500) NULL AFTER video_duration,
ADD INDEX idx_ai_status (ai_status),
ADD INDEX idx_needs_review (needs_manual_review),
ADD INDEX idx_youtube_status (youtube_status);

-- Add scripts table for pre-written scripts that creators can use
CREATE TABLE IF NOT EXISTS scripts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    content TEXT NOT NULL,
    category ENUM('actor', 'director', 'writer') NOT NULL,
    difficulty ENUM('beginner', 'intermediate', 'advanced') DEFAULT 'beginner',
    duration_hint VARCHAR(50) COMMENT 'Suggested duration like "30-60 seconds"',
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_category (category),
    INDEX idx_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert some sample scripts
INSERT INTO scripts (title, content, category, difficulty, duration_hint) VALUES
('The Confession', 'You''ve been keeping a secret for years. Today, you finally have to tell your best friend the truth about what happened that night. Start calm, but let the emotion build as you reveal more details. End with asking for forgiveness.', 'actor', 'intermediate', '60-90 seconds'),
('Job Interview Gone Wrong', 'You''re in the most important job interview of your life. Everything was going well until they asked about your biggest failure. Show the struggle between being honest and wanting to impress.', 'actor', 'beginner', '45-60 seconds'),
('The Breakup Call', 'You''re on a video call, breaking up with someone you still care about. They can''t understand why. Show the internal conflict - you know this is right, but it still hurts.', 'actor', 'advanced', '90-120 seconds'),
('Pitch Your Vision', 'You''re presenting your dream film project to potential investors. You have 60 seconds to make them believe in your vision. Be passionate, specific, and compelling.', 'director', 'intermediate', '60 seconds'),
('Behind the Scene', 'Take us through how you would direct a pivotal emotional scene. Explain your vision for lighting, camera angles, and actor direction.', 'director', 'advanced', '90-120 seconds'),
('Story Pitch', 'Pitch your original story idea in under 2 minutes. Hook us with the premise, introduce the protagonist, and tease the central conflict.', 'writer', 'beginner', '90-120 seconds'),
('Character Monologue', 'Perform a monologue you''ve written. Show us your character''s voice, their desires, and their fears through their own words.', 'writer', 'intermediate', '60-90 seconds');

-- Update moderation_logs to track AI processing steps
ALTER TABLE moderation_logs
ADD COLUMN ai_processing_time_ms INT NULL AFTER reason,
ADD COLUMN ai_model_version VARCHAR(50) NULL AFTER ai_processing_time_ms;
