-- Add logo size setting

INSERT INTO settings (setting_key, setting_value, setting_type, description) 
VALUES 
('site_logo_height', '44', 'text', 'Logo height in pixels (width auto-adjusts)')
ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value);
