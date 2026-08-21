-- Migration: Add header and footer content settings
-- Description: Adds landing_header_content and landing_footer_content fields for customizable header/footer text
-- Date: 2026-08-21

-- Add header content setting
INSERT INTO settings (setting_key, setting_value, setting_type, description, created_at, updated_at)
VALUES (
    'landing_header_content',
    '',
    'text',
    'Optional header content text displayed in the header area across all pages',
    NOW(),
    NOW()
)
ON DUPLICATE KEY UPDATE updated_at = NOW();

-- Add footer content setting
INSERT INTO settings (setting_key, setting_value, setting_type, description, created_at, updated_at)
VALUES (
    'landing_footer_content',
    '© 2024 Faceless Pictures. All rights reserved.',
    'text',
    'Footer content text displayed in the footer area with copyright and other information',
    NOW(),
    NOW()
)
ON DUPLICATE KEY UPDATE updated_at = NOW();
