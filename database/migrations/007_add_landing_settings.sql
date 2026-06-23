-- Migration 007: Landing page + audition brief settings
-- Run with: php database/run-migrations.php

INSERT IGNORE INTO settings (setting_key, setting_value, setting_type, description) VALUES
('landing_poster_url',   '', 'text', 'Poster image URL for landing page hero'),
('landing_trailer_url',  '', 'text', 'Trailer video URL for landing page modal'),
('landing_hero_title',   'NO FACE.\nNO CONNECTIONS.\nJUST TALENT.', 'text', 'Landing page hero headline (one line per row)'),
('landing_hero_subtitle','India is first anonymous film competition. Actors, Directors and Writers compete purely on talent.', 'text', 'Landing page hero subtitle'),
('landing_about_text',   'Faceless Pictures is India is first anonymous film competition where talent speaks without a face.', 'text', 'About section text on landing page'),
('actor_dialog_script',  'Perform the following scene with full emotion. You receive a call that changes everything. Convey shock, then resolve, in under 90 seconds.', 'text', 'Dialog audition brief for actors'),
('actor_song_script',    'Choose any song that represents a character going through transformation. Perform a 60-second version showing emotional range, just your voice.', 'text', 'Song audition brief for actors'),
('director_brief',       'You have one actor, one phone camera, and a single location. Shoot a 60-second scene that tells a complete emotional story. Include your framing choices in your submission notes.', 'text', 'Director audition brief'),
('writer_brief',         'Scene 1 ends with the line: I never thought you would come back. Write Scene 2, between 1 and 3 pages. Format it as a proper screenplay and record yourself reading it on video.', 'text', 'Writer audition brief')
