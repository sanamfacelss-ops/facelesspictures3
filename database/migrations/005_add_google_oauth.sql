-- Add Google OAuth support to users table
ALTER TABLE users ADD COLUMN IF NOT EXISTS google_id VARCHAR(255) NULL AFTER content_categories;
ALTER TABLE users ADD UNIQUE INDEX IF NOT EXISTS idx_users_google_id (google_id);

-- Add email settings table
CREATE TABLE IF NOT EXISTS email_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) NOT NULL UNIQUE,
    setting_value TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Insert default email notification settings
INSERT IGNORE INTO email_settings (setting_key, setting_value) VALUES
('notify_on_signup', '1'),
('notify_on_video_submit', '1'),
('notify_on_video_approved', '1'),
('notify_on_video_rejected', '1'),
('notify_on_video_flagged', '1'),
('notify_on_video_processing', '1'),
('admin_notification_email', ''),
('email_footer_text', 'Faceless Pictures 3 - India''s First Anonymous Film Competition');
