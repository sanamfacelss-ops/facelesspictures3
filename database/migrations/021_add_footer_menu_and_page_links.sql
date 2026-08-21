-- Migration: Add footer menu items and convert menu URLs to page selections
-- Description: Adds footer menu items and changes header/footer menus to use page dropdown instead of free-text URLs
-- Date: 2026-08-21

-- Update header menu items to use 'page' instead of 'url'
UPDATE settings SET 
    setting_key = 'header_menu_item_1_page',
    description = 'Page selection for first header menu item (home/writer/director/actor/about)'
WHERE setting_key = 'header_menu_item_1_url';

UPDATE settings SET 
    setting_key = 'header_menu_item_2_page',
    description = 'Page selection for second header menu item (home/writer/director/actor/about)'
WHERE setting_key = 'header_menu_item_2_url';

UPDATE settings SET 
    setting_key = 'header_menu_item_3_page',
    description = 'Page selection for third header menu item (home/writer/director/actor/about)'
WHERE setting_key = 'header_menu_item_3_url';

UPDATE settings SET 
    setting_key = 'header_menu_item_4_page',
    description = 'Page selection for fourth header menu item (home/writer/director/actor/about)'
WHERE setting_key = 'header_menu_item_4_url';

-- Update header menu page values to match page names
UPDATE settings SET setting_value = 'about' WHERE setting_key = 'header_menu_item_1_page';
UPDATE settings SET setting_value = 'writer' WHERE setting_key = 'header_menu_item_2_page';
UPDATE settings SET setting_value = 'director' WHERE setting_key = 'header_menu_item_3_page';
UPDATE settings SET setting_value = 'actor' WHERE setting_key = 'header_menu_item_4_page';

-- Add footer menu item 1 text
INSERT INTO settings (setting_key, setting_value, setting_type, description, created_at, updated_at)
VALUES (
    'footer_menu_item_1_text',
    'About',
    'text',
    'Text label for first footer menu item',
    NOW(),
    NOW()
)
ON DUPLICATE KEY UPDATE updated_at = NOW();

-- Add footer menu item 1 page
INSERT INTO settings (setting_key, setting_value, setting_type, description, created_at, updated_at)
VALUES (
    'footer_menu_item_1_page',
    'about',
    'text',
    'Page selection for first footer menu item (home/writer/director/actor/about)',
    NOW(),
    NOW()
)
ON DUPLICATE KEY UPDATE updated_at = NOW();

-- Add footer menu item 1 order
INSERT INTO settings (setting_key, setting_value, setting_type, description, created_at, updated_at)
VALUES (
    'footer_menu_item_1_order',
    '1',
    'text',
    'Display order for first footer menu item',
    NOW(),
    NOW()
)
ON DUPLICATE KEY UPDATE updated_at = NOW();

-- Add footer menu item 2 text
INSERT INTO settings (setting_key, setting_value, setting_type, description, created_at, updated_at)
VALUES (
    'footer_menu_item_2_text',
    'Writers',
    'text',
    'Text label for second footer menu item',
    NOW(),
    NOW()
)
ON DUPLICATE KEY UPDATE updated_at = NOW();

-- Add footer menu item 2 page
INSERT INTO settings (setting_key, setting_value, setting_type, description, created_at, updated_at)
VALUES (
    'footer_menu_item_2_page',
    'writer',
    'text',
    'Page selection for second footer menu item (home/writer/director/actor/about)',
    NOW(),
    NOW()
)
ON DUPLICATE KEY UPDATE updated_at = NOW();

-- Add footer menu item 2 order
INSERT INTO settings (setting_key, setting_value, setting_type, description, created_at, updated_at)
VALUES (
    'footer_menu_item_2_order',
    '2',
    'text',
    'Display order for second footer menu item',
    NOW(),
    NOW()
)
ON DUPLICATE KEY UPDATE updated_at = NOW();

-- Add footer menu item 3 text
INSERT INTO settings (setting_key, setting_value, setting_type, description, created_at, updated_at)
VALUES (
    'footer_menu_item_3_text',
    'Directors',
    'text',
    'Text label for third footer menu item',
    NOW(),
    NOW()
)
ON DUPLICATE KEY UPDATE updated_at = NOW();

-- Add footer menu item 3 page
INSERT INTO settings (setting_key, setting_value, setting_type, description, created_at, updated_at)
VALUES (
    'footer_menu_item_3_page',
    'director',
    'text',
    'Page selection for third footer menu item (home/writer/director/actor/about)',
    NOW(),
    NOW()
)
ON DUPLICATE KEY UPDATE updated_at = NOW();

-- Add footer menu item 3 order
INSERT INTO settings (setting_key, setting_value, setting_type, description, created_at, updated_at)
VALUES (
    'footer_menu_item_3_order',
    '3',
    'text',
    'Display order for third footer menu item',
    NOW(),
    NOW()
)
ON DUPLICATE KEY UPDATE updated_at = NOW();

-- Add footer menu item 4 text
INSERT INTO settings (setting_key, setting_value, setting_type, description, created_at, updated_at)
VALUES (
    'footer_menu_item_4_text',
    'Actors',
    'text',
    'Text label for fourth footer menu item',
    NOW(),
    NOW()
)
ON DUPLICATE KEY UPDATE updated_at = NOW();

-- Add footer menu item 4 page
INSERT INTO settings (setting_key, setting_value, setting_type, description, created_at, updated_at)
VALUES (
    'footer_menu_item_4_page',
    'actor',
    'text',
    'Page selection for fourth footer menu item (home/writer/director/actor/about)',
    NOW(),
    NOW()
)
ON DUPLICATE KEY UPDATE updated_at = NOW();

-- Add footer menu item 4 order
INSERT INTO settings (setting_key, setting_value, setting_type, description, created_at, updated_at)
VALUES (
    'footer_menu_item_4_order',
    '4',
    'text',
    'Display order for fourth footer menu item',
    NOW(),
    NOW()
)
ON DUPLICATE KEY UPDATE updated_at = NOW();
