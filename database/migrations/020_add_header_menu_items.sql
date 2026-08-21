-- Migration: Add customizable header menu items
-- Description: Adds settings for four customizable menu items in the header navigation with ordering
-- Date: 2026-08-21

-- Add menu item 1 text
INSERT INTO settings (setting_key, setting_value, setting_type, description, created_at, updated_at)
VALUES (
    'header_menu_item_1_text',
    'About',
    'text',
    'Text label for first header menu item',
    NOW(),
    NOW()
)
ON DUPLICATE KEY UPDATE updated_at = NOW();

-- Add menu item 1 URL
INSERT INTO settings (setting_key, setting_value, setting_type, description, created_at, updated_at)
VALUES (
    'header_menu_item_1_url',
    '#about',
    'text',
    'URL/link for first header menu item',
    NOW(),
    NOW()
)
ON DUPLICATE KEY UPDATE updated_at = NOW();

-- Add menu item 1 order
INSERT INTO settings (setting_key, setting_value, setting_type, description, created_at, updated_at)
VALUES (
    'header_menu_item_1_order',
    '1',
    'text',
    'Display order for first header menu item (1=leftmost, 4=rightmost)',
    NOW(),
    NOW()
)
ON DUPLICATE KEY UPDATE updated_at = NOW();

-- Add menu item 2 text
INSERT INTO settings (setting_key, setting_value, setting_type, description, created_at, updated_at)
VALUES (
    'header_menu_item_2_text',
    'Writers',
    'text',
    'Text label for second header menu item',
    NOW(),
    NOW()
)
ON DUPLICATE KEY UPDATE updated_at = NOW();

-- Add menu item 2 URL
INSERT INTO settings (setting_key, setting_value, setting_type, description, created_at, updated_at)
VALUES (
    'header_menu_item_2_url',
    '/writer',
    'text',
    'URL/link for second header menu item',
    NOW(),
    NOW()
)
ON DUPLICATE KEY UPDATE updated_at = NOW();

-- Add menu item 2 order
INSERT INTO settings (setting_key, setting_value, setting_type, description, created_at, updated_at)
VALUES (
    'header_menu_item_2_order',
    '2',
    'text',
    'Display order for second header menu item (1=leftmost, 4=rightmost)',
    NOW(),
    NOW()
)
ON DUPLICATE KEY UPDATE updated_at = NOW();

-- Add menu item 3 text
INSERT INTO settings (setting_key, setting_value, setting_type, description, created_at, updated_at)
VALUES (
    'header_menu_item_3_text',
    'Directors',
    'text',
    'Text label for third header menu item',
    NOW(),
    NOW()
)
ON DUPLICATE KEY UPDATE updated_at = NOW();

-- Add menu item 3 URL
INSERT INTO settings (setting_key, setting_value, setting_type, description, created_at, updated_at)
VALUES (
    'header_menu_item_3_url',
    '/director',
    'text',
    'URL/link for third header menu item',
    NOW(),
    NOW()
)
ON DUPLICATE KEY UPDATE updated_at = NOW();

-- Add menu item 3 order
INSERT INTO settings (setting_key, setting_value, setting_type, description, created_at, updated_at)
VALUES (
    'header_menu_item_3_order',
    '3',
    'text',
    'Display order for third header menu item (1=leftmost, 4=rightmost)',
    NOW(),
    NOW()
)
ON DUPLICATE KEY UPDATE updated_at = NOW();

-- Add menu item 4 text
INSERT INTO settings (setting_key, setting_value, setting_type, description, created_at, updated_at)
VALUES (
    'header_menu_item_4_text',
    'Actors',
    'text',
    'Text label for fourth header menu item',
    NOW(),
    NOW()
)
ON DUPLICATE KEY UPDATE updated_at = NOW();

-- Add menu item 4 URL
INSERT INTO settings (setting_key, setting_value, setting_type, description, created_at, updated_at)
VALUES (
    'header_menu_item_4_url',
    '/actor',
    'text',
    'URL/link for fourth header menu item',
    NOW(),
    NOW()
)
ON DUPLICATE KEY UPDATE updated_at = NOW();

-- Add menu item 4 order
INSERT INTO settings (setting_key, setting_value, setting_type, description, created_at, updated_at)
VALUES (
    'header_menu_item_4_order',
    '4',
    'text',
    'Display order for fourth header menu item (1=leftmost, 4=rightmost)',
    NOW(),
    NOW()
)
ON DUPLICATE KEY UPDATE updated_at = NOW();
