# Submission System Updates - Actor Dual Video Support

## Overview
Complete implementation of dual-video support for actor submissions with modern, mobile-friendly admin UI and automated email notifications.

## Key Features Implemented

### 1. **Actor Dual Video Submission** (Dialog + Song)
- ✅ Actors can submit TWO videos in one submission (Dialog Audition + Song Audition)
- ✅ Both videos are mandatory for actors
- ✅ Each video goes through independent AI moderation
- ✅ Separate AI status tracking for both videos
- ✅ Both videos pushed to YouTube pipeline if auto-publish is enabled

### 2. **Database Changes**
**Migration: `010_actor_dual_upload.sql`** - Enhanced with:
- `file_path_2`, `file_type_2`, `file_size_bytes_2` - Second video fields
- `video_id_2` - Links second video to pipeline
- `ai_status`, `ai_status_2` - AI moderation status for both videos
- `ai_flagged`, `ai_flagged_2` - Flag indicators
- `ai_notes`, `ai_notes_2` - AI moderation notes
- `submission_tag` - Quick identification tag ('actor-dual', 'director-single', 'writer-single')

### 3. **Modern Admin Dashboard UI**

#### **Card-Based Submission List**
- **Role-specific avatars** with color coding (🎭 Actor=Red, 🎬 Director=Amber, ✍️ Writer=Blue)
- **Dual Video Badge** - Clearly shows when actor has submitted 2 videos
- **Expandable dropdown** - Click to reveal both dialog & song videos
- **AI Status indicators** - Real-time status for each video (✅ Pass, 🚩 Flagged, ⏳ Pending)
- **Quick actions** - Watch video, change status, view details

#### **Enhanced Video Cards**
**Actor Submissions:**
- **Dialog Video Card** (Blue theme)
  - Watch Video button
  - AI status badge
  - AI moderation notes
  - Format information

- **Song Video Card** (Pink theme)
  - Watch Video button
  - AI status badge
  - AI moderation notes
  - Format information

**Director/Writer Submissions:**
- Single video display with AI status
- Full-width watch button
- AI moderation details

#### **Detailed Modal View**
- **Applicant Info** - Name, role, email, phone, submission date
- **Role Badges** - Emoji + color-coded tags
- **Video Section** - Side-by-side for actors, full-width for others
- **AI Status Display** - Color-coded badges with notes
- **Admin Notes** - Internal notes field with save functionality
- **Quick Actions** - Delete, close buttons

### 4. **Mobile-First Responsive Design**
- ✅ Fully responsive card layout
- ✅ Touch-friendly buttons and dropdowns
- ✅ Collapsible sections for small screens
- ✅ Clear visual hierarchy
- ✅ Easy-to-tap action buttons

### 5. **Email Notifications**

#### **Submission Received Email** (Sent to Applicant)
- Modern, branded template
- Role-specific emoji and colors
- Submission details confirmation
- Next steps explanation (AI → Team Review → Email Updates)
- Mobile-optimized HTML

#### **Admin New Submission Email** (Sent to Admin)
- Notification of new audition
- Submission ID and applicant details
- Direct link to admin dashboard
- Role and type information

**Email Features:**
- Beautiful HTML templates with Faceless Pictures 3 branding
- Responsive design (works on all devices)
- Clear call-to-action buttons
- Automatic sending on submission

### 6. **Backend Updates**

**SubmissionController.php:**
- Added `submission_tag` to track submission type
- Email notification triggers after successful submission
- Both `store()` and `actorSubmit()` methods updated

**Submission.php Model:**
- Added `updateAIStatus()` for first video
- Added `updateAIStatus2()` for second video
- Enhanced `create()` method with tag support

**EmailService.php:**
- New `sendSubmissionReceivedEmail()` method
- New `sendAdminNewSubmissionEmail()` method
- Automatic email sending based on notification settings

**EmailTemplateService.php:**
- New `submissionReceivedEmail()` template
- New `adminNewSubmissionEmail()` template
- Modern, branded HTML design

