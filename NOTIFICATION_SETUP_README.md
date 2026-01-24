# Notification System Setup Guide

## Overview
This document explains how the notification system works and how to set it up.

## Notification Types Implemented

### 1. Task Delay Warnings (5 minutes before delay)
- **Trigger**: Automatically checked when `my_task.php` page loads
- **Also**: Can be run via cron job (`cron/notification_checks.php`)
- **Checks**: Delegation tasks, Checklist tasks, and FMS tasks
- **Notification**: Sent to the task assignee 5 minutes before planned time

### 2. Day Special Notifications (Birthdays & Work Anniversaries)
- **Trigger**: Automatically checked once per day when any page loads (via `header.php`)
- **Also**: Can be run via cron job (`cron/notification_checks.php`)
- **Checks**: User birthdays and work anniversaries
- **Notification**: Sent to all users when someone has a birthday or work anniversary

### 3. Leave Request Notifications
- **Trigger**: Automatically when a new leave request is synced from Google Sheets
- **Files**: `ajax/leave_auto_sync.php` and `scripts/smart_cron_leave_sync.php`
- **Recipients**: 
  - Direct manager of the employee
  - All admins
  - Managers who have the employee in their team
- **Notification**: Includes approve/reject action buttons

### 4. Notes Reminders
- **Trigger**: 
  - When `checkReminders()` is called via AJAX (`ajax/notes_handler.php`)
  - Automatically via cron job (`cron/notification_checks.php`)
- **Checks**: Notes with reminder dates that are due (within 5 minutes)
- **Notification**: Sent to note owner when reminder time is reached

## Setup Instructions

### Option 1: Automatic Checks (Recommended)
The system automatically checks for:
- **Task delay warnings**: Every time `my_task.php` loads
- **Day special events**: Once per day when any page loads (via `header.php`)
- **Leave requests**: When synced from Google Sheets
- **Notes reminders**: When `checkReminders()` is called via AJAX

### Option 2: Cron Job Setup (For More Reliable Notifications)

For more reliable and timely notifications, set up a cron job to run `cron/notification_checks.php` every minute:

#### Windows (Task Scheduler)
1. Open Task Scheduler
2. Create Basic Task
3. Set trigger: Daily, repeat every 1 minute
4. Action: Start a program
5. Program: `php.exe`
6. Arguments: `C:\xampp\htdocs\app-v4.8\cron\notification_checks.php`
7. Start in: `C:\xampp\htdocs\app-v4.8`

#### Linux/Mac (Crontab)
```bash
# Edit crontab
crontab -e

# Add this line to run every minute:
* * * * * /usr/bin/php /path/to/app-v4.8/cron/notification_checks.php >> /path/to/app-v4.8/logs/notification_cron.log 2>&1
```

## Files Modified/Created

### Created Files:
- `cron/notification_checks.php` - Cron job script for periodic checks

### Modified Files:
- `includes/notification_triggers.php` - Enhanced notification trigger functions
- `pages/my_task.php` - Added task delay warning check on page load
- `includes/header.php` - Added day special notification check (once per day)
- `ajax/notes_handler.php` - Updated `checkReminders()` to create notifications
- `ajax/leave_auto_sync.php` - Added notification trigger for new leave requests
- `scripts/smart_cron_leave_sync.php` - Added notification trigger for new leave requests

## Testing

To test notifications, you can:

1. **Task Delay Warnings**: Create a task with planned time 5 minutes from now
2. **Day Special**: Set a user's birthday or work anniversary to today's date
3. **Leave Requests**: Submit a new leave request via Google Sheets sync
4. **Notes Reminders**: Create a note with reminder date set to current time

## Troubleshooting

- **Notifications not appearing**: Check that the `notifications` table exists and has all required columns
- **Cron job not running**: Check file permissions and PHP path in cron command
- **Leave notifications not working**: Ensure `getManagerTeamMembers()` function is available (included in `dashboard_components.php`)

## Notes

- Task delay warnings are checked for delegation, checklist, and FMS tasks
- Day special notifications are only created once per day (cached in session)
- Leave request notifications are only sent for new requests with pending status
- Notes reminders are checked when the reminder date is within 5 minutes

