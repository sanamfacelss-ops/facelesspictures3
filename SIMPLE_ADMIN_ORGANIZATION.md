# Simple Admin Organization - SAFER APPROACH

## Problem
Current Landing Page Settings are one long scrolling list with no organization. Settings for home page, role cards, and individual role pages are all mixed together.

## Simple Solution: Collapsible Sections

Instead of complex tabs, use **Alpine.js collapsible accordions** with clear headings:

### Structure:
```
Landing Page & Site Settings
├─ 🏠 HOME PAGE CONTENT ▼
│   ├─ Brand (logo, size)
│   ├─ Hero Section
│   ├─ Role Cards Heading
│   ├─ Film Posters (6 slots)
│   ├─ Horizontal Trailer
│   ├─ Manifesto Videos
│   ├─ About Section
│   └─ Marquee
│
├─ 🎭 ROLE CARDS (shown on home page) ▼
│   ├─ Writer Card
│   ├─ Director Card
│   └─ Actor Card
│
├─ ✍️ WRITER PAGE TEXT ▼
│   ├─ Hero Section
│   └─ Submission Form
│
├─ 🎬 DIRECTOR PAGE TEXT ▼
│   ├─ Hero Section
│   └─ Submission Form
│
└─ 🎤 ACTOR PAGE TEXT ▼
    ├─ Hero Section
    └─ Submission Form
```

## Benefits
- ✅ Minimal code changes (safer)
- ✅ All settings still on one page
- ✅ Clear visual hierarchy
- ✅ Expandable/collapsible sections
- ✅ Mobile friendly
- ✅ No complex tab state management
- ✅ Same save button works for everything
- ✅ Can default to Home Page expanded, others collapsed

## Implementation
Use Alpine.js `x-data` with simple show/hide:

```html
<div x-data="{showHome: true, showRoleCards: false, showWriter: false, showDirector: false, showActor: false}">
  
  <!-- HOME PAGE -->
  <div class="section-header" @click="showHome = !showHome">
    <h4>🏠 HOME PAGE CONTENT</h4>
    <span x-show="showHome">▼</span>
    <span x-show="!showHome">▶</span>
  </div>
  <div x-show="showHome" x-transition>
    <!-- All home page settings -->
  </div>

  <!-- ROLE CARDS -->
  <div class="section-header" @click="showRoleCards = !showRoleCards">
    <h4>🎭 ROLE CARDS</h4>
    <span x-show="showRoleCards">▼</span>
    <span x-show="!showRoleCards">▶</span>
  </div>
  <div x-show="showRoleCards" x-transition>
    <!-- Role card settings -->
  </div>

  <!-- ... etc -->
</div>
```

## Styling
```css
.section-header {
  background: #f9fafb;
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  padding: 1rem 1.5rem;
  margin-bottom: 1rem;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: space-between;
  transition: all 0.2s;
}

.section-header:hover {
  background: #f3f4f6;
  border-color: #d1d5db;
}

.section-header h4 {
  font-size: 14px;
  font-weight: 600;
  margin: 0;
}
```

This is MUCH safer than complex tab restructuring!

