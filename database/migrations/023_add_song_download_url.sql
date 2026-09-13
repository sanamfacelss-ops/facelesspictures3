-- Add song_download_url column to scripts table for downloadable audio files
ALTER TABLE scripts ADD COLUMN IF NOT EXISTS song_download_url TEXT DEFAULT NULL;
