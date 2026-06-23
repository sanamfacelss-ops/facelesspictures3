-- Migration 012: Add preview_video_url, script_pdf_url, tune_youtube_url to scripts table
-- These let each script card have its own mock video, downloadable PDF, and song tune link

ALTER TABLE scripts ADD COLUMN preview_video_url VARCHAR(500) NULL AFTER image_url;
ALTER TABLE scripts ADD COLUMN script_pdf_url VARCHAR(500) NULL AFTER preview_video_url;
ALTER TABLE scripts ADD COLUMN tune_youtube_url VARCHAR(500) NULL AFTER script_pdf_url;
