<?php
/**
 * Simple Standalone Script to Create Notifications Table
 * 
 * This is a simpler version that uses your existing database connection.
 * Just include your database connection file and run this script.
 * 
 * Usage:
 * 1. Include your database connection at the top (e.g., require_once 'config.php')
 * 2. Make sure $conn variable contains your mysqli connection
 * 3. Run: php create_notifications_table_simple.php
 */

// ============================================
// INCLUDE YOUR DATABASE CONNECTION
// ============================================
// Uncomment and modify the line below to include your database connection
// require_once 'path/to/your/config.php';

// If you don't have a config file, uncomment and configure the connection below:
/*
$db_host = 'localhost';
$db_username = 'root';
$db_password = '';
$db_name = 'your_database_name';
$conn = mysqli_connect($db_host, $db_username, $db_password, $db_name);
if (!$conn) {
    die("Database connection failed: " . mysqli_connect_error());
}
*/

// Check if $conn exists
if (!isset($conn) || !$conn) {
    die("Error: Database connection not found. Please configure the connection.\n");
}

echo "Creating notifications table...\n\n";

// Check if users table exists (for foreign key)
$users_check = mysqli_query($conn, "SHOW TABLES LIKE 'users'");
$has_users_table = mysqli_num_rows($users_check) > 0;

// Check if table exists
$table_check = mysqli_query($conn, "SHOW TABLES LIKE 'notifications'");

if (mysqli_num_rows($table_check) == 0) {
    // Create table
    $foreign_key = $has_users_table ? ", FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE" : "";
    
    $sql = "CREATE TABLE notifications (
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
        INDEX idx_user_id (user_id),
        INDEX idx_type (type),
        INDEX idx_is_read (is_read),
        INDEX idx_created_at (created_at),
        INDEX idx_action_required (action_required)
        $foreign_key
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";
    
    if (mysqli_query($conn, $sql)) {
        echo "✓ Notifications table created successfully!\n";
    } else {
        die("✗ Error: " . mysqli_error($conn) . "\n");
    }
} else {
    echo "Table already exists. Checking for missing columns...\n";
    
    // Add missing columns
    $columns = [
        'related_id' => "VARCHAR(255) NULL COMMENT 'ID of related record'",
        'related_type' => "VARCHAR(50) NULL COMMENT 'Type of related record'",
        'action_required' => "BOOLEAN DEFAULT 0 COMMENT 'Whether notification requires user action'",
        'action_data' => "TEXT NULL COMMENT 'JSON data for action buttons'",
        'read_at' => "DATETIME NULL"
    ];
    
    foreach ($columns as $col => $def) {
        $check = mysqli_query($conn, "SHOW COLUMNS FROM notifications LIKE '$col'");
        if (mysqli_num_rows($check) == 0) {
            $pos = $col === 'related_id' ? 'AFTER message' : 
                   ($col === 'related_type' ? 'AFTER related_id' : 
                   ($col === 'action_required' ? 'AFTER is_read' : 
                   ($col === 'action_data' ? 'AFTER action_required' : 'AFTER created_at')));
            mysqli_query($conn, "ALTER TABLE notifications ADD COLUMN $col $def $pos");
            echo "✓ Added column: $col\n";
        }
    }
    
    echo "✓ Table is ready!\n";
}

echo "\nDone!\n";
?>

