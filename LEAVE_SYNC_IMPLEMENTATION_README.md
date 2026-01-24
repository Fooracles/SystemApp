# Leave Sync Tracking Implementation

## Overview

This implementation provides comprehensive sync tracking for your leave management system, recording timestamps and status for both automatic (cron) and manual (refresh button) sync operations.

## Features

- **Universal Sync Tracking**: Records both cron job and manual refresh syncs
- **Status Monitoring**: Tracks success, failure, and skipped syncs
- **Error Logging**: Records error messages for failed syncs
- **Performance Metrics**: Tracks rows processed and sync counts
- **Frontend Integration**: Display sync status in the UI
- **Database Management**: Automatically creates required tables

## Files Included

### 1. `implement_leave_sync_tracking.php`
**Main implementation script**
- Creates `leave_sheet_sync` table
- Tests sync tracking functionality
- Generates all required files
- Provides comprehensive logging

### 2. `setup_leave_sync_config.php`
**Configuration setup script**
- Interactive configuration setup
- Creates necessary directories
- Generates configuration file
- Updates main script with your settings

### 3. Generated Files (after running main script)
- `ajax/get_sync_status.php` - AJAX endpoint for sync status
- `includes/leave_sync_functions.php` - Enhanced sync functions
- `assets/js/leave_sync_status.js` - Frontend JavaScript
- `leave_sync_config.php` - Configuration file
- `LEAVE_SYNC_IMPLEMENTATION_GUIDE.md` - Usage guide

## Quick Start

### Step 1: Configure the System
```bash
php setup_leave_sync_config.php
```

This will ask you for:
- Database connection details
- Google Sheet ID
- Tab name
- File directory paths

### Step 2: Run the Implementation
```bash
php implement_leave_sync_tracking.php
```

This will:
- Create the `leave_sheet_sync` table
- Test the sync tracking functionality
- Generate all required files
- Create implementation guide

### Step 3: Integrate with Your System

#### A. Update Your Manual Sync Scripts
```php
<?php
require_once 'includes/leave_sync_functions.php';

// Your existing sync logic here...

// After successful sync
updateLeaveSyncRecord(
    $conn, 
    $sheet_id, 
    $tab_name, 
    'manual', 
    'success', 
    null, 
    $u2_value, 
    $v2_value, 
    $rows_processed, 
    $records_synced
);
?>
```

#### B. Update Your Cron Job Scripts
```php
<?php
require_once 'includes/leave_sync_functions.php';

// Your existing sync logic here...

// After successful sync
updateLeaveSyncRecord(
    $conn, 
    $sheet_id, 
    $tab_name, 
    'cron', 
    'success', 
    null, 
    $u2_value, 
    $v2_value, 
    $rows_processed, 
    $records_synced
);
?>
```

#### C. Update Your Frontend
```html
<!-- Add sync status display -->
<div id="syncStatus" class="mb-3"></div>

<!-- Update your refresh button -->
<button id="refreshButton" onclick="refreshLeaveData()">
    <i class="fas fa-sync-alt me-1"></i>Refresh
</button>

<!-- Include the JavaScript -->
<script src="assets/js/leave_sync_status.js"></script>
```

## Database Schema

### `leave_sheet_sync` Table
```sql
CREATE TABLE leave_sheet_sync (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sheet_id VARCHAR(255) NOT NULL,
    tab_name VARCHAR(100) NOT NULL,
    last_synced DATETIME DEFAULT CURRENT_TIMESTAMP,
    sync_type ENUM('cron', 'manual', 'force') DEFAULT 'manual',
    rows_count INT DEFAULT 0,
    actuals_count INT DEFAULT 0,
    u2_value VARCHAR(50) DEFAULT NULL,
    v2_value VARCHAR(50) DEFAULT NULL,
    sync_status ENUM('success', 'failed', 'skipped') DEFAULT 'success',
    error_message TEXT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_sheet_tab (sheet_id, tab_name)
);
```

## API Functions

### `updateLeaveSyncRecord()`
Records sync information
```php
updateLeaveSyncRecord(
    $conn,           // Database connection
    $sheet_id,       // Google Sheet ID
    $tab_name,       // Tab name
    $sync_type,      // 'cron', 'manual', or 'force'
    $sync_status,    // 'success', 'failed', or 'skipped'
    $error_message,  // Error message (if any)
    $u2_value,       // U2 cell value
    $v2_value,       // V2 cell value
    $rows_count,     // Number of rows processed
    $actuals_count   // Number of records synced
);
```

