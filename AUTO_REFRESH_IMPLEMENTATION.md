# Auto-Refresh System - Implementation Guide

## What It Does

**Automatically refreshes video status** on all pages when AI processing completes:
- ✅ No page reload needed
- ✅ Silent background polling
- ✅ Shows notifications when status changes
- ✅ Updates UI elements in real-time
- ✅ Works on dashboard, admin panel, moderation pages, etc.

---

## How to Enable

### Step 1: Add Script to Your Pages

Add this line in the `<head>` or before `</body>` of any page that shows videos:

```html
<script src="/assets/js/auto-refresh.js"></script>
```

**Pages that need it:**
- Admin panel (`admin.php`)
- Dashboard (`dashboard.php`)
- Moderation panel (`moderation.php` or similar)
- Creator dashboard (`creator/dashboard.php`)
- Any page with video lists

### Step 2: Add Data Attributes to Video Rows

In your HTML table/list, add `data-video-id` attribute:

```html
<tr data-video-id="123" class="video-row">
    <td>
        <span class="status-badge status-pending">pending</span>
    </td>
    <td class="status-text">Pending Review</td>
    <td class="rejection-reason" style="display: none;"></td>
</tr>
```

**Key attributes:**
- `data-video-id="123"` - Required on the row/container
- `class="status-badge"` - Will be updated with new status
- `class="status-text"` - Will show human-readable status
- `class="rejection-reason"` - Will show reason if rejected

### Step 3: (Optional) Custom Refresh Function

If your page has a custom function to reload video data:

```javascript
// Define this function on your page
window.refreshVideoList = function() {
    // Your custom logic to reload videos
    // Example: refetch from API and update table
    fetch('/api/videos')
        .then(r => r.json())
        .then(data => {
            // Update your table/list with new data
            updateVideoTable(data.videos);
        });
};
```

The auto-refresh system will automatically call this function when status changes.

---

## Configuration Options

### Default Settings:
- **Poll interval:** 5 seconds
- **Max retries:** 5
- **Auto-start:** Yes (on video pages)
- **Notifications:** Yes (with sound)

### Custom Configuration:

```javascript
// Create custom instance with different settings
window.videoRefresher = new VideoStatusRefresher({
    pollInterval: 10000,  // Check every 10 seconds instead of 5
    maxRetries: 10,       // More retries before giving up
    onStatusChange: (video, oldStatus, newStatus) => {
        // Custom callback when status changes
        console.log(`Video ${video.id}: ${oldStatus} → ${newStatus}`);
        
        // Your custom logic here
        if (newStatus === 'approved') {
            showSuccessMessage('Video approved!');
        }
    }
});

// Start polling
window.videoRefresher.start();

// Stop polling (if needed)
window.videoRefresher.stop();
```

---

## How It Works

### 1. Background Polling
Every 5 seconds, the system:
1. Calls `/api/videos/status-check`
2. Gets current status of all videos
3. Compares with last known status
4. Detects changes

### 2. When Status Changes
The system automatically:
- ✅ Shows notification (top-right corner)
- ✅ Updates status badge in table/list
- ✅ Updates status text
- ✅ Shows rejection reason (if rejected)
- ✅ Plays subtle sound (optional)
- ✅ Calls `window.refreshVideoList()` (if defined)

### 3. Smart Polling
- ⏸️ Pauses when tab is hidden (saves resources)
- ▶️ Resumes when tab is visible
- 🛑 Stops on page unload
- 🔄 Retries on failure (up to 5 times)
- ❌ Auto-stops after max retries

---

## Status Flow Example

```
Video uploaded → status: "pending"
     ↓
AI starts processing → status: "processing"  🔄 (notification shown)
     ↓
AI completes → status: "approved"  ✅ (notification shown, sound plays)
     ↓
Published to YouTube → status: "published"  📺 (notification shown)
```

**All these changes happen automatically without page reload!**

---

## Styling the Notifications

Notifications are styled automatically, but you can customize:

