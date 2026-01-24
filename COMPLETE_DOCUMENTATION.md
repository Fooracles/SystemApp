# FMS Leave Management System - Complete Documentation

## 📋 **Overview**
This document provides comprehensive documentation for the FMS Leave Management System, including implementation details, deployment instructions, and troubleshooting guides.

---

## 🗂️ **System Architecture**

### **Core Components**
- **Frontend**: PHP-based web interface with JavaScript
- **Backend**: PHP with MySQL database
- **Integration**: Google Sheets API for data synchronization
- **Authentication**: Session-based user management

### **Key Features**
- Leave request management
- Google Sheets synchronization
- User role management (Admin, Manager, Doer)
- Real-time notifications
- Holiday management
- Task delegation system

---

## 🗄️ **Database Schema**

### **Main Tables**

#### **Leave_request Table**
```sql
CREATE TABLE Leave_request (
    id INT AUTO_INCREMENT PRIMARY KEY,
    unique_service_no VARCHAR(64) UNIQUE,
    employee_name VARCHAR(128),
    manager_name VARCHAR(128),
    manager_email VARCHAR(256),
    department VARCHAR(128),
    leave_type VARCHAR(64),
    duration VARCHAR(64),
    start_date DATE,
    end_date DATE NULL,
    reason TEXT,
    leave_count VARCHAR(16),
    file_url TEXT,
    status ENUM('PENDING', 'Approve', 'Reject', 'Cancelled'),
    sheet_timestamp DATETIME,
    created_at DATETIME,
    updated_at DATETIME,
    INDEX (employee_email),
    INDEX (manager_email),
    INDEX (status),
    INDEX (start_date)
);
```

#### **leave_status_actions Table**
```sql
CREATE TABLE leave_status_actions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    unique_service_no VARCHAR(64),
    action VARCHAR(50),
    performed_by VARCHAR(128),
    timestamp DATETIME,
    notes TEXT
);
```

---

## 📁 **File Structure**

### **Core Application Files**
```
├── index.php                          # Application entry point
├── login.php                          # User authentication
├── logout.php                         # User logout
└── sw.js                             # Service Worker (PWA)
```

### **Configuration & Database**
```
includes/
├── config.php                        # Database configuration
├── db_schema.php                     # Database table definitions
├── db_functions.php                  # Database utility functions
├── functions.php                     # General utility functions
├── google_sheets_client.php          # Google Sheets API client
├── header.php                        # Common header template
├── footer.php                        # Common footer template
└── sidebar.php                       # Navigation sidebar
```

### **Leave System Pages**
```
pages/
├── leave_request.php                 # Main leave request management
├── holiday_list.php                  # Holiday management
└── profile.php                       # User profile management
```

### **AJAX Endpoints**
```
ajax/
├── leave_auto_sync.php               # Manual leave data sync
├── leave_fetch_pending.php           # Fetch pending requests
├── leave_fetch_totals.php            # Fetch total requests
├── leave_metrics.php                 # Fetch leave statistics
├── leave_status_action.php           # Process leave actions
├── holiday_handler.php               # Holiday management
└── get_notifications.php             # Notification system
```

### **Assets**
```
assets/
├── css/
│   ├── style.css                     # Main stylesheet
│   ├── leave_request.css             # Leave-specific styles
│   └── doer_dashboard.css            # Dashboard styles
├── js/
│   ├── script.js                     # Main JavaScript
│   ├── leave_request.js              # Leave management JS
│   └── table-sorter.js               # Table sorting
└── images/                           # Image assets
```

---

## 🚀 **Deployment Guide**

### **Prerequisites**
- PHP 7.4+ with MySQL extension
- MySQL 5.7+ or MariaDB 10.3+
- Web server (Apache/Nginx)
- Google Sheets API credentials

### **Installation Steps**

1. **Upload Files**
   ```bash
   # Upload all project files to web server
   # Ensure proper file permissions (755 for directories, 644 for files)
   ```

2. **Database Setup**
   ```bash
   # Update database credentials in includes/config.php
   # Run database schema creation scripts
   ```

3. **Google Sheets Integration**
   ```bash
   # Upload credentials.json file
   # Configure Google Sheets API settings
   ```

4. **Permissions**
   ```bash
   # Set proper permissions for logs directory
   chmod 755 logs/
   chmod 644 logs/*.log
   ```

### **Configuration**

#### **Database Configuration**
```php
// includes/config.php
define('DB_SERVER', 'localhost');
define('DB_USERNAME', 'your_username');
define('DB_PASSWORD', 'your_password');
define('DB_NAME', 'your_database');
```

#### **Google Sheets Configuration**
```php
define('GOOGLE_SA_EMAIL', 'your-service-account@project.iam.gserviceaccount.com');
define('GOOGLE_SA_JSON_PATH', __DIR__ . '/../credentials.json');
define('LEAVE_SHEET_ID', 'your-google-sheet-id');
```

---

## 🔧 **API Endpoints**

### **Leave Management**
- `POST /ajax/leave_auto_sync.php` - Manual sync with Google Sheets
- `GET /ajax/leave_fetch_pending.php` - Fetch pending leave requests
- `GET /ajax/leave_fetch_totals.php` - Fetch total leave statistics
- `POST /ajax/leave_status_action.php` - Process leave approval/rejection

### **System Management**
- `GET /api/check.php` - System health check
- `GET /api/debug.php` - Debug information (development only)

---

## 🛠️ **Troubleshooting**

### **Common Issues**

#### **Database Connection Issues**
```bash
# Check database credentials
# Verify MySQL service is running
# Test connection with mysqli_connect()
```

#### **Google Sheets Sync Issues**
```bash
# Verify credentials.json file exists and is valid
# Check Google Sheets API permissions
# Review logs/leave_sync.log for errors
```

#### **Authentication Problems**
```bash
# Check session configuration
# Verify user table structure
# Review login.php for errors
```

### **Log Files**
- `logs/leave_sync.log` - Leave synchronization logs
- `logs/cron_sync.log` - Automated sync logs
- `logs/ajax_errors.log` - AJAX error logs
- `logs/db_operations.log` - Database operation logs

---

## 📊 **Performance Optimization**

### **Database Indexes**
```sql
-- Performance indexes for Leave_request table
CREATE INDEX idx_employee_email ON Leave_request(employee_email);
CREATE INDEX idx_manager_email ON Leave_request(manager_email);
CREATE INDEX idx_status ON Leave_request(status);
CREATE INDEX idx_start_date ON Leave_request(start_date);
```

### **Caching Strategy**
- Session-based caching for user data
- Database query result caching
- Static asset caching

---

## 🔒 **Security Considerations**

### **Authentication**
- Session-based authentication
- Role-based access control
- Password hashing with PHP password_hash()

### **Data Protection**
- Prepared statements for all database queries
- Input validation and sanitization
- CSRF protection for forms

---

## 📈 **Monitoring & Maintenance**

### **Regular Maintenance**
- Monitor log files for errors
- Clean up old log entries
- Update Google Sheets API credentials
- Database optimization and cleanup

### **Performance Monitoring**
- Database query performance
- Page load times
- Memory usage monitoring
- Error rate tracking

---

## 📞 **Support & Updates**

### **Version Information**
- Current Version: FMS-4.31
- Last Updated: 2025-01-27
- PHP Version: 7.4+
- MySQL Version: 5.7+

### **Documentation Updates**
This documentation is maintained alongside the codebase. For the most current information, refer to the project repository.

---

*This documentation covers the complete FMS Leave Management System implementation, deployment, and maintenance procedures.*
