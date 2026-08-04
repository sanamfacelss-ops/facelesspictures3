-- Clear fake playlists from database (playlists that don't exist on YouTube)
-- Run this to reset and try creating playlists again with proper OAuth scopes

-- First, check what playlists are in the database
SELECT 
    id,
    playlist_id,
    title,
    role,
    audition_type,
    created_at
FROM youtube_playlists
ORDER BY created_at DESC;

-- To delete all playlists and start fresh, uncomment and run:
-- DELETE FROM youtube_playlists;

-- Also clear the playlist IDs from videos table:
-- UPDATE videos SET youtube_playlist_id = NULL WHERE youtube_playlist_id IS NOT NULL;

-- After running this, you need to fix your YouTube OAuth scope to include playlist management
-- Then click "Create Default Playlists" again
