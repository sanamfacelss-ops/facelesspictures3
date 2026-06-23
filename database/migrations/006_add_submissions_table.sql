-- Migration 006: Add public submissions table (no-login guest uploads)
-- Actors, Directors, Writers submit with contact details, no account required

CREATE TABLE IF NOT EXISTS submissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    role ENUM('actor','director','writer') NOT NULL,
    audition_type VARCHAR(100) NOT NULL COMMENT 'e.g. Dialog Audition, Song Audition, Script Submission',
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL,
    phone VARCHAR(30) NOT NULL,
    script_id INT NULL COMMENT 'Which script/prompt they used',
    script_title VARCHAR(255) NULL,
    notes TEXT NULL COMMENT 'Applicant notes or brief message',
    file_path VARCHAR(500) NULL COMMENT 'Uploaded video filename',
    file_type VARCHAR(50) NULL,
    file_size_bytes BIGINT NULL,
    video_id INT NULL COMMENT 'FK to videos table — feeds AI/YouTube pipeline',
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add landing page settings keys
INSERT INTO settings (setting_key, setting_value, setting_type, description) VALUES
('landing_poster_url', '', 'text', 'Movie poster image URL for landing page hero'),
('landing_trailer_url', '', 'text', 'Trailer video URL (YouTube or direct MP4) for landing page'),
('landing_hero_title', 'NO FACE.\nNO CONNECTIONS.\nJUST TALENT.', 'text', 'Landing page hero headline'),
('landing_hero_subtitle', 'India''s first anonymous film competition. Actors, Directors & Writers compete purely on talent.', 'text', 'Landing page hero subtitle'),
('landing_about_text', 'Faceless Pictures is India''s first anonymous film competition where talent speaks without a face. We believe the best stories deserve to be told — regardless of who you know or what you look like.', 'text', 'About section text on landing page'),
('actor_dialog_script', 'Perform the following scene with full emotion. The scene: You receive a call that changes everything. Your character must convey shock, then resolve — all in under 90 seconds.', 'text', 'Default dialog audition script for actors'),
('actor_song_script', 'Choose any song that represents a character going through a transformation. Perform a 60-second version showing emotional range — no instruments needed, just your voice.', 'text', 'Default song audition script for actors'),
('director_brief', 'You have one actor, one phone camera, and a single location. Pitch and shoot a 60-second scene that tells a complete emotional story. Include your framing choices and lighting decisions in your submission notes.', 'text', 'Director audition brief'),
('writer_brief', 'Scene 1 ends with the line: "I never thought you''d come back." Write Scene 2 — between 1 and 3 pages. Format it as a proper screenplay. You may submit as a PDF or perform a reading on video.', 'text', 'Writer audition brief')
ON DUPLICATE KEY UPDATE setting_key = setting_key;
