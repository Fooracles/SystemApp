<?php
/**
 * Database Schema Definitions
 * 
 * This file contains definitions for all database tables used in the application.
 * Each table is defined with its columns, primary keys, indexes, and foreign keys.
 */

// Define all tables and their schemas
$DB_TABLES = [
    // Departments table
    'departments' => [
        'sql' => "CREATE TABLE IF NOT EXISTS departments (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL UNIQUE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )"
    ],
    
    // Users table
    'users' => [
        'sql' => "CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(50) NOT NULL UNIQUE,
            name VARCHAR(100) NOT NULL,
            email VARCHAR(100) NOT NULL UNIQUE,
            password VARCHAR(255) NOT NULL,
            department_id INT,
            user_type ENUM('admin', 'manager', 'doer', 'client') NOT NULL,
            manager VARCHAR(255) NULL,
            manager_id INT NULL,
            joining_date DATE NULL,
            date_of_birth DATE NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE SET NULL
        )"
    ],
    
    // Tasks table
    'tasks' => [
        'sql' => "CREATE TABLE IF NOT EXISTS tasks (
            id INT AUTO_INCREMENT PRIMARY KEY,
            unique_id VARCHAR(20) NOT NULL UNIQUE,
            description TEXT NOT NULL,
            planned_date DATE NOT NULL,
            planned_time TIME NOT NULL,
            actual_date DATE,
            actual_time TIME,
            duration DECIMAL(4,2) NOT NULL,
            duration_minutes INT NULL,
            doer_id INT,
            doer_name VARCHAR(100),
            manager_id INT,
            created_by INT,
            doer_manager_id INT,
            assigned_by INT NULL,
            department_id INT,
            status ENUM('pending', 'completed', 'shifted', 'not done', 'can not be done') DEFAULT 'pending',
            is_delayed BOOLEAN DEFAULT 0,
            delay_duration VARCHAR(50),
            shifted_count INT DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (doer_id) REFERENCES users(id) ON DELETE SET NULL,
            FOREIGN KEY (manager_id) REFERENCES users(id) ON DELETE SET NULL,
            FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
            FOREIGN KEY (doer_manager_id) REFERENCES users(id) ON DELETE SET NULL,
            FOREIGN KEY (assigned_by) REFERENCES users(id) ON DELETE SET NULL,
            FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE SET NULL
        )"
    ],
    
    // User Departments junction table
    'user_departments' => [
        'sql' => "CREATE TABLE IF NOT EXISTS user_departments (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            department_id INT NOT NULL,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE CASCADE,
            UNIQUE KEY (user_id, department_id)
        )"
    ],
    
    // Forms table
    'forms' => [
        'sql' => "CREATE TABLE IF NOT EXISTS forms (
            id INT AUTO_INCREMENT PRIMARY KEY,
            form_name VARCHAR(255) NOT NULL,
            form_url VARCHAR(1000) NOT NULL,
            visible_for ENUM('admin','manager','doer','all') NOT NULL DEFAULT 'all',
            created_by INT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
    ],

    // Form to User mapping table
    'form_user_map' => [
        'sql' => "CREATE TABLE IF NOT EXISTS form_user_map (
            id INT AUTO_INCREMENT PRIMARY KEY,
            form_id INT NOT NULL,
            user_id INT NOT NULL,
            UNIQUE KEY uniq_form_user (form_id, user_id),
            FOREIGN KEY (form_id) REFERENCES forms(id) ON DELETE CASCADE,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
    ],

    // FMS Sheets table
    'fms_sheets' => [
        'sql' => "CREATE TABLE IF NOT EXISTS fms_sheets (
            id INT AUTO_INCREMENT PRIMARY KEY,
            sheet_id VARCHAR(255) NOT NULL,
            tab_name VARCHAR(255) NOT NULL,
            label VARCHAR(255) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY unique_sheet_tab (sheet_id, tab_name)
        )"
    ],
    
    // FMS Sheet Metadata table
    'fms_sheet_metadata' => [
        'sql' => "CREATE TABLE IF NOT EXISTS fms_sheet_metadata (
            id INT AUTO_INCREMENT PRIMARY KEY,
            sheet_id VARCHAR(255) NOT NULL,
            last_t1_value VARCHAR(255),
            last_u1_value VARCHAR(255),
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY unique_sheet (sheet_id)
        )"
    ],
    
    // FMS Tasks table
    'fms_tasks' => [
        'sql' => "CREATE TABLE IF NOT EXISTS fms_tasks (
            id INT AUTO_INCREMENT PRIMARY KEY,
            sheet_id VARCHAR(255) NOT NULL,
            unique_key VARCHAR(255),
            step_name VARCHAR(255),
            planned VARCHAR(255),
            actual VARCHAR(255),
            status VARCHAR(255),
            duration VARCHAR(255),
            doer_name VARCHAR(255),
            department VARCHAR(255),
            task_link VARCHAR(255),
            sheet_label VARCHAR(255),
            step_code VARCHAR(255),
            is_delayed TINYINT(1) DEFAULT 0,
            delay_duration VARCHAR(100) DEFAULT NULL,
            imported_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )"
    ],
    
    // FMS Sheet Sync table
    'fms_sheet_sync' => [
        'sql' => "CREATE TABLE IF NOT EXISTS fms_sheet_sync (
            id INT AUTO_INCREMENT PRIMARY KEY,
            sheet_id VARCHAR(255) NOT NULL,
            rows_count VARCHAR(255),      /* U2 - Current number of rows */
            actuals_count VARCHAR(255),   /* V2 - Current Number of Actuals */
            last_rows_count VARCHAR(255), /* W2 - Last updated Rows in System App */
            last_actuals_count VARCHAR(255), /* X2 - Last updated Actuals in System App */
            last_synced TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE(sheet_id)
        )"
    ],
    
    // Holidays table
    'holidays' => [
        'sql' => "CREATE TABLE IF NOT EXISTS holidays (
            id INT AUTO_INCREMENT PRIMARY KEY,
            holiday_date DATE NOT NULL,
            holiday_name VARCHAR(255) NOT NULL,
            created_by INT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
            UNIQUE KEY unique_holiday_date (holiday_date)
        )"
    ],
    
    // Checklist Subtasks table
    'checklist_subtasks' => [
        'sql' => "CREATE TABLE IF NOT EXISTS checklist_subtasks (
            id INT AUTO_INCREMENT PRIMARY KEY,
            task_code VARCHAR(20) DEFAULT NULL,
            assignee VARCHAR(100) DEFAULT NULL,
            department VARCHAR(100) DEFAULT NULL,
            task_description TEXT,
            frequency VARCHAR(20) DEFAULT NULL,
            task_date DATE DEFAULT NULL,
            duration TIME DEFAULT NULL,
            status VARCHAR(50) DEFAULT 'pending',
            actual_date DATE DEFAULT NULL,
            actual_time TIME DEFAULT NULL,
            is_delayed TINYINT(1) DEFAULT 0,
            delay_duration VARCHAR(50) DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
    ],
    
    // Password Reset Requests table
    'password_reset_requests' => [
        'sql' => "CREATE TABLE IF NOT EXISTS password_reset_requests (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(50) NOT NULL,
            email VARCHAR(100) NOT NULL,
            reset_code VARCHAR(255) NOT NULL,
            status ENUM('pending', 'approved', 'rejected', 'used') DEFAULT 'pending',
            requested_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            approved_at TIMESTAMP NULL,
            rejected_at TIMESTAMP NULL,
            used_at TIMESTAMP NULL,
            approved_by INT NULL,
            rejected_by INT NULL,
            FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL,
            FOREIGN KEY (rejected_by) REFERENCES users(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
    ],
    
    // Leave request table (renamed from leave_requests_cache)
    'Leave_request' => [
        'sql' => "CREATE TABLE IF NOT EXISTS Leave_request (
            id INT AUTO_INCREMENT PRIMARY KEY,
            unique_service_no VARCHAR(64) UNIQUE,
            employee_name VARCHAR(128),
            employee_email VARCHAR(256),
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
            status ENUM('PENDING','Approve','Reject','Cancelled') DEFAULT 'PENDING',
            sheet_timestamp DATETIME,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX (employee_email),
            INDEX (manager_email),
            INDEX (status),
            INDEX (start_date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
    ],
    
    // Leave Status Actions table
    'leave_status_actions' => [
        'sql' => "CREATE TABLE IF NOT EXISTS leave_status_actions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            unique_service_no VARCHAR(64),
            employee_name VARCHAR(128),
            manager_email VARCHAR(256),
            actor_email VARCHAR(256),
            action ENUM('Approve','Reject') NOT NULL,
            note TEXT,
            sheet_row_id VARCHAR(32) NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX (unique_service_no),
            INDEX (manager_email),
            INDEX (actor_email)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
    ],
    
    // User Notes table
    'user_notes' => [
        'sql' => "CREATE TABLE IF NOT EXISTS user_notes (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            title VARCHAR(255) NOT NULL,
            content TEXT NOT NULL,
            is_important BOOLEAN DEFAULT 0,
            is_completed BOOLEAN DEFAULT 0,
            reminder_date DATETIME NULL,
            reminder_sent BOOLEAN DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            INDEX (user_id),
            INDEX (reminder_date),
            INDEX (is_important),
            INDEX (is_completed)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
    ],
    
    // Note Sharing table
    'note_sharing' => [
        'sql' => "CREATE TABLE IF NOT EXISTS note_sharing (
            id INT AUTO_INCREMENT PRIMARY KEY,
            note_id INT NOT NULL,
            shared_with_user_id INT NOT NULL,
            permission ENUM('view', 'comment', 'edit') NOT NULL DEFAULT 'view',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (note_id) REFERENCES user_notes(id) ON DELETE CASCADE,
            FOREIGN KEY (shared_with_user_id) REFERENCES users(id) ON DELETE CASCADE,
            UNIQUE KEY unique_note_user (note_id, shared_with_user_id),
            INDEX (note_id),
            INDEX (shared_with_user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
    ],
    
    // Note Comments table
    'note_comments' => [
        'sql' => "CREATE TABLE IF NOT EXISTS note_comments (
            id INT AUTO_INCREMENT PRIMARY KEY,
            note_id INT NOT NULL,
            user_id INT NOT NULL,
            comment TEXT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (note_id) REFERENCES user_notes(id) ON DELETE CASCADE,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            INDEX (note_id),
            INDEX (user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
    ],
    
    // Personal URLs table (user's personal bookmarks)
    'personal_urls' => [
        'sql' => "CREATE TABLE IF NOT EXISTS personal_urls (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            title VARCHAR(255) NOT NULL,
            url VARCHAR(500) NOT NULL,
            description TEXT NULL,
            category VARCHAR(100) NULL,
            sort_order INT DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            INDEX (user_id),
            INDEX (category),
            INDEX (sort_order),
            INDEX (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    ],
    
    // Admin URLs table (global links)
    'admin_urls' => [
        'sql' => "CREATE TABLE IF NOT EXISTS admin_urls (
            id INT AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(255) NOT NULL,
            url VARCHAR(1000) NOT NULL,
            description TEXT NULL,
            category VARCHAR(100) NULL,
            visible_for ENUM('admin','manager','doer','all') NOT NULL DEFAULT 'all',
            created_by INT NOT NULL,
            sort_order INT DEFAULT 0,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE,
            INDEX (visible_for),
            INDEX (category),
            INDEX (created_by),
            INDEX (sort_order),
            INDEX (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    ],
    
    // Personal URL Sharing table
    'personal_url_sharing' => [
        'sql' => "CREATE TABLE IF NOT EXISTS personal_url_sharing (
            id INT AUTO_INCREMENT PRIMARY KEY,
            url_id INT NOT NULL,
            shared_with_user_id INT NOT NULL,
            permission ENUM('view','comment','edit') DEFAULT 'view',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (url_id) REFERENCES personal_urls(id) ON DELETE CASCADE,
            FOREIGN KEY (shared_with_user_id) REFERENCES users(id) ON DELETE CASCADE,
            UNIQUE KEY unique_share (url_id, shared_with_user_id),
            INDEX (url_id),
            INDEX (shared_with_user_id),
            INDEX (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    ],
    
    // Admin URL Sharing table
    'admin_url_sharing' => [
        'sql' => "CREATE TABLE IF NOT EXISTS admin_url_sharing (
            id INT AUTO_INCREMENT PRIMARY KEY,
            url_id INT NOT NULL,
            shared_with_user_id INT NOT NULL,
            permission ENUM('view','comment','edit') DEFAULT 'view',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (url_id) REFERENCES admin_urls(id) ON DELETE CASCADE,
            FOREIGN KEY (shared_with_user_id) REFERENCES users(id) ON DELETE CASCADE,
            UNIQUE KEY unique_share (url_id, shared_with_user_id),
            INDEX (url_id),
            INDEX (shared_with_user_id),
            INDEX (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    ],
    
    // RQC Scores table (synced from Google Sheets)
    'rqc_scores' => [
        'sql' => "CREATE TABLE IF NOT EXISTS rqc_scores (
            id INT(11) NOT NULL AUTO_INCREMENT,
            timestamp DATETIME NOT NULL,
            name VARCHAR(255) NOT NULL,
            score VARCHAR(50) NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            INDEX (timestamp),
            INDEX (name),
            INDEX (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
    ],
    
    // Meeting Requests table
    'meeting_requests' => [
        'sql' => "CREATE TABLE IF NOT EXISTS meeting_requests (
            id INT AUTO_INCREMENT PRIMARY KEY,
            doer_name VARCHAR(255) NOT NULL,
            doer_email VARCHAR(255) NOT NULL,
            reason TEXT NOT NULL,
            duration VARCHAR(20) NOT NULL,
            urgency VARCHAR(50) NULL,
            preferred_date DATE NULL,
            preferred_time TIME NULL,
            status ENUM('Pending', 'Approved', 'Scheduled', 'Completed') DEFAULT 'Pending',
            scheduled_date DATETIME NULL,
            schedule_comment TEXT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX (doer_email),
            INDEX (status),
            INDEX (scheduled_date),
            INDEX (preferred_date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
    ],
    
    // Notifications table
    'notifications' => [
        'sql' => "CREATE TABLE IF NOT EXISTS notifications (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            type ENUM('task_delay', 'meeting_request', 'meeting_approved', 'meeting_rescheduled', 'day_special', 'notes_reminder', 'leave_request', 'leave_approved', 'leave_rejected') NOT NULL,
            title VARCHAR(255) NOT NULL,
            message TEXT NOT NULL,
            related_id VARCHAR(255) NULL COMMENT 'ID of related record (task_id, meeting_id, leave_id, note_id, etc.) - Can be INT or VARCHAR',
            related_type VARCHAR(50) NULL COMMENT 'Type of related record (task, meeting, leave, note, etc.)',
            is_read BOOLEAN DEFAULT 0,
            action_required BOOLEAN DEFAULT 0 COMMENT 'Whether notification requires user action',
            action_data TEXT NULL COMMENT 'JSON data for action buttons (approve, reject, reschedule, etc.)',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            read_at DATETIME NULL,
            INDEX (user_id),
            INDEX (type),
            INDEX (is_read),
            INDEX (created_at),
            INDEX (action_required),
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
    ],
    
    // User Sessions table
    'user_sessions' => [
        'sql' => "CREATE TABLE IF NOT EXISTS user_sessions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(50) NOT NULL COMMENT 'Username (not user_id)',
            login_time DATETIME NOT NULL,
            logout_time DATETIME NULL,
            duration_seconds INT NULL COMMENT 'Total session duration in seconds',
            ip_address VARCHAR(45) NOT NULL COMMENT 'IPv4 or IPv6 address',
            device_info TEXT NULL COMMENT 'Device and browser information',
            logout_reason VARCHAR(50) NULL COMMENT 'manual, auto, admin_forced, etc.',
            is_active BOOLEAN DEFAULT 1 COMMENT 'Whether session is currently active',
            INDEX (username),
            INDEX (login_time),
            INDEX (logout_time),
            INDEX (is_active),
            INDEX (ip_address)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    ],
    
    // Reports table
    'reports' => [
        'sql' => "CREATE TABLE IF NOT EXISTS reports (
            id INT AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(255) NOT NULL,
            project_name VARCHAR(255) NOT NULL,
            file_path VARCHAR(500) NOT NULL,
            file_name VARCHAR(255) NOT NULL,
            file_type VARCHAR(100) NOT NULL,
            file_size INT NOT NULL COMMENT 'File size in bytes',
            uploaded_by INT NOT NULL COMMENT 'User ID of the manager who uploaded',
            uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE CASCADE,
            INDEX (uploaded_by),
            INDEX (uploaded_at),
            INDEX (project_name)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    ],
    
    // Updates table
    'updates' => [
        'sql' => "CREATE TABLE IF NOT EXISTS updates (
            id INT AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(255) NOT NULL DEFAULT '' COMMENT 'Title of the update',
            content TEXT NOT NULL,
            attachment_path VARCHAR(500) NULL COMMENT 'Path to uploaded attachment file',
            attachment_name VARCHAR(255) NULL COMMENT 'Original name of the attachment',
            voice_recording_path VARCHAR(500) NULL COMMENT 'Path to voice recording file',
            created_by INT NOT NULL COMMENT 'User ID of the admin/manager who created',
            target_client_id INT NULL COMMENT 'Target client user ID (for admin/manager to send updates to specific clients)',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (target_client_id) REFERENCES users(id) ON DELETE SET NULL,
            INDEX (created_by),
            INDEX (target_client_id),
            INDEX (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    ]
];

// Table creation order to handle dependencies
$TABLE_CREATION_ORDER = [
    'departments',
    'users',
    'tasks',
    'user_departments',
    'fms_sheets',
    'fms_sheet_metadata',
    'fms_tasks',
    'fms_sheet_sync',
    'holidays',
    'checklist_subtasks',
    'password_reset_requests',
    'Leave_request',
    'leave_status_actions',
    'forms',
    'form_user_map',
    'user_notes',
    'note_sharing',
    'note_comments',
    'personal_urls',
    'admin_urls',
    'personal_url_sharing',
    'admin_url_sharing',
    'rqc_scores',
    'meeting_requests',
    'notifications',
    'user_sessions',
    'reports',
    'updates'
];

/**
 * Create all database tables if they don't exist
 * 
 * @param mysqli $conn Database connection
 * @param bool $verbose Whether to output creation status
 * @return bool True if all tables created successfully, false otherwise
 */
function createDatabaseTables($conn, $verbose = false) {
    global $DB_TABLES, $TABLE_CREATION_ORDER;
    
    if (!$conn) {
        if ($verbose) echo "Error: No database connection available\n";
        return false;
    }
    
    $success = true;
    $created_count = 0;
    $existing_count = 0;
    $error_count = 0;
    
    if ($verbose) {
        echo "<div style='background: #f8f9fa; padding: 15px; border-radius: 5px; margin: 10px 0;'>";
        echo "<h4>Database Table Creation Status</h4>";
    }
    
    // Create tables in the specified order to handle dependencies
    foreach ($TABLE_CREATION_ORDER as $table_name) {
        if (!isset($DB_TABLES[$table_name])) {
            if ($verbose) echo "<p style='color: orange;'>⚠️ Table '$table_name' not defined in schema</p>";
            continue;
        }
        
        $sql = $DB_TABLES[$table_name]['sql'];
        
        // Check if table already exists
        $check_sql = "SHOW TABLES LIKE '$table_name'";
        $result = mysqli_query($conn, $check_sql);
        
        if (mysqli_num_rows($result) > 0) {
            $existing_count++;
            if ($verbose) echo "<p style='color: #6c757d;'>✓ Table '$table_name' already exists</p>";
        } else {
            // Create the table
            if (mysqli_query($conn, $sql)) {
                $created_count++;
                if ($verbose) echo "<p style='color: green;'>✓ Created table '$table_name'</p>";
            } else {
                $error_count++;
                $error = mysqli_error($conn);
                if ($verbose) echo "<p style='color: red;'>✗ Failed to create table '$table_name': $error</p>";
                $success = false;
            }
        }
    }
    
    if ($verbose) {
        echo "<hr>";
        echo "<p><strong>Summary:</strong></p>";
        echo "<p>✓ Created: $created_count tables</p>";
        echo "<p>📋 Existing: $existing_count tables</p>";
        if ($error_count > 0) {
            echo "<p style='color: red;'>✗ Errors: $error_count tables</p>";
        }
        echo "</div>";
    }
    
    return $success;
}

/**
 * Check if all required tables exist
 * 
 * @param mysqli $conn Database connection
 * @return array Array with 'exists' (bool) and 'missing_tables' (array)
 */
function checkDatabaseTables($conn) {
    global $TABLE_CREATION_ORDER;
    
    $missing_tables = [];
    
    foreach ($TABLE_CREATION_ORDER as $table_name) {
        $check_sql = "SHOW TABLES LIKE '$table_name'";
        $result = mysqli_query($conn, $check_sql);
        
        if (mysqli_num_rows($result) == 0) {
            $missing_tables[] = $table_name;
        }
    }
    
    return [
        'exists' => empty($missing_tables),
        'missing_tables' => $missing_tables
    ];
}
?> 