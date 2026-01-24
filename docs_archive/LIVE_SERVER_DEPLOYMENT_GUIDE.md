# Leave System - Live Server Deployment Guide

## 🎯 **COMPLETE DEPLOYMENT CHECKLIST**

### **Step 1: Upload Necessary Files**

#### **Core Leave System Files:**
```
pages/leave_request.php                    # Main leave request page
assets/js/leave_request.js                # Frontend JavaScript
ajax/leave_auto_sync.php                   # Manual sync endpoint
scripts/cron_leave_sync.php               # Automatic sync script
```

#### **Database & Configuration:**
```
includes/config.php                        # Database configuration
includes/db_schema.php                     # Database schema definitions
includes/db_functions.php                  # Database utility functions
includes/google_sheets_client.php         # Google Sheets API client
```

#### **Google Sheets Integration:**
```
credentials.json                          # Google Sheets API credentials
vendor/                                    # Google API PHP client (entire folder)
```

#### **Database Setup:**
```
setup_leave_database.php                  # Database setup script
```

#### **Logs Directory:**
```
logs/                                      # Create this directory
```

---

### **Step 2: Database Setup**

1. **Update database credentials in `includes/config.php`:**
   ```php
   define('DB_SERVER', 'your_live_server');
   define('DB_USERNAME', 'your_username');
   define('DB_PASSWORD', 'your_password');
   define('DB_NAME', 'your_database_name');
   ```

2. **Run database setup script:**
   ```bash
   php setup_leave_database.php
   ```

3. **Verify tables created:**
   - `Leave_request`
   - `leave_sheet_sync`
   - `leave_status_actions`
   - `users` (if not exists)
   - `departments` (if not exists)
   - `holidays` (if not exists)

---

### **Step 3: Google Sheets Setup**

1. **Upload `credentials.json`** to your server
2. **Verify Google Sheets API is enabled**
3. **Test connection** by running:
   ```bash
   php scripts/cron_leave_sync.php
   ```

---

### **Step 4: Cron Job Setup**

#### **For Linux/Mac:**
```bash
# Add to crontab (crontab -e)
*/15 * * * * cd /path/to/your/leave/system && php scripts/cron_leave_sync.php >> logs/cron_sync.log 2>&1
```

#### **For Windows:**
```cmd
# Create task to run every 15 minutes
schtasks /create /tn "Leave Sync Smart" /tr "php \"C:\path\to\your\leave\system\scripts\cron_leave_sync.php\"" /sc minute /mo 15 /ru SYSTEM
```

---

### **Step 5: File Permissions**

```bash
# Set proper permissions
chmod 755 scripts/cron_leave_sync.php
chmod 755 ajax/leave_auto_sync.php
chmod 777 logs/
chmod 644 credentials.json
```

---

### **Step 6: Testing**

1. **Test manual sync:**
   - Visit your leave request page
   - Click refresh button
   - Check `logs/leave_sync.log`

2. **Test automatic sync:**
   ```bash
   php scripts/cron_leave_sync.php
   ```
   - Check `logs/cron_sync.log`

3. **Verify U2/V2 detection:**
   - Look for "SYNC TRIGGERED" or "SKIP SYNC" messages

---

## 📁 **FINAL FILE STRUCTURE**

```
your-live-server/
├── pages/
│   └── leave_request.php
├── assets/js/
│   └── leave_request.js
├── ajax/
│   └── leave_auto_sync.php
├── scripts/
│   └── cron_leave_sync.php
├── includes/
│   ├── config.php
│   ├── db_schema.php
│   ├── db_functions.php
│   └── google_sheets_client.php
├── credentials.json
├── vendor/ (Google API client)
├── logs/ (directory)
├── setup_leave_database.php
└── LIVE_SERVER_DEPLOYMENT_GUIDE.md
```

---

## ✅ **DEPLOYMENT COMPLETE!**

Your leave system is now ready with:
- ✅ Manual sync (refresh button)
- ✅ Automatic sync (cron job)
- ✅ Smart U2/V2 detection
- ✅ Complete database setup
- ✅ Google Sheets integration
- ✅ Proper logging and monitoring

---

## 🔧 **TROUBLESHOOTING**

### **Common Issues:**

1. **Database connection failed:**
   - Check credentials in `includes/config.php`
   - Verify database server is running

2. **Google Sheets API error:**
   - Check `credentials.json` file
   - Verify API is enabled

3. **Cron job not running:**
   - Check cron service status
   - Verify file permissions
   - Check cron logs

4. **Sync not working:**
   - Check `logs/cron_sync.log`
   - Verify U2/V2 values in Google Sheet
   - Test manual sync first

---

## 📞 **SUPPORT**

If you encounter any issues:
1. Check log files for errors
2. Verify all files are uploaded correctly
3. Test each component individually
4. Ensure proper file permissions

**Your leave system is now production-ready!**
