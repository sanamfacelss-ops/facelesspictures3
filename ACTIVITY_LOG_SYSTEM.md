# Activity Log System

## Overview
The activity log system tracks all user and admin actions in Faceless Pictures 3 with automatic cleanup after 90 days and modern bulk delete capabilities.

## Features Implemented

### 1. **Automatic 90-Day Cleanup**
- Activity logs older than 90 days are automatically deleted
- Runs via stored procedure: `cleanup_old_activity_logs()`
- Can be triggered manually from admin panel or via cron job

### 2. **Database Schema** 
Migration file: `database/migrations/013_add_activity_log.sql`

```sql
CREATE TABLE activity_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    action VARCHAR(100) NOT NULL,
    entity_type ENUM('video', 'script', 'season', 'user', 'submission', 'system', 'auth', 'settings'),
    entity_id INT DEFAULT NULL,
    description TEXT NOT NULL,
    ip_address VARCHAR(45),
    user_agent TEXT,
    metadata JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    ...indexes...
)
```

### 3. **ActivityLog Model**
File: `app/models/ActivityLog.php`

**Main Methods:**
- `log()` - Log a new activity
- `getAll()` - Get all logs with pagination and filtering
- `getByUser()` - Get logs for specific user
- `getByEntity()` - Get logs for specific entity
- `delete()` - Delete single log entry
- `bulkDelete()` - Delete multiple logs at once
- `cleanupOldLogs()` - Delete logs older than 90 days
- `getStats()` - Get activity statistics

### 4. **AdminController API Endpoints**

#### Get Activity Logs
```
GET /api/admin/activity-logs?page=1&per_page=50&filter=video
```
Returns paginated activity logs with optional filtering.

#### Delete Single Log
```
POST /api/admin/activity-logs/delete/:id
Body: { csrf_token: "..." }
```
Permanently deletes a single activity log entry.

#### Bulk Delete Logs
```
POST /api/admin/activity-logs/bulk-delete
Body: { 
  log_ids: [1, 2, 3, ...],
  csrf_token: "..." 
}
```
Permanently deletes multiple activity logs at once.

#### Manual Cleanup (90+ days)
```
POST /api/admin/activity-logs/cleanup
Body: { csrf_token: "..." }
```
Manually triggers deletion of logs older than 90 days.

### 5. **Automated Cleanup via Cron**
File: `cron/activity-cleanup.php`

**Setup Instructions:**

#### Windows (Task Scheduler)
```cmd
php C:\path\to\facelesspictures3\cron\activity-cleanup.php
```
Schedule to run daily at 2:00 AM

#### Linux (Crontab)
```bash
# Daily at 2:00 AM
0 2 * * * /usr/bin/php /path/to/facelesspictures3/cron/activity-cleanup.php >> /path/to/facelesspictures3/logs/cron.log 2>&1
```

### 6. **Helper Function**
File: `app/helpers/functions.php`

```php
// Log an activity
log_activity(
    userId: 1,
    action: 'create',
    entityType: 'video',
    entityId: 123,
    description: 'User created new video',
    metadata: ['title' => 'My Video', 'duration' => 120]
);
```

## Usage Examples

### Logging Activities in Your Code

```php
// When user uploads a video
log_activity(
    $userId,
    'upload',
    'video',
    $videoId,
    "User {$userName} uploaded video: {$videoTitle}"
);

// When admin deletes a submission
log_activity(
    $_SESSION['user_id'],
    'delete',
    'submission',
    $submissionId,
    "Admin deleted submission #{$submissionId}",
    ['reason' => 'duplicate', 'ip' => $_SERVER['REMOTE_ADDR']]
);

// When user logs in
log_activity(
    $userId,
    'login',
    'auth',
    null,
    "User {$userName} logged in successfully"
);
```

### Admin Panel Integration

The admin panel should have an "Activity Logs" section with:

1. **Table view** showing:
   - Timestamp
   - User name/email
   - Action performed
   - Entity type & ID
   - Description
   - IP address

2. **Filters**:
   - Search by action, description, or entity type
   - Date range filter
   - User filter

3. **Actions**:
   - Individual delete button (trash icon) with confirmation modal
   - Checkbox for bulk selection
   - "Delete Selected" button for bulk delete with confirmation
   - "Cleanup Old Logs" button to manually run 90-day cleanup
   - Stats display (total logs, unique users, date range)

4. **Confirmation Modals**:
   ```javascript
   // Example confirmation for delete
   if (confirm('This will permanently delete the selected activity log(s). This action cannot be undone. Continue?')) {
       // Proceed with delete
   }
   ```

## Entity Types
- `video` - Video uploads, edits, deletions
- `script` - Script management actions
- `season` - Season management
- `user` - User account actions
- `submission` - Public audition submissions
- `system` - System-level events
- `auth` - Authentication events (login, logout, password reset)
- `settings` - Settings changes

## Common Actions
- `create` - Entity creation
- `update` - Entity modification
- `delete` - Entity deletion
- `upload` - File uploads
- `approve` - Approval actions
- `reject` - Rejection actions
- `login` - User login
- `logout` - User logout
- `view` - View/access events
- `export` - Data exports

## Migration Instructions

1. **Run the migration:**
   ```bash
   php database/run-migrations.php
   ```

2. **Verify table creation:**
   ```sql
   SHOW TABLES LIKE 'activity_log';
   DESCRIBE activity_log;
   ```

3. **Test the stored procedure:**
   ```sql
   CALL cleanup_old_activity_logs();
   ```

4. **Set up cron job** (see above for platform-specific instructions)

## Security Considerations

1. **Authentication Required**: All admin endpoints require admin authentication
2. **CSRF Protection**: All POST endpoints require valid CSRF token
3. **Input Validation**: All inputs are validated and sanitized
4. **No Soft Deletes**: Activity logs are permanently deleted (hard delete)
5. **IP Tracking**: Records IP addresses for audit trail
6. **Metadata**: JSON metadata field for flexible additional context

## Performance Notes

- Indexed fields: `user_id`, `action`, `entity_type`, `entity_id`, `created_at`
- Automatic cleanup prevents table bloat
- Pagination limits query load
- Consider archiving instead of deleting for compliance requirements

## Future Enhancements

- Export activity logs to CSV/JSON
- Advanced filtering (date ranges, multiple users, etc.)
- Activity log dashboard with charts
- Real-time activity feed
- Email notifications for critical actions
- Archive old logs to separate table instead of deletion
- Activity log search with full-text indexing

## Troubleshooting

### Migration fails
```bash
# Check if table already exists
mysql> SHOW TABLES LIKE 'activity_log';

# Drop and recreate if needed
mysql> DROP TABLE IF EXISTS activity_log;
mysql> SOURCE database/migrations/013_add_activity_log.sql;
```

### Cron job not running
```bash
# Test manually
php cron/activity-cleanup.php

# Check cron logs
tail -f /var/log/syslog | grep CRON  # Linux
# Check Task Scheduler History in Windows
```

### Logs not appearing
```php
// Check if table exists
$activityLog = new \App\Models\ActivityLog();
var_dump($activityLog->tableExists());

// Test logging
$result = log_activity(1, 'test', 'system', null, 'Test log entry');
var_dump($result);
```

## Related Files
- `database/migrations/013_add_activity_log.sql` - Database schema
- `app/models/ActivityLog.php` - Model class
- `app/controllers/AdminController.php` - API endpoints
- `app/helpers/functions.php` - Helper function
- `cron/activity-cleanup.php` - Automated cleanup script
