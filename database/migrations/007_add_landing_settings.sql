-- Migration 007: Landing page + audition brief settings
-- Run with: php database/run-migrations.php

INSERT IGNORE INTO settings (setting_key, setting_value, setting_type, description) VALUES
('landing_poster_url',    '', 'text', 'Poster 1 image URL'),
('landing_poster_title',  'Faceless Pictures 3', 'text', 'Poster 1 title shown on card'),
('landing_trailer_url',   '', 'text', 'Poster 1 trailer URL (YouTube or MP4)'),
('landing_poster2_url',   '', 'text', 'Poster 2 image URL'),
('landing_poster2_title', '', 'text', 'Poster 2 title'),
('landing_trailer2_url',  '', 'text', 'Poster 2 trailer URL'),
('landing_poster3_url',   '', 'text', 'Poster 3 image URL'),
('landing_poster3_title', '', 'text', 'Poster 3 title'),
('landing_trailer3_url',  '', 'text', 'Poster 3 trailer URL'),
('landing_about_text',    'Faceless Pictures is India is first anonymous film competition where talent speaks without a face.', 'text', 'About section text'),
('actor_dialog_script',   'Perform the following scene with full emotion. You receive a call that changes everything. Convey shock, then resolve, in under 90 seconds.', 'text', 'Dialog audition brief'),
('actor_song_script',     'Choose any song representing a character going through transformation. Perform a 60-second version showing emotional range.', 'text', 'Song audition brief'),
('director_brief',        'You have one actor, one phone camera, and a single location. Shoot a 60-second scene that tells a complete emotional story.', 'text', 'Director brief'),
('writer_brief',          'Scene 1 ends: I never thought you would come back. Write Scene 2, 1 to 3 pages, proper screenplay format.', 'text', 'Writer brief')
