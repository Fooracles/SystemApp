# Leave System - Complete File Documentation

## 📋 **OVERVIEW**
This document provides a comprehensive list of ALL files used in the Leave Management System, including their purposes, dependencies, and relationships. This is the complete reference for understanding the entire leave system architecture.

---

## 🗂️ **FILE STRUCTURE BY CATEGORY**

### **1. CORE APPLICATION FILES**
```
├── index.php                          # Main application entry point
├── login.php                          # User authentication
├── logout.php                         # User logout functionality
└── sw.js                             # Service Worker for PWA functionality
```

### **2. CONFIGURATION & DATABASE FILES**
```
includes/
├── config.php                        # Database configuration and constants
├── db_schema.php                     # Database table definitions
├── db_functions.php                  # Database utility functions
├── functions.php                     # General utility functions
├── google_sheets_client.php          # Google Sheets API client
├── header.php                        # Common header template
├── footer.php                        # Common footer template
└── sidebar.php                       # Navigation sidebar template
```

### **3. LEAVE SYSTEM PAGES**
```
pages/
├── leave_request.php                 # Main leave request management page
├── holiday_list.php                  # Holiday management page
└── profile.php                       # User profile management
```

### **4. AJAX ENDPOINTS (Backend API)**
```
ajax/
├── leave_auto_sync.php               # Manual leave data synchronization
├── leave_fetch_pending.php           # Fetch pending leave requests
├── leave_fetch_totals.php            # Fetch total leave requests
├── leave_metrics.php                 # Fetch leave metrics and statistics
├── leave_status_action.php           # Approve/Reject leave requests
├── holiday_handler.php               # Holiday management operations
└── get_notifications.php             # Fetch system notifications
```

### **5. FRONTEND ASSETS**

#### **JavaScript Files**
```
assets/js/
├── leave_request.js                  # Leave system frontend logic
├── script.js                         # General application scripts
└── table-sorter.js                   # Table sorting functionality
```

#### **CSS Files**
```
assets/css/
├── leave_request.css                 # Leave system specific styles
├── style.css                         # Global application styles
├── table-sorter.css                  # Table sorting styles
├── tables.css                        # Table styling
└── theme.css                         # Theme and color schemes
```

#### **Images & Icons**
```
assets/images/
├── logo.png                          # Application logo
├── favicon-16x16.png                 # Favicon 16x16
├── favicon-32x32.png                 # Favicon 32x32
├── apple-touch-icon.png              # Apple touch icon
├── android-chrome-192x192.png        # Android chrome icon 192x192
└── android-chrome-512x512.png        # Android chrome icon 512x512
```

### **6. SYNCHRONIZATION SCRIPTS**
```
scripts/
├── cron_leave_sync.php               # Main cron job for automatic sync
├── smart_cron_leave_sync.php         # Smart sync with U2/V2 detection
├── setup_cron_job.php                # Cron job setup utility
├── setup_windows_scheduler.bat       # Windows task scheduler setup
├── update_leave_requests.php         # Legacy update script
├── update_leave_requests_minimal.php  # Minimal update script
└── update_leave_requests_simple.php   # Simple update script
```

### **7. DATABASE SETUP & MIGRATION**
```
├── setup_leave_database.php          # Database setup script
├── complete_database_schema.sql      # Complete database schema
├── database.sql                      # Core database structure
├── database_schema_notes_urls.sql    # Additional schema for notes/URLs
└── fix_database_enum.php             # Database enum fixes
```

### **8. TESTING & DEBUGGING FILES**
```
├── test_sync_debug.php               # Sync debugging utility
├── test_direct_sync.php              # Direct sync testing
├── test_manual_sync.php              # Manual sync testing
├── test_cron_script.php              # Cron script testing
├── test_database_connection.php      # Database connection testing
├── debug_ajax_test.php               # AJAX endpoint testing
├── test_leave_modal.html             # Leave modal testing
└── sync_test_output.txt              # Sync test output logs
```

### **9. LOG FILES**
```
logs/
├── leave_sync.log                    # Manual sync operations log
├── cron_sync.log                     # Automatic sync operations log
├── smart_sync.log                    # Smart sync operations log
├── ajax_errors.log                   # AJAX error logging
├── db_operations.log                  # Database operations log
└── manual_status_sync.log            # Manual status sync log
```

### **10. DEPLOYMENT & CONFIGURATION**
```
├── credentials.json                  # Google Sheets API credentials
├── composer.json                     # PHP dependencies
├── composer.lock                     # Locked dependency versions
└── vendor/                           # Composer dependencies
    ├── autoload.php
    ├── google/                       # Google API client
    ├── firebase/                     # Firebase SDK
    ├── guzzlehttp/                   # HTTP client
    ├── monolog/                      # Logging library
    └── [other dependencies]
```

### **11. DOCUMENTATION FILES**
```
├── LEAVE_SYSTEM_IMPLEMENTATION_GUIDE.txt    # Implementation guide
├── LEAVE_SYSTEM_DEPLOYMENT_FILES.md          # Deployment file list
├── LIVE_SERVER_DEPLOYMENT_GUIDE.md           # Live server setup
├── AUTOMATIC_SYNC_SETUP.md                   # Automatic sync setup
├── SMART_SYNC_IMPLEMENTATION.md              # Smart sync documentation
├── NEW_FEATURES_README.md                    # New features documentation
├── PROJECT_CLEANUP_REPORT.md                 # Project cleanup report
└── DATE_FIX_SOLUTION.md                      # Date handling fixes
```

