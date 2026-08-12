# Admin Guide: Adding Horizontal Trailer Video

## Quick Start Guide

### Step 1: Access Admin Dashboard
1. Log in to your admin account
2. Navigate to **Admin Dashboard** (top menu or /admin URL)
3. Click on **"Settings"** tab in the left sidebar

### Step 2: Find Landing Page Settings
1. In the Settings tab, look for **"Landing Page Content"** section
2. Scroll past the three poster card fields
3. You'll see a new section: **"Horizontal Auto-Play Trailer"**

### Step 3: Add Your Trailer Video

#### Option A: Upload Video File
1. Click on **"Upload Video File"** tab
2. Either:
   - **Drag and drop** your video file into the upload area, OR
   - **Click** the upload area to browse your computer
3. Supported formats: MP4, MOV, WEBM
4. Maximum file size: 500 MB
5. Recommended: Horizontal 16:9 aspect ratio video
6. Wait for upload to complete (progress bar will show)
7. ✅ Green checkmark indicates successful upload

#### Option B: Use YouTube Link
1. Click on **"YouTube URL"** tab
2. Paste your YouTube video URL (any of these formats work):
   - `https://youtu.be/VIDEO_ID`
   - `https://youtube.com/watch?v=VIDEO_ID`
   - `https://youtube.com/shorts/VIDEO_ID`
3. Click **"Save URL"** button
4. ✅ Checkmark appears when saved

### Step 4: Save Changes
1. Scroll to bottom of the Landing Page section
2. Click **"Save All Settings"** button
3. Wait for confirmation message: "✓ Saved successfully"

### Step 5: View on Homepage
1. Open your website homepage in a new tab
2. Scroll to the poster section
3. Your horizontal trailer video will appear below the three posters
4. Video will auto-play (muted) when page loads

## Tips & Best Practices

### Video Recommendations:
- **Aspect Ratio:** 16:9 (horizontal) for best appearance
- **Resolution:** 1080p or higher recommended
- **Duration:** 30 seconds to 2 minutes ideal for trailers
- **File Size:** Keep under 100MB for faster loading
- **Format:** MP4 (H.264 codec) for best compatibility

### YouTube vs Upload:
- **YouTube URL:**
  ✅ Faster page load (video hosted by YouTube)
  ✅ Better for longer videos
  ✅ YouTube handles all formats/quality
  ❌ Requires stable YouTube connection
  
- **Upload Video File:**
  ✅ Full control over video hosting
  ✅ Works without external dependencies
  ✅ Can use custom video players later
  ❌ Uses your server storage
  ❌ Larger files = slower page load

### Priority Note:
⚠️ **If both YouTube URL and uploaded file are set, YouTube URL takes priority**

## Changing or Removing the Trailer

### To Change Video:
1. Go back to Admin → Settings → Landing Page
2. Upload a new file or enter new YouTube URL
3. Click "Save All Settings"
4. Old video is automatically replaced

### To Remove Video:
1. Go to Admin → Settings → Landing Page
2. Find the "Horizontal Auto-Play Trailer" section
3. For uploaded file: Click the ❌ (X) button on the preview
4. For YouTube: Clear the URL field and click "Save URL"
5. Click "Save All Settings"
6. Video will no longer appear on homepage

## Troubleshooting

### Video Won't Upload:
- ❌ **File too large?** → Compress video or use YouTube instead
- ❌ **Wrong format?** → Convert to MP4 using video converter
- ❌ **Upload stuck?** → Check internet connection, try again
- ❌ **"Failed to save" error?** → Check server storage space

### Video Not Showing on Homepage:
- ❌ **Did you click "Save All Settings"?** → Must save changes
- ❌ **Cache issue?** → Hard refresh page (Ctrl+F5 or Cmd+Shift+R)
- ❌ **Empty field?** → Verify video was uploaded/URL was saved

### Autoplay Not Working:
- ℹ️ **This is normal!** Browsers block autoplay with sound
- ℹ️ Video auto-plays **muted** to comply with browser policies
- ℹ️ Users can unmute by clicking volume control
- ℹ️ Some mobile browsers may require user interaction first

### Video Quality Issues:
- **Blurry video?** → Upload higher resolution source
- **Slow loading?** → Compress file or use YouTube
- **Stuttering playback?** → Reduce file size or optimize encoding

## Visual Layout

```
┌─────────────────────────────────────────────────────────────┐
│                      HOMEPAGE LAYOUT                         │
├─────────────────────────────────────────────────────────────┤
│                                                              │
│  ┌──────────┐  ┌──────────┐  ┌──────────┐                 │
│  │ POSTER 1 │  │ POSTER 2 │  │ POSTER 3 │  ← Same Width   │
│  │  (2:3)   │  │  (2:3)   │  │  (2:3)   │                 │
│  └──────────┘  └──────────┘  └──────────┘                 │
│                                                              │
│  ┌──────────────────────────────────────────────────┐      │
│  │                                                   │      │
│  │     HORIZONTAL AUTO-PLAY TRAILER (16:9)         │      │
│  │     ▶️ Plays automatically (muted)              │      │
│  │     🔊 User can unmute                           │      │
│  │                                                   │      │
│  └──────────────────────────────────────────────────┘      │
│  ↑                                                           │
│  └─ Same total width as three posters combined             │
│                                                              │
│  [Rest of page content...]                                  │
└─────────────────────────────────────────────────────────────┘
```

## Support

If you encounter any issues:
1. Check this guide first
2. Clear browser cache and try again
3. Test on different browser
4. Check admin error logs (Admin → Settings → Logs)
5. Contact your developer with specific error messages

---

**Last Updated:** August 12, 2026
**Feature Version:** 1.0
