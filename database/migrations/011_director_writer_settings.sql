-- Migration 011: Director and writer page settings
INSERT IGNORE INTO settings (setting_key, setting_value, setting_type, description) VALUES
('director_preview_video_url', '', 'text', 'Preview video shown at top of director brief card'),
('director_script_image_url',  '', 'text', 'Portrait image of director script'),
('director_script_pdf_url',    '', 'text', 'PDF download URL for director script'),
('writer_preview_video_url',   '', 'text', 'Preview video shown at top of writer brief card'),
('writer_script_image_url',    '', 'text', 'Portrait image of writer script/story'),
('writer_script_pdf_url',      '', 'text', 'PDF download URL for writer script');
