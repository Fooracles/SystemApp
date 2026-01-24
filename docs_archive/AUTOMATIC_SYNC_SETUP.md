# Automatic Leave Sync Setup Guide

## 🎯 Overview

This system provides **two sync options**:
1. **Manual Sync**: Refresh button in the UI
2. **Automatic Sync**: Cron job runs every 10-15 minutes

## 📁 Files Created

- `scripts/cron_leave_sync.php` - Main cron sync script
- `scripts/setup_cron_job.php` - Linux/Mac cron setup helper
- `scripts/setup_windows_scheduler.bat` - Windows Task Scheduler setup
- `logs/cron_sync.log` - Cron sync logs (separate from manual sync logs)

## 🚀 Setup Instructions

### Option 1: Linux/Mac (Cron Job)

1. **Run the setup script:**
   ```bash
   php scripts/setup_cron_job.php
   ```

2. **Add cron job:**
   ```bash
   crontab -e
   ```

3. **Add one of these lines:**
   ```bash
   # Every 10 minutes
   */10 * * * * cd /path/to/FMS-5.2-App && php scripts/cron_leave_sync.php >> logs/cron_sync.log 2>&1
   
   # Every 15 minutes
   */15 * * * * cd /path/to/FMS-5.2-App && php scripts/cron_leave_sync.php >> logs/cron_sync.log 2>&1
   ```

4. **Verify cron job:**
   ```bash
   crontab -l
   ```

### Option 2: Windows (Task Scheduler)

1. **Run the setup script:**
   ```cmd
   scripts\setup_windows_scheduler.bat
   ```

2. **Manual setup in Task Scheduler:**
   - Open `taskschd.msc`
   - Create Basic Task
   - Name: "Leave Sync Automatic"
   - Trigger: Daily or as needed
   - Action: Start a program
   - Program: `php`
   - Arguments: `"C:\xampp\htdocs\FMS-5.2-App\scripts\cron_leave_sync.php"`
   - Start in: `C:\xampp\htdocs\FMS-5.2-App`

3. **Or use command line:**
   ```cmd
   schtasks /create /tn "Leave Sync Automatic" /tr "php \"C:\xampp\htdocs\FMS-5.2-App\scripts\cron_leave_sync.php\"" /sc minute /mo 15 /ru SYSTEM
   ```

## 📊 Monitoring

### Log Files

- **Manual Sync**: `logs/leave_sync.log`
- **Automatic Sync**: `logs/cron_sync.log`

### Log Entries

**Manual Sync:**
```
[2025-10-22 13:29:00] UPDATED: Leave_Request-55 - Tejas (Data refreshed)
```

**Automatic Sync:**
```
[2025-10-22 13:29:00] CRON: UPDATED: Leave_Request-55 - Tejas (Data refreshed)
```

### Check Sync Status

```bash
# Check manual sync logs
tail -f logs/leave_sync.log

# Check automatic sync logs
tail -f logs/cron_sync.log

# Check both logs
tail -f logs/leave_sync.log logs/cron_sync.log
```

## 🔧 Manual Testing

```bash
# Test cron script manually
php scripts/cron_leave_sync.php

# Test manual sync (via browser)
# Click refresh button in leave request page
```

## ⚙️ Configuration

### Sync Frequency Options

- **Every 5 minutes**: `*/5 * * * *`
- **Every 10 minutes**: `*/10 * * * *`
- **Every 15 minutes**: `*/15 * * * *`
- **Every 30 minutes**: `*/30 * * * *`

### Custom Schedule Examples

```bash
# Every weekday at 9 AM
0 9 * * 1-5 cd /path/to/FMS-5.2-App && php scripts/cron_leave_sync.php

# Every hour during business hours (9 AM - 6 PM)
0 9-18 * * * cd /path/to/FMS-5.2-App && php scripts/cron_leave_sync.php

# Every 2 hours
0 */2 * * * cd /path/to/FMS-5.2-App && php scripts/cron_leave_sync.php
```

## 🐛 Troubleshooting

### Common Issues

1. **Cron job not running:**
   - Check if cron service is running: `systemctl status cron`
   - Check cron logs: `tail -f /var/log/cron`
   - Verify file permissions: `chmod +x scripts/cron_leave_sync.php`

2. **Permission errors:**
   - Ensure PHP has access to Google Sheets credentials
   - Check database connection permissions
   - Verify log directory is writable

3. **Google Sheets API errors:**
   - Check credentials.json file
   - Verify Google Sheets API is enabled
   - Check API quotas and limits

### Debug Commands

```bash
# Test database connection
php -r "echo mysqli_connect('localhost', 'root', '', 'task_management') ? 'Connected' : 'Failed';"

# Test Google Sheets API
php scripts/cron_leave_sync.php

# Check PHP error logs
tail -f /var/log/php_errors.log
```

## 📈 Performance

### Sync Statistics

The cron script logs detailed statistics:
- Total records processed
- New records inserted
- Existing records updated
- Errors encountered

### Example Output

```
[2025-10-22 13:29:00] CRON: === STARTING AUTOMATIC CRON SYNC ===
[2025-10-22 13:29:02] CRON: Google Sheets API returned 1027 data rows
[2025-10-22 13:29:05] CRON: === CRON SYNC COMPLETED ===
[2025-10-22 13:29:05] CRON: Total records processed: 1027
[2025-10-22 13:29:05] CRON: Records synced: 1027
[2025-10-22 13:29:05] CRON: New records inserted: 0
[2025-10-22 13:29:05] CRON: Existing records updated: 1027
```

## ✅ Success Criteria

- ✅ Manual refresh button works
- ✅ Automatic cron job runs every 10-15 minutes
- ✅ Both sync methods update the same database
- ✅ Logs show successful sync operations
- ✅ New records from Google Sheets appear automatically
- ✅ Status changes are reflected in the database

## 🎉 You're All Set!

Your leave sync system now has both manual and automatic sync capabilities!
