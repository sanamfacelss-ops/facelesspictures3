-- Add role card text settings for Writer, Director, Actor

-- Writer Role Card
INSERT INTO settings (setting_key, setting_value, setting_type, description) 
VALUES 
('role_writer_title', 'WRITER', 'text', 'Writer role card title'),
('role_writer_icon', '✍️', 'text', 'Writer role card icon emoji'),
('role_writer_description', 'Read your script on camera.\nYour words. Your voice. One video.', 'text', 'Writer role card description (use \n for line breaks)'),
('role_writer_badge1', 'Script Reading', 'text', 'Writer role card first badge (leave blank to hide)'),
('role_writer_badge2', '', 'text', 'Writer role card second badge (leave blank to hide)'),
('role_writer_button_text', 'Click Here →', 'text', 'Writer role card button text'),
('role_writer_button_url', '/writer', 'text', 'Writer role card button URL')
ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value);

-- Director Role Card
INSERT INTO settings (setting_key, setting_value, setting_type, description) 
VALUES 
('role_director_title', 'DIRECTOR', 'text', 'Director role card title'),
('role_director_icon', '🎬', 'text', 'Director role card icon emoji'),
('role_director_description', 'Shoot your scene your way.\nOne phone. One take. Your vision.', 'text', 'Director role card description (use \n for line breaks)'),
('role_director_badge1', 'Scene Direction', 'text', 'Director role card first badge (leave blank to hide)'),
('role_director_badge2', 'Pitch', 'text', 'Director role card second badge (leave blank to hide)'),
('role_director_button_text', 'Click Here →', 'text', 'Director role card button text'),
('role_director_button_url', '/director', 'text', 'Director role card button URL')
ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value);

-- Actor Role Card
INSERT INTO settings (setting_key, setting_value, setting_type, description) 
VALUES 
('role_actor_title', 'ACTOR', 'text', 'Actor role card title'),
('role_actor_icon', '🎭', 'text', 'Actor role card icon emoji'),
('role_actor_description', 'Shoot your scene on camera.\nFace hidden. Talent only.', 'text', 'Actor role card description (use \n for line breaks)'),
('role_actor_badge1', 'Dialogue', 'text', 'Actor role card first badge (leave blank to hide)'),
('role_actor_badge2', 'Song', 'text', 'Actor role card second badge (leave blank to hide)'),
('role_actor_button_text', 'Click Here →', 'text', 'Actor role card button text'),
('role_actor_button_url', '/actor', 'text', 'Actor role card button URL')
ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value);

-- Marquee text items (10 items, leave blank to hide)
INSERT INTO settings (setting_key, setting_value, setting_type, description) 
VALUES 
('marquee_item1', 'ACTORS', 'text', 'Marquee text item 1 (leave blank to hide)'),
('marquee_item2', 'DIRECTORS', 'text', 'Marquee text item 2 (leave blank to hide)'),
('marquee_item3', 'WRITERS', 'text', 'Marquee text item 3 (leave blank to hide)'),
('marquee_item4', 'NO CONNECTIONS', 'text', 'Marquee text item 4 (leave blank to hide)'),
('marquee_item5', 'ONE VIDEO', 'text', 'Marquee text item 5 (leave blank to hide)'),
('marquee_item6', 'ONE CHANCE', 'text', 'Marquee text item 6 (leave blank to hide)'),
('marquee_item7', 'NOW OPEN', 'text', 'Marquee text item 7 (leave blank to hide)'),
('marquee_item8', 'NO FACE', 'text', 'Marquee text item 8 (leave blank to hide)'),
('marquee_item9', 'JUST TALENT', 'text', 'Marquee text item 9 (leave blank to hide)'),
('marquee_item10', 'SUBMIT TODAY', 'text', 'Marquee text item 10 (leave blank to hide)')
ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value);

-- About section settings
INSERT INTO settings (setting_key, setting_value, setting_type, description) 
VALUES 
('about_section_label', 'About', 'text', 'About section top label'),
('about_section_heading', 'WHAT IS FACELESS PICTURES?', 'text', 'About section main heading')
ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value);
