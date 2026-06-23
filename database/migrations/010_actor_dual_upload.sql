-- Migration 010: Actor dual-video submission (dialog + song as one submission)
-- Run with: php database/run-migrations.php

ALTER TABLE submissions
    ADD COLUMN file_path_2 VARCHAR(500) NULL COMMENT 'Second video file (song audition)',
    ADD COLUMN file_type_2 VARCHAR(50) NULL,
    ADD COLUMN file_size_bytes_2 BIGINT NULL,
    ADD COLUMN video_id_2 INT NULL COMMENT 'Second video in pipeline (song)';

-- Settings for actor page preview videos and song tune
INSERT IGNORE INTO settings (setting_key, setting_value, setting_type, description) VALUES
('actor_preview_video_url',  '', 'text', 'Preview/mock video shown at top of actor dialog card'),
('song_preview_video_url',   '', 'text', 'Preview/mock video shown at top of actor song card'),
('actor_script_image_url',   '', 'text', 'Portrait image of the dialog script'),
('song_lyrics_image_url',    '', 'text', 'Portrait image of song lyrics'),
('song_tune_youtube_url',    '', 'text', 'YouTube URL for the song tune embed (Get Tune button)'),
('actor_script_pdf_url',     '', 'text', 'PDF download URL for dialog script'),
('song_lyrics_pdf_url',      '', 'text', 'PDF download URL for song lyrics')
