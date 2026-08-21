-- Migration: Add role-specific submission success/failure messages
-- Date: 2026-08-21
-- Description: Adds customizable success and failure messages for actor, director, writer submissions

-- Actor submission messages
INSERT INTO settings (setting_key, setting_value, setting_type, description) VALUES
('actor_success_heading', 'ACTOR SUBMISSION RECEIVED!', 'text', 'Success modal heading for actor submissions'),
('actor_success_message', 'Your acting video is in the queue for AI review and will be published to YouTube once approved. We''ll be in touch at your email.', 'text', 'Success modal message for actor submissions'),
('actor_success_pdf_button', 'Download Actor Brief PDF', 'text', 'PDF download button label for actor submissions'),
('actor_failure_heading', 'SUBMISSION FAILED', 'text', 'Failure modal heading for actor submissions'),
('actor_failure_message', 'We couldn''t process your acting video. Please check your file and try again.', 'text', 'Failure modal message for actor submissions'),
('actor_failure_retry_button', 'Try Again', 'text', 'Retry button label for actor submissions')
ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value);

-- Director submission messages
INSERT INTO settings (setting_key, setting_value, setting_type, description) VALUES
('director_success_heading', 'DIRECTOR SUBMISSION RECEIVED!', 'text', 'Success modal heading for director submissions'),
('director_success_message', 'Your director video is in the queue for AI review and will be published to YouTube once approved. We''ll be in touch at your email.', 'text', 'Success modal message for director submissions'),
('director_success_pdf_button', 'Download Director Brief PDF', 'text', 'PDF download button label for director submissions'),
('director_failure_heading', 'SUBMISSION FAILED', 'text', 'Failure modal heading for director submissions'),
('director_failure_message', 'We couldn''t process your director video. Please check your file and try again.', 'text', 'Failure modal message for director submissions'),
('director_failure_retry_button', 'Try Again', 'text', 'Retry button label for director submissions')
ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value);

-- Writer submission messages
INSERT INTO settings (setting_key, setting_value, setting_type, description) VALUES
('writer_success_heading', 'WRITER SUBMISSION RECEIVED!', 'text', 'Success modal heading for writer submissions'),
('writer_success_message', 'Your writer video is in the queue for AI review and will be published to YouTube once approved. We''ll be in touch at your email.', 'text', 'Success modal message for writer submissions'),
('writer_success_pdf_button', 'Download Writer Brief PDF', 'text', 'PDF download button label for writer submissions'),
('writer_failure_heading', 'SUBMISSION FAILED', 'text', 'Failure modal heading for writer submissions'),
('writer_failure_message', 'We couldn''t process your writer video. Please check your file and try again.', 'text', 'Failure modal message for writer submissions'),
('writer_failure_retry_button', 'Try Again', 'text', 'Retry button label for writer submissions')
ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value);