### 7. **Tags & Quick Glance**
Every submission now has clear visual identifiers:
- **Actor Dual** - 📹 Dual Video badge + Purple accent
- **Actor/Director/Writer** - Role emoji (🎭/🎬/✍️) + Color badge
- **AI Status** - Per-video status indicators
- **Submission Status** - Color-coded dropdown (New/Reviewed/Shortlisted/Rejected)

## How It Works

### Actor Submission Flow:
1. Actor visits `/actor` page
2. Fills form (name, email, phone)
3. Uploads Dialog Audition video (mandatory)
4. Uploads Song Audition video (mandatory)
5. Submits form → **One submission record created** with `submission_tag='actor-dual'`
6. **Two pipeline videos created:**
   - Video 1: "Dialog Audition — [Name]"
   - Video 2: "Song Audition — [Name]"
7. Both videos queued for AI processing
8. **Email sent to applicant** (confirmation)
9. **Email sent to admin** (notification)
10. AI processes both videos independently
11. If both pass + auto-publish ON → Both push to YouTube
12. Admin reviews in dashboard with dropdown showing both videos

### Director/Writer Submission Flow:
1. Visit `/director` or `/writer` page
2. Fill form + upload single video
3. Submit → One record with `submission_tag='director-single'` or `'writer-single'`
4. One pipeline video created
5. AI processing
6. Emails sent (applicant + admin)
7. Admin reviews single video in dashboard

## UI Design Highlights

### Color Scheme:
- **Actor** - Red (#DC2626)
- **Director** - Amber (#F59E0B)
- **Writer** - Blue (#3B82F6)
- **Primary** - Crimson (#D92B3A)
- **Cream Background** - (#F8F5F0)

### Typography:
- **Headings** - Bebas Neue (display font)
- **Body** - DM Sans
- **Buttons** - Bold, rounded, with hover effects

### Components:
- **Cards** - White background, subtle shadow, rounded corners
- **Badges** - Pill-shaped, color-coded
- **Buttons** - CTA style with hover animations
- **Dropdowns** - Smooth collapse/expand animation
- **Status Indicators** - Emoji + text for clarity

## Files Modified

### Database:
- `database/migrations/010_actor_dual_upload.sql` ✅

### Models:
- `app/models/Submission.php` ✅

### Controllers:
- `app/controllers/SubmissionController.php` ✅

### Services:
- `app/services/EmailService.php` ✅
- `app/services/EmailTemplateService.php` ✅

### Views:
- `public/admin.php` ✅ (Submissions tab completely redesigned)

## Next Steps

1. **Run the migration:**
   ```bash
   php database/run-migrations.php
   ```

2. **Configure Email Settings** in Admin Dashboard → Email tab

3. **Test Actor Dual Submission:**
   - Visit `/actor`
   - Submit both videos
   - Check admin dashboard
   - Verify emails received

4. **AI Integration** (if not already set up):
   - Ensure AI moderation service is configured
   - Videos will auto-process through AI
   - Status updates in submission records

5. **YouTube Auto-Publish:**
   - Enable in Settings if desired
   - Both actor videos will push to YouTube when approved

## Benefits

✅ **Better UX** - Clean, intuitive admin interface
✅ **Mobile-Ready** - Works perfectly on phones/tablets  
✅ **Quick Glance** - Tags and badges show info at a glance
✅ **Organized** - Expandable sections keep UI clean
✅ **Professional** - Modern design matches brand
✅ **Automated** - Email notifications keep everyone informed
✅ **Scalable** - Easily handle hundreds of submissions
✅ **AI-Ready** - Full integration with existing AI pipeline

## Testing Checklist

- [ ] Run database migration
- [ ] Submit actor dual video (dialog + song)
- [ ] Submit director single video
- [ ] Submit writer single video
- [ ] Check admin dashboard displays all correctly
- [ ] Expand actor submission to see both videos
- [ ] Update submission status via dropdown
- [ ] View submission detail modal
- [ ] Verify AI status displays correctly
- [ ] Check emails sent to applicant
- [ ] Check emails sent to admin
- [ ] Test on mobile device
- [ ] Test video watch links work
- [ ] Test delete submission
- [ ] Test search/filter functionality

---

**Implementation Date:** January 2025
**System:** Faceless Pictures 3 - Submission Management  
**Status:** ✅ Complete & Ready for Testing
