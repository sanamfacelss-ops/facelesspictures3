# Scripts Tab UI Redesign Plan

## Current Problems
1. **Confusing Layout**: Create form and list are side-by-side, unclear what's what
2. **No Clear Action**: "Edit" button purpose is unclear
3. **No Delete Confirmation**: Delete happens immediately, no "Are you sure?"
4. **Not Mobile-Friendly**: Side-by-side layout doesn't work on mobile
5. **Poor Visual Hierarchy**: Hard to scan the list of scripts

## Proposed New Design

### Layout Structure

```
┌─────────────────────────────────────────────────────────────┐
│  [Info Banner: Briefs moved to Settings]                    │
└─────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────┐
│  [+ Create New Script]  (Big, prominent button, top-right)  │
└─────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────┐
│  Existing Scripts (Full Width List)                          │
│                                                               │
│  ┌──────────────────────────────────────────────────────┐   │
│  │ 🎭 Dialog Audition  │  Dramatic Monologue #1         │   │
│  │ Actor · 60-90 sec   │  [✏️ Edit] [🗑️ Delete]         │   │
│  └──────────────────────────────────────────────────────┘   │
│                                                               │
│  ┌──────────────────────────────────────────────────────┐   │
│  │ 🎤 Song Audition    │  Bollywood Classic Song        │   │
│  │ Actor · 2-3 minutes │  [✏️ Edit] [🗑️ Delete]         │   │
│  └──────────────────────────────────────────────────────┘   │
│                                                               │
│  ┌──────────────────────────────────────────────────────┐   │
│  │ 🎬 Director Audition│  Scene Direction Brief         │   │
│  │ Director · 1 minute │  [✏️ Edit] [🗑️ Delete]         │   │
│  └──────────────────────────────────────────────────────┘   │
└─────────────────────────────────────────────────────────────┘
```

### Key Features

#### 1. **"+ Create New Script" Button**
- **Location**: Top-right of the tab
- **Style**: Large, prominent, blue/crimson background
- **Action**: Opens a modal or slides down form
- **Icon**: Plus icon + "Create New Script" text

#### 2. **Full-Width Script Cards**
Each script shows as a card with:
- **Left Side**: 
  - Emoji icon (🎭 dialog, 🎤 song, 🎬 director, ✍️ writer)
  - Audition Type (bold)
  - Category · Duration (smaller, gray text)
  
- **Right Side**:
  - Script Title (medium size)
  - **Edit Button** (pencil icon, blue/gray)
  - **Delete Button** (trash icon, red)

#### 3. **Edit Flow**
**Option A: Modal**
- Click Edit → Opens modal with form
- Fill in details
- Save or Cancel buttons
- Modal closes on save

**Option B: Inline Expansion**
- Click Edit → Card expands to show full form below
- Edit in place
- Save or Cancel buttons

**Recommendation**: Modal is cleaner

#### 4. **Delete Confirmation**
- Click Delete → Shows modal:
  ```
  ⚠️ Delete Script?
  
  Are you sure you want to delete "Dramatic Monologue #1"?
  This action cannot be undone.
  
  [Cancel]  [Delete Script]
  ```
- "Delete Script" button is red
- Clicking outside modal or Cancel closes it
- Only deletes on confirmation

#### 5. **Mobile-Friendly Design**
- Full-width cards stack vertically
- Buttons stack on mobile if needed
- Touch-friendly button sizes (min 44px)
- Swipe actions optional (swipe left to delete)

### Implementation Steps

1. **Create Modal Component**
   - Alpine.js modal for create/edit
   - Alpine.js modal for delete confirmation
   
2. **Redesign Script List**
   - Change from grid to full-width stack
   - Add clear card design
   - Add prominent edit/delete buttons

3. **Add "+ Create New Script" Button**
   - Fixed position at top
   - Opens modal on click

4. **Style Improvements**
   - Consistent spacing
   - Clear visual hierarchy
   - Color-coded by category (optional)
   - Icons for quick recognition

5. **Test Thoroughly**
   - Desktop: all resolutions
   - Mobile: touch interactions
   - Tablet: intermediate layouts

### Benefits

✅ **Clear Action**: Big "Create New Script" button is obvious
✅ **Easy to Scan**: Full-width list is easy to read
✅ **Safer Delete**: Confirmation prevents accidents
✅ **Mobile-Friendly**: Stack layout works on all screens
✅ **Better UX**: Edit/Delete actions are clear and accessible

---

## Alternative: Simplified Approach (Faster to Implement)

If full redesign is too complex, we can do a **quick fix**:

1. Keep current layout but:
   - Move "Create New Script" to top with a big button
   - Add prominent "✏️ EDIT" and "🗑️ DELETE" text labels to buttons
   - Add delete confirmation dialog
   - Improve spacing

This would take 30 minutes vs. 2-3 hours for full redesign.

**Which approach do you prefer?**

