# YouTube Playlist Migration Fix

The migration 014 encountered an error because it tried to use a `category` column that doesn't exist in your settings table.

## What Happened
- Migration 014 tried to insert settings with a `category` column
- Your settings table only has: `setting_key`, `setting_value`, `setting_type`, `description`
- The migration was fixed to remove the `category` reference

## Solution

### Option 1: Quick Fix (Recommended)

Run this command to manually mark migration 014 as complete and run migration 015:

```bash
# Navigate to your project
cd e:\facelesspictures3

# Run the fix SQL (you'll need to connect to MySQL)
mysql -u your_username -p your_database < database/fix-migration-014.sql

# Then run migrations again
php database/run-migrations.php
```

### Option 2: Manual Steps

1. **Connect to your MySQL database** (via phpMyAdmin, MySQL Workbench, or command line)

2. **Mark migration 014 as complete:**
   ```sql
   INSERT INTO migrations (filename) VALUES ('014_add_playlist_support.sql')
   ON DUPLICATE KEY UPDATE filename = filename;
   ```

3. **Verify the youtube_playlists table exists:**
   ```sql
   SHOW TABLES LIKE 'youtube_playlists';
   ```

4. **If it doesn't exist, create it:**
   ```sql
   CREATE TABLE youtube_playlists (
       id INT AUTO_INCREMENT PRIMARY KEY,
       playlist_id VARCHAR(255) NOT NULL UNIQUE,
       title VARCHAR(255) NOT NULL,
       description TEXT,
       role ENUM('actor', 'director', 'writer') NOT NULL,
       audition_type VARCHAR(100) DEFAULT NULL,
       season_id INT DEFAULT NULL,
       created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
       updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
       FOREIGN KEY (season_id) REFERENCES seasons(id) ON DELETE SET NULL,
       INDEX idx_role (role),
       INDEX idx_audition_type (audition_type),
       INDEX idx_season (season_id)
   ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
   ```

5. **Add the settings:**
   ```sql
   INSERT INTO settings (setting_key, setting_value, description) 
   VALUES 
       ('youtube_playlist_enabled', '1', 'Enable automatic playlist organization'),
       ('youtube_playlist_per_season', '0', 'Create separate playlists for each season')
   ON DUPLICATE KEY UPDATE setting_value = setting_value;
   ```

6. **Run migrations again to apply migration 015:**
   ```bash
   php database/run-migrations.php
   ```

## What's Fixed

1. ✅ **Migration 014** - Fixed to not use `category` column
2. ✅ **Migration 015** - New migration that adds `youtube_playlist_id` to videos table
3. ✅ Both migrations now work with your existing database schema

## After Fix

Once completed, you should see:
```
SKIP: 001_add_password_resets.sql (already applied)
...
SKIP: 014_add_playlist_support.sql (already applied)
RAN: 015_add_playlist_id_to_videos.sql

Done. 1 applied, 14 skipped.
```

Then you can use the YouTube Playlist Management feature in the admin panel!
