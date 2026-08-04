# YouTube Playlist OAuth Scope Fix

## Problem

The playlists appear in your admin dashboard but **don't exist on YouTube**. This happens because your YouTube OAuth refresh token doesn't have the **playlist management scope**.

## Why This Happens

When you created your YouTube OAuth credentials, you only requested these scopes:
- `https://www.googleapis.com/auth/youtube.upload` - Upload videos
- `https://www.googleapis.com/auth/youtube.readonly` - Read channel info

But to **create and manage playlists**, you need:
- `https://www.googleapis.com/auth/youtube` - Full YouTube access (includes playlists)

OR specifically:
- `https://www.googleapis.com/auth/youtube.force-ssl` - Manage playlists

## Solution

You need to **regenerate your OAuth refresh token** with the correct scopes.

### Step 1: Clear Fake Playlists from Database

Run this SQL in your database:

```sql
-- Delete fake playlists that don't exist on YouTube
DELETE FROM youtube_playlists;

-- Clear playlist references from videos
UPDATE videos SET youtube_playlist_id = NULL WHERE youtube_playlist_id IS NOT NULL;
```

### Step 2: Get New OAuth Refresh Token with Playlist Scope

#### Option A: Using Google OAuth Playground (Easiest)

1. Go to: https://developers.google.com/oauthplayground/

2. Click the **⚙️ (gear icon)** in top right

3. Check **"Use your own OAuth credentials"**

4. Enter your:
   - **OAuth Client ID** (from Google Cloud Console)
   - **OAuth Client Secret**

5. In the left panel, find **YouTube Data API v3**

6. Select these scopes:
   ```
   ✅ https://www.googleapis.com/auth/youtube
   ✅ https://www.googleapis.com/auth/youtube.upload
   ```

7. Click **"Authorize APIs"**

8. Sign in with your YouTube channel Google account

9. Click **"Allow"** to grant permissions

10. You'll be redirected back - Click **"Exchange authorization code for tokens"**

11. Copy the **"Refresh token"** that appears

12. Update your `.env` file:
    ```
    YOUTUBE_REFRESH_TOKEN=your_new_refresh_token_here
    ```

#### Option B: Update OAuth Consent Screen Scopes

1. Go to: https://console.cloud.google.com/apis/credentials/consent

2. Click **"EDIT APP"**

3. Go to **"Scopes"** section

4. Click **"ADD OR REMOVE SCOPES"**

5. Find and select:
   ```
   ✅ .../auth/youtube
   ✅ .../auth/youtube.upload
   ✅ .../auth/youtube.force-ssl
   ```

6. Save and then regenerate your refresh token (use Option A steps 1-11)

### Step 3: Test the Connection

1. Go to **Admin Panel → YouTube tab**

2. Click **"Test YouTube + AI Connection"**

3. Verify all checks pass (especially OAuth Token Test)

### Step 4: Create Playlists Again

1. Scroll down to **YouTube Playlists** section

2. Click **"Create Default Playlists"**

3. Check your YouTube channel playlists tab - you should now see 4 new playlists!

## Verify It Worked

Go to your YouTube channel:
- https://www.youtube.com/channel/YOUR_CHANNEL_ID/playlists

You should see:
- ✅ Faceless Pictures - Actor Auditions
- ✅ Faceless Pictures - Actor Song Auditions  
- ✅ Faceless Pictures - Director Submissions
- ✅ Faceless Pictures - Writer Submissions

## Common Issues

### "Invalid Scope" Error
- Your OAuth consent screen doesn't have the right scopes configured
- Follow Option B above to add YouTube scopes

### "Access Denied" Error
- You're not signed in with the right Google account
- Make sure you sign in with the account that owns the YouTube channel

### Playlists Still Not Appearing
- Check browser console (F12) for errors
- Check logs: `logs/*.log` for error messages
- Verify your Channel ID is correct in settings

## Need Help?

If you're still having issues:
1. Check the error log: `logs/error.log`
2. Look for any "playlist" related errors
3. The error message will tell you exactly what's wrong (usually a scope/permission issue)
