-- Add text settings for Writer, Director, Actor pages (hero and form sections)

INSERT INTO settings (setting_key, setting_value, setting_type, description) 
VALUES 
-- Writer page
('writer_hero_label', 'Submissions Now Open', 'text', 'Writer page hero top label (leave blank to hide)'),
('writer_hero_heading', 'WRITER SUBMISSIONS', 'text', 'Writer page hero main heading'),
('writer_hero_description', 'READ THE SCENE. WRITE THE NEXT PAGE. RECORD YOUR NARRATION. UPLOAD YOUR VIDEO.', 'text', 'Writer page hero description'),
('writer_form_heading', 'Ready to Write? Submit Your Continuation', 'text', 'Writer submission form heading'),
('writer_form_description', 'Read the given script, write what happens next, then record yourself narrating it on camera.', 'text', 'Writer submission form description'),

-- Director page
('director_hero_label', 'Auditions Now Open', 'text', 'Director page hero top label (leave blank to hide)'),
('director_hero_heading', 'DIRECTOR AUDITIONS', 'text', 'Director page hero main heading'),
('director_hero_description', 'CAST YOUR ACTOR. SHOOT YOUR SCENE. SHOW US YOUR VISION.', 'text', 'Director page hero description'),
('director_form_heading', 'Ready to Direct? Submit Your Scene', 'text', 'Director submission form heading'),
('director_form_description', 'Cast your actor, give them the script, shoot the scene, and upload your video.', 'text', 'Director submission form description'),

-- Actor page
('actor_hero_label', 'Auditions Now Open', 'text', 'Actor page hero top label (leave blank to hide)'),
('actor_hero_heading', 'ACTOR AUDITIONS', 'text', 'Actor page hero main heading'),
('actor_hero_description', 'Two auditions, one submission. Read the dialog brief, learn the song, then shoot both videos.', 'text', 'Actor page hero description'),
('actor_form_heading', 'Ready to Perform? Submit Your Auditions', 'text', 'Actor submission form heading'),
('actor_form_description', 'Shoot your dialog scene and song audition, then upload both videos below.', 'text', 'Actor submission form description')

ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value);
