
# Leave System Deployment Files

## 📁 Complete File List for Live Server Deployment

### **Core Application Files**
```
index.php
login.php
logout.php
```

### **Configuration Files**
```
includes/
├── config.php
├── db_functions.php
├── db_schema.php
├── functions.php
├── google_sheets_client.php
├── header.php
├── footer.php
└── sidebar.php
```

### **Leave System Pages**
```
pages/
├── leave_request.php
├── holiday_list.php
└── profile.php
```

### **AJAX Endpoints**
```
ajax/
├── leave_auto_sync.php
├── leave_fetch_pending.php
├── leave_fetch_totals.php
├── leave_metrics.php
├── leave_status_action.php
├── holiday_handler.php
└── get_notifications.php
```

### **JavaScript Files**
```
assets/js/
├── leave_request.js
├── script.js
└── table-sorter.js
```

### **CSS Files**
```
assets/css/
├── leave_request.css
├── style.css
├── table-sorter.css
├── tables.css
└── theme.css
```

### **Images**
```
assets/images/
├── logo.png
├── favicon-16x16.png
├── favicon-32x32.png
├── apple-touch-icon.png
└── android-chrome-192x192.png
```

### **Cron Job Scripts**
```
scripts/
├── cron_leave_sync.php
└── smart_cron_leave_sync.php
```

### **Database Setup Script**
```
setup_leave_database.php
```

### **Logs Directory**
```
logs/
├── leave_sync.log
├── cron_sync.log
└── ajax_errors.log
```

### **Vendor Dependencies**
```
vendor/
├── autoload.php
├── composer/
├── firebase/
├── google/
├── guzzlehttp/
├── monolog/
├── paragonie/
├── phpseclib/
├── psr/
├── ralouphie/
└── symfony/
```

### **Composer Files**
```
composer.json
composer.lock
```

### **Service Worker**
```
sw.js
```

## 🚀 Deployment Steps

1. **Upload all files** to your live server
2. **Run the database setup script**: `setup_leave_database.php`
3. **Configure database credentials** in `includes/config.php`
4. **Set up cron job** for automatic sync:
   ```bash
   */15 * * * * /usr/bin/php /path/to/your/project/scripts/cron_leave_sync.php
   ```
5. **Set proper permissions** for logs directory:
   ```bash
   chmod 755 logs/
   chmod 644 logs/*.log
   ```

## 📋 Database Tables Required

- `Leave_request` - Main leave requests table
- `leave_status_actions` - Leave status change tracking
- `leave_sheet_sync` - Google Sheets sync tracking
- `users` - User management
- `departments` - Department information
- `holidays` - Holiday calendar
- `password_reset_requests` - Password reset functionality

## ⚙️ Configuration Requirements

1. **Database Connection**: Update `includes/config.php`
2. **Google Sheets API**: Configure credentials in `includes/google_sheets_client.php`
3. **Cron Job**: Set up automatic sync every 15 minutes
4. **File Permissions**: Ensure logs directory is writable