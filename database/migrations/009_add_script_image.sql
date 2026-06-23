-- Migration 009: Add image_url and audition_type columns to scripts table
-- Run with: php database/run-migrations.php

ALTER TABLE scripts
    ADD COLUMN IF NOT EXISTS image_url VARCHAR(500) NULL COMMENT 'Poster/cover image for the script card' AFTER duration_hint,
    ADD COLUMN IF NOT EXISTS audition_type VARCHAR(100) NULL COMMENT 'e.g. Dialog Audition, Song Audition, Scene Direction' AFTER image_url,
    ADD COLUMN IF NOT EXISTS rules TEXT NULL COMMENT 'Rules/limitations shown on the card (one per line)' AFTER audition_type;

-- Update existing scripts with audition types
UPDATE scripts SET audition_type = 'Dialog Audition' WHERE category = 'actor' AND audition_type IS NULL;
UPDATE scripts SET audition_type = 'Scene Direction' WHERE category = 'director' AND audition_type IS NULL;
UPDATE scripts SET audition_type = 'Script Reading'  WHERE category = 'writer'   AND audition_type IS NULL;

-- Default rules for actors
UPDATE scripts SET rules = 'Video must be under 3 minutes\nShoot on any device\nFace must not be visible\nClear audio required\nNo background music' WHERE category = 'actor' AND rules IS NULL;
UPDATE scripts SET rules = 'Video must be under 5 minutes\nShoot on any device\nDirector may be heard, not seen\nExplain your creative choices\nOne take preferred' WHERE category = 'director' AND rules IS NULL;
UPDATE scripts SET rules = 'Record yourself reading your script\nVideo must be under 3 minutes\nClear audio, minimal background noise\nFace not required to be visible\nOriginal work only' WHERE category = 'writer' AND rules IS NULL;
