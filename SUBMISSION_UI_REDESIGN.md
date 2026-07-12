# Submission UI Redesign - Implementation Guide

## Summary of Changes

The admin dashboard has been redesigned to remove the confusing dual-tab structure (Videos + Auditions) and consolidate everything into a single "Submissions" tab.

### What Was Changed:

1. **Sidebar Navigation** (✅ COMPLETE)
   - Renamed "Videos" button to "Submissions" 
   - Added new submission count badge on the Submissions button
   - Removed the separate "Auditions" button
   - File: `public/admin.php` lines ~498-512

2. **Tab Title** (✅ COMPLETE)
   - Changed tab title from "VIDEOS" to "SUBMISSIONS"
   - File: `public/admin.php` line ~4305

3. **Videos Tab Redesign** (⚠️ PARTIALLY COMPLETE - NEEDS FINAL STEP)
   - Replaced the old video-centric UI with a submission-centric UI
   - Added header with "ALL SUBMISSIONS" title
   - Added submission filter controls (search, role filter, status filter)
   - File: `public/admin.php` lines ~924-990

### What Still Needs To Be Done:

**CRITICAL:** The submission card display template needs to be copied from the existing "SUBMISSIONS TAB" section and pasted into the new "Videos" tab (which is now showing as "Submissions" in the sidebar).

#### Step-by-Step Instructions:

1. Open `public/admin.php`

2. Find the SUBMISSIONS TAB section (around line 1221)
   - Look for: `<!-- Modern Card-Based Submissions List -->`
   
3. Copy the ENTIRE submission card template loop starting from:
   ```html
   <template x-for="sub in filteredSubmissions" :key="sub.id">
       <div class="bg-white rounded-xl border border-dark/5 overflow-hidden hover:shadow-lg transition-shadow">
   ```
   
4. Copy all the way down to the closing `</template>` and the empty state div:
   ```html
   </template>
   
   <!-- Empty State -->
   <div x-show="filteredSubmissions.length === 0" class="bg-white rounded-xl border border-dark/5 p-12 text-center">
       ...
   </div>
   ```
   
5. Find the Videos tab section (around line 971) that contains the blue placeholder message

6. Replace the entire `<p class="bg-blue-50...">` placeholder block with the copied template

7. Save the file

### Why This Design Is Better:

- **ONE PLACE**: All submissions (Actor dual-videos, Directors, Writers) are in one tab
- **SMART GROUPING**: Actor dual submissions show as ONE entry with expandable dropdown for both videos
- **CLEAR STATUS**: Each submission shows AI status for each video separately  
- **SIMPLE UI**: Non-tech-savvy client sees submissions grouped logically by person, not scattered across multiple entries

### Features of the New Design:

✅ **Actor Dual-Video Submissions**:
- Show as single card with "📹 Dual Video" badge
- Expandable dropdown reveals both Dialog and Song auditions
- Each video has its own AI status indicator (✅ Pass, 🚩 Flagged, ⏳ Pending)
- Each video has "Watch Video" button

✅ **Director/Writer Single-Video Submissions**:
- Show as single card with one video
- Watch button and AI status displayed inline
- Same status management as actors

✅ **Status Management**:
- Dropdown to change submission status (New, Reviewed, Shortlisted, Rejected)
- Color-coded badges for each status
- Stats pills at the top showing counts

✅ **Filtering**:
- Search by name, email, phone
- Filter by role (Actor, Director, Writer)
- Filter by status (New, Reviewed, Shortlisted, Rejected)

## Database Structure

The system uses the `submissions` table with these key columns:

- `id` - Primary key
- `name`, `email`, `phone` - Applicant info
- `role` - actor, director, or writer
- `submission_tag` - 'actor-dual', 'director-single', 'writer-single'
- `file_path` - First video (dialog for actors)
- `file_path_2` - Second video (song for actors only)
- `ai_status`, `ai_status_2` - AI moderation status for each video
- `ai_flagged`, `ai_flagged_2` - Boolean flags for each video
- `ai_notes`, `ai_notes_2` - AI notes for each video
- `video_id`, `video_id_2` - Links to videos table for YouTube publishing
- `status` - new, reviewed, shortlisted, rejected
- `submitted_at` - Timestamp

## Next Steps After UI Completion:

1. **Test the UI** - Make sure all submissions display correctly
2. **Test dual-video expandable dropdown** - Click "2 Videos Submitted" button to expand/collapse
3. **Test status changes** - Change submission status and verify it saves
4. **Test filtering** - Try searching and filtering by role/status
5. **YouTube Integration** - Verify both actor videos can be published to YouTube separately (requires linking video_id and video_id_2)

## File Locations:

- Main admin UI: `public/admin.php`
- Submission model: `app/models/Submission.php`
- Submission controller: `app/controllers/SubmissionController.php`
- Database migration: `database/migrations/010_actor_dual_upload.sql`

## Questions or Issues?

If the submission cards don't display:
- Check that `$submissionTableMissing` is false
- Verify `filteredSubmissions` array has data
- Check browser console for JavaScript errors
- Ensure Alpine.js is loaded

If actor dual videos don't show:
- Verify `submission_tag` = 'actor-dual'
- Verify `file_path_2` is not null
- Check that `x-collapse` directive is available (Alpine Collapse plugin)