### **12. UTILITY & MAINTENANCE FILES**
```
├── complete_leave_sync_setup.php     # Complete sync setup
├── complete_duplication_fix.php      # Duplication fix utility
├── implement_proper_duplicate_prevention.php # Duplicate prevention
├── fix_duplication_issues.php         # Fix duplication issues
├── create_url_tables.php             # URL tables creation
├── update_fms_tasks_web.php          # FMS tasks web update
└── log.txt                           # General application log
```

---

## 🔗 **FILE DEPENDENCIES & RELATIONSHIPS**

### **Core Dependencies**
```
config.php
├── db_functions.php
├── db_schema.php
└── google_sheets_client.php
    └── credentials.json
```

### **Page Dependencies**
```
leave_request.php
├── includes/header.php
├── includes/sidebar.php
├── includes/footer.php
├── assets/css/leave_request.css
├── assets/js/leave_request.js
└── ajax/leave_*.php (all AJAX endpoints)
```

### **Sync System Dependencies**
```
cron_leave_sync.php
├── includes/config.php
├── includes/google_sheets_client.php
├── credentials.json
└── logs/cron_sync.log
```

### **AJAX Endpoint Dependencies**
```
ajax/leave_*.php
├── includes/config.php
├── includes/db_functions.php
└── includes/google_sheets_client.php (for sync endpoints)
```

---

## 📊 **DATABASE TABLES USED**

### **Primary Tables**
- `Leave_request` - Main leave request data
- `leave_status_actions` - Audit trail for status changes
- `leave_sheet_sync` - Sync tracking and metadata
- `users` - User management
- `departments` - Department information
- `holidays` - Holiday calendar

### **Supporting Tables**
- `user_notes` - User notes system
- `admin_urls` - Admin URL management
- `personal_urls` - Personal URL management

---

## 🔄 **SYSTEM WORKFLOW**

### **1. Data Flow**
```
Google Sheets → leave_auto_sync.php → Leave_request table → leave_request.php → User Interface
```

### **2. Sync Process**
```
U2/V2 Detection → Smart Sync Logic → Database Update → Logging
```

### **3. User Actions**
```
User Interface → AJAX Endpoints → Database Operations → Response
```

---

## 🛠️ **DEVELOPMENT & MAINTENANCE**

### **Key Files for Development**
1. **Frontend Changes**: `assets/js/leave_request.js`, `assets/css/leave_request.css`
2. **Backend Changes**: `ajax/leave_*.php`, `pages/leave_request.php`
3. **Database Changes**: `includes/db_schema.php`, `setup_leave_database.php`
4. **Sync Logic**: `scripts/cron_leave_sync.php`, `ajax/leave_auto_sync.php`

### **Testing Files**
- `test_*.php` - Individual component testing
- `debug_*.php` - Debugging utilities
- `logs/` - System operation logs

### **Deployment Files**
- `LIVE_SERVER_DEPLOYMENT_GUIDE.md` - Production deployment
- `AUTOMATIC_SYNC_SETUP.md` - Sync setup
- `setup_leave_database.php` - Database initialization

---

## 📈 **PERFORMANCE & MONITORING**

### **Log Files for Monitoring**
- `logs/leave_sync.log` - Manual sync operations
- `logs/cron_sync.log` - Automatic sync operations
- `logs/smart_sync.log` - Smart sync operations
- `logs/ajax_errors.log` - AJAX error tracking

### **Key Metrics to Monitor**
- Sync frequency and success rates
- Database query performance
- Google Sheets API usage
- User interaction patterns

---

## 🔧 **CONFIGURATION REQUIREMENTS**

### **Required Environment Variables**
- Database connection settings in `config.php`
- Google Sheets API credentials in `credentials.json`
- Proper file permissions for `logs/` directory
- Cron job setup for automatic sync

### **Dependencies**
- PHP 7.4+ with MySQLi extension
- Google Sheets API access
- Composer dependencies (vendor folder)
- Web server with PHP support

---

## 📝 **NOTES**

1. **File Count**: This system uses approximately **50+ files** across different categories
2. **Core Logic**: The main business logic is in `ajax/` endpoints and `pages/leave_request.php`
3. **Sync System**: Uses U2/V2 cell detection for efficient synchronization
4. **User Interface**: Modern responsive design with Bootstrap components
5. **Database**: MySQL with proper indexing and foreign key relationships
6. **Logging**: Comprehensive logging system for debugging and monitoring

---

## ✅ **DEPLOYMENT CHECKLIST**

### **Essential Files for Production**
- All files in `pages/`, `ajax/`, `assets/`, `includes/`
- `scripts/cron_leave_sync.php`
- `setup_leave_database.php`
- `credentials.json`
- `vendor/` directory
- `logs/` directory (create if not exists)

### **Optional Files**
- Test files (`test_*.php`)
- Debug files (`debug_*.php`)
- Documentation files (`.md` files)
- Utility scripts in root directory

---

*This documentation covers the complete leave system file structure as of the current implementation. All files are essential for the proper functioning of the leave management system.*
