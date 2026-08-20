# Role Pages Design Consistency Fix

## Problem
Writer and Director pages have inconsistent design compared to Actor page:
- **Actor page**: Beautiful two-column grid with consistent brief cards
- **Writer/Director pages**: Side-by-side layout with different styling

## Solution Required
Make Writer and Director pages match the Actor page design exactly.

## Current Actor Page Structure (GOOD - this is what we want):
```
1. Hero section (consistent across all)
2. Two-column brief card grid (.brief-grid)
   - Each card: .brief-card with:
     - Card header (with role type badge)
     - Preview video container (.media-9-16 with custom video player)
     - Brief/Script section with .sec-label
     - Script image (.portrait-img-wrap with zoom functionality)
     - Download button (.btn-outline)
3. Film Song Card (actor-specific, between briefs and form)
4. Submission form at bottom (full width)
```

## Current Writer/Director Structure (NEEDS FIX):
```
1. Hero section
2. Side-by-side layout (.side-by-side):
   - LEFT: Brief card (narrower, different style)
   - RIGHT: Submission form (inline, not at bottom)
```

## What Needs to Change:

### Writer Page:
1. Remove `.side-by-side` container
2. Add `.brief-grid` with single column (max-width: 640px centered)
3. Use same `.brief-card` structure as Actor:
   - Same header styling
   - Same `.media-9-16` video container with custom player
   - Same `.portrait-img-wrap` with zoom functionality  
   - Same button styling
4. Move submission form below, full width like Actor

### Director Page:
1. Same changes as Writer
2. Handle dual upload (Scene Direction + Pitch) like Actor handles Dialogue + Song
3. Can keep two-column grid if there are 2 audition types

## Key Components to Replicate:

### 1. Video Player (.media-9-16 + custom controls):
```html
<div class="media-9-16 pv-wrap" x-data="localPlayer()">
  <!-- Badge overlay -->
  <div class="media-badge">DIALOG AUDITION</div>
  
  <!-- Video element -->
  <video></video>
  
  <!-- Play overlay -->
  <div class="pv-overlay">
    <div class="pv-play"><!-- play icon --></div>
  </div>
  
  <!-- Control bar -->
  <div class="pv-bar">
    <div class="pv-progress" @click="seek"></div>
    <div class="pv-row">
      <button class="pv-btn" @click="togglePlay"><!-- icon --></button>
      <button class="pv-btn" @click="toggleMute"><!-- icon --></button>
      <div class="pv-time"></div>
    </div>
  </div>
</div>
```

### 2. Image Zoom (.portrait-img-wrap):
```html
<div class="portrait-img-wrap" @click="openLightbox()">
  <img src="..." alt="Script">
</div>
```

### 3. Lightbox Modal:
```html
<div id="imgLightbox" :class="lightboxOpen?'open':''">
  <div class="lb-close" @click="closeLightbox">×</div>
  <!-- Zoom/pan controls -->
</div>
```

### 4. Alpine.js Components Needed:
- `localPlayer()` - Custom video player controls
- Image lightbox state management
- Submission form state

## Files to Update:
- `public/writer.php` - Complete redesign to match actor.php
- `public/director.php` - Complete redesign to match actor.php

## CSS Classes to Use (from actor.php):
- `.brief-grid` - Main grid container
- `.brief-card` - Card wrapper
- `.card-sec` - Card sections
- `.media-9-16` - Video container (9:16 aspect ratio)
- `.media-badge` - Overlapping badge
- `.pv-wrap`, `.pv-overlay`, `.pv-bar` - Custom player
- `.portrait-img-wrap` - Image with zoom
- `.btn-outline` - Download buttons
- `.submit-card` - Form at bottom

## Testing Checklist:
- [ ] Writer page matches Actor visual design
- [ ] Director page matches Actor visual design
- [ ] Video player works (play/pause/seek/mute)
- [ ] Image zoom/lightbox works
- [ ] Download PDF buttons work
- [ ] Submission forms work
- [ ] Mobile responsive (cards stack)
- [ ] All Alpine.js interactions work

## Notes:
- Actor page has 2 card types (Dialog + Song) with Film Song card between
- Writer has 1 card type (single script)
- Director has 1 or 2 types (Scene + Pitch)
- All should use same card design, just different count