```css
/* In your custom CSS */
.status-notification {
    /* Your custom styles */
}

.notification-content {
    /* Customize notification layout */
}

/* Status badge colors */
.status-badge.status-approved {
    background: #d1fae5;
    color: #065f46;
}

.status-badge.status-rejected {
    background: #fee2e2;
    color: #991b1b;
}
```

---

## Disable Auto-Start (Manual Control)

If you don't want automatic polling on certain pages:

```javascript
// Stop auto-polling
if (window.videoRefresher) {
    window.videoRefresher.stop();
}

// Start only when user clicks a button
document.getElementById('enable-auto-refresh').addEventListener('click', () => {
    if (!window.videoRefresher) {
        window.videoRefresher = new VideoStatusRefresher();
    }
    window.videoRefresher.start();
});
```

---

## Disable Notification Sounds

```javascript
// Override the sound function globally
window.playNotificationSound = function() {
    // Do nothing = no sound
};
```

---

## Events You Can Listen To

The system dispatches custom events:

```javascript
// Listen for video status updates
window.addEventListener('videos-updated', (event) => {
    console.log('Videos were updated!');
    // Your custom refresh logic
});

// Listen for specific status changes
window.addEventListener('video-approved', (event) => {
    console.log('A video was approved:', event.detail);
});
```

---

## Troubleshooting

### "Auto-refresh not working"
1. Check browser console for `[Auto-Refresh]` logs
2. Verify script is loaded: check Network tab for `/assets/js/auto-refresh.js`
3. Check API endpoint works: open `/api/videos/status-check` in browser

### "Notifications not showing"
1. Check `data-video-id` attribute exists on video rows
2. Verify status is actually changing (check database)
3. Check browser console for errors

### "Polling stops after a while"
1. Check if max retries reached (default: 5)
2. Verify API endpoint `/api/videos/status-check` is accessible
3. Check server logs for errors

### "High CPU/memory usage"
1. Increase poll interval: `pollInterval: 10000` (10 seconds)
2. Reduce max retries: `maxRetries: 3`
3. Stop polling when not needed: `videoRefresher.stop()`

---

## Performance Notes

**Resource Usage:**
- 🟢 Low: ~1 API call every 5 seconds
- 🟢 Minimal: Only polls when tab is visible
- 🟢 Lightweight: Only fetches video IDs and status (not full data)
- 🟢 Efficient: Automatically stops on high failure rate

**Scaling:**
- ✅ Works for up to 100 videos per user
- ✅ Handles multiple users polling simultaneously
- ✅ No database performance impact
- ✅ Can be disabled per-user if needed

---

## Example: Full Page Implementation

```html
<!DOCTYPE html>
<html>
<head>
    <title>Video Dashboard</title>
    <!-- Add auto-refresh script -->
    <script src="/assets/js/auto-refresh.js"></script>
</head>
<body>

<div class="video-list">
    <!-- Each video row needs data-video-id -->
    <div class="video-item" data-video-id="123">
        <h3>My Video Title</h3>
        <span class="status-badge status-pending">pending</span>
        <p class="status-text">Pending Review</p>
        <p class="rejection-reason" style="display:none;"></p>
    </div>

    <div class="video-item" data-video-id="456">
        <h3>Another Video</h3>
        <span class="status-badge status-processing">processing</span>
        <p class="status-text">AI Processing...</p>
        <p class="rejection-reason" style="display:none;"></p>
    </div>
</div>

<script>
// Optional: Custom refresh function
window.refreshVideoList = function() {
    console.log('Refreshing video list...');
    // Reload your video data here
};

// Optional: Listen for status changes
window.addEventListener('videos-updated', () => {
    console.log('Videos were updated!');
});
</script>

</body>
</html>
```

---

## Summary

✅ **Add script:** `<script src="/assets/js/auto-refresh.js"></script>`  
✅ **Add attributes:** `data-video-id="123"` on video rows  
✅ **Automatic:** Works out of the box on dashboard/admin pages  
✅ **Customizable:** Override settings, callbacks, styling  
✅ **Performant:** Smart polling, auto-pauses, lightweight  

That's it! The system will now automatically refresh video statuses across all pages when AI processing completes. 🎉
