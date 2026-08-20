# Admin Landing Page Settings Reorganization Plan

## Goal
Organize the scattered landing page settings into clear, logical tabs that are easy for non-technical users to understand on both desktop and mobile.

## Current Issues
- All settings are in one long scrolling page
- Related settings are mixed together
- Hard to find specific settings
- No clear separation between home page vs. role-specific pages
- Not intuitive for non-technical users

## Proposed Tab Structure

### Tab 1: 🏠 **Home Page**
**Purpose**: All content shown on the home page (/)

**Sections**:
1. **Brand** - Logo, logo size
2. **Hero Section** - Main headline, tagline
3. **Role Cards Heading** - "Become a Star in 3 Clicks" heading/subheading
4. **Film Poster Cards** - 6 poster slots with titles, trailers, buttons
5. **Horizontal Auto-Play Trailer** - Full-width video
6. **Manifesto Video Slider** - 6 video slots
7. **About Section** - Label, heading, text
8. **Marquee** - 10 scrolling text items

---

### Tab 2: 🎭 **Role Cards**  
**Purpose**: The 3 role cards (Writer/Director/Actor) shown on home page

**Sections**:
1. **Writer Card** - Title, icon, description, badges, button
2. **Director Card** - Title, icon, description, badges, button
3. **Actor Card** - Title, icon, description, badges, button

---

### Tab 3: ✍️ **Writer Page**
**Purpose**: Content shown on /writer page

**Sections**:
1. **Hero Section** - Label, heading, description
2. **Submission Form** - Form heading, form description

---

### Tab 4: 🎬 **Director Page**
**Purpose**: Content shown on /director page

**Sections**:
1. **Hero Section** - Label, heading, description
2. **Submission Form** - Form heading, form description

---

### Tab 5: 🎤 **Actor Page**
**Purpose**: Content shown on /actor page

**Sections**:
1. **Hero Section** - Label, heading, description
2. **Submission Form** - Form heading, form description

---

## Implementation Notes

### Alpine.js Structure
```javascript
<div x-data="{landingTab: 'home'}">
  <!-- Tab Navigation -->
  <div class="tabs">
    <button @click="landingTab='home'" :class="active">🏠 Home Page</button>
    <button @click="landingTab='rolecards'" :class="active">🎭 Role Cards</button>
    <button @click="landingTab='writer'" :class="active">✍️ Writer Page</button>
    <button @click="landingTab='director'" :class="active">🎬 Director Page</button>
    <button @click="landingTab='actor'" :class="active">🎤 Actor Page</button>
  </div>

  <!-- Tab Content -->
  <div x-show="landingTab==='home'" x-transition>
    <!-- Home page settings -->
  </div>
  
  <div x-show="landingTab==='rolecards'" x-transition>
    <!-- Role cards settings -->
  </div>
  
  <!-- ... etc for other tabs -->
</div>
```

### Mobile Friendly Features
- Horizontal scrolling tab buttons on mobile
- Stack tabs vertically if needed
- Touch-friendly button sizing (min 44px height)
- Clear active state with background color
- Emoji icons help with visual recognition

### User-Friendly Features  
- Clear tab names (not technical jargon)
- Helper text at top of each tab explaining what it controls
- Preview link for each page ("Visit /writer →")
- Consistent visual design across all tabs
- Single "Save All Settings" button that works for all tabs
- Saved confirmation shows regardless of active tab

---

## Benefits

1. **Logical Organization**: Settings grouped by page/feature
2. **Easier Navigation**: Jump directly to what you need
3. **Less Scrolling**: Only see relevant settings
4. **Clearer Purpose**: Each tab has one clear job
5. **Mobile Friendly**: Tabs work well on small screens
6. **Non-Technical**: Names and emojis anyone can understand
7. **No Breaking Changes**: All existing functionality preserved

---

## Rollout Plan

1. ✅ Create this plan document
2. Create backup of admin.php
3. Implement tab navigation UI
4. Move "Home Page" content into first tab
5. Move "Role Cards" into second tab
6. Move "Writer/Director/Actor" into separate tabs
7. Test all functionality
8. Test mobile responsiveness
9. Verify save works across all tabs
10. Commit and push changes

