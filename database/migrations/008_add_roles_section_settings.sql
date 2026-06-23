-- Migration 008: Add role cards section heading settings
-- Run with: php database/run-migrations.php

INSERT IGNORE INTO settings (setting_key, setting_value, setting_type, description) VALUES
('landing_roles_heading',    'Become a Star in 3 Clicks',                    'text', 'Heading above the Actor/Director/Writer role cards'),
('landing_roles_subheading', 'Pick your role. Shoot your video. Submit.',     'text', 'Subheading below the roles heading')