### `getLeaveSyncStatus()`
Retrieves sync status information
```php
$sync_status = getLeaveSyncStatus($conn, $sheet_id, $tab_name);
```

## Frontend Integration

### JavaScript Functions
- `displaySyncStatus()` - Shows sync status in UI
- `refreshLeaveData()` - Enhanced refresh with sync tracking
- Auto-refresh every 30 seconds
- Toast notifications for sync status

### HTML Elements
- `#syncStatus` - Container for sync status display
- `#refreshButton` - Refresh button (update your existing button ID)

## Sync Types

### 1. Manual Sync (`sync_type = 'manual'`)
- Triggered by refresh button
- Always syncs (no U2/V2 check)
- Immediate user feedback
- Records manual sync timestamp

### 2. Cron Sync (`sync_type = 'cron'`)
- Triggered by scheduled cron job
- Smart sync with U2/V2 detection
- Background processing
- Records automatic sync timestamp

### 3. Force Sync (`sync_type = 'force'`)
- Manual override sync
- Bypasses all checks
- Emergency sync option
- Records force sync timestamp

## Status Types

### Success (`sync_status = 'success'`)
- Sync completed successfully
- Data updated in database
- Records processed count
- Green status indicator

### Failed (`sync_status = 'failed'`)
- Sync encountered errors
- Error message recorded
- No data updated
- Red status indicator

### Skipped (`sync_status = 'skipped'`)
- No changes detected
- U2/V2 values unchanged
- No processing needed
- Yellow status indicator

## Monitoring and Logging

### Log Files
- `leave_sync_implementation.log` - Implementation process
- `leave_sync.log` - Runtime sync operations
- Database records in `leave_sheet_sync` table

### Monitoring Queries
```sql
-- Get latest sync status
SELECT * FROM leave_sheet_sync 
WHERE sheet_id = 'your_sheet_id' 
ORDER BY last_synced DESC LIMIT 1;

-- Get sync history
SELECT last_synced, sync_type, sync_status, rows_count 
FROM leave_sheet_sync 
WHERE sheet_id = 'your_sheet_id' 
ORDER BY last_synced DESC;

-- Get failed syncs
SELECT * FROM leave_sheet_sync 
WHERE sync_status = 'failed' 
ORDER BY last_synced DESC;
```

## Troubleshooting

### Common Issues

1. **Database Connection Failed**
   - Check database credentials in `leave_sync_config.php`
   - Ensure database server is running
   - Verify database name exists

2. **Table Creation Failed**
   - Check database permissions
   - Ensure MySQL version supports the syntax
   - Check for existing table conflicts

3. **AJAX Endpoint Not Working**
   - Verify file paths in configuration
   - Check web server permissions
   - Ensure PHP is enabled

4. **Frontend Not Displaying Status**
   - Check JavaScript console for errors
   - Verify AJAX endpoint URL
   - Ensure jQuery is loaded

### Debug Mode
Enable debug logging by adding to your scripts:
```php
error_reporting(E_ALL);
ini_set('display_errors', 1);
```

## Performance Considerations

### Database Indexes
The table includes optimized indexes:
- `unique_sheet_tab` - Fast lookups by sheet and tab
- `last_synced` - Efficient timestamp queries
- `sync_type` - Filter by sync type
- `sync_status` - Filter by status

### Query Optimization
- Use prepared statements
- Limit result sets for history queries
- Archive old sync records periodically

## Security Considerations

### Input Validation
- All inputs are sanitized
- Prepared statements prevent SQL injection
- Error messages don't expose sensitive data

### Access Control
- Restrict AJAX endpoints to authenticated users
- Log all sync operations for audit
- Monitor for unusual sync patterns

## Maintenance

### Regular Tasks
1. **Monitor sync logs** for errors
2. **Archive old records** (keep last 30 days)
3. **Check sync frequency** (should be regular
4. **Verify Google Sheets access** periodically

### Cleanup Script
```sql
-- Archive old sync records (older than 30 days)
DELETE FROM leave_sheet_sync 
WHERE last_synced < DATE_SUB(NOW(), INTERVAL 30 DAY);
```

## Support

For issues or questions:
1. Check the log files for detailed error information
2. Verify configuration settings
3. Test database connectivity
4. Review the implementation guide

## Version History

- **v1.0**: Initial implementation with comprehensive sync tracking
- Based on FMS-5.2-App leave system logic
- Supports all sync scenarios (cron, manual, force)
- Complete frontend integration
- Comprehensive error handling and logging
