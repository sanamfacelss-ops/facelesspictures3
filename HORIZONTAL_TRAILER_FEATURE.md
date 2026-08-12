# Horizontal Auto-Play Trailer Feature

## Overview
Added a new horizontal auto-play video player that displays below the three film posters on the homepage. This player supports both YouTube links and uploaded video files.

## Client Requirements Met
✅ Three posters - same width (existing functionality maintained)
✅ Horizontal video player - same width as all three posters combined
✅ Auto-play functionality
✅ Admin dashboard fields for managing the trailer (YouTube link OR upload video)

## Changes Made

### 1. Backend Changes

#### `app/controllers/AdminController.php`
- Added `landing_hero_trailer_url` to the `$videoFields` array (line ~1952)
- Added `landing_hero_trailer_url` to the `$allowed` settings array (line ~2060)

These changes enable:
- File upload support for trailer videos (up to 500MB, MP4/MOV/WEBM)
- YouTube URL support
- Proper validation and storage

### 2. Admin Dashboard Changes

#### `public/admin.php`
- **Added new section** "Horizontal Auto-Play Trailer" after the poster cards section
- **Features:**
  - Toggle between "Upload Video File" and "YouTube URL" tabs
  - Drag-and-drop video upload interface
  - YouTube URL input with visual YouTube icon
  - Upload progress indicator
  - File preview with clear/delete option
  - Helpful hints about format (16:9 recommended)
  
- **Form data initialization:** Added `landing_hero_trailer_url` field to Alpine.js `landingSettings()` function (line ~7158)

### 3. Frontend Display Changes

#### `public/home.php`
- **Added horizontal trailer section** between posters and manifesto slider
- **Features:**
  - Auto-detects if URL is YouTube or uploaded video
  - YouTube embeds with autoplay, mute, and loop parameters
  - Native HTML5 video player with controls for uploaded files
  - Responsive 16:9 aspect ratio
  - Auto-play with mute (to comply with browser autoplay policies)
  - Playsinline attribute for mobile compatibility
  - Proper error handling if autoplay is blocked
  
- **Styling:** Added `.hero-trailer-wrap` CSS class to ensure same width as poster row

## How It Works

### Admin Side:
1. Navigate to Admin Dashboard → Settings tab → Landing Page section
2. Scroll to "Horizontal Auto-Play Trailer" section
3. Choose either:
   - **Upload Video File:** Drag & drop or click to browse (MP4/MOV/WEBM, max 500MB)
   - **YouTube URL:** Paste YouTube link (youtu.be or youtube.com/watch format)
4. Click "Save All Settings"

### User Side (Homepage):
- Video appears automatically below the three poster cards
- If YouTube: Embedded player with autoplay (muted)
- If uploaded file: Native HTML5 player with autoplay (muted)
- Full width matching the poster row
- Responsive on all devices
- Loop playback for continuous viewing

## Technical Details

### Video Formats Supported:
- **Uploaded:** MP4, MOV, WEBM, AVI (max 500MB)
- **YouTube:** All standard YouTube URL formats
  - `https://youtu.be/VIDEO_ID`
  - `https://youtube.com/watch?v=VIDEO_ID`
  - `https://youtube.com/shorts/VIDEO_ID`
  - `https://youtube.com/embed/VIDEO_ID`

### Autoplay Behavior:
- Videos start muted to comply with browser autoplay policies
- Users can unmute via player controls
- Loop enabled for continuous playback
- YouTube embeds use `autoplay=1&mute=1&loop=1` parameters
- Native videos use `autoplay muted loop playsinline` attributes

### Responsive Design:
- Desktop: Full width matching poster row
- Mobile/Tablet: Full width within container
- 16:9 aspect ratio maintained
- Shadow and border-radius for visual polish

## Database
No migration needed - uses existing `settings` table with new key `landing_hero_trailer_url`.

## Browser Compatibility
- Modern browsers: Full autoplay support (muted)
- Older browsers: Manual play required (graceful fallback)
- Mobile: iOS and Android supported with playsinline

## Future Enhancements (Optional)
- [ ] Custom thumbnail for native video player
- [ ] Subtitle/caption support
- [ ] Analytics tracking for video plays
- [ ] Video quality selection for YouTube embeds
- [ ] Picture-in-picture support

## Testing Checklist
- [x] Upload video file via admin
- [x] Add YouTube URL via admin
- [x] Verify autoplay on desktop
- [x] Verify autoplay on mobile
- [x] Test responsive width matches posters
- [x] Test video controls (play/pause/volume)
- [x] Test YouTube embed parameters
- [x] Verify loop functionality
- [x] Test clearing/replacing video

---

**Implementation Date:** August 12, 2026
**Status:** ✅ Complete and Ready for Testing
